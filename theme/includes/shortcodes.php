<?php
// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Shortcodes for embedding profile and pets panels on pages.
 *
 * Usage:
 * - [roro_profile_form] to render the profile editing form
 * - [roro_pets_panel] to render the pets management panel
 */
add_shortcode( 'roro_profile_form', function() {
    ob_start();
    locate_template( 'templates/partials/profile-form.php', true, false );
    return ob_get_clean();
} );
add_shortcode( 'roro_pets_panel', function() {
    ob_start();
    locate_template( 'templates/partials/pets-panel.php', true, false );
    return ob_get_clean();
} );
