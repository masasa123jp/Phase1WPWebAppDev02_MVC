<?php
/**
 * Functions and definitions for the RORO Mock Theme (magazine-unified).
 *
 * ポイント
 * - プラグイン版 roro-magazine は magazine ページでは強制停止（dequeue / deregister）。
 * - テーマ版 js/magazine.js をヘッダで読込（DOMContentLoaded 済でも動くように）。
 * - magazine.js にテーマURL（画像パス正規化用）を wp_localize_script で渡す。
 * - そのほかのページ（map / login / signup / favorites / profile / dify）は従来どおり読み分け。
 */

// Exit if accessed directly.
// Pull in our MVC autoloader early so that controllers and models can be
// instantiated as needed throughout the theme.  This file registers an
// SPL autoloader that maps class names to files in the app/models and
// app/controllers directories.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Load autoloader for MVC classes
require_once get_template_directory() . '/app/autoload.php';
// Include AuthController definition so that REST routes can be registered below.  The
// autoloader will also pick up this class when referenced, but requiring here
// ensures it is available during the rest_api_init hook.  If the file is
// missing the class_exists check will silently skip registration.
require_once get_template_directory() . '/app/controllers/AuthController.php';

/**
 * テーマ初期化
 */
function roro_mock_theme_setup() {
    add_theme_support( 'title-tag' );
    add_theme_support( 'custom-logo' );
    add_theme_support( 'post-thumbnails' );
    // Load translations from the theme's languages directory.  This
    // enables the use of esc_html__(), esc_attr__() and other
    // translation functions throughout the theme.  When translators
    // provide .po/.mo files in the languages folder, WordPress will
    // automatically pick them up for the 'roro' text domain.
    load_theme_textdomain( 'roro', get_template_directory() . '/languages' );
}
add_action( 'after_setup_theme', 'roro_mock_theme_setup' );

/**
 * Register REST routes for social login (Google / LINE).  We defer this
 * registration until rest_api_init so that WordPress has initialised the
 * REST API.  The AuthController will register its endpoints only if the
 * class exists.
 */
add_action( 'rest_api_init', function() {
    if ( class_exists( 'AuthController' ) ) {
        $auth = new AuthController();
        $auth->register_routes();
    }
    // Register analytics routes when available.  AnalyticsController handles
    // view and click events for magazine and other pages.  Instantiation
    // happens here to ensure $wpdb is initialised.
    if ( class_exists( 'AnalyticsController' ) ) {
        $analytics = new AnalyticsController();
        $analytics->register_routes();
    }
} );

/**
 * Add a settings page under Settings → RORO Auth to allow administrators
 * to configure Google and LINE client IDs and secrets.  These values are
 * stored in the WordPress options table using the Settings API.
 */
function roro_mock_theme_add_auth_settings_page() {
    add_options_page(
        'RORO ソーシャルログイン設定',
        'RORO Auth',
        'manage_options',
        'roro-auth-settings',
        'roro_mock_theme_render_auth_settings_page'
    );
}
add_action( 'admin_menu', 'roro_mock_theme_add_auth_settings_page' );

/**
 * Register settings for storing OAuth credentials.  These settings are
 * grouped under roro-auth-settings-group and can be edited via the
 * options page.  Sanitisation is handled automatically.
 */
function roro_mock_theme_register_auth_settings() {
    register_setting( 'roro-auth-settings-group', 'roro_google_client_id' );
    register_setting( 'roro-auth-settings-group', 'roro_google_client_secret' );
    register_setting( 'roro-auth-settings-group', 'roro_line_client_id' );
    register_setting( 'roro-auth-settings-group', 'roro_line_client_secret' );
}
add_action( 'admin_init', 'roro_mock_theme_register_auth_settings' );

/**
 * Add custom capabilities for granular RORO management.
 *
 * Administrators are granted the following capabilities:
 *  - manage_roro_events: create/update/delete events
 *  - manage_roro_spots: create/update/delete travel spots
 *
 * These capabilities allow finer control than the global manage_options.
 */
/*
 * NOTE: The initial implementation of roro_add_custom_caps (above) added
 * manage_roro_events, manage_roro_spots and manage_roro_advice to the
 * administrator role. Later in this file another roro_add_custom_caps
 * implementation was defined which added edit_own_roro_profile and
 * manage_roro capabilities to multiple roles. Having duplicate function
 * definitions is problematic because PHP will overwrite the earlier
 * definition with the latter, resulting in the first set of custom
 * capabilities never being registered. To resolve this, the original
 * function has been removed and its logic has been merged into the
 * later roro_add_custom_caps implementation. This comment documents
 * the removal for clarity.  See the second definition further down
 * for the combined logic.
 */

/**
 * Render the contents of the social login settings page.  Provides form
 * fields for GoogleとLINEのクライアントIDとシークレットを入力します。
 */
function roro_mock_theme_render_auth_settings_page() {
    if ( ! current_user_can( 'manage_options' ) ) {
        return;
    }
    ?>
    <div class="wrap">
        <h1>RORO ソーシャルログイン設定</h1>
        <form method="post" action="options.php">
            <?php settings_fields( 'roro-auth-settings-group' ); ?>
            <?php do_settings_sections( 'roro-auth-settings-group' ); ?>
            <table class="form-table" role="presentation">
                <tr>
                    <th scope="row"><label for="roro_google_client_id">Google Client ID</label></th>
                    <td><input type="text" id="roro_google_client_id" name="roro_google_client_id" value="<?php echo esc_attr( get_option( 'roro_google_client_id' ) ); ?>" class="regular-text" /></td>
                </tr>
                <tr>
                    <th scope="row"><label for="roro_google_client_secret">Google Client Secret</label></th>
                    <td><input type="text" id="roro_google_client_secret" name="roro_google_client_secret" value="<?php echo esc_attr( get_option( 'roro_google_client_secret' ) ); ?>" class="regular-text" /></td>
                </tr>
                <tr>
                    <th scope="row"><label for="roro_line_client_id">LINE Client ID</label></th>
                    <td><input type="text" id="roro_line_client_id" name="roro_line_client_id" value="<?php echo esc_attr( get_option( 'roro_line_client_id' ) ); ?>" class="regular-text" /></td>
                </tr>
                <tr>
                    <th scope="row"><label for="roro_line_client_secret">LINE Client Secret</label></th>
                    <td><input type="text" id="roro_line_client_secret" name="roro_line_client_secret" value="<?php echo esc_attr( get_option( 'roro_line_client_secret' ) ); ?>" class="regular-text" /></td>
                </tr>
            </table>
            <?php submit_button(); ?>
        </form>
    </div>
    <?php
}

