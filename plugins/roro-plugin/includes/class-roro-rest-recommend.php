<?php
// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * REST endpoints providing simple recommendations, such as trending events or top‑viewed magazine pages.
 *
 * This class exposes two endpoints:
 *  - /roro/v1/recommend-events: returns the top 5 events ordered by favourite count and recency, with human‑readable reasons
 *  - /roro/v1/recommend-magazine: returns the top 5 magazine pages ordered by aggregated views, with reasons
 *
 * The event recommendations are public, while the magazine recommendations are restricted
 * to administrators (manage_options) because they expose internal analytics data.
 */
class Roro_REST_Recommend {
    const NS = 'roro/v1';

    /**
     * Register the recommendation routes.
     */
    public static function register() {
        register_rest_route( self::NS, '/recommend-events', [
            [
                'methods'             => 'GET',
                'callback'            => [ __CLASS__, 'recommend_events' ],
                'permission_callback' => function() {
                    // Public endpoint: no special capability required.
                    return true;
                },
            ],
        ] );
        register_rest_route( self::NS, '/recommend-magazine', [
            [
                'methods'             => 'GET',
                'callback'            => [ __CLASS__, 'recommend_magazine' ],
                'permission_callback' => function() {
                    // Require admin to access magazine analytics
                    return current_user_can( 'manage_options' );
                },
            ],
        ] );

        // Advanced event recommendation endpoint.  This route exposes
        // recommendations based on a more nuanced scoring model that
        // incorporates not only favourites and recency but also a bonus
        // weighting for upcoming events within a 30‑day window.  A
        // personalised boost is applied when the requesting user has
        // favourited an event.  Anyone may call this endpoint.
        register_rest_route( self::NS, '/recommend-events-advanced', [
            [
                'methods'             => 'GET',
                'callback'            => [ __CLASS__, 'recommend_events_advanced' ],
                'permission_callback' => function() {
                    return true;
                },
            ],
        ] );
    }

