<?php
// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Render the Recommendation Analytics admin page.
 *
 * This page provides simple analytics around the recommendation system.  It
 * aggregates favourite counts for each event to highlight which events are
 * most popular amongst users.  Administrators can use this data to gauge
 * the effectiveness of the recommendation engine and to understand user
 * preferences at a glance.
 */
function roro_admin_recommend_page() {
    if ( ! current_user_can( 'manage_options' ) ) {
        return;
    }
    global $wpdb;
    // Aggregate favourite counts and recommendation click counts for each event.  We
    // join against the RORO_RECOMMEND_EVENT_METRICS table to count how many
    // times an event was clicked from a recommendation.  Events without any
    // interactions are still included so administrators can see zero values.
    $table_fav  = $wpdb->prefix . 'RORO_MAP_FAVORITE';
    $table_click = $wpdb->prefix . 'RORO_RECOMMEND_EVENT_METRICS';
    // Compose a single query to avoid multiple round trips.  Use COALESCE to
    // treat NULL counts as zero.
    $sql  = "SELECT e.id, e.name,
                    COALESCE(fav_counts.fav_count, 0) AS fav_count,
                    COALESCE(click_counts.click_count, 0) AS click_count
             FROM RORO_EVENTS_MASTER e
             LEFT JOIN (
               SELECT target_id, COUNT(*) AS fav_count
               FROM {$table_fav}
               WHERE target_type='event'
               GROUP BY target_id
             ) fav_counts ON fav_counts.target_id = e.id
             LEFT JOIN (
               SELECT event_id, COUNT(*) AS click_count
               FROM {$table_click}
               GROUP BY event_id
             ) click_counts ON click_counts.event_id = e.id
             ORDER BY click_count DESC, fav_count DESC, e.name ASC";
    $rows = $wpdb->get_results( $sql, ARRAY_A );
    ?>
    <div class="wrap">
        <h1><?php echo esc_html__( '推薦分析', 'roro' ); ?></h1>
        <p><?php echo esc_html__( 'イベントのクリック率とお気に入り率を確認できます。', 'roro' ); ?></p>
        <?php if ( empty( $rows ) ) : ?>
            <p><?php echo esc_html__( '現在表示するデータがありません。', 'roro' ); ?></p>
        <?php else : ?>
        <table class="widefat fixed striped">
            <thead>
                <tr>
                    <th><?php echo esc_html__( 'イベントID', 'roro' ); ?></th>
                    <th><?php echo esc_html__( 'イベント名', 'roro' ); ?></th>
                    <th><?php echo esc_html__( 'クリック数', 'roro' ); ?></th>
                    <th><?php echo esc_html__( 'お気に入り数', 'roro' ); ?></th>
                    <th><?php echo esc_html__( 'お気に入り率', 'roro' ); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ( $rows as $row ) :
                    $fav_count   = intval( $row['fav_count'] );
                    $click_count = intval( $row['click_count'] );
                    $rate = $click_count > 0 ? round( ( $fav_count / $click_count ) * 100, 1 ) : 0;
                    ?>
                    <tr>
                        <td><?php echo esc_html( $row['id'] ); ?></td>
                        <td><?php echo esc_html( $row['name'] ); ?></td>
                        <td><?php echo esc_html( $click_count ); ?></td>
                        <td><?php echo esc_html( $fav_count ); ?></td>
                        <td><?php echo esc_html( $rate ); ?>%</td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>
    </div>
    <?php
}