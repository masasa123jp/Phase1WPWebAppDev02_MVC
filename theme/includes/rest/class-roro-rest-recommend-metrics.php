<?php
/**
 * Class Roro_Rest_Recommend_Metrics (Stage32)
 *
 * 拡張内容：
 * - Stage31 で実装した A/B テスト基盤と KPI 可視化に対し、クリック率（CTR）の統計的検定を追加。
 *   管理画面で露出数とクリック数から２つのバリアント間の z 検定を実行し、p 値と有意性を表示します。
 * - 既存機能（重複抑止・A/B 割当・イベント記録・管理 UI）は維持し、追加計算のみ実施します。
 *
 * 注意：このファイルは Stage31 のクラス定義を元にしています。WordPress の REST ルーティング、
 *       dbDelta によるテーブル作成、A/B 割当 API や管理画面の構造は Stage31 と同じです。
 */

if ( ! class_exists( 'Roro_Rest_Recommend_Metrics' ) ) :

class Roro_Rest_Recommend_Metrics {

    /** @var string REST namespace */
    private $namespace = 'roro/v1';

    /** @var string cookie key for anonymous session id */
    private $sid_cookie = 'roro_sid';

    public function __construct() {
        add_action( 'rest_api_init', array( $this, 'register_routes' ) );
        add_action( 'admin_menu', array( $this, 'register_admin_menu' ) );
        add_action( 'admin_init', array( $this, 'maybe_create_ab_tables' ) );
        add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_front_assets' ) );
    }

    /**
     * Register REST API routes.
     *
     * 同名の関数内では Stage31 と同様のルート登録を行います。
     */
    public function register_routes() {
        register_rest_route( $this->namespace, '/recommend-events-hit', array(
            'methods'             => 'POST',
            'callback'            => array( $this, 'record_recommend_hit' ),
            'permission_callback' => '__return_true',
            'args' => array(
                'event_id'   => array( 'required' => true,  'sanitize_callback' => 'absint' ),
                'experiment' => array( 'required' => false, 'sanitize_callback' => array( $this, 'sanitize_key_short' ) ),
                'variant'    => array( 'required' => false, 'sanitize_callback' => array( $this, 'sanitize_key_short' ) ),
                'context'    => array( 'required' => false, 'sanitize_callback' => 'sanitize_text_field' ),
            ),
        ) );

        register_rest_route( $this->namespace, '/recommend-events-report', array(
            'methods'             => 'GET',
            'callback'            => array( $this, 'report_recommend_clicks' ),
            'permission_callback' => array( $this, 'require_manage_options' ),
            'args' => array(
                'since' => array( 'required' => false, 'sanitize_callback' => array( $this, 'sanitize_date' ) ),
                'until' => array( 'required' => false, 'sanitize_callback' => array( $this, 'sanitize_date' ) ),
                'limit' => array( 'required' => false, 'sanitize_callback' => 'absint', 'default' => 100 ),
            ),
        ) );

        // --- A/B testing ---
        register_rest_route( $this->namespace, '/ab/assign', array(
            'methods'             => 'GET',
            'callback'            => array( $this, 'ab_assign' ),
            'permission_callback' => '__return_true',
            'args' => array(
                'experiment' => array( 'required' => true, 'sanitize_callback' => array( $this, 'sanitize_key_short' ) ),
                'variants'   => array( 'required' => true, 'sanitize_callback' => array( $this, 'sanitize_csv_variants' ) ),
                'split'      => array( 'required' => false, 'sanitize_callback' => 'absint', 'default' => 50 ),
            ),
        ) );

        register_rest_route( $this->namespace, '/ab/event', array(
            'methods'             => 'POST',
            'callback'            => array( $this, 'ab_event' ),
            'permission_callback' => '__return_true',
            'args' => array(
                'experiment' => array( 'required' => true,  'sanitize_callback' => array( $this, 'sanitize_key_short' ) ),
                'variant'    => array( 'required' => true,  'sanitize_callback' => array( $this, 'sanitize_key_short' ) ),
                'event_name' => array( 'required' => true,  'sanitize_callback' => array( $this, 'sanitize_key_short' ) ),
                'value'      => array( 'required' => false, 'sanitize_callback' => 'floatval', 'default' => 1.0 ),
                'context'    => array( 'required' => false, 'sanitize_callback' => 'sanitize_text_field' ),
            ),
        ) );
    }

