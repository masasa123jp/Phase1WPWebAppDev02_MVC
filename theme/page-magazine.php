<?php
/**
 * Template Name: Magazine Page
 *
 * テーマ内の js/magazine.js と連動する雑誌ビューのマークアップ。
 * クリック対象のカードは .magazine-card として2枚用意（増やしてOK）。
 */

get_header();
?>

<header class="app-header">
  <!-- Specify explicit dimensions and lazy loading to improve LCP/CLS -->
  <img src="<?php echo esc_url( get_template_directory_uri() ); ?>/images/logo_roro.png" alt="ロゴ" class="small-logo" width="200" height="80" loading="lazy" fetchpriority="high" />
  <h2 data-i18n-key="mag_title">雑誌</h2>
  <button id="lang-toggle-btn" class="lang-toggle" title="Change language" role="button" aria-label="<?php echo esc_attr__( 'Change language', 'roro' ); ?>">
    <!-- Provide width/height and lazy loading for the language icon -->
    <img src="<?php echo esc_url( get_template_directory_uri() ); ?>/images/icon_language.png" alt="Language" width="64" height="64" loading="lazy" />
  </button>
</header>

<!-- Anchor for skip link -->
<a id="main" tabindex="-1"></a>
<main class="magazine-grid">
  <div class="magazine-card" data-mag-index="0" tabindex="0" role="button" aria-label="<?php echo esc_attr__( '2025年6月号', 'roro' ); ?>">
    <!-- 6月号 -->
    <img src="<?php echo esc_url( get_template_directory_uri() ); ?>/images/magazine_cover1.png" alt="2025年6月号" width="300" height="400" loading="lazy" />
    <div class="magazine-info">
      <h3 data-i18n-key="mag_issue_june">2025年6月号</h3>
      <p data-i18n-key="mag_desc_june">雨の日でも犬と楽しく過ごせる特集</p>
    </div>
  </div>
  <div class="magazine-card" data-mag-index="1" tabindex="0" role="button" aria-label="<?php echo esc_attr__( '2025年7月号', 'roro' ); ?>">
    <!-- 7月号 -->
    <img src="<?php echo esc_url( get_template_directory_uri() ); ?>/images/magazine_cover2.png" alt="2025年7月号" width="300" height="400" loading="lazy" />
    <div class="magazine-info">
      <h3 data-i18n-key="mag_issue_july">2025年7月号</h3>
      <p data-i18n-key="mag_desc_july">紫外線対策とワンちゃんのおでかけ特集</p>
    </div>
  </div>
  <div class="magazine-card" data-mag-index="2" tabindex="0" role="button" aria-label="<?php echo esc_attr__( '2025年8月号', 'roro' ); ?>">
    <!-- 8月号 (Placeholder cover for August) -->
    <img src="<?php echo esc_url( get_template_directory_uri() ); ?>/images/magazine_cover_aug.png" alt="2025年8月号" width="300" height="400" loading="lazy" />
    <div class="magazine-info">
      <h3 data-i18n-key="mag_issue_aug">2025年8月号</h3>
      <p data-i18n-key="mag_desc_aug">夏のお散歩とクールダウン特集</p>
    </div>
  </div>
  <div class="magazine-card" data-mag-index="3" tabindex="0" role="button" aria-label="<?php echo esc_attr__( '2025年9月号', 'roro' ); ?>">
    <!-- 9月号 (Placeholder cover for September) -->
    <img src="<?php echo esc_url( get_template_directory_uri() ); ?>/images/magazine_cover_sep.png" alt="2025年9月号" width="300" height="400" loading="lazy" />
    <div class="magazine-info">
      <h3 data-i18n-key="mag_issue_sep">2025年9月号</h3>
      <p data-i18n-key="mag_desc_sep">秋の行楽とグッズ特集</p>
    </div>
  </div>
  <!-- ワンポイントアドバイス（ランダム表示） -->
  <div id="random-advice" class="random-advice" style="margin-top:2rem;">
    <!-- アドバイスをここに表示します -->
  </div>
</main>

<!-- 雑誌閲覧用オーバーレイ（js/magazine.js が操作） -->
<div id="magazine-viewer" class="magazine-viewer" style="display:none;">
  <div class="book"></div>
</div>

<script>
// ランダムなワンポイントアドバイスを取得して表示する
document.addEventListener('DOMContentLoaded', function(){
  // WordPress の REST API エンドポイントを呼び出す
  fetch('<?php echo esc_url( rest_url( "roro/v1/advice/random" ) ); ?>')
    .then(function(res){ return res.json(); })
    .then(function(data){
      if (data && data.title) {
        const container = document.getElementById('random-advice');
        // シンプルなカード形式で表示
        container.innerHTML = `
          <h3 style="font-size:1.25rem;margin-bottom:0.5rem;">ワンポイントアドバイス</h3>
          <h4 style="font-size:1rem;margin:0;">${data.title}</h4>
          <p style="margin-top:0.25rem;">${data.body || ''}</p>
        `;
      }
    }).catch(function(err){
      console.error('Advice fetch error', err);
    });
});
</script>

<?php get_footer(); ?>