<?php
/**
 * AuthController implements OAuth based social login flows for Google and LINE.
 *
 * This controller exposes REST endpoints under the `roro-auth/v1` namespace
 * which return an authorization URL for each provider and handle the callback
 * to exchange the authorization code for tokens, decode the ID token and
 * synchronise the authenticated user with WordPress and the RORO tables.
 *
 * NOTE: In this version we implement PKCE and basic JWT signature
 * verification using the provider's JSON Web Key Set (JWKS).  The
 * implementation verifies the ID token signature using openssl and
 * checks the audience (`aud`), issuer (`iss`), expiration (`exp`), and
 * nonce stored during the login flow.  Ensure that your site runs
 * under HTTPS and that secrets remain confidential.
 */

require_once __DIR__ . '/BaseController.php';
require_once __DIR__ . '/../models/CustomerModel.php';

class AuthController extends BaseController {
    /**
     * Register REST routes.  Attach this method to the `rest_api_init` action.
     */
    public function register_routes() {
        register_rest_route( 'roro-auth/v1', '/google/login', [
            'methods'             => 'GET',
            'callback'            => [ $this, 'google_login' ],
            'permission_callback' => '__return_true',
        ] );
        register_rest_route( 'roro-auth/v1', '/google/callback', [
            'methods'             => 'GET',
            'callback'            => [ $this, 'google_callback' ],
            'permission_callback' => '__return_true',
        ] );
        register_rest_route( 'roro-auth/v1', '/line/login', [
            'methods'             => 'GET',
            'callback'            => [ $this, 'line_login' ],
            'permission_callback' => '__return_true',
        ] );
        register_rest_route( 'roro-auth/v1', '/line/callback', [
            'methods'             => 'GET',
            'callback'            => [ $this, 'line_callback' ],
            'permission_callback' => '__return_true',
        ] );
    }

    /**
     * Generate a high entropy code verifier for PKCE.  Returns a URL-safe
     * base64 string between 43 and 128 characters.  Uses random_bytes
     * internally.
     *
     * @return string
     */
    private function generate_code_verifier() {
        $bytes = random_bytes( 32 ); // 256 bits
        return rtrim( strtr( base64_encode( $bytes ), '+/', '-_' ), '=' );
    }

    /**
     * Create a code challenge from a verifier using S256 method.  Returns
     * a URL-safe base64 string.
     *
     * @param string $verifier
     * @return string
     */
    private function code_challenge_s256( $verifier ) {
        $hash = hash( 'sha256', $verifier, true );
        return rtrim( strtr( base64_encode( $hash ), '+/', '-_' ), '=' );
    }

    /**
     * Fetch JWKS from a URL and return the decoded JSON.  Caches the
     * response in a transient for 12 hours to reduce latency.  Returns
     * an array with `keys` or an empty array on error.
     *
     * @param string $url
     * @return array
     */
    private function fetch_jwks( $url ) {
        $cache_key = 'roro_jwks_' . md5( $url );
        $jwks = get_transient( $cache_key );
        if ( ! $jwks ) {
            $resp = wp_remote_get( $url, [ 'timeout' => 10 ] );
            if ( is_wp_error( $resp ) ) {
                return [ 'keys' => [] ];
            }
            $jwks = json_decode( wp_remote_retrieve_body( $resp ), true );
            if ( $jwks ) {
                set_transient( $cache_key, $jwks, 12 * HOUR_IN_SECONDS );
            }
        }
        return is_array( $jwks ) ? $jwks : [ 'keys' => [] ];
    }

