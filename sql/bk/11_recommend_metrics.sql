-- 11_recommend_metrics.sql
--
-- This migration introduces a table used to record interactions with
-- recommended events.  Whenever a user clicks on a recommended event
-- (for example, from the home page or a recommendation widget), the
-- front‑end should call the `/recommend-events-hit` REST endpoint to
-- insert a row into this table.  These metrics can then be used to
-- evaluate the effectiveness of the recommendation engine (e.g.
-- click‑through rates).
--
-- Fields:
--   id         : Primary key
--   wp_user_id : WordPress user ID if logged in (nullable)
--   event_id   : ID of the recommended event that was clicked
--   created_at : Timestamp of the click

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