/**
 * アセット読み込み（ページ文脈ごとにスクリプト選別）
 */
function roro_mock_theme_enqueue_assets() {
    $theme_uri  = get_template_directory_uri();
    $theme_path = get_template_directory();

    // CSS
    $css = $theme_path . '/css/styles.css';
    if ( file_exists( $css ) ) {
        wp_enqueue_style( 'roro-styles', $theme_uri . '/css/styles.css', array(), filemtime( $css ) );
    }

    // 共通JS（言語/メイン）
    $core_scripts = array(
        'lang' => 'js/lang.js',
        'main' => 'js/main.js',
    );
    foreach ( $core_scripts as $handle => $relative_path ) {
        $full = $theme_path . '/' . $relative_path;
        if ( file_exists( $full ) ) {
            wp_enqueue_script( 'roro-' . $handle, $theme_uri . '/' . $relative_path, array(), filemtime( $full ), true );
        }
    }

    // ページ文脈の判定：テンプレ優先 / slug fallback
    $context = 'login';
    $slug = '';
    if ( is_page() ) {
        $post = get_post();
        if ( $post ) $slug = strtolower( $post->post_name );
    }
    if ( is_front_page() || is_home() ) {
        $context = 'login';
    } elseif ( is_page_template( 'page-map.php' ) || $slug === 'map' ) {
        $context = 'map';
    } elseif ( is_page_template( 'page-magazine.php' ) || $slug === 'magazine' ) {
        $context = 'magazine';
    } elseif ( is_page_template( 'page-favorites.php' ) || $slug === 'favorites' ) {
        $context = 'favorites';
    } elseif ( is_page_template( 'page-profile.php' ) || $slug === 'profile' ) {
        $context = 'profile';
    } elseif ( is_page_template( 'page-signup.php' ) || $slug === 'signup' ) {
        $context = 'signup';
    } elseif ( is_page_template( 'page-dify.php' ) || $slug === 'dify' ) {
        $context = 'dify';
    }

    // 文脈別に追加スクリプトを選別
    $additional = array();
    switch ( $context ) {
        case 'map':
            $additional = array(
                'map-loader' => 'js/map-loader.js',
                'map'        => 'js/map.js',
            );
            break;
        case 'magazine':
            $additional = array(
                'magazine' => 'js/magazine.js',
            );
            break;
        case 'favorites':
            $additional = array( 'favorites' => 'js/favorites.js' );
            break;
        case 'profile':
            $additional = array( 'profile' => 'js/profile.js' );
            break;
        case 'signup':
            $additional = array( 'signup' => 'js/signup.js' );
            break;
        case 'dify':
            $additional = array( 'dify' => 'js/dify.js' );
            break;
        case 'login':
        default:
            $additional = array( 'login' => 'js/login.js' );
            break;
    }

    // 追加スクリプトのenqueue（map/magazineはヘッダで）
    foreach ( $additional as $handle => $relative_path ) {
        $full = $theme_path . '/' . $relative_path;
        if ( ! file_exists( $full ) ) {
            continue;
        }
        $in_footer = true;
        if ( $context === 'map' && ( $handle === 'map' || $handle === 'map-loader' ) ) {
            $in_footer = false; // Google callback=initMap 対策
        }
        if ( $context === 'magazine' && $handle === 'magazine' ) {
            $in_footer = false; // DOMContentLoaded 済でも確実に初期化したい
        }
        wp_enqueue_script(
            'roro-' . $handle,
            $theme_uri . '/' . $relative_path,
            array( 'roro-lang', 'roro-main' ),
            filemtime( $full ),
            $in_footer
        );
    }

    // mapページ：events.js（map.js より前に）
    if ( $context === 'map' ) {
        // Fetch event data from the database and localise into roro-map
        if ( class_exists( 'MapController' ) ) {
            $map_controller = new MapController();
            // Ensure roro-map is enqueued before localising
            if ( wp_script_is( 'roro-map', 'enqueued' ) ) {
                $map_controller->enqueue_event_data( 'roro-map' );
            } else {
                // If map script is not enqueued yet (e.g. due to plugin), hook after enqueue
                add_action( 'wp_print_scripts', function() use ( $map_controller ) {
                    if ( wp_script_is( 'roro-map', 'enqueued' ) ) {
                        $map_controller->enqueue_event_data( 'roro-map' );
                    }
                } );
            }
        }
    }

    // ルート情報（例：画面遷移等で使用）
    $routes = array(
        'login'  => home_url( '/' ),
        'map'    => home_url( '/map/' ),
        'signup' => home_url( '/signup/' ),
        'index'  => home_url( '/' ),
        'magazine' => home_url( '/magazine/' ),
    );
    foreach ( array( 'main', 'login', 'signup', 'profile' ) as $h ) {
        $hd = 'roro-' . $h;
        if ( wp_script_is( $hd, 'enqueued' ) ) {
            wp_localize_script( $hd, 'RORO_ROUTES', $routes );
            // Provide ajaxurl for AJAX requests on the front‑end.  WordPress
            // normally defines this in the admin area only, so we expose it
            // globally for consistency.  The variable name must be a
            // valid JavaScript identifier.
            wp_localize_script( $hd, 'ajaxurl', admin_url( 'admin-ajax.php' ) );
        }
    }

    /**
     * 重要：Magazineページをテーマ版に“一本化”
     * - プラグイン roro-magazine のスクリプトが読み込まれている場合は無効化。
     * - テーマ版 roro-magazine を使い、テーマURLをJSへ渡す。
     */
    if ( $context === 'magazine' ) {
        if ( wp_script_is( 'roro-magazine', 'registered' ) || wp_script_is( 'roro-magazine', 'enqueued' ) ) {
            // プラグインが同名ハンドルで登録している想定
            wp_dequeue_script( 'roro-magazine' );
            wp_deregister_script( 'roro-magazine' );
        }
        // テーマ版 magazine.js（上で enqueue 済み）
        if ( wp_script_is( 'roro-magazine', 'enqueued' ) ) {
            wp_localize_script( 'roro-magazine', 'RORO_THEME', array(
                'base' => $theme_uri, // 画像パスの正規化に使用（JS側の img() で使用）
            ) );
        } else {
            // もしハンドル名が 'roro-magazine' になっていない場合の保険
            if ( wp_script_is( 'roro-magazine', 'registered' ) ) {
                wp_localize_script( 'roro-magazine', 'RORO_THEME', array( 'base' => $theme_uri ) );
            } elseif ( wp_script_is( 'roro-magazine', 'enqueued' ) === false && wp_script_is( 'roro-magazine', 'registered' ) === false ) {
                // 追加スクリプトが 'roro-magazine' でなかったケース（例えば 'roro-magazine-custom' のような場合）
                // 'roro-magazine' に統一するのが望ましいが、ここでは最初の magazine.js に対してローカライズ。
                foreach ( $additional as $handle => $rp ) {
                    if ( $handle === 'magazine' ) {
                        wp_localize_script( 'roro-' . $handle, 'RORO_THEME', array( 'base' => $theme_uri ) );
                    }
                }
            }
        }

        // 雑誌データをローカライズ。MagazineController が存在する場合に
        // RORO_MAG_DATA を magazine.js に注入します。複数ハンドルに対応するため、
        // enqueued 状態を確認してから実行します。
        if ( class_exists( 'MagazineController' ) ) {
            $mag_controller = new MagazineController();
            add_action( 'wp_print_scripts', function () use ( $mag_controller ) {
                // テーマ版のハンドル
                if ( wp_script_is( 'roro-magazine', 'enqueued' ) ) {
                    $mag_controller->enqueue_magazine_data( 'roro-magazine' );
                }
                // グローバルインジェクタ版
                if ( wp_script_is( 'roro-magazine-global', 'enqueued' ) ) {
                    $mag_controller->enqueue_magazine_data( 'roro-magazine-global' );
                }
            } );
        }
    }
    // Localise profile data when on profile page.  Since scripts may not be
    // enqueued until later in the request, defer localisation until
    // `wp_print_scripts` runs.  This ensures that the `roro-profile`
    // handle has been registered.
    if ( $context === 'profile' && class_exists( 'ProfileController' ) ) {
        $profile_controller = new ProfileController();
        add_action( 'wp_print_scripts', function() use ( $profile_controller ) {
            if ( wp_script_is( 'roro-profile', 'enqueued' ) ) {
                $profile_controller->localize_profile_data( 'roro-profile' );
            }
        } );
    }
}
add_action( 'wp_enqueue_scripts', 'roro_mock_theme_enqueue_assets' );

