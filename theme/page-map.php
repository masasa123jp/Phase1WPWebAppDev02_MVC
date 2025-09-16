<?php
/**
 * Template Name: Map Page
 *
 * 最優先ゴール：
 *  - このテンプレートが使われる限り、地図関連JSが必ず正しい順で読み込まれる。
 *  - Google の callback=initMap が確実に window.initMap を呼べる。
 *
 * 注意：
 *  - 最適化系プラグイン使用時は、以下4ハンドルを「結合・遅延の除外」にしてください。
 *      roro-map-loader, roro-events, roro-map, google-maps-api
 */

get_header();

// テーマのURLとパス
$theme_uri  = get_template_directory_uri();
$theme_path = get_template_directory();

/** 1) Google callback 用ローダー（必ず先に） */
wp_enqueue_script(
  'roro-map-loader',
  $theme_uri . '/js/map-loader.js',
  array(), // 依存なし
  file_exists($theme_path . '/js/map-loader.js') ? filemtime($theme_path . '/js/map-loader.js') : null,
  false    // ヘッダで
);

/** 2) イベント/マーカーのデータ
 *
 * 以前の実装では、`data/events.js` を読み込んで静的なイベント配列
 * (`window.eventsData`) を提供していましたが、フェーズ 1 の改修により
 * イベントは REST API (`/wp-json/roro/v1/events`) から取得するようになりました。
 * そのため `events.js` の読み込みを依存関係から外します。ファイル自体は
 * フォールバック用途に残していますが、通常は読み込まれません。
 */
$deps_for_map = array();

/** 3) map.js（地図初期化本体。グローバルに window.initMap を公開する実装前提） */
wp_enqueue_script(
  'roro-map',
  $theme_uri . '/js/map.js',
  $deps_for_map, // events.js に依存
  file_exists($theme_path . '/js/map.js') ? filemtime($theme_path . '/js/map.js') : null,
  false          // ヘッダで
);

/** 4) Google Maps API（&callback=initMap） */
if ( ! function_exists('roro_get_google_maps_api_key') ) {
  /**
   * APIキー取得：環境変数 → 定数 → WPオプション の順。
   */
  function roro_get_google_maps_api_key() {
    $candidates = array(
      getenv('RORO_GOOGLE_MAPS_API_KEY'),
      getenv('GOOGLE_MAPS_API_KEY'),
      defined('RORO_GOOGLE_MAPS_API_KEY') ? RORO_GOOGLE_MAPS_API_KEY : null,
      get_option('roro_google_maps_api_key'),
    );
    foreach ($candidates as $v) if (!empty($v)) return $v;
    return '';
  }
}

$gmaps_key = roro_get_google_maps_api_key();

// 言語と地域（必要に応じて固定でもOK）
$loc    = get_locale(); // 例: ja_JP / en_US / zh_CN / ko_KR ...
$lang   = 'ja';
$region = 'JP';
if (strpos($loc,'en_')===0 || $loc==='en') { $lang='en';   $region='US'; }
elseif (strpos($loc,'zh_')===0|| $loc==='zh'){ $lang='zh-CN';$region='CN'; }
elseif (strpos($loc,'ko_')===0|| $loc==='ko'){ $lang='ko';  $region='KR'; }

if ( ! empty($gmaps_key) ) {
  $api_url = sprintf(
    'https://maps.googleapis.com/maps/api/js?key=%s&callback=initMap&language=%s&region=%s&loading=async',
    rawurlencode($gmaps_key), rawurlencode($lang), rawurlencode($region)
  );

  // Google 本体はフッタでOK（先に map-loader/map.js を評価済にするため）
  wp_enqueue_script(
    'google-maps-api',
    $api_url,
    array('roro-map-loader', 'roro-map'),
    null,
    true
  );

  // async defer 付与
  add_filter('script_loader_tag', function($tag, $handle){
    if ($handle === 'google-maps-api' && strpos($tag,'async')===false) {
      $tag = str_replace('<script ', '<script async defer ', $tag);
    }
    return $tag;
  }, 10, 2);
}

?>
<!-- ========================== 画面描画部 ========================== -->
<header class="app-header">
  <!-- Add width/height and lazy loading to improve performance -->
  <img src="<?php echo esc_url( get_template_directory_uri() ); ?>/images/logo_roro.png" alt="ロゴ" class="small-logo" width="200" height="80" loading="lazy" fetchpriority="high" />
  <h2 data-i18n-key="map_title">おでかけマップ</h2>
  <button id="lang-toggle-btn" class="lang-toggle" title="Change language" role="button" aria-label="<?php echo esc_attr__( 'Change language', 'roro' ); ?>">
    <img src="<?php echo esc_url( get_template_directory_uri() ); ?>/images/icon_language.png" alt="Language" width="64" height="64" loading="lazy" />
  </button>
</header>

