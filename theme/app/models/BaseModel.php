<?php
/**
 * BaseModel provides a thin abstraction over the global $wpdb instance.
 *
 * All model classes should extend this class and set the `$table` property
 * to the appropriate database table name.  Methods provided here simplify
 * common CRUD operations.  WordPress' `$wpdb` already handles proper
 * escaping, but callers are responsible for sanitising untrusted input
 * before passing values into these methods.
 */

class BaseModel {
    /**
     * @var string The database table name (without prefix) for this model.
     */
    protected $table;

    /**
     * @var wpdb WordPress database abstraction instance.
     */
    protected $db;

    /**
     * Constructor.  Accepts an optional table name; otherwise relies on
     * subclasses to set `$table` before instantiation.
     *
     * @param string|null $table
     */
    public function __construct( $table = null ) {
        global $wpdb;
        $this->db = $wpdb;
        if ( $table !== null ) {
            $this->table = $table;
        }
    }

    /**
     * Returns the full table name including WordPress' table prefix.
     *
     * @return string
     */
    protected function get_table() {
        return $this->db->prefix . $this->table;
    }

    /**
     * Fetches one row from the table matching the given where clause.
     *
     * @param array $where Associative array of column => value pairs.
     * @return object|null The first matching row or null if none found.
     */
    public function get_one( array $where ) {
        $table = $this->get_table();
        $where_clauses = [];
        $values = [];
        foreach ( $where as $column => $value ) {
            $where_clauses[] = "`$column` = %s";
            $values[]        = $value;
        }
        $sql = "SELECT * FROM `$table` WHERE " . implode( ' AND ', $where_clauses ) . ' LIMIT 1';
        return $this->db->get_row( $this->db->prepare( $sql, $values ) );
    }

    /**
     * Fetches multiple rows from the table optionally limited by where clause.
     *
     * @param array|null $where
     * @param int|null   $limit
     * @param int|null   $offset
     * @return array Array of row objects.
     */
    public function get_many( array $where = null, $limit = null, $offset = null ) {
        $table = $this->get_table();
        $sql   = "SELECT * FROM `$table`";
        $values = [];
        if ( $where ) {
            $clauses = [];
            foreach ( $where as $column => $value ) {
                $clauses[] = "`$column` = %s";
                $values[]  = $value;
            }
            $sql .= " WHERE " . implode( ' AND ', $clauses );
        }
        if ( $limit !== null ) {
            $sql .= " LIMIT %d";
            $values[] = (int) $limit;
            if ( $offset !== null ) {
                $sql .= " OFFSET %d";
                $values[] = (int) $offset;
            }
        }
        return $this->db->get_results( $this->db->prepare( $sql, $values ) );
    }

    /**
     * Inserts a new row into the table.
     *
     * @param array $data Associative array of column => value pairs.
     * @return int|false The insert id on success or false on failure.
     */
    public function insert( array $data ) {
        $table = $this->get_table();
        $result = $this->db->insert( $table, $data );
        if ( $result ) {
            return $this->db->insert_id;
        }
        return false;
    }

    /**
     * Updates rows in the table matching where clause.
     *
     * @param array $data  Associative array of column => value pairs to update.
     * @param array $where Associative array of column => value pairs to match.
     * @return int|false Number of rows updated or false on error.
     */
    public function update( array $data, array $where ) {
        $table = $this->get_table();
        return $this->db->update( $table, $data, $where );
    }

    /**
     * Deletes rows from the table matching the given where clause.
     *
     * @param array $where
     * @return int|false Number of rows deleted or false on error.
     */
    public function delete( array $where ) {
        $table = $this->get_table();
        return $this->db->delete( $table, $where );
    }
}
