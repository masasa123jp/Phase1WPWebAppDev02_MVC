<?php
/**
 * Roro_PostcodeController
 *
 * Provides a lightweight proxy to lookup Japanese address information from
 * a postal code.  The controller queries the third‑party "ポストくん" API
 * (https://postcode.teraren.com/) which is a drop‑in compatible API for
 * ZipCloud with additional romanised fields.  If that lookup fails, the
 * controller falls back to the official ZipCloud service.  Results are
 * normalised into a consistent structure and cached via transients to
 * mitigate API rate limits.  No API key is required.
 */

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Roro_PostcodeController {
    /**
     * Prefix for transient keys.  Transients are used to cache lookups
     * for 24 hours to reduce remote requests.  The key is composed of
     * this prefix and the normalised ZIP code.
     */
    const TRANSIENT_PREFIX = 'roro_zip_';

    /**
     * Register the REST route.  This method is hooked on rest_api_init.
     */
    public function register_routes() {
        register_rest_route( 'roro-geo/v1', '/postcode', array(
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => array( $this, 'lookup' ),
            'permission_callback' => '__return_true',
            'args'                => array(
                'zip' => array(
                    'required'          => true,
                    'sanitize_callback' => array( $this, 'sanitize_zip' ),
                    'validate_callback' => array( $this, 'validate_zip' ),
                    'description'       => 'Numeric postal code (3–7 digits)'
                ),
            ),
        ) );
    }

    /**
     * Sanitize the zip parameter by stripping non‑digit characters.  Returns
     * null if nothing remains after sanitisation.
     *
     * @param mixed $value Raw zip parameter
     * @return string|null Sanitised zip or null
     */
    public function sanitize_zip( $value ) {
        $zip = preg_replace( '/[^0-9]/', '', (string) $value );
        return $zip !== '' ? $zip : null;
    }

    /**
     * Validate the zip parameter.  Accepts 3 to 7 digit strings.  Returns
     * true for valid values or a WP_Error for invalid values.
     *
     * @param string $value Sanitised zip
     * @param WP_REST_Request $request Request object
     * @param string $param Parameter name
     * @return true|WP_Error
     */
    public function validate_zip( $value, $request, $param ) {
        if ( is_null( $value ) || ! is_string( $value ) ) {
            return new WP_Error( 'invalid_zip', 'Invalid postal code', array( 'status' => 400 ) );
        }
        $len = strlen( $value );
        if ( $len < 3 || $len > 7 ) {
            return new WP_Error( 'invalid_zip_length', 'Postal code must be between 3 and 7 digits', array( 'status' => 400 ) );
        }
        return true;
    }

    /**
     * Perform the postcode lookup.  Attempts ポストくん first and falls back to ZipCloud.
     *
     * @param WP_REST_Request $request Request object
     * @return WP_REST_Response|WP_Error Response data or error
     */
    public function lookup( WP_REST_Request $request ) {
        $zip = $request->get_param( 'zip' );
        // Read runtime settings: allow postcode lookup and cache TTL (in seconds)
        $enabled = get_option( 'roro_geo_zip_enabled', true );
        // If disabled via settings, short‑circuit with a 503 error
        if ( ! $enabled ) {
            return new WP_Error( 'disabled', 'postcode lookup disabled', array( 'status' => 503 ) );
        }
        $ttl = intval( get_option( 'roro_geo_cache_ttl', DAY_IN_SECONDS ) );
        // Cache key keyed by numeric zip
        $cache_key = self::TRANSIENT_PREFIX . $zip;
        $cached    = get_transient( $cache_key );
        if ( $cached ) {
            return rest_ensure_response( $cached );
        }
        // 1) ポストくん (ZipCloud互換 + romaji).  It returns JSON similar to:
        // { status: 200, message: null, results: [ { address1, address2, address3, kana1, kana2, kana3, 
        //   prefecture_roma, city_roma, town_roma } ] }
        $data = null;
        $url1 = 'https://postcode.teraren.com/api?zipcode=' . rawurlencode( $zip );
        $resp1 = wp_remote_get( $url1, array( 'timeout' => 6 ) );
        if ( ! is_wp_error( $resp1 ) && (int) wp_remote_retrieve_response_code( $resp1 ) === 200 ) {
            $body = wp_remote_retrieve_body( $resp1 );
            $json = json_decode( $body, true );
            $data = $this->normalize_zipcloud_like( $json );
        }
        // 2) Fallback: ZipCloud (漢字のみ).  Only call if no data or incomplete.
        if ( ! $data ) {
            $url2  = 'https://zipcloud.ibsnet.co.jp/api/search?zipcode=' . rawurlencode( $zip );
            $resp2 = wp_remote_get( $url2, array( 'timeout' => 6 ) );
            if ( ! is_wp_error( $resp2 ) && (int) wp_remote_retrieve_response_code( $resp2 ) === 200 ) {
                $body2 = wp_remote_retrieve_body( $resp2 );
                $json2 = json_decode( $body2, true );
                $data  = $this->normalize_zipcloud_like( $json2 );
            }
        }
        if ( ! $data ) {
            return new WP_Error( 'not_found', 'No address found for this postal code', array( 'status' => 404 ) );
        }
        // Cache results using TTL from settings.  A TTL of 0 disables caching.
        if ( $ttl > 0 ) {
            set_transient( $cache_key, $data, $ttl );
        }
        return rest_ensure_response( $data );
    }

    /**
     * Normalise ZipCloud style response into a uniform associative array with keys:
     * pref, city, town, pref_kana, city_kana, town_kana, pref_roma, city_roma, town_roma.
     * Returns null if the data structure is not as expected or no results exist.
     *
     * @param array|null $json Parsed JSON from the API
     * @return array|null Normalised associative array or null
     */
    private function normalize_zipcloud_like( $json ) {
        if ( ! is_array( $json ) || ! isset( $json['results'] ) || ! is_array( $json['results'] ) || empty( $json['results'] ) ) {
            return null;
        }
        // Use the first result only; additional results indicate split codes but we ignore them.
        $r = $json['results'][0];
        $norm = array(
            'pref'      => isset( $r['address1'] ) ? (string) $r['address1'] : null,
            'city'      => isset( $r['address2'] ) ? (string) $r['address2'] : null,
            'town'      => isset( $r['address3'] ) ? (string) $r['address3'] : null,
            'pref_kana' => isset( $r['kana1'] ) ? (string) $r['kana1'] : null,
            'city_kana' => isset( $r['kana2'] ) ? (string) $r['kana2'] : null,
            'town_kana' => isset( $r['kana3'] ) ? (string) $r['kana3'] : null,
            'pref_roma' => isset( $r['prefecture_roma'] ) ? (string) $r['prefecture_roma'] : null,
            'city_roma' => isset( $r['city_roma'] ) ? (string) $r['city_roma'] : null,
            'town_roma' => isset( $r['town_roma'] ) ? (string) $r['town_roma'] : null,
        );
        return $norm;
    }
}