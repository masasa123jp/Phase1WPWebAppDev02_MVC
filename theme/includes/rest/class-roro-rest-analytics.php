<?php
// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * REST API endpoints for analytics.  Provides aggregated magazine
 * analytics data for administrators.  The data is sourced from the
 * RORO_MAGAZINE_DAILY table and can be filtered by date range and
 * issue.  A typical response includes the issue ID, page ID, date
 * (YYYY‑MM‑DD), view count and click count.
 */
class Roro_REST_Analytics {
    const NS = 'roro/v1';

    /**
     * Register routes.
     */
    public static function register() {
        register_rest_route( self::NS, '/analytics', [
            [
                'methods'             => 'GET',
                'callback'            => [ __CLASS__, 'get_data' ],
                'permission_callback' => function() {
                    return current_user_can( 'manage_options' );
                },
            ],
        ] );
    }

    /**
     * Returns aggregated analytics data.  Supports optional query
     * parameters:
     *   - start_date (YYYY-MM-DD)
     *   - end_date   (YYYY-MM-DD)
     *   - issue_id   (int)
     *
     * @param WP_REST_Request $req
     * @return WP_REST_Response
     */
    public static function get_data( $req ) {
        global $wpdb;
        $conditions = [];
        $params     = [];
        $start_date = $req['start_date'];
        $end_date   = $req['end_date'];
        $issue_id   = $req['issue_id'];
        if ( $start_date ) {
            $conditions[] = 'date_key >= %s';
            $params[]     = $start_date;
        }
        if ( $end_date ) {
            $conditions[] = 'date_key <= %s';
            $params[]     = $end_date;
        }
        if ( $issue_id ) {
            $conditions[] = 'issue_id = %d';
            $params[]     = intval( $issue_id );
        }
        $where = $conditions ? ' WHERE ' . implode( ' AND ', $conditions ) : '';
        $sql = "SELECT issue_id, page_id, DATE_FORMAT(date_key, '%Y-%m-%d') AS date, views, clicks
                FROM RORO_MAGAZINE_DAILY
                $where
                ORDER BY date_key DESC, issue_id, page_id";
        $rows = $wpdb->get_results( $wpdb->prepare( $sql, $params ), ARRAY_A );
        return Roro_REST_Utils::respond( $rows );
    }
}