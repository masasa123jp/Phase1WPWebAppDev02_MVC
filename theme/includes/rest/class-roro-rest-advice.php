<?php
// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * REST endpoints for "One Point Advice" management.  Provides CRUD
 * operations on the RORO_ONE_POINT_ADVICE_MASTER table as well as a
 * public endpoint to fetch a random advice entry.  All management
 * endpoints require appropriate capabilities (manage_options by default).
 */

// Load AdviceModel so that the class is available without relying on the autoloader.
require_once get_template_directory() . '/app/models/AdviceModel.php';

class Roro_REST_Advice {
    const NS = 'roro/v1';

    /**
     * Register advice routes with the WordPress REST API.
     */
    public static function register() {
        // List and create advice entries
        register_rest_route( self::NS, '/advice', [
            [
                'methods'             => 'GET',
                'callback'            => [ __CLASS__, 'list' ],
                'permission_callback' => function() {
                    // Anyone can fetch advice list.  For administration, include hidden flag.
                    return current_user_can( 'read' );
                },
            ],
            [
                'methods'             => 'POST',
                'callback'            => [ __CLASS__, 'create' ],
                'permission_callback' => function() {
                    return current_user_can( 'manage_roro_advice' );
                },
                'args'                => [
                    'pet_type' => [
                        'required'          => false,
                        'sanitize_callback' => function( $value ) {
                            return strtoupper( sanitize_text_field( $value ) );
                        },
                        'validate_callback' => function( $value ) {
                            return in_array( strtoupper( $value ), [ 'DOG', 'CAT', 'OTHER', '' ], true );
                        },
                    ],
                    'category_code' => [
                        'required'          => false,
                        'sanitize_callback' => 'sanitize_text_field',
                    ],
                    'title' => [
                        'required'          => true,
                        'sanitize_callback' => 'sanitize_text_field',
                        'validate_callback' => function ( $value ) {
                            return is_string( $value ) && trim( $value ) !== '';
                        },
                    ],
                    'body' => [
                        'required'          => true,
                        'sanitize_callback' => 'wp_kses_post',
                        'validate_callback' => function ( $value ) {
                            return is_string( $value ) && trim( $value ) !== '';
                        },
                    ],
                    'url' => [
                        'required'          => false,
                        'sanitize_callback' => 'esc_url_raw',
                    ],
                    'for_which_pets' => [
                        'required'          => false,
                        'sanitize_callback' => 'sanitize_text_field',
                    ],
                    'isVisible' => [
                        'required'          => false,
                        'validate_callback' => function ( $value ) {
                            return $value === null || $value === '' || in_array( (string) $value, [ '0', '1' ], true );
                        },
                    ],
                    'status' => [
                        'required'          => false,
                        'validate_callback' => function( $value ) {
                            return in_array( $value, [ 'draft', 'published' ], true );
                        },
                    ],
                ],
            ],
        ] );

        // Fetch random advice
        register_rest_route( self::NS, '/advice/random', [
            [
                'methods'             => 'GET',
                'callback'            => [ __CLASS__, 'random' ],
                'permission_callback' => function() {
                    return true; // Publicly accessible
                },
            ],
        ] );

        // Single advice operations by OPAM_ID
        register_rest_route( self::NS, '/advice/(?P<id>[^/]+)', [
            [
                'methods'             => 'GET',
                'callback'            => [ __CLASS__, 'get_one' ],
                'permission_callback' => function() {
                    return current_user_can( 'read' );
                },
            ],
            [
                'methods'             => 'PUT',
                'callback'            => [ __CLASS__, 'update' ],
                'permission_callback' => function() {
                    return current_user_can( 'manage_roro_advice' );
                },
                'args'                => [
                    'pet_type' => [
                        'required'          => false,
                        'sanitize_callback' => function( $value ) {
                            return strtoupper( sanitize_text_field( $value ) );
                        },
                        'validate_callback' => function( $value ) {
                            return in_array( strtoupper( $value ), [ 'DOG', 'CAT', 'OTHER', '' ], true );
                        },
                    ],
                    'category_code' => [
                        'required'          => false,
                        'sanitize_callback' => 'sanitize_text_field',
                    ],
                    'title' => [
                        'required'          => false,
                        'sanitize_callback' => 'sanitize_text_field',
                        'validate_callback' => function( $value ) {
                            return trim( $value ) !== '';
                        },
                    ],
                    'body' => [
                        'required'          => false,
                        'sanitize_callback' => 'wp_kses_post',
                        'validate_callback' => function( $value ) {
                            return trim( $value ) !== '';
                        },
                    ],
                    'url' => [
                        'required'          => false,
                        'sanitize_callback' => 'esc_url_raw',
                    ],
                    'for_which_pets' => [
                        'required'          => false,
                        'sanitize_callback' => 'sanitize_text_field',
                    ],
                    'isVisible' => [
                        'required'          => false,
                        'validate_callback' => function( $value ) {
                            return $value === null || $value === '' || in_array( (string) $value, [ '0', '1' ], true );
                        },
                    ],
                    'status' => [
                        'required'          => false,
                        'validate_callback' => function( $value ) {
                            return in_array( $value, [ 'draft', 'published' ], true );
                        },
                    ],
                ],
            ],
            [
                'methods'             => 'DELETE',
                'callback'            => [ __CLASS__, 'delete' ],
                'permission_callback' => function() {
                    return current_user_can( 'manage_roro_advice' );
                },
            ],
        ] );
    }

