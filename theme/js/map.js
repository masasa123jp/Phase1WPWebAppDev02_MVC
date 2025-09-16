/*
  map.js – イベントマップ描画

  CSVから生成したJSONファイルを読み込み、Google Maps上にマーカーを表示します。
  各マーカーにはイベント情報を表示するInfoWindowを紐付け、お気に入り登録
  ボタンでローカルストレージへの保存が行えます。
*/

// グローバル変数
let map;
let infoWindow;
// 全マーカーリストとカテゴリ状態
let markersList = [];
// 選択されたカテゴリを管理する集合。空の場合はすべて表示
const selectedCategories = new Set();

/**
 * カテゴリごとの表示色を共通定義します。イベント・スポットの両方で使用される
 * 色をここにまとめることで、Google と HERE で重複定義されていた配色を
 * 一元管理します。キーはカテゴリー名、値は16進カラーコードです。
 */
const categoryColors = {
  event: '#FFC72C',        // イベント：黄色
  restaurant: '#E74C3C',   // レストラン：赤
  hotel: '#8E44AD',        // ホテル：紫
  activity: '#3498DB',     // アクティビティ：青
  museum: '#27AE60',       // 博物館：緑
  facility: '#95A5A6',     // 施設：グレー
  // スポットはイベントと区別するためオレンジ系を使用
  spot: '#E67E22'
};

/**
 * 現在の検索語句。検索ボックスでユーザーが入力した文字列を保持します。
 * 空文字列の場合は検索フィルターは適用されません。
 */
let searchTerm = '';
// eventsData は data/events.js で定義されるグローバル変数を参照
// eventsData 変数は data/events.js でグローバルに提供されます。

/*
 * デフォルト中心と表示半径の定数を定義します。ユーザーが言語を切り替えた際にも
 * 常に同じ地点（池袋小学校）を中心に 1km の円を表示できるようにするため、
 * 座標と半径をここで固定しておきます。Wikipedia によると池袋小学校の座標は
 * 北緯35.7379528度・東経139.7098528度です【909400092082427†L121-L124】。
 */
const DEFAULT_CENTER = { lat: 35.7379528, lng: 139.7098528 };
// 半径10km（メートル単位）に変更しました
const DEFAULT_RADIUS_M = 10000;
// デフォルト円オブジェクトを保持するための変数（再利用のため）
let defaultCircleGoogle = null;
let defaultCircleHere = null;

// =====================================
// SNS共有および推薦読み込みヘルパー関数
//
// Twitter共有：intentツイートURLを新規ウィンドウで開きます。
function shareOnTwitter(url, text) {
  try {
    const shareUrl = 'https://twitter.com/intent/tweet?text=' + encodeURIComponent(text) + '&url=' + encodeURIComponent(url);
    window.open(shareUrl, '_blank', 'noopener');
  } catch (e) {
    console.error('shareOnTwitter error', e);
  }
}

// Instagram共有：Web Share APIがあれば利用し、無ければクリップボードにコピーします。
// copyMsg はコピー完了後に表示するメッセージです。
function shareOnInstagram(url, text, copyMsg) {
  const fullText = text + ' ' + url;
  // Web Share API
  if (navigator.share) {
    navigator.share({ title: text, text: text, url: url }).catch(() => {
      // fallback to clipboard
      try {
        navigator.clipboard.writeText(fullText).then(() => {
          alert(copyMsg);
        }).catch(() => {
          alert(copyMsg);
        });
      } catch (e) {
        alert(copyMsg);
      }
    });
  } else {
    // Clipboard API
    try {
      if (navigator.clipboard) {
        navigator.clipboard.writeText(fullText).then(() => {
          alert(copyMsg);
        }).catch(() => {
          alert(copyMsg);
        });
      } else {
        // Fallback: prompt user to copy manually
        window.prompt(copyMsg, fullText);
      }
    } catch (e) {
      window.prompt(copyMsg, fullText);
    }
  }
}

// LINE共有：LINEのシェアURLを新規ウィンドウで開きます。
// text は投稿メッセージ、url は共有するリンクです。
function shareOnLine(url, text) {
  try {
    // LINE It! 共有URL
    const shareUrl = 'https://social-plugins.line.me/lineit/share?url=' + encodeURIComponent(url) + '&text=' + encodeURIComponent(text);
    window.open(shareUrl, '_blank', 'noopener');
  } catch (e) {
    console.error('shareOnLine error', e);
  }
}

// 推薦イベントをロードして UI に表示します。地図が初期化された後に呼び出されます。
async function loadRecommendations() {
  try {
    const container = document.getElementById('recommend-list');
    const titleEl = document.querySelector('#recommend-section h3');
    if (!container) return;
    // REST API からおすすめイベントを取得。limit=5 はデフォルト。
    const resp = await fetch('/wp-json/roro/v1/recommend-events');
    if (!resp.ok) return;
    const data = await resp.json();
    if (!Array.isArray(data)) return;
    container.innerHTML = '';
    // 翻訳を取得
    const lang = typeof getUserLang === 'function' ? getUserLang() : 'ja';
    const t = (window.translations && window.translations[lang]) || {};
    const viewLabel = t.view_details || '詳細を見る';
    // カードを生成
    data.forEach(item => {
      const card = document.createElement('div');
      card.className = 'recommend-card';
      // シンプルなカードUI：ボーダーと内余白
      card.style.border = '1px solid #ccc';
      card.style.borderRadius = '8px';
      card.style.padding = '0.6rem';
      card.style.backgroundColor = '#fff';
      card.style.width = '200px';
      card.style.boxShadow = '0 2px 4px rgba(0,0,0,0.1)';
      // 内容を構築
      const reasonText = item.reason ? `<p style="margin:0 0 0.3rem 0;font-size:0.75rem;color:#555;">${item.reason}</p>` : '';
      card.innerHTML = `
        <h4 style="margin:0 0 0.3rem 0;font-size:1rem;color:#1F497D;">${item.name}</h4>
        ${reasonText}
        <button class="recommend-view" style="margin-top:0.4rem;background:#1F497D;color:#fff;border:none;padding:0.3rem 0.6rem;font-size:0.8rem;border-radius:4px;cursor:pointer;">${viewLabel}</button>
      `;
      container.appendChild(card);
      const btn = card.querySelector('.recommend-view');
      btn.addEventListener('click', () => {
        try {
          // 対応するイベントオブジェクトを検索。id または name で照合します。
          let eventObj = null;
          if (window.localEvents && Array.isArray(window.localEvents)) {
            // 一致するid
            eventObj = window.localEvents.find(ev => (ev.id && (ev.id == item.id || ev.id == item.event_id)) || ev.name === item.name);
          }
          if (eventObj) {
            // 座標がある場合は地図を移動
            if (eventObj.lat && eventObj.lon) {
              const lat = parseFloat(eventObj.lat);
              const lng = parseFloat(eventObj.lon);
              if (!isNaN(lat) && !isNaN(lng)) {
                map.setCenter({ lat: lat, lng: lng });
                map.setZoom(14);
              }
            }
            // マーカーをトリガーして詳細表示
            const entry = markersList.find(m => m.name === eventObj.name);
            if (entry && entry.marker) {
              google.maps.event.trigger(entry.marker, 'click');
            }
          }
          // クリック計測 API を呼び出し
          const evId = item.id || item.event_id || (eventObj && (eventObj.id || eventObj.event_id)) || null;
          if (evId) {
            fetch('/wp-json/roro/v1/recommend-events-hit', {
              method: 'POST',
              headers: { 'Content-Type': 'application/json' },
              body: JSON.stringify({ event_id: evId })
            }).catch(() => {});
          }
        } catch (err) {
          console.warn('recommend view error', err);
        }
      });
    });
    // 推薦カード生成後に翻訳を再適用
    if (typeof applyTranslations === 'function') {
      applyTranslations();
    }
  } catch (e) {
    console.error('loadRecommendations error', e);
  }
}

