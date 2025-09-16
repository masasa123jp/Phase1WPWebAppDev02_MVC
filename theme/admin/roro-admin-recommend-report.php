<?php
// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Render the Recommend Click Report admin page.
 *
 * This page allows administrators to view aggregated click counts
 * for recommended events over a chosen date range.  The data is
 * obtained from the REST endpoint `/roro/v1/recommend-events-report`.
 * Administrators can specify a start and end date (YYYY‑MM‑DD).  If
 * omitted, the last 30 days are used by default.  Results are
 * displayed in a sortable table.
 */
function roro_admin_recommend_report_page() {
    if ( ! current_user_can( 'manage_options' ) ) {
        return;
    }
    // Determine date range from GET parameters or default to last 30 days.
    $start_date = isset( $_GET['start_date'] ) ? sanitize_text_field( $_GET['start_date'] ) : '';
    $end_date   = isset( $_GET['end_date'] )   ? sanitize_text_field( $_GET['end_date'] )   : '';
    if ( empty( $start_date ) || ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $start_date ) ) {
        // Default to 30 days ago
        $start_date = date( 'Y-m-d', strtotime( '-30 days' ) );
    }
    if ( empty( $end_date ) || ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $end_date ) ) {
        // Default to today
        $end_date = date( 'Y-m-d' );
    }
    // Build a REST request internally to avoid HTTP overhead.  Use rest_do_request.
    $request = new WP_REST_Request( 'GET', '/roro/v1/recommend-events-report' );
    $request->set_param( 'start_date', $start_date );
    $request->set_param( 'end_date', $end_date );
    // Ensure current user has proper capabilities; the endpoint will check again.
    $response = rest_do_request( $request );
    $data = [];
    if ( ! $response->is_error() ) {
        $result = $response->get_data();
        if ( is_array( $result ) ) {
            $data = $result;
        }
    }
    ?>
    <div class="wrap">
        <h1><?php echo esc_html__( 'Recommend Report', 'roro' ); ?></h1>
        <form method="get" action="">
            <input type="hidden" name="page" value="roro-admin-recommend-report" />
            <table class="form-table">
                <tr>
                    <th scope="row"><label for="start_date"><?php echo esc_html__( 'Start Date', 'roro' ); ?></label></th>
                    <td><input type="date" id="start_date" name="start_date" value="<?php echo esc_attr( $start_date ); ?>" /></td>
                </tr>
                <tr>
                    <th scope="row"><label for="end_date"><?php echo esc_html__( 'End Date', 'roro' ); ?></label></th>
                    <td><input type="date" id="end_date" name="end_date" value="<?php echo esc_attr( $end_date ); ?>" /></td>
                </tr>
            </table>
            <?php submit_button( __( 'Filter', 'roro' ) ); ?>
        </form>
        <?php if ( empty( $data ) ) : ?>
            <p><?php echo esc_html__( 'No data found for the selected period.', 'roro' ); ?></p>
        <?php else : ?>
        <table class="widefat fixed striped">
            <thead>
                <tr>
                    <th><?php echo esc_html__( 'イベントID', 'roro' ); ?></th>
                    <th><?php echo esc_html__( 'イベント名', 'roro' ); ?></th>
                    <th><?php echo esc_html__( 'クリック数', 'roro' ); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ( $data as $row ) : ?>
                <tr>
                    <td><?php echo esc_html( $row['id'] ); ?></td>
                    <td><?php echo esc_html( $row['name'] ); ?></td>
                    <td><?php echo esc_html( $row['clicks'] ); ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>
    </div>
    <?php
}