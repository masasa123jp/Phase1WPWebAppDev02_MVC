<?php
require_once __DIR__ . '/BaseModel.php';

/**
 * EventModel provides methods for retrieving event data from the
 * RORO_EVENTS_MASTER table.  This model only exposes read operations as
 * events are maintained externally and should not be modified via the
 * front‑end.
 */
class EventModel extends BaseModel {
    public function __construct() {
        $this->table = 'RORO_EVENTS_MASTER';
        parent::__construct( $this->table );
    }

    /**
     * Returns all visible events.  An event is considered visible when
     * its `isVisible` flag equals 1.
     *
     * @param int|null $limit Number of events to return (optional)
     * @return array List of event row objects
     */
    public function get_all_visible( $limit = null ) {
        $table  = $this->get_table();
        // Only return events marked as visible AND published.  If a status column
        // does not exist (backward compatibility), the condition will silently
        // be ignored by MySQL.  Limit the result if a limit is provided.
        $sql    = "SELECT * FROM `$table` WHERE `isVisible` = 1 AND `status` = 'published'";
        $values = [];
        if ( $limit !== null ) {
            $sql .= " LIMIT %d";
            $values[] = (int) $limit;
        }
        return $this->db->get_results( $this->db->prepare( $sql, $values ) );
    }

    /**
     * Fetches a single event by its primary key (event_id).
     *
     * @param string $event_id
     * @return object|null
     */
    public function get_by_id( $event_id ) {
        return $this->get_one( [ 'event_id' => $event_id ] );
    }
}
