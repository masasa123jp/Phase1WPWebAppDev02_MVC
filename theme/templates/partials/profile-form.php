<?php
// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
?>
<div class="roro-profile">
  <h2>プロフィール</h2>
  <form id="roro-profile-form">
    <label>氏名 <input type="text" name="name"></label>
    <label>電話 <input type="text" name="phone"></label>
    <label>郵便番号 <input type="text" name="postal_code"></label>
    <label>都道府県 <input type="text" name="prefecture"></label>
    <label>市区町村 <input type="text" name="city"></label>
    <label>住所 <input type="text" name="address_line"></label>
    <button>保存</button>
  </form>
</div>
<script>
(function(){
  const api = wp.apiFetch;
  const form = document.getElementById('roro-profile-form');
  // Fetch current profile
  api({ path: '/roro/v1/profile' }).then(res => {
    if(res.customer){
      form.name.value = res.customer.name || '';
      form.phone.value = res.customer.phone || '';
    }
    if(res.address){
      form.postal_code.value = res.address.postal_code || '';
      form.prefecture.value = res.address.prefecture || '';
      form.city.value = res.address.city || '';
      form.address_line.value = res.address.address_line || '';
    }
  });
  form.addEventListener('submit', function(e){
    e.preventDefault();
    const fd = new FormData(form);
    api({ path: '/roro/v1/profile', method: 'PUT', data: Object.fromEntries(fd) }).then(() => alert('保存しました'));
  });
})();
</script>