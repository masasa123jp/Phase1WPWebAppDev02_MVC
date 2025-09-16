/*
  main.js – 共通JavaScript処理

  すべてのページで共有される関数や初期化ロジックを集約したスクリプトです。
  ・現在のページに対応するボトムナビゲーションのハイライト
  ・ログイン状態のチェックと必要に応じたリダイレクト
*/

// ページ読み込み時の共通処理
document.addEventListener('DOMContentLoaded', () => {
      // Guard against unintended URL patterns with repeated index.html segments.
      // Some hosting environments may append another "index.html" to the URL,
      // causing a redirect loop such as `/mock-01_logon/index.html/index.html`.
      // Detect this pattern and replace it with a single `index.html` to
      // stabilise the URL before any other logic runs.
      const path = location.pathname;
      if (/index\.html\/index\.html/.test(path)) {
        const cleaned = path.replace(/(index\.html\/)+index\.html$/, 'index.html');
        // Use replace() instead of assign() to avoid adding a new history entry.
        location.replace(cleaned + location.search + location.hash);
        return;
      }
  highlightNavigation();
  // WordPress 版では .html 拡張子を持つファイル名が無い場合に
  // ログインリダイレクトなどの処理をスキップします。静的 HTML
  // にのみ適用されるロジックであり、WordPress では不要です。
  const currentFile = location.pathname.split('/').pop();
  const isHtmlBased = currentFile.includes('.html');
  // Note: if the page is not an HTML file (e.g. WordPress pretty URL)
  // skip the remaining login enforcement logic.
  if (!isHtmlBased) {
    return;
  }
  // デフォルトの登録ユーザーが存在しない場合は初期登録ユーザーを作成
  try {
    const registered = localStorage.getItem('registeredUser');
    if (!registered) {
      const defaultUser = {
        name: 'testユーザー',
        email: 'test@test.com',
        password: 'testtest!!test12345@',
        petType: 'dog',
        petAge: 'adult',
        // デフォルト住所を東京都豊島区池袋4丁目に設定
        address: '東京都豊島区池袋4丁目',
        phone: ''
      };
      localStorage.setItem('registeredUser', JSON.stringify(defaultUser));
    }
    // 登録済みユーザーが存在しても住所が未定義の場合は設定
    if (registered) {
      try {
        const regObj = JSON.parse(registered);
        if (!regObj.address || regObj.address.trim() === '') {
          regObj.address = '東京都豊島区池袋4丁目';
          localStorage.setItem('registeredUser', JSON.stringify(regObj));
        }
      } catch (err) {
        /* ignore */
      }
    }
  } catch (e) {
    // localStorage が利用できない場合は何もしない
  }
  // 古い実装で localStorage に保存されていたセッション用 user を削除
  try {
    localStorage.removeItem('user');
  } catch (e) {
    /* ignore */
  }
  // 現在のページ名を取得（WordPress 版では上部で currentFile が定義済み）
  // index または signup ページ以外ではログイン必須
  const _currentFile = currentFile;
  const unrestrictedPages = ['index.html', 'signup.html', ''];
  if (!unrestrictedPages.includes(_currentFile)) {
    requireLogin();
  }
  // すでにログイン済みで index や signup ページを開いた場合はマップへリダイレクト
      if (isLoggedIn()) {
        if (_currentFile === 'index.html' || _currentFile === '' || _currentFile === '/') {
          if (typeof RORO_ROUTES !== 'undefined' && RORO_ROUTES.map) {
            location.href = RORO_ROUTES.map;
          } else {
            location.href = 'map.html';
          }
        }
        if (_currentFile === 'signup.html') {
          if (typeof RORO_ROUTES !== 'undefined' && RORO_ROUTES.map) {
            location.href = RORO_ROUTES.map;
          } else {
            location.href = 'map.html';
          }
        }
      }

  // ヘッダーのロゴクリック時の遷移処理
  // small-logo クラスを持つロゴ画像がクリックされたら、
  // セッションが存在する場合は map.html へ、そうでなければ index.html へ遷移する。
  const logoEl = document.querySelector('.small-logo');
  if (logoEl) {
    logoEl.style.cursor = 'pointer';
    logoEl.addEventListener('click', () => {
      if (isLoggedIn()) {
        if (typeof RORO_ROUTES !== 'undefined' && RORO_ROUTES.map) {
          location.href = RORO_ROUTES.map;
        } else {
          location.href = 'map.html';
        }
      } else {
        if (typeof RORO_ROUTES !== 'undefined' && RORO_ROUTES.login) {
          location.href = RORO_ROUTES.login;
        } else {
          location.href = 'index.html';
        }
      }
    });
  }
});

/**
 * 現在のURLに基づいてボトムナビのアクティブ状態を設定する。
 */
function highlightNavigation() {
  const navLinks = document.querySelectorAll('.bottom-nav .nav-item');
  if (!navLinks) return;
  const currentPage = location.pathname.split('/').pop();
  navLinks.forEach((link) => {
    const href = link.getAttribute('href');
    if (href === currentPage) {
      link.classList.add('active');
    } else {
      link.classList.remove('active');
    }
  });
}

/**
 * ログイン状態を判定する。ユーザーオブジェクトがlocalStorageに存在すればtrue。
 * @returns {boolean}
 */
function isLoggedIn() {
  // セッションストレージにユーザー情報があればログイン状態とみなす。
  return !!sessionStorage.getItem('user');
}

/**
 * ログインが必要なページでログインしていなければログイン画面にリダイレクトする。
 */
function requireLogin() {
  if (!isLoggedIn()) {
    // ログインしていない場合はトップページにリダイレクト
    if (typeof RORO_ROUTES !== 'undefined' && RORO_ROUTES.login) {
      location.href = RORO_ROUTES.login;
    } else {
      location.href = 'index.html';
    }
  }
}