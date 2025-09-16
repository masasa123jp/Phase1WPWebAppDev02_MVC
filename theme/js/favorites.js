/*
  favorites.js – お気に入りページの表示ロジック（DB連携版）

  認証済みユーザーのお気に入りイベントをサーバーから取得し、一覧表示します。
  お気に入りの削除はAjax経由でサーバーに保存されたレコードをトグルします。
  ローカルストレージには保存しません。
*/

// ===== Analytics Helpers (Favorites) =====
function roroEnsureSession() {
  try {
    if (!localStorage.getItem('roro_sid')) {
      const uuid = (typeof crypto !== 'undefined' && crypto.randomUUID) ? crypto.randomUUID() : (Date.now() + '-' + Math.random());
      localStorage.setItem('roro_sid', uuid);
    }
  } catch (e) {}
}
function roroSendBeaconJSON(url, payload) {
  try {
    const blob = new Blob([JSON.stringify(payload)], { type: 'application/json' });
    navigator.sendBeacon(url, blob);
  } catch (e) {
    fetch(url, { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(payload) }).catch(() => {});
  }
}
function trackFavoritesView() {
  roroEnsureSession();
  try {
    roroSendBeaconJSON('/wp-json/roro-analytics/v1/view', {
      issue_id: 'favorites',
      page_id: null,
      dwell_ms: null,
      session_id: localStorage.getItem('roro_sid'),
      referer: document.referrer || null,
      device: /Mobi/i.test(navigator.userAgent) ? 'mobile' : 'pc',
      lang: (navigator.language || 'ja')
    });
  } catch (e) {}
}

// REST base for favourites.  We avoid admin-ajax and use the REST API
const FAV_REST_BASE = '/wp-json/roro/v1/favorites';

// Fetch favorites from server and render them
async function fetchFavorites() {
  const listEl = document.getElementById('favorites-list');
  const noFavEl = document.getElementById('no-favorites');
  try {
    const resp = await fetch(FAV_REST_BASE, { credentials: 'same-origin' });
    if (!resp.ok) throw new Error('Failed to load');
    const favorites = await resp.json();
    if (!favorites || favorites.length === 0) {
      noFavEl.style.display = 'block';
      if (typeof applyTranslations === 'function') applyTranslations();
      return;
    }
    favorites.forEach(item => {
      const li = document.createElement('li');
      li.className = 'favorite-item';
      const detailsDiv = document.createElement('div');
      detailsDiv.className = 'details';
      // Badge for category (if available)
      if (item.prefecture || item.city) {
        const badge = document.createElement('span');
        badge.style.marginRight = '0.4rem';
        badge.style.fontSize = '0.9rem';
        badge.textContent = `${item.prefecture || ''}${item.city ? ' ' + item.city : ''}`;
        detailsDiv.appendChild(badge);
      }
      // Title (event name)
      const title = document.createElement('a');
      title.textContent = item.name || '(no title)';
      title.href = item.url || '#';
      title.target = '_blank';
      title.rel = 'noopener';
      detailsDiv.appendChild(title);
      // Date
      if (item.date) {
        const date = document.createElement('p');
        date.textContent = item.date;
        date.style.margin = '0.2rem 0';
        detailsDiv.appendChild(date);
      }
      // Location (prefecture/city)
      if (item.location) {
        const address = document.createElement('p');
        address.textContent = item.location;
        address.style.margin = '0';
        detailsDiv.appendChild(address);
      }
      const removeBtn = document.createElement('button');
      removeBtn.className = 'remove-btn';
      try {
        const lang = typeof getUserLang === 'function' ? getUserLang() : 'ja';
        removeBtn.textContent = (translations && translations[lang] && translations[lang].delete) || '削除';
      } catch (err) {
        removeBtn.textContent = '削除';
      }
      removeBtn.addEventListener('click', () => {
        toggleFavorite(item.target_type, item.target_id, li);
      });
      li.appendChild(detailsDiv);
      li.appendChild(removeBtn);
      listEl.appendChild(li);
    });
    // Apply translations for the page
    if (typeof applyTranslations === 'function') applyTranslations();
  } catch (err) {
    // Show no favorites if error occurs
    noFavEl.style.display = 'block';
    if (typeof applyTranslations === 'function') applyTranslations();
    console.error('Failed to fetch favorites:', err);
  }
}

// Toggle favorite state on server and update DOM
function toggleFavorite(targetType, targetId, li) {
  // Use REST API to remove favourite
  fetch(`${FAV_REST_BASE}/${encodeURIComponent(targetType)}/${targetId}`, {
    method: 'DELETE',
    credentials: 'same-origin'
  }).then(() => {
    // Remove the item from DOM
    li.remove();
    // If list is empty, show no-favorites message
    if (!document.querySelector('.favorite-item')) {
      const noFavEl = document.getElementById('no-favorites');
      if (noFavEl) noFavEl.style.display = 'block';
    }
  }).catch(() => {
    // Ignore errors for toggle
  });
}

document.addEventListener('DOMContentLoaded', () => {
  try { trackFavoritesView(); } catch (err) {}
  requireLogin();
  fetchFavorites();
});