    /**
     * Convert a JWK (RSA) to a PEM formatted public key.  Only handles
     * RSA keys with modulus `n` and exponent `e`.  Returns the PEM string
     * or null on failure.
     *
     * @param array $jwk
     * @return string|null
     */
    private function jwk_to_pem( $jwk ) {
        if ( ! isset( $jwk['n'] ) || ! isset( $jwk['e'] ) ) {
            return null;
        }
        $n = $this->base64url_decode( $jwk['n'] );
        $e = $this->base64url_decode( $jwk['e'] );
        // Build ASN.1 structure for RSA public key
        $components = [];
        // SEQUENCE
        $components[] = "30";
        // INTEGER (n)
        $components[] = $this->asn1_integer( $n );
        // INTEGER (e)
        $components[] = $this->asn1_integer( $e );
        $body = implode( '', $components );
        $body = $this->hex2bin_str( $body );
        // Prepend sequence header with length
        $seq = "30" . $this->asn1_length( strlen( $body ) ) . bin2hex( $body );
        $bitstring = "03" . $this->asn1_length( strlen( $seq ) / 2 + 1 ) . "00" . $seq;
        // AlgorithmIdentifier for RSA:
        $alg = "300d06092a864886f70d0101010500"; // OID 1.2.840.113549.1.1.1 + NULL param
        $seq2 = "30" . $this->asn1_length( ( strlen( $alg ) + strlen( $bitstring ) ) / 2 ) . $alg . $bitstring;
        $pem = "-----BEGIN PUBLIC KEY-----\n" . chunk_split( base64_encode( $this->hex2bin_str( $seq2 ) ), 64, "\n" ) . "-----END PUBLIC KEY-----\n";
        return $pem;
    }

    /**
     * Verify a JWT using the given JWKS.  Returns the payload array if
     * verification succeeds, or null on failure.  Also checks aud, iss,
     * exp, iat, and nonce if provided.
     *
     * @param string $jwt
     * @param array $jwks
     * @param string $expected_aud
     * @param string|array $expected_iss
     * @param string|null $expected_nonce
     * @return array|null
     */
    private function verify_jwt( $jwt, $jwks, $expected_aud, $expected_iss, $expected_nonce = null ) {
        $parts = explode( '.', $jwt );
        if ( count( $parts ) !== 3 ) {
            return null;
        }
        list( $header_b64, $payload_b64, $sig_b64 ) = $parts;
        // Decode header and payload
        $header = json_decode( $this->base64url_decode( $header_b64 ), true );
        $payload= json_decode( $this->base64url_decode( $payload_b64 ), true );
        if ( ! is_array( $header ) || ! is_array( $payload ) ) {
            return null;
        }
        // Find matching key by kid
        $kid = isset( $header['kid'] ) ? $header['kid'] : null;
        $pem = null;
        if ( $kid && isset( $jwks['keys'] ) ) {
            foreach ( $jwks['keys'] as $jwk ) {
                if ( isset( $jwk['kid'] ) && $jwk['kid'] === $kid ) {
                    $pem = $this->jwk_to_pem( $jwk );
                    break;
                }
            }
        }
        if ( ! $pem ) {
            return null;
        }
        // Verify signature (RS256) using openssl
        $data = $header_b64 . '.' . $payload_b64;
        $signature = $this->base64url_decode( $sig_b64 );
        $ok = openssl_verify( $data, $signature, $pem, OPENSSL_ALGO_SHA256 );
        if ( $ok !== 1 ) {
            return null;
        }
        // Validate claims
        $aud = isset( $payload['aud'] ) ? $payload['aud'] : null;
        $iss = isset( $payload['iss'] ) ? $payload['iss'] : null;
        $exp = isset( $payload['exp'] ) ? intval( $payload['exp'] ) : 0;
        $iat = isset( $payload['iat'] ) ? intval( $payload['iat'] ) : 0;
        $nonce = isset( $payload['nonce'] ) ? $payload['nonce'] : null;
        $now = time();
        // aud may be array or string
        $aud_ok = false;
        if ( is_array( $aud ) ) {
            $aud_ok = in_array( $expected_aud, $aud, true );
        } else {
            $aud_ok = ( $aud === $expected_aud );
        }
        $iss_ok = false;
        if ( is_array( $expected_iss ) ) {
            $iss_ok = in_array( $iss, $expected_iss, true );
        } else {
            $iss_ok = ( $iss === $expected_iss );
        }
        if ( ! $aud_ok || ! $iss_ok ) {
            return null;
        }
        if ( $exp < $now - 300 ) { // allow 5 min skew
            return null;
        }
        if ( $iat > $now + 300 ) {
            return null;
        }
        if ( $expected_nonce !== null && $nonce !== $expected_nonce ) {
            return null;
        }
        return $payload;
    }