// ===== Analytics Helpers =====
// Ensure there is a session identifier for analytics
function roroEnsureSession() {
  try {
    if (!localStorage.getItem('roro_sid')) {
      const uuid = (typeof crypto !== 'undefined' && crypto.randomUUID) ? crypto.randomUUID() : (Date.now() + '-' + Math.random());
      localStorage.setItem('roro_sid', uuid);
    }
  } catch (e) {}
}

// Send JSON payload via Beacon API with fallback
function roroSendBeaconJSON(url, payload) {
  try {
    const blob = new Blob([JSON.stringify(payload)], { type: 'application/json' });
    navigator.sendBeacon(url, blob);
  } catch (e) {
    fetch(url, { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(payload) }).catch(() => {});
  }
}

// Send a view event for the map page
function trackMapView() {
  roroEnsureSession();
  try {
    roroSendBeaconJSON('/wp-json/roro-analytics/v1/view', {
      issue_id: 'map',
      page_id: null,
      dwell_ms: null,
      session_id: localStorage.getItem('roro_sid'),
      referer: document.referrer || null,
      device: /Mobi/i.test(navigator.userAgent) ? 'mobile' : 'pc',
      lang: (navigator.language || 'ja')
    });
  } catch (e) {}
}

/**
 * Google Maps 上でデフォルトの中心と半径1kmの円を適用し、円の外接境界に
 * カメラをフィットさせます。存在している円は先に削除し、再描画します。
 * @param {google.maps.Map} map Google Maps のマップインスタンス
 */
function applyDefaultViewGoogle(map) {
  // Google ライブラリがロードされているかを再確認
  if (typeof google === 'undefined' || !google.maps) {
    return;
  }
  // 既存の円を除去
  if (defaultCircleGoogle) {
    try {
      defaultCircleGoogle.setMap(null);
    } catch (e) {
      /* ignore */
    }
    defaultCircleGoogle = null;
  }
  // 新たに円を生成してマップに追加
  defaultCircleGoogle = new google.maps.Circle({
    center: DEFAULT_CENTER,
    radius: DEFAULT_RADIUS_M,
    strokeColor: '#1F497D',
    strokeOpacity: 0.9,
    strokeWeight: 1,
    fillColor: '#FFC72C',
    fillOpacity: 0.15,
    clickable: false
  });
  defaultCircleGoogle.setMap(map);
  // 円の境界を取得してビューをフィットさせる
  const bounds = defaultCircleGoogle.getBounds();
  if (bounds) {
    map.fitBounds(bounds);
    // 拡大しすぎを防ぐためズームの上限を設定
    const maxZoom = 16;
    const listener = google.maps.event.addListenerOnce(map, 'idle', () => {
      if (map.getZoom() > maxZoom) {
        map.setZoom(maxZoom);
      }
    });
    // 念のためリスナーを一定時間後に削除
    setTimeout(() => {
      try {
        google.maps.event.removeListener(listener);
      } catch (err) {
        /* ignore */
      }
    }, 2000);
  } else {
    // 境界が取得できない場合はデフォルト中心とズームを設定
    map.setCenter(DEFAULT_CENTER);
    map.setZoom(15);
  }
}

/**
 * HERE Maps 上でデフォルトの中心と半径1kmの円を適用し、円の外接境界に
 * カメラをフィットさせます。存在している円は先に削除し、再描画します。
 * @param {H.Map} map HERE Maps のマップインスタンス
 * @param {Object} H HERE Maps の名前空間
 */
function applyDefaultViewHere(map, H) {
  // 既存の円を除去
  if (defaultCircleHere) {
    try {
      map.removeObject(defaultCircleHere);
    } catch (e) {
      /* ignore */
    }
    defaultCircleHere = null;
  }
  // 半径1kmの円オブジェクトを生成して追加
  defaultCircleHere = new H.map.Circle(
    { lat: DEFAULT_CENTER.lat, lng: DEFAULT_CENTER.lng },
    DEFAULT_RADIUS_M,
    {
      style: {
        lineColor: '#1F497D',
        lineWidth: 1,
        strokeColor: '#1F497D',
        fillColor: 'rgba(255,199,44,0.15)'
      }
    }
  );
  map.addObject(defaultCircleHere);
  // 円のバウンディングボックスを取得してビューをフィット
  const bounds = defaultCircleHere.getBoundingBox();
  if (bounds) {
    map.getViewModel().setLookAtData({ bounds: bounds });
    const maxZoom = 16;
    if (map.getZoom && map.getZoom() > maxZoom) {
      map.setZoom(maxZoom);
    }
  } else {
    map.setCenter(DEFAULT_CENTER);
    map.setZoom(15);
  }
}

/**
 * Google Maps の初期化関数。API読み込み時にコールバックされます。
 * 日本語・英語・韓国語のモードでは Google Maps を使用します。
 */
