/*
  login.js – ログイン画面のイベントハンドラ

  ユーザーがログインフォームを送信した際に入力値を検証し、
  ローカルストレージへユーザー情報を保存してマップページへ遷移します。
  ソーシャルログインボタンは本番環境ではOAuthで処理しますが、
  このモックでは即座にログイン扱いとします。
*/

document.addEventListener('DOMContentLoaded', () => {
  /**
   * ローカルユーザー名・パスワードによるログイン
   * 登録済みユーザーの場合は照合し、未登録の場合は仮登録します。
   */
  const form = document.getElementById('login-form');
  if (form) {
    form.addEventListener('submit', (e) => {
      e.preventDefault();
      const email = document.getElementById('login-email').value.trim();
      const password = document.getElementById('login-password').value.trim();
      if (!email || !password) {
        alert('メールアドレスとパスワードを入力してください');
        return;
      }
      // シンプルなローカルストレージを用いた認証（モック用）
      let registered;
      try {
        registered = JSON.parse(localStorage.getItem('registeredUser'));
      } catch (err) {
        registered = null;
      }
      if (registered && registered.email === email && registered.password === password) {
        const user = { ...registered };
        sessionStorage.setItem('user', JSON.stringify(user));
        if (typeof RORO_ROUTES !== 'undefined' && RORO_ROUTES.map) {
          location.href = RORO_ROUTES.map;
        } else {
          location.href = 'map.html';
        }
      } else {
        // 既存ユーザーが存在しない場合は仮登録してログイン扱い
        const user = { email, name: email.split('@')[0] };
        sessionStorage.setItem('user', JSON.stringify(user));
        if (typeof RORO_ROUTES !== 'undefined' && RORO_ROUTES.map) {
          location.href = RORO_ROUTES.map;
        } else {
          location.href = 'map.html';
        }
      }
    });
  }

  /**
   * ソーシャルログインボタンのクリック時に REST API から認証URLを取得し、
   * ブラウザをリダイレクトします。 Google/LINE 双方に対応します。
   */
  const googleBtn = document.querySelector('.google-btn');
  if (googleBtn) {
    googleBtn.addEventListener('click', () => {
      fetch('/wp-json/roro-auth/v1/google/login')
        .then((res) => res.json())
        .then((data) => {
          if (data.auth_url) {
            window.location.href = data.auth_url;
          } else if (data.error) {
            alert('Googleログインの初期化に失敗しました: ' + data.error);
          }
        })
        .catch((err) => {
          console.error(err);
          alert('Googleログインの初期化に失敗しました');
        });
    });
  }
  const lineBtn = document.querySelector('.line-btn');
  if (lineBtn) {
    lineBtn.addEventListener('click', () => {
      fetch('/wp-json/roro-auth/v1/line/login')
        .then((res) => res.json())
        .then((data) => {
          if (data.auth_url) {
            window.location.href = data.auth_url;
          } else if (data.error) {
            alert('LINEログインの初期化に失敗しました: ' + data.error);
          }
        })
        .catch((err) => {
          console.error(err);
          alert('LINEログインの初期化に失敗しました');
        });
    });
  }
});