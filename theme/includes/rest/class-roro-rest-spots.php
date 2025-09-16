<?php
// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * REST endpoints for managing pet‑friendly travel spots.
 *
 * このクラスは RORO_TRAVEL_SPOT_MASTER テーブルに対する CRUD 操作を提供します。
 * 公開スポット一覧は誰でも取得できますが、作成・更新・削除には manage_options 権限が必要です。
 */
class Roro_REST_Spots {
    const NS = 'roro/v1';

    /**
     * Register REST routes.
     */
    public static function register() {
        // GET /spots 取得、POST /spots 作成
        register_rest_route( self::NS, '/spots', [
            [
                'methods'             => 'GET',
                'callback'            => [ __CLASS__, 'list' ],
                // Anyone can view spots
                'permission_callback' => function() {
                    return true;
                },
            ],
            [
                'methods'             => 'POST',
                'callback'            => [ __CLASS__, 'create' ],
                'permission_callback' => function() {
                    // Require custom capability for spot creation
                    return current_user_can( 'manage_roro_spots' );
                },
                'args'                => [
                    'name' => [
                        'required'          => true,
                        'sanitize_callback' => 'sanitize_text_field',
                        'validate_callback' => function ( $value ) {
                            return is_string( $value ) && trim( $value ) !== '';
                        },
                        'description'       => __( 'Name of the spot', 'roro' ),
                    ],
                    'prefecture' => [
                        'required'          => false,
                        'sanitize_callback' => 'sanitize_text_field',
                    ],
                    'address' => [
                        'required'          => false,
                        'sanitize_callback' => 'sanitize_text_field',
                    ],
                    'latitude' => [
                        'required'          => true,
                        'validate_callback' => function ( $value ) {
                            return is_numeric( $value );
                        },
                    ],
                    'longitude' => [
                        'required'          => true,
                        'validate_callback' => function ( $value ) {
                            return is_numeric( $value );
                        },
                    ],
                    'url' => [
                        'required'          => false,
                        'sanitize_callback' => 'esc_url_raw',
                    ],
                    'status' => [
                        'required'          => false,
                        'validate_callback' => function ( $value ) {
                            return in_array( $value, [ 'draft', 'published' ], true );
                        },
                    ],
                ],
            ],
        ] );
        // PUT/DELETE /spots/{id}
        register_rest_route( self::NS, '/spots/(?P<id>\d+)', [
            [
                'methods'             => 'PUT',
                'callback'            => [ __CLASS__, 'update' ],
                'permission_callback' => function() {
                    return current_user_can( 'manage_roro_spots' );
                },
                'args'                => [
                    'name' => [
                        'required'          => false,
                        'sanitize_callback' => 'sanitize_text_field',
                        'validate_callback' => function ( $value ) {
                            return trim( $value ) !== '';
                        },
                    ],
                    'prefecture' => [
                        'required'          => false,
                        'sanitize_callback' => 'sanitize_text_field',
                    ],
                    'address' => [
                        'required'          => false,
                        'sanitize_callback' => 'sanitize_text_field',
                    ],
                    'latitude' => [
                        'required'          => false,
                        'validate_callback' => function ( $value ) {
                            return $value === null || $value === '' || is_numeric( $value );
                        },
                    ],
                    'longitude' => [
                        'required'          => false,
                        'validate_callback' => function ( $value ) {
                            return $value === null || $value === '' || is_numeric( $value );
                        },
                    ],
                    'url' => [
                        'required'          => false,
                        'sanitize_callback' => 'esc_url_raw',
                    ],
                    'status' => [
                        'required'          => false,
                        'validate_callback' => function ( $value ) {
                            return in_array( $value, [ 'draft', 'published' ], true );
                        },
                    ],
                    'isVisible' => [
                        'required'          => false,
                        'validate_callback' => function ( $value ) {
                            return $value === null || $value === '' || in_array( (string) $value, [ '0', '1' ], true );
                        },
                    ],
                ],
            ],
            [
                'methods'             => 'DELETE',
                'callback'            => [ __CLASS__, 'delete' ],
                'permission_callback' => function() {
                    return current_user_can( 'manage_roro_spots' );
                },
            ],
        ] );
    }