/**
 * 見た目用のbodyクラス（Frontを簡易ログイン画面扱いに）
 */
function roro_mock_theme_body_class( $classes ) {
    if ( is_front_page() || is_home() ) {
        $classes[] = 'login-page';
    }
    return $classes;
}
add_filter( 'body_class', 'roro_mock_theme_body_class' );

/**
 * Register AJAX endpoints for favourites.
 *
 * These actions are registered during the `init` hook to ensure that
 * WordPress is loaded before attempting to instantiate controllers.  Both
 * logged‑in users and guests can call the endpoints; guests will
 * receive an authentication error.
 */
add_action( 'init', function() {
    if ( class_exists( 'FavoritesController' ) ) {
        $controller = new FavoritesController();
        add_action( 'wp_ajax_roro_get_favorites', [ $controller, 'ajax_get_favorites' ] );
        add_action( 'wp_ajax_nopriv_roro_get_favorites', [ $controller, 'ajax_get_favorites' ] );
        add_action( 'wp_ajax_roro_toggle_favorite', [ $controller, 'ajax_toggle_favorite' ] );
        add_action( 'wp_ajax_nopriv_roro_toggle_favorite', [ $controller, 'ajax_toggle_favorite' ] );
    }
} );

/*
 * Return a list of all 47 Japanese prefectures.
 *
 * This helper centralises the prefecture names so that both the
 * front‑end profile page and the admin interface can reuse the
 * same list.  Prefectures are returned in the traditional
 * north‑to‑south order, using their common Japanese names.  See
 * nippon.com’s “The Prefectures of Japan” guide for the full
 * enumeration of the 47 prefectures【586954716513274†L160-L228】.
 *
 * @return array List of prefecture names in Japanese.
 */
function roro_get_japanese_prefectures() {
    return array(
        '北海道',
        '青森県',
        '岩手県',
        '宮城県',
        '秋田県',
        '山形県',
        '福島県',
        '茨城県',
        '栃木県',
        '群馬県',
        '埼玉県',
        '千葉県',
        '東京都',
        '神奈川県',
        '新潟県',
        '富山県',
        '石川県',
        '福井県',
        '山梨県',
        '長野県',
        '岐阜県',
        '静岡県',
        '愛知県',
        '三重県',
        '滋賀県',
        '京都府',
        '大阪府',
        '兵庫県',
        '奈良県',
        '和歌山県',
        '鳥取県',
        '島根県',
        '岡山県',
        '広島県',
        '山口県',
        '徳島県',
        '香川県',
        '愛媛県',
        '高知県',
        '福岡県',
        '佐賀県',
        '長崎県',
        '熊本県',
        '大分県',
        '宮崎県',
        '鹿児島県',
        '沖縄県'
    );
}

