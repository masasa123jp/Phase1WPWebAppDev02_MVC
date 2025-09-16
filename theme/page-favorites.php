<?php
/**
 * Template Name: Favorites Page
 *
 * Displays a list of favourited events stored in local storage.  This
 * page is based on favorites.html from the original mockup and
 * includes a container for dynamically generated list items.  No
 * server side processing takes place here; all logic is handled by
 * the enqueued JavaScript.
 */

get_header();
?>

<header class="app-header">
    <!-- Explicit dimensions and lazy loading for logo -->
    <img src="<?php echo esc_url( get_template_directory_uri() ); ?>/images/logo_roro.png" alt="ロゴ" class="small-logo" width="200" height="80" loading="lazy" fetchpriority="high" />
    <h2 data-i18n-key="favorites_title">お気に入り</h2>
    <button id="lang-toggle-btn" class="lang-toggle" title="Change language" role="button" aria-label="<?php echo esc_attr__( 'Change language', 'roro' ); ?>">
        <img src="<?php echo esc_url( get_template_directory_uri() ); ?>/images/icon_language.png" alt="Language" width="64" height="64" loading="lazy" />
    </button>
</header>

<!-- Anchor for skip link -->
<a id="main" tabindex="-1"></a>
<main id="favorites-container">
    <!-- トレンドイベントセクション -->
    <div id="trending-events-section" class="trending-section" style="margin-bottom:1.5rem;">
        <h3 style="font-size:1.25rem;margin-bottom:0.5rem;" data-i18n-key="trending_events">人気のイベント</h3>
        <ul id="trending-events-list" style="list-style:none;padding-left:0;"></ul>
    </div>

    <ul id="favorites-list"></ul>
    <p id="no-favorites" style="display:none;" data-i18n-key="no_favorites">お気に入りがまだありません。</p>
</main>

<script>
// Fetch and display trending events when the page loads.
// Use the advanced recommendation endpoint to leverage the improved scoring model.
document.addEventListener('DOMContentLoaded', function(){
  var apiUrl = '<?php echo esc_url( rest_url( "roro/v1/recommend-events-advanced" ) ); ?>';
  // Pass the current user ID if available (via a global WP variable), otherwise omit.
  var params = '';
  if (window.roroCurrentUserId) {
    params = '?user_id=' + encodeURIComponent(window.roroCurrentUserId);
  }
  fetch(apiUrl + params)
    .then(function(res){ return res.json(); })
    .then(function(data){
      if (!Array.isArray(data) || data.length === 0) {
        document.getElementById('trending-events-section').style.display = 'none';
        return;
      }
      var list = document.getElementById('trending-events-list');
      data.forEach(function(ev){
        var li = document.createElement('li');
        li.style.marginBottom = '0.5rem';
        var favCount = ev.favourites !== undefined ? ev.favourites : ev.favorites || 0;
        var text = ev.name + ' (' + favCount + '件の\u300cお気に入り\u300d)';
        // If a reason is provided, append it for explainability.
        if (ev.reason) {
          text += ' \u2013 ' + ev.reason;
        }
        li.textContent = text;
        // Make list items focusable and convey a label for assistive technologies.
        li.tabIndex = 0;
        li.setAttribute('aria-label', text);
        list.appendChild(li);
      });
    })
    .catch(function(err){ console.error('recommend-events-advanced error', err); });
});
</script>

<?php get_footer(); ?>