<?php
// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Render the Events admin page.
 * Provides a simple form and table to manage events via the REST API.
 */
function roro_admin_events_page() {
    ?>
    <div class="wrap">
        <h1>イベント管理</h1>
        <form id="roro-event-form">
            <!-- 編集時は editingId にセットされる -->
            <input type="hidden" name="editing_id" />
            <input type="text" name="name" placeholder="イベント名" required />
            <input type="date" name="date" required />
            <input type="text" name="place" placeholder="場所" />
            <input type="number" step="any" name="lat" placeholder="緯度" />
            <input type="number" step="any" name="lng" placeholder="経度" />
            <select name="status">
                <option value="draft">下書き</option>
                <option value="published">公開</option>
            </select>
            <button type="submit" class="button button-primary">追加</button>
        </form>
        <hr />
        <!-- ステータス絞り込みフィルター -->
        <div style="margin:1rem 0;">
            <label for="event-status-filter">表示ステータス:</label>
            <select id="event-status-filter">
                <option value="all">すべて</option>
                <option value="published">公開のみ</option>
                <option value="draft">下書きのみ</option>
            </select>
            <!-- CSVエクスポートボタン -->
            <button type="button" id="event-export-csv" class="button" style="margin-left:1rem;">CSVダウンロード</button>
            <!-- CSVインポート用: ファイル選択とボタン -->
            <input type="file" id="event-import-input" accept=".csv" style="margin-left:1rem;" />
            <button type="button" id="event-import-btn" class="button">CSVインポート</button>
        </div>
        <table class="widefat" id="roro-event-list"><thead><tr><th>ID</th><th>名称</th><th>日付</th><th>表示</th><th>ステータス</th><th>操作</th></tr></thead><tbody></tbody></table>
    </div>
    <script>
    (function(){
      const api = wp.apiFetch;
      let events = [];
      let editingId = null;
      const form = document.getElementById('roro-event-form');
      const submitBtn = form.querySelector('button[type="submit"]');
      const listTable = document.getElementById('roro-event-list');
      function list() {
        // Build query params based on filter selection
        const statusVal = document.getElementById('event-status-filter').value;
        let qs = '?all=1';
        if (statusVal !== 'all') {
          qs += '&status=' + encodeURIComponent(statusVal);
        }
        api({ path: '/roro/v1/events' + qs }).then(rows => {
          events = rows;
          const tbody = listTable.querySelector('tbody');
          tbody.innerHTML = '';
          rows.forEach(r => {
            const tr = document.createElement('tr');
            tr.innerHTML = `<td>${r.id||''}</td><td>${r.name||''}</td><td>${r.date||''}</td><td>${r.isVisible?'✔':''}</td><td>${r.status||''}</td>` +
              `<td><button data-id="${r.id}" class="button edit">編集</button> <button data-id="${r.id}" class="button del">削除</button></td>`;
            tbody.appendChild(tr);
          });
        });
      }
      list();
      // When filter changes, reload list
      document.getElementById('event-status-filter').addEventListener('change', function(){
        list();
      });
      form.addEventListener('submit', function(e){
        e.preventDefault();
        const fd = new FormData(form);
        const data = Object.fromEntries(fd);
        // Remove editing_id from data
        delete data.editing_id;
        // 入力バリデーション: 名称と日付必須、緯度・経度は数値
        const nameVal = form.querySelector('input[name="name"]').value.trim();
        const dateVal = form.querySelector('input[name="date"]').value;
        const latVal = form.querySelector('input[name="lat"]').value;
        const lngVal = form.querySelector('input[name="lng"]').value;
        if (!nameVal) { alert('イベント名を入力してください'); return; }
        if (!dateVal) { alert('日付を入力してください'); return; }
        if (latVal && isNaN(parseFloat(latVal))) { alert('緯度は数値で入力してください'); return; }
        if (lngVal && isNaN(parseFloat(lngVal))) { alert('経度は数値で入力してください'); return; }

        if (editingId) {
          api({ path: '/roro/v1/events/' + editingId, method: 'PUT', data: data }).then(() => {
            editingId = null;
            form.reset();
            submitBtn.textContent = '追加';
            list();
          });
        } else {
          api({ path: '/roro/v1/events', method: 'POST', data: data }).then(() => {
            form.reset();
            list();
          });
        }
      });
      listTable.addEventListener('click', function(e){
        if (e.target.classList.contains('del')) {
          const id = e.target.getAttribute('data-id');
          if (confirm('削除しますか？')) {
            api({ path: '/roro/v1/events/' + id, method: 'DELETE' }).then(() => list());
          }
        } else if (e.target.classList.contains('edit')) {
          const id = e.target.getAttribute('data-id');
          // Find event row from cached data
          const row = events.find(ev => String(ev.id) === String(id));
          if (row) {
            editingId = id;
            submitBtn.textContent = '更新';
            // Populate form fields
            form.querySelector('input[name="name"]').value = row.name || '';
            // The API returns date in YYYY-MM-DD; assign directly
            form.querySelector('input[name="date"]').value = row.date || '';
            form.querySelector('input[name="place"]').value = row.place || '';
            form.querySelector('input[name="lat"]').value = row.lat || row.latitude || '';
            form.querySelector('input[name="lng"]').value = row.lng || row.longitude || '';
            form.querySelector('select[name="status"]').value = row.status || 'draft';
          }
        }
      });

      // CSVエクスポート機能
      document.getElementById('event-export-csv')?.addEventListener('click', function(){
        exportCSV();
      });
      function exportCSV() {
        api({ path: '/roro/v1/events?all=1' }).then(rows => {
          const header = ['id','name','date','place','lat','lng','status'];
          let csv = header.join(',') + '\n';
          rows.forEach(r => {
            const row = [r.id||'', r.name||'', r.date||'', r.place||'', (r.lat||r.latitude||''), (r.lng||r.longitude||''), r.status||''];
            csv += row.map(v => '"' + String(v).replace(/"/g,'""') + '"').join(',') + '\n';
          });
          const blob = new Blob([csv], {type:'text/csv;charset=utf-8;'});
          const url = URL.createObjectURL(blob);
          const a = document.createElement('a');
          a.href = url;
          a.download = 'events.csv';
          a.click();
          URL.revokeObjectURL(url);
        });
      }

      // CSVインポート機能
      document.getElementById('event-import-btn')?.addEventListener('click', function() {
        const input = document.getElementById('event-import-input');
        if (!input || !input.files || input.files.length === 0) {
          alert('インポートするCSVファイルを選択してください');
          return;
        }
        const file = input.files[0];
        const reader = new FileReader();
        reader.onload = function(e) {
          const text = e.target.result;
          const lines = text.trim().split(/\r?\n/);
          if (lines.length <= 1) {
            alert('CSVにデータ行がありません');
            return;
          }
          lines.shift(); // remove header
          let count = 0;
          const promises = lines.map(line => {
            if (!line) return Promise.resolve();
            const cols = parseCsvLine(line);
            // columns: id,name,date,place,lat,lng,status
            const data = {
              name: cols[1] || '',
              date: cols[2] || '',
              place: cols[3] || '',
              lat: cols[4] || '',
              lng: cols[5] || '',
              status: cols[6] || 'draft'
            };
            if (!data.name || !data.date) return Promise.resolve();
            count++;
            return api({ path: '/roro/v1/events', method: 'POST', data: data }).catch(err => console.error(err));
          });
          Promise.all(promises).then(() => {
            alert('CSVインポートが完了しました（' + count + '件登録）');
            input.value = '';
            list();
          });
        };
        reader.readAsText(file);
      });

      // 簡易CSV行パーサー（引用符対応）
      function parseCsvLine(line) {
        const result = [];
        let current = '';
        let insideQuote = false;
        for (let i = 0; i < line.length; i++) {
          const ch = line[i];
          if (insideQuote) {
            if (ch === '"') {
              if (i + 1 < line.length && line[i+1] === '"') {
                current += '"';
                i++;
              } else {
                insideQuote = false;
              }
            } else {
              current += ch;
            }
          } else {
            if (ch === '"') {
              insideQuote = true;
            } else if (ch === ',') {
              result.push(current);
              current = '';
            } else {
              current += ch;
            }
          }
        }
        result.push(current);
        return result;
      }

    })();
    </script>
    <?php
}
