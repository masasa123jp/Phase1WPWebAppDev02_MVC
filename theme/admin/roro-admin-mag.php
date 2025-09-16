<?php
// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Render the magazine admin page.
 * Provides simple forms to create issues and pages via REST API.
 */
function roro_admin_mag_page() {
    ?>
    <div class="wrap">
        <h1>雑誌管理</h1>
        <h2>号</h2>
        <form id="roro-issue-form">
            <input type="text" name="title" placeholder="タイトル" required />
            <input type="text" name="issue_code" placeholder="コード" />
            <label><input type="checkbox" name="is_published" value="1" />公開</label>
            <button class="button button-primary">追加</button>
        </form>
        <table class="widefat" id="roro-issue-list"><thead><tr><th>ID</th><th>タイトル</th><th>公開</th><th>操作</th></tr></thead><tbody></tbody></table>
        <hr />
        <h2>ページ</h2>
        <form id="roro-page-form">
            <input type="number" name="issue_id" placeholder="issue_id" required />
            <input type="number" name="page_order" placeholder="順序" required />
            <input type="text" name="title" placeholder="ページタイトル" />
            <input type="url" name="image_url" placeholder="画像URL（推奨）" />
            <button class="button button-primary">追加</button>
        </form>
        <p>※ BLOBが残る環境ではURL優先で表示。マイグレーションが未適用の場合、画像は従来のBLOB列から読み出されます。</p>
    </div>
    <script>
    (function(){
      const api = wp.apiFetch;
      const listIssues = () => api({ path: '/roro/v1/magazine/issues' }).then(rows => {
        const tb = document.querySelector('#roro-issue-list tbody');
        tb.innerHTML = '';
        rows.forEach(r => {
          const tr = document.createElement('tr');
          tr.innerHTML = `<td>${r.id}</td><td>${r.title||''}</td><td>${r.is_published?'✔':''}</td><td><button data-id="${r.id}" class="button del">削除</button></td>`;
          tb.appendChild(tr);
        });
      });
      listIssues();
      document.querySelector('#roro-issue-form').addEventListener('submit', function(e){
        e.preventDefault();
        const fd = new FormData(this);
        const data = Object.fromEntries(fd);
        if(!data.is_published) data.is_published = 0;
        api({ path: '/roro/v1/magazine/issues', method: 'POST', data }).then(() => {
          this.reset();
          listIssues();
        });
      });
      document.querySelector('#roro-issue-list').addEventListener('click', function(e){
        if(e.target.classList.contains('del')){
          const id = e.target.getAttribute('data-id');
          api({ path: '/roro/v1/magazine/issues/' + id, method: 'DELETE' }).then(() => listIssues());
        }
      });
      document.querySelector('#roro-page-form').addEventListener('submit', function(e){
        e.preventDefault();
        const fd = new FormData(this);
        api({ path: '/roro/v1/magazine/pages', method: 'POST', data: Object.fromEntries(fd) }).then(() => {
          this.reset();
          alert('追加しました');
        });
      });
    })();
    </script>
    <?php
}
