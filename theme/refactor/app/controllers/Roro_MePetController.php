<?php
/**
 * REST controller for owner‑scoped pet operations.  Provides
 * endpoints under `/wp-json/roro/me/pets` that allow a logged in user
 * to manage their own pets.  All operations verify that the pet
 * belongs to the current user and require the `edit_own_roro_profile`
 * capability for modifications.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

require_once __DIR__ . '/../models/Roro_CustomerModel.php';
require_once __DIR__ . '/../models/Roro_PetModel.php';

class Roro_MePetController {
    /**
     * Register the pet routes under the `roro/me` namespace.
     */
    public function register_routes() {
        $namespace = 'roro/me';
        // GET /roro/me/pets
        register_rest_route( $namespace, '/pets', array(
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => array( $this, 'get_pets' ),
            'permission_callback' => array( $this, 'permissions_read' ),
        ) );
        // POST /roro/me/pets
        register_rest_route( $namespace, '/pets', array(
            'methods'             => WP_REST_Server::CREATABLE,
            'callback'            => array( $this, 'create_pet' ),
            'permission_callback' => array( $this, 'permissions_edit' ),
        ) );
        // GET /roro/me/pets/{id}
        register_rest_route( $namespace, '/pets/(?P<id>\d+)', array(
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => array( $this, 'get_pet' ),
            'permission_callback' => array( $this, 'permissions_read' ),
            'args'                => array(
                'id' => array( 'validate_callback' => 'absint' ),
            ),
        ) );
        // PUT /roro/me/pets/{id}
        register_rest_route( $namespace, '/pets/(?P<id>\d+)', array(
            'methods'             => WP_REST_Server::EDITABLE,
            'callback'            => array( $this, 'update_pet' ),
            'permission_callback' => array( $this, 'permissions_edit' ),
            'args'                => array(
                'id' => array( 'validate_callback' => 'absint' ),
            ),
        ) );
        // DELETE /roro/me/pets/{id}
        register_rest_route( $namespace, '/pets/(?P<id>\d+)', array(
            'methods'             => WP_REST_Server::DELETABLE,
            'callback'            => array( $this, 'delete_pet' ),
            'permission_callback' => array( $this, 'permissions_edit' ),
            'args'                => array(
                'id' => array( 'validate_callback' => 'absint' ),
            ),
        ) );
    }

    /**
     * Permission callback for read operations.  Only logged in users
     * may view their pets.
     *
     * @return bool
     */
    public function permissions_read() {
        return is_user_logged_in();
    }

    /**
     * Permission callback for edit operations.  Requires the
     * `edit_own_roro_profile` capability.  This capability is added to
     * the subscriber role in functions.php.
     *
     * @return bool
     */
    public function permissions_edit() {
        return current_user_can( 'edit_own_roro_profile' );
    }

    /**
     * Get all pets belonging to the current user.
     *
     * @param WP_REST_Request $request
     * @return WP_REST_Response|WP_Error
     */
    public function get_pets( WP_REST_Request $request ) {
        $user_id = get_current_user_id();
        if ( ! $user_id ) {
            return new WP_Error( 'roro_not_logged_in', __( 'Not logged in', 'roro' ), array( 'status' => 403 ) );
        }
        $cust_model = new Roro_CustomerModel();
        $cust       = $cust_model->get_by_wp_user( $user_id );
        if ( ! $cust ) {
            return new WP_Error( 'roro_customer_not_found', __( 'Customer not found', 'roro' ), array( 'status' => 404 ) );
        }
        $pet_model = new Roro_PetModel();
        $rows      = $pet_model->get_by_customer( $cust->customer_id );
        $pets      = array();
        foreach ( $rows as $p ) {
            $pets[] = array(
                'pet_id'    => (int) $p->pet_id,
                'species'   => $p->species,
                'pet_name'  => $p->pet_name,
                'sex'       => $p->sex,
                'birth_date'=> $p->birth_date,
                'weight_kg' => $p->weight_kg,
            );
        }
        return rest_ensure_response( $pets );
    }

    /**
     * Retrieve a single pet for the current user.
     *
     * @param WP_REST_Request $request
     * @return WP_REST_Response|WP_Error
     */
    public function get_pet( WP_REST_Request $request ) {
        $user_id = get_current_user_id();
        if ( ! $user_id ) {
            return new WP_Error( 'roro_not_logged_in', __( 'Not logged in', 'roro' ), array( 'status' => 403 ) );
        }
        $pet_id = (int) $request['id'];
        $cust_model = new Roro_CustomerModel();
        $cust       = $cust_model->get_by_wp_user( $user_id );
        if ( ! $cust ) {
            return new WP_Error( 'roro_customer_not_found', __( 'Customer not found', 'roro' ), array( 'status' => 404 ) );
        }
        $pet_model = new Roro_PetModel();
        // Verify ownership
        if ( ! $pet_model->pet_belongs_to_customer( $pet_id, $cust->customer_id ) ) {
            return new WP_Error( 'roro_pet_forbidden', __( 'You do not have permission to view this pet', 'roro' ), array( 'status' => 403 ) );
        }
        $p = $pet_model->get_one_by( 'pet_id', $pet_id );
        $data = array(
            'pet_id'    => (int) $p->pet_id,
            'species'   => $p->species,
            'pet_name'  => $p->pet_name,
            'sex'       => $p->sex,
            'birth_date'=> $p->birth_date,
            'weight_kg' => $p->weight_kg,
        );
        return rest_ensure_response( $data );
    }

    /**
     * Create a new pet for the current user.
     *
     * @param WP_REST_Request $request
     * @return WP_REST_Response|WP_Error
     */
    public function create_pet( WP_REST_Request $request ) {
        $user_id = get_current_user_id();
        if ( ! $user_id ) {
            return new WP_Error( 'roro_not_logged_in', __( 'Not logged in', 'roro' ), array( 'status' => 403 ) );
        }
        $cust_model = new Roro_CustomerModel();
        $cust       = $cust_model->get_by_wp_user( $user_id );
        if ( ! $cust ) {
            return new WP_Error( 'roro_customer_not_found', __( 'Customer not found', 'roro' ), array( 'status' => 404 ) );
        }
        $pet_model = new Roro_PetModel();
        $params    = $request->get_json_params();
        if ( ! is_array( $params ) ) {
            return new WP_Error( 'roro_invalid_payload', __( 'Invalid payload', 'roro' ), array( 'status' => 400 ) );
        }
        // Normalise species to uppercase; default to DOG if missing
        $species = isset( $params['species'] ) ? sanitize_text_field( $params['species'] ) : 'DOG';
        $species = strtoupper( $species );
        $pet_name = isset( $params['pet_name'] ) ? sanitize_text_field( $params['pet_name'] ) : '';
        $insert_data = array(
            'customer_id' => (int) $cust->customer_id,
            'species'     => $species,
            'pet_name'    => $pet_name,
            'sex'         => isset( $params['sex'] ) ? sanitize_text_field( $params['sex'] ) : null,
            'birth_date'  => isset( $params['birth_date'] ) ? sanitize_text_field( $params['birth_date'] ) : null,
            'weight_kg'   => isset( $params['weight_kg'] ) ? sanitize_text_field( $params['weight_kg'] ) : null,
        );
        $insert_id = $pet_model->insert( $insert_data );
        if ( ! $insert_id ) {
            return new WP_Error( 'roro_pet_insert_failed', __( 'Could not create pet', 'roro' ), array( 'status' => 500 ) );
        }
        $data = array(
            'pet_id' => (int) $insert_id,
        );
        return rest_ensure_response( $data );
    }

    /**
     * Update an existing pet for the current user.
     *
     * @param WP_REST_Request $request
     * @return WP_REST_Response|WP_Error
     */
    public function update_pet( WP_REST_Request $request ) {
        $user_id = get_current_user_id();
        if ( ! $user_id ) {
            return new WP_Error( 'roro_not_logged_in', __( 'Not logged in', 'roro' ), array( 'status' => 403 ) );
        }
        $pet_id = (int) $request['id'];
        $cust_model = new Roro_CustomerModel();
        $cust       = $cust_model->get_by_wp_user( $user_id );
        if ( ! $cust ) {
            return new WP_Error( 'roro_customer_not_found', __( 'Customer not found', 'roro' ), array( 'status' => 404 ) );
        }
        $pet_model = new Roro_PetModel();
        if ( ! $pet_model->pet_belongs_to_customer( $pet_id, $cust->customer_id ) ) {
            return new WP_Error( 'roro_pet_forbidden', __( 'You do not have permission to modify this pet', 'roro' ), array( 'status' => 403 ) );
        }
        $params = $request->get_json_params();
        if ( ! is_array( $params ) ) {
            return new WP_Error( 'roro_invalid_payload', __( 'Invalid payload', 'roro' ), array( 'status' => 400 ) );
        }
        $update = array();
        if ( array_key_exists( 'species', $params ) ) {
            $update['species'] = strtoupper( sanitize_text_field( $params['species'] ) );
        }
        if ( array_key_exists( 'pet_name', $params ) ) {
            $update['pet_name'] = sanitize_text_field( $params['pet_name'] );
        }
        if ( array_key_exists( 'sex', $params ) ) {
            $update['sex'] = sanitize_text_field( $params['sex'] );
        }
        if ( array_key_exists( 'birth_date', $params ) ) {
            $update['birth_date'] = sanitize_text_field( $params['birth_date'] );
        }
        if ( array_key_exists( 'weight_kg', $params ) ) {
            $update['weight_kg'] = sanitize_text_field( $params['weight_kg'] );
        }
        if ( ! empty( $update ) ) {
            $pet_model->update_by_pk( $pet_id, $update );
        }
        return $this->get_pet( $request );
    }

    /**
     * Delete a pet belonging to the current user.
     *
     * @param WP_REST_Request $request
     * @return WP_REST_Response|WP_Error
     */
    public function delete_pet( WP_REST_Request $request ) {
        $user_id = get_current_user_id();
        if ( ! $user_id ) {
            return new WP_Error( 'roro_not_logged_in', __( 'Not logged in', 'roro' ), array( 'status' => 403 ) );
        }
        $pet_id = (int) $request['id'];
        $cust_model = new Roro_CustomerModel();
        $cust       = $cust_model->get_by_wp_user( $user_id );
        if ( ! $cust ) {
            return new WP_Error( 'roro_customer_not_found', __( 'Customer not found', 'roro' ), array( 'status' => 404 ) );
        }
        $pet_model = new Roro_PetModel();
        if ( ! $pet_model->pet_belongs_to_customer( $pet_id, $cust->customer_id ) ) {
            return new WP_Error( 'roro_pet_forbidden', __( 'You do not have permission to delete this pet', 'roro' ), array( 'status' => 403 ) );
        }
        $deleted = $pet_model->delete_by_pk( $pet_id );
        if ( false === $deleted ) {
            return new WP_Error( 'roro_pet_delete_failed', __( 'Could not delete pet', 'roro' ), array( 'status' => 500 ) );
        }
        return rest_ensure_response( array( 'deleted' => true ) );
    }
}