<?php
// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Register top-level Roro management menu and submenu pages.
 */
add_action( 'admin_menu', function() {
    // Top-level menu
    add_menu_page( 'Roro管理', 'Roro管理', 'manage_options', 'roro-admin', function() {
        echo '<div class="wrap"><h1>Roro 管理</h1><p>イベントと雑誌の管理を行えます。</p></div>';
    }, 'dashicons-pets', 30 );
    // Event submenu: use fine‑grained capability so only users with
    // manage_roro_events can access event management.
    add_submenu_page( 'roro-admin', 'イベント管理', 'イベント管理', 'manage_roro_events', 'roro-admin-events', 'roro_admin_events_page' );
    // Magazine submenu remains under manage_options; magazine management
    // continues to require full admin rights for now.
    add_submenu_page( 'roro-admin', '雑誌管理', '雑誌管理', 'manage_options', 'roro-admin-mag', 'roro_admin_mag_page' );

    // Analytics submenu remains admin-only.
    add_submenu_page( 'roro-admin', 'アナリティクス', 'アナリティクス', 'manage_options', 'roro-admin-analytics', 'roro_admin_analytics_page' );

    // Spots submenu: assign manage_roro_spots capability.
    add_submenu_page( 'roro-admin', 'スポット管理', 'スポット管理', 'manage_roro_spots', 'roro-admin-spots', 'roro_admin_spots_page' );

    // Advice submenu: assign manage_roro_advice capability.
    add_submenu_page( 'roro-admin', 'アドバイス管理', 'アドバイス管理', 'manage_roro_advice', 'roro-admin-advice', 'roro_admin_advice_page' );

    // Status history submenu: allow administrators to inspect status change logs.
    add_submenu_page( 'roro-admin', 'ステータス履歴', '履歴閲覧', 'manage_options', 'roro-admin-history', 'roro_admin_history_page' );

    // Recommendation analytics submenu: display aggregate favourite counts for events.
    add_submenu_page( 'roro-admin', '推薦分析', '推薦分析', 'manage_options', 'roro-admin-recommend', 'roro_admin_recommend_page' );

    // Recommendation click report submenu: display aggregated click counts over a date range.
    add_submenu_page( 'roro-admin', 'クリックレポート', 'クリックレポート', 'manage_options', 'roro-admin-recommend-report', 'roro_admin_recommend_report_page' );

    // Stage27: KPI dashboard with charts for recommendation clicks
    add_submenu_page( 'roro-admin', '推薦ダッシュボード', '推薦ダッシュボード', 'manage_options', 'roro-admin-recommend-dashboard', 'roro_admin_recommend_dashboard_page' );

    // Core Web Vitals submenu: expose average performance metrics.  This
    // page summarises LCP/CLS/INP values over recent periods to aid
    // performance tuning.  Only administrators (manage_options) may
    // view this data.
    add_submenu_page( 'roro-admin', 'Web Vitals', 'Web Vitals', 'manage_options', 'roro-admin-web-vitals', 'roro_admin_web_vitals_page' );
} );