    /**
     * Retrieve all visible spots.
     *
     * @param WP_REST_Request $req
     * @return WP_REST_Response
     */
    public static function list( $req ) {
        global $wpdb;
        // Use a transient cache for spot list; key is static because no query params.
        $cache_key = 'roro_spots_list';
        $cached = get_transient( $cache_key );
        if ( false === $cached ) {
            // Administrators can request all spots (including drafts) by passing `all=1`
            $include_all = isset( $req['all'] ) && intval( $req['all'] ) === 1 && current_user_can( 'manage_options' );
            $where = "WHERE isVisible = 1";
            // Status filter for admin (optional)
            $status_filter = null;
            if ( $include_all && isset( $req['status'] ) ) {
                $sf = sanitize_text_field( $req['status'] );
                if ( in_array( $sf, [ 'draft', 'published' ], true ) ) {
                    $status_filter = $sf;
                }
            }
            if ( ! $include_all ) {
                $where .= " AND status = 'published'";
            } elseif ( $status_filter ) {
                $where .= $wpdb->prepare( " AND status = %s", $status_filter );
            }
            $rows = $wpdb->get_results(
                "SELECT id, TSM_ID, name, prefecture, address, latitude, longitude, url, isVisible, status
                 FROM RORO_TRAVEL_SPOT_MASTER
                 $where",
                ARRAY_A
            );
            set_transient( $cache_key, $rows, HOUR_IN_SECONDS );
        } else {
            $rows = $cached;
        }
        return Roro_REST_Utils::respond( $rows );
    }

    /**
     * Create a new spot.
     *
     * @param WP_REST_Request $req
     * @return WP_REST_Response
     */
    public static function create( $req ) {
        global $wpdb;
        $prefecture = sanitize_text_field( $req['prefecture'] ?? '' );
        $name       = sanitize_text_field( $req['name'] ?? '' );
        $address    = sanitize_text_field( $req['address'] ?? '' );
        // Validation: ensure name and coordinates are provided and numeric
        $lat_raw    = $req['latitude'] ?? null;
        $lng_raw    = $req['longitude'] ?? null;
        if ( empty( $name ) ) {
            return new WP_Error( 'missing_name', __( 'Spot name is required', 'roro' ), [ 'status' => 400 ] );
        }
        if ( $lat_raw === null || $lat_raw === '' || ! is_numeric( $lat_raw ) ) {
            return new WP_Error( 'invalid_lat', __( 'Latitude is required and must be numeric', 'roro' ), [ 'status' => 400 ] );
        }
        if ( $lng_raw === null || $lng_raw === '' || ! is_numeric( $lng_raw ) ) {
            return new WP_Error( 'invalid_lng', __( 'Longitude is required and must be numeric', 'roro' ), [ 'status' => 400 ] );
        }
        $latitude   = floatval( $lat_raw );
        $longitude  = floatval( $lng_raw );
        $latitude   = isset( $req['latitude'] ) ? floatval( $req['latitude'] ) : null;
        $longitude  = isset( $req['longitude'] ) ? floatval( $req['longitude'] ) : null;
        $url        = esc_url_raw( $req['url'] ?? '' );
                // Prevent duplicates: avoid creating a spot with identical name and address
        $existing = $wpdb->get_var( $wpdb->prepare(
            "SELECT id FROM RORO_TRAVEL_SPOT_MASTER WHERE name = %s AND address = %s",
            $name,
            $address
        ) );
        if ( $existing ) {
            return new WP_Error( 'duplicate_spot', __( 'A spot with the same name and address already exists', 'roro' ), [ 'status' => 400 ] );
        }
// Generate a unique TSM_ID if none provided
        $TSM_ID     = 'TSM_' . uniqid();
        // Determine publication status (draft by default)
        $status = sanitize_text_field( $req['status'] ?? '' );
        if ( ! in_array( $status, [ 'draft', 'published' ], true ) ) {
            $status = 'draft';
        }
        $data = [
            'TSM_ID'       => $TSM_ID,
            'branch_no'    => 1,
            'prefecture'   => $prefecture,
            'region'       => '',
            'spot_area'    => '',
            'genre'        => '',
            'name'         => $name,
            'phone'        => '',
            'address'      => $address,
            'opening_time' => '',
            'closing_time' => '',
            'url'          => $url,
            'latitude'     => $latitude,
            'longitude'    => $longitude,
            'google_rating'       => null,
            'google_review_count' => null,
            'english_support'     => null,
            'review'       => '',
            'category_code' => null,
            'isVisible'    => 1,
            'status'       => $status,
            'status_updated_at' => current_time( 'mysql' ),
            'created_at'   => current_time( 'mysql' ),
        ];
        $wpdb->insert( 'RORO_TRAVEL_SPOT_MASTER', $data );
        $insert_id = $wpdb->insert_id;
        // Insert into status history
        $wpdb->insert( 'RORO_STATUS_HISTORY', [
            'table_name' => 'RORO_TRAVEL_SPOT_MASTER',
            'record_id'  => (string) $insert_id,
            'old_status' => null,
            'new_status' => $status,
            'changed_by' => get_current_user_id(),
        ] );
        // Invalidate spot list cache
        delete_transient( 'roro_spots_list' );
        return Roro_REST_Utils::respond( [ 'id' => $insert_id ], 201 );
    }

