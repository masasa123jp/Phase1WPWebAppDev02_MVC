<?php
/**
 * Header template for the RORO Mock Theme.
 *
 * This template outputs the basic document structure and includes the
 * WordPress header hooks.  Individual page templates are responsible
 * for rendering their own page‑specific header content (such as the
 * application header with logo and title) after calling get_header().
 */
?><!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
    <meta charset="<?php bloginfo( 'charset' ); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <!-- Include the favicon from the images directory -->
    <link rel="icon" href="<?php echo esc_url( get_template_directory_uri() ); ?>/images/favicon.ico" type="image/x-icon" />
    <!-- Preload critical images to improve LCP -->
    <link rel="preload" as="image" href="<?php echo esc_url( get_template_directory_uri() ); ?>/images/logo_roro.png" />
    <link rel="preload" as="image" href="<?php echo esc_url( get_template_directory_uri() ); ?>/images/magazine_cover1.png" />
    <link rel="preload" as="image" href="<?php echo esc_url( get_template_directory_uri() ); ?>/images/magazine_cover2.png" />
    <!-- Preconnect to fonts.googleapis.com/gstatic to speed up font loading -->
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <?php wp_head(); ?>
</head>
<body <?php body_class(); ?>>

<!-- Expose the current logged-in user ID to JavaScript for personalised features -->
<script>
  // Provide the WordPress user ID (0 if not logged in) for personalisation (e.g., recommendations).
  window.roroCurrentUserId = <?php echo json_encode( get_current_user_id() ); ?>;
</script>

<!-- Accessibility: Skip link allows keyboard users to jump directly to main content -->
<a href="#main" class="skip-link" data-i18n-key="skip_to_main" style="position:absolute;left:-10000px;top:auto;width:1px;height:1px;overflow:hidden;">メインコンテンツへスキップ</a>

<?php
// ===== デスクトップ向けトップナビゲーション =====
// ログインページやサインアップページではナビゲーションを表示しない。
$current_slug_top = '';
if ( is_front_page() || is_home() ) {
    $current_slug_top = 'index';
} elseif ( is_page() ) {
    global $post;
    if ( $post instanceof WP_Post ) {
        $current_slug_top = $post->post_name;
    }
}
$show_top_nav = true;
// Hide on login/front and sign‑up pages
if ( is_front_page() || is_home() || $current_slug_top === '' || $current_slug_top === 'index' || is_page_template( 'page-signup.php' ) ) {
    $show_top_nav = false;
}
if ( $show_top_nav ) : ?>
<nav class="top-nav" role="navigation" aria-label="Main navigation">
<a href="<?php echo esc_url( home_url( '/map/' ) ); ?>" class="nav-item<?php echo ( $current_slug_top === 'map' ? ' active' : '' ); ?>" role="menuitem" aria-label="<?php echo esc_attr__( 'Map', 'roro' ); ?>">
    <img src="<?php echo esc_url( get_template_directory_uri() ); ?>/images/icon_map.png" alt="<?php echo esc_attr__( 'Map', 'roro' ); ?>" width="64" height="64" loading="lazy" fetchpriority="high" />
    <span data-i18n-key="nav_map">マップ</span>
</a>
    <a href="<?php echo esc_url( home_url( '/favorites/' ) ); ?>" class="nav-item<?php echo ( $current_slug_top === 'favorites' ? ' active' : '' ); ?>" role="menuitem" aria-label="<?php echo esc_attr__( 'Favorites', 'roro' ); ?>">
        <img src="<?php echo esc_url( get_template_directory_uri() ); ?>/images/icon_favorite.png" alt="<?php echo esc_attr__( 'Favorites', 'roro' ); ?>" width="64" height="64" loading="lazy" fetchpriority="high" />
        <span data-i18n-key="nav_favorites">お気に入り</span>
    </a>
    <a href="<?php echo esc_url( home_url( '/magazine/' ) ); ?>" class="nav-item<?php echo ( $current_slug_top === 'magazine' ? ' active' : '' ); ?>" role="menuitem" aria-label="<?php echo esc_attr__( 'Magazine', 'roro' ); ?>">
        <img src="<?php echo esc_url( get_template_directory_uri() ); ?>/images/icon_magazine.png" alt="<?php echo esc_attr__( 'Magazine', 'roro' ); ?>" width="64" height="64" loading="lazy" fetchpriority="high" />
        <span data-i18n-key="nav_magazine">雑誌</span>
    </a>
    <a href="<?php echo esc_url( home_url( '/profile/' ) ); ?>" class="nav-item<?php echo ( $current_slug_top === 'profile' ? ' active' : '' ); ?>" role="menuitem" aria-label="<?php echo esc_attr__( 'Profile', 'roro' ); ?>">
        <img src="<?php echo esc_url( get_template_directory_uri() ); ?>/images/icon_profile.png" alt="<?php echo esc_attr__( 'Profile', 'roro' ); ?>" width="64" height="64" loading="lazy" fetchpriority="high" />
        <span data-i18n-key="nav_profile">マイページ</span>
    </a>
</nav>
<?php endif; ?>

<?php
// ===== パンくずナビゲーション =====
// 表示中のページタイトルをホームリンクと区切りで表示します。フロントページやホームでは非表示にします。
if ( ! is_front_page() && ! is_home() ) :
    $breadcrumb_title = wp_get_document_title();
    ?>
    <nav class="breadcrumb">
        <a href="<?php echo esc_url( home_url( "/" ) ); ?>" class="breadcrumb-home" data-i18n-key="nav_home">ホーム</a>
        <span class="breadcrumb-separator">&gt;</span>
        <span class="breadcrumb-current">
            <?php echo esc_html( $breadcrumb_title ); ?>
        </span>
    </nav>
<?php endif; ?>