    /** Permission: manage_options only */
    public function require_manage_options() {
        return current_user_can( 'manage_options' );
    }

    /** Sanitize YYYY-MM-DD */
    public function sanitize_date( $v ) {
        $v = sanitize_text_field( $v );
        if ( empty( $v ) ) return '';
        $d = date_create_from_format( 'Y-m-d', $v );
        return $d ? $d->format( 'Y-m-d' ) : '';
    }

    /** Sanitize short key (a-z0-9_- max 64) */
    public function sanitize_key_short( $v ) {
        $v = sanitize_key( $v );
        if ( strlen( $v ) > 64 ) $v = substr( $v, 0, 64 );
        return $v;
    }

    /** Sanitize CSV variants into array of up to 8 values */
    public function sanitize_csv_variants( $v ) {
        $v = sanitize_text_field( $v );
        $parts = array_filter( array_map( 'trim', explode( ',', $v ) ) );
        $out = array();
        foreach ( $parts as $p ) {
            $k = $this->sanitize_key_short( $p );
            if ( ! empty( $k ) ) $out[] = $k;
            if ( count( $out ) >= 8 ) break;
        }
        return $out;
    }

    /**
     * Ensure anonymous session id cookie (roro_sid). Returns string sid.
     */
    private function ensure_sid() {
        $sid = isset( $_COOKIE[ $this->sid_cookie ] ) ? sanitize_text_field( $_COOKIE[ $this->sid_cookie ] ) : '';
        if ( empty( $sid ) || ! preg_match( '/^[A-Za-z0-9]{16,40}$/', $sid ) ) {
            $sid = wp_generate_password( 20, false, false );
            setcookie( $this->sid_cookie, $sid, time() + 60*60*24*180, COOKIEPATH, COOKIE_DOMAIN, is_ssl(), true );
        }
        return $sid;
    }

    /**
     * POST /recommend-events-hit
     * Records a click for recommended event with 10s duplicate suppression.
     * Optionally records AB test click event.
     */
    public function record_recommend_hit( WP_REST_Request $req ) {
        global $wpdb;
        $event_id   = (int) $req['event_id'];
        $experiment = isset( $req['experiment'] ) ? $this->sanitize_key_short( $req['experiment'] ) : '';
        $variant    = isset( $req['variant'] ) ? $this->sanitize_key_short( $req['variant'] ) : '';

        if ( $event_id <= 0 ) {
            return new WP_REST_Response( array( 'ok' => false, 'error' => 'invalid_event_id' ), 400 );
        }

        $user_id = get_current_user_id();
        $sid = $this->ensure_sid();

        $table = $wpdb->prefix . 'roro_recommend_event_metrics';
        $now   = current_time( 'mysql', true );
        $now_ts = current_time( 'timestamp', true );

        // Duplicate suppression window: 10s
        $where_user = $user_id ? $wpdb->prepare( 'user_id = %d', $user_id ) : $wpdb->prepare( 'session_id = %s', $sid );
        $recent = $wpdb->get_var( $wpdb->prepare(
            "SELECT UNIX_TIMESTAMP(created_at) FROM {$table}
             WHERE event_id = %d AND {$where_user}
             ORDER BY id DESC LIMIT 1",
            $event_id
        ) );
        if ( $recent && ( (int)$recent >= ($now_ts - 10) ) ) {
            return array(
                'ok'        => true,
                'duplicate' => true,
                'suppressed_window_sec' => 10,
            );
        }

        // Insert metric
        $wpdb->insert( $table, array(
            'event_id'   => $event_id,
            'user_id'    => $user_id ? $user_id : null,
            'session_id' => $sid,
            'context'    => isset( $req['context'] ) ? sanitize_text_field( $req['context'] ) : '',
            'created_at' => $now,
        ), array( '%d', '%d', '%s', '%s', '%s' ) );
        $inserted_id = $wpdb->insert_id;

        // If AB info present, store AB click event
        if ( $experiment && $variant ) {
            $this->insert_ab_event( $user_id, $sid, $experiment, $variant, 'click', 1.0, 'recommend-events-hit' );
        }

        return array(
            'ok'       => true,
            'id'       => (int) $inserted_id,
            'duplicate'=> false,
        );
    }

