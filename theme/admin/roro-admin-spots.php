<?php
// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Render the Spots admin page.
 * Provides a simple form and table to manage travel spots via the REST API.
 */
function roro_admin_spots_page() {
    ?>
    <div class="wrap">
        <h1>スポット管理</h1>
        <form id="roro-spot-form">
            <!-- 編集時は editingId にセットされる -->
            <input type="hidden" name="editing_id" />
            <input type="text" name="prefecture" placeholder="都道府県" />
            <input type="text" name="name" placeholder="名称" required />
            <input type="text" name="address" placeholder="住所" />
            <input type="number" step="any" name="latitude" placeholder="緯度" />
            <input type="number" step="any" name="longitude" placeholder="経度" />
            <input type="url" name="url" placeholder="URL" />
            <select name="status">
                <option value="draft">下書き</option>
                <option value="published">公開</option>
            </select>
            <button type="submit" class="button button-primary">追加</button>
        </form>
        <hr />
        <!-- ステータス絞り込みフィルター -->
        <div style="margin:1rem 0;">
            <label for="spot-status-filter">表示ステータス:</label>
            <select id="spot-status-filter">
                <option value="all">すべて</option>
                <option value="published">公開のみ</option>
                <option value="draft">下書きのみ</option>
            </select>
            <!-- CSVエクスポートボタン -->
            <button type="button" id="spot-export-csv" class="button" style="margin-left:1rem;">CSVダウンロード</button>
            <!-- CSVインポート用: ファイル選択とボタン -->
            <input type="file" id="spot-import-input" accept=".csv" style="margin-left:1rem;" />
            <button type="button" id="spot-import-btn" class="button">CSVインポート</button>
        </div>
        <table class="widefat" id="roro-spot-list"><thead><tr><th>ID</th><th>名称</th><th>都道府県</th><th>表示</th><th>ステータス</th><th>操作</th></tr></thead><tbody></tbody></table>
    </div>
    <script>
    (function(){
      const api = wp.apiFetch;
      let spots = [];
      let editingId = null;
      const form = document.getElementById('roro-spot-form');
      const submitBtn = form.querySelector('button[type="submit"]');
      const listTable = document.getElementById('roro-spot-list');
      function list() {
        const statusVal = document.getElementById('spot-status-filter').value;
        let qs = '?all=1';
        if (statusVal !== 'all') {
          qs += '&status=' + encodeURIComponent(statusVal);
        }
        api({ path: '/roro/v1/spots' + qs }).then(rows => {
          spots = rows;
          const tbody = listTable.querySelector('tbody');
          tbody.innerHTML = '';
          rows.forEach(r => {
            const tr = document.createElement('tr');
            tr.innerHTML = `<td>${r.id||''}</td><td>${r.name||''}</td><td>${r.prefecture||''}</td><td>${r.isVisible?'✔':''}</td><td>${r.status||''}</td>` +
              `<td><button data-id="${r.id}" class="button edit">編集</button> <button data-id="${r.id}" class="button del">削除</button></td>`;
            tbody.appendChild(tr);
          });
        });
      }
      list();
      document.getElementById('spot-status-filter').addEventListener('change', function(){
        list();
      });
      form.addEventListener('submit', function(e){
        e.preventDefault();
        const fd = new FormData(form);
        const data = Object.fromEntries(fd);
        delete data.editing_id;
        // 入力バリデーション: 名称必須、緯度・経度は数値
        const nameVal = form.querySelector('input[name="name"]').value.trim();
        const latVal = form.querySelector('input[name="latitude"]').value;
        const lngVal = form.querySelector('input[name="longitude"]').value;
        if (!nameVal) { alert('名称を入力してください'); return; }
        if (latVal && isNaN(parseFloat(latVal))) { alert('緯度は数値で入力してください'); return; }
        if (lngVal && isNaN(parseFloat(lngVal))) { alert('経度は数値で入力してください'); return; }

        if (editingId) {
          api({ path: '/roro/v1/spots/' + editingId, method: 'PUT', data: data }).then(() => {
            editingId = null;
            form.reset();
            submitBtn.textContent = '追加';
            list();
          });
        } else {
          api({ path: '/roro/v1/spots', method: 'POST', data: data }).then(() => {
            form.reset();
            list();
          });
        }
      });
      listTable.addEventListener('click', function(e){
        if (e.target.classList.contains('del')) {
          const id = e.target.getAttribute('data-id');
          if (confirm('削除しますか？')) {
            api({ path: '/roro/v1/spots/' + id, method: 'DELETE' }).then(() => list());
          }
        } else if (e.target.classList.contains('edit')) {
          const id = e.target.getAttribute('data-id');
          const row = spots.find(sp => String(sp.id) === String(id));
          if (row) {
            editingId = id;
            submitBtn.textContent = '更新';
            form.querySelector('input[name="prefecture"]').value = row.prefecture || '';
            form.querySelector('input[name="name"]').value = row.name || '';
            form.querySelector('input[name="address"]').value = row.address || '';
            form.querySelector('input[name="latitude"]').value = row.latitude || '';
            form.querySelector('input[name="longitude"]').value = row.longitude || '';
            form.querySelector('input[name="url"]').value = row.url || '';
            form.querySelector('select[name="status"]').value = row.status || 'draft';
          }
        }
      });

      // CSVエクスポート機能
      document.getElementById('spot-export-csv')?.addEventListener('click', function(){
        exportCSV();
      });
      function exportCSV() {
        api({ path: '/roro/v1/spots?all=1' }).then(rows => {
          const header = ['id','prefecture','name','address','latitude','longitude','url','status'];
          let csv = header.join(',') + '\n';
          rows.forEach(r => {
            const row = [r.id||'', r.prefecture||'', r.name||'', r.address||'', r.latitude||'', r.longitude||'', r.url||'', r.status||''];
            csv += row.map(v => '"' + String(v).replace(/"/g,'""') + '"').join(',') + '\n';
          });
          const blob = new Blob([csv], {type:'text/csv;charset=utf-8;'});
          const url = URL.createObjectURL(blob);
          const a = document.createElement('a');
          a.href = url;
          a.download = 'spots.csv';
          a.click();
          URL.revokeObjectURL(url);
        });
      }

      // CSVインポート機能
      document.getElementById('spot-import-btn')?.addEventListener('click', function() {
        const input = document.getElementById('spot-import-input');
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
          lines.shift();
          let count = 0;
          const promises = lines.map(line => {
            if (!line) return Promise.resolve();
            const cols = parseCsvLine(line);
            // columns: id,prefecture,name,address,latitude,longitude,url,status
            const data = {
              prefecture: cols[1] || '',
              name: cols[2] || '',
              address: cols[3] || '',
              latitude: cols[4] || '',
              longitude: cols[5] || '',
              url: cols[6] || '',
              status: cols[7] || 'draft'
            };
            if (!data.name) return Promise.resolve();
            count++;
            return api({ path: '/roro/v1/spots', method: 'POST', data: data }).catch(err => console.error(err));
          });
          Promise.all(promises).then(() => {
            alert('CSVインポートが完了しました（' + count + '件登録）');
            input.value = '';
            list();
          });
        };
        reader.readAsText(file);
      });

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