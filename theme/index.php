<?php
/**
 * Front page template (login page).
 *
 * This template replicates the original login screen from the mockup.  It
 * provides a simple email/password form along with social login buttons.
 * The actual authentication logic is not implemented as WordPress
 * handles login through its own mechanism; this template is purely
 * presentational.  The page uses the `login-page` body class for
 * styling defined in css/styles.css.
 */

get_header();
?>

<header>
    <!-- Provide explicit dimensions and lazy loading for the login logo. Use fetchpriority to prioritise initial load -->
    <img src="<?php echo esc_url( get_template_directory_uri() ); ?>/images/logo_roro.png" alt="Project RORO ロゴ" class="logo" width="200" height="80" loading="lazy" fetchpriority="high" />
</header>
<!-- Anchor for skip link -->
<a id="main" tabindex="-1"></a>
<main>
    <!-- 挨拶文と言語切替ボタンを横並びで配置 -->
    <div class="login-header">
        <h1 data-i18n-key="login_greeting">こんにちは！</h1>
        <button id="lang-toggle-btn" class="lang-toggle" title="Change language" role="button" aria-label="<?php echo esc_attr__( 'Change language', 'roro' ); ?>">
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

<?php get_footer(); ?>