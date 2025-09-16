<?php
// Prevent direct access
if ( ! defined('ABSPATH') ) {
    exit;
}

/**
 * Roro REST loader
 *
 * This class registers all REST endpoints defined for the Roro MVC Theme.
 */
class Roro_REST_Loader {
    /**
     * Initialise REST routes.
     * Hooked into rest_api_init by the loader itself.
     */
    public static function init() {
        add_action( 'rest_api_init', [ __CLASS__, 'register_routes' ] );
    }

    /**
     * Register all custom routes.
     */
    public static function register_routes() {
        // Utility helpers
        require_once __DIR__ . '/class-roro-rest-utils.php';
        // Profile endpoints
        require_once __DIR__ . '/class-roro-rest-profile.php';
        // Pets endpoints
        require_once __DIR__ . '/class-roro-rest-pets.php';
        // Events endpoints
        require_once __DIR__ . '/class-roro-rest-events.php';
        // Magazine endpoints
        require_once __DIR__ . '/class-roro-rest-magazine.php';
        // Maintenance endpoints
        require_once __DIR__ . '/class-roro-rest-maintenance.php';

        // Favorites endpoints
        require_once __DIR__ . '/class-roro-rest-favorites.php';

        // Analytics endpoints
        require_once __DIR__ . '/class-roro-rest-analytics.php';

        // Spots endpoints (travel spots).  Provides CRUD for RORO_TRAVEL_SPOT_MASTER.
        require_once __DIR__ . '/class-roro-rest-spots.php';

        // Advice endpoints (One Point Advice).  Provides CRUD for
        // RORO_ONE_POINT_ADVICE_MASTER and random advice retrieval.
        require_once __DIR__ . '/class-roro-rest-advice.php';

        // Recommendation endpoints (trending events and magazine pages).
        require_once __DIR__ . '/class-roro-rest-recommend.php';

        Roro_REST_Profile::register();
        Roro_REST_Pets::register();
        Roro_REST_Events::register();
        Roro_REST_Magazine::register();
        Roro_REST_Maintenance::register();

        // Register Favorites routes
        Roro_REST_Favorites::register();

        // Register analytics routes
        Roro_REST_Analytics::register();

        // Register spots routes
        Roro_REST_Spots::register();

        // Register advice routes
        Roro_REST_Advice::register();

        // Register recommendation routes
        Roro_REST_Recommend::register();

        // Web vitals routes: record Core Web Vitals metrics posted from the front end.
        require_once __DIR__ . '/class-roro-rest-web-vitals.php';
        Roro_REST_Web_Vitals::register();

        // Recommendation metrics: log when recommended events are clicked
        require_once __DIR__ . '/class-roro-rest-recommend-metrics.php';
        Roro_REST_Recommend_Metrics::register();
    }
}

// Kick off registration immediately so that routes are ready.
Roro_REST_Loader::init();
