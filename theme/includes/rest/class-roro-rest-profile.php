<?php
// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * REST endpoints for user profile management.
 */
class Roro_REST_Profile {
    /**
     * Namespace for the API.
     */
    const NS = 'roro/v1';

    /**
     * Register routes.
     */
    public static function register() {
        register_rest_route( self::NS, '/profile', [
            [
                'methods'             => 'GET',
                'callback'            => [ __CLASS__, 'get' ],
                'permission_callback' => function() {
                    return is_user_logged_in();
                },
            ],
            [
                'methods'             => 'POST',
                'callback'            => [ __CLASS__, 'create' ],
                'permission_callback' => function() {
                    return is_user_logged_in();
                },
            ],
            [
                'methods'             => 'PUT',
                'callback'            => [ __CLASS__, 'update' ],
                'permission_callback' => function() {
                    return is_user_logged_in();
                },
            ],
            [
                'methods'             => 'DELETE',
                'callback'            => [ __CLASS__, 'delete' ],
                'permission_callback' => function() {
                    return is_user_logged_in();
                },
            ],
        ] );
    }

    /**
     * Get the current user profile and address.
     *
     * @param WP_REST_Request $req
     * @return WP_REST_Response|WP_Error
     */
    public static function get( $req ) {
        $uid = get_current_user_id();
        if ( ! $uid ) {
            return new WP_Error( 'not_logged_in', 'ログインが必要です', [ 'status' => 401 ] );
        }
        global $wpdb;
        // Retrieve customer ID by link table
        $customer_id = $wpdb->get_var( $wpdb->prepare( 'SELECT customer_id FROM RORO_USER_LINK_WP WHERE wp_user_id=%d', $uid ) );
        if ( ! $customer_id ) {
            return Roro_REST_Utils::respond( [ 'customer' => null, 'address' => null ] );
        }
        $customer = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM RORO_CUSTOMER WHERE id=%d', $customer_id ), ARRAY_A );
        $address  = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM RORO_ADDRESS WHERE customer_id=%d', $customer_id ), ARRAY_A );
        return Roro_REST_Utils::respond( [ 'customer' => $customer, 'address' => $address ] );
    }

    /**
     * Create an initial profile record for the current user if absent.
     *
     * @param WP_REST_Request $req
     * @return WP_REST_Response|WP_Error
     */
    public static function create( $req ) {
        $uid = get_current_user_id();
        if ( ! $uid ) {
            return new WP_Error( 'not_logged_in', 'ログインが必要です', [ 'status' => 401 ] );
        }
        global $wpdb;
        $existing = $wpdb->get_var( $wpdb->prepare( 'SELECT customer_id FROM RORO_USER_LINK_WP WHERE wp_user_id=%d', $uid ) );
        if ( $existing ) {
            return new WP_Error( 'exists', '既に作成済みです', [ 'status' => 400 ] );
        }
        $email = wp_get_current_user()->user_email;
        $wpdb->insert( 'RORO_CUSTOMER', [ 'email' => $email, 'created_at' => current_time( 'mysql' ) ] );
        $cid = (int) $wpdb->insert_id;
        $wpdb->insert( 'RORO_USER_LINK_WP', [ 'wp_user_id' => $uid, 'customer_id' => $cid ] );
        return Roro_REST_Utils::respond( [ 'customer_id' => $cid ], 201 );
    }

    /**
     * Update the current user's customer and address records.
     *
     * @param WP_REST_Request $req
     * @return WP_REST_Response|WP_Error
     */
    public static function update( $req ) {
        $uid = get_current_user_id();
        if ( ! $uid ) {
            return new WP_Error( 'not_logged_in', 'ログインが必要です', [ 'status' => 401 ] );
        }
        global $wpdb;
        $cid = $wpdb->get_var( $wpdb->prepare( 'SELECT customer_id FROM RORO_USER_LINK_WP WHERE wp_user_id=%d', $uid ) );
        if ( ! $cid ) {
            return new WP_Error( 'not_found', '顧客レコードがありません', [ 'status' => 404 ] );
        }
        $name_raw  = $req['name'] ?? '';
        $phone_raw = $req['phone'] ?? '';
        $postal_raw = $req['postal_code'] ?? '';
        $pref_raw  = $req['prefecture'] ?? '';
        $city_raw  = $req['city'] ?? '';
        $addr_line_raw = $req['address_line'] ?? '';
        // Sanitize inputs
        $name  = Roro_REST_Utils::sanitize_text( $name_raw );
        $phone = Roro_REST_Utils::sanitize_text( $phone_raw );
        $postal= Roro_REST_Utils::sanitize_text( $postal_raw );
        $pref  = Roro_REST_Utils::sanitize_text( $pref_raw );
        $city  = Roro_REST_Utils::sanitize_text( $city_raw );
        $addr_line = Roro_REST_Utils::sanitize_text( $addr_line_raw );
        // Validate postal code (e.g. 123-4567 or 1234567)
        if ( $postal && ! preg_match( '/^\d{3}-?\d{4}$/', $postal ) ) {
            return new WP_Error( 'invalid_postal', '郵便番号の形式が正しくありません', [ 'status' => 400 ] );
        }
        // Validate phone number (10〜15 digits, can include + and hyphens)
        if ( $phone && ! preg_match( '/^[0-9\-\+]{10,15}$/', $phone ) ) {
            return new WP_Error( 'invalid_phone', '電話番号の形式が正しくありません', [ 'status' => 400 ] );
        }
        // Prefecture should not be empty if provided
        if ( $pref && mb_strlen( $pref ) < 1 ) {
            return new WP_Error( 'invalid_prefecture', '都道府県名が無効です', [ 'status' => 400 ] );
        }
        // Update customer
        $wpdb->update( 'RORO_CUSTOMER', [ 'name' => $name, 'phone' => $phone, 'updated_at' => current_time( 'mysql' ) ], [ 'id' => $cid ] );
        // Upsert address
        $row = $wpdb->get_var( $wpdb->prepare( 'SELECT id FROM RORO_ADDRESS WHERE customer_id=%d', $cid ) );
        $addr = [
            'customer_id'  => $cid,
            'postal_code'  => $postal,
            'prefecture'   => $pref,
            'city'         => $city,
            'address_line' => $addr_line,
            'updated_at'   => current_time( 'mysql' ),
        ];
        if ( $row ) {
            $wpdb->update( 'RORO_ADDRESS', $addr, [ 'customer_id' => $cid ] );
        } else {
            $addr['created_at'] = current_time( 'mysql' );
            $wpdb->insert( 'RORO_ADDRESS', $addr );
        }
        return Roro_REST_Utils::respond( [ 'ok' => true ] );
    }

    /**
     * Delete the current user (and trigger linked deletion via hook).
     *
     * @param WP_REST_Request $req
     * @return WP_REST_Response|WP_Error
     */
    public static function delete( $req ) {
        $uid = get_current_user_id();
        if ( ! $uid ) {
            return new WP_Error( 'not_logged_in', 'ログインが必要です', [ 'status' => 401 ] );
        }
        require_once ABSPATH . 'wp-admin/includes/user.php';
        wp_delete_user( $uid );
        return Roro_REST_Utils::respond( [ 'deleted' => true ] );
    }
}
