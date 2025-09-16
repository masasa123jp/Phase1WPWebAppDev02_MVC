<?php
// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * REST endpoints for magazine management (issues and pages).
 */
class Roro_REST_Magazine {
    const NS = 'roro/v1';

    /**
     * Register routes.
     */
    public static function register() {
        // Issues
        register_rest_route( self::NS, '/magazine/issues', [
            [
                'methods'             => 'GET',
                'callback'            => [ __CLASS__, 'issues_list' ],
                'permission_callback' => function() {
                    return current_user_can( 'read' );
                },
            ],
            [
                'methods'             => 'POST',
                'callback'            => [ __CLASS__, 'issues_create' ],
                'permission_callback' => function() {
                    return current_user_can( 'manage_options' );
                },
            ],
        ] );
        register_rest_route( self::NS, '/magazine/issues/(?P<id>\d+)', [
            [
                'methods'             => 'PUT',
                'callback'            => [ __CLASS__, 'issues_update' ],
                'permission_callback' => function() {
                    return current_user_can( 'manage_options' );
                },
            ],
            [
                'methods'             => 'DELETE',
                'callback'            => [ __CLASS__, 'issues_delete' ],
                'permission_callback' => function() {
                    return current_user_can( 'manage_options' );
                },
            ],
        ] );
        // Pages
        register_rest_route( self::NS, '/magazine/pages', [
            [
                'methods'             => 'GET',
                'callback'            => [ __CLASS__, 'pages_list' ],
                'permission_callback' => function() {
                    return current_user_can( 'read' );
                },
            ],
            [
                'methods'             => 'POST',
                'callback'            => [ __CLASS__, 'pages_create' ],
                'permission_callback' => function() {
                    return current_user_can( 'manage_options' );
                },
            ],
        ] );
        register_rest_route( self::NS, '/magazine/pages/(?P<id>\d+)', [
            [
                'methods'             => 'PUT',
                'callback'            => [ __CLASS__, 'pages_update' ],
                'permission_callback' => function() {
                    return current_user_can( 'manage_options' );
                },
            ],
            [
                'methods'             => 'DELETE',
                'callback'            => [ __CLASS__, 'pages_delete' ],
                'permission_callback' => function() {
                    return current_user_can( 'manage_options' );
                },
            ],
        ] );
    }

    /**
     * List all published issues.
     *
     * @param WP_REST_Request $req
     * @return WP_REST_Response
     */
    public static function issues_list( $req ) {
        global $wpdb;
        $rows = $wpdb->get_results( 'SELECT * FROM RORO_MAGAZINE_ISSUE WHERE is_published=1', ARRAY_A );
        return Roro_REST_Utils::respond( $rows );
    }

    /**
     * Create a new issue.
     */
    public static function issues_create( $req ) {
        global $wpdb;
        $data = [
            'title'       => sanitize_text_field( $req[ 'title' ] ?? '' ),
            'issue_code'  => sanitize_text_field( $req[ 'issue_code' ] ?? '' ),
            'is_published'=> isset( $req[ 'is_published' ] ) ? intval( $req[ 'is_published' ] ) : 0,
            'created_at'  => current_time( 'mysql' ),
        ];
        $wpdb->insert( 'RORO_MAGAZINE_ISSUE', $data );
        return Roro_REST_Utils::respond( [ 'id' => $wpdb->insert_id ], 201 );
    }

    /**
     * Update an existing issue.
     */
    public static function issues_update( $req ) {
        global $wpdb;
        $id = intval( $req[ 'id' ] );
        $data = [
            'title'        => sanitize_text_field( $req[ 'title' ] ?? '' ),
            'is_published' => isset( $req[ 'is_published' ] ) ? intval( $req[ 'is_published' ] ) : 0,
            'updated_at'   => current_time( 'mysql' ),
        ];
        $wpdb->update( 'RORO_MAGAZINE_ISSUE', $data, [ 'id' => $id ] );
        return Roro_REST_Utils::respond( [ 'ok' => true ] );
    }

    /**
     * Delete an issue and its pages.
     */
    public static function issues_delete( $req ) {
        global $wpdb;
        $id = intval( $req[ 'id' ] );
        $wpdb->delete( 'RORO_MAGAZINE_ISSUE', [ 'id' => $id ] );
        $wpdb->query( $wpdb->prepare( 'DELETE FROM RORO_MAGAZINE_PAGE WHERE issue_id=%d', $id ) );
        return Roro_REST_Utils::respond( [ 'deleted' => true ] );
    }

    /**
     * List pages for a given issue.
     *
     * @param WP_REST_Request $req
     * @return WP_REST_Response
     */
    public static function pages_list( $req ) {
        global $wpdb;
        $issue_id = intval( $req[ 'issue_id' ] ?? 0 );
        $rows     = $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM RORO_MAGAZINE_PAGE WHERE issue_id=%d ORDER BY page_order ASC', $issue_id ), ARRAY_A );
        return Roro_REST_Utils::respond( $rows );
    }

    /**
     * Create a new magazine page.
     */
    public static function pages_create( $req ) {
        global $wpdb;
        $data = [
            'issue_id'   => intval( $req[ 'issue_id' ] ?? 0 ),
            'page_order' => intval( $req[ 'page_order' ] ?? 0 ),
            'title'      => sanitize_text_field( $req[ 'title' ] ?? '' ),
            'image_url'  => esc_url_raw( $req[ 'image_url' ] ?? '' ),
            'created_at' => current_time( 'mysql' ),
        ];
        $wpdb->insert( 'RORO_MAGAZINE_PAGE', $data );
        return Roro_REST_Utils::respond( [ 'id' => $wpdb->insert_id ], 201 );
    }

    /**
     * Update a magazine page.
     */
    public static function pages_update( $req ) {
        global $wpdb;
        $id = intval( $req[ 'id' ] );
        $data = [
            'page_order' => intval( $req[ 'page_order' ] ?? 0 ),
            'title'      => sanitize_text_field( $req[ 'title' ] ?? '' ),
            'image_url'  => esc_url_raw( $req[ 'image_url' ] ?? '' ),
            'updated_at' => current_time( 'mysql' ),
        ];
        $wpdb->update( 'RORO_MAGAZINE_PAGE', $data, [ 'id' => $id ] );
        return Roro_REST_Utils::respond( [ 'ok' => true ] );
    }

    /**
     * Delete a magazine page.
     */
    public static function pages_delete( $req ) {
        global $wpdb;
        $id = intval( $req[ 'id' ] );
        $wpdb->delete( 'RORO_MAGAZINE_PAGE', [ 'id' => $id ] );
        return Roro_REST_Utils::respond( [ 'deleted' => true ] );
    }
}
