/*
  js/magazine.js – テーマ版（プラグイン不使用）
  目的：
   - ページめくり（クリック/矢印/スワイプ）
   - クリック委譲でカード生成タイミングに依存しない
   - 画像URLをテーマURL（RORO_THEME.base）に正規化
   - DOMContentLoaded 済/未済の両方で確実に初期化
   - window.openMagazine を公開（デバッグ・強制起動用）
*/

let viewer, book;
let currentIssuePages = [];
let currentPageIndex = 0;
let prevArrow, nextArrow, closeBtn;

// ===== Analytics Helpers =====
// Ensure there is a unique session ID stored in localStorage for analytics
function roroEnsureSession() {
  try {
    if (!localStorage.getItem('roro_sid')) {
      const uuid = (typeof crypto !== 'undefined' && crypto.randomUUID) ? crypto.randomUUID() : (Date.now() + '-' + Math.random());
      localStorage.setItem('roro_sid', uuid);
    }
  } catch (e) {}
}

// Send JSON payload via Beacon API or fetch fallback
function roroSendBeaconJSON(url, payload) {
  try {
    const blob = new Blob([JSON.stringify(payload)], { type: 'application/json' });
    navigator.sendBeacon(url, blob);
  } catch (e) {
    // Fallback to fetch if beacon is not available
    fetch(url, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(payload)
    }).catch(() => {});
  }
}

// Track a page view event for analytics
function trackView(issueId, pageIndex, dwell) {
  roroEnsureSession();
  try {
    roroSendBeaconJSON('/wp-json/roro-analytics/v1/view', {
      issue_id: issueId,
      page_id: pageIndex,
      dwell_ms: dwell || null,
      session_id: localStorage.getItem('roro_sid'),
      referer: document.referrer || null,
      device: /Mobi/i.test(navigator.userAgent) ? 'mobile' : 'pc',
      lang: (navigator.language || 'ja')
    });
  } catch (e) {}
}

// Track a link click event for analytics
function trackClick(issueId, pageIndex, linkId, href) {
  roroEnsureSession();
  try {
    roroSendBeaconJSON('/wp-json/roro-analytics/v1/click', {
      issue_id: issueId,
      page_id: pageIndex,
      link_id: linkId || null,
      href: href || null,
      session_id: localStorage.getItem('roro_sid')
    });
  } catch (e) {}
}

// 画像URLをテーマURLに揃える
function img(path) {
  try {
    if (window.RORO_THEME && RORO_THEME.base) {
      return `${RORO_THEME.base}/${path.replace(/^\/+/, '')}`;
    }
  } catch (e) {}
  return path;
}