    /**
     * Recommend events based on favourite counts and recency.
     * Returns top 5 events with the highest number of favourites and most recent dates.
     * Also attaches a "reason" explaining why each event is recommended.
     *
     * @param WP_REST_Request $req
     * @return WP_REST_Response
     */
    public static function recommend_events( $req ) {
        global $wpdb;
        /*
         * Advanced recommendation algorithm
         *
         * This algorithm computes a weighted score for each event based on two factors:
         * - Overall popularity (number of favourites) multiplied by 10.
         * - Recency: subtract the number of days since the event date.
         *
         * If a user_id parameter is supplied, we personalise the ranking by
         * awarding a bonus score when the event appears in the user's own
         * favourites.  This encourages events the user has already shown
         * interest in to be promoted toward the top of the list.
         */
        $user_id = 0;
        if ( $req instanceof WP_REST_Request ) {
            $user_param = $req->get_param( 'user_id' );
            if ( ! empty( $user_param ) ) {
                $user_id = intval( $user_param );
            }
        }
        // Build dynamic SQL fragments based on whether a user ID is provided.
        $userJoin   = '';
        $userSelect = '';
        $userScore  = '';
        if ( $user_id > 0 ) {
            $userJoin   = $wpdb->prepare( " LEFT JOIN wp_RORO_MAP_FAVORITE fu ON fu.target_type='event' AND fu.target_id=e.id AND fu.user_id = %d ", $user_id );
            $userSelect = ', COUNT(fu.id) AS user_fav_count';
            $userScore  = ' + (CASE WHEN COUNT(fu.id) > 0 THEN 50 ELSE 0 END)';
        }
        // Construct the full SQL statement.
        $sql = "SELECT e.id, e.name,
                    COALESCE(DATE_FORMAT(e.event_date, '%Y-%m-%d'), e.date) AS date,
                    COUNT(f.id) AS favorites" . $userSelect . ",
                    DATEDIFF(NOW(), e.event_date) AS days_diff,
                    (COUNT(f.id) * 10 - DATEDIFF(NOW(), e.event_date)" . $userScore . ") AS score
             FROM RORO_EVENTS_MASTER e
             LEFT JOIN wp_RORO_MAP_FAVORITE f
               ON f.target_type='event' AND f.target_id=e.id
             " . $userJoin . "
             WHERE e.isVisible = 1
             GROUP BY e.id
             ORDER BY score DESC, favorites DESC, e.event_date DESC
             LIMIT 5";
        $rows = $wpdb->get_results( $sql, ARRAY_A );
        // Append a human‑readable reason explaining why each event was recommended.
        $now = new DateTime();
        foreach ( $rows as &$row ) {
            $fav   = isset( $row['favorites'] ) ? intval( $row['favorites'] ) : 0;
            $dateStr = isset( $row['date'] ) ? $row['date'] : '';
            // Determine whether this event is favourited by the requesting user.
            $userFav  = isset( $row['user_fav_count'] ) ? intval( $row['user_fav_count'] ) : 0;
            $reasonParts = [];
            if ( $userFav > 0 ) {
                // Highlight personal favourites first when available.
                $reasonParts[] = __( 'あなたのお気に入りに登録されているため', 'roro' );
            }
            if ( $fav > 0 ) {
                // Explain the popularity based on favourite count.
                $reasonParts[] = sprintf( __( 'お気に入りが%d件', 'roro' ), $fav );
            }
            // Determine how soon the event is or was. Use the date string if available.
            if ( ! empty( $dateStr ) ) {
                try {
                    $eventDate = new DateTime( $dateStr );
                    $interval = $now->diff( $eventDate );
                    $days     = (int) $interval->days;
                    if ( $eventDate >= $now ) {
                        // Upcoming event
                        if ( $days <= 30 ) {
                            $reasonParts[] = sprintf( __( '開催日が近い（%s）', 'roro' ), $dateStr );
                        } else {
                            $reasonParts[] = sprintf( __( '開催日が%sで人気イベント', 'roro' ), $dateStr );
                        }
                    } else {
                        // Past event: emphasise popularity or recency
                        if ( $days <= 30 ) {
                            $reasonParts[] = sprintf( __( '最近開催（%s）', 'roro' ), $dateStr );
                        } else {
                            // Past and older: emphasise general popularity only.
                        }
                    }
                } catch ( Exception $e ) {
                    // Fallback if the date cannot be parsed.
                    $reasonParts[] = sprintf( __( '開催日が%s', 'roro' ), $dateStr );
                }
            }
            if ( empty( $reasonParts ) ) {
                $row['reason'] = __( '人気度と開催日からおすすめしています', 'roro' );
            } else {
                $row['reason'] = implode( '、', $reasonParts ) . __( 'ためおすすめしています', 'roro' );
            }
        }
        unset( $row );
        return Roro_REST_Utils::respond( $rows );
    }