/**
 * mapの古い静的パス互換（省略可能）
 */
function roro_mock_theme_add_rewrites() {
    add_rewrite_rule( '^mock-02_map(?:/index\.html)?/?$', 'index.php?pagename=map', 'top' );
}
add_action( 'init', 'roro_mock_theme_add_rewrites' );

/**
 * ============================================================
 * RORO_FORCE_MAG_GLOBAL: Always enqueue magazine assets site-wide
 * - Enqueues js/magazine.js and css/magazine.css on every page to avoid missing injection.
 * - Adds inline bootstrap to expose openMagazineAlias with retries.
 * RORO_MAG_EMERGENCY_INJECTOR: Dynamic injection if magazine.js is removed by optimizers.
 * ============================================================
 */
add_action( 'wp_enqueue_scripts', function () {
    $theme_uri  = get_template_directory_uri();
    $theme_path = get_template_directory();
    $mag_js  = $theme_path . '/js/magazine.js';
    $mag_css = $theme_path . '/css/magazine.css';
    if ( file_exists( $mag_css ) ) {
        wp_enqueue_style( 'roro-magazine-global', $theme_uri . '/css/magazine.css', array(), filemtime( $mag_css ) );
    }
    if ( file_exists( $mag_js ) ) {
        wp_enqueue_script( 'roro-magazine-global', $theme_uri . '/js/magazine.js', array(), filemtime( $mag_js ), false );
        wp_localize_script( 'roro-magazine-global', 'RORO_THEME', array( 'base' => $theme_uri ) );
        $bootstrap = <<<JS
(function(){
  function expose(){
    if (typeof window.openMagazine === 'function') {
      window.openMagazineAlias = window.openMagazine;
      return;
    }
    var tries=0;
    (function retry(){
      tries++;
      if (typeof window.openMagazine === 'function') {
        window.openMagazineAlias = window.openMagazine;
      } else if (tries < 40) {
        setTimeout(retry, 150);
      }
    })();
  }
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', expose, {once:true});
  } else {
    expose();
  }
  window.addEventListener('load', expose, {once:true});
})();
JS;
        wp_add_inline_script( 'roro-magazine-global', $bootstrap, 'after' );
    }
}, 5);

// Emergency injector: if magazine.js is missing at runtime, inject it
add_action( 'wp_print_footer_scripts', function(){
    ?>
    <script>
    (function(){
      var hasMag = Array.prototype.some.call(document.scripts, function(s){ return /\/js\/magazine\.js(\?|$)/.test(s.src); });
      if(!hasMag){
        var base = (window.RORO_THEME ? RORO_THEME.base : document.body.getAttribute('data-theme-base') || '');
        var s = document.createElement('script');
        s.src = base + '/js/magazine.js';
        document.head.appendChild(s);
      }
    })();
    </script>
    <?php
});
/* End RORO_MAG_EMERGENCY_INJECTOR */

/**
 * ============================================================
 * Analytics Cron Registration
 * - Registers a custom cron event to aggregate daily view/click stats.
 * ============================================================
 */
add_action( 'roro_analytics_update_daily', function() {
    if ( class_exists( 'AnalyticsController' ) ) {
        $analytics = new AnalyticsController();
        $analytics->update_daily();
    }
} );
// Schedule the hourly cron if it isn't scheduled yet.  Start after 5 minutes.
if ( ! wp_next_scheduled( 'roro_analytics_update_daily' ) ) {
    wp_schedule_event( time() + 300, 'hourly', 'roro_analytics_update_daily' );
}

/**
 * ------------------------------------------------------------
 * RORO Custom Capabilities and Admin Menu
 *
 * To allow logged in users to edit their own profile and pets
 * without granting broad editing privileges, we define a custom
 * capability (`edit_own_roro_profile`) which is added to
 * subscribers and above.  We also define a `manage_roro`
 * capability for administrators and editors who need to manage
 * RORO data via the backend.  These capabilities are granted on
 * the `init` hook.
 */
function roro_add_custom_caps() {
    // Define roles that should be allowed to edit their own RORO
    // profile.  Subscribers and all higher roles will receive
    // this capability.
    $edit_roles = array( 'subscriber', 'contributor', 'author', 'editor', 'administrator' );
    foreach ( $edit_roles as $role_name ) {
        $role = get_role( $role_name );
        if ( $role && ! $role->has_cap( 'edit_own_roro_profile' ) ) {
            $role->add_cap( 'edit_own_roro_profile' );
        }
    }
    // Grant the manage_roro capability to editors and administrators only.
    foreach ( array( 'editor', 'administrator' ) as $role_name ) {
        $role = get_role( $role_name );
        if ( $role ) {
            if ( ! $role->has_cap( 'manage_roro' ) ) {
                $role->add_cap( 'manage_roro' );
            }
            // Additionally assign fine‑grained management capabilities to
            // administrators. Editors could receive these caps in the
            // future but for now only administrators manage events,
            // spots and advice. Using has_cap() checks avoids adding
            // duplicate capabilities if they already exist.
            if ( 'administrator' === $role_name ) {
                if ( ! $role->has_cap( 'manage_roro_events' ) ) {
                    $role->add_cap( 'manage_roro_events' );
                }
                if ( ! $role->has_cap( 'manage_roro_spots' ) ) {
                    $role->add_cap( 'manage_roro_spots' );
                }
                if ( ! $role->has_cap( 'manage_roro_advice' ) ) {
                    $role->add_cap( 'manage_roro_advice' );
                }
            }
        }
    }
}
add_action( 'init', 'roro_add_custom_caps' );

/**
 * Register the RORO admin menu.  Only users with the
 * `manage_roro` capability can access this menu.  The menu page
 * displays lists of customers and pets along with CSV export
 * functionality.  The rendering logic is defined in
 * app/admin-roro.php.
 */
