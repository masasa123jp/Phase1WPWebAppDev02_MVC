<?php
// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * REST endpoints for event management.  Public list; admin create/update/delete.
 */
class Roro_REST_Events {
    const NS = 'roro/v1';

    /**
     * Register routes.
     */
    public static function register() {
        register_rest_route( self::NS, '/events', [
            [
                'methods'             => 'GET',
                'callback'            => [ __CLASS__, 'list' ],
                'permission_callback' => function() {
                    return current_user_can( 'read' );
                },
            ],
            [
                'methods'             => 'POST',
                'callback'            => [ __CLASS__, 'create' ],
                'permission_callback' => function() {
                    return current_user_can( 'manage_roro_events' );
                },
                // Define required arguments and basic validation rules for event creation.
                'args'                => [
                    'name' => [
                        'required'          => true,
                        'sanitize_callback' => 'sanitize_text_field',
                        'validate_callback' => function ( $value ) {
                            return is_string( $value ) && trim( $value ) !== '';
                        },
                        'description'       => __( 'Name of the event', 'roro' ),
                    ],
                    'date' => [
                        'required'          => true,
                        'sanitize_callback' => 'sanitize_text_field',
                        'validate_callback' => function ( $value ) {
                            return strtotime( $value ) !== false;
                        },
                        'description'       => __( 'Event date (YYYY-MM-DD)', 'roro' ),
                    ],
                    'place' => [
                        'required'          => false,
                        'sanitize_callback' => 'sanitize_text_field',
                    ],
                    'lat' => [
                        'required'          => false,
                        'validate_callback' => function ( $value ) {
                            return $value === null || $value === '' || is_numeric( $value );
                        },
                    ],
                    'lng' => [
                        'required'          => false,
                        'validate_callback' => function ( $value ) {
                            return $value === null || $value === '' || is_numeric( $value );
                        },
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
        register_rest_route( self::NS, '/events/(?P<id>\d+)', [
            [
                'methods'             => 'PUT',
                'callback'            => [ __CLASS__, 'update' ],
                'permission_callback' => function() {
                    return current_user_can( 'manage_roro_events' );
                },
                // Provide validation for updatable fields.  Fields are optional but
                // validated when present.
                'args'                => [
                    'name' => [
                        'required'          => false,
                        'sanitize_callback' => 'sanitize_text_field',
                        'validate_callback' => function ( $value ) {
                            return trim( $value ) !== '';
                        },
                    ],
                    'date' => [
                        'required'          => false,
                        'sanitize_callback' => 'sanitize_text_field',
                        'validate_callback' => function ( $value ) {
                            return strtotime( $value ) !== false;
                        },
                    ],
                    'place' => [
                        'required'          => false,
                        'sanitize_callback' => 'sanitize_text_field',
                    ],
                    'lat' => [
                        'required'          => false,
                        'validate_callback' => function ( $value ) {
                            return $value === null || $value === '' || is_numeric( $value );
                        },
                    ],
                    'lng' => [
                        'required'          => false,
                        'validate_callback' => function ( $value ) {
                            return $value === null || $value === '' || is_numeric( $value );
                        },
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
                    return current_user_can( 'manage_roro_events' );
                },
            ],
        ] );
    }

    /**
     * List all visible events.
     *
     * @param WP_REST_Request $req
     * @return WP_REST_Response
     */
    public static function list( $req ) {
        global $wpdb;
        // Use a transient cache for the public events list to improve performance.
        // The cache is keyed on a simple string because the query has no parameters.
        $cache_key = 'roro_events_list';
        $cached = get_transient( $cache_key );
        if ( false === $cached ) {
            /*
             * For public calls, return only visible & published events.  Administrators
             * can request all events (including drafts) by passing `all=1`.  Use
             * normalized columns to avoid exposing internal fields.
             */
            $include_all = isset( $req['all'] ) && intval( $req['all'] ) === 1 && current_user_can( 'manage_options' );
            $where = "WHERE isVisible = 1";
            // Determine if a status filter is specified (admin only)
            $status_filter = null;
            if ( $include_all && isset( $req['status'] ) ) {
                $sf = sanitize_text_field( $req['status'] );
                if ( in_array( $sf, [ 'draft', 'published' ], true ) ) {
                    $status_filter = $sf;
                }
            }
            if ( ! $include_all ) {
                // Non‑admins always see only published
                $where .= " AND status = 'published'";
            } elseif ( $status_filter ) {
                $where .= $wpdb->prepare( " AND status = %s", $status_filter );
            }
            $rows = $wpdb->get_results(
                "SELECT id, name, COALESCE(DATE_FORMAT(event_date, '%Y-%m-%d'), `date`) AS date, isVisible, status
                 FROM RORO_EVENTS_MASTER
                 $where
                 ORDER BY event_date DESC",
                ARRAY_A
            );
            // Cache the results for one hour.
            set_transient( $cache_key, $rows, HOUR_IN_SECONDS );
        } else {
            $rows = $cached;
        }
        return Roro_REST_Utils::respond( $rows );
    }

    /**
     * Create a new event.
     */
    public static function create( $req ) {
        global $wpdb;
        $raw_date = $req['date'] ?? '';
        $name     = sanitize_text_field( $req['name'] ?? '' );
        $place    = sanitize_text_field( $req['place'] ?? '' );
        // Validation: ensure required fields and coordinate format
        $lat_raw  = $req['lat'] ?? null;
        $lng_raw  = $req['lng'] ?? null;
        if ( empty( $name ) ) {
            return new WP_Error( 'missing_name', __( 'Event name is required', 'roro' ), [ 'status' => 400 ] );
        }
        if ( empty( $raw_date ) || strtotime( $raw_date ) === false ) {
            return new WP_Error( 'invalid_date', __( 'A valid event date (YYYY-MM-DD) is required', 'roro' ), [ 'status' => 400 ] );
        }
        if ( $lat_raw !== null && $lat_raw !== '' && ! is_numeric( $lat_raw ) ) {
            return new WP_Error( 'invalid_lat', __( 'Latitude must be numeric', 'roro' ), [ 'status' => 400 ] );
        }
        if ( $lng_raw !== null && $lng_raw !== '' && ! is_numeric( $lng_raw ) ) {
            return new WP_Error( 'invalid_lng', __( 'Longitude must be numeric', 'roro' ), [ 'status' => 400 ] );
        }
        $lat      = $lat_raw === null || $lat_raw === '' ? 0 : floatval( $lat_raw );
        $lng      = $lng_raw === null || $lng_raw === '' ? 0 : floatval( $lng_raw );
        // Normalize date input.  Accepts YYYY-MM-DD or similar.  If invalid, leave NULL.
        $event_date = null;
        if ( ! empty( $raw_date ) ) {
            $ts = strtotime( $raw_date );
            if ( $ts !== false ) {
                $event_date = gmdate( 'Y-m-d', $ts );
            }
        }
                // Prevent duplicate events with same name and date
        if ( ! empty( $event_date ) ) {
            $existing = $wpdb->get_var( $wpdb->prepare(
                "SELECT id FROM RORO_EVENTS_MASTER WHERE name = %s AND event_date = %s",
                $name,
                $event_date
            ) );
            if ( $existing ) {
                return new WP_Error( 'duplicate_event', __( 'An event with the same name and date already exists', 'roro' ), [ 'status' => 400 ] );
            }
        }
// Determine publication status (draft by default)
        $status = sanitize_text_field( $req['status'] ?? '' );
        if ( ! in_array( $status, [ 'draft', 'published' ], true ) ) {
            $status = 'draft';
        }
        $data = [
            'name'       => $name,
            'date'       => $raw_date,
            'event_date' => $event_date,
            'place'      => $place,
            'lat'        => $lat,
            'lng'        => $lng,
            'isVisible'  => 1,
            'status'     => $status,
            'status_updated_at' => current_time( 'mysql' ),
            'created_at' => current_time( 'mysql' ),
        ];
        $wpdb->insert( 'RORO_EVENTS_MASTER', $data );
        $new_id = $wpdb->insert_id;
        // Record status history on create
        $wpdb->insert( 'RORO_STATUS_HISTORY', [
            'table_name' => 'RORO_EVENTS_MASTER',
            'record_id'  => (string) $new_id,
            'old_status' => null,
            'new_status' => $status,
            'changed_by' => get_current_user_id(),
        ] );
        // Invalidate events list cache
        delete_transient( 'roro_events_list' );
        return Roro_REST_Utils::respond( [ 'id' => $new_id ], 201 );
    }

    /**
     * Update an existing event.
     */
    public static function update( $req ) {
        global $wpdb;
        $id   = intval( $req['id'] );
        $name = sanitize_text_field( $req['name'] ?? '' );
        $raw_date = $req['date'] ?? '';
        $place = sanitize_text_field( $req['place'] ?? '' );
        // Pull raw coordinates from the request.  May be strings or empty.  Defining these variables
        // here ensures they are available for validation below.  Treat absence as null.
        $lat_raw = $req['lat'] ?? null;
        $lng_raw = $req['lng'] ?? null;
        // Validation: ensure required fields and coordinate format
        if ( empty( $name ) ) {
            return new WP_Error( 'missing_name', __( 'Event name is required', 'roro' ), [ 'status' => 400 ] );
        }
        if ( empty( $raw_date ) || strtotime( $raw_date ) === false ) {
            return new WP_Error( 'invalid_date', __( 'A valid event date (YYYY-MM-DD) is required', 'roro' ), [ 'status' => 400 ] );
        }
        if ( $lat_raw !== null && $lat_raw !== '' && ! is_numeric( $lat_raw ) ) {
            return new WP_Error( 'invalid_lat', __( 'Latitude must be numeric', 'roro' ), [ 'status' => 400 ] );
        }
        if ( $lng_raw !== null && $lng_raw !== '' && ! is_numeric( $lng_raw ) ) {
            return new WP_Error( 'invalid_lng', __( 'Longitude must be numeric', 'roro' ), [ 'status' => 400 ] );
        }
        $lat      = $lat_raw === null || $lat_raw === '' ? 0 : floatval( $lat_raw );
        $lng      = $lng_raw === null || $lng_raw === '' ? 0 : floatval( $lng_raw );
        $isVisible = isset( $req['isVisible'] ) ? intval( $req['isVisible'] ) : 1;
        // Normalize date value
        $event_date = null;
        if ( ! empty( $raw_date ) ) {
            $ts = strtotime( $raw_date );
            if ( $ts !== false ) {
                $event_date = gmdate( 'Y-m-d', $ts );
            }
        }

        // Prevent duplicate events: do not allow another event with the same name and date.
        // Only perform the check if a valid normalized event date exists.  Exclude the current event ID.
        if ( ! empty( $event_date ) ) {
            $existing = $wpdb->get_var( $wpdb->prepare(
                "SELECT id FROM RORO_EVENTS_MASTER WHERE name = %s AND event_date = %s AND id <> %d",
                $name,
                $event_date,
                $id
            ) );
            if ( $existing ) {
                return new WP_Error( 'duplicate_event', __( 'An event with the same name and date already exists', 'roro' ), [ 'status' => 400 ] );
            }
        }
        // Determine desired status.  If not provided, keep existing status.
        $status = null;
        if ( isset( $req['status'] ) ) {
            $status_tmp = sanitize_text_field( $req['status'] );
            if ( in_array( $status_tmp, [ 'draft', 'published' ], true ) ) {
                $status = $status_tmp;
            }
        }
        $data = [
            'name'       => $name,
            'date'       => $raw_date,
            'event_date' => $event_date,
            'place'      => $place,
            'lat'        => $lat,
            'lng'        => $lng,
            'isVisible'  => $isVisible,
            'updated_at' => current_time( 'mysql' ),
        ];
        // Fetch current status for history and update if changed
        if ( $status !== null ) {
            $row = $wpdb->get_row( $wpdb->prepare( "SELECT status FROM RORO_EVENTS_MASTER WHERE id = %d", $id ) );
            $old_status = $row ? $row->status : null;
            $data['status'] = $status;
            $data['status_updated_at'] = current_time( 'mysql' );
            if ( $old_status !== null && $old_status !== $status ) {
                $wpdb->insert( 'RORO_STATUS_HISTORY', [
                    'table_name' => 'RORO_EVENTS_MASTER',
                    'record_id'  => (string) $id,
                    'old_status' => $old_status,
                    'new_status' => $status,
                    'changed_by' => get_current_user_id(),
                ] );
            }
        }
        $wpdb->update( 'RORO_EVENTS_MASTER', $data, [ 'id' => $id ] );
        // Invalidate events list cache
        delete_transient( 'roro_events_list' );
        return Roro_REST_Utils::respond( [ 'ok' => true ] );
    }

    /**
     * Delete an event and clean up related favorites.
     */
    public static function delete( $req ) {
        global $wpdb;
        $id = intval( $req[ 'id' ] );
        $wpdb->delete( 'RORO_EVENTS_MASTER', [ 'id' => $id ] );
        // Remove favorites that referenced this event
        $wpdb->query( $wpdb->prepare( "DELETE FROM wp_RORO_MAP_FAVORITE WHERE target_type='event' AND target_id=%d", $id ) );
        // Invalidate events list cache
        delete_transient( 'roro_events_list' );
        return Roro_REST_Utils::respond( [ 'deleted' => true ] );
    }
}
