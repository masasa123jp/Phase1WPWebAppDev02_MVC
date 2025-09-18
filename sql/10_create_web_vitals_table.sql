-- 10_create_web_vitals_table.sql
--
-- フロントエンドから取得した Core Web Vitals の計測値を保存する
-- テーブルを作成します。各行は 1 件のメトリクスサンプルを表し、
-- 訪問者がログインしていた場合は WordPress ユーザーを参照します。
-- テーブルがすでに存在する場合でも、CREATE TABLE IF NOT EXISTS 句により
-- エラーは発生しません。

CREATE TABLE IF NOT EXISTS `RORO_WEB_VITALS` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `wp_user_id` BIGINT(20) UNSIGNED NULL,
  `metric` VARCHAR(32) NOT NULL,
  `value` DOUBLE NOT NULL,
  `url` VARCHAR(255) NOT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_roro_web_vitals_user` (`wp_user_id`),
  CONSTRAINT `fk_roro_web_vitals_user` FOREIGN KEY (`wp_user_id`) REFERENCES `wp_users`(`ID`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