function roro_register_roro_admin_menu() {
    if ( current_user_can( 'manage_roro' ) ) {
        add_menu_page(
            'RORO 管理',
            'RORO 管理',
            'manage_roro',
            'roro-admin',
            'roro_render_admin_page',
            'dashicons-admin-generic',
            56
        );
    }
}
add_action( 'admin_menu', 'roro_register_roro_admin_menu' );

// Include the admin page renderer.  This file defines
// roro_render_admin_page() which outputs the contents of the
// admin page.
require_once get_template_directory() . '/app/admin-roro.php';

// Include the KPI dashboard admin page.  This file defines
// roro_admin_kpi_page() which outputs aggregated recommendation metrics.
require_once get_template_directory() . '/admin/roro-admin-kpi.php';

/**
 * Register the KPI dashboard under the RORO admin menu.  Only users with
 * the `manage_roro` capability can access this page.  The submenu
 * provides a view of recommendation KPIs across different time
 * windows.
 */
function roro_register_kpi_admin() {
    // We place the submenu only for users with manage_roro capability.
    if ( current_user_can( 'manage_roro' ) ) {
        add_submenu_page(
            'roro-admin',
            __( '推薦KPI', 'roro' ),
            __( '推薦KPI', 'roro' ),
            'manage_roro',
            'roro-kpi',
            'roro_admin_kpi_page'
        );
    }
}
add_action( 'admin_menu', 'roro_register_kpi_admin' );

// Load the refactor bootstrap to register our owner scoped
// REST endpoints and associated models.  This requires the
// `refactor` directory to exist in the theme.
require_once get_template_directory() . '/refactor/bootstrap.php';

/* -----------------------------------------------------------------------------
 * Handlers for RORO admin form submissions
 *
 * These functions process POST requests from the RORO 管理ページ.  They
 * update customer and pet records based on submitted form values.  Only
 * users with the `manage_roro` capability may invoke these handlers.  Upon
 * completion the user is redirected back to the admin page with a query
 * string parameter indicating success.  The handlers are hooked into the
 * WordPress admin_post action below.
 */

/**
 * Handle updates to a customer record from the admin interface.
 *
 * Expects the following POST fields:
 *   - customer_id (int) – ID of the customer to update
 *   - prefecture (string) – new prefecture
 *   - city (string) – new city
 *
 * Future enhancements could include updating additional columns (e.g.
 * address_line1, building, default_pet_id, etc.).
 */
function roro_handle_update_customer() {
    // Only users with manage_roro can process updates
    if ( ! current_user_can( 'manage_roro' ) ) {
        wp_die( __( 'You do not have sufficient permissions to update customers.', 'roro' ) );
    }
    // Validate and sanitise input.  Verify nonce to prevent CSRF.
    if ( ! isset( $_POST['_wpnonce_roro_update_customer'] ) || ! wp_verify_nonce( $_POST['_wpnonce_roro_update_customer'], 'roro_update_customer' ) ) {
        wp_die( __( 'Security check failed for customer update.', 'roro' ) );
    }
    $cust_id    = isset( $_POST['customer_id'] ) ? intval( $_POST['customer_id'] ) : 0;
    $prefecture = isset( $_POST['prefecture'] ) ? sanitize_text_field( wp_unslash( $_POST['prefecture'] ) ) : '';
    $city       = isset( $_POST['city'] ) ? sanitize_text_field( wp_unslash( $_POST['city'] ) ) : '';
    if ( $cust_id > 0 ) {
        global $wpdb;
        $table = $wpdb->prefix . 'RORO_CUSTOMER';
        $wpdb->update( $table, array(
            'prefecture' => $prefecture,
            'city'       => $city,
        ), array( 'customer_id' => $cust_id ), array( '%s', '%s' ), array( '%d' ) );
    }
    // Redirect back to admin page with updated flag
    $redirect = add_query_arg( array( 'page' => 'roro-admin', 'updated' => 'customer' ), admin_url( 'admin.php' ) );
    wp_safe_redirect( $redirect );
    exit;
}

/**
 * Handle updates to a pet record from the admin interface.
 *
 * Expects the following POST fields:
 *   - pet_id (int)      – ID of the pet to update
 *   - customer_id (int) – ID of the owning customer (not editable via form)
 *   - species (string)  – new species (dog/cat/other)
 *   - pet_name (string) – new pet name
 *
 * At present only species and pet_name are updatable.  Additional
 * attributes such as breed or age could be added later.
 */
function roro_handle_update_pet() {
    if ( ! current_user_can( 'manage_roro' ) ) {
        wp_die( __( 'You do not have sufficient permissions to update pets.', 'roro' ) );
    }
    // Verify nonce to prevent CSRF.  The nonce field is generated in the admin form with key '_wpnonce_roro_update_pet'.
    if ( ! isset( $_POST['_wpnonce_roro_update_pet'] ) || ! wp_verify_nonce( $_POST['_wpnonce_roro_update_pet'], 'roro_update_pet' ) ) {
        wp_die( __( 'Security check failed for pet update.', 'roro' ) );
    }
    $pet_id   = isset( $_POST['pet_id'] ) ? intval( $_POST['pet_id'] ) : 0;
    $species  = isset( $_POST['species'] ) ? sanitize_text_field( wp_unslash( $_POST['species'] ) ) : '';
    $pet_name = isset( $_POST['pet_name'] ) ? sanitize_text_field( wp_unslash( $_POST['pet_name'] ) ) : '';
    if ( $pet_id > 0 ) {
        global $wpdb;
        $table = $wpdb->prefix . 'RORO_PET';
        // Normalise species: allow only dog, cat or other.  Anything else defaults to OTHER.
        $species_lc = strtolower( $species );
        if ( ! in_array( $species_lc, array( 'dog', 'cat', 'other' ), true ) ) {
            $species_lc = 'other';
        }
        $species_db = strtoupper( $species_lc );
        $wpdb->update( $table, array(
            'species'  => $species_db,
            'pet_name' => $pet_name,
        ), array( 'pet_id' => $pet_id ), array( '%s', '%s' ), array( '%d' ) );
    }
    $redirect = add_query_arg( array( 'page' => 'roro-admin', 'updated' => 'pet' ), admin_url( 'admin.php' ) );
    wp_safe_redirect( $redirect );
    exit;
}