    /**
     * List advice entries.  If the request contains include_hidden=1 and the
     * user has manage_options capability, hidden (isVisible=0) entries are
     * included.  Otherwise only visible entries are returned.
     *
     * @param WP_REST_Request $req
     * @return WP_REST_Response
     */
    public static function list( $req ) {
        $include_hidden = ( isset( $req['include_hidden'] ) && intval( $req['include_hidden'] ) === 1 && current_user_can( 'manage_options' ) );
        $model = new AdviceModel();
        $rows  = $model->get_all( ! $include_hidden );
        return Roro_REST_Utils::respond( $rows );
    }

    /**
     * Retrieve a single advice entry by OPAM_ID.
     *
     * @param WP_REST_Request $req
     * @return WP_REST_Response
     */
    public static function get_one( $req ) {
        $id    = $req['id'];
        $model = new AdviceModel();
        $row   = $model->get_by_id( $id );
        if ( empty( $row ) ) {
            return Roro_REST_Utils::respond( null, 404 );
        }
        return Roro_REST_Utils::respond( $row );
    }

    /**
     * Return a random advice entry.  Optional query param pet_type can be
     * provided (DOG, CAT, OTHER).  Only visible entries are considered.
     *
     * @param WP_REST_Request $req
     * @return WP_REST_Response
     */
    public static function random( $req ) {
        $pet_type = isset( $req['pet_type'] ) ? strtoupper( sanitize_text_field( $req['pet_type'] ) ) : null;
        if ( ! in_array( $pet_type, [ 'DOG', 'CAT', 'OTHER', null ], true ) ) {
            $pet_type = null;
        }
        $model = new AdviceModel();
        $row   = $model->get_random( $pet_type );
        if ( empty( $row ) ) {
            return Roro_REST_Utils::respond( null, 404 );
        }
        return Roro_REST_Utils::respond( $row );
    }

    /**
     * Create a new advice entry.  Accepts pet_type, category_code, title,
     * body, url, for_which_pets and isVisible.  Fields are sanitised.
     *
     * @param WP_REST_Request $req
     * @return WP_REST_Response
     */
    public static function create( $req ) {
        $data = [];
        $data['pet_type']    = strtoupper( sanitize_text_field( $req['pet_type'] ?? '' ) );
        if ( ! in_array( $data['pet_type'], [ 'DOG', 'CAT', 'OTHER' ], true ) ) {
            $data['pet_type'] = 'OTHER';
        }
        $data['category_code'] = sanitize_text_field( $req['category_code'] ?? '' );
        $data['title']       = sanitize_text_field( $req['title'] ?? '' );
        $data['body']        = wp_kses_post( $req['body'] ?? '' );
        $data['url']         = esc_url_raw( $req['url'] ?? '' );
        $data['for_which_pets'] = sanitize_text_field( $req['for_which_pets'] ?? '' );
        $data['isVisible']   = isset( $req['isVisible'] ) ? intval( $req['isVisible'] ) : 1;
        // Determine publication status; if not provided, default to draft
        $status = sanitize_text_field( $req['status'] ?? '' );
        if ( ! in_array( $status, [ 'draft', 'published' ], true ) ) {
            $status = 'draft';
        }
        $data['status'] = $status;
        $model = new AdviceModel();
        $id = $model->create( $data );
        // Insert status history row
        global $wpdb;
        $wpdb->insert( 'RORO_STATUS_HISTORY', [
            'table_name' => 'RORO_ONE_POINT_ADVICE_MASTER',
            'record_id'  => $id,
            'old_status' => null,
            'new_status' => $status,
            'changed_by' => get_current_user_id(),
        ] );
        return Roro_REST_Utils::respond( [ 'id' => $id ], 201 );
    }

    /**
     * Update an advice entry.  Accepts same fields as create.  Only
     * provided fields are updated.  Requires manage_options capability.
     *
     * @param WP_REST_Request $req
     * @return WP_REST_Response
     */
    public static function update( $req ) {
        $id = $req['id'];
        $data = [];
        if ( isset( $req['pet_type'] ) ) {
            $pt = strtoupper( sanitize_text_field( $req['pet_type'] ) );
            if ( in_array( $pt, [ 'DOG', 'CAT', 'OTHER' ], true ) ) {
                $data['pet_type'] = $pt;
            }
        }
        if ( isset( $req['category_code'] ) ) {
            $data['category_code'] = sanitize_text_field( $req['category_code'] );
        }
        if ( isset( $req['title'] ) ) {
            $data['title'] = sanitize_text_field( $req['title'] );
        }
        if ( isset( $req['body'] ) ) {
            $data['body'] = wp_kses_post( $req['body'] );
        }
        if ( isset( $req['url'] ) ) {
            $data['url'] = esc_url_raw( $req['url'] );
        }
        if ( isset( $req['for_which_pets'] ) ) {
            $data['for_which_pets'] = sanitize_text_field( $req['for_which_pets'] );
        }
        if ( isset( $req['isVisible'] ) ) {
            $data['isVisible'] = intval( $req['isVisible'] );
        }
        // Status update handling
        if ( isset( $req['status'] ) ) {
            $st = sanitize_text_field( $req['status'] );
            if ( in_array( $st, [ 'draft', 'published' ], true ) ) {
                $data['status'] = $st;
            }
        }
        $model = new AdviceModel();
        $model->update_entry( $id, $data );
        return Roro_REST_Utils::respond( [ 'ok' => true ] );
    }

    /**
     * Delete an advice entry by id.
     *
     * @param WP_REST_Request $req
     * @return WP_REST_Response
     */
    public static function delete( $req ) {
        $id = $req['id'];
        $model = new AdviceModel();
        $model->delete_entry( $id );
        return Roro_REST_Utils::respond( [ 'deleted' => true ] );
    }
}