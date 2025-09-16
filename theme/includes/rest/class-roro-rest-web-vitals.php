<?php
// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * REST endpoints to record Core Web Vitals metrics from the front‑end.
 *
 * The front‑end can post JSON containing an array of metrics or a single
 * metric.  Each entry must include a `metric` name (e.g. "LCP",
 * "CLS", "INP"), the numeric `value`, and the current page `url`.
 * Optionally, the client may include a `timestamp` (ISO8601), but the
 * server will default to the current time if omitted.  The logged in
 * WordPress user ID, if available, will be stored alongside the metric.
 */
class Roro_REST_Web_Vitals {
    const NS = 'roro/v1';

    /**
     * Register the web vitals route.  Open to all clients; no
     * authentication is required because the metrics contain no
     * sensitive data.  The current user ID (if any) is recorded.
     */
    public static function register() {
        register_rest_route( self::NS, '/web-vitals', [
            [
                'methods'             => 'POST',
                'callback'            => [ __CLASS__, 'record_metrics' ],
                'permission_callback' => '__return_true',
                'args'                => [
                    'metrics' => [
                        'required'          => false,
                        'validate_callback' => function ( $value ) {
                            return is_array( $value );
                        },
                    ],
                    'metric' => [ 'required' => false ],
                    'value'  => [ 'required' => false ],
                    'url'    => [ 'required' => false ],
                ],
            ],
        ] );
    }

    /**
     * Handle POST requests containing one or multiple metric samples.
     *
     * @param WP_REST_Request $req
     * @return WP_REST_Response
     */
    public static function record_metrics( $req ) {
        global $wpdb;
        $user_id = get_current_user_id() ?: null;
        $table   = 'RORO_WEB_VITALS';
        $rows    = [];
        // If the request contains a 'metrics' array, normalise it; otherwise
        // treat the root parameters as a single sample.
        $payload = $req->get_param( 'metrics' );
        if ( ! is_array( $payload ) ) {
            $metric = sanitize_text_field( $req->get_param( 'metric' ) );
            $value  = floatval( $req->get_param( 'value' ) );
            $url    = esc_url_raw( $req->get_param( 'url' ) );
            if ( ! empty( $metric ) && $value >= 0 && ! empty( $url ) ) {
                $payload = [ [ 'metric' => $metric, 'value' => $value, 'url' => $url ] ];
            } else {
                $payload = [];
            }
        }
        // Whitelisted metric names.  Only accept recognised Core Web Vitals to
        // prevent arbitrary data injection.  Value ranges are approximate
        // sanity bounds: LCP up to 10000ms, CLS up to 5 and INP up to 5000ms.
        $allowed_metrics = [ 'LCP' => [ 0, 10000 ], 'CLS' => [ 0, 5 ], 'INP' => [ 0, 5000 ] ];
        $count = 0;
        foreach ( $payload as $item ) {
            if ( $count > 50 ) {
                break; // prevent excessive inserts in a single request
            }
            $metric = isset( $item['metric'] ) ? strtoupper( sanitize_text_field( $item['metric'] ) ) : '';
            $value  = isset( $item['value'] ) ? floatval( $item['value'] ) : null;
            $url    = isset( $item['url'] ) ? esc_url_raw( $item['url'] ) : '';
            if ( isset( $allowed_metrics[ $metric ] ) && $value !== null && $url ) {
                list( $minVal, $maxVal ) = $allowed_metrics[ $metric ];
                if ( $value < $minVal ) {
                    $value = $minVal;
                } elseif ( $value > $maxVal ) {
                    $value = $maxVal;
                }
                // Deduplicate: skip if the same metric, value, url and user were logged within the last minute.
                $table_name = $wpdb->prefix . $table;
                $duplicate = $wpdb->get_var( $wpdb->prepare(
                    "SELECT id FROM {$table_name} WHERE metric = %s AND url = %s AND value = %f AND wp_user_id <=> %s AND created_at >= DATE_SUB(NOW(), INTERVAL 1 MINUTE) LIMIT 1",
                    $metric,
                    $url,
                    $value,
                    $user_id
                ) );
                if ( ! $duplicate ) {
                    $rows[] = [
                        'wp_user_id' => $user_id,
                        'metric'     => $metric,
                        'value'      => $value,
                        'url'        => $url,
                        'created_at' => current_time( 'mysql' ),
                    ];
                    $count++;
                }
            }
        }
        if ( ! empty( $rows ) ) {
            // Build insert statement; use $wpdb->prefix if necessary
            $table_name = $wpdb->prefix . $table;
            foreach ( $rows as $r ) {
                $wpdb->insert( $table_name, $r, [ '%d','%s','%f','%s','%s' ] );
            }
        }
        return new WP_REST_Response( [ 'saved' => count( $rows ) ], 200 );
    }
}