// Hook handlers to admin_post actions.  WordPress will fire these when the
// appropriate `action` field is passed in POST data.  The `_nopriv_` prefix
// variant is omitted since only logged in users with manage_roro should
// submit these forms.
add_action( 'admin_post_roro_update_customer', 'roro_handle_update_customer' );
add_action( 'admin_post_roro_update_pet', 'roro_handle_update_pet' );
add_action( 'admin_post_roro_delete_favorite', 'roro_handle_delete_favorite' );

/* ===========================================================================
 * CSV Export Handlers
 *
 * The following handlers allow administrators to export customer, pet and
 * favorite data as CSV files.  These actions are triggered via the
 * admin-post.php endpoint by specifying the appropriate `action` query
 * parameter.  Only users with the `manage_roro` capability may perform
 * exports.  Each handler outputs a CSV document to the browser with
 * appropriate headers and terminates execution.
 */

/**
 * Export all customer records as a CSV file.
 *
 * The CSV includes basic customer attributes.  Fields exported:
 * - customer_id
 * - wp_user_id
 * - prefecture
 * - city
 * - is_active
 * - default_pet_id
 * - created_at
 * - updated_at
 */
function roro_handle_export_customers() {
    if ( ! current_user_can( 'manage_roro' ) ) {
        wp_die( __( 'You do not have permission to export customers.', 'roro' ) );
    }
    global $wpdb;
    $table = $wpdb->prefix . 'RORO_CUSTOMER';
    $rows  = $wpdb->get_results( "SELECT customer_id, wp_user_id, prefecture, city, is_active, default_pet_id, created_at, updated_at FROM {$table}", ARRAY_A );
    // Send headers
    header( 'Content-Type: text/csv; charset=UTF-8' );
    header( 'Content-Disposition: attachment; filename="roro_customers.csv"' );
    $output = fopen( 'php://output', 'w' );
    // Output header row
    fputcsv( $output, array( 'customer_id', 'wp_user_id', 'prefecture', 'city', 'is_active', 'default_pet_id', 'created_at', 'updated_at' ) );
    foreach ( $rows as $row ) {
        fputcsv( $output, $row );
    }
    fclose( $output );
    exit;
}

/**
 * Export all pet records as a CSV file.
 *
 * The CSV includes key pet attributes.  Fields exported:
 * - pet_id
 * - customer_id
 * - species
 * - pet_name
 * - birth_date
 * - sex
 * - is_active
 * - created_at
 * - updated_at
 */
function roro_handle_export_pets() {
    if ( ! current_user_can( 'manage_roro' ) ) {
        wp_die( __( 'You do not have permission to export pets.', 'roro' ) );
    }
    global $wpdb;
    $table = $wpdb->prefix . 'RORO_PET';
    $rows  = $wpdb->get_results( "SELECT pet_id, customer_id, species, pet_name, birth_date, sex, is_active, created_at, updated_at FROM {$table}", ARRAY_A );
    header( 'Content-Type: text/csv; charset=UTF-8' );
    header( 'Content-Disposition: attachment; filename="roro_pets.csv"' );
    $output = fopen( 'php://output', 'w' );
    fputcsv( $output, array( 'pet_id', 'customer_id', 'species', 'pet_name', 'birth_date', 'sex', 'is_active', 'created_at', 'updated_at' ) );
    foreach ( $rows as $row ) {
        fputcsv( $output, $row );
    }
    fclose( $output );
    exit;
}

/**
 * Export all favorites as a CSV file, including contextual information.
 *
 * The CSV includes the following fields:
 * - id
 * - user_id
 * - target_type
 * - target_id
 * - target_name (event/spot name, if available)
 * - prefecture
 * - city
 * - created_at
 */
function roro_handle_export_favorites() {
    if ( ! current_user_can( 'manage_roro' ) ) {
        wp_die( __( 'You do not have permission to export favorites.', 'roro' ) );
    }
    global $wpdb;
    $fav_table = $wpdb->prefix . 'RORO_MAP_FAVORITE';
    $events_table = 'RORO_EVENTS_MASTER';
    $spots_table  = 'RORO_TRAVEL_SPOT_MASTER';
    // Join favorites with event and spot tables to obtain names and locations
    $sql = "SELECT f.id, f.user_id, f.target_type, f.target_id,"
        . " CASE WHEN f.target_type='event' THEN e.name ELSE s.name END AS target_name,"
        . " CASE WHEN f.target_type='event' THEN e.prefecture ELSE s.prefecture END AS prefecture,"
        . " CASE WHEN f.target_type='event' THEN e.city ELSE s.spot_area END AS city,"
        . " f.created_at"
        . " FROM {$fav_table} AS f"
        . " LEFT JOIN {$events_table} AS e ON (f.target_type='event' AND f.target_id=e.event_id)"
        . " LEFT JOIN {$spots_table} AS s ON (f.target_type='spot' AND f.target_id=s.TSM_ID)";
    $rows = $wpdb->get_results( $sql, ARRAY_A );
    header( 'Content-Type: text/csv; charset=UTF-8' );
    header( 'Content-Disposition: attachment; filename="roro_favorites.csv"' );
    $output = fopen( 'php://output', 'w' );
    fputcsv( $output, array( 'id', 'user_id', 'target_type', 'target_id', 'target_name', 'prefecture', 'city', 'created_at' ) );
    foreach ( $rows as $row ) {
        fputcsv( $output, $row );
    }
    fclose( $output );
    exit;
}

// Hook CSV export handlers to admin_post actions.  WordPress triggers these
// when the corresponding `action` parameter is provided via admin-post.php.
add_action( 'admin_post_roro_export_customers', 'roro_handle_export_customers' );
add_action( 'admin_post_roro_export_pets', 'roro_handle_export_pets' );
add_action( 'admin_post_roro_export_favorites', 'roro_handle_export_favorites' );

