<?php
// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Admin page for viewing status change history.
 *
 * Displays rows from the RORO_STATUS_HISTORY table in a simple table.
 * Only users with the manage_options capability (administrators) can view
 * this page.  For future refinement, a dedicated manage_roro_history
 * capability could be introduced and assigned to roles that should see
 * this log.
 */
function roro_admin_history_page() {
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_die( __( 'You do not have sufficient permissions to access this page.', 'roro' ) );
    }
    global $wpdb;
    // Retrieve the most recent 200 status changes.  Join users to get display names where possible.
    $history_table = 'RORO_STATUS_HISTORY';
    $user_table    = $wpdb->users;
    $rows = $wpdb->get_results( "SELECT h.id, h.table_name, h.record_id, h.old_status, h.new_status, h.changed_at, h.changed_by, u.display_name
                                 FROM {$history_table} h
                                 LEFT JOIN {$user_table} u ON h.changed_by = u.ID
                                 ORDER BY h.id DESC
                                 LIMIT 200", ARRAY_A );
    ?>
    <div class="wrap">
        <h1><?php esc_html_e( 'ステータス履歴', 'roro' ); ?></h1>
        <p><?php esc_html_e( '最近のステータス変更の一覧です。', 'roro' ); ?></p>
        <table class="wp-list-table widefat fixed striped">
            <thead>
            <tr>
                <th><?php esc_html_e( 'ID', 'roro' ); ?></th>
                <th><?php esc_html_e( 'エンティティ', 'roro' ); ?></th>
                <th><?php esc_html_e( 'レコードID', 'roro' ); ?></th>
                <th><?php esc_html_e( '旧ステータス', 'roro' ); ?></th>
                <th><?php esc_html_e( '新ステータス', 'roro' ); ?></th>
                <th><?php esc_html_e( '変更日時', 'roro' ); ?></th>
                <th><?php esc_html_e( '変更者', 'roro' ); ?></th>
            </tr>
            </thead>
            <tbody>
            <?php if ( ! empty( $rows ) ) : ?>
                <?php foreach ( $rows as $row ) : ?>
                    <tr>
                        <td><?php echo esc_html( $row['id'] ); ?></td>
                        <td><?php echo esc_html( $row['table_name'] ); ?></td>
                        <td><?php echo esc_html( $row['record_id'] ); ?></td>
                        <td><?php echo esc_html( $row['old_status'] ); ?></td>
                        <td><?php echo esc_html( $row['new_status'] ); ?></td>
                        <td><?php echo esc_html( $row['changed_at'] ); ?></td>
                        <td><?php echo esc_html( $row['display_name'] ?: $row['changed_by'] ); ?></td>
                    </tr>
                <?php endforeach; ?>
            <?php else : ?>
                <tr>
                    <td colspan="7">
                        <?php esc_html_e( '履歴データがありません。', 'roro' ); ?>
                    </td>
                </tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
    <?php
}
