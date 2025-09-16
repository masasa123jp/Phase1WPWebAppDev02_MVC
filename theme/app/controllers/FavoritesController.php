<?php
require_once __DIR__ . '/BaseController.php';
require_once __DIR__ . '/../models/FavoriteModel.php';

/**
 * FavoritesController provides methods to fetch and toggle favourites.
 * It can be used from page templates and also registers AJAX handlers
 * for asynchronous operations.
 */
class FavoritesController extends BaseController {
    /**
     * Sends the current user's favourite items as JSON.  This is typically
     * called via AJAX from the favourites page.
     */
    public function ajax_get_favorites() {
        $user_id = get_current_user_id();
        if ( ! $user_id ) {
            wp_send_json_error( [ 'message' => 'ログインが必要です。' ], 401 );
        }
        $model = new FavoriteModel();
        $rows  = $model->get_by_user( $user_id );
        $data  = [];
        // Build enriched favourite entries.  When the favourite refers to an
        // event we also include the event's metadata for display.  If the
        // event is not found or is not visible it will be omitted.
        if ( ! class_exists( 'EventModel' ) ) {
            require_once __DIR__ . '/../models/EventModel.php';
        }
        $event_model = class_exists( 'EventModel' ) ? new EventModel() : null;
        foreach ( $rows as $row ) {
            $entry = [
                'id'          => (int) $row->id,
                'target_type' => $row->target_type,
                'target_id'   => $row->target_id,
            ];
            if ( 'event' === $row->target_type && $event_model ) {
                $event = $event_model->get_by_id( $row->target_id );
                if ( $event ) {
                    $entry['name']     = $event->name;
                    $entry['date']     = $event->date;
                    $entry['prefecture'] = $event->prefecture;
                    $entry['city']     = $event->city;
                    $entry['location'] = $event->location;
                    $entry['url']      = $event->url;
                }
            }
            $data[] = $entry;
        }
        wp_send_json_success( $data );
    }

    /**
     * Toggles a favourite entry for the current user.  Expects target_type
     * and target_id in the request.  Returns the new favourite state.
     */
    public function ajax_toggle_favorite() {
        $user_id = get_current_user_id();
        if ( ! $user_id ) {
            wp_send_json_error( [ 'message' => 'ログインが必要です。' ], 401 );
        }
        $target_type = isset( $_POST['target_type'] ) ? sanitize_text_field( $_POST['target_type'] ) : '';
        $target_id   = isset( $_POST['target_id'] )   ? intval( $_POST['target_id'] ) : 0;
        if ( ! $target_type || ! $target_id ) {
            wp_send_json_error( [ 'message' => 'Invalid parameters' ], 400 );
        }
        $model   = new FavoriteModel();
        $exists  = $model->toggle_favorite( $user_id, $target_type, $target_id );
        wp_send_json_success( [ 'favorite' => $exists ] );
    }
}