    /**
     * GET /recommend-events-report
     * Aggregate clicks by event within a date range. Admin only.
     */
    public function report_recommend_clicks( WP_REST_Request $req ) {
        global $wpdb;
        $since = $req['since'] ?: gmdate( 'Y-m-d', time() - 7*24*3600 );
        $until = $req['until'] ?: gmdate( 'Y-m-d' );
        $limit = (int) $req['limit'];

        $table = $wpdb->prefix . 'roro_recommend_event_metrics';
        $sql = $wpdb->prepare(
            "SELECT event_id, COUNT(*) AS clicks
               FROM {$table}
              WHERE created_at BETWEEN %s AND %s
           GROUP BY event_id
           ORDER BY clicks DESC
              LIMIT %d",
            $since . ' 00:00:00', $until . ' 23:59:59', $limit
        );
        $rows = $wpdb->get_results( $sql, ARRAY_A );
        return array(
            'ok'    => true,
            'since' => $since,
            'until' => $until,
            'rows'  => $rows,
        );
    }

    /**
     * GET /ab/assign?experiment=...&variants=A,B&split=50
     * Assigns a stable variant per user/session; persists to cookie & DB.
     */
    public function ab_assign( WP_REST_Request $req ) {
        global $wpdb;
        $exp = $req['experiment'];
        $variants = (array) $req['variants'];
        $split = (int) $req['split'];
        if ( count( $variants ) < 2 ) {
            return array( 'ok' => false, 'error' => 'need_at_least_two_variants' );
        }
        $user_id = get_current_user_id();
        $sid = $this->ensure_sid();
        $assign_table = $wpdb->prefix . 'roro_ab_assignment';
        // Try find existing assignment for user
        if ( $user_id ) {
            $variant = $wpdb->get_var( $wpdb->prepare(
                "SELECT variant FROM {$assign_table} WHERE user_id = %d AND experiment_key = %s ORDER BY id DESC LIMIT 1",
                $user_id, $exp
            ) );
            if ( $variant ) {
                $this->set_ab_cookie( $exp, $variant );
                return array( 'ok' => true, 'experiment' => $exp, 'variant' => $variant, 'source' => 'db_user' );
            }
        }
        // By session
        $variant = $wpdb->get_var( $wpdb->prepare(
            "SELECT variant FROM {$assign_table} WHERE user_id IS NULL AND session_id = %s AND experiment_key = %s ORDER BY id DESC LIMIT 1",
            $sid, $exp
        ) );
        if ( $variant ) {
            $this->set_ab_cookie( $exp, $variant );
            return array( 'ok' => true, 'experiment' => $exp, 'variant' => $variant, 'source' => 'db_session' );
        }
        // Cookie
        $cookie_variant = $this->get_ab_cookie( $exp );
        if ( $cookie_variant ) {
            $variant = $this->sanitize_key_short( $cookie_variant );
            $this->insert_assignment( $user_id, $sid, $exp, $variant );
            return array( 'ok' => true, 'experiment' => $exp, 'variant' => $variant, 'source' => 'cookie' );
        }
        // New assignment
        $variant = $this->deterministic_variant( $sid . '|' . $user_id . '|' . $exp, $variants, $split );
        $this->set_ab_cookie( $exp, $variant );
        $this->insert_assignment( $user_id, $sid, $exp, $variant );
        // log exposure event
        $this->insert_ab_event( $user_id, $sid, $exp, $variant, 'exposure', 1.0, 'ab_assign' );
        return array( 'ok' => true, 'experiment' => $exp, 'variant' => $variant, 'source' => 'assigned' );
    }

    /** POST /ab/event : record generic AB event */
    public function ab_event( WP_REST_Request $req ) {
        $exp   = $req['experiment'];
        $var   = $req['variant'];
        $name  = $req['event_name'];
        $value = isset( $req['value'] ) ? floatval( $req['value'] ) : 1.0;
        $ctx   = isset( $req['context'] ) ? sanitize_text_field( $req['context'] ) : '';
        $user_id = get_current_user_id();
        $sid = $this->ensure_sid();
        $this->insert_ab_event( $user_id, $sid, $exp, $var, $name, $value, $ctx );
        return array( 'ok' => true );
    }

    /** Insert into roro_ab_event */
    private function insert_ab_event( $user_id, $sid, $exp, $var, $name, $value, $ctx ) {
        global $wpdb;
        $table = $wpdb->prefix . 'roro_ab_event';
        $wpdb->insert( $table, array(
            'user_id'       => $user_id ? $user_id : null,
            'session_id'    => $sid,
            'experiment_key'=> $exp,
            'variant'       => $var,
            'event_name'    => $name,
            'value'         => floatval( $value ),
            'context'       => $ctx,
            'created_at'    => current_time( 'mysql', true ),
        ), array( '%d','%s','%s','%s','%s','%f','%s','%s' ) );
    }

