<?php
/**
 * Base model providing simple CRUD helpers for RORO custom tables.
 *
 * Each concrete model extending this class should set the `$table`
 * property to the unprefixed database table name (without the
 * `wp_` prefix) and the `$primary_key` property to the primary key
 * column.  Methods in this class will automatically apply the
 * WordPress table prefix and use `$wpdb` for all database access.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Roro_BaseModel {
    /**
     * Fully prefixed table name including the WordPress prefix.
     *
     * @var string
     */
    protected $table;

    /**
     * Primary key column name.
     *
     * @var string
     */
    protected $primary_key;

    /**
     * Global $wpdb object injected at construction time.
     *
     * @var wpdb
     */
    protected $wpdb;

    /**
     * Construct a new model instance.  Child classes should pass the
     * unprefixed table name and primary key name.  The WordPress
     * database prefix will be prepended to the table name at runtime.
     *
     * @param string $table       Unprefixed table name
     * @param string $primary_key Primary key column
     */
    public function __construct( $table, $primary_key ) {
        global $wpdb;
        $this->wpdb        = $wpdb;
        // Prepend the WordPress table prefix to the supplied table
        // name.  For example, passing `RORO_CUSTOMER` will result in
        // something like `wp_RORO_CUSTOMER` depending on the site's
        // prefix.  Use prepare() when building queries instead of
        // directly concatenating untrusted data.
        $this->table       = $wpdb->prefix . $table;
        $this->primary_key = $primary_key;
    }

    /**
     * Retrieve a single record matching the specified column/value.
     *
     * @param string $column Column to match
     * @param mixed  $value  Value to search for
     * @return object|null   Row object or null if not found
     */
    public function get_one_by( $column, $value ) {
        return $this->wpdb->get_row(
            $this->wpdb->prepare(
                "SELECT * FROM {$this->table} WHERE {$column} = %s LIMIT 1",
                $value
            )
        );
    }

    /**
     * Retrieve all records matching the specified column/value.
     *
     * @param string $column Column to match
     * @param mixed  $value  Value to search for
     * @return array         Array of row objects
     */
    public function get_all_by( $column, $value ) {
        return $this->wpdb->get_results(
            $this->wpdb->prepare(
                "SELECT * FROM {$this->table} WHERE {$column} = %s",
                $value
            )
        );
    }

    /**
     * Update a record using its primary key.
     *
     * @param mixed $pk   Primary key value
     * @param array $data Associative array of column => value pairs
     * @return int|false  Number of rows affected, or false on error
     */
    public function update_by_pk( $pk, array $data ) {
        return $this->wpdb->update(
            $this->table,
            $data,
            array( $this->primary_key => $pk )
        );
    }

    /**
     * Insert a new record into the table.
     *
     * @param array $data Associative array of column => value pairs
     * @return int|false  Insert ID on success, false on failure
     */
    public function insert( array $data ) {
        $result = $this->wpdb->insert( $this->table, $data );
        if ( false === $result ) {
            return false;
        }
        return (int) $this->wpdb->insert_id;
    }

    /**
     * Delete a record by primary key.
     *
     * @param mixed $pk Primary key value
     * @return int|false Number of rows deleted, or false on error
     */
    public function delete_by_pk( $pk ) {
        return $this->wpdb->delete(
            $this->table,
            array( $this->primary_key => $pk )
        );
    }
}