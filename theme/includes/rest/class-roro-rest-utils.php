<?php
// Prevent direct access
if ( ! defined('ABSPATH') ) {
    exit;
}

/**
 * Utility functions for Roro REST endpoints.
 */
class Roro_REST_Utils {
    /**
     * Ensure the current user is logged in.  Returns the user ID or a WP_Error.
     *
     * @return int|WP_Error
     */
    public static function current_user_id_or_error() {
        $uid = get_current_user_id();
        if ( ! $uid ) {
            return new WP_Error( 'not_logged_in', 'ログインが必要です', [ 'status' => 401 ] );
        }
        return $uid;
    }

    /**
     * Verify that the current user has the given capability.  Returns true or WP_Error.
     *
     * @param string $cap Capability
     * @return true|WP_Error
     */
    public static function require_cap( $cap ) {
        if ( ! current_user_can( $cap ) ) {
            return new WP_Error( 'forbidden', '権限が不足しています', [ 'status' => 403 ] );
        }
        return true;
    }

    /**
     * Sanitize a string field using sanitize_text_field.
     *
     * @param string $v
     * @return string
     */
    public static function sanitize_text( $v ) {
        return sanitize_text_field( $v ?? '' );
    }

    /**
     * Create a REST response with a given status code.
     *
     * @param mixed $data Data to return
     * @param int   $status HTTP status code
     * @return WP_REST_Response
     */
    public static function respond( $data, $status = 200 ) {
        return new WP_REST_Response( $data, $status );
    }
}