    /**
     * Provide an advanced recommendation list for events.  This method uses
     * a more sophisticated scoring function that boosts events happening
     * soon (within 30 days) and applies a significant bonus if the
     * requesting user has previously favourited the event.  The score
     * calculation is as follows:
     *
     *   score = favourites * 15
     *         + (user_favourited ? 70 : 0)
     *         + max(0, 30 - days_until_event) * 5
     *         - abs(days_since_event)  // penalise older events
     *
     * The resulting list is ordered by score descending.  Each entry
     * includes a human‑readable reason describing why it was recommended.
     *
     * @param WP_REST_Request $req
     * @return WP_REST_Response
     */
    public static function recommend_events_advanced( $req ) {
        global $wpdb;
        $user_id = 0;
        if ( $req instanceof WP_REST_Request ) {
            $uid = $req->get_param( 'user_id' );
            if ( ! empty( $uid ) ) {
                $user_id = intval( $uid );
            }
        }
        // Build optional join and select for user favourites.
        $userJoin   = '';
        $userSelect = '';
        $userScore  = '';
        if ( $user_id > 0 ) {
            $userJoin   = $wpdb->prepare( " LEFT JOIN wp_RORO_MAP_FAVORITE uf ON uf.target_type='event' AND uf.target_id=e.id AND uf.user_id = %d ", $user_id );
            $userSelect = ', COUNT(uf.id) AS user_fav_count';
            $userScore  = ' + (CASE WHEN COUNT(uf.id) > 0 THEN 70 ELSE 0 END)';
        }
        // Compose SQL with weighted scoring.  We compute days_until_event
        // (negative for past events) and days_since_event (absolute days
        // difference) to influence the score.  COALESCE is used to
        // gracefully handle null event_date values.
        $sql = "SELECT e.id, e.name,
                       COALESCE(DATE_FORMAT(e.event_date, '%Y-%m-%d'), e.date) AS date,
                       COUNT(f.id) AS favourites" . $userSelect . ",
                       DATEDIFF(e.event_date, NOW()) AS days_until,
                       ABS(DATEDIFF(NOW(), e.event_date)) AS days_since,
                       (COUNT(f.id) * 15" . $userScore . " + GREATEST(0, 30 - ABS(DATEDIFF(e.event_date, NOW()))) * 5 - ABS(DATEDIFF(NOW(), e.event_date))) AS score
                FROM RORO_EVENTS_MASTER e
                LEFT JOIN wp_RORO_MAP_FAVORITE f ON f.target_type='event' AND f.target_id=e.id
                " . $userJoin . "
                WHERE e.isVisible = 1
                GROUP BY e.id
                ORDER BY score DESC, favourites DESC, e.event_date DESC
                LIMIT 5";
        $rows = $wpdb->get_results( $sql, ARRAY_A );
        // Build reasons for each result.
        $now  = new DateTime();
        foreach ( $rows as &$row ) {
            $fav = isset( $row['favourites'] ) ? intval( $row['favourites'] ) : 0;
            $dateStr = isset( $row['date'] ) ? $row['date'] : '';
            $userFav = isset( $row['user_fav_count'] ) ? intval( $row['user_fav_count'] ) : 0;
            $reasonParts = [];
            if ( $userFav > 0 ) {
                $reasonParts[] = __( 'あなたのお気に入りに登録されているため', 'roro' );
            }
            if ( $fav > 0 ) {
                $reasonParts[] = sprintf( __( 'お気に入りが%d件', 'roro' ), $fav );
            }
            if ( ! empty( $dateStr ) ) {
                try {
                    $eventDate = new DateTime( $dateStr );
                    $interval  = $now->diff( $eventDate );
                    $days      = (int) $interval->days;
                    if ( $eventDate >= $now ) {
                        if ( $days <= 30 ) {
                            $reasonParts[] = sprintf( __( '開催日が近い（%s）', 'roro' ), $dateStr );
                        } else {
                            $reasonParts[] = sprintf( __( '開催日が%sで人気イベント', 'roro' ), $dateStr );
                        }
                    } else {
                        if ( $days <= 30 ) {
                            $reasonParts[] = sprintf( __( '最近開催（%s）', 'roro' ), $dateStr );
                        }
                    }
                } catch ( Exception $e ) {
                    $reasonParts[] = sprintf( __( '開催日が%s', 'roro' ), $dateStr );
                }
            }
            if ( empty( $reasonParts ) ) {
                $row['reason'] = __( '人気度と開催日からおすすめしています', 'roro' );
            } else {
                $row['reason'] = implode( '、', $reasonParts ) . __( 'ためおすすめしています', 'roro' );
            }
        }
        unset( $row );
        return Roro_REST_Utils::respond( $rows );
    }

    /**
     * Recommend magazine pages based on aggregated views (top 5).
     * Also attaches a reason to each recommendation.
     *
     * @param WP_REST_Request $req
     * @return WP_REST_Response
     */
    public static function recommend_magazine( $req ) {
        global $wpdb;
        $rows = $wpdb->get_results(
            "SELECT issue_id, page_id, SUM(views) AS views
             FROM RORO_MAGAZINE_DAILY
             GROUP BY issue_id, page_id
             ORDER BY views DESC
             LIMIT 5",
            ARRAY_A
        );
        // Add a reason for each magazine recommendation based on view counts.
        foreach ( $rows as &$row ) {
            $views = isset( $row['views'] ) ? intval( $row['views'] ) : 0;
            if ( $views > 0 ) {
                $row['reason'] = sprintf( __( 'ビュー数が%d回と高いためおすすめしています', 'roro' ), $views );
            } else {
                $row['reason'] = __( '閲覧数が高いためおすすめしています', 'roro' );
            }
        }
        unset( $row );
        return Roro_REST_Utils::respond( $rows );
    }
}