    /** Insert into roro_ab_assignment */
    private function insert_assignment( $user_id, $sid, $exp, $variant ) {
        global $wpdb;
        $table = $wpdb->prefix . 'roro_ab_assignment';
        $wpdb->insert( $table, array(
            'user_id'        => $user_id ? $user_id : null,
            'session_id'     => $sid,
            'experiment_key' => $exp,
            'variant'        => $variant,
            'assigned_at'    => current_time( 'mysql', true ),
        ), array( '%d','%s','%s','%s','%s' ) );
    }

    /** Hash to choose a variant deterministically (stable) */
    private function deterministic_variant( $seed, $variants, $split_percent_for_first = 50 ) {
        $hash = md5( $seed );
        $num  = hexdec( substr( $hash, 0, 8 ) );
        if ( count( $variants ) == 2 ) {
            $p = $num % 100;
            return ( $p < max( 1, min( 99, (int)$split_percent_for_first ) ) ) ? $variants[0] : $variants[1];
        }
        $idx = $num % count( $variants );
        return $variants[ $idx ];
    }

    /** Cookie helpers for AB assignment */
    private function set_ab_cookie( $experiment, $variant ) {
        $key = 'roro_ab_' . $experiment;
        setcookie( $key, $variant, time() + 60*60*24*180, COOKIEPATH, COOKIE_DOMAIN, is_ssl(), true );
    }
    private function get_ab_cookie( $experiment ) {
        $key = 'roro_ab_' . $experiment;
        return isset( $_COOKIE[ $key ] ) ? $this->sanitize_key_short( $_COOKIE[ $key ] ) : '';
    }

    /** Enqueue front-end assets (abtest.js) */
    public function enqueue_front_assets() {
        $uri = get_stylesheet_directory_uri() . '/js/roro-abtest.js';
        wp_enqueue_script( 'roro-abtest', $uri, array(), null, true );
        wp_localize_script( 'roro-abtest', 'RORO_ABCFG', array(
            'rest'  => esc_url_raw( rest_url( $this->namespace ) ),
            'nonce' => wp_create_nonce( 'wp_rest' ),
        ) );
    }

    /** Admin menu: A/B Test report */
    public function register_admin_menu() {
        add_menu_page(
            'RORO A/Bテスト', 'A/Bテスト', 'manage_options', 'roro-abtest',
            array( $this, 'render_ab_admin_page' ), 'dashicons-randomize', 56
        );
    }

