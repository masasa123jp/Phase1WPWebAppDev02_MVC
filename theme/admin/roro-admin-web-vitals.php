<?php
// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Admin page for viewing aggregated Core Web Vitals metrics.
 *
 * This page queries the RORO_WEB_VITALS table and computes average
 * Largest Contentful Paint (LCP), Cumulative Layout Shift (CLS) and
 * Interaction to Next Paint (INP) values over several time windows.
 * Administrators can use this information to monitor performance
 * regressions and evaluate optimisation efforts.  Only users with
 * manage_options capability can access this page.
 */
function roro_admin_web_vitals_page() {
    if ( ! current_user_can( 'manage_options' ) ) {
        return;
    }
    global $wpdb;
    $table_name = $wpdb->prefix . 'RORO_WEB_VITALS';
    // Helper to compute average, median (P50) and 90th percentile (P90) metrics
    // for a given interval (e.g. '1 DAY', '7 DAY').  P50 and P90 are
    // calculated in PHP to avoid MySQL version differences.
    $compute_stats = function( $interval ) use ( $wpdb, $table_name ) {
        // Initialise output structure for each metric.
        $out = [
            'LCP' => [ 'avg' => null, 'p50' => null, 'p90' => null ],
            'CLS' => [ 'avg' => null, 'p50' => null, 'p90' => null ],
            'INP' => [ 'avg' => null, 'p50' => null, 'p90' => null ],
        ];
        // Fetch all values for the interval and order them by metric/value to make percentile calc easier.
        $results = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT metric, value FROM {$table_name} WHERE created_at >= DATE_SUB(NOW(), INTERVAL {$interval}) AND metric IN ('LCP','CLS','INP') ORDER BY metric, value",
                []
            ),
            ARRAY_A
        );
        // Group values by metric.
        $values_by_metric = [ 'LCP' => [], 'CLS' => [], 'INP' => [] ];
        if ( is_array( $results ) ) {
            foreach ( $results as $row ) {
                $metric = $row['metric'];
                $value = floatval( $row['value'] );
                if ( isset( $values_by_metric[ $metric ] ) ) {
                    $values_by_metric[ $metric ][] = $value;
                }
            }
        }
        // Helper to compute percentile from sorted array.
        $percentile = function( $arr, $p ) {
            if ( empty( $arr ) ) {
                return null;
            }
            $count = count( $arr );
            $index = ( $count - 1 ) * $p;
            $lower = floor( $index );
            $upper = ceil( $index );
            if ( $lower === $upper ) {
                return $arr[ (int) $index ];
            }
            $weight = $index - $lower;
            return $arr[ $lower ] + ( $arr[ $upper ] - $arr[ $lower ] ) * $weight;
        };
        // Compute stats for each metric.
        foreach ( $values_by_metric as $metric_name => $arr ) {
            if ( ! empty( $arr ) ) {
                $count = count( $arr );
                $sum = array_sum( $arr );
                // Sorted for percentile.
                sort( $arr );
                $avg = $sum / $count;
                $p50 = $percentile( $arr, 0.5 );
                $p90 = $percentile( $arr, 0.9 );
                $out[ $metric_name ] = [
                    'avg' => $avg,
                    'p50' => $p50,
                    'p90' => $p90,
                ];
            }
        }
        return $out;
    };
    // Compute statistics for 1, 7 and 30 day periods.
    $today  = $compute_stats( '1 DAY' );
    $week   = $compute_stats( '7 DAY' );
    $month  = $compute_stats( '30 DAY' );
    ?>
    <div class="wrap">
        <h1><?php echo esc_html__( 'Core Web Vitals', 'roro' ); ?></h1>
        <p><?php echo esc_html__( '最近のページ表示から収集された LCP / CLS / INP の平均値・中央値・P90 を表示します。', 'roro' ); ?></p>
        <table class="widefat fixed striped">
            <thead>
                <tr>
                    <th><?php echo esc_html__( '期間', 'roro' ); ?></th>
                    <th><?php echo esc_html__( 'LCP平均 (ms)', 'roro' ); ?></th>
                    <th><?php echo esc_html__( 'LCP中央値 (ms)', 'roro' ); ?></th>
                    <th><?php echo esc_html__( 'LCP P90 (ms)', 'roro' ); ?></th>
                    <th><?php echo esc_html__( 'CLS平均', 'roro' ); ?></th>
                    <th><?php echo esc_html__( 'CLS中央値', 'roro' ); ?></th>
                    <th><?php echo esc_html__( 'CLS P90', 'roro' ); ?></th>
                    <th><?php echo esc_html__( 'INP平均 (ms)', 'roro' ); ?></th>
                    <th><?php echo esc_html__( 'INP中央値 (ms)', 'roro' ); ?></th>
                    <th><?php echo esc_html__( 'INP P90 (ms)', 'roro' ); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php
                $rows = [
                    __( '当日', 'roro' ) => $today,
                    __( '7日間', 'roro' ) => $week,
                    __( '30日間', 'roro' ) => $month,
                ];
                foreach ( $rows as $label => $metrics ) :
                    ?>
                    <tr>
                        <td><?php echo esc_html( $label ); ?></td>
                        <td><?php echo $metrics['LCP']['avg'] !== null ? number_format( $metrics['LCP']['avg'], 0 ) : '–'; ?></td>
                        <td><?php echo $metrics['LCP']['p50'] !== null ? number_format( $metrics['LCP']['p50'], 0 ) : '–'; ?></td>
                        <td><?php echo $metrics['LCP']['p90'] !== null ? number_format( $metrics['LCP']['p90'], 0 ) : '–'; ?></td>
                        <td><?php echo $metrics['CLS']['avg'] !== null ? number_format( $metrics['CLS']['avg'], 3 ) : '–'; ?></td>
                        <td><?php echo $metrics['CLS']['p50'] !== null ? number_format( $metrics['CLS']['p50'], 3 ) : '–'; ?></td>
                        <td><?php echo $metrics['CLS']['p90'] !== null ? number_format( $metrics['CLS']['p90'], 3 ) : '–'; ?></td>
                        <td><?php echo $metrics['INP']['avg'] !== null ? number_format( $metrics['INP']['avg'], 0 ) : '–'; ?></td>
                        <td><?php echo $metrics['INP']['p50'] !== null ? number_format( $metrics['INP']['p50'], 0 ) : '–'; ?></td>
                        <td><?php echo $metrics['INP']['p90'] !== null ? number_format( $metrics['INP']['p90'], 0 ) : '–'; ?></td>
                    </tr>
                    <?php
                endforeach;
                ?>
            </tbody>
        </table>
    </div>
    <?php
}