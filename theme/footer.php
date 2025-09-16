<?php
/**
 * Footer template for the RORO Mock Theme.
 *
 * The theme includes a bottom navigation bar on every page after login.
 * This footer renders that navigation and highlights the active page
 * automatically based on the current post slug.  After the navigation
 * WordPress footer hooks are called and the document is closed.
 */

// Determine the current slug to apply the active class.  If the front
// page is being displayed we treat it as the login page.
$current_slug = '';
if ( is_front_page() || is_home() ) {
    $current_slug = 'index';
} elseif ( is_page() ) {
    global $post;
    if ( $post instanceof WP_Post ) {
        $current_slug = $post->post_name;
    }
}

// Helper function to echo active class.
function roro_active_class( $slug, $current_slug ) {
    return ( $slug === $current_slug ) ? ' active' : '';
}
?>

    <?php
    /**
     * Only output the bottom navigation when we are not on the login page.
     * The login page is the front page (or home) of the site.  When a
     * legacy URL like /mock-01_logon/ is rewritten to the front page
     * the slug will be empty or "index".  In these cases we skip the
     * navigation entirely so that users cannot navigate before
     * authenticating.
     */
    $show_nav = true;
    // Hide navigation on the front/login page and the sign‑up page.
    if ( is_front_page() || is_home() || $current_slug === '' || $current_slug === 'index' || is_page_template( 'page-signup.php' ) ) {
        $show_nav = false;
    }
    if ( $show_nav ) :
    ?>
    <nav class="bottom-nav" role="navigation" aria-label="Bottom navigation">
        <a href="<?php echo esc_url( home_url( '/map/' ) ); ?>" class="nav-item<?php echo roro_active_class( 'map', $current_slug ); ?>" role="menuitem" aria-label="<?php echo esc_attr__( 'Map', 'roro' ); ?>">
            <img src="<?php echo esc_url( get_template_directory_uri() ); ?>/images/icon_map.png" alt="<?php echo esc_attr__( 'Map', 'roro' ); ?>" width="64" height="64" loading="lazy" fetchpriority="high" />
            <span data-i18n-key="nav_map">マップ</span>
        </a>
        <!-- AIアシスタント機能は未実装のため、ナビゲーションから除外しています -->
        <!--
        <a href="<?php echo esc_url( home_url( '/dify/' ) ); ?>" class="nav-item<?php echo roro_active_class( 'dify', $current_slug ); ?>">
            <img src="<?php echo esc_url( get_template_directory_uri() ); ?>/images/icon_ai.png" alt="AI" />
            <span data-i18n-key="nav_ai">AI</span>
        </a>
        -->
        <a href="<?php echo esc_url( home_url( '/favorites/' ) ); ?>" class="nav-item<?php echo roro_active_class( 'favorites', $current_slug ); ?>" role="menuitem" aria-label="<?php echo esc_attr__( 'Favorites', 'roro' ); ?>">
            <img src="<?php echo esc_url( get_template_directory_uri() ); ?>/images/icon_favorite.png" alt="<?php echo esc_attr__( 'Favorites', 'roro' ); ?>" width="64" height="64" loading="lazy" fetchpriority="high" />
            <span data-i18n-key="nav_favorites">お気に入り</span>
        </a>
        <a href="<?php echo esc_url( home_url( '/magazine/' ) ); ?>" class="nav-item<?php echo roro_active_class( 'magazine', $current_slug ); ?>" role="menuitem" aria-label="<?php echo esc_attr__( 'Magazine', 'roro' ); ?>">
            <img src="<?php echo esc_url( get_template_directory_uri() ); ?>/images/icon_magazine.png" alt="<?php echo esc_attr__( 'Magazine', 'roro' ); ?>" width="64" height="64" loading="lazy" fetchpriority="high" />
            <span data-i18n-key="nav_magazine">雑誌</span>
        </a>
        <a href="<?php echo esc_url( home_url( '/profile/' ) ); ?>" class="nav-item<?php echo roro_active_class( 'profile', $current_slug ); ?>" role="menuitem" aria-label="<?php echo esc_attr__( 'Profile', 'roro' ); ?>">
            <img src="<?php echo esc_url( get_template_directory_uri() ); ?>/images/icon_profile.png" alt="<?php echo esc_attr__( 'Profile', 'roro' ); ?>" width="64" height="64" loading="lazy" fetchpriority="high" />
            <span data-i18n-key="nav_profile">マイページ</span>
        </a>
    </nav>
    <?php endif; ?>

    <?php wp_footer(); ?>
</body>
</html>