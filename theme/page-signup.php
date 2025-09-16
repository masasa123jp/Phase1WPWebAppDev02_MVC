<?php
/**
 * Template Name: Sign Up Page
 *
 * Presents a registration form for new users.  This is a direct
 * translation of signup.html from the original mockup, adapted for
 * WordPress.  The form does not submit to a backend; it is purely
 * illustrative.  Replace the placeholders with your own logic or
 * integrate with a membership plugin as needed.
 */

get_header();
?>

<header>
    <!-- Add dimensions and lazy loading to logo for layout stability -->
    <img src="<?php echo esc_url( get_template_directory_uri() ); ?>/images/logo_roro.png" alt="Project RORO ロゴ" class="logo" width="200" height="80" loading="lazy" fetchpriority="high" />
</header>
<!-- Anchor for skip link -->
<a id="main" tabindex="-1"></a>
<main class="signup-container">
    <h1>新規登録</h1>
    <form id="signup-form" autocomplete="off">
        <div class="input-group">
            <label for="name">お名前</label>
            <input type="text" id="name" placeholder="山田太郎" required />
        </div>
        <div class="input-group">
            <label for="furigana">ふりがな</label>
            <input type="text" id="furigana" placeholder="やまだたろう" />
        </div>
        <div class="input-group">
            <label for="email">メールアドレス</label>
            <input type="email" id="email" placeholder="sample@example.com" required />
        </div>
        <div class="input-group">
            <label for="password">パスワード</label>
            <input type="password" id="password" placeholder="半角英数6文字以上" required />
        </div>
        <div class="input-group">
            <label for="petType">ペットの種類</label>
            <select id="petType">
                <option value="dog">犬</option>
                <option value="cat">猫</option>
            </select>
        </div>
        <div class="input-group">
            <label for="petName">ペットのお名前</label>
            <input type="text" id="petName" placeholder="ぽち" />
        </div>
        <div class="input-group">
            <label for="petAge">ペットの年齢</label>
            <select id="petAge">
                <option value="puppy">子犬/子猫 (1歳未満)</option>
                <option value="adult">成犬/成猫 (1〜7歳)</option>
                <option value="senior">シニア犬/シニア猫 (7歳以上)</option>
            </select>
        </div>
        <div class="input-group">
            <label for="address">住所</label>
            <input type="text" id="address" placeholder="東京都港区…" />
        </div>
        <div class="input-group">
            <label for="phone">電話番号</label>
            <input type="tel" id="phone" placeholder="09012345678" />
        </div>
        <button type="submit" class="btn primary-btn">新規登録</button>
    </form>
    <div class="social-login">
        <button type="button" class="btn google-btn">Googleで登録</button>
        <button type="button" class="btn line-btn">LINEで登録</button>
    </div>
    <p>
        すでにアカウントをお持ちの方は
        <a href="<?php echo esc_url( home_url( '/' ) ); ?>">こちらからログイン</a>
    </p>
</main>

<?php get_footer(); ?>