    /**
     * Base64url decode helper.  Adds padding if needed.
     *
     * @param string $data
     * @return string
     */
    private function base64url_decode( $data ) {
        $data = strtr( $data, '-_', '+/' );
        $pad = strlen( $data ) % 4;
        if ( $pad ) {
            $data .= str_repeat( '=', 4 - $pad );
        }
        return base64_decode( $data );
    }

    /**
     * Helper to convert a binary integer into ASN.1 encoded form.  Used
     * when constructing an RSA public key.  Returns a hex string.
     *
     * @param string $int
     * @return string
     */
    private function asn1_integer( $int ) {
        // Ensure positive by prefixing zero if MSB is set
        $h = bin2hex( $int );
        if ( ( ord( $int[0] ) & 0x80 ) ) {
            $h = '00' . $h;
        }
        return '02' . $this->asn1_length( strlen( $h ) / 2 ) . $h;
    }

    /**
     * Encode length in ASN.1 DER format.  Returns a hex string.
     *
     * @param int $len
     * @return string
     */
    private function asn1_length( $len ) {
        if ( $len < 0x80 ) {
            return sprintf( '%02x', $len );
        }
        $len_hex = dechex( $len );
        if ( strlen( $len_hex ) % 2 ) {
            $len_hex = '0' . $len_hex;
        }
        $n = strlen( $len_hex ) / 2;
        return sprintf( '%02x', 0x80 | $n ) . $len_hex;
    }

    /**
     * Convert a hex string to binary string.  Wrapper for hex2bin with
     * fallback for older PHP versions.
     *
     * @param string $h
     * @return string
     */
    private function hex2bin_str( $h ) {
        if ( function_exists( 'hex2bin' ) ) {
            return hex2bin( $h );
        }
        $r = '';
        for ( $i = 0; $i < strlen( $h ); $i += 2 ) {
            $r .= chr( hexdec( $h[$i] . $h[$i+1] ) );
        }
        return $r;
    }

    /**
     * Generate a Google OAuth authorization URL.  The client id and
     * additional settings should be configured via the WordPress options
     * `roro_google_client_id` and `roro_google_redirect_uri`.  The state
     * parameter is a nonce to guard against CSRF attacks.
     *
     * @return WP_REST_Response
     */
    public function google_login() {
        $client_id = get_option( 'roro_google_client_id' );
        $redirect  = home_url( '/wp-json/roro-auth/v1/google/callback' );
        $scope     = urlencode( 'openid email profile' );
        if ( empty( $client_id ) ) {
            return new WP_REST_Response( [ 'error' => 'Google client id is not configured.' ], 500 );
        }
        // Generate state, nonce and PKCE verifier/challenge.  Store
        // verifier and nonce in a transient keyed by state for use in the
        // callback.  The transient expires after 20 minutes.
        $state        = wp_generate_uuid4();
        $nonce        = wp_generate_uuid4();
        $code_verifier = $this->generate_code_verifier();
        $code_challenge = $this->code_challenge_s256( $code_verifier );
        set_transient( 'roro_google_auth_' . $state, [
            'code_verifier' => $code_verifier,
            'nonce'         => $nonce,
        ], 20 * MINUTE_IN_SECONDS );
        // Build authorization URL with PKCE parameters
        $auth_url = 'https://accounts.google.com/o/oauth2/v2/auth'
            . '?response_type=code'
            . '&client_id=' . rawurlencode( $client_id )
            . '&redirect_uri=' . rawurlencode( $redirect )
            . '&scope=' . $scope
            . '&state=' . rawurlencode( $state )
            . '&nonce=' . rawurlencode( $nonce )
            . '&code_challenge=' . rawurlencode( $code_challenge )
            . '&code_challenge_method=S256';
        return new WP_REST_Response( [ 'auth_url' => $auth_url ] );
    }