// Initialize the Google map. This function is declared as async so that we can
// await asynchronous operations (e.g. fetching events via REST) before
// proceeding with marker creation. The Google Maps API will call this
// function after the script is loaded.
async function initGoogleMap() {
  // カテゴリの色定義はグローバル categoryColors を使用します。
  // Google Maps ライブラリが読み込まれているかチェック
  // ネットワークの問題や API キーの未設定により google オブジェクトが存在しない場合、
  // ここで処理を中断してエラーメッセージを出力します。
  if (typeof google === 'undefined' || !google.maps) {
    console.error('Google Maps API is not loaded or google is undefined.');
    return;
  }
  // ログイン状態を確認
  requireLogin();

  // === イベントデータの REST 取得 ===
  // Phase1 での改修により、イベントは静的な events.js ではなく REST API
  // `/wp-json/roro/v1/events` から取得します。従来は同期的な
  // XMLHttpRequest を使用していましたが、UI ブロッキングを避けるため
  // Promise ベースの fetch API に置き換えました。取得に失敗した場合は
  // 既存の window.eventsData をそのまま使用します。
  try {
    await fetch('/wp-json/roro/v1/events', { method: 'GET' })
      .then(res => {
        if (!res.ok) {
          throw new Error('Network response was not ok');
        }
        return res.json();
      })
      .then(data => {
        if (Array.isArray(data)) {
          window.eventsData = data;
        }
      })
      .catch(err => {
        console.warn('Failed to fetch events via REST API; fallback to static events.js', err);
      });
  } catch (ex) {
    console.warn('Events fetch error', ex);
  }
  // デフォルトの中心（池袋小学校付近）。言語切替に関わらず固定とします。
  const defaultCenter = DEFAULT_CENTER;
  // マップスタイル：ブランドカラーに合わせて淡い配色に
  const styles = [
    { elementType: 'geometry', stylers: [{ color: '#F5F5F5' }] },
    { elementType: 'labels.icon', stylers: [{ visibility: 'off' }] },
    { elementType: 'labels.text.fill', stylers: [{ color: '#616161' }] },
    { elementType: 'labels.text.stroke', stylers: [{ color: '#F5F5F5' }] },
    {
      featureType: 'administrative.land_parcel',
      elementType: 'labels.text.fill',
      stylers: [{ color: '#BDBDBD' }]
    },
    {
      featureType: 'poi',
      elementType: 'geometry',
      stylers: [{ color: '#eeeeee' }]
    },
    {
      featureType: 'poi',
      elementType: 'labels.text.fill',
      stylers: [{ color: '#757575' }]
    },
    {
      featureType: 'poi.park',
      elementType: 'geometry',
      stylers: [{ color: '#e5f4e8' }]
    },
    {
      featureType: 'poi.park',
      elementType: 'labels.text.fill',
      stylers: [{ color: '#388e3c' }]
    },
    {
      featureType: 'road',
      elementType: 'geometry',
      stylers: [{ color: '#ffffff' }]
    },
    {
      featureType: 'road.arterial',
      elementType: 'labels.text.fill',
      stylers: [{ color: '#757575' }]
    },
    {
      featureType: 'road.highway',
      elementType: 'geometry',
      stylers: [{ color: '#dadada' }]
    },
    {
      featureType: 'road.highway',
      elementType: 'labels.text.fill',
      stylers: [{ color: '#616161' }]
    },
    {
      featureType: 'transit',
      elementType: 'geometry',
      stylers: [{ color: '#f2f2f2' }]
    },
    {
      featureType: 'transit.station',
      elementType: 'labels.text.fill',
      stylers: [{ color: '#9e9e9e' }]
    },
    {
      featureType: 'water',
      elementType: 'geometry',
      stylers: [{ color: '#cddffb' }]
    },
    {
      featureType: 'water',
      elementType: 'labels.text.fill',
      stylers: [{ color: '#9e9e9e' }]
    }
  ];
  // マップオプション
  map = new google.maps.Map(document.getElementById('map'), {
    center: defaultCenter,
    // 初期ズームは大きめに設定し、後で applyDefaultViewGoogle() で調整します
    zoom: 14,
    styles: styles,
    mapTypeControl: false,
    fullscreenControl: false
  });
  infoWindow = new google.maps.InfoWindow();
  // data/events.js にて定義された eventsData を利用してマーカーを生成
  const localEvents = Array.isArray(window.eventsData) ? window.eventsData.slice() : [];
    // 池袋4丁目付近にダミーの施設を生成し、マーカーとして表示するために配列に追加
    // 200 件のダミー施設を生成する。generateDummyEvents では正規分布に近い乱数を利用
    // し、都心に近いほど密度が高くなるよう調整しています。
    const dummyEvents = generateDummyEvents(200);
  // const 配列は再代入できないが、内容の push は可能
  localEvents.push(...dummyEvents);
  // 推薦UIで参照できるように、取得済みイベントをグローバルに公開します。
  window.localEvents = localEvents;
  if (localEvents.length === 0) {
    console.warn('イベントデータが空です');
    return;
  }
  const bounds = new google.maps.LatLngBounds();
  // カスタムマーカーの設定：雫型のシンボルを使用してブランドカラーに
  // 雫型パス（上部が丸く、下に尖るデザイン）
  const markerPath = 'M0,0 C8,0 8,-12 0,-20 C-8,-12 -8,0 0,0 Z';
  const markerSymbol = {
    path: markerPath,
    fillColor: '#FFC72C',
    fillOpacity: 0.9,
    strokeColor: '#1F497D',
    strokeWeight: 1,
    scale: 1
  };

  /*
   * カスタムアイコンを生成するためのヘルパー関数。
   * 現在は従来の Marker の icon オプションで利用するため、SVG パスと色を指定します。
   * @param {string} color 塗りつぶし色
   * @returns {Object} google.maps.Symbol 互換のオブジェクト
   */
  function createMarkerIcon(color) {
    return {
      path: markerPath,
      fillColor: color,
      fillOpacity: 0.9,
      strokeColor: '#1F497D',
      strokeWeight: 1,
      scale: 1
    };
  }
    // グローバル変数 "event" との競合を避けるため、コールバックの引数名を
  // eventItem とする。ブラウザによっては window.event が const として
  // 定義されており、再代入しようとすると "Assignment to constant variable"
  // エラーが発生する可能性があるためである。
    localEvents.forEach((eventItem, index) => {
    const position = { lat: eventItem.lat, lng: eventItem.lon };
        // カテゴリを割り当て。既存のイベントには 'event' を設定し、ダミーにはランダムカテゴリを設定
        if (!eventItem.category) {
          if (index < (window.eventsData ? window.eventsData.length : 0)) {
            eventItem.category = 'event';
          } else {
            // ランダムにカテゴリを選択（eventを除外）。
            // 交通機関・薬局・ATM カテゴリは仕様により除外しました。
            const catOptions = ['restaurant','hotel','activity','museum','facility'];
            eventItem.category = catOptions[Math.floor(Math.random() * catOptions.length)];
          }
        }
    // カテゴリ別アイコン色を決定。共通定義 categoryColors から取得します。
    const iconColor = categoryColors[eventItem.category] || '#FFC72C';
    // 従来の google.maps.Marker を使用してマーカーを作成します。
    // AdvancedMarkerElement は mapId が必要で setVisible メソッドが無いなど、
    // 本アプリケーションでは適切に動作しないため使用しません。
    const marker = new google.maps.Marker({
      position: position,
      map: map,
      title: eventItem.name,
      icon: createMarkerIcon(iconColor)
    });
    bounds.extend(position);
    // markersList に格納。検索用に名前と住所も保存します。
    markersList.push({
      marker,
      category: eventItem.category,
      name: eventItem.name || '',
      address: eventItem.address || ''
    });
    // click イベントを登録
    marker.addListener('click', (...args) => {
      // InfoWindowの内容を動的に生成
      const dateStr = eventItem.date && eventItem.date !== 'nan' ? `<p>${eventItem.date}</p>` : '';
      const addressStr = eventItem.address && eventItem.address !== 'nan' ? `<p>${eventItem.address}</p>` : '';
      const linkStr = eventItem.url && eventItem.url !== 'nan' ? `<p><a href="${eventItem.url}" target="_blank" rel="noopener">詳細を見る</a></p>` : '';
      // 保存ボタンとメニュー
      const menuHtml = `
        <div class="save-menu" style="display:none;position:absolute;top:110%;left:0;background:#fff;border:1px solid #ccc;border-radius:6px;padding:0.4rem;box-shadow:0 2px 6px rgba(0,0,0,0.2);width:130px;font-size:0.8rem;">
          <div class="save-option" data-list="favorite" style="cursor:pointer;padding:0.2rem 0.4rem;display:flex;align-items:center;gap:0.3rem;"><span>❤️</span><span>お気に入り</span></div>
          <div class="save-option" data-list="want" style="cursor:pointer;padding:0.2rem 0.4rem;display:flex;align-items:center;gap:0.3rem;"><span>🚩</span><span>行ってみたい</span></div>
          <div class="save-option" data-list="plan" style="cursor:pointer;padding:0.2rem 0.4rem;display:flex;align-items:center;gap:0.3rem;"><span>🧳</span><span>旅行プラン</span></div>
          <div class="save-option" data-list="star" style="cursor:pointer;padding:0.2rem 0.4rem;display:flex;align-items:center;gap:0.3rem;"><span>⭐</span><span>スター付き</span></div>
        </div>`;
      // 翻訳辞書から各テキストを取得
      const lang = typeof getUserLang === 'function' ? getUserLang() : 'ja';
      const t = (window.translations && window.translations[lang]) || {};
      const saveLabel = t.save || '保存';
      const viewDetailsLabel = t.view_details || '詳細を見る';
      const saveFavorite = t.save_favorite || 'お気に入り';
      const saveWant = t.save_want || '行ってみたい';
      const savePlan = t.save_plan || '旅行プラン';
      const saveStar = t.save_star || 'スター付き';

      // === SNS共有用の翻訳テキスト ===
      const shareLabel = t.share || '共有';
      const shareXLabel = t.share_x || 'Xで共有';
      const shareInstagramLabel = t.share_instagram || 'Instagramで共有';
      const shareLineLabel = t.share_line || 'LINEで共有';
      const copyMsg = t.copy_message || 'リンクをコピーしました。Instagramで共有してください。';
      const menuHtmlTrans = `
        <div class="save-menu" style="display:none;position:absolute;top:110%;left:0;background:#fff;border:1px solid #ccc;border-radius:6px;padding:0.4rem;box-shadow:0 2px 6px rgba(0,0,0,0.2);width:130px;font-size:0.8rem;">
          <div class="save-option" data-list="favorite" style="cursor:pointer;padding:0.2rem 0.4rem;display:flex;align-items:center;gap:0.3rem;"><span>❤️</span><span>${saveFavorite}</span></div>
          <div class="save-option" data-list="want" style="cursor:pointer;padding:0.2rem 0.4rem;display:flex;align-items:center;gap:0.3rem;"><span>🚩</span><span>${saveWant}</span></div>
          <div class="save-option" data-list="plan" style="cursor:pointer;padding:0.2rem 0.4rem;display:flex;align-items:center;gap:0.3rem;"><span>🧳</span><span>${savePlan}</span></div>
          <div class="save-option" data-list="star" style="cursor:pointer;padding:0.2rem 0.4rem;display:flex;align-items:center;gap:0.3rem;"><span>⭐</span><span>${saveStar}</span></div>
        </div>`;
      const linkHtml = linkStr ? `<p><a href="${eventItem.url}" target="_blank" rel="noopener">${viewDetailsLabel}</a></p>` : '';
      // SNS共有ボタンのHTMLを定義します。色や配置はブランドカラーに合わせています。
      const shareHtml = `
        <div class="share-wrapper" style="margin-top:0.5rem; display:flex; gap:0.4rem; align-items:center; font-size:0.8rem;">
          <span style="color:#1F497D;">${shareLabel}:</span>
          <button class="share-btn-x" style="background-color:#1DA1F2;color:#fff;border:none;padding:0.2rem 0.4rem;font-size:0.8rem;border-radius:4px;cursor:pointer;">${shareXLabel}</button>
          <button class="share-btn-instagram" style="background-color:#E1306C;color:#fff;border:none;padding:0.2rem 0.4rem;font-size:0.8rem;border-radius:4px;cursor:pointer;">${shareInstagramLabel}</button>
          <button class="share-btn-line" style="background-color:#06C755;color:#fff;border:none;padding:0.2rem 0.4rem;font-size:0.8rem;border-radius:4px;cursor:pointer;">${shareLineLabel}</button>
        </div>`;
      const content = `
        <div class="info-content" style="position:relative;">
          <h3 style="margin:0 0 0.2rem 0;">${eventItem.name}</h3>
          ${dateStr}
          ${addressStr}
          ${linkHtml}
          ${shareHtml}
          <div class="save-wrapper" style="position:relative;display:inline-block;margin-top:0.5rem;">
            <button class="save-btn" data-index="${index}" style="background-color:transparent;border:none;color:#1F497D;font-size:0.9rem;cursor:pointer;display:flex;align-items:center;gap:0.3rem;">
              <span class="save-icon">🔖</span><span>${saveLabel}</span>
            </button>
            ${menuHtmlTrans}
          </div>
        </div>`;
      infoWindow.setContent(content);
      // InfoWindow を表示
      // 従来の google.maps.Marker を使用しているため、第二引数にマーカーを渡す形式を使用します。
      infoWindow.open(map, marker);
      // InfoWindow内のボタンにイベントを付与するため、DOMReadyで監視
      google.maps.event.addListenerOnce(infoWindow, 'domready', () => {
        // 保存ボタンとメニューの操作
        const saveBtn = document.querySelector('.save-btn');
        const saveMenu = document.querySelector('.save-menu');
        if (saveBtn && saveMenu) {
          saveBtn.addEventListener('click', (e) => {
            e.stopPropagation();
            // メニュー表示をトグル
            saveMenu.style.display = saveMenu.style.display === 'none' ? 'block' : 'none';
          });
          saveMenu.querySelectorAll('.save-option').forEach(opt => {
            opt.addEventListener('click', (ev) => {
              const listType = opt.getAttribute('data-list');
              addToFavorites(localEvents[index], listType);
              saveMenu.style.display = 'none';
            });
          });
        }

        // === SNS共有ボタンのイベント登録 ===
        try {
          const btnX = document.querySelector('.share-btn-x');
          const btnInstagram = document.querySelector('.share-btn-instagram');
          const btnLine = document.querySelector('.share-btn-line');
          if (btnX) {
            btnX.addEventListener('click', (e) => {
              e.stopPropagation();
              // Twitter共有: イベント名とURLを含めて共有
              shareOnTwitter(eventItem.url || window.location.href, eventItem.name);
            });
          }
          if (btnInstagram) {
            btnInstagram.addEventListener('click', (e) => {
              e.stopPropagation();
              // Instagram共有: Web Share APIまたはクリップボード経由
              shareOnInstagram(eventItem.url || window.location.href, eventItem.name, copyMsg);
            });
          }
          if (btnLine) {
            btnLine.addEventListener('click', (e) => {
              e.stopPropagation();
              // LINE共有: LINEシェアURLを使用
              shareOnLine(eventItem.url || window.location.href, eventItem.name);
            });
          }
        } catch (shareErr) {
          console.warn('Share button error', shareErr);
        }
        // 吹き出し内に動的に挿入した要素にも翻訳を適用する
        if (typeof applyTranslations === 'function') applyTranslations();
      });
    });
  });
  // ユーザー住所によって中心とズームを調整
  let userCenter = null;
  let userZoom = 6;
  try {
    const user = JSON.parse(sessionStorage.getItem('user')) || {};
    if (user.address) {
      // 東京都豊島区池袋4丁目付近の住所を検出。"池袋" または "豊島区" を含むかで判定する。
      if (user.address.includes('池袋') || user.address.includes('豊島区')) {
        // 池袋4丁目付近の概算座標
        // DEFAULT_CENTER を利用して統一します（同エリアのため）。
        userCenter = { lat: DEFAULT_CENTER.lat, lng: DEFAULT_CENTER.lng };
        userZoom = 11; // 約20kmの範囲を表示
      }
    }
  } catch (e) {
    /* ignore */
  }
  if (userCenter) {
    map.setCenter(userCenter);
    map.setZoom(userZoom);
  } else {
    // ユーザーの住所が無い場合、全マーカーが見えるように調整
    map.fitBounds(bounds);
  }

  // デフォルト中心＋1kmのビューを適用
  applyDefaultViewGoogle(map);

  /**
   * Virtualise marker rendering to improve INP.  Rendering hundreds of markers
   * simultaneously can cause jank during panning and zooming.  Instead, only
   * display markers that fall within the current viewport bounds.  Each
   * marker remains in the global markersList; the map reference is toggled
   * between the active map and null to hide or show it.
   */
  function updateMarkerVisibility() {
    try {
      const b = map.getBounds();
      if (!b) return;
      markersList.forEach(item => {
        const m = item.marker;
        // Some markers may not yet be initialised
        if (!m || typeof m.getPosition !== 'function') return;
        const pos = m.getPosition();
        const visible = b.contains(pos);
        // Only set the map when visibility changes to reduce map API overhead
        if (visible && m.getMap() !== map) {
          m.setMap(map);
        } else if (!visible && m.getMap() !== null) {
          m.setMap(null);
        }
      });
    } catch (e) {
      // Ignore errors (bounds may be undefined during initialisation)
    }
  }
  let visibilityTimeout = null;
  google.maps.event.addListener(map, 'idle', function() {
    if (visibilityTimeout) clearTimeout(visibilityTimeout);
    visibilityTimeout = setTimeout(updateMarkerVisibility, 150);
  });
  // Trigger initial visibility update after a brief delay to allow markers
  // created during initialisation to populate markersList.
  setTimeout(updateMarkerVisibility, 500);

  // 初期化完了後におすすめイベントを読み込み、推薦セクションを更新します。
  try {
    loadRecommendations();
  } catch (e) {
    console.error('loadRecommendations call failed', e);
  }

  // ================= スポットマーカーの描画 =================
  // 公開スポットを REST API から取得し、カテゴリ 'spot' として Google マップに追加します。
  // events のマーカー作成後に呼び出し、バウンディングボックスとマーカーリストを更新します。
  try {
    fetch('/wp-json/roro/v1/spots')
      .then(resp => resp.json())
      .then(spots => {
        if (Array.isArray(spots)) {
          spots.forEach((spot) => {
            const lat = parseFloat(spot.latitude);
            const lng = parseFloat(spot.longitude);
            if (!isNaN(lat) && !isNaN(lng)) {
              const position = { lat: lat, lng: lng };
              // スポット用マーカーを作成。カテゴリーカラーを参照し、fallback でオレンジ。
              const marker = new google.maps.Marker({
                position: position,
                map: map,
                title: spot.name,
                icon: createMarkerIcon(categoryColors['spot'] || '#E67E22')
              });
              // バウンディングボックスに含める
              bounds.extend(position);
              // マーカリストに追加。検索用に名前と住所も保存します。
              markersList.push({
                marker,
                category: 'spot',
                name: spot.name || '',
                address: spot.address || ''
              });
              // クリックハンドラ：詳細表示とお気に入り登録
              marker.addListener('click', () => {
                // 翻訳用辞書を取得
                const lang2 = typeof getUserLang === 'function' ? getUserLang() : 'ja';
                const t2 = (window.translations && window.translations[lang2]) || {};
                const saveLabel2 = t2.save || '保存';
                const viewDetailsLabel2 = t2.view_details || '詳細を見る';
                const addressStr = spot.address && spot.address !== 'nan' ? `<p>${spot.address}</p>` : '';
                const linkStr = spot.url && spot.url !== 'nan' ? `<p><a href="${spot.url}" target="_blank" rel="noopener">${viewDetailsLabel2}</a></p>` : '';
                // InfoWindow のコンテンツ組み立て
                const spotContent = `
                  <div class="info-content" style="position:relative;">
                    <h3 style="margin:0 0 0.2rem 0;">${spot.name}</h3>
                    ${addressStr}
                    ${linkStr}
                    <button id="spot-fav-btn" style="background-color:transparent;border:none;color:#1F497D;font-size:0.9rem;cursor:pointer;margin-top:0.5rem;display:flex;align-items:center;gap:0.3rem;">
                      <span>🔖</span><span>${saveLabel2}</span>
                    </button>
                  </div>`;
                infoWindow.setContent(spotContent);
                infoWindow.open(map, marker);
                google.maps.event.addListenerOnce(infoWindow, 'domready', () => {
                  const btn = document.getElementById('spot-fav-btn');
                  if (btn) {
                    btn.addEventListener('click', (ev) => {
                      ev.stopPropagation();
                      addSpotFavorite(spot);
                    });
                  }
                  // 吹き出し内の動的要素にも翻訳を適用
                  if (typeof applyTranslations === 'function') applyTranslations();
                });
              });
            }
          });
          // スポットを追加後、カテゴリバー生成が済んでいれば可視状態を更新
          updateMarkerVisibility();
        }
      })
      .catch(() => {
        console.warn('Failed to load spots');
      });
  } catch (e) {
    console.warn('Failed to load spots', e);
  }

  // 周辺表示ボタンに機能を追加
  const resetBtn = document.getElementById('reset-view-btn');
  if (resetBtn) {
    resetBtn.addEventListener('click', () => {
      // デフォルト中心＋半径1kmに戻す
      applyDefaultViewGoogle(map);
    });
  }

  // カテゴリフィルタバーを初期化
  createCategoryButtons();
  // 初期表示は全てのマーカーを表示
  updateMarkerVisibility();
}

