<?php
/**
 * Roro_FavoriteModel
 *
 * Provides search and pagination functions for favourites stored in the
 * wp_RORO_MAP_FAVORITE table.  Favourites may reference either events
 * (RORO_EVENTS_MASTER) or travel spots (RORO_TRAVEL_SPOT_MASTER).  This
 * model wraps the complex SQL needed to join these tables and filter
 * results based on user input.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

require_once __DIR__ . '/Roro_BaseModel.php';

class Roro_FavoriteModel extends Roro_BaseModel {
    public function __construct() {
        // Primary key is `id` on wp_RORO_MAP_FAVORITE
        parent::__construct( 'RORO_MAP_FAVORITE', 'id' );
    }

    /**
     * Search favourites with pagination and optional filters.
     *
     * @param array $args {
     *   @type int    $page Page number (1‑based)
     *   @type int    $per  Number of items per page
     *   @type string $q    Search term (matches various columns)
     *   @type string $type Target type filter ('event', 'spot' or empty for all)
     * }
     * @return array {
     *   @type int   $total Total number of matches
     *   @type array $rows  Array of result objects
     *   @type int   $page  Current page number
     *   @type int   $per   Items per page
     * }
     */
    public function search_paged( array $args ) {
        global $wpdb;
        // Extract and sanitise parameters
        $page = isset( $args['page'] ) ? max( 1, intval( $args['page'] ) ) : 1;
        $per  = isset( $args['per'] )  ? max( 1, intval( $args['per'] ) )  : 20;
        $off  = ( $page - 1 ) * $per;
        $q    = isset( $args['q'] )    ? trim( (string) $args['q'] )       : '';
        $type = isset( $args['type'] ) && in_array( $args['type'], array( 'event', 'spot' ), true ) ? $args['type'] : '';

        // Table aliases
        $fav = $this->wpdb->prefix . 'RORO_MAP_FAVORITE';
        $evt = 'RORO_EVENTS_MASTER';
        $spt = 'RORO_TRAVEL_SPOT_MASTER';

        // Build WHERE conditions
        $where  = '1=1';
        $params = array();
        if ( $type ) {
            $where .= ' AND f.target_type = %s';
            $params[] = $type;
        }
        if ( $q !== '' ) {
            $like = '%' . $wpdb->esc_like( $q ) . '%';
            // Use prepare to safely include variables in the LIKE clause
            $where .= $wpdb->prepare(
                ' AND (CAST(f.id AS CHAR) LIKE %s OR CAST(f.user_id AS CHAR) LIKE %s OR CAST(f.target_id AS CHAR) LIKE %s OR e.name LIKE %s OR s.name LIKE %s OR e.prefecture LIKE %s OR s.prefecture LIKE %s OR e.city LIKE %s OR s.spot_area LIKE %s)',
                $like, $like, $like, $like, $like, $like, $like, $like, $like
            );
        }

        // Total count query
        $sql_count = "SELECT COUNT(*) FROM {$fav} AS f "
                   . "LEFT JOIN {$evt} AS e ON (f.target_type='event' AND f.target_id=e.event_id) "
                   . "LEFT JOIN {$spt} AS s ON (f.target_type='spot'  AND f.target_id=s.TSM_ID) "
                   . "WHERE {$where}";
        $total = (int) $wpdb->get_var( $wpdb->prepare( $sql_count, $params ) );

        // Data query with pagination
        $sql_data = "SELECT f.id, f.user_id, f.target_type, f.target_id, f.created_at, "
                  . "CASE WHEN f.target_type='event' THEN e.name ELSE s.name END AS target_name, "
                  . "CASE WHEN f.target_type='event' THEN e.prefecture ELSE s.prefecture END AS prefecture, "
                  . "CASE WHEN f.target_type='event' THEN e.city ELSE s.spot_area END AS city "
                  . "FROM {$fav} AS f "
                  . "LEFT JOIN {$evt} AS e ON (f.target_type='event' AND f.target_id=e.event_id) "
                  . "LEFT JOIN {$spt} AS s ON (f.target_type='spot'  AND f.target_id=s.TSM_ID) "
                  . "WHERE {$where} "
                  . "ORDER BY f.created_at DESC "
                  . "LIMIT %d OFFSET %d";
        $params_for_data = array_merge( $params, array( $per, $off ) );
        $rows = $wpdb->get_results( $wpdb->prepare( $sql_data, $params_for_data ) );

        return array(
            'total' => $total,
            'rows'  => $rows,
            'page'  => $page,
            'per'   => $per,
        );
    }

    /**
     * Delete a favourite by its primary key.
     * Wrapper for the base model's delete method.
     *
     * @param int $id Primary key of favourite
     * @return bool|int Number of rows deleted or false on failure
     */
    public function delete_by_id( $id ) {
        return $this->delete_by_pk( $id );
    }
}