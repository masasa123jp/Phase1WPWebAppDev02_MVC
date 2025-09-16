<?php
/**
 * REST controller for owner‑scoped customer operations.  Provides
 * endpoints under `/wp-json/roro/me/customer` that allow a logged in
 * user to fetch and update their own customer profile.  To prevent
 * privilege escalation, all update operations require the
 * `edit_own_roro_profile` capability and the controller uses the
 * RORO_USER_LINK_WP table to resolve the relationship between
 * WordPress users and RORO customers.  Additional address fields
 * beyond prefecture/city are stored in user meta.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

require_once __DIR__ . '/../models/Roro_CustomerModel.php';

class Roro_MeCustomerController {
    /**
     * Register routes for the current user customer endpoints.
     */
    public function register_routes() {
        $namespace = 'roro/me';
        // GET /roro/me/customer
        register_rest_route( $namespace, '/customer', array(
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => array( $this, 'get_customer' ),
            'permission_callback' => array( $this, 'permissions_read' ),
        ) );
        // PUT /roro/me/customer
        register_rest_route( $namespace, '/customer', array(
            'methods'             => WP_REST_Server::EDITABLE,
            'callback'            => array( $this, 'update_customer' ),
            'permission_callback' => array( $this, 'permissions_edit' ),
        ) );
    }

    /**
     * Ensure the requester is logged in to read their profile.
     *
     * @return bool
     */
    public function permissions_read() {
        return is_user_logged_in();
    }

    /**
     * Check that the current user can edit their own profile.  Uses
     * the custom capability `edit_own_roro_profile` registered in
     * functions.php.
     *
     * @return bool
     */
    public function permissions_edit() {
        return current_user_can( 'edit_own_roro_profile' );
    }

    /**
     * Retrieve the current user's customer profile and return it in
     * a JSON friendly structure.  Additional contact fields stored in
     * user meta are included.
     *
     * @param WP_REST_Request $request
     * @return WP_REST_Response|WP_Error
     */
    public function get_customer( WP_REST_Request $request ) {
        $user_id = get_current_user_id();
        if ( ! $user_id ) {
            return new WP_Error( 'roro_not_logged_in', __( 'Not logged in', 'roro' ), array( 'status' => 403 ) );
        }
        $cust_model = new Roro_CustomerModel();
        $cust       = $cust_model->get_by_wp_user( $user_id );
        if ( ! $cust ) {
            return new WP_Error( 'roro_customer_not_found', __( 'Customer not found', 'roro' ), array( 'status' => 404 ) );
        }
        $user = get_userdata( $user_id );
        $data = array(
            'customer_id'   => (int) $cust->customer_id,
            'email'         => $user->user_email,
            'prefecture'    => $cust->prefecture,
            'city'          => $cust->city,
            'address_line1' => get_user_meta( $user_id, 'roro_address_line1', true ),
            'building'      => get_user_meta( $user_id, 'roro_building', true ),
            'phone'         => get_user_meta( $user_id, 'roro_phone', true ),
        );
        return rest_ensure_response( $data );
    }

    /**
     * Update the current user's customer profile.  Only whitelisted
     * fields are updated.  Prefecture and city are stored in the
     * customer table.  Additional fields are stored in user meta.  The
     * email address is updated on the WordPress user record.
     *
     * @param WP_REST_Request $request
     * @return WP_REST_Response|WP_Error
     */
    public function update_customer( WP_REST_Request $request ) {
        $user_id = get_current_user_id();
        if ( ! $user_id ) {
            return new WP_Error( 'roro_not_logged_in', __( 'Not logged in', 'roro' ), array( 'status' => 403 ) );
        }
        $cust_model = new Roro_CustomerModel();
        $cust       = $cust_model->get_by_wp_user( $user_id );
        if ( ! $cust ) {
            return new WP_Error( 'roro_customer_not_found', __( 'Customer not found', 'roro' ), array( 'status' => 404 ) );
        }
        $params = $request->get_json_params();
        if ( ! is_array( $params ) ) {
            return new WP_Error( 'roro_invalid_payload', __( 'Invalid payload', 'roro' ), array( 'status' => 400 ) );
        }
        // Update email address if provided and valid
        if ( isset( $params['email'] ) ) {
            $email = sanitize_email( $params['email'] );
            if ( ! empty( $email ) && filter_var( $email, FILTER_VALIDATE_EMAIL ) ) {
                $user_update = wp_update_user( array( 'ID' => $user_id, 'user_email' => $email ) );
                if ( is_wp_error( $user_update ) ) {
                    return $user_update;
                }
            }
        }
        // Prepare data for the customer table
        $cust_data = array();
        if ( array_key_exists( 'prefecture', $params ) ) {
            $cust_data['prefecture'] = sanitize_text_field( $params['prefecture'] );
        }
        if ( array_key_exists( 'city', $params ) ) {
            $cust_data['city'] = sanitize_text_field( $params['city'] );
        }
        if ( ! empty( $cust_data ) ) {
            $cust_model->update_by_pk( $cust->customer_id, $cust_data );
        }
        // Update address_line1 and building in user meta
        if ( array_key_exists( 'address_line1', $params ) ) {
            update_user_meta( $user_id, 'roro_address_line1', sanitize_text_field( $params['address_line1'] ) );
        }
        if ( array_key_exists( 'building', $params ) ) {
            update_user_meta( $user_id, 'roro_building', sanitize_text_field( $params['building'] ) );
        }
        // Update phone number in user meta
        if ( array_key_exists( 'phone', $params ) ) {
            update_user_meta( $user_id, 'roro_phone', sanitize_text_field( $params['phone'] ) );
        }
        // Return the updated profile
        return $this->get_customer( $request );
    }
}