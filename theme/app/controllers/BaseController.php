<?php
/**
 * BaseController supplies common helper functionality to all controllers.
 *
 * Controllers are responsible for orchestrating requests, invoking models
 * and rendering views.  They should not perform data access directly.
 */

class BaseController {
    /**
     * Renders a PHP template and extracts provided data variables into the
     * local scope.  Templates should be stored in the theme root or
     * within the `app/views` directory.  This method falls back to
     * `locate_template()` which searches through the current theme and
     * parent themes.
     *
     * @param string $template Relative path to the template (e.g. 'app/views/signup-form.php')
     * @param array  $data     Associative array of variables to extract for the template.
     */
    protected function render( $template, array $data = [] ) {
        if ( ! empty( $data ) ) {
            extract( $data, EXTR_SKIP );
        }
        $full_path = locate_template( $template );
        if ( ! $full_path ) {
            // Attempt to resolve relative to theme directory
            $full_path = get_template_directory() . '/' . ltrim( $template, '/' );
        }
        if ( file_exists( $full_path ) ) {
            include $full_path;
        } else {
            echo '<!-- Template not found: ' . esc_html( $template ) . ' -->';
        }
    }
}
