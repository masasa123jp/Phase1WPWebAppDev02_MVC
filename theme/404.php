<?php
/**
 * Custom 404 template.
 *
 * When WordPress cannot find a matching post or page, it normally
 * displays a generic "404 Not Found" message along with the site
 * header and footer.  Because this theme is intended to mimic a
 * single–page application with discrete static pages (e.g. index.html,
 * map.html, etc.), hitting a dead permalink such as
 * `/mock-01_logon/index.html` would otherwise trigger a default 404
 * which looks nothing like the mockup.  To provide a seamless
 * experience, this template returns a 200 status and renders our
 * login screen instead.  Any unknown URL will therefore fall back to
 * the login page rather than showing WordPress messaging.
 */

// Override the default status header so that browsers and search
// engines treat this as a normal page.  Without this, the server
// would return a 404 status which may confuse crawlers and users
// expecting the login screen.
status_header( 200 );
nocache_headers();

// Use the login page template to render the content.  Rather than
// duplicating markup here, we simply include the header and footer
// and output the same form used on the front page.  The body class
// `login-page` will be automatically applied via our body_class
// filter when this becomes the front page or home page.
get_header();
?>

<header>
    <!-- Provide dimensions and lazy loading for the fallback login logo -->
    <img src="<?php echo esc_url( get_template_directory_uri() ); ?>/images/logo_roro.png" alt="Project RORO ロゴ" class="logo" width="200" height="80" loading="lazy" />
</header>
<!-- Anchor for skip link -->
<a id="main" tabindex="-1"></a>
<main>
    <!-- 挨拶文と言語切替ボタンを横並びで配置 -->
    <div class="login-header">
        <h1 data-i18n-key="login_greeting">こんにちは！</h1>
        <button id="lang-toggle-btn" class="lang-toggle" title="Change language">
            <img src="<?php echo esc_url( get_template_directory_uri() ); ?>/images/icon_language.png" alt="Language" width="64" height="64" loading="lazy" />
        </button>
    </div>
    <!-- ログインフォーム -->
    <form id="login-form" autocomplete="off">
        <div class="input-group">
            <label for="login-email" data-i18n-key="login_email">メールアドレス</label>
            <input type="email" id="login-email" placeholder="sample@example.com" required />
        </div>
        <div class="input-group">
            <label for="login-password" data-i18n-key="login_password">パスワード</label>
            <input type="password" id="login-password" placeholder="パスワード" required />
        </div>
        <button type="submit" class="btn primary-btn" data-i18n-key="login_submit">ログイン</button>
    </form>
    <!-- ソーシャルログインボタン -->
    <div class="social-login">
        <button type="button" class="btn google-btn" data-i18n-key="login_google">Googleでログイン</button>
        <button type="button" class="btn line-btn" data-i18n-key="login_line">LINEでログイン</button>
    </div>
    <p>
        <span data-i18n-key="login_no_account">アカウントをお持ちでない場合は</span>
        <a href="<?php echo esc_url( home_url( '/signup/' ) ); ?>" data-i18n-key="login_register_link">こちらから新規登録</a>
    </p>
</main>

<?php
get_footer();