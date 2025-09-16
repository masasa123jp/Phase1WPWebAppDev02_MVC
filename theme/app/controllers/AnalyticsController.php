<?php
/**
 * AnalyticsController collects view and click events for magazine and other
 * pages.  It exposes REST endpoints under the `roro-analytics/v1`
 * namespace and provides a cron job to compute daily aggregations.
 *
 * The controller does not perform authentication; however, it records
 * the current WordPress user ID when present.  Clients should send
 * `issue_id`, `page_id`, `session_id`, and other optional parameters
 * (device, lang, referer, dwell_ms) when posting a view event.  Click
 * events should include `issue_id`, `page_id`, `link_id` or `href`,
 * and session information.  Dwell time is optional and can be sent
 * after a page is closed.
 */

require_once __DIR__ . '/BaseController.php';

class AnalyticsController extends BaseController {
    /**
     * Register REST routes.  Attach via rest_api_init in functions.php.
     */
    public function register_routes() {
        register_rest_route( 'roro-analytics/v1', '/view', [
            'methods'             => 'POST',
            'callback'            => [ $this, 'rest_view' ],
            'permission_callback' => '__return_true',
        ] );
        register_rest_route( 'roro-analytics/v1', '/click', [
            'methods'             => 'POST',
            'callback'            => [ $this, 'rest_click' ],
            'permission_callback' => '__return_true',
        ] );
    }

    /**
     * Handle a view event.  Expects JSON with at minimum `issue_id` and
     * optionally `page_id`, `session_id`, `device`, `lang`, `referer`,
     * and `dwell_ms`.  Stores an entry in RORO_MAGAZINE_VIEW.
     *
     * @param WP_REST_Request $req
     * @return WP_REST_Response
     */
    public function rest_view( WP_REST_Request $req ) {
        global $wpdb;
        $issue_id   = intval( $req->get_param( 'issue_id' ) );
        $page_id    = $req->get_param( 'page_id' ) !== null ? intval( $req->get_param( 'page_id' ) ) : null;
        $wp_user_id = get_current_user_id() ?: null;
        $session_id = sanitize_text_field( $req->get_param( 'session_id' ) );
        $device     = sanitize_text_field( $req->get_param( 'device' ) );
        $lang       = sanitize_text_field( $req->get_param( 'lang' ) );
        $referer    = esc_url_raw( $req->get_param( 'referer' ) );
        $dwell_ms   = $req->get_param( 'dwell_ms' );
        $dwell_ms   = ( $dwell_ms !== null ) ? intval( $dwell_ms ) : null;
        // Compute hashed IP/UA to avoid storing raw PII
        $ip_hash  = isset( $_SERVER['REMOTE_ADDR'] ) ? hash( 'sha256', $_SERVER['REMOTE_ADDR'] ) : null;
        $ua_hash  = isset( $_SERVER['HTTP_USER_AGENT'] ) ? hash( 'sha256', $_SERVER['HTTP_USER_AGENT'] ) : null;
        $table    = $wpdb->prefix . 'RORO_MAGAZINE_VIEW';
        // Insert row
        $wpdb->insert( $table, [
            'issue_id' => $issue_id,
            'page_id'  => $page_id,
            'wp_user_id' => $wp_user_id,
            'session_id' => $session_id,
            'ip_hash' => $ip_hash,
            'ua_hash' => $ua_hash,
            'referer' => $referer,
            'device'  => $device,
            'lang'    => $lang,
            'event_ts'=> current_time( 'mysql' ),
            'dwell_ms'=> $dwell_ms,
        ], [
            '%d','%d','%d','%s','%s','%s','%s','%s','%s','%s','%d'
        ] );
        return new WP_REST_Response( [ 'ok' => true ], 200 );
    }

    /**
     * Handle a click event.  Expects `issue_id`, `page_id`, and either
     * `link_id` or `href`.  Stores an entry in RORO_MAGAZINE_CLICK.
     *
     * @param WP_REST_Request $req
     * @return WP_REST_Response
     */
    public function rest_click( WP_REST_Request $req ) {
        global $wpdb;
        $issue_id   = intval( $req->get_param( 'issue_id' ) );
        $page_id    = $req->get_param( 'page_id' ) !== null ? intval( $req->get_param( 'page_id' ) ) : null;
        $link_id    = $req->get_param( 'link_id' ) !== null ? intval( $req->get_param( 'link_id' ) ) : null;
        $href       = $req->get_param( 'href' ) ? esc_url_raw( $req->get_param( 'href' ) ) : null;
        $wp_user_id = get_current_user_id() ?: null;
        $session_id = sanitize_text_field( $req->get_param( 'session_id' ) );
        $ip_hash    = isset( $_SERVER['REMOTE_ADDR'] ) ? hash( 'sha256', $_SERVER['REMOTE_ADDR'] ) : null;
        $table      = $wpdb->prefix . 'RORO_MAGAZINE_CLICK';
        $wpdb->insert( $table, [
            'issue_id' => $issue_id,
            'page_id'  => $page_id,
            'link_id'  => $link_id,
            'href'     => $href,
            'wp_user_id' => $wp_user_id,
            'session_id' => $session_id,
            'ip_hash'  => $ip_hash,
            'event_ts' => current_time( 'mysql' ),
        ], [ '%d','%d','%d','%s','%d','%s','%s','%s' ] );
        return new WP_REST_Response( [ 'ok' => true ], 200 );
    }

    /**
     * Perform daily aggregation of views and clicks.  To be scheduled
     * via WP-Cron.  Aggregates the last two days (yesterday and today)
     * to account for delayed beacon events.
     */
    public function update_daily() {
        global $wpdb;
        $views  = $wpdb->prefix . 'RORO_MAGAZINE_VIEW';
        $clicks = $wpdb->prefix . 'RORO_MAGAZINE_CLICK';
        $daily  = $wpdb->prefix . 'RORO_MAGAZINE_DAILY';
        // Replace into daily aggregated table
        $sql = "
            REPLACE INTO $daily (`date`,`issue_id`,`page_id`,`views`,`unique_uv`,`clicks`,`avg_dwell_ms`)
            SELECT
              DATE(v.event_ts) AS d,
              v.issue_id,
              v.page_id,
              COUNT(*) AS views,
              COUNT(DISTINCT COALESCE(v.session_id, v.ip_hash)) AS unique_uv,
              COALESCE((
                SELECT COUNT(*) FROM $clicks c1
                WHERE c1.issue_id=v.issue_id AND (c1.page_id <=> v.page_id) AND DATE(c1.event_ts)=DATE(v.event_ts)
              ),0) AS clicks,
              COALESCE(ROUND(AVG(NULLIF(v.dwell_ms,0))),0) AS avg_dwell_ms
            FROM $views v
            WHERE v.event_ts >= DATE_SUB(CURDATE(), INTERVAL 2 DAY)
            GROUP BY DATE(v.event_ts), v.issue_id, v.page_id
        ";
        $wpdb->query( $sql );
    }
}