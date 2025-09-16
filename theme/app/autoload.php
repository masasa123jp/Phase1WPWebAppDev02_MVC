<?php
/**
 * Simple autoloader for MVC classes.
 *
 * This autoloader attempts to load classes from the `models` and `controllers`
 * directories under the `app` folder.  Classes should be named without
 * namespaces (e.g. `CustomerModel` or `SignupController`) and saved in
 * files matching the class name.  For example, `CustomerModel` should live
 * in `app/models/CustomerModel.php`.
 */

spl_autoload_register( function ( $class ) {
    // Only handle our own classes (no namespace)
    // Build potential file paths in the models and controllers directories
    $base_dir = __DIR__;
    $locations = [
        $base_dir . '/models/' . $class . '.php',
        $base_dir . '/controllers/' . $class . '.php',
    ];
    foreach ( $locations as $file ) {
        if ( file_exists( $file ) ) {
            require_once $file;
            return;
        }
    }
} );