    /**
     * Handle the Google OAuth callback.  Exchange the code for tokens, decode
     * the ID token, create or update WordPress and RORO users, log the
     * visitor in, and redirect to the profile page.
     *
     * @param WP_REST_Request $request
     * @return void|WP_Error
     */
    public function google_callback( WP_REST_Request $request ) {
        $code  = $request->get_param( 'code' );
        $state = $request->get_param( 'state' );
        if ( empty( $code ) || empty( $state ) ) {
            return new WP_Error( 'bad_request', 'Missing code or state', [ 'status' => 400 ] );
        }
        // Retrieve verifier/nonce saved during login
        $transient_key = 'roro_google_auth_' . $state;
        $stored = get_transient( $transient_key );
        if ( ! $stored || ! is_array( $stored ) ) {
            return new WP_Error( 'invalid_state', 'State expired or invalid', [ 'status' => 400 ] );
        }
        $code_verifier = $stored['code_verifier'];
        $nonce         = $stored['nonce'];
        // Remove transient after retrieval
        delete_transient( $transient_key );
        $client_id     = get_option( 'roro_google_client_id' );
        $client_secret = get_option( 'roro_google_client_secret' );
        $redirect      = home_url( '/wp-json/roro-auth/v1/google/callback' );
        // Exchange code for tokens using code_verifier (PKCE)
        $response = wp_remote_post( 'https://oauth2.googleapis.com/token', [
            'body' => [
                'code'          => $code,
                'client_id'     => $client_id,
                'client_secret' => $client_secret,
                'redirect_uri'  => $redirect,
                'grant_type'    => 'authorization_code',
                'code_verifier' => $code_verifier,
            ],
            'timeout' => 15,
        ] );
        if ( is_wp_error( $response ) ) {
            return $response;
        }
        $body = json_decode( wp_remote_retrieve_body( $response ), true );
        if ( ! isset( $body['id_token'] ) ) {
            return new WP_Error( 'no_token', 'ID token not returned', [ 'status' => 500 ] );
        }
        $id_token = $body['id_token'];
        // Fetch Google's JWKS and verify ID token
        $jwks = $this->fetch_jwks( 'https://www.googleapis.com/oauth2/v3/certs' );
        $payload = $this->verify_jwt( $id_token, $jwks, $client_id, [ 'https://accounts.google.com', 'accounts.google.com' ], $nonce );
        if ( ! $payload ) {
            return new WP_Error( 'invalid_token', 'Failed to verify ID token', [ 'status' => 400 ] );
        }
        // Extract profile information
        $email = isset( $payload['email'] ) ? $payload['email'] : null;
        $name  = isset( $payload['name'] ) ? $payload['name'] : null;
        $sub   = isset( $payload['sub'] ) ? $payload['sub'] : null;
        if ( empty( $email ) ) {
            $email = 'google-' . $sub . '@example.com';
        }
        // Create or update WordPress user
        $user_id = email_exists( $email );
        if ( ! $user_id ) {
            $password = wp_generate_password( 20 );
            $user_id  = wp_create_user( $email, $password, $email );
            if ( is_wp_error( $user_id ) ) {
                return $user_id;
            }
        }
        $user = get_user_by( 'id', $user_id );
        if ( $name && $user && empty( $user->display_name ) ) {
            wp_update_user( [ 'ID' => $user_id, 'display_name' => $name ] );
        }
        // Create or fetch customer
        $customer_model = new CustomerModel();
        $existing       = $customer_model->get_by_email( $email );
        if ( $existing ) {
            $customer_id = (int) $existing->customer_id;
        } else {
            $customer_id = $customer_model->create( $email );
        }
        // Link WP user and customer
        global $wpdb;
        $link_table = $wpdb->prefix . 'RORO_USER_LINK_WP';
        $wpdb->delete( $link_table, [ 'wp_user_id' => $user_id ] );
        $wpdb->insert( $link_table, [
            'customer_id' => $customer_id,
            'wp_user_id'  => $user_id,
            'linked_at'   => current_time( 'mysql' ),
        ] );
        // Log the user in
        wp_set_current_user( $user_id );
        wp_set_auth_cookie( $user_id );
        wp_safe_redirect( home_url( '/profile/' ) );
        exit;
    }

