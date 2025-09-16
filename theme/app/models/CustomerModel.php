<?php
require_once __DIR__ . '/BaseModel.php';

/**
 * CustomerModel handles interactions with the RORO_CUSTOMER table.  Note that
 * this model references the `RORO_CUSTOMER` table which is distinct from
 * WordPress' own `wp_users` table.  Customers are application-level users
 * and may or may not have an associated WordPress user account.
 */
class CustomerModel extends BaseModel {
    public function __construct() {
        // Name of our custom customer table without WordPress prefix
        $this->table = 'RORO_CUSTOMER';
        parent::__construct( $this->table );
    }

    /**
     * Fetch a customer by email address.
     *
     * @param string $email
     * @return object|null
     */
    public function get_by_email( $email ) {
        return $this->get_one( [ 'email' => $email ] );
    }

    /**
     * Creates a new customer record.  Only a subset of fields are required
     * here; additional profile data can be set later via update().
     *
     * @param string $email
     * @param string $prefecture
     * @param string $city
     * @return int|false Newly created customer id or false on failure.
     */
    public function create( $email, $prefecture = null, $city = null ) {
        $data = [
            'email'       => $email,
            'prefecture'  => $prefecture,
            'city'        => $city,
            'created_at'  => current_time( 'mysql' ),
            'updated_at'  => current_time( 'mysql' ),
        ];
        return $this->insert( $data );
    }
}