/**
 * Handle deletion of a favorite from the admin interface.
 *
 * Expects the following POST fields:
 *   - id (int) – ID of the favorite record to delete
 *
 * Only users with the `manage_roro` capability may perform deletions.
 * After removing the record, the user is redirected back to the RORO
 * admin page with an appropriate query flag indicating that a favorite
 * was deleted.  At present there is no undo; the record is removed
 * permanently.  Future enhancements could include a soft‑delete or
 * trash mechanism.
 */
function roro_handle_delete_favorite() {
    if ( ! current_user_can( 'manage_roro' ) ) {
        wp_die( __( 'You do not have sufficient permissions to delete favorites.', 'roro' ) );
    }
    // Verify nonce.  The nonce field is _wpnonce_roro_delete_favorite in the admin form.
    if ( ! isset( $_POST['_wpnonce_roro_delete_favorite'] ) || ! wp_verify_nonce( $_POST['_wpnonce_roro_delete_favorite'], 'roro_delete_favorite' ) ) {
        wp_die( __( 'Security check failed for favorite deletion.', 'roro' ) );
    }
    $fav_id = isset( $_POST['id'] ) ? intval( $_POST['id'] ) : 0;
    if ( $fav_id > 0 ) {
        global $wpdb;
        $fav_table = $wpdb->prefix . 'RORO_MAP_FAVORITE';
        // Delete the record.  Use %d format for ID to avoid SQL injection.
        $wpdb->delete( $fav_table, array( 'id' => $fav_id ), array( '%d' ) );
    }
    // Redirect back to the referring admin page with a flag indicating a favorite was deleted.
    $redirect = add_query_arg( array( 'page' => 'roro-admin', 'deleted' => 'favorite' ), admin_url( 'admin.php' ) );
    wp_safe_redirect( $redirect );
    exit;
}

// Handler registration for roro_delete_favorite is declared earlier with other admin actions.

/**
 * =============================
 * 郵便番号補完の設定
 *
 * 管理者が住所自動補完機能の有効化／無効化とキャッシュの TTL を調整できるように、
 * 設定 API を登録します。 これらの設定は options テーブルに保存されます。
 */

/**
 * Register settings for postcode auto‑complete.  The options are:
 *  - roro_geo_zip_enabled (boolean): whether postcode lookup is enabled.  Defaults to true.
 *  - roro_geo_cache_ttl   (integer): cache TTL in seconds.  Defaults to 86400 (1 day).
 */
function roro_geo_register_settings() {
    register_setting( 'roro-geo-settings', 'roro_geo_zip_enabled', array(
        'type'              => 'boolean',
        'default'           => true,
        // Sanitize any value to a strict boolean.  Checked checkboxes return '1'.
        'sanitize_callback' => function ( $value ) {
            return (bool) $value;
        },
    ) );
    register_setting( 'roro-geo-settings', 'roro_geo_cache_ttl', array(
        'type'              => 'integer',
        'default'           => DAY_IN_SECONDS,
        'sanitize_callback' => function ( $value ) {
            $val = intval( $value );
            // Force non‑negative integers; 0 disables caching
            return $val < 0 ? 0 : $val;
        },
    ) );
    // Settings section (informational only)
    add_settings_section( 'roro_geo_main', '郵便番号補完の設定', '__return_null', 'roro-geo-settings' );
    // Enable/disable field
    add_settings_field( 'roro_geo_zip_enabled', '機能の有効化', function () {
        $enabled = get_option( 'roro_geo_zip_enabled', true );
        echo '<label><input type="checkbox" name="roro_geo_zip_enabled" value="1" ' . checked( $enabled, true, false ) . ' /> 有効にする</label>';
    }, 'roro-geo-settings', 'roro_geo_main' );
    // TTL field
    add_settings_field( 'roro_geo_cache_ttl', 'キャッシュTTL（秒）', function () {
        $ttl = intval( get_option( 'roro_geo_cache_ttl', DAY_IN_SECONDS ) );
        echo '<input type="number" name="roro_geo_cache_ttl" value="' . esc_attr( $ttl ) . '" min="0" step="60" />';
        echo '<p class="description">0でキャッシュなし。既定は24時間（86400秒）</p>';
    }, 'roro-geo-settings', 'roro_geo_main' );
}
add_action( 'admin_init', 'roro_geo_register_settings' );

/**
 * Add a settings page under Settings → RORO 住所補完.  This page allows
 * administrators to enable/disable postcode lookup and adjust the cache TTL.
 */
function roro_geo_add_settings_page() {
    add_options_page( 'RORO 住所補完', 'RORO 住所補完', 'manage_options', 'roro-geo-settings', 'roro_geo_render_settings_page' );
}
add_action( 'admin_menu', 'roro_geo_add_settings_page' );

/**
 * Render the postcode auto‑complete settings page.  Uses the Settings API
 * functions to output form fields and handle submissions.
 */
function roro_geo_render_settings_page() {
    if ( ! current_user_can( 'manage_options' ) ) {
        return;
    }
    echo '<div class="wrap">';
    echo '<h1>RORO 住所補完設定</h1>';
    echo '<form method="post" action="options.php">';
    settings_fields( 'roro-geo-settings' );
    do_settings_sections( 'roro-geo-settings' );
    submit_button();
    echo '</form>';
    echo '</div>';
}

// ===== Roro REST Loader =====
// Register custom REST API routes for profile, pets, events, magazine, maintenance.
require_once get_template_directory() . '/includes/rest/class-roro-rest-loader.php';

// ===== Shortcodes for profile and pets panels =====
require_once get_template_directory() . '/includes/shortcodes.php';

// ===== Admin menu integrations for events and magazine =====
require_once get_template_directory() . '/admin/roro-admin-menu.php';
require_once get_template_directory() . '/admin/roro-admin-events.php';
require_once get_template_directory() . '/admin/roro-admin-mag.php';
require_once get_template_directory() . '/admin/roro-admin-analytics.php';
// Spots admin page
require_once get_template_directory() . '/admin/roro-admin-spots.php';

