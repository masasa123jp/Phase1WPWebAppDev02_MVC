<?php
require_once __DIR__ . '/BaseController.php';
require_once __DIR__ . '/../models/CustomerModel.php';

/**
 * ProfileController gathers profile information for the logged in user.
 * It combines data from WordPress, the RORO customer table and the pet
 * table to generate a structured profile object for the front‑end.
 */
class ProfileController extends BaseController {
    /**
     * Localises the current user's profile data into a script.  The
     * front‑end can then populate the profile page from the `RORO_PROFILE`
     * variable.
     *
     * @param string $script_handle
     */
    public function localize_profile_data( $script_handle = 'roro-profile' ) {
        $user_id = get_current_user_id();
        if ( ! $user_id ) {
            // No user logged in; no data to provide
            return;
        }
        $user = get_userdata( $user_id );
        // Retrieve linked customer
        global $wpdb;
        $link_table = $wpdb->prefix . 'RORO_USER_LINK_WP';
        $cust_id    = $wpdb->get_var( $wpdb->prepare( "SELECT customer_id FROM $link_table WHERE wp_user_id = %d", $user_id ) );
        $cust_data  = null;
        if ( $cust_id ) {
            $cust_model = new CustomerModel();
            $cust_data  = $cust_model->get_one( [ 'customer_id' => $cust_id ] );
        }
        // Build profile array
        $profile = [
            'wp_user_id'   => $user_id,
            'display_name' => $user->display_name,
            'email'        => $user->user_email,
            'customer_id'  => $cust_id,
            'prefecture'   => $cust_data ? $cust_data->prefecture : null,
            'city'         => $cust_data ? $cust_data->city : null,
        ];
        // Fetch pets
        $pets = [];
        if ( $cust_id ) {
            $pet_table = $wpdb->prefix . 'RORO_PET';
            $pet_rows  = $wpdb->get_results( $wpdb->prepare( "SELECT pet_id, species, pet_name, sex, birth_date, weight_kg FROM $pet_table WHERE customer_id = %d AND is_active = 1", $cust_id ) );
            foreach ( $pet_rows as $p ) {
                $pets[] = [
                    'pet_id'    => (int) $p->pet_id,
                    'species'   => $p->species,
                    'name'      => $p->pet_name,
                    'sex'       => $p->sex,
                    'birth_date'=> $p->birth_date,
                    'weight_kg' => $p->weight_kg,
                ];
            }
        }
        $profile['pets'] = $pets;
        wp_localize_script( $script_handle, 'RORO_PROFILE', $profile );
    }
}
