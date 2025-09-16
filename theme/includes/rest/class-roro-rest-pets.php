<?php
// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * REST endpoints for managing user pets.
 */
class Roro_REST_Pets {
    /**
     * Namespace for the API.
     */
    const NS = 'roro/v1';

    /**
     * Register routes.
     */
    public static function register() {
        // List and create
        register_rest_route( self::NS, '/pets', [
            [
                'methods'             => 'GET',
                'callback'            => [ __CLASS__, 'list' ],
                'permission_callback' => function() { return is_user_logged_in(); },
            ],
            [
                'methods'             => 'POST',
                'callback'            => [ __CLASS__, 'create' ],
                'permission_callback' => function() { return is_user_logged_in(); },
            ],
        ] );
        // Update and delete
        register_rest_route( self::NS, '/pets/(?P<id>\d+)', [
            [
                'methods'             => 'PUT',
                'callback'            => [ __CLASS__, 'update' ],
                'permission_callback' => function() { return is_user_logged_in(); },
            ],
            [
                'methods'             => 'DELETE',
                'callback'            => [ __CLASS__, 'delete' ],
                'permission_callback' => function() { return is_user_logged_in(); },
            ],
        ] );
    }

    /**
     * Helper to get the current user's customer_id.
     *
     * @return int|null
     */
    private static function customer_id() {
        global $wpdb;
        $uid = get_current_user_id();
        return $wpdb->get_var( $wpdb->prepare( 'SELECT customer_id FROM RORO_USER_LINK_WP WHERE wp_user_id=%d', $uid ) );
    }

    /**
     * List all active pets for the current user.
     *
     * @param WP_REST_Request $req
     * @return WP_REST_Response|WP_Error
     */
    public static function list( $req ) {
        global $wpdb;
        $cid = self::customer_id();
        if ( ! $cid ) {
            return new WP_Error( 'not_found', '顧客がありません', [ 'status' => 404 ] );
        }
        $rows = $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM RORO_PET WHERE customer_id=%d AND (is_active=1 OR is_active IS NULL)', $cid ), ARRAY_A );
        return Roro_REST_Utils::respond( $rows );
    }

    /**
     * Create a new pet record for the current user.
     *
     * @param WP_REST_Request $req
     * @return WP_REST_Response|WP_Error
     */
    public static function create( $req ) {
        global $wpdb;
        $cid = self::customer_id();
        if ( ! $cid ) {
            return new WP_Error( 'not_found', '顧客がありません', [ 'status' => 404 ] );
        }
        $data = [
            'customer_id' => $cid,
            'name'        => Roro_REST_Utils::sanitize_text( $req[ 'name' ] ?? '' ),
            'species'     => Roro_REST_Utils::sanitize_text( $req[ 'species' ] ?? 'dog' ),
            'gender'      => Roro_REST_Utils::sanitize_text( $req[ 'gender' ] ?? '' ),
            'birthday'    => Roro_REST_Utils::sanitize_text( $req[ 'birthday' ] ?? '' ),
            'weight'      => Roro_REST_Utils::sanitize_text( $req[ 'weight' ] ?? '' ),
            'is_active'   => 1,
            'created_at'  => current_time( 'mysql' ),
        ];
        $wpdb->insert( 'RORO_PET', $data );
        return Roro_REST_Utils::respond( [ 'id' => $wpdb->insert_id ], 201 );
    }

    /**
     * Update a pet record belonging to the current user.
     *
     * @param WP_REST_Request $req
     * @return WP_REST_Response|WP_Error
     */
    public static function update( $req ) {
        global $wpdb;
        $cid = self::customer_id();
        $id  = intval( $req[ 'id' ] );
        // Ensure the pet belongs to the current customer
        $owner = $wpdb->get_var( $wpdb->prepare( 'SELECT customer_id FROM RORO_PET WHERE id=%d', $id ) );
        if ( $owner != $cid ) {
            return new WP_Error( 'forbidden', '権限がありません', [ 'status' => 403 ] );
        }
        $data = [
            'name'     => Roro_REST_Utils::sanitize_text( $req[ 'name' ] ?? '' ),
            'gender'   => Roro_REST_Utils::sanitize_text( $req[ 'gender' ] ?? '' ),
            'birthday' => Roro_REST_Utils::sanitize_text( $req[ 'birthday' ] ?? '' ),
            'weight'   => Roro_REST_Utils::sanitize_text( $req[ 'weight' ] ?? '' ),
            'updated_at' => current_time( 'mysql' ),
        ];
        $wpdb->update( 'RORO_PET', $data, [ 'id' => $id ] );
        return Roro_REST_Utils::respond( [ 'ok' => true ] );
    }

    /**
     * Delete (deactivate) a pet record.
     *
     * @param WP_REST_Request $req
     * @return WP_REST_Response|WP_Error
     */
    public static function delete( $req ) {
        global $wpdb;
        $cid = self::customer_id();
        $id  = intval( $req[ 'id' ] );
        $owner = $wpdb->get_var( $wpdb->prepare( 'SELECT customer_id FROM RORO_PET WHERE id=%d', $id ) );
        if ( $owner != $cid ) {
            return new WP_Error( 'forbidden', '権限がありません', [ 'status' => 403 ] );
        }
        // Soft-delete by setting is_active=0
        $wpdb->update( 'RORO_PET', [ 'is_active' => 0, 'updated_at' => current_time( 'mysql' ) ], [ 'id' => $id ] );
        return Roro_REST_Utils::respond( [ 'deleted' => true ] );
    }
}