/**
 * カテゴリフィルタバーを生成し、ボタンにクリックイベントを設定します。
 */
function createCategoryButtons() {
  const bar = document.getElementById('category-bar');
  if (!bar) return;
  // 定義したカテゴリリスト
  // 対応カテゴリの一覧。表示文字列は翻訳辞書から取得します。
  const cats = [
    { key: 'event', emoji: '🎪' },
    { key: 'restaurant', emoji: '🍴' },
    { key: 'hotel', emoji: '🏨' },
    { key: 'activity', emoji: '🎠' },
    { key: 'museum', emoji: '🏛️' },
    { key: 'facility', emoji: '🏢' },
    // スポット用カテゴリ。猫の足跡アイコンで表現
    { key: 'spot', emoji: '🐾' }
  ];
  cats.forEach((cat) => {
    const btn = document.createElement('button');
    btn.className = 'filter-btn';
    btn.setAttribute('data-category', cat.key);
    const emojiSpan = document.createElement('span');
    emojiSpan.textContent = cat.emoji;
    const labelSpan = document.createElement('span');
    // 翻訳キーを設定して applyTranslations で更新できるようにする
    const i18nKey = 'cat_' + cat.key;
    labelSpan.setAttribute('data-i18n-key', i18nKey);
    // 初期表示を設定（ユーザー言語に合わせる）
    try {
      const lang = typeof getUserLang === 'function' ? getUserLang() : 'ja';
      labelSpan.textContent = (window.translations && window.translations[lang] && window.translations[lang][i18nKey]) || cat.key;
    } catch (e) {
      labelSpan.textContent = cat.key;
    }
    btn.appendChild(emojiSpan);
    btn.appendChild(labelSpan);
    btn.addEventListener('click', () => {
      const key = btn.getAttribute('data-category');
      if (btn.classList.contains('active')) {
        btn.classList.remove('active');
        selectedCategories.delete(key);
      } else {
        btn.classList.add('active');
        selectedCategories.add(key);
      }
      updateMarkerVisibility();
    });
    bar.appendChild(btn);
  });
  // 初期化後に翻訳を適用してボタンラベルを更新
  if (typeof applyTranslations === 'function') applyTranslations();
}