    /**
     * Generate a LINE OAuth authorization URL.  The client id (channel id)
     * and settings should be configured via the options
     * `roro_line_client_id` and `roro_line_client_secret`.
     *
     * @return WP_REST_Response
     */
    public function line_login() {
        $client_id = get_option( 'roro_line_client_id' );
        $redirect  = home_url( '/wp-json/roro-auth/v1/line/callback' );
        if ( empty( $client_id ) ) {
            return new WP_REST_Response( [ 'error' => 'LINE client id is not configured.' ], 500 );
        }
        // Generate state, nonce, and PKCE challenge
        $state         = wp_generate_uuid4();
        $nonce         = wp_generate_uuid4();
        $code_verifier = $this->generate_code_verifier();
        $code_challenge= $this->code_challenge_s256( $code_verifier );
        set_transient( 'roro_line_auth_' . $state, [
            'code_verifier' => $code_verifier,
            'nonce'         => $nonce,
        ], 20 * MINUTE_IN_SECONDS );
        $scope = urlencode( 'profile openid email' );
        $auth_url = 'https://access.line.me/oauth2/v2.1/authorize'
            . '?response_type=code'
            . '&client_id=' . rawurlencode( $client_id )
            . '&redirect_uri=' . rawurlencode( $redirect )
            . '&scope=' . $scope
            . '&state=' . rawurlencode( $state )
            . '&nonce=' . rawurlencode( $nonce )
            . '&code_challenge=' . rawurlencode( $code_challenge )
            . '&code_challenge_method=S256';
        return new WP_REST_Response( [ 'auth_url' => $auth_url ] );
    }

