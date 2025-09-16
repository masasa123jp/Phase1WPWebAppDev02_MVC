<?php
// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Render the Advice admin page.
 * Provides a simple form and table to manage one-point advice entries via the REST API.
 */
function roro_admin_advice_page() {
    ?>
    <div class="wrap">
        <h1>アドバイス管理</h1>
        <form id="roro-advice-form">
            <!-- 編集時に使用するhiddenフィールド -->
            <input type="hidden" name="editing_id" />
            <label>ペット種別
                <select name="pet_type">
                    <option value="DOG">犬</option>
                    <option value="CAT">猫</option>
                    <option value="OTHER">その他</option>
                </select>
            </label>
            <input type="text" name="category_code" placeholder="カテゴリコード" />
            <input type="text" name="title" placeholder="タイトル" required />
            <textarea name="body" placeholder="本文" rows="3" style="width:100%;"></textarea>
            <input type="url" name="url" placeholder="URL" />
            <input type="text" name="for_which_pets" placeholder="対象ペット" />
            <label style="display:block;margin-top:0.5rem;">
                <input type="checkbox" name="isVisible" value="1" checked /> 表示
            </label>
            <label style="display:block;margin-top:0.5rem;">
                ステータス
                <select name="status">
                    <option value="draft">下書き</option>
                    <option value="published">公開</option>
                </select>
            </label>
            <button type="submit" class="button button-primary">追加</button>
        </form>
        <hr />
        <table class="widefat" id="roro-advice-list">
            <thead><tr><th>ID</th><th>タイトル</th><th>ペット種別</th><th>表示</th><th>ステータス</th><th>操作</th></tr></thead>
            <tbody></tbody>
        </table>
    </div>
    <script>
    (function(){
      const api = wp.apiFetch;
      let editingId = null;
      const form = document.getElementById('roro-advice-form');
      const listTable = document.getElementById('roro-advice-list');
      const submitBtn = form.querySelector('button[type="submit"]');
      function list() {
        // Administrators request all advice entries (including drafts) via include_hidden=1
        api({ path: '/roro/v1/advice?include_hidden=1' }).then(rows => {
          const tbody = listTable.querySelector('tbody');
          tbody.innerHTML = '';
          rows.forEach(row => {
            const tr = document.createElement('tr');
            tr.innerHTML = `<td>${row.OPAM_ID}</td><td>${row.title||''}</td><td>${row.pet_type||''}</td><td>${row.isVisible ? '✔' : ''}</td><td>${row.status||''}</td>` +
              `<td><button class="button edit" data-id="${row.OPAM_ID}">編集</button> <button class="button del" data-id="${row.OPAM_ID}">削除</button></td>`;
            tbody.appendChild(tr);
          });
        });
      }
      list();
      form.addEventListener('submit', function(e){
        e.preventDefault();
        const fd = new FormData(form);
        const data = Object.fromEntries(fd);
        // convert checkbox to integer
        data.isVisible = form.querySelector('input[name="isVisible"]').checked ? 1 : 0;
        if (editingId) {
          // update
          api({ path: '/roro/v1/advice/' + editingId, method: 'PUT', data: data }).then(() => {
            editingId = null;
            form.reset();
            // default pet type back to DOG to avoid blank select
            form.querySelector('select[name="pet_type"]').value = 'DOG';
            submitBtn.textContent = '追加';
            list();
          });
        } else {
          // create
          api({ path: '/roro/v1/advice', method: 'POST', data: data }).then(() => {
            form.reset();
            form.querySelector('select[name="pet_type"]').value = 'DOG';
            list();
          });
        }
      });
      listTable.addEventListener('click', function(e){
        if (e.target.classList.contains('del')) {
          const id = e.target.getAttribute('data-id');
          if (confirm('削除しますか？')) {
            api({ path: '/roro/v1/advice/' + id, method: 'DELETE' }).then(() => list());
          }
        } else if (e.target.classList.contains('edit')) {
          const id = e.target.getAttribute('data-id');
          api({ path: '/roro/v1/advice/' + id }).then(row => {
            editingId = id;
            submitBtn.textContent = '更新';
            form.querySelector('select[name="pet_type"]').value = row.pet_type || 'OTHER';
            form.querySelector('input[name="category_code"]').value = row.category_code || '';
            form.querySelector('input[name="title"]').value = row.title || '';
            form.querySelector('textarea[name="body"]').value = row.body || '';
            form.querySelector('input[name="url"]').value = row.url || '';
            form.querySelector('input[name="for_which_pets"]').value = row.for_which_pets || '';
            form.querySelector('input[name="isVisible"]').checked = row.isVisible == 1;
            form.querySelector('select[name="status"]').value = row.status || 'draft';
          });
        }
      });
    })();
    </script>
    <?php
}