/**
 * 選択されたカテゴリに基づいてマーカーの表示・非表示を更新します。
 */
function updateMarkerVisibility() {
  // 検索語を小文字に変換してトリム
  const term = (searchTerm || '').toString().toLowerCase().trim();
  markersList.forEach((item) => {
    // カテゴリ選択の有無を判定
    const inCategory = selectedCategories.size === 0 || selectedCategories.has(item.category);
    // 検索フィルターの有無を判定。検索語が空の場合は true、
    // そうでない場合は名称または住所に含まれているかを確認します。
    let matchesSearch = true;
    if (term) {
      const name = (item.name || '').toString().toLowerCase();
      const addr = (item.address || '').toString().toLowerCase();
      matchesSearch = name.includes(term) || addr.includes(term);
    }
    const visible = inCategory && matchesSearch;
    // Google Maps Marker には setVisible、HERE Marker には setVisibility が存在します
    if (item.marker && typeof item.marker.setVisible === 'function') {
      item.marker.setVisible(visible);
    } else if (item.marker && typeof item.marker.setVisibility === 'function') {
      item.marker.setVisibility(visible);
    } else {
      // それ以外の場合は単純にマップへの追加・削除で対応
      try {
        if (visible) {
          if (typeof map.addObject === 'function') {
            map.addObject(item.marker);
          } else if (typeof item.marker.setMap === 'function') {
            item.marker.setMap(map);
          }
        } else {
          if (typeof map.removeObject === 'function') {
            map.removeObject(item.marker);
          } else if (typeof item.marker.setMap === 'function') {
            item.marker.setMap(null);
          }
        }
      } catch (e) {
        /* ignore */
      }
    }
  });
}

