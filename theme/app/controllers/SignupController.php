<?php
require_once __DIR__ . '/BaseController.php';
require_once __DIR__ . '/../models/CustomerModel.php';

/**
 * SignupController handles registration of new customers.  It creates both
 * a WordPress user account and a corresponding record in the
 * RORO_CUSTOMER table.  After successful signup the controller logs
 * the new user in and redirects to the home page.
 */
class SignupController extends BaseController {
    /**
     * Entry point.  Call this from a page template to process incoming
     * requests and display the sign‑up form.  If the request method is
     * POST the controller attempts to create a new account; otherwise it
     * simply renders the form.
     */
    public function handle_request() {
        if ( $_SERVER['REQUEST_METHOD'] === 'POST' ) {
            $this->process_signup();
        }
    }

    /**
     * Performs the sign‑up workflow: validate input, create the WordPress
     * user, create the customer record, optionally create a pet record and
     * link the two accounts.  On success the user is automatically logged
     * in and redirected.  On failure an error message is stored in
     * `$this->error` and output in the template.
     */
    protected function process_signup() {
        // Collect and sanitise form fields
        $name     = isset( $_POST['name'] )     ? sanitize_text_field( $_POST['name'] )     : '';
        $furigana = isset( $_POST['furigana'] ) ? sanitize_text_field( $_POST['furigana'] ) : '';
        $email    = isset( $_POST['email'] )    ? sanitize_email( $_POST['email'] )          : '';
        $password = isset( $_POST['password'] ) ? $_POST['password'] : '';
        $petType  = isset( $_POST['petType'] )  ? sanitize_text_field( $_POST['petType'] )  : '';
        $petName  = isset( $_POST['petName'] )  ? sanitize_text_field( $_POST['petName'] )  : '';

        if ( empty( $email ) || empty( $password ) ) {
            add_action( 'wp_footer', function() {
                echo '<div class="notice notice-error"><p>メールアドレスとパスワードは必須です。</p></div>';
            } );
            return;
        }

        // Check if WordPress user already exists
        if ( username_exists( $email ) || email_exists( $email ) ) {
            add_action( 'wp_footer', function() {
                echo '<div class="notice notice-error"><p>既に登録されているメールアドレスです。</p></div>';
            } );
            return;
        }

        // Create WordPress user with role 'subscriber'
        $user_id = wp_create_user( $email, $password, $email );
        if ( is_wp_error( $user_id ) ) {
            add_action( 'wp_footer', function() use ( $user_id ) {
                echo '<div class="notice notice-error"><p>ユーザーの作成に失敗しました: ' . esc_html( $user_id->get_error_message() ) . '</p></div>';
            } );
            return;
        }
        wp_update_user( [ 'ID' => $user_id, 'display_name' => $name ] );

        // Create customer record
        $cust_model = new CustomerModel();
        $customer_id = $cust_model->create( $email );
        if ( ! $customer_id ) {
            // Roll back WP user creation
            wp_delete_user( $user_id );
            add_action( 'wp_footer', function() {
                echo '<div class="notice notice-error"><p>顧客情報の登録に失敗しました。</p></div>';
            } );
            return;
        }

        // Link WP user and customer
        global $wpdb;
        $link_table = $wpdb->prefix . 'RORO_USER_LINK_WP';
        $wpdb->insert( $link_table, [
            'customer_id' => $customer_id,
            'wp_user_id'  => $user_id,
            'linked_at'   => current_time( 'mysql' ),
        ] );

        // Optionally create a pet record if provided
        if ( ! empty( $petType ) || ! empty( $petName ) ) {
            // Determine species and breed fields for the pet
            $species = strtoupper( $petType );
            if ( ! in_array( $species, [ 'DOG', 'CAT', 'OTHER' ], true ) ) {
                $species = 'OTHER';
            }
            $pet_data = [
                'customer_id' => $customer_id,
                'species'     => $species,
                'pet_name'    => $petName,
                'created_at'  => current_time( 'mysql' ),
                'updated_at'  => current_time( 'mysql' ),
            ];
            $pet_table = $wpdb->prefix . 'RORO_PET';
            $wpdb->insert( $pet_table, $pet_data );
        }

        // Log the user in automatically
        wp_set_current_user( $user_id );
        wp_set_auth_cookie( $user_id );

        // Redirect to the map page after registration
        wp_safe_redirect( home_url( '/map/' ) );
        exit;
    }
}