// Advice admin page
require_once get_template_directory() . '/admin/roro-admin-advice.php';

// Status history admin page
require_once get_template_directory() . '/admin/roro-admin-history.php';

// Recommendation analytics admin page
require_once get_template_directory() . '/admin/roro-admin-recommend.php';

// Stage27: Recommendation KPI dashboard page
require_once get_template_directory() . '/admin/roro-admin-recommend-dashboard.php';

// Core Web Vitals admin page for aggregated metric insights.  This file
// registers a new admin submenu and renders a simple table of average
// LCP/CLS/INP values over recent periods (today/7days/30days).
require_once get_template_directory() . '/admin/roro-admin-web-vitals.php';

// Register Roro Editor role for content managers.  This role grants
// basic post editing capabilities plus manage_options (for simplicity).
// If the role already exists, it will not be recreated.  Only run this
// in the admin context to avoid unnecessary overhead on the front-end.
add_action( 'init', function() {
    if ( ! is_admin() ) {
        return;
    }
    if ( ! get_role( 'roro_editor' ) ) {
        add_role( 'roro_editor', 'Roro Editor', [
            'read'           => true,
            'edit_posts'     => true,
            'manage_options' => true,
        ] );
    }
} );

// ===== ユーザー削除時の連動クリーンアップ =====
add_action( 'delete_user', function( $user_id ) {
    global $wpdb;
    $cid = $wpdb->get_var( $wpdb->prepare( 'SELECT customer_id FROM RORO_USER_LINK_WP WHERE wp_user_id=%d', $user_id ) );
    if ( $cid ) {
        // Delete pets for this customer
        $wpdb->delete( 'RORO_PET', [ 'customer_id' => $cid ] );
        // Delete favorites belonging to this WP user
        $wpdb->delete( 'wp_RORO_MAP_FAVORITE', [ 'user_id' => $user_id ] );
        // Delete link
        $wpdb->delete( 'RORO_USER_LINK_WP', [ 'wp_user_id' => $user_id ] );
        // Delete address
        $wpdb->delete( 'RORO_ADDRESS', [ 'customer_id' => $cid ] );
        // Delete customer
        $wpdb->delete( 'RORO_CUSTOMER', [ 'id' => $cid ] );
    }
}, 10, 1 );

// ===== Cron インターバル追加（週次） =====
add_filter( 'cron_schedules', function( $schedules ) {
    if ( ! isset( $schedules['weekly'] ) ) {
        $schedules['weekly'] = [ 'interval' => 7 * 24 * 60 * 60, 'display' => 'Once Weekly' ];
    }
    return $schedules;
} );

// ===== WP API フェッチ用スクリプト enqueue =====
// Ensure wp.apiFetch and wp.element are available on admin and front‑end for our REST UI widgets.
add_action( 'admin_enqueue_scripts', function() {
    wp_enqueue_script( 'wp-api-fetch' );
    wp_enqueue_script( 'wp-element' );
} );
add_action( 'wp_enqueue_scripts', function() {
    wp_enqueue_script( 'wp-api-fetch' );
    wp_enqueue_script( 'wp-element' );
} );

/**
 * Add defer attribute to theme scripts to improve initial page load performance.
 *
 * Adding `defer` tells the browser not to block HTML parsing while downloading
 * and executing the script. We apply this only to scripts registered with
 * handles that start with "roro-", excluding the map scripts because
 * Google Maps requires synchronous loading for its callback mechanisms.
 *
 * @param string $tag    The script tag for the enqueued script.
 * @param string $handle The script's registered handle.
 * @param string $src    The script's source URL.
 * @return string Modified script tag with `defer` attribute when appropriate.
 */
function roro_add_defer_attribute( $tag, $handle, $src ) {
    // Skip map loader and map scripts; Google Maps relies on synchronous execution.
    if ( strpos( $handle, 'roro-map' ) === 0 ) {
        return $tag;
    }
    // Defer all other theme scripts starting with the 'roro-' prefix.
    if ( strpos( $handle, 'roro-' ) === 0 ) {
        // Only add defer if it's not already present.
        if ( false === strpos( $tag, ' defer ' ) ) {
            $tag = str_replace( '<script ', '<script defer ', $tag );
        }
    }
    return $tag;
}
add_filter( 'script_loader_tag', 'roro_add_defer_attribute', 10, 3 );

/**
 * Enqueue the web vitals measurement script on all front‑end pages.
 *
 * This script leverages the PerformanceObserver API to capture Core Web
 * Vitals metrics (LCP, CLS, INP) and posts them to a REST endpoint for
 * aggregation.  It is enqueued with the theme version as cache busting
 * and localises the endpoint URL into the global
 * `RORO_WEB_VITALS_ENDPOINT` variable.
 */
function roro_enqueue_web_vitals() {
    $theme_uri  = get_template_directory_uri();
    $theme_path = get_template_directory();
    $script_path = $theme_path . '/js/web-vitals.js';
    if ( file_exists( $script_path ) ) {
        wp_enqueue_script( 'roro-web-vitals', $theme_uri . '/js/web-vitals.js', [], filemtime( $script_path ), true );
        // Localise both the REST endpoint and sampling rate for the web vitals script.  The
        // sampling rate controls what percentage of page loads will report metrics.
        wp_localize_script( 'roro-web-vitals', 'RORO_WEB_VITALS_ENDPOINT', rest_url( 'roro/v1/web-vitals' ) );
        // Provide a default sampling rate of 0.1 (10%).  Administrators can filter
        // roro_web_vitals_sampling to override this value via theme or plugin.
        $sampling = apply_filters( 'roro_web_vitals_sampling', 0.1 );
        wp_localize_script( 'roro-web-vitals', 'RORO_WEB_VITALS_SAMPLING', $sampling );
    }
}
add_action( 'wp_enqueue_scripts', 'roro_enqueue_web_vitals' );