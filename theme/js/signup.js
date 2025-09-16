/*
  signup.js – 新規登録画面のイベントハンドラ

  登録フォームの送信時に入力値をチェックし、ユーザー情報とペット情報を
  ローカルストレージへ保存します。実際の環境ではここからサーバーへ
  リクエストを送信してユーザー登録を完了させますが、本モックでは
  完了後に直接マップページへ遷移します。
*/

document.addEventListener('DOMContentLoaded', () => {
  const form = document.getElementById('signup-form');
  if (form) {
    form.addEventListener('submit', (e) => {
      e.preventDefault();
      // 必須項目を取得
      const name = document.getElementById('name').value.trim();
      const furigana = document.getElementById('furigana').value.trim();
      const email = document.getElementById('email').value.trim();
      const password = document.getElementById('password').value.trim();
      const petType = document.getElementById('petType').value;
      const petName = document.getElementById('petName') ? document.getElementById('petName').value.trim() : '';
      const petAge = document.getElementById('petAge').value;
      const address = document.getElementById('address').value.trim();
      const phone = document.getElementById('phone').value.trim();
      if (!name || !email || !password) {
        alert('名前、メールアドレス、パスワードは必須です');
        return;
      }
      // 新しいデータ構造では pets 配列にペット情報を格納する
      const pets = [];
      if (petType) {
        pets.push({ type: petType, name: petName, age: petAge });
      }
      const user = {
        name,
        furigana,
        email,
        password,
        address,
        phone,
        pets
      };
      // 新規登録ユーザーを保存
      localStorage.setItem('registeredUser', JSON.stringify(user));
      // 現在のログインユーザーとしても保存
      localStorage.setItem('user', JSON.stringify(user));
      if (typeof RORO_ROUTES !== 'undefined' && RORO_ROUTES.map) {
        location.href = RORO_ROUTES.map;
      } else {
        location.href = 'map.html';
      }
    });
  }
  // Google登録
  const googleBtn = document.querySelector('.google-btn');
  if (googleBtn) {
    googleBtn.addEventListener('click', () => {
      const user = { email: 'google@example.com', name: 'Googleユーザー' };
      localStorage.setItem('user', JSON.stringify(user));
      if (typeof RORO_ROUTES !== 'undefined' && RORO_ROUTES.map) {
        location.href = RORO_ROUTES.map;
      } else {
        location.href = 'map.html';
      }
    });
  }
  // LINE登録
  const lineBtn = document.querySelector('.line-btn');
  if (lineBtn) {
    lineBtn.addEventListener('click', () => {
      const user = { email: 'line@example.com', name: 'LINEユーザー' };
      localStorage.setItem('user', JSON.stringify(user));
      if (typeof RORO_ROUTES !== 'undefined' && RORO_ROUTES.map) {
        location.href = RORO_ROUTES.map;
      } else {
        location.href = 'map.html';
      }
    });
  }
});