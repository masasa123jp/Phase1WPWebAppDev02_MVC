<?php
/**
 * Bootstrap file to register custom REST API endpoints and load
 * underlying models and controllers for the RORO theme.
 *
 * This file ensures that the refactor classes are only loaded when
 * the theme is active.  It registers owner‑scoped endpoints under
 * the `roro/me` namespace which allow logged in users to retrieve
 * and update their own customer profile and pets without exposing
 * arbitrary CRUD operations on other users' data.
 */

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Autoload base models and controllers.  The files will be loaded
// relative to this bootstrap file.  When adding new models or
// controllers, ensure they are required here.
require_once __DIR__ . '/app/models/Roro_BaseModel.php';
require_once __DIR__ . '/app/models/Roro_CustomerModel.php';
require_once __DIR__ . '/app/models/Roro_PetModel.php';
// Include FavoriteModel to support unified favorites search in admin UI
require_once __DIR__ . '/app/models/Roro_FavoriteModel.php';
// The generic CRUD controller is provided for completeness but not
// actively registered in this bootstrap.  It can be enabled if
// broader REST endpoints are needed in the future.
require_once __DIR__ . '/app/controllers/Roro_CrudController.php';
require_once __DIR__ . '/app/controllers/Roro_MeCustomerController.php';
require_once __DIR__ . '/app/controllers/Roro_MePetController.php';

// Postcode lookup controller for address auto completion.  Loaded on init
// below.  This controller registers its own REST route to look up
// address information by postal code via ポストくん/ZipCloud.
require_once __DIR__ . '/app/controllers/Roro_PostcodeController.php';

// Register our custom endpoints on the rest_api_init hook.  Using a
// closure here allows classes to be lazily instantiated only when
// WordPress initialises the REST API.  See each controller for
// details of the routes registered.
add_action( 'rest_api_init', function () {
    // Owner scoped customer endpoints
    if ( class_exists( 'Roro_MeCustomerController' ) ) {
        $me_cust = new Roro_MeCustomerController();
        $me_cust->register_routes();
    }
    // Owner scoped pet endpoints
    if ( class_exists( 'Roro_MePetController' ) ) {
        $me_pet = new Roro_MePetController();
        $me_pet->register_routes();
    }
    // Postcode lookup controller
    if ( class_exists( 'Roro_PostcodeController' ) ) {
        $postcode = new Roro_PostcodeController();
        $postcode->register_routes();
    }
} );