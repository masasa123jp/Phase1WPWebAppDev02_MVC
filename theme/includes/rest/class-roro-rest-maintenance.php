<?php
// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * REST and cron endpoints for maintenance tasks such as log cleanup.
 */
class Roro_REST_Maintenance {
    const NS = 'roro/v1';

    /**
     * Register routes and cron jobs.
     */
    public static function register() {
        register_rest_route( self::NS, '/maintenance/cleanup', [
            [
                'methods'             => 'POST',
                'callback'            => [ __CLASS__, 'cleanup' ],
                'permission_callback' => function() {
                    return current_user_can( 'manage_options' );
                },
            ],
        ] );
        // Schedule weekly cleanup if not already scheduled
        add_action( 'roro_weekly_cleanup', [ __CLASS__, 'cron_cleanup' ] );
        if ( ! wp_next_scheduled( 'roro_weekly_cleanup' ) ) {
            wp_schedule_event( time() + 300, 'weekly', 'roro_weekly_cleanup' );
        }
    }

    /**
     * Internal routine to clean log tables.
     */
    private static function run_cleanup() {
        global $wpdb;
        // Delete raw view/click records older than 180 days
        $wpdb->query( "DELETE FROM RORO_MAGAZINE_VIEW WHERE viewed_at < (CURRENT_DATE - INTERVAL 180 DAY)" );
        $wpdb->query( "DELETE FROM RORO_MAGAZINE_CLICK WHERE clicked_at < (CURRENT_DATE - INTERVAL 180 DAY)" );
        // Delete daily aggregates older than 365 days
        $wpdb->query( "DELETE FROM RORO_MAGAZINE_DAILY WHERE date_key < (CURRENT_DATE - INTERVAL 365 DAY)" );
    }

    /**
     * REST endpoint callback for cleanup.
     */
    public static function cleanup( $req ) {
        self::run_cleanup();
        return Roro_REST_Utils::respond( [ 'ok' => true ] );
    }

    /**
     * Cron callback for cleanup.
     */
    public static function cron_cleanup() {
        self::run_cleanup();
    }
}