// ====== 雑誌データ（必要に応じて拡張/差し替え） ======
// Use server-provided magazine data if available.
// If window.RORO_MAG_DATA is defined and is an array, override the static data.
const magazineData = (function() {
  // Static fallback data defined below
  const staticData = [
  {
    id: '2025-06',
    title: '2025年6月号',
    pages: [
      { html: `
        <div style="display:flex;flex-direction:column;height:100%;">
          <img src="${img('images/magazine_cover1.png')}" alt="cover" style="width:100%;height:65%;object-fit:cover;border-radius:8px;">
          <div style="padding:0.3rem;text-align:center;">
            <h2 style="margin:0;color:#1F497D;" data-i18n-key="mag_issue_june">2025年6月号</h2>
            <h3 style="margin:0.2rem 0;color:#e67a8a;font-size:1.3rem;" data-i18n-key="mag_theme_june">犬と梅雨のおうち時間</h3>
            <p style="font-size:1.0rem;" data-i18n-key="mag_desc_june">雨の日でも犬と楽しく過ごせる特集</p>
          </div>
        </div>`},
      { html: `
        <h3 style="color:#1F497D;" data-i18n-key="mag_event_title">今月のイベント</h3>
        <ul style="list-style:disc;padding-left:1.2rem;font-size:0.9rem;">
          <li>6/12 オンラインヨガ with ワンちゃん</li>
          <li>6/26 室内ドッグラン・オフ会</li>
          <li>6/30 レインコートファッションショー</li>
        </ul>`},
      { html: `
        <h3 style="color:#1F497D;" data-i18n-key="mag_disaster_title">ちぃまめの防災アドバイス</h3>
        <img src="${img('images/chiamame_disaster.png')}" alt="防災" style="width:100%;max-height:250px;object-fit:contain;margin-bottom:0.5rem;">`},
      { html: `
        <h3 style="color:#1F497D;">ちぃまめの占い</h3>
        <img src="${img('images/fortune_advice.png')}" alt="占い" style="width:100%;max-height:300px;object-fit:contain;margin-bottom:0.5rem;border-radius:8px;">`},
      { html: `
        <div style="background:#F9E9F3;display:flex;align-items:center;justify-content:center;height:100%;padding:1rem;">
          <div style="writing-mode:vertical-rl; transform: rotate(180deg); font-size:1.4rem; color:#1F497D; text-align:center;">
            PROJECT RORO<br>2025年6月号
          </div>
        </div>`}
    ]
  },
  {
    id: '2025-07',
    title: '2025年7月号',
    pages: [
      { html: `
        <div style="display:flex;flex-direction:column;height:100%;">
          <img src="${img('images/magazine_cover2.png')}" alt="cover" style="width:100%;height:65%;object-fit:cover;border-radius:8px;">
          <div style="padding:0.4rem;text-align:center;">
            <h2 style="margin:0;color:#1F497D;" data-i18n-key="mag_issue_july">2025年7月号</h2>
            <h3 style="margin:0.3rem 0;color:#e67a8a;font-size:1.3rem;" data-i18n-key="mag_theme_july">犬と夏のおでかけ × UVケア</h3>
            <p style="font-size:1.0rem;" data-i18n-key="mag_desc_july">紫外線対策とおでかけスポット♪</p>
          </div>
        </div>`},
      { html: `
        <h3 style="color:#1F497D;" data-i18n-key="mag_relax_cafe_title">ワンちゃんとくつろげるカフェ</h3>
        <img src="${img('images/pet_cafe.png')}" alt="カフェ" style="width:100%;max-height:250px;object-fit:cover;border-radius:8px;margin-bottom:0.5rem;">`},
      { html: `
        <div style="background:#F9E9F3;display:flex;align-items:center;justify-content:center;height:100%;padding:1rem;">
          <div style="writing-mode:vertical-rl; transform: rotate(180deg); font-size:1.4rem; color:#1F497D; text-align:center;">
            PROJECT RORO<br>2025年7月号
          </div>
        </div>`}
    ]
  }
  ,
  {
    id: '2025-08',
    title: '2025年8月号',
    pages: [
      { html: `
        <div style="display:flex;flex-direction:column;height:100%;">
          <img src="${img('images/magazine_cover_aug.png')}" alt="cover" style="width:100%;height:65%;object-fit:cover;border-radius:8px;">
          <div style="padding:0.3rem;text-align:center;">
            <h2 style="margin:0;color:#1F497D;" data-i18n-key="mag_issue_aug">2025年8月号</h2>
            <h3 style="margin:0.2rem 0;color:#e67a8a;font-size:1.3rem;" data-i18n-key="mag_theme_aug">夏のお散歩とクールダウン</h3>
            <p style="font-size:1.0rem;" data-i18n-key="mag_desc_aug">暑い季節に役立つグッズ特集</p>
          </div>
        </div>` },
      { html: `
        <h3 style="color:#1F497D;" data-i18n-key="mag_product_title">おすすめグッズ</h3>
        <div style="display:flex;gap:1rem;flex-wrap:wrap;justify-content:center;">
          <div style="text-align:center;width:45%;">
            <img src="${img('images/product_uv_clothes.png')}" alt="UVケア洋服" style="width:100%;max-height:180px;object-fit:contain;border-radius:6px;" />
            <p style="margin-top:0.3rem;font-size:0.8rem;" data-i18n-key="mag_product_uv">UV対策洋服</p>
          </div>
          <div style="text-align:center;width:45%;">
            <img src="${img('images/product_cooling_mat.png')}" alt="クールマット" style="width:100%;max-height:180px;object-fit:contain;border-radius:6px;" />
            <p style="margin-top:0.3rem;font-size:0.8rem;" data-i18n-key="mag_product_cool">ひんやりマット</p>
          </div>
        </div>` },
      { html: `
        <div style="background:#F9E9F3;display:flex;align-items:center;justify-content:center;height:100%;padding:1rem;">
          <div style="writing-mode:vertical-rl; transform: rotate(180deg); font-size:1.4rem; color:#1F497D; text-align:center;">
            PROJECT RORO<br>2025年8月号
          </div>
        </div>` }
    ]
  }
  ,
  {
    id: '2025-09',
    title: '2025年9月号',
    pages: [
      { html: `
        <div style="display:flex;flex-direction:column;height:100%;">
          <img src="${img('images/magazine_cover_sep.png')}" alt="cover" style="width:100%;height:65%;object-fit:cover;border-radius:8px;">
          <div style="padding:0.3rem;text-align:center;">
            <h2 style="margin:0;color:#1F497D;" data-i18n-key="mag_issue_sep">2025年9月号</h2>
            <h3 style="margin:0.2rem 0;color:#e67a8a;font-size:1.3rem;" data-i18n-key="mag_theme_sep">秋の行楽×ワンちゃん</h3>
            <p style="font-size:1.0rem;" data-i18n-key="mag_desc_sep">紅葉狩りと防寒対策</p>
          </div>
        </div>` },
      { html: `
        <h3 style="color:#1F497D;" data-i18n-key="mag_event_autumn">秋のイベント</h3>
        <ul style="list-style:disc;padding-left:1.2rem;font-size:0.9rem;">
          <li>9/10 愛犬とハイキング</li>
          <li>9/22 ペットフェア</li>
        </ul>` },
      { html: `
        <h3 style="color:#1F497D;" data-i18n-key="mag_tips_title">ちぃまめの帽子コーデ</h3>
        <img src="${img('images/chiamame_hat_cartoon.png')}" alt="帽子コーデ" style="width:100%;max-height:250px;object-fit:contain;border-radius:8px;margin-bottom:0.5rem;" />` },
      { html: `
        <div style="background:#F9E9F3;display:flex;align-items:center;justify-content:center;height:100%;padding:1rem;">
          <div style="writing-mode:vertical-rl; transform: rotate(180deg); font-size:1.4rem; color:#1F497D; text-align:center;">
            PROJECT RORO<br>2025年9月号
          </div>
        </div>` }
    ]
  }
  ];
  // Check for server-provided magazine data. If present and valid, override the static data.
  try {
    if (Array.isArray(window.RORO_MAG_DATA) && window.RORO_MAG_DATA.length > 0) {
      return window.RORO_MAG_DATA;
    }
  } catch (err) {}
  return staticData;
})();

