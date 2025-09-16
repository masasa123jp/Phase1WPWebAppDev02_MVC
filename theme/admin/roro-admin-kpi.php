<?php
// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * RORO Recommendation KPI Dashboard
 *
 * This admin page aggregates key performance indicators (KPIs) for the
 * recommendation engine.  Administrators can select a time window
 * (today, last 7 days, last 30 days) and view click counts, favourite
 * counts, favourite rate and a simple revisit rate per event.  The
 * revisit rate is approximated by counting how many unique session
 * identifiers have more than one click event for the same event within
 * the selected window.  This dashboard helps evaluate recommendation
 * effectiveness without exposing individual user data.
 */
function roro_admin_kpi_page() {
    if ( ! current_user_can( 'manage_roro' ) ) {
        return;
    }
    global $wpdb;
    // Determine the selected period; default to 'today'
    $allowed = array( 'today', '7days', '30days' );
    $period  = isset( $_GET['period'] ) ? sanitize_text_field( $_GET['period'] ) : 'today';
    if ( ! in_array( $period, $allowed, true ) ) {
        $period = 'today';
    }
    // Calculate date range condition
    $where_clicks = '';
    $today        = current_time( 'Y-m-d' );
    if ( 'today' === $period ) {
        $where_clicks = $wpdb->prepare( ' WHERE DATE(created_at) = %s', $today );
    } elseif ( '7days' === $period ) {
        $where_clicks = $wpdb->prepare( ' WHERE created_at >= DATE_SUB(%s, INTERVAL 7 DAY)', $today );
    } elseif ( '30days' === $period ) {
        $where_clicks = $wpdb->prepare( ' WHERE created_at >= DATE_SUB(%s, INTERVAL 30 DAY)', $today );
    }
    // Table names
    $table_clicks = $wpdb->prefix . 'RORO_RECOMMEND_EVENT_METRICS';
    $table_fav    = $wpdb->prefix . 'RORO_MAP_FAVORITE';
    // Click counts per event
    $click_sql = "SELECT event_id, COUNT(*) AS click_count, COUNT(DISTINCT session_id) AS sessions
                  FROM {$table_clicks}
                  {$where_clicks}
                  GROUP BY event_id";
    $click_counts = $wpdb->get_results( $click_sql, OBJECT_K );
    // Count sessions with multiple clicks per event to approximate revisit
    $revisit_counts = [];
    foreach ( $click_counts as $ev_id => $row ) {
        $event_id   = intval( $ev_id );
        $sql        = $wpdb->prepare( "SELECT session_id, COUNT(*) AS c FROM {$table_clicks} {$where_clicks} AND event_id = %d GROUP BY session_id", $event_id );
        $rows       = $wpdb->get_results( $sql, ARRAY_A );
        $total      = 0;
        $repeat     = 0;
        foreach ( $rows as $r ) {
            $total++;
            if ( intval( $r['c'] ) > 1 ) {
                $repeat++;
            }
        }
        $revisit_counts[ $event_id ] = [
            'total_sessions'  => $total,
            'repeated'        => $repeat,
            'revisit_rate'    => $total > 0 ? round( ( $repeat / $total ) * 100, 1 ) : 0,
        ];
    }
    // Favourite counts per event (not time filtered; favourites accumulate)
    $fav_sql  = "SELECT target_id, COUNT(*) AS fav_count
                 FROM {$table_fav}
                 WHERE target_type = 'event'
                 GROUP BY target_id";
    $fav_counts = $wpdb->get_results( $fav_sql, OBJECT_K );
    // Fetch event names
    $events = $wpdb->get_results( 'SELECT id, name FROM RORO_EVENTS_MASTER', ARRAY_A );
    // Display page
    ?>
    <div class="wrap">
        <h1><?php echo esc_html__( '推薦KPIダッシュボード', 'roro' ); ?></h1>
        <p><?php echo esc_html__( '期間別にクリック数、ファボ数、ファボ率、再訪率を表示します。', 'roro' ); ?></p>
        <ul class="subsubsub">
            <li><a href="<?php echo esc_url( add_query_arg( 'period', 'today' ) ); ?>" class="<?php echo ( 'today' === $period ? 'current' : '' ); ?>"><?php echo esc_html__( '今日', 'roro' ); ?></a> | </li>
            <li><a href="<?php echo esc_url( add_query_arg( 'period', '7days' ) ); ?>" class="<?php echo ( '7days' === $period ? 'current' : '' ); ?>"><?php echo esc_html__( '7日間', 'roro' ); ?></a> | </li>
            <li><a href="<?php echo esc_url( add_query_arg( 'period', '30days' ) ); ?>" class="<?php echo ( '30days' === $period ? 'current' : '' ); ?>"><?php echo esc_html__( '30日間', 'roro' ); ?></a></li>
        </ul>
        <table class="widefat fixed striped">
            <thead>
                <tr>
                    <th><?php echo esc_html__( 'イベントID', 'roro' ); ?></th>
                    <th><?php echo esc_html__( 'イベント名', 'roro' ); ?></th>
                    <th><?php echo esc_html__( 'セッション数', 'roro' ); ?></th>
                    <th><?php echo esc_html__( 'クリック数', 'roro' ); ?></th>
                    <th><?php echo esc_html__( 'クリック率', 'roro' ); ?></th>
                    <th><?php echo esc_html__( 'お気に入り数', 'roro' ); ?></th>
                    <th><?php echo esc_html__( 'お気に入り率', 'roro' ); ?></th>
                    <th><?php echo esc_html__( '再訪率', 'roro' ); ?></th>
                </tr>
            </thead>
            <tbody>
            <?php
            foreach ( $events as $ev ) {
                $id     = intval( $ev['id'] );
                $name   = $ev['name'];
                $clicks = isset( $click_counts[ $id ] ) ? intval( $click_counts[ $id ]->click_count ) : 0;
                $sessions = isset( $click_counts[ $id ] ) ? intval( $click_counts[ $id ]->sessions ) : 0;
                $favs   = isset( $fav_counts[ $id ] ) ? intval( $fav_counts[ $id ]->fav_count ) : 0;
                $fav_rate = $clicks > 0 ? round( ( $favs / $clicks ) * 100, 1 ) : 0;
                $rev  = isset( $revisit_counts[ $id ] ) ? $revisit_counts[ $id ]['revisit_rate'] : 0;
                ?>
                <tr>
                    <td><?php echo esc_html( $id ); ?></td>
                    <td><?php echo esc_html( $name ); ?></td>
                    <td><?php echo esc_html( $sessions ); ?></td>
                    <td><?php echo esc_html( $clicks ); ?></td>
                    <td><?php echo $sessions > 0 ? esc_html( round( ( $clicks / $sessions ) * 100, 1 ) ) : '0'; ?>%</td>
                    <td><?php echo esc_html( $favs ); ?></td>
                    <td><?php echo esc_html( $fav_rate ); ?>%</td>
                    <td><?php echo esc_html( $rev ); ?>%</td>
                </tr>
                <?php
            }
            ?>
            </tbody>
        </table>
    </div>
    <?php
}