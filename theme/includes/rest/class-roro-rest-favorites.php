<?php
// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * REST API endpoints for managing user favourites.  This class
 * replaces the legacy AJAX actions (`roro_get_favorites` and
 * `roro_toggle_favorite`) with a consistent REST interface.  The
 * favourites are stored in the `wp_RORO_MAP_FAVORITE` table via
 * `FavoriteModel` and linked back to events or spots when listing.
 */
class Roro_REST_Favorites {
    const NS = 'roro/v1';

    /**
     * Registers the REST routes for favourites.
     */
    public static function register() {
        register_rest_route( self::NS, '/favorites', [
            [
                'methods'             => 'GET',
                'callback'            => [ __CLASS__, 'list' ],
                'permission_callback' => function() {
                    return is_user_logged_in();
                },
            ],
            [
                'methods'             => 'POST',
                'callback'            => [ __CLASS__, 'create' ],
                'permission_callback' => function() {
                    return is_user_logged_in();
                },
                // Validate input parameters for creating a favourite
                'args'                => [
                    'target_type' => [
                        'required'          => true,
                        'sanitize_callback' => 'sanitize_key',
                        'validate_callback' => function ( $value ) {
                            // Only allow known target types to prevent abuse
                            return in_array( $value, [ 'event', 'spot', 'pet' ], true );
                        },
                        'description'       => __( 'Type of the target (event, spot, pet)', 'roro' ),
                    ],
                    'target_id'   => [
                        'required'          => true,
                        'validate_callback' => function ( $value ) {
                            return is_numeric( $value ) && intval( $value ) > 0;
                        },
                        'description'       => __( 'ID of the target item', 'roro' ),
                    ],
                ],
            ],
        ] );
        register_rest_route( self::NS, '/favorites/(?P<target_type>[^/]+)/(?P<target_id>\d+)', [
            [
                'methods'             => 'DELETE',
                'callback'            => [ __CLASS__, 'delete' ],
                'permission_callback' => function() {
                    return is_user_logged_in();
                },
                // Provide validation for delete route parameters
                'args'                => [
                    'target_type' => [
                        'validate_callback' => function ( $value ) {
                            return in_array( $value, [ 'event', 'spot', 'pet' ], true );
                        },
                    ],
                    'target_id'   => [
                        'validate_callback' => function ( $value ) {
                            return is_numeric( $value ) && intval( $value ) > 0;
                        },
                    ],
                ],
            ],
        ] );
    }

    /**
     * Returns the list of favourites for the current user.  Each
     * favourite entry is enriched with associated event or spot
     * information so the front end does not need to issue additional
     * queries.
     *
     * @param WP_REST_Request $req
     * @return WP_REST_Response
     */
    public static function list( $req ) {
        $user_id = get_current_user_id();
        if ( ! $user_id ) {
            return Roro_REST_Utils::error( __( 'Authentication required', 'roro' ), 401 );
        }
        $fav_model = new FavoriteModel();
        $favs = $fav_model->get_by_user( $user_id );
        $result = [];
        global $wpdb;
        foreach ( $favs as $fav ) {
            $item = [
                'target_type' => $fav->target_type,
                'target_id'   => intval( $fav->target_id ),
            ];
            if ( $fav->target_type === 'event' ) {
                // Fetch event details.  Use id or event_id depending on table structure.
                $row = $wpdb->get_row( $wpdb->prepare(
                    "SELECT id, name, COALESCE(DATE_FORMAT(event_date, '%Y-%m-%d'), `date`) AS date,
                            place AS location, prefecture, city, url
                     FROM RORO_EVENTS_MASTER
                     WHERE id = %d OR event_id = %s LIMIT 1",
                    $fav->target_id,
                    $fav->target_id
                ), ARRAY_A );
                if ( $row ) {
                    $item = array_merge( $item, $row );
                }
            } elseif ( $fav->target_type === 'spot' ) {
                // Fetch spot details from travel spot master if implemented
                $row = $wpdb->get_row( $wpdb->prepare(
                    "SELECT name, address AS location, prefecture, city, url
                     FROM RORO_TRAVEL_SPOT_MASTER
                     WHERE id = %d OR TSM_ID = %s LIMIT 1",
                    $fav->target_id,
                    $fav->target_id
                ), ARRAY_A );
                if ( $row ) {
                    $item = array_merge( $item, $row );
                }
            }
            $result[] = $item;
        }
        return Roro_REST_Utils::respond( $result );
    }

    /**
     * Adds a favourite for the current user.  If the favourite already
     * exists it is silently ignored.  Accepts `target_type` and
     * `target_id` in the request body (POST data).
     *
     * @param WP_REST_Request $req
     * @return WP_REST_Response
     */
    public static function create( $req ) {
        $user_id = get_current_user_id();
        if ( ! $user_id ) {
            return Roro_REST_Utils::error( __( 'Authentication required', 'roro' ), 401 );
        }
        $target_type = sanitize_key( $req['target_type'] ?? '' );
        $target_id   = intval( $req['target_id'] ?? 0 );
        if ( ! $target_type || ! $target_id ) {
            return Roro_REST_Utils::error( __( 'Missing parameters', 'roro' ), 400 );
        }
        $fav_model = new FavoriteModel();
        $fav_model->add_favorite( $user_id, $target_type, $target_id );
        return Roro_REST_Utils::respond( [ 'ok' => true ] );
    }

    /**
     * Removes a favourite for the current user.  The route captures
     * `target_type` and `target_id` as URL parameters.
     *
     * @param WP_REST_Request $req
     * @return WP_REST_Response
     */
    public static function delete( $req ) {
        $user_id = get_current_user_id();
        if ( ! $user_id ) {
            return Roro_REST_Utils::error( __( 'Authentication required', 'roro' ), 401 );
        }
        $target_type = sanitize_key( $req['target_type'] );
        $target_id   = intval( $req['target_id'] );
        if ( ! $target_type || ! $target_id ) {
            return Roro_REST_Utils::error( __( 'Missing parameters', 'roro' ), 400 );
        }
        $fav_model = new FavoriteModel();
        $fav_model->remove_favorite( $user_id, $target_type, $target_id );
        return Roro_REST_Utils::respond( [ 'deleted' => true ] );
    }
}