/**
 * 指定されたイベントをお気に入りに追加する。
 * @param {Object} event イベントオブジェクト
 */
/**
 * イベントをユーザーのリストに保存します。リスト種別が「お気に入り」の場合は
 * REST API (/roro/v1/favorites) へ保存処理を送信し、その他の場合はローカル
 * ストレージに保存します。旧来の localStorage 専用実装を拡張し、統一した
 * インタフェースでサーバ連携を行います。
 *
 * @param {Object} eventItem イベントオブジェクト
 * @param {String} listType 保存するリストの種別（favorite, want, plan, star など）
 */
function addToFavorites(eventItem, listType = 'favorite') {
  // お気に入りリストの場合は REST API へ登録
  if (listType === 'favorite') {
    // eventItem から ID を取得。REST API では ID が必要なので、
    // event_id などの代替フィールドをフォールバックとして利用します。
    const eventId = eventItem.id || eventItem.event_id || eventItem.eventId || null;
    if (!eventId) {
      // ID が無い場合は従来のローカル保存にフォールバック
      saveFavoriteLocally(eventItem, listType);
      return;
    }
    fetch('/wp-json/roro/v1/favorites', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ target_type: 'event', target_id: eventId })
    }).then(resp => {
      if (resp.ok) {
        // サーバ側登録成功後、ローカルにも簡易キャッシュを保存します
        saveFavoriteLocally(eventItem, listType);
        // メッセージ表示
        try {
          const lang = typeof getUserLang === 'function' ? getUserLang() : 'ja';
          const t = (window.translations && window.translations[lang]) || {};
          alert(t.saved_msg || 'リストに保存しました');
        } catch (e) {
          alert('リストに保存しました');
        }
      } else {
        alert('お気に入り登録に失敗しました');
      }
    }).catch(() => {
      alert('お気に入り登録に失敗しました');
    });
    return;
  }
  // その他のリストはローカル保存のみ
  saveFavoriteLocally(eventItem, listType);
}

/**
 * ローカルストレージにイベントを保存します。重複チェックを行い、
 * ユーザーにメッセージを表示します。お気に入り以外の listType
 * のための共通ヘルパです。
 *
 * @param {Object} eventItem イベントオブジェクト
 * @param {String} listType 保存するリストの種別
 */
function saveFavoriteLocally(eventItem, listType = 'favorite') {
  let favorites;
  try {
    favorites = JSON.parse(localStorage.getItem('favorites')) || [];
  } catch (e) {
    favorites = [];
  }
  // 重複チェック（名称と座標で判定）
  const exists = favorites.some((f) => f.name === eventItem.name && f.lat === eventItem.lat && f.lon === eventItem.lon && f.listType === listType);
  if (!exists) {
    const itemToSave = { ...eventItem, listType };
    favorites.push(itemToSave);
    localStorage.setItem('favorites', JSON.stringify(favorites));
    // 翻訳されたメッセージを表示
    try {
      const lang = typeof getUserLang === 'function' ? getUserLang() : 'ja';
      const t = (window.translations && window.translations[lang]) || {};
      alert(t.saved_msg || 'リストに保存しました');
    } catch (e) {
      alert('リストに保存しました');
    }
  } else {
    try {
      const lang2 = typeof getUserLang === 'function' ? getUserLang() : 'ja';
      const t2 = (window.translations && window.translations[lang2]) || {};
      alert(t2.already_saved_msg || '既にこのリストに登録済みです');
    } catch (e) {
      alert('既にこのリストに登録済みです');
    }
  }
}

/**
 * 指定されたスポットをお気に入りに登録する（サーバ連携）。
 * target_type="spot" を指定して REST API (/roro/v1/favorites) に POST します。
 * 成功時にはローカルストレージにも簡易コピーを保存し、ユーザーにメッセージを表示します。
 *
 * @param {Object} spot スポットオブジェクト（id, name, latitude, longitude 等を含む）
 */
