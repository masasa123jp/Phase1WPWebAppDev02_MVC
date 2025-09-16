<?php
/**
 * AdviceModel encapsulates CRUD operations for the
 * RORO_ONE_POINT_ADVICE_MASTER table.  This table stores "ワンポイントアドバイス"
 * records such as a title, body and associated pet type.  Advice entries may
 * be created, updated, listed or deleted via this model.  The OPAM_ID
 * string column functions as the primary key.  When creating a new entry,
 * a unique identifier will be generated automatically.
 */

require_once __DIR__ . '/BaseModel.php';

class AdviceModel extends BaseModel {
    /**
     * Construct a new AdviceModel.  Defines the base table name and calls
     * the parent constructor to initialise the wpdb object.
     */
    public function __construct() {
        $this->table = 'RORO_ONE_POINT_ADVICE_MASTER';
        parent::__construct( $this->table );
    }

    /**
     * Retrieve all advice entries.  Optionally filter by visibility.
     *
     * @param bool $visibleOnly Only return entries where isVisible=1
     * @return array List of associative arrays representing advice rows
     */
    public function get_all( $visibleOnly = true ) {
        $sql = "SELECT * FROM `{$this->table}`";
        $values = [];
        if ( $visibleOnly ) {
            // Visible AND published advice only
            $sql .= " WHERE `isVisible` = 1 AND `status` = 'published'";
        }
        return $this->db->get_results( $sql, ARRAY_A );
    }

    /**
     * Retrieve a single advice entry by its OPAM_ID.  Returns null if
     * no matching entry exists.
     *
     * @param string $opam_id The primary key value
     * @return array|null The row as an associative array, or null
     */
    public function get_by_id( $opam_id ) {
        return $this->db->get_row( $this->db->prepare( "SELECT * FROM `{$this->table}` WHERE `OPAM_ID` = %s", $opam_id ), ARRAY_A );
    }

    /**
     * Return a random advice entry.  If a pet type is provided, restrict
     * selection to that type.  Only visible entries are considered.
     *
     * @param string|null $pet_type One of 'DOG', 'CAT', 'OTHER' or null
     * @return array|null A single advice row or null
     */
    public function get_random( $pet_type = null ) {
        // Only visible and published advice entries are eligible for random selection
        $sql = "SELECT * FROM `{$this->table}` WHERE `isVisible` = 1 AND `status` = 'published'";
        $values = [];
        if ( ! empty( $pet_type ) ) {
            $sql .= " AND `pet_type` = %s";
            $values[] = $pet_type;
        }
        $sql .= " ORDER BY RAND() LIMIT 1";
        return $this->db->get_row( $this->db->prepare( $sql, $values ), ARRAY_A );
    }

    /**
     * Create a new advice entry.  Generates a unique OPAM_ID string if
     * one is not provided in $data.
     *
     * @param array $data Array of column => value pairs
     * @return string The newly created OPAM_ID
     */
    public function create( array $data ) {
        // Generate a unique OPAM_ID if not supplied
        if ( empty( $data['OPAM_ID'] ) ) {
            $data['OPAM_ID'] = uniqid( 'opam_', true );
        }
        // Set timestamps
        $data['created_at'] = current_time( 'mysql' );
        $data['updated_at'] = current_time( 'mysql' );
        // Default visibility to 1
        if ( ! isset( $data['isVisible'] ) ) {
            $data['isVisible'] = 1;
        }
        // Default status to draft if not provided
        if ( empty( $data['status'] ) ) {
            $data['status'] = 'draft';
        }
        // Set status_updated_at to now on creation
        $data['status_updated_at'] = current_time( 'mysql' );
        $this->db->insert( $this->table, $data );
        return $data['OPAM_ID'];
    }

    /**
     * Update an advice entry by OPAM_ID.  Only provided fields in $data
     * will be updated.  Automatically sets the updated_at timestamp.
     *
     * @param string $opam_id The identifier to update
     * @param array $data The fields to update
     * @return int Number of rows updated (0 or 1)
     */
    public function update_entry( $opam_id, array $data ) {
        if ( empty( $opam_id ) ) {
            return 0;
        }
        // Remove OPAM_ID from data to avoid updating the primary key
        if ( isset( $data['OPAM_ID'] ) ) {
            unset( $data['OPAM_ID'] );
        }
        $data['updated_at'] = current_time( 'mysql' );
        // If status is provided, update status_updated_at and record history if changed
        if ( isset( $data['status'] ) ) {
            // Fetch current status for comparison
            $row = $this->db->get_row( $this->db->prepare( "SELECT `status` FROM `{$this->table}` WHERE `OPAM_ID` = %s", $opam_id ) );
            $old_status = $row ? $row->status : null;
            // Update status_updated_at regardless of change to reflect modification time
            $data['status_updated_at'] = current_time( 'mysql' );
            if ( $old_status !== null && $old_status !== $data['status'] ) {
                // Insert into status history
                $this->db->insert( 'RORO_STATUS_HISTORY', [
                    'table_name'  => $this->table,
                    'record_id'   => $opam_id,
                    'old_status'  => $old_status,
                    'new_status'  => $data['status'],
                    'changed_at'  => current_time( 'mysql' ),
                    'changed_by'  => get_current_user_id(),
                ] );
            }
        }
        return $this->db->update( $this->table, $data, [ 'OPAM_ID' => $opam_id ] );
    }

    /**
     * Delete an advice entry by its OPAM_ID.
     *
     * @param string $opam_id
     * @return int Number of rows deleted (0 or 1)
     */
    public function delete_entry( $opam_id ) {
        if ( empty( $opam_id ) ) {
            return 0;
        }
        return $this->db->delete( $this->table, [ 'OPAM_ID' => $opam_id ] );
    }
}