<?php
// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Render the Analytics admin page.
 * This page allows administrators to view aggregated magazine analytics
 * (views and clicks) with optional date and issue filters, and to
 * download the results as a CSV.  Data is fetched via the REST API
 * registered in Roro_REST_Analytics.
 */
function roro_admin_analytics_page() {
    ?>
    <div class="wrap">
        <h1>アナリティクス</h1>
        <form id="roro-analytics-form" style="margin-bottom:1em;">
            <label style="margin-right:1em;">開始日：<input type="date" name="start_date" /></label>
            <label style="margin-right:1em;">終了日：<input type="date" name="end_date" /></label>
            <label style="margin-right:1em;">号：
                <select name="issue_id">
                    <option value="">すべて</option>
                </select>
            </label>
            <button class="button button-primary" type="submit">検索</button>
            <button class="button" type="button" id="roro-analytics-csv">CSVダウンロード</button>
        </form>
        <table class="widefat" id="roro-analytics-table">
            <thead>
                <tr><th>号ID</th><th>ページID</th><th>日付</th><th>閲覧数</th><th>クリック数</th></tr>
            </thead>
            <tbody></tbody>
        </table>
    </div>
    <script>
    (function(){
      const api = wp.apiFetch;
      let lastData = [];
      // Populate issue dropdown
      function loadIssues(){
        api({ path: '/roro/v1/magazine/issues' }).then(issues => {
          const sel = document.querySelector('#roro-analytics-form select[name="issue_id"]');
          issues.forEach(i => {
            const opt = document.createElement('option');
            opt.value = i.id;
            opt.textContent = i.title || i.issue_code || i.id;
            sel.appendChild(opt);
          });
        }).catch(() => {});
      }
      // Render table data
      function render(rows){
        const tbody = document.querySelector('#roro-analytics-table tbody');
        tbody.innerHTML = '';
        rows.forEach(r => {
          const tr = document.createElement('tr');
          tr.innerHTML = `<td>${r.issue_id || ''}</td><td>${r.page_id || ''}</td><td>${r.date || ''}</td><td>${r.views || 0}</td><td>${r.clicks || 0}</td>`;
          tbody.appendChild(tr);
        });
      }
      // Fetch analytics data based on form
      function fetchData(){
        const form = document.getElementById('roro-analytics-form');
        const formData = new FormData(form);
        const params = {};
        for (const [k, v] of formData.entries()){
          if (v) params[k] = v;
        }
        const query = new URLSearchParams(params).toString();
        api({ path: '/roro/v1/analytics' + (query ? '?' + query : '') }).then(rows => {
          lastData = rows || [];
          render(lastData);
        });
      }
      // CSV download
      function downloadCSV(){
        if(!lastData || !lastData.length) return;
        let csv = 'issue_id,page_id,date,views,clicks\n';
        lastData.forEach(r => {
          csv += `${r.issue_id||''},${r.page_id||''},${r.date||''},${r.views||0},${r.clicks||0}\n`;
        });
        const blob = new Blob([csv], { type: 'text/csv' });
        const url = URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url;
        const now = new Date().toISOString().slice(0,10);
        a.download = `analytics-${now}.csv`;
        a.style.display = 'none';
        document.body.appendChild(a);
        a.click();
        document.body.removeChild(a);
        URL.revokeObjectURL(url);
      }
      // Event handlers
      document.getElementById('roro-analytics-form').addEventListener('submit', function(e){
        e.preventDefault();
        fetchData();
      });
      document.getElementById('roro-analytics-csv').addEventListener('click', function(){
        downloadCSV();
      });
      // Init
      loadIssues();
      fetchData();
    })();
    </script>
    <?php
}