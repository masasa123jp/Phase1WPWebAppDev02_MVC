<?php
/**
 * Template Name: AI Assistant Page
 *
 * Embeds a Dify AI assistant or a custom chat UI.  The display
 * toggling and actual chat functionality are handled by the
 * JavaScript in js/dify-switch.js and js/custom-chat-ui.js.  This
 * template mirrors the structure of dify.html from the original
 * mockup and leaves styling to the existing CSS.
 */

get_header();
?>

<header class="app-header">
    <!-- Logo with dimensions and lazy loading -->
    <img src="<?php echo esc_url( get_template_directory_uri() ); ?>/images/logo_roro.png" alt="ロゴ" class="small-logo" width="200" height="80" loading="lazy" fetchpriority="high" />
    <h2 data-i18n-key="ai_title">AIアシスタント</h2>
    <button id="lang-toggle-btn" class="lang-toggle" title="言語切替" role="button" aria-label="<?php echo esc_attr__( 'Change language', 'roro' ); ?>">
        <img src="<?php echo esc_url( get_template_directory_uri() ); ?>/images/icon_language.png" alt="Language" width="64" height="64" loading="lazy" />
    </button>
</header>

<!-- Anchor for skip link -->
<a id="main" tabindex="-1"></a>
<main class="dify-container">
    <p data-i18n-key="ai_intro">
      AIアシスタントにペットのイベント情報やおすすめスポットなど、気になることを気軽に質問してみましょう。
    </p>

    <!-- ▼ 公式 Dify 画面（iframe または script バブル） -->
    <section id="embed-area" class="panel">
      <!--<h3 class="section-title">Dify AI Chat</h3>-->

      <!-- iframe 方式のホスト（styles.css に準拠した外観） -->
      <div id="embed-host" class="external-chat-container">
        <!-- JS がここに iframe を挿入します（script 方式時は空のまま） -->
      </div>

      <!--<div class="note">
        ・script 方式では画面右下に “バブル” が表示されます（config を先に定義 → script 読込が必須）。<br>
        ・iframe 方式も公式で案内されている単純な埋め込み方法です。
      </div>
      <div class="toolbar">
        <button id="open-bubble" class="btn secondary-btn" title="（script方式の時）バブルを開く">バブルを開く</button>
      </div>
      -->
    </section>

    <!-- ▼ 自作 Dify 風 UI（SSE対応。API が無ければデモ応答） -->
    <section id="custom-area" class="panel">
      <h3 class="section-title">オリジナル UI（Dify 風）</h3>
      <div id="custom-host"></div>
      <div class="note">
        ・サーバに <code>/api/chat</code>（Dify の <code>/v1/chat-messages</code> へプロキシ）を用意すると、<br>
        　<code>response_mode: "streaming"</code> で SSE を受信しストリーミング描画します。
      </div>
    </section>
    <!--
    <div class="toolbar">
      <button id="toggle-btn" class="btn primary-btn">表示切替（公式 ⇄ 自作）</button>
    </div>
    -->
</main>

<!-- ページ固有の最小限スタイル（共通スタイルに沿った微調整） -->
<style>
  main.dify-container { padding: 1rem; padding-bottom: calc(var(--nav-height) + 1rem); }
  .panel { background:#fff; border:1px solid #e5e7eb; border-radius: var(--border-radius); padding: 12px; }
  .section-title { margin: 0 0 8px; font-weight: 700; color: var(--base-color); }
  /* ２エリア（どちらか片方のみ表示） */
  #embed-area, #custom-area { display: none; }
  /* iframe で Dify を埋め込むホスト（共通スタイルの external-chat-container に合わせる） */
  #embed-host.external-chat-container { height: calc(100vh - var(--nav-height) - 220px); }
  #embed-host iframe { width: 100%; height: 100%; border: none; border-radius: var(--border-radius); }
  /* 自作 UI のホスト */
  #custom-host { height: calc(100vh - var(--nav-height) - 220px); background:#fff; border:1px solid #e5e7eb; border-radius: var(--border-radius); overflow: hidden; }
  /* 操作用ボタン */
  .toolbar { display:flex; gap:.5rem; justify-content:flex-end; margin:.5rem 0 0; }
  .toolbar .btn { padding:.5rem .9rem; }
</style>

<!-- 表示切替とマウント処理 -->
<script type="module" src="<?php echo esc_url( get_template_directory_uri() ); ?>/js/dify-switch.js"></script>

<?php get_footer(); ?>