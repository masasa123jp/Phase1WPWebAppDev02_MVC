<?php
/**
 * Model for the RORO_CUSTOMER table.  Provides helper methods for
 * retrieving and updating customer records and for resolving a
 * customer based on the currently logged in WordPress user via
 * the RORO_USER_LINK_WP table.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

require_once __DIR__ . '/Roro_BaseModel.php';

class Roro_CustomerModel extends Roro_BaseModel {
    public function __construct() {
        parent::__construct( 'RORO_CUSTOMER', 'customer_id' );
    }

    /**
     * Retrieve the customer row associated with a given WordPress user.
     *
     * Uses the wp_RORO_USER_LINK_WP table to resolve the mapping
     * between a WP user ID and a customer ID.  Returns null if no
     * mapping exists.
     *
     * @param int $user_id WordPress user ID
     * @return object|null Customer record or null
     */
    public function get_by_wp_user( $user_id ) {
        global $wpdb;
        // Look up the customer ID in the mapping table.  The table
        // prefix is applied automatically.
        $link_table = $wpdb->prefix . 'RORO_USER_LINK_WP';
        $cust_id    = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT customer_id FROM {$link_table} WHERE wp_user_id = %d",
                $user_id
            )
        );
        if ( $cust_id ) {
            return $this->get_one_by( $this->primary_key, $cust_id );
        }
        return null;
    }
}