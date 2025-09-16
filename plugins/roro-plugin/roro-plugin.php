<?php
/*
Plugin Name: RORO Functionality Plugin
Description: Separates key functionalities of the RORO theme into a plugin to support theme independence and extendability. This plugin registers REST endpoints for recommending events and magazine content with explanatory reasons.
Version: 1.0.0
Author: RORO Project Team
*/

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Load the recommendation class from the plugin's includes directory
// Include utility classes first
require_once plugin_dir_path( __FILE__ ) . 'includes/class-roro-rest-utils.php';
require_once plugin_dir_path( __FILE__ ) . 'includes/class-roro-rest-recommend.php';

/**
 * Hook into the REST API initialization to register RORO endpoints.
 */
function roro_plugin_register_routes() {
    if ( class_exists( 'Roro_REST_Recommend' ) ) {
        Roro_REST_Recommend::register();
    }
}
add_action( 'rest_api_init', 'roro_plugin_register_routes' );