    /**
     * Update a spot.
     *
     * @param WP_REST_Request $req
     * @return WP_REST_Response
     */
    public static function update( $req ) {
        global $wpdb;
        $id         = intval( $req['id'] );
        $prefecture = sanitize_text_field( $req['prefecture'] ?? '' );
        $name       = sanitize_text_field( $req['name'] ?? '' );
        $address    = sanitize_text_field( $req['address'] ?? '' );
        $latitude   = isset( $req['latitude'] ) ? floatval( $req['latitude'] ) : null;
        $longitude  = isset( $req['longitude'] ) ? floatval( $req['longitude'] ) : null;
        $url        = esc_url_raw( $req['url'] ?? '' );
        $lat_raw = $req['latitude'] ?? null;
        $lng_raw = $req['longitude'] ?? null;
        // Validation: ensure provided name is not empty and coordinates are numeric when supplied
        if ( isset( $req['name'] ) && empty( $name ) ) {
            return new WP_Error( 'missing_name', __( 'Spot name cannot be empty', 'roro' ), [ 'status' => 400 ] );
        }
        if ( $lat_raw !== null && $lat_raw !== '' && ! is_numeric( $lat_raw ) ) {
            return new WP_Error( 'invalid_lat', __( 'Latitude must be numeric', 'roro' ), [ 'status' => 400 ] );
        }
        if ( $lng_raw !== null && $lng_raw !== '' && ! is_numeric( $lng_raw ) ) {
            return new WP_Error( 'invalid_lng', __( 'Longitude must be numeric', 'roro' ), [ 'status' => 400 ] );
        }
        $latitude   = ( $lat_raw === null || $lat_raw === '' ) ? null : floatval( $lat_raw );
        $longitude  = ( $lng_raw === null || $lng_raw === '' ) ? null : floatval( $lng_raw );
        $isVisible  = isset( $req['isVisible'] ) ? intval( $req['isVisible'] ) : 1;

        // Prevent duplicates: avoid updating a record to have the same name and address as another spot.
        // Only perform check when both name and address are provided, and exclude the current record ID.
        if ( ! empty( $name ) && ! empty( $address ) ) {
            global $wpdb;
            $existing = $wpdb->get_var( $wpdb->prepare(
                "SELECT id FROM RORO_TRAVEL_SPOT_MASTER WHERE name = %s AND address = %s AND id <> %d",
                $name,
                $address,
                $id
            ) );
            if ( $existing ) {
                return new WP_Error( 'duplicate_spot', __( 'A spot with the same name and address already exists', 'roro' ), [ 'status' => 400 ] );
            }
        }
        // Determine desired status; if omitted, keep existing
        $status = null;
        if ( isset( $req['status'] ) ) {
            $st = sanitize_text_field( $req['status'] );
            if ( in_array( $st, [ 'draft', 'published' ], true ) ) {
                $status = $st;
            }
        }
        $data = [
            'prefecture' => $prefecture,
            'name'       => $name,
            'address'    => $address,
            'latitude'   => $latitude,
            'longitude'  => $longitude,
            'url'        => $url,
            'isVisible'  => $isVisible,
            'updated_at' => current_time( 'mysql' ),
        ];
        if ( $status !== null ) {
            // Fetch current status
            $row = $wpdb->get_row( $wpdb->prepare( "SELECT status FROM RORO_TRAVEL_SPOT_MASTER WHERE id = %d", $id ) );
            $old_status = $row ? $row->status : null;
            $data['status'] = $status;
            $data['status_updated_at'] = current_time( 'mysql' );
            if ( $old_status !== null && $old_status !== $status ) {
                $wpdb->insert( 'RORO_STATUS_HISTORY', [
                    'table_name' => 'RORO_TRAVEL_SPOT_MASTER',
                    'record_id'  => (string) $id,
                    'old_status' => $old_status,
                    'new_status' => $status,
                    'changed_by' => get_current_user_id(),
                ] );
            }
        }
        $wpdb->update( 'RORO_TRAVEL_SPOT_MASTER', $data, [ 'id' => $id ] );
        // Invalidate spot list cache
        delete_transient( 'roro_spots_list' );
        return Roro_REST_Utils::respond( [ 'ok' => true ] );
    }

    /**
     * Delete a spot and remove related favourites.
     *
     * @param WP_REST_Request $req
     * @return WP_REST_Response
     */
    public static function delete( $req ) {
        global $wpdb;
        $id = intval( $req['id'] );
        $wpdb->delete( 'RORO_TRAVEL_SPOT_MASTER', [ 'id' => $id ] );
        // Remove favorites referencing this spot
        $wpdb->query( $wpdb->prepare( "DELETE FROM wp_RORO_MAP_FAVORITE WHERE target_type='spot' AND target_id=%d", $id ) );
        // Invalidate spot list cache
        delete_transient( 'roro_spots_list' );
        return Roro_REST_Utils::respond( [ 'deleted' => true ] );
    }
}