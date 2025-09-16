<?php
// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Render the Recommendation KPI Dashboard page.
 *
 * This page provides an at‑a‑glance overview of recommendation performance
 * over a selectable date range.  It pulls aggregated metrics via
 * `/roro/v1/recommend-events-stats` and visualises them with simple
 * canvas‑based charts (bar and line).  Administrators can choose
 * a start/end date to limit the period of analysis.  The dashboard
 * displays total clicks, the number of unique events, a ranking of
 * the top events by click count, and a trend line showing daily
 * clicks over the selected window.
 */
function roro_admin_recommend_dashboard_page() {
    if ( ! current_user_can( 'manage_options' ) ) {
        return;
    }
    // Determine date range defaults (last 30 days)
    $today        = current_time( 'Y-m-d' );
    $thirty_days  = date( 'Y-m-d', strtotime( '-29 days', strtotime( $today ) ) );
    $start_date   = isset( $_GET['start_date'] ) ? sanitize_text_field( $_GET['start_date'] ) : $thirty_days;
    $end_date     = isset( $_GET['end_date'] ) ? sanitize_text_field( $_GET['end_date'] ) : $today;
    ?>
    <div class="wrap">
        <h1><?php echo esc_html__( '推薦ダッシュボード', 'roro' ); ?></h1>
        <p><?php echo esc_html__( '期間を指定してクリック統計と傾向を確認できます。', 'roro' ); ?></p>
        <form method="get" id="roro-reco-dashboard-form" style="margin-bottom: 1em;">
            <input type="hidden" name="page" value="roro-admin-recommend-dashboard" />
            <label style="margin-right:1em;">
                <?php echo esc_html__( '開始日', 'roro' ); ?>：
                <input type="date" name="start_date" value="<?php echo esc_attr( $start_date ); ?>" />
            </label>
            <label style="margin-right:1em;">
                <?php echo esc_html__( '終了日', 'roro' ); ?>：
                <input type="date" name="end_date" value="<?php echo esc_attr( $end_date ); ?>" />
            </label>
            <button class="button button-primary" type="submit"><?php echo esc_html__( '更新', 'roro' ); ?></button>
        </form>
        <div id="roro-reco-summary" style="margin-bottom:1em;"></div>
        <div style="display:flex; flex-wrap:wrap; gap:2em;">
            <div>
                <canvas id="roro-reco-bar" width="500" height="300"></canvas>
            </div>
            <div>
                <canvas id="roro-reco-line" width="500" height="300"></canvas>
            </div>
        </div>
    </div>
    <script>
    (function(){
      const api = wp.apiFetch;
      // Retrieve query parameters from server-rendered input values
      const start_date = <?php echo wp_json_encode( $start_date ); ?>;
      const end_date = <?php echo wp_json_encode( $end_date ); ?>;
      // Prepare summary container and canvas contexts
      const summaryEl = document.getElementById('roro-reco-summary');
      const barCanvas = document.getElementById('roro-reco-bar');
      const barCtx = barCanvas.getContext('2d');
      const lineCanvas = document.getElementById('roro-reco-line');
      const lineCtx = lineCanvas.getContext('2d');

      // Simple bar chart renderer
      function drawBarChart(ctx, labels, values){
        const width = ctx.canvas.width;
        const height = ctx.canvas.height;
        ctx.clearRect(0, 0, width, height);
        const margin = 40;
        const chartWidth = width - margin * 2;
        const chartHeight = height - margin * 2;
        const maxVal = Math.max(...values, 1);
        const barWidth = chartWidth / values.length;
        // axes
        ctx.strokeStyle = '#333';
        ctx.lineWidth = 1;
        ctx.beginPath();
        ctx.moveTo(margin, margin);
        ctx.lineTo(margin, margin + chartHeight);
        ctx.lineTo(margin + chartWidth, margin + chartHeight);
        ctx.stroke();
        // bars
        ctx.fillStyle = '#4b9cd3';
        values.forEach((v, i) => {
          const barHeight = (v / maxVal) * (chartHeight - 10);
          const x = margin + i * barWidth + barWidth * 0.1;
          const y = margin + chartHeight - barHeight;
          const bw = barWidth * 0.8;
          ctx.fillRect(x, y, bw, barHeight);
          // labels
          ctx.fillStyle = '#333';
          ctx.font = '10px sans-serif';
          ctx.textAlign = 'center';
          ctx.fillText(labels[i], margin + i * barWidth + barWidth / 2, margin + chartHeight + 12);
          ctx.fillText(v.toString(), margin + i * barWidth + barWidth / 2, y - 4);
          ctx.fillStyle = '#4b9cd3';
        });
      }
      // Simple line chart renderer
      function drawLineChart(ctx, labels, values){
        const width = ctx.canvas.width;
        const height = ctx.canvas.height;
        ctx.clearRect(0, 0, width, height);
        const margin = 40;
        const chartWidth = width - margin * 2;
        const chartHeight = height - margin * 2;
        const maxVal = Math.max(...values, 1);
        // axes
        ctx.strokeStyle = '#333';
        ctx.lineWidth = 1;
        ctx.beginPath();
        ctx.moveTo(margin, margin);
        ctx.lineTo(margin, margin + chartHeight);
        ctx.lineTo(margin + chartWidth, margin + chartHeight);
        ctx.stroke();
        // line
        ctx.strokeStyle = '#e27d60';
        ctx.lineWidth = 2;
        ctx.beginPath();
        values.forEach((v, i) => {
          const x = margin + (chartWidth / (values.length - 1)) * i;
          const y = margin + chartHeight - (v / maxVal) * (chartHeight - 10);
          if (i === 0) ctx.moveTo(x, y);
          else ctx.lineTo(x, y);
        });
        ctx.stroke();
        // points
        ctx.fillStyle = '#e27d60';
        values.forEach((v, i) => {
          const x = margin + (chartWidth / (values.length - 1)) * i;
          const y = margin + chartHeight - (v / maxVal) * (chartHeight - 10);
          ctx.beginPath();
          ctx.arc(x, y, 3, 0, Math.PI * 2);
          ctx.fill();
        });
        // labels on x-axis
        ctx.fillStyle = '#333';
        ctx.font = '9px sans-serif';
        ctx.textAlign = 'center';
        labels.forEach((lbl, i) => {
          const x = margin + (chartWidth / (labels.length - 1)) * i;
          ctx.fillText(lbl, x, margin + chartHeight + 12);
        });
        // y-axis tick values
        const tickCount = 5;
        ctx.textAlign = 'right';
        for (let i=0; i<=tickCount; i++){
          const val = Math.round(maxVal * i / tickCount);
          const y = margin + chartHeight - (val / maxVal) * (chartHeight - 10);
          ctx.fillText(val.toString(), margin - 5, y + 3);
          ctx.beginPath();
          ctx.moveTo(margin - 3, y);
          ctx.lineTo(margin, y);
          ctx.stroke();
        }
      }
      // Fetch and render stats
      function loadStats(){
        const params = new URLSearchParams();
        if (start_date) params.append('start_date', start_date);
        if (end_date) params.append('end_date', end_date);
        params.append('limit', '5');
        api({ path: '/roro/v1/recommend-events-stats?' + params.toString() }).then(res => {
          if(!res || !res.ok){ return; }
          // Summary
          summaryEl.innerHTML = '';
          const sumHtml = '<div style="display:flex; gap:2em; flex-wrap:wrap;">'
            + '<div><strong><?php echo esc_html__( '合計クリック数', 'roro' ); ?>:</strong> ' + res.total_clicks + '</div>'
            + '<div><strong><?php echo esc_html__( 'イベント数', 'roro' ); ?>:</strong> ' + res.unique_events + '</div>'
            + '</div>';
          summaryEl.innerHTML = sumHtml;
          // Bar chart for top events
          const barLabels = res.top_events.map(e => e.event_name);
          const barValues = res.top_events.map(e => e.clicks);
          drawBarChart(barCtx, barLabels, barValues);
          // Line chart for daily clicks
          const lineLabels = res.daily_clicks.map(d => d.date.substr(5));
          const lineValues = res.daily_clicks.map(d => d.clicks);
          drawLineChart(lineCtx, lineLabels, lineValues);
        }).catch(() => {
          summaryEl.innerHTML = '<p><?php echo esc_html__( 'データ取得に失敗しました。', 'roro' ); ?></p>';
        });
      }
      loadStats();
    })();
    </script>
    <?php
}