// ====== 初期化 ======
function initMagazine() {
  viewer = document.getElementById('magazine-viewer');
  book   = viewer ? viewer.querySelector('.book') : null;
  if (!viewer || !book) return;

  // クリック委譲（カードは後から追加されても動く）
  document.addEventListener('click', (e) => {
    const card = e.target.closest('.magazine-card, .mag-card, [data-mag-issue]');
    if (!card) return;
    let idx = parseInt(card.getAttribute('data-mag-index'), 10);
    if (Number.isNaN(idx)) {
      const cards = Array.from(document.querySelectorAll('.magazine-card, .mag-card, [data-mag-issue]'));
      idx = Math.max(0, cards.indexOf(card));
    }
    try { openMagazine(idx); } catch(err){ console.error('openMagazine failed:', err); }
  }, { passive: true });

  // キーボード操作の委譲：Enter/Spaceでカードを開く
  document.addEventListener('keydown', (e) => {
    // We only handle activation keys
    if (e.key !== 'Enter' && e.key !== ' ') return;
    const card = e.target.closest('.magazine-card, .mag-card, [data-mag-issue]');
    if (!card) return;
    e.preventDefault();
    let idx = parseInt(card.getAttribute('data-mag-index'), 10);
    if (Number.isNaN(idx)) {
      const cards = Array.from(document.querySelectorAll('.magazine-card, .mag-card, [data-mag-issue]'));
      idx = Math.max(0, cards.indexOf(card));
    }
    try { openMagazine(idx); } catch(err){ console.error('openMagazine failed:', err); }
  });

  // 表示領域のフィット
  const fit = () => {
    const h = Math.max(320, Math.floor(window.innerHeight * 0.82));
    book.style.height = `${h}px`;
    book.style.width  = `${Math.floor(h * 0.72)}px`;
  };
  window.addEventListener('resize', fit);
  fit();
}

