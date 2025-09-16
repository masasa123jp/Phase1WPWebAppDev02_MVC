<?php
require_once __DIR__ . '/BaseModel.php';

/**
 * FavoriteModel encapsulates access to the wp_RORO_MAP_FAVORITE table which
 * stores user favourites for map spots and events.  Each favourite is
 * uniquely identified by the tuple (user_id, target_type, target_id).
 */
class FavoriteModel extends BaseModel {
    public function __construct() {
        // This table is prefixed just like WordPress core tables; note the
        // base table name does not include any prefix.
        $this->table = 'RORO_MAP_FAVORITE';
        parent::__construct( $this->table );
    }

    /**
     * Retrieve all favourites for a given user.
     *
     * @param int $user_id WordPress user ID
     * @return array Array of favourite row objects
     */
    public function get_by_user( $user_id ) {
        return $this->get_many( [ 'user_id' => $user_id ] );
    }

    /**
     * Determines whether a favourite entry exists.
     *
     * @param int    $user_id
     * @param string $target_type 'spot' or 'event'
     * @param int    $target_id
     * @return bool
     */
    public function exists( $user_id, $target_type, $target_id ) {
        $row = $this->get_one( [
            'user_id'    => $user_id,
            'target_type' => $target_type,
            'target_id'   => $target_id,
        ] );
        return ! is_null( $row );
    }

    /**
     * Adds a favourite record for a user/target.  If the favourite already
     * exists, the method returns true without inserting a new row.
     *
     * @param int    $user_id
     * @param string $target_type
     * @param int    $target_id
     * @return bool True on success, false on error
     */
    public function add_favorite( $user_id, $target_type, $target_id ) {
        if ( $this->exists( $user_id, $target_type, $target_id ) ) {
            return true;
        }
        $result = $this->insert( [
            'user_id'      => $user_id,
            'target_type'  => $target_type,
            'target_id'    => $target_id,
            'created_at'   => current_time( 'mysql' ),
        ] );
        return (bool) $result;
    }

    /**
     * Removes a favourite entry.
     *
     * @param int    $user_id
     * @param string $target_type
     * @param int    $target_id
     * @return bool True if a row was deleted, false otherwise
     */
    public function remove_favorite( $user_id, $target_type, $target_id ) {
        $deleted = $this->delete( [
            'user_id'     => $user_id,
            'target_type' => $target_type,
            'target_id'   => $target_id,
        ] );
        return (bool) $deleted;
    }

    /**
     * Toggles a favourite for a user/target.  If the favourite exists it is
     * removed; otherwise it is added.  The return value indicates whether
     * the favourite now exists after the operation.
     *
     * @param int    $user_id
     * @param string $target_type
     * @param int    $target_id
     * @return bool True if favourite now exists, false if removed
     */
    public function toggle_favorite( $user_id, $target_type, $target_id ) {
        if ( $this->exists( $user_id, $target_type, $target_id ) ) {
            $this->remove_favorite( $user_id, $target_type, $target_id );
            return false;
        } else {
            $this->add_favorite( $user_id, $target_type, $target_id );
            return true;
        }
    }
}