    /**
     * 管理画面：A/Bテストの集計ページ。
     * Stage32: CTRと統計的有意性を算出して表示します。
     */
    public function render_ab_admin_page() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'You do not have sufficient permissions.', 'roro' ) );
        }
        global $wpdb;
        // 日付パラメータの読み取り
        $since = isset($_GET['since']) ? $this->sanitize_date($_GET['since']) : gmdate('Y-m-d', time()-30*24*3600);
        $until = isset($_GET['until']) ? $this->sanitize_date($_GET['until']) : gmdate('Y-m-d');
        $evt   = $wpdb->prefix . 'roro_ab_event';
        // Exposures & Clicks per variant
        $exp_rows = $wpdb->get_results( $wpdb->prepare(
            "SELECT experiment_key, variant,
                    SUM(CASE WHEN event_name='exposure' THEN 1 ELSE 0 END) AS exposures,
                    SUM(CASE WHEN event_name='click'    THEN 1 ELSE 0 END) AS clicks
               FROM {$evt}
              WHERE created_at BETWEEN %s AND %s
           GROUP BY experiment_key, variant
           ORDER BY experiment_key, variant",
            $since . ' 00:00:00', $until . ' 23:59:59'
        ), ARRAY_A );
        // Determine first experiment for daily chart
        $first_exp = '';
        if ( ! empty( $exp_rows ) ) $first_exp = $exp_rows[0]['experiment_key'];
        $daily = array();
        if ( $first_exp ) {
            $daily = $wpdb->get_results( $wpdb->prepare(
                "SELECT DATE(created_at) AS d, variant, COUNT(*) AS clicks
                   FROM {$evt}
                  WHERE experiment_key = %s AND event_name = 'click'
                    AND created_at BETWEEN %s AND %s
               GROUP BY d, variant
               ORDER BY d ASC",
                $first_exp, $since . ' 00:00:00', $until . ' 23:59:59'
            ), ARRAY_A );
        }
        // Render HTML
        echo '<div class="wrap"><h1>A/Bテスト - 集計</h1>';
        echo '<form method="get"><input type="hidden" name="page" value="roro-abtest"/>';
        echo '期間: <input type="date" name="since" value="' . esc_attr($since) . '"/> 〜 ';
        echo '<input type="date" name="until" value="' . esc_attr($until) . '"/> ';
        echo '<button class="button button-primary">更新</button></form><hr/>';
        echo '<table class="widefat striped"><thead><tr>';
        echo '<th>Experiment</th><th>Variant</th><th>Exposures</th><th>Clicks</th><th>CTR</th>';
        echo '</tr></thead><tbody>';
        // Build associative mapping for significance calculation
        $exp_map = array();
        foreach ( $exp_rows as $r ) {
            $ctr = ($r['exposures'] > 0) ? sprintf('%.2f%%', 100.0 * $r['clicks'] / $r['exposures']) : '-';
            echo '<tr>';
            echo '<td>' . esc_html( $r['experiment_key'] ) . '</td>';
            echo '<td>' . esc_html( strtoupper($r['variant']) ) . '</td>';
            echo '<td>' . number_format_i18n( (int)$r['exposures'] ) . '</td>';
            echo '<td>' . number_format_i18n( (int)$r['clicks'] ) . '</td>';
            echo '<td>' . esc_html( $ctr ) . '</td>';
            echo '</tr>';
            // accumulate exposures/clicks for significance test
            $exp_key = $r['experiment_key'];
            $var_key = strtoupper($r['variant']);
            if ( ! isset( $exp_map[ $exp_key ] ) ) $exp_map[ $exp_key ] = array();
            $exp_map[ $exp_key ][ $var_key ] = array(
                'exposures' => (int) $r['exposures'],
                'clicks'    => (int) $r['clicks'],
            );
        }
        echo '</tbody></table>';
        // Significance test for first experiment (if 2 variants)
        if ( $first_exp && isset( $exp_map[ $first_exp ] ) && count( $exp_map[ $first_exp ] ) == 2 ) {
            // Extract exposures & clicks
            $vars = array_keys( $exp_map[ $first_exp ] );
            $v1 = $vars[0];
            $v2 = $vars[1];
            $n1 = $exp_map[ $first_exp ][ $v1 ]['exposures'];
            $n2 = $exp_map[ $first_exp ][ $v2 ]['exposures'];
            $c1 = $exp_map[ $first_exp ][ $v1 ]['clicks'];
            $c2 = $exp_map[ $first_exp ][ $v2 ]['clicks'];
            $p1 = ($n1 > 0) ? $c1 / $n1 : 0;
            $p2 = ($n2 > 0) ? $c2 / $n2 : 0;
            $p  = ($n1 + $n2) > 0 ? ($c1 + $c2) / ($n1 + $n2) : 0;
            $z  = 0;
            $pvalue = 1;
            if ( $n1 > 0 && $n2 > 0 && $p > 0 && $p < 1 ) {
                $std = sqrt( $p * (1 - $p) * ( 1.0 / $n1 + 1.0 / $n2 ) );
                if ( $std > 0 ) {
                    $z = ($p1 - $p2) / $std;
                    $pvalue = 2 * ( 1 - $this->normal_cdf( abs( $z ) ) );
                }
            }
            // Format output
            echo '<h2 style="margin-top:24px;">統計検定 (Experiment: ' . esc_html( $first_exp ) . ')</h2>';
            echo '<p>バリアント ' . esc_html( $v1 ) . ' と ' . esc_html( $v2 ) . ' の CTR 比較を実施しました。</p>';
            echo '<ul>';
            echo '<li>Variant ' . esc_html( $v1 ) . ': CTR = ' . sprintf( '%.2f%%', $p1*100 ) . '</li>';
            echo '<li>Variant ' . esc_html( $v2 ) . ': CTR = ' . sprintf( '%.2f%%', $p2*100 ) . '</li>';
            echo '<li>差 (V1 - V2) = ' . sprintf( '%.2f%%', ($p1-$p2)*100 ) . '</li>';
            echo '<li>z 値 = ' . sprintf( '%.3f', $z ) . '</li>';
            echo '<li>p 値 = ' . sprintf( '%.4f', $pvalue ) . '</li>';
            echo '<li>結果: ' . ( $pvalue < 0.05 ? '<strong style="color:#d63384;">有意差あり (p&lt;0.05)</strong>' : '有意差なし' ) . '</li>';
            echo '</ul>';
        }

        // Chart section
        echo '<h2 style="margin-top:24px;">日別クリック数（サンプル: ' . esc_html( $first_exp ?: 'N/A' ) . '）</h2>';
        echo '<canvas id="roroAbClicks" width="900" height="360" style="max-width:100%;"></canvas>';
        $chartData = array();
        foreach ( $daily as $row ) {
            $chartData[] = array( 'd' => $row['d'], 'v' => $row['variant'], 'c' => (int) $row['clicks'] );
        }
        echo '<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>';
        echo '<script> (function(){';
        echo 'const raw = ' . wp_json_encode( $chartData ) . ';';
        echo 'const labels = [...new Set(raw.map(r=>r.d))];';
        echo 'const variants = [...new Set(raw.map(r=>r.v))];';
        echo 'const datasets = variants.map(v => ({label: v.toUpperCase(), data: labels.map(d => {const r=raw.find(x=>x.d===d && x.v===v); return r? r.c:0;})}));';
        echo 'const ctx = document.getElementById("roroAbClicks").getContext("2d");';
        echo 'new Chart(ctx, { type: "line", data: { labels, datasets } });';
        echo '})();</script>';
        echo '</div>';
    }

    /**
     * Approximated cumulative distribution function of the standard normal distribution.
     *
     * Uses a rational approximation of the error function.
     * @param float $x Z-score
     * @return float cumulative probability P(X <= x)
     */
    private function normal_cdf( $x ) {
        // Abramowitz & Stegun approximation constants
        $sign = 1;
        if ( $x < 0 ) {
            $sign = -1;
        }
        $x_abs = abs( $x ) / sqrt(2);
        // approximation constants
        $t = 1 / (1 + 0.47047 * $x_abs);
        $a1 = 0.3480242; $a2 = -0.0958798; $a3 = 0.7478556;
        $poly = $t * ($a1 + $t * ($a2 + $t * $a3));
        $erf = 1 - $poly * exp( -$x_abs * $x_abs );
        $result = 0.5 * (1 + $sign * $erf);
        return $result;
    }

    /**
     * dbDelta: create AB tables if not exist.
     */
    public function maybe_create_ab_tables() {
        global $wpdb;
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        $charset_collate = $wpdb->get_charset_collate();
        $tbl_assign = $wpdb->prefix . 'roro_ab_assignment';
        $tbl_event  = $wpdb->prefix . 'roro_ab_event';
        $sql1 = "CREATE TABLE {$tbl_assign} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            user_id BIGINT UNSIGNED NULL,
            session_id VARCHAR(64) NOT NULL,
            experiment_key VARCHAR(64) NOT NULL,
            variant VARCHAR(16) NOT NULL,
            assigned_at DATETIME NOT NULL,
            PRIMARY KEY (id),
            KEY idx_user (user_id, experiment_key),
            KEY idx_session (session_id, experiment_key),
            KEY idx_assigned (assigned_at)
        ) {$charset_collate};";
        dbDelta( $sql1 );
        $sql2 = "CREATE TABLE {$tbl_event} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            user_id BIGINT UNSIGNED NULL,
            session_id VARCHAR(64) NOT NULL,
            experiment_key VARCHAR(64) NOT NULL,
            variant VARCHAR(16) NOT NULL,
            event_name VARCHAR(32) NOT NULL,
            value DOUBLE NOT NULL DEFAULT 1.0,
            context VARCHAR(128) NULL,
            created_at DATETIME NOT NULL,
            PRIMARY KEY (id),
            KEY idx_ev1 (experiment_key, variant, event_name),
            KEY idx_ev2 (created_at),
            KEY idx_user (user_id),
            KEY idx_session (session_id)
        ) {$charset_collate};";
        dbDelta( $sql2 );
    }
}

endif;
// Instantiate on theme/plugin load
if ( class_exists( 'Roro_Rest_Recommend_Metrics' ) ) {
    global $roro_rest_recommend_metrics;
    if ( ! isset( $roro_rest_recommend_metrics ) ) {
        $roro_rest_recommend_metrics = new Roro_Rest_Recommend_Metrics();
    }
}