function addSpotFavorite(spot) {
  try {
    fetch('/wp-json/roro/v1/favorites', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ target_type: 'spot', target_id: spot.id })
    }).then(resp => {
      if (resp.ok) {
        // レスポンスに関わらずローカルストレージへも保存しておく（簡易キャッシュ）
        try {
          let favorites = JSON.parse(localStorage.getItem('favorites')) || [];
          // 重複チェック：同じidのスポットが既に存在しないか確認
          const exists = favorites.some((f) => f.target_type === 'spot' && f.target_id === spot.id);
          if (!exists) {
            favorites.push({
              target_type: 'spot',
              target_id: spot.id,
              name: spot.name,
              lat: parseFloat(spot.latitude),
              lon: parseFloat(spot.longitude),
              listType: 'favorite'
            });
            localStorage.setItem('favorites', JSON.stringify(favorites));
          }
        } catch (e) {
          /* ignore */
        }
        // 成功メッセージ
        try {
          const lang = typeof getUserLang === 'function' ? getUserLang() : 'ja';
          const t = (window.translations && window.translations[lang]) || {};
          alert(t.saved_msg || 'リストに保存しました');
        } catch (e) {
          alert('リストに保存しました');
        }
      } else {
        alert('お気に入り登録に失敗しました');
      }
    }).catch(() => {
      alert('お気に入り登録に失敗しました');
    });
  } catch (e) {
    alert('お気に入り登録に失敗しました');
  }
}

/**
 * 池袋4丁目付近を中心にランダムなダミーイベントを生成します。
 * @param {number} count 生成するイベント数
 * @returns {Array<Object>} ダミーイベントの配列
 */
function generateDummyEvents(count) {
  const results = [];
  // 基準点：池袋小学校付近の座標（DEFAULT_CENTER を利用）。
  const baseLat = DEFAULT_CENTER.lat;
  const baseLng = DEFAULT_CENTER.lng;
  // 国道16号線内の緯度経度境界（東京周辺）
  const latLowerBound = 35.5;
  const latUpperBound = 35.9;
  const lngLowerBound = 139.2;
  const lngUpperBound = 139.9;
  // 正規分布に近い乱数を生成する関数（ボックス＝ミュラー法）
  function gaussianRandom() {
    let u = 0, v = 0;
    while (u === 0) u = Math.random(); // 0 にならないように
    while (v === 0) v = Math.random();
    return Math.sqrt(-2.0 * Math.log(u)) * Math.cos(2.0 * Math.PI * v);
  }
  for (let i = 0; i < count; i++) {
    // ガウシアン分布を用いて中心から緩やかに散布
    let lat = baseLat + gaussianRandom() * 0.05; // 約5km程度の分散
    let lng = baseLng + gaussianRandom() * 0.06; // 経度方向の分散をやや広げる
    // 国道16号線内に収まるよう境界チェックを行い、外れた場合は境界内にクランプする
    if (lat < latLowerBound) lat = latLowerBound + Math.random() * 0.05;
    if (lat > latUpperBound) lat = latUpperBound - Math.random() * 0.05;
    if (lng < lngLowerBound) lng = lngLowerBound + Math.random() * 0.05;
    if (lng > lngUpperBound) lng = lngUpperBound - Math.random() * 0.05;
    results.push({
      name: `ペット関連施設 ${i + 1}`,
      date: '',
      location: 'dummy',
      venue: 'dummy',
      address: '東京都近郊のペット施設',
      prefecture: '東京都',
      city: '',
      lat: lat,
      lon: lng,
      source: 'Dummy',
      url: '#'
    });
  }
  return results;
}

/**
 * 多言語に対応したマップ初期化ラッパー。
 * userLang が 'zh' の場合は HERE Maps、それ以外は Google Maps を初期化します。
 * この関数は map.html の API スクリプトにおける callback として登録されます。
 */
function initMap() {
  // 多言語に対応してマップを初期化します。ここではユーザーの言語に応じて
  // 使用するマップライブラリを切り替えます。ここで try/catch を使うのは、
  // 外部ライブラリが読み込めない場合にフォールバック処理を行うためです。
  try {
    const lang = typeof getUserLang === 'function' ? getUserLang() : 'ja';
    // 中国語モードでも Google Maps を使用します。HERE Maps は利用しません。
    if (lang === 'zh') {
      if (typeof initGoogleMap === 'function') {
        initGoogleMap();
      }
    } else if (typeof initHereMap === 'function' && lang === 'here') {
      // 現在は使用していませんが、将来的に別の条件で HERE を使いたい場合に備えて残してあります
      initHereMap();
    } else if (typeof initGoogleMap === 'function') {
      initGoogleMap();
    }
  } catch (e) {
    // 例外が発生した場合は、言語設定に応じて適切な初期化関数を呼び出します。
    const fallbackLang = typeof getUserLang === 'function' ? getUserLang() : 'ja';
    // フォールバック時も中国語は Google Maps を利用
    if (fallbackLang === 'zh') {
      if (typeof initGoogleMap === 'function') {
        initGoogleMap();
      }
    } else if (typeof initHereMap === 'function' && fallbackLang === 'here') {
      initHereMap();
    } else if (typeof initGoogleMap === 'function') {
      initGoogleMap();
    }
  }
  // Send analytics event for map view
  try {
    trackMapView();
  } catch (err) {}
}

// グローバルに公開
window.initMap = initMap;

// 検索ボックスのイベントハンドラを登録します。ページ読み込み後に
// search-input 要素を取得し、入力が行われるたびに searchTerm
// 変数を更新してマーカー表示を更新します。
document.addEventListener('DOMContentLoaded', function() {
  const searchInput = document.getElementById('search-input');
  if (searchInput) {
    // Debounce search to reduce INP impact from frequent updates
    let searchTimeout = null;
    searchInput.addEventListener('input', function(e) {
      searchTerm = e.target.value || '';
      if (searchTimeout) clearTimeout(searchTimeout);
      searchTimeout = setTimeout(function() {
        updateMarkerVisibility();
      }, 200);
    });
  }
});

/**
 * HERE Maps の初期化関数。
 * 中国語モードで呼び出され、HERE Maps API を用いてマップを描画します。
 */
