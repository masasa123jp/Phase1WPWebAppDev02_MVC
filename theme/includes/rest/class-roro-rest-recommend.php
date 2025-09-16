<?php
// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * REST endpoints providing simple recommendations, such as trending events or top‑viewed magazine pages.
 *
 * This class exposes two endpoints:
 *  - /recommend-events: returns the top 5 events ordered by favourite count and recency
 *  - /recommend-magazine: returns the top 5 magazine pages ordered by aggregated views
 *
 * The event recommendations are public, while the magazine recommendations are restricted
 * to administrators (manage_options) because they expose internal analytics data.
 */
class Roro_REST_Recommend {
    const NS = 'roro/v1';

    /**
     * Weight constants used in the event recommendation scoring algorithm.
     * These constants allow fine‑tuning of the relative importance of each
     * factor (similarity, history, recency, popularity, proximity).  The
     * weights should sum to 1.0 for proper normalisation.  Adjust as
     * necessary when extending or rebalancing the algorithm.
     */
    const W_SIMILARITY = 0.20;
    const W_HISTORY    = 0.20;
    const W_RECENCY    = 0.20;
    const W_POPULARITY = 0.20;
    const W_PROXIMITY  = 0.20;

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

        // Expose a configurable recommendation endpoint.  This route
        // allows callers to specify custom weights for each scoring
        // dimension via query parameters.  For example, clients can
        // request `/roro/v1/recommend-events-configurable?w_recency=0.4&w_popularity=0.3&w_history=0.1&w_similarity=0.1&w_proximity=0.1` to
        // emphasise upcoming events over other factors.  If some weights
        // are omitted or invalid, the defaults defined by the class
        // constants will be used.  All weights are normalised so that
        // their sum equals 1.  This endpoint is public and does not
        // require authentication.  Results include a `reason` field
        // analogous to the standard recommendation endpoint.
        register_rest_route( self::NS, '/recommend-events-configurable', [
            [
                'methods'             => 'GET',
                'callback'            => [ __CLASS__, 'recommend_events_configurable' ],
                'permission_callback' => function() {
                    return true;
                },
                'args'                => [
                    'w_similarity' => [ 'required' => false ],
                    'w_history'    => [ 'required' => false ],
                    'w_recency'    => [ 'required' => false ],
                    'w_popularity' => [ 'required' => false ],
                    'w_proximity'  => [ 'required' => false ],
                ],
            ],
        ] );
    }

    /**
     * Recommend events based on favourite counts and recency.
     * Returns top 5 events with the highest number of favourites and most recent dates.
     *
     * @param WP_REST_Request $req
     * @return WP_REST_Response
     */
    public static function recommend_events( $req ) {
        global $wpdb;
        $user_id = get_current_user_id();
        // Determine current user's prefecture for proximity scoring.  Link the
        // WordPress user to the customer table via RORO_USER_LINK_WP and
        // retrieve the prefecture from RORO_CUSTOMER.  If no user is logged
        // in or the lookup fails, $user_prefecture remains null.
        $user_prefecture = null;
        if ( $user_id ) {
            // Use $wpdb for queries
            $link_table = $wpdb->prefix . 'RORO_USER_LINK_WP';
            $cust_id    = $wpdb->get_var( $wpdb->prepare( "SELECT customer_id FROM {$link_table} WHERE wp_user_id = %d", $user_id ) );
            if ( $cust_id ) {
                $cust_table = $wpdb->prefix . 'RORO_CUSTOMER';
                $pref       = $wpdb->get_var( $wpdb->prepare( "SELECT prefecture FROM {$cust_table} WHERE customer_id = %d", $cust_id ) );
                if ( $pref ) {
                    $user_prefecture = trim( (string) $pref );
                }
            }
        }
        // Define weights for each scoring factor.  The constants above allow
        // centralised control of weighting.  Adjust these in one place when
        // tuning the recommendation algorithm.
        $weights = [
            'similarity' => self::W_SIMILARITY,
            'history'    => self::W_HISTORY,
            'recency'    => self::W_RECENCY,
            'popularity' => self::W_POPULARITY,
            'proximity'  => self::W_PROXIMITY,
        ];

        // When personalised, exclude events that the user already favourited.
        $exclude_ids = [];
        $user_categories = [];
        if ( $user_id ) {
            $fav_table = $wpdb->prefix . 'RORO_MAP_FAVORITE';
            // Fetch the IDs of events favourited by the user
            $ids = $wpdb->get_col( $wpdb->prepare(
                "SELECT target_id FROM {$fav_table}
                 WHERE target_type = 'event' AND user_id = %d",
                $user_id
            ) );
            $exclude_ids = array_filter( array_map( 'intval', $ids ), function( $v ) { return $v > 0; } );
            // Fetch categories of those events to compute similarity.  Use DISTINCT to avoid duplicates.
            if ( ! empty( $exclude_ids ) ) {
                $placeholders = implode( ',', array_fill( 0, count( $exclude_ids ), '%d' ) );
                $sql_cats = $wpdb->prepare( "SELECT DISTINCT category FROM RORO_EVENTS_MASTER WHERE id IN ($placeholders)", $exclude_ids );
                $user_categories = $wpdb->get_col( $sql_cats );
            }
        }
        // Build a single query to fetch candidate events along with aggregated
        // popularity and history counts.  Exclude unpublished or invisible events.
        $fav_table   = $wpdb->prefix . 'RORO_MAP_FAVORITE';
        $click_table = $wpdb->prefix . 'RORO_RECOMMEND_EVENT_METRICS';
        $sql  = "SELECT e.id, e.name, e.event_date, e.category, e.prefecture,
                        COALESCE(fav_counts.fav_count,0) AS fav_count,
                        COALESCE(click_counts.click_count,0) AS click_count
                 FROM RORO_EVENTS_MASTER e
                 LEFT JOIN (
                   SELECT target_id, COUNT(*) AS fav_count
                   FROM {$fav_table}
                   WHERE target_type='event'
                   GROUP BY target_id
                 ) fav_counts ON fav_counts.target_id = e.id
                 LEFT JOIN (
                   SELECT event_id, COUNT(*) AS click_count
                   FROM {$click_table}
                   GROUP BY event_id
                 ) click_counts ON click_counts.event_id = e.id
                 WHERE e.isVisible = 1 AND e.status = 'published'";
        // Exclude the user’s already favourited events so new suggestions surface.
        if ( ! empty( $exclude_ids ) ) {
            $placeholders2 = implode( ',', array_fill( 0, count( $exclude_ids ), '%d' ) );
            $sql .= $wpdb->prepare( " AND e.id NOT IN ($placeholders2)", $exclude_ids );
        }
        // Limit results to a reasonable number (e.g. 1000) to avoid memory
        // exhaustion.  The list will be sorted and truncated after scoring.
        $sql .= " LIMIT 1000";
        $events = $wpdb->get_results( $sql, ARRAY_A );
        if ( ! is_array( $events ) || empty( $events ) ) {
            return Roro_REST_Utils::respond( [] );
        }
        // Compute max values for normalisation.  Avoid divide by zero by
        // defaulting to 1 when the dataset is empty.
        $maxFav   = 0;
        $maxClick = 0;
        foreach ( $events as $ev ) {
            $fc = intval( $ev['fav_count'] );
            $cc = intval( $ev['click_count'] );
            if ( $fc > $maxFav )   $maxFav   = $fc;
            if ( $cc > $maxClick ) $maxClick = $cc;
        }
        if ( $maxFav === 0 )   $maxFav   = 1;
        if ( $maxClick === 0 ) $maxClick = 1;
        // Score each event and build a recommendation list
        $recommendations = [];
        $now = time();
        foreach ( $events as $ev ) {
            $id       = intval( $ev['id'] );
            $name     = $ev['name'];
            $category = $ev['category'];
            // Event prefecture, used for proximity scoring
            $ev_prefecture = isset( $ev['prefecture'] ) ? trim( (string) $ev['prefecture'] ) : '';
            $favCount = intval( $ev['fav_count'] );
            $clicks   = intval( $ev['click_count'] );
            // Normalised popularity and history scores
            $popularity_score = $favCount / $maxFav;
            $history_score    = $clicks / $maxClick;
            // Recency: convert event_date string to timestamp.  More recent -> higher score.
            $date_str = $ev['event_date'];
            $recency_score = 0;
            if ( ! empty( $date_str ) ) {
                $ts = strtotime( $date_str );
                if ( $ts !== false ) {
                    $days_diff = max( 0, ( $now - $ts ) / 86400.0 );
                    // Use inverse days with decay; events within 30 days get score near 1
                    $recency_score = 1 / ( 1 + $days_diff / 30.0 );
                }
            }
            // Similarity: if user has favourite categories, match category
            $similarity_score = 0;
            if ( $user_id && ! empty( $user_categories ) && ! empty( $category ) ) {
                if ( in_array( $category, $user_categories, true ) ) {
                    $similarity_score = 1;
                }
            }
            // Proximity: compute if user's prefecture matches event prefecture.
            $proximity_score = 0;
            if ( $user_prefecture && $ev_prefecture ) {
                if ( strcasecmp( $user_prefecture, $ev_prefecture ) === 0 ) {
                    $proximity_score = 1;
                }
            }
            // Weighted sum.  Ensure each partial score is between 0 and 1.
            $score = 0;
            $score += $weights['similarity'] * $similarity_score;
            $score += $weights['history']    * $history_score;
            $score += $weights['recency']    * $recency_score;
            $score += $weights['popularity'] * $popularity_score;
            $score += $weights['proximity']  * $proximity_score;
            // Build reason based on highest contributing factors.  Collect messages
            // for factors above a threshold.
            $reasons = [];
            if ( $user_id ) {
                $reasons[] = __( 'まだお気に入りに追加していません', 'roro' );
            }
            // Determine if popularity is significant
            if ( $popularity_score >= 0.6 ) {
                $reasons[] = __( '人気度が高い', 'roro' );
            }
            if ( $history_score >= 0.5 ) {
                $reasons[] = __( '多くクリックされている', 'roro' );
            }
            if ( $recency_score >= 0.5 ) {
                $reasons[] = __( '新しいイベント', 'roro' );
            }
            // Proximity reason: highlight events held near the user
            if ( $proximity_score >= 0.5 ) {
                $reasons[] = __( '近くで開催されます', 'roro' );
            }
            // Fallback message if no factors met threshold
            if ( empty( $reasons ) ) {
                $reasons[] = __( '人気度と開催日からおすすめしています', 'roro' );
            }
            // Concatenate reasons
            $reason_text = implode( '、', array_unique( $reasons ) ) . __( 'ためおすすめしています', 'roro' );
            $recommendations[] = [
                'id'       => $id,
                'name'     => $name,
                'score'    => round( $score, 4 ),
                'reason'   => $reason_text,
            ];
        }
        // Sort by score descending, then by recency (implicit in score), and limit to top 5
        usort( $recommendations, function ( $a, $b ) {
            if ( $a['score'] == $b['score'] ) {
                // Stable sort by name if scores are equal
                return strcmp( $a['name'], $b['name'] );
            }
            return ( $a['score'] > $b['score'] ) ? -1 : 1;
        } );
        $recommendations = array_slice( $recommendations, 0, 5 );
        return Roro_REST_Utils::respond( $recommendations );
    }

    /**
     * Configurable version of the event recommendation endpoint.  This
     * method mirrors `recommend_events()` but allows callers to
     * override the weighting of each scoring factor via query
     * parameters.  The parameters `w_similarity`, `w_history`,
     * `w_recency`, `w_popularity` and `w_proximity` accept numeric
     * values in the range 0–1.  Missing or invalid values fall back
     * to the defaults defined by the class constants.  Before
     * scoring, the weights are normalised to ensure they sum to 1.0.
     *
     * Example usage:
     *   /roro/v1/recommend-events-configurable?w_recency=0.5&w_popularity=0.3&w_history=0.1&w_similarity=0.05&w_proximity=0.05
     *
     * @param WP_REST_Request $req
     * @return WP_REST_Response
     */
    public static function recommend_events_configurable( $req ) {
        global $wpdb;
        $user_id = get_current_user_id();
        // Resolve user prefecture for proximity scoring
        $user_prefecture = null;
        if ( $user_id ) {
            $link_table = $wpdb->prefix . 'RORO_USER_LINK_WP';
            $cust_id    = $wpdb->get_var( $wpdb->prepare( "SELECT customer_id FROM {$link_table} WHERE wp_user_id = %d", $user_id ) );
            if ( $cust_id ) {
                $cust_table = $wpdb->prefix . 'RORO_CUSTOMER';
                $pref       = $wpdb->get_var( $wpdb->prepare( "SELECT prefecture FROM {$cust_table} WHERE customer_id = %d", $cust_id ) );
                if ( $pref ) {
                    $user_prefecture = trim( (string) $pref );
                }
            }
        }
        // Parse weight parameters from query string.  If a value is
        // present and numeric (>=0), use it; otherwise fallback to
        // class constants.  After collecting, normalise the weights
        // so that they sum to 1.0.  If the total is zero (all
        // weights invalid or zero), revert to defaults.
        $weights = [
            'similarity' => self::W_SIMILARITY,
            'history'    => self::W_HISTORY,
            'recency'    => self::W_RECENCY,
            'popularity' => self::W_POPULARITY,
            'proximity'  => self::W_PROXIMITY,
        ];
        $params = [
            'similarity' => $req->get_param( 'w_similarity' ),
            'history'    => $req->get_param( 'w_history' ),
            'recency'    => $req->get_param( 'w_recency' ),
            'popularity' => $req->get_param( 'w_popularity' ),
            'proximity'  => $req->get_param( 'w_proximity' ),
        ];
        $sum = 0;
        foreach ( $params as $key => $val ) {
            if ( $val !== null && $val !== '' && is_numeric( $val ) ) {
                $f = floatval( $val );
                if ( $f >= 0 ) {
                    $weights[ $key ] = $f;
                }
            }
            $sum += $weights[ $key ];
        }
        if ( $sum > 0 ) {
            // Normalise
            foreach ( $weights as $k => $v ) {
                $weights[ $k ] = $v / $sum;
            }
        } else {
            // Reset to defaults if invalid
            $weights = [
                'similarity' => self::W_SIMILARITY,
                'history'    => self::W_HISTORY,
                'recency'    => self::W_RECENCY,
                'popularity' => self::W_POPULARITY,
                'proximity'  => self::W_PROXIMITY,
            ];
        }
        // When personalised, exclude events the user already favourited
        $exclude_ids = [];
        $user_categories = [];
        if ( $user_id ) {
            $fav_table = $wpdb->prefix . 'RORO_MAP_FAVORITE';
            $ids = $wpdb->get_col( $wpdb->prepare( "SELECT target_id FROM {$fav_table} WHERE target_type = 'event' AND user_id = %d", $user_id ) );
            $exclude_ids = array_filter( array_map( 'intval', $ids ), function ( $v ) { return $v > 0; } );
            if ( ! empty( $exclude_ids ) ) {
                $placeholders = implode( ',', array_fill( 0, count( $exclude_ids ), '%d' ) );
                $sql_cats = $wpdb->prepare( "SELECT DISTINCT category FROM RORO_EVENTS_MASTER WHERE id IN ($placeholders)", $exclude_ids );
                $user_categories = $wpdb->get_col( $sql_cats );
            }
        }
        // Fetch candidate events with aggregated favourite and click counts
        $fav_table   = $wpdb->prefix . 'RORO_MAP_FAVORITE';
        $click_table = $wpdb->prefix . 'RORO_RECOMMEND_EVENT_METRICS';
        $sql = "SELECT e.id, e.name, e.event_date, e.category, e.prefecture,
                        COALESCE(fav_counts.fav_count,0) AS fav_count,
                        COALESCE(click_counts.click_count,0) AS click_count
                 FROM RORO_EVENTS_MASTER e
                 LEFT JOIN (
                   SELECT target_id, COUNT(*) AS fav_count
                   FROM {$fav_table}
                   WHERE target_type='event'
                   GROUP BY target_id
                 ) fav_counts ON fav_counts.target_id = e.id
                 LEFT JOIN (
                   SELECT event_id, COUNT(*) AS click_count
                   FROM {$click_table}
                   GROUP BY event_id
                 ) click_counts ON click_counts.event_id = e.id
                 WHERE e.isVisible = 1 AND e.status = 'published'";
        if ( ! empty( $exclude_ids ) ) {
            $placeholders = implode( ',', array_fill( 0, count( $exclude_ids ), '%d' ) );
            $sql .= $wpdb->prepare( " AND e.id NOT IN ($placeholders)", $exclude_ids );
        }
        $sql .= " LIMIT 1000";
        $events = $wpdb->get_results( $sql, ARRAY_A );
        if ( empty( $events ) ) {
            return Roro_REST_Utils::respond( [] );
        }
        // Determine maximum favourite and click counts for normalisation
        $maxFav = 0;
        $maxClick = 0;
        foreach ( $events as $ev ) {
            $fc = intval( $ev['fav_count'] );
            $cc = intval( $ev['click_count'] );
            if ( $fc > $maxFav )   $maxFav   = $fc;
            if ( $cc > $maxClick ) $maxClick = $cc;
        }
        if ( $maxFav === 0 )   $maxFav   = 1;
        if ( $maxClick === 0 ) $maxClick = 1;
        $recommendations = [];
        $now = time();
        foreach ( $events as $ev ) {
            $id       = intval( $ev['id'] );
            $name     = $ev['name'];
            $category = $ev['category'];
            $ev_prefecture = isset( $ev['prefecture'] ) ? trim( (string) $ev['prefecture'] ) : '';
            $favCount = intval( $ev['fav_count'] );
            $clicks   = intval( $ev['click_count'] );
            $popularity_score = $favCount / $maxFav;
            $history_score    = $clicks / $maxClick;
            $recency_score    = 0;
            $date_str = $ev['event_date'];
            if ( ! empty( $date_str ) ) {
                $ts = strtotime( $date_str );
                if ( $ts !== false ) {
                    $days_diff = max( 0, ( $now - $ts ) / 86400.0 );
                    $recency_score = 1 / ( 1 + $days_diff / 30.0 );
                }
            }
            $similarity_score = 0;
            if ( $user_id && ! empty( $user_categories ) && ! empty( $category ) ) {
                if ( in_array( $category, $user_categories, true ) ) {
                    $similarity_score = 1;
                }
            }
            $proximity_score = 0;
            if ( $user_prefecture && $ev_prefecture ) {
                if ( strcasecmp( $user_prefecture, $ev_prefecture ) === 0 ) {
                    $proximity_score = 1;
                }
            }
            // Weighted sum
            $score = 0;
            $score += $weights['similarity'] * $similarity_score;
            $score += $weights['history']    * $history_score;
            $score += $weights['recency']    * $recency_score;
            $score += $weights['popularity'] * $popularity_score;
            $score += $weights['proximity']  * $proximity_score;
            // Build reasons similarly to the default algorithm.  Use
            // thresholds relative to weights: a factor is considered
            // significant if its weighted contribution exceeds 30% of
            // the maximum possible contribution (i.e. weight).  This
            // heuristic highlights factors that materially influence the
            // recommendation.  Include a fallback message when no
            // factor meets the threshold.
            $reasons = [];
            if ( $user_id ) {
                $reasons[] = __( 'まだお気に入りに追加していません', 'roro' );
            }
            // Determine contributions
            $thresholds = [
                'popularity' => 0.3 * $weights['popularity'],
                'history'    => 0.3 * $weights['history'],
                'recency'    => 0.3 * $weights['recency'],
                'similarity' => 0.3 * $weights['similarity'],
                'proximity'  => 0.3 * $weights['proximity'],
            ];
            if ( $popularity_score * $weights['popularity'] >= $thresholds['popularity'] ) {
                $reasons[] = __( '人気度が高い', 'roro' );
            }
            if ( $history_score * $weights['history'] >= $thresholds['history'] ) {
                $reasons[] = __( '多くクリックされている', 'roro' );
            }
            if ( $recency_score * $weights['recency'] >= $thresholds['recency'] ) {
                $reasons[] = __( '新しいイベント', 'roro' );
            }
            if ( $similarity_score * $weights['similarity'] >= $thresholds['similarity'] ) {
                $reasons[] = __( 'あなたの興味に関連しています', 'roro' );
            }
            if ( $proximity_score * $weights['proximity'] >= $thresholds['proximity'] ) {
                $reasons[] = __( '近くで開催されます', 'roro' );
            }
            if ( empty( $reasons ) ) {
                $reasons[] = __( '人気度と開催日からおすすめしています', 'roro' );
            }
            $reason_text = implode( '、', array_unique( $reasons ) ) . __( 'ためおすすめしています', 'roro' );
            $recommendations[] = [
                'id'     => $id,
                'name'   => $name,
                'score'  => round( $score, 4 ),
                'reason' => $reason_text,
            ];
        }
        // Sort and pick top 5
        usort( $recommendations, function ( $a, $b ) {
            if ( $a['score'] == $b['score'] ) {
                return strcmp( $a['name'], $b['name'] );
            }
            return ( $a['score'] > $b['score'] ) ? -1 : 1;
        } );
        $recommendations = array_slice( $recommendations, 0, 5 );
        return Roro_REST_Utils::respond( $recommendations );
    }

    /**
     * Recommend magazine pages based on aggregated views (top 5).
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