<!-- Anchor for skip link -->
<a id="main" tabindex="-1"></a>
<main id="map-container">
  <div id="category-bar" class="category-bar"></div>
  <!-- 検索バー：スポットやイベントを名称・住所で絞り込む -->
  <div id="search-container" class="search-container" style="margin:0.5rem 0; text-align:center;">
    <input type="text" id="search-input" class="search-input" placeholder="検索..." data-i18n-key="search_placeholder" style="width:90%;max-width:400px;padding:0.4rem;border:1px solid #ccc;border-radius:4px;" />
  </div>
  <!-- Set a minimum height to avoid layout shift until CSS loads -->
  <div id="map" style="min-height:400px;"></div>
  <button id="reset-view-btn" class="reset-btn" title="周辺表示" data-i18n-key="reset_view" tabindex="0" role="button" aria-label="<?php echo esc_attr__( 'Reset view', 'roro' ); ?>">周辺表示</button>
</main>

<!-- ========================== 推薦イベント表示セクション ========================== -->
<!--
  このセクションでは、AIや履歴に基づいたおすすめイベントを表示します。
  JS側で /roro/v1/recommend-events などのREST APIを呼び出し、
  取得したイベントをカード形式で #recommend-list に挿入します。
  多言語対応のため見出しには data-i18n-key 属性を付与します。
-->
<section id="recommend-section" class="recommend-section" style="padding:1rem 0; text-align:center;">
  <h3 data-i18n-key="recommend_title" style="margin-bottom:0.5rem; font-size:1.2rem;">おすすめ</h3>
  <div id="recommend-list" class="recommend-list" style="display:flex; flex-wrap:wrap; gap:1rem; justify-content:center;"></div>
</section>

<!--
  補足：
  - zh のとき HERE を併用する場合は、下のローダーで HERE を読み込み、
    全部読み込めたら window.initMap() を呼ぶ（map.js 側の分岐仕様に合わせて調整）。
  - 今回は “Google を必ず読み込む” を enqueue に一本化しているため、
    ここで Google を直接読み込むことはしない。
-->
<script>
(function () {
  try {
    var lang = (localStorage.getItem('userLang') || 'ja').toLowerCase();
    if (lang === 'zh') {
      var hereScripts = [
        'https://js.api.here.com/v3/3.1/mapsjs-core.js',
        'https://js.api.here.com/v3/3.1/mapsjs-service.js',
        'https://js.api.here.com/v3/3.1/mapsjs-ui.js',
        'https://js.api.here.com/v3/3.1/mapsjs-mapevents.js'
      ];
      var loaded = 0;
      hereScripts.forEach(function (src) {
        var s = document.createElement('script');
        s.src = src;
        s.async = true;
        s.defer = true;
        s.onload = function () {
          loaded++;
          if (loaded === hereScripts.length && typeof window.initMap === 'function') {
            window.initMap(); // map.js の実装に従い Google/HERE いずれかで初期化される
          }
        };
        s.onerror = function () { console.error('HERE script failed:', src); };
        document.head.appendChild(s);
      });
    }
  } catch (e) {
    console.error('HERE loader error:', e);
  }
})();
</script>

<!--
  ★フォールバック（超重要）：もし最適化等で map.js / Google API が欠けた場合に、その場で補う。
  コンソールで確認すると map-loader.js と events.js だけが出ているケースを救済する。
-->
<script>
(function(){
  var base = "<?php echo esc_js($theme_uri); ?>";

  function has(pattern) {
    return Array.prototype.some.call(document.scripts, function(s){ return pattern.test(s.src || ''); });
  }
  function inject(src, defer) {
    var s = document.createElement('script');
    s.src = src;
    if (defer) s.defer = true;
    s.async = true;
    document.head.appendChild(s);
    console.log('[map fallback] injected:', src);
  }

  // map.js がいなければ注入
  if (!has(/\/js\/map\.js(\?|$)/)) {
    inject(base + '/js/map.js', true);
  }

  // Google API がいなければ注入（キーを使って同じURLを生成）
  <?php if (!empty($gmaps_key)) : ?>
    if (!window.google || !window.google.maps || !has(/maps\.googleapis\.com\/maps\/api\/js/)) {
      var lang = "<?php echo esc_js($lang); ?>";
      var region = "<?php echo esc_js($region); ?>";
      var url = 'https://maps.googleapis.com/maps/api/js?key=' +
                encodeURIComponent('<?php echo esc_js($gmaps_key); ?>') +
                '&callback=initMap&language=' + encodeURIComponent(lang) +
                '&region=' + encodeURIComponent(region) +
                '&loading=async';
      inject(url, true);
    }
  <?php else: ?>
    console.warn('[map fallback] Google API key is not configured on server; cannot inject API.');
  <?php endif; ?>
})();
</script>

<?php get_footer(); ?>