function initHereMap() {
  // ログイン状態を確認
  if (typeof requireLogin === 'function') requireLogin();
  // デフォルト中心（池袋小学校付近）。言語切替に関わらず固定とします。
  const defaultCenter = DEFAULT_CENTER;
  // イベントデータの取得
  const localEvents = Array.isArray(window.eventsData) ? window.eventsData.slice() : [];
  const dummyEvents = generateDummyEvents(200);
  localEvents.push(...dummyEvents);
  if (localEvents.length === 0) {
    console.warn('イベントデータが空です');
    return;
  }
  // HERE Platform の初期化
  // meta タグから base64 エンコードされた HERE API キーを取得してデコード
  let apikey = '';
  try {
    const metaHere = document.querySelector('meta[name="here-api-key"]');
    if (metaHere && metaHere.getAttribute('content')) {
      const encoded = metaHere.getAttribute('content');
      try {
        apikey = atob(encoded);
      } catch (err) {
        apikey = encoded;
      }
    }
  } catch (e) {
    apikey = '';
  }
  const platform = new H.service.Platform({ apikey: apikey });
  const defaultLayers = platform.createDefaultLayers();
  // マップインスタンス生成
  map = new H.Map(document.getElementById('map'), defaultLayers.vector.normal.map, {
    center: defaultCenter,
    // 初期ズームは大きめに設定し、後で applyDefaultViewHere() で調整します
    zoom: 14,
    pixelRatio: window.devicePixelRatio || 1
  });
  // インタラクションを有効化
  const behavior = new H.mapevents.Behavior(new H.mapevents.MapEvents(map));
  // UI を生成
  const ui = H.ui.UI.createDefault(map, defaultLayers);
  // マーカーリスト初期化
  markersList = [];
  // カテゴリ別の色定義は共通定義 categoryColors を使用します。
  // イベントを処理してマーカーを作成
  localEvents.forEach((eventItem, index) => {
    // カテゴリ付与
    if (!eventItem.category) {
      if (index < (window.eventsData ? window.eventsData.length : 0)) {
        eventItem.category = 'event';
      } else {
        const catOptions = ['restaurant','hotel','activity','museum','facility'];
        eventItem.category = catOptions[Math.floor(Math.random() * catOptions.length)];
      }
    }
    const iconColor = categoryColors[eventItem.category] || '#FFC72C';
    // SVG マーカーアイコン。HERE Maps では SVG 文字列をそのまま渡すと URL と解釈され
    // 不正なリクエストが発生するため、data URI としてエンコードして渡します。
    const svgMarkup = `<?xml version="1.0" encoding="UTF-8"?>\
<svg width="24" height="32" viewBox="-8 -20 16 20" xmlns="http://www.w3.org/2000/svg">\
  <path d="M0,0 C8,0 8,-12 0,-20 C-8,-12 -8,0 0,0 Z" fill="${iconColor}" stroke="#1F497D" stroke-width="1"/>\
</svg>`;
    const dataUri = 'data:image/svg+xml;charset=UTF-8,' + encodeURIComponent(svgMarkup);
    const icon = new H.map.Icon(dataUri);
    const marker = new H.map.Marker({ lat: eventItem.lat, lng: eventItem.lon }, { icon: icon });
    marker.setData(index);
    map.addObject(marker);
    // markersList に格納。検索用に名前と住所も保持します。
    markersList.push({
      marker,
      category: eventItem.category,
      name: eventItem.name || '',
      address: eventItem.address || ''
    });
    marker.addEventListener('tap', function(evt) {
      const idx = marker.getData();
      const eItem = localEvents[idx];
      const dateStr = eItem.date && eItem.date !== 'nan' ? `<p>${eItem.date}</p>` : '';
      const addressStr = eItem.address && eItem.address !== 'nan' ? `<p>${eItem.address}</p>` : '';
      const lang = typeof getUserLang === 'function' ? getUserLang() : 'ja';
      const t = (window.translations && window.translations[lang]) || {};
      const linkHtml = eItem.url && eItem.url !== 'nan' ? `<p><a href="${eItem.url}" target="_blank" rel="noopener">${t.view_details || '詳細を見る'}</a></p>` : '';
      const saveLabel = t.save || '保存';
      const saveFavorite = t.save_favorite || 'お気に入り';
      const saveWant = t.save_want || '行ってみたい';
      const savePlan = t.save_plan || '旅行プラン';
      const saveStar = t.save_star || 'スター付き';
      const contentHtml = `
        <div class="info-content" style="position:relative;">
          <h3 style="margin:0 0 0.2rem 0;">${eItem.name}</h3>
          ${dateStr}
          ${addressStr}
          ${linkHtml}
          <div class="save-wrapper" style="position:relative;display:inline-block;margin-top:0.5rem;">
            <button class="save-btn" data-index="${idx}" style="background-color:transparent;border:none;color:#1F497D;font-size:0.9rem;cursor:pointer;display:flex;align-items:center;gap:0.3rem;">
              <span class="save-icon">🔖</span><span>${saveLabel}</span>
            </button>
            <div class="save-menu" style="display:none;position:absolute;top:110%;left:0;background:#fff;border:1px solid #ccc;border-radius:6px;padding:0.4rem;box-shadow:0 2px 6px rgba(0,0,0,0.2);width:130px;font-size:0.8rem;">
              <div class="save-option" data-list="favorite" style="cursor:pointer;padding:0.2rem 0.4rem;display:flex;align-items:center;gap:0.3rem;"><span>❤️</span><span>${saveFavorite}</span></div>
              <div class="save-option" data-list="want" style="cursor:pointer;padding:0.2rem 0.4rem;display:flex;align-items:center;gap:0.3rem;"><span>🚩</span><span>${saveWant}</span></div>
              <div class="save-option" data-list="plan" style="cursor:pointer;padding:0.2rem 0.4rem;display:flex;align-items:center;gap:0.3rem;"><span>🧳</span><span>${savePlan}</span></div>
              <div class="save-option" data-list="star" style="cursor:pointer;padding:0.2rem 0.4rem;display:flex;align-items:center;gap:0.3rem;"><span>⭐</span><span>${saveStar}</span></div>
            </div>
          </div>
        </div>`;
      // 既存のバブルを削除
      ui.getBubbles().forEach(function(b) { ui.removeBubble(b); });
      const bubble = new H.ui.InfoBubble(evt.target.getGeometry(), { content: contentHtml });
      ui.addBubble(bubble);
      // 翻訳とイベント設定を遅延で実行
      setTimeout(() => {
        const saveBtn = document.querySelector('.save-btn');
        const saveMenu = document.querySelector('.save-menu');
        if (saveBtn && saveMenu) {
          saveBtn.addEventListener('click', (e) => {
            e.stopPropagation();
            saveMenu.style.display = saveMenu.style.display === 'none' ? 'block' : 'none';
          });
          saveMenu.querySelectorAll('.save-option').forEach(opt => {
            opt.addEventListener('click', (ev) => {
              const listType = opt.getAttribute('data-list');
              addToFavorites(eItem, listType);
              saveMenu.style.display = 'none';
            });
          });
        }
        if (typeof applyTranslations === 'function') applyTranslations();
      }, 0);
    });
  });

  // すべてのマーカー追加後にビューを調整します。
  // Google Maps と同様に、全マーカーが収まる矩形を計算して地図をフィットさせます。
  try {
    const lats = localEvents.map(e => parseFloat(e.lat));
    const lngs = localEvents.map(e => parseFloat(e.lon));
    if (lats.length > 0 && lngs.length > 0) {
      const minLat = Math.min.apply(null, lats);
      const maxLat = Math.max.apply(null, lats);
      const minLng = Math.min.apply(null, lngs);
      const maxLng = Math.max.apply(null, lngs);
      // H.geo.Rect のコンストラクタは (top, left, bottom, right) の順です
      const boundsRect = new H.geo.Rect(maxLat, minLng, minLat, maxLng);
      map.getViewModel().setLookAtData({ bounds: boundsRect });
      // 必要に応じてズーム制限をかけます
      const maxZoom = 14;
      if (map.getZoom() > maxZoom) {
        map.setZoom(maxZoom);
      }
    }
  } catch (err) {
    // ビュー調整は失敗しても致命的でないため、ログに出力するだけとします
    console.warn('Failed to fit map bounds:', err);
  }
  // カテゴリボタンを生成
  createCategoryButtons();
  // マーカー表示更新
  updateMarkerVisibility();
  // デフォルト中心＋1kmのビューを適用
  applyDefaultViewHere(map, H);
  // 周辺表示ボタンの処理
  const resetBtn = document.getElementById('reset-view-btn');
  if (resetBtn) {
    resetBtn.addEventListener('click', () => {
      // デフォルト中心＋半径1kmに戻す
      applyDefaultViewHere(map, H);
    });
  }
}