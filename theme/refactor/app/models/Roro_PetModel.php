<?php
/**
 * Model for the RORO_PET table.  Provides helper methods for
 * retrieving, creating, updating and deleting pet records.  A
 * customer may have multiple pets.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

require_once __DIR__ . '/Roro_BaseModel.php';

class Roro_PetModel extends Roro_BaseModel {
    public function __construct() {
        parent::__construct( 'RORO_PET', 'pet_id' );
    }

    /**
     * Retrieve all pets for a given customer.
     *
     * @param int $customer_id Customer ID
     * @return array           Array of pet rows
     */
    public function get_by_customer( $customer_id ) {
        return $this->wpdb->get_results(
            $this->wpdb->prepare(
                "SELECT * FROM {$this->table} WHERE customer_id = %d",
                $customer_id
            )
        );
    }

    /**
     * Check if a pet belongs to a given customer.
     *
     * @param int $pet_id      Pet ID
     * @param int $customer_id Customer ID
     * @return bool
     */
    public function pet_belongs_to_customer( $pet_id, $customer_id ) {
        $pet = $this->get_one_by( $this->primary_key, $pet_id );
        if ( ! $pet ) {
            return false;
        }
        return (int) $pet->customer_id === (int) $customer_id;
    }
}