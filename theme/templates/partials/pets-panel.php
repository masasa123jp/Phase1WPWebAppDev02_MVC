<?php
// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
?>
<div class="roro-pets">
  <h2>ペット</h2>
  <form id="roro-pet-form">
    <input type="text" name="name" placeholder="名前" required />
    <select name="species"><option value="dog">犬</option><option value="cat">猫</option></select>
    <input type="text" name="gender" placeholder="性別" />
    <input type="date" name="birthday" placeholder="誕生日" />
    <input type="number" step="any" name="weight" placeholder="体重" />
    <button>追加</button>
  </form>
  <table class="roro-pet-list"><thead><tr><th>名前</th><th>性別</th><th>誕生日</th><th>操作</th></tr></thead><tbody></tbody></table>
</div>
<script>
(function(){
  const api = wp.apiFetch;
  const list = () => api({ path: '/roro/v1/pets' }).then(rows => {
    const tbody = document.querySelector('.roro-pet-list tbody');
    tbody.innerHTML = '';
    rows.forEach(r => {
      const tr = document.createElement('tr');
      tr.innerHTML = `<td><input value="${r.name||''}" data-id="${r.id}" class="nm" /></td>` +
                     `<td><input value="${r.gender||''}" data-id="${r.id}" class="gd" /></td>` +
                     `<td><input value="${r.birthday||''}" data-id="${r.id}" class="bd" /></td>` +
                     `<td><button class="upd" data-id="${r.id}">更新</button> <button class="del" data-id="${r.id}">削除</button></td>`;
      tbody.appendChild(tr);
    });
  });
  list();
  document.getElementById('roro-pet-form').addEventListener('submit', function(e){
    e.preventDefault();
    const fd = new FormData(this);
    api({ path: '/roro/v1/pets', method: 'POST', data: Object.fromEntries(fd) }).then(() => {
      this.reset();
      list();
    });
  });
  document.querySelector('.roro-pet-list').addEventListener('click', function(e){
    const id = e.target.getAttribute('data-id');
    if(e.target.classList.contains('upd')){
      const tr = e.target.closest('tr');
      const data = {
        name: tr.querySelector('.nm').value,
        gender: tr.querySelector('.gd').value,
        birthday: tr.querySelector('.bd').value,
      };
      api({ path: '/roro/v1/pets/' + id, method: 'PUT', data }).then(() => list());
    }
    if(e.target.classList.contains('del')){
      api({ path: '/roro/v1/pets/' + id, method: 'DELETE' }).then(() => list());
    }
  });
})();
</script>