function safeReady(fn) {
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', fn, { once:true });
  } else {
    fn();
  }
  // 最適化プラグイン対策：window.load でも初期化
  window.addEventListener('load', () => { try{ fn(); }catch(e){} }, { once:true });
}
safeReady(initMagazine);

// ====== ビューア制御 ======
function openMagazine(idx = 0) {
  const issue = magazineData[idx] || magazineData[0];
  currentIssuePages = issue.pages;
  currentPageIndex  = 0;
  viewer.style.display = 'flex';
  renderPages();
  // Send view event for the first page when viewer opens
  try {
    trackView(issue.id || null, 0, null);
  } catch (e) {}
}
window.openMagazine = openMagazine; // デバッグ用に公開

function renderPages() {
  book.innerHTML = '';

  // ナビと閉じる
  prevArrow = document.createElement('div'); prevArrow.className = 'nav-arrow prev'; prevArrow.innerHTML = '&#9664;';
  nextArrow = document.createElement('div'); nextArrow.className = 'nav-arrow next'; nextArrow.innerHTML = '&#9654;';
  closeBtn  = document.createElement('div'); closeBtn.className  = 'close-btn';     closeBtn.innerHTML  = '&times;';
  prevArrow.addEventListener('click', (e)=>{ e.stopPropagation(); flipBack(); });
  nextArrow.addEventListener('click', (e)=>{ e.stopPropagation(); flipNext(); });
  closeBtn .addEventListener('click', (e)=>{ e.stopPropagation(); closeViewer(); });

  book.appendChild(closeBtn); book.appendChild(prevArrow); book.appendChild(nextArrow);

  const total = currentIssuePages.length;
  for (let i = total - 1; i >= 0; i--) {
    const page = document.createElement('div');
    page.className = 'page'; page.dataset.index = i;
    if (i === total - 1) page.classList.add('back-cover');
    page.style.zIndex = (total - i); // 先頭ページが最前面
    const content = document.createElement('div'); content.className = 'page-content';
    content.innerHTML = currentIssuePages[i].html; // ここに画像パスを含むHTMLが入る
    page.appendChild(content); book.appendChild(page);
  }
  updateNav();
  if (typeof applyTranslations === 'function') applyTranslations();
}

function flipNext() {
  const total = currentIssuePages.length;
  if (currentPageIndex >= total) return;
  const pages = book.querySelectorAll('.page');
  const target = pages[pages.length - 1 - currentPageIndex];
  target.classList.add('flipped');
  currentPageIndex++;
  updateNav();
  // Track view for the next page
  try {
    const issue = magazineData.find(m => m.pages === currentIssuePages) || {};
    trackView(issue.id || null, currentPageIndex, null);
  } catch (e) {}
}

function flipBack() {
  if (currentPageIndex <= 0) return;
  currentPageIndex--;
  const pages = book.querySelectorAll('.page');
  const target = pages[pages.length - 1 - currentPageIndex];
  target.classList.remove('flipped');
  updateNav();
  // Track view for the previous page
  try {
    const issue = magazineData.find(m => m.pages === currentIssuePages) || {};
    trackView(issue.id || null, currentPageIndex, null);
  } catch (e) {}
}

function updateNav() {
  if (!prevArrow || !nextArrow) return;
  prevArrow.style.display = currentPageIndex === 0 ? 'none' : 'block';
  const total = currentIssuePages.length;
  nextArrow.style.display = currentPageIndex >= total - 1 ? 'none' : 'block';
}

function closeViewer() {
  document.querySelectorAll('.page').forEach(p => p.classList.remove('flipped'));
  viewer.style.display = 'none';
}

// Global click listener to track link clicks inside magazine pages
document.addEventListener('click', (e) => {
  // Use closest() to check if an anchor is inside a page-content
  const anchor = e.target.closest('.page-content a[href]');
  if (!anchor) return;
  // Only track clicks when the viewer is visible
  if (!viewer || viewer.style.display === 'none') return;
  try {
    const issue = magazineData.find(m => m.pages === currentIssuePages) || {};
    trackClick(issue.id || null, currentPageIndex, null, anchor.href);
  } catch (err) {}
}, true);