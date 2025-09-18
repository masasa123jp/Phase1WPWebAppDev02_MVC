-- 11_create_recommend_event_metrics.sql
--
-- 推薦イベントとのインタラクションを記録するテーブルを作成します。
-- 訪問者が推薦イベントをクリックした際、フロントエンドは
-- 適切な REST エンドポイントを呼び出し、このテーブルに行を追加します。
-- このテーブルには、ユーザー（ログインしている場合）、イベントID、
-- クリックのタイムスタンプが記録されます。
-- CREATE TABLE IF NOT EXISTS 句により、このマイグレーションは
-- 複数回実行してもエラーになりません。

CREATE TABLE IF NOT EXISTS `RORO_RECOMMEND_EVENT_METRICS` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `wp_user_id` BIGINT(20) UNSIGNED NULL,
  `event_id` INT UNSIGNED NOT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_roro_recommend_event_user` (`wp_user_id`),
  KEY `idx_roro_recommend_event_event` (`event_id`),
  CONSTRAINT `fk_roro_recommend_event_user` FOREIGN KEY (`wp_user_id`) REFERENCES `wp_users`(`ID`) ON DELETE SET NULL,
  CONSTRAINT `fk_roro_recommend_event_event` FOREIGN KEY (`event_id`) REFERENCES `RORO_EVENTS_MASTER`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