    /**
     * Handle the LINE OAuth callback.  Exchange the code for an access
     * token, fetch the user profile, create/update WordPress and RORO
     * users, log the user in, and redirect.  Note that LINE may not
     * provide an email; we synthesise one in that case.
     *
     * @param WP_REST_Request $request
     * @return void|WP_Error
     */
    public function line_callback( WP_REST_Request $request ) {
        $code  = $request->get_param( 'code' );
        $state = $request->get_param( 'state' );
        if ( empty( $code ) || empty( $state ) ) {
            return new WP_Error( 'bad_request', 'Missing code or state', [ 'status' => 400 ] );
        }
        // Retrieve stored PKCE verifier and nonce from transient using the state
        $transient_key = 'roro_line_auth_' . $state;
        $stored = get_transient( $transient_key );
        if ( ! $stored || ! is_array( $stored ) ) {
            return new WP_Error( 'invalid_state', 'State expired or invalid', [ 'status' => 400 ] );
        }
        $code_verifier = $stored['code_verifier'];
        $nonce         = $stored['nonce'];
        delete_transient( $transient_key );
        $client_id     = get_option( 'roro_line_client_id' );
        $client_secret = get_option( 'roro_line_client_secret' );
        $redirect      = home_url( '/wp-json/roro-auth/v1/line/callback' );
        // Exchange code for access token and ID token using PKCE (include code_verifier)
        $response = wp_remote_post( 'https://api.line.me/oauth2/v2.1/token', [
            'body' => [
                'grant_type'    => 'authorization_code',
                'code'          => $code,
                'redirect_uri'  => $redirect,
                'client_id'     => $client_id,
                'client_secret' => $client_secret,
                'code_verifier' => $code_verifier,
            ],
            'timeout' => 15,
        ] );
        if ( is_wp_error( $response ) ) {
            return $response;
        }
        $body = json_decode( wp_remote_retrieve_body( $response ), true );
        // Expect id_token from LINE token endpoint
        if ( ! isset( $body['id_token'] ) ) {
            return new WP_Error( 'no_id_token', 'ID token not returned', [ 'status' => 500 ] );
        }
        $id_token = $body['id_token'];
        // Verify ID token using LINE JWKS
        $jwks = $this->fetch_jwks( 'https://api.line.me/oauth2/v2.1/certs' );
        // Accept both access.line.me and api.line.me as issuers
        $payload = $this->verify_jwt( $id_token, $jwks, $client_id, [ 'https://access.line.me', 'https://api.line.me' ], $nonce );
        if ( ! $payload ) {
            return new WP_Error( 'invalid_token', 'Failed to verify LINE ID token', [ 'status' => 400 ] );
        }
        // Extract subject and email/name if present
        $sub  = isset( $payload['sub'] ) ? $payload['sub'] : null;
        $email= isset( $payload['email'] ) ? $payload['email'] : null;
        $name = isset( $payload['name'] ) ? $payload['name'] : null;
        if ( empty( $email ) && $sub ) {
            $email = 'line-' . $sub . '@example.com';
        }
        // If name missing, fallback to later profile
        // Use access token to fetch profile (displayName)
        $access_token = isset( $body['access_token'] ) ? $body['access_token'] : null;
        if ( $access_token ) {
            $profile_resp = wp_remote_get( 'https://api.line.me/v2/profile', [
                'headers' => [ 'Authorization' => 'Bearer ' . $access_token ],
                'timeout' => 15,
            ] );
            if ( ! is_wp_error( $profile_resp ) ) {
                $pbody = json_decode( wp_remote_retrieve_body( $profile_resp ), true );
                if ( isset( $pbody['displayName'] ) && empty( $name ) ) {
                    $name = $pbody['displayName'];
                }
            }
        }
        if ( empty( $name ) && $sub ) {
            $name = $sub;
        }
        // Create or update WordPress user
        $user_id = email_exists( $email );
        if ( ! $user_id ) {
            $password = wp_generate_password( 20 );
            $user_id  = wp_create_user( $email, $password, $email );
            if ( is_wp_error( $user_id ) ) {
                return $user_id;
            }
        }
        $user = get_user_by( 'id', $user_id );
        if ( $name && $user && empty( $user->display_name ) ) {
            wp_update_user( [ 'ID' => $user_id, 'display_name' => $name ] );
        }
        // Create or fetch customer
        $customer_model = new CustomerModel();
        $existing       = $customer_model->get_by_email( $email );
        if ( $existing ) {
            $customer_id = (int) $existing->customer_id;
        } else {
            $customer_id = $customer_model->create( $email );
        }
        // Link WP user and customer
        global $wpdb;
        $link_table = $wpdb->prefix . 'RORO_USER_LINK_WP';
        // Remove previous link if exists
        $wpdb->delete( $link_table, [ 'wp_user_id' => $user_id ] );
        $wpdb->insert( $link_table, [
            'customer_id' => $customer_id,
            'wp_user_id'  => $user_id,
            'linked_at'   => current_time( 'mysql' ),
        ] );
        // Log the user in
        wp_set_current_user( $user_id );
        wp_set_auth_cookie( $user_id );
        wp_safe_redirect( home_url( '/profile/' ) );
        exit;
    }

    /**
     * Decode the payload of a JWT.  Assumes the token is in the format
     * header.payload.signature and that the payload is base64url encoded.
     *
     * @param string $jwt
     * @return array|null
     */
    private function decode_jwt_payload( $jwt ) {
        $parts = explode( '.', $jwt );
        if ( count( $parts ) !== 3 ) {
            return null;
        }
        $payload = $parts[1];
        // Pad base64url to base64
        $payload .= str_repeat( '=', 4 - ( strlen( $payload ) % 4 ) );
        $payload = strtr( $payload, '-_', '+/' );
        $json    = base64_decode( $payload );
        return json_decode( $json, true );
    }
}