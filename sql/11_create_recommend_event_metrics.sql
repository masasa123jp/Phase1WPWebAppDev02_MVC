-- 11_create_recommend_event_metrics.sql
--
-- Creates a table used to record interactions with recommended events.
-- When a visitor clicks on a recommended event, the front end should
-- call the appropriate REST endpoint, which inserts a row into this
-- table.  The table records the user (if logged in), the event ID and
-- the timestamp of the click.  The CREATE TABLE IF NOT EXISTS clause
-- allows this migration to be run multiple times without errors.

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