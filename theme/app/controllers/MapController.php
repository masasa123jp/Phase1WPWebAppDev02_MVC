<?php
require_once __DIR__ . '/BaseController.php';
require_once __DIR__ . '/../models/EventModel.php';

/**
 * MapController supplies event data to the map page.  It fetches visible
 * events from the database and localises them into the `roro-map` script
 * so the JavaScript can render markers.  The controller is lightweight
 * because all heavy lifting is done in the EventModel.
 */
class MapController extends BaseController {
    /**
     * Loads event data and localises it into the specified script handle.
     *
     * @param string $script_handle The registered handle of the map script.
     */
    public function enqueue_event_data( $script_handle = 'roro-map' ) {
        $event_model = new EventModel();
        $events      = $event_model->get_all_visible();
        // Transform events into a simpler array for front‑end use
        $data = [];
        foreach ( $events as $e ) {
            $data[] = [
                'event_id' => $e->event_id,
                'name'     => $e->name,
                'date'     => $e->date,
                'prefecture' => $e->prefecture,
                'city'     => $e->city,
                'lat'      => (float) $e->lat,
                'lon'      => (float) $e->lon,
                'url'      => $e->url,
            ];
        }
        // Localise into the map script; the variable RORO_EVENT_DATA will
        // contain the array of events
        wp_localize_script( $script_handle, 'RORO_EVENT_DATA', $data );
    }
}
