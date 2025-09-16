-- 10_web_vitals.sql
--
-- This migration introduces a table for collecting Core Web Vitals metrics
-- from the front‑end.  Each row records a single metric sample along
-- with contextual data such as the visited URL and user identifier.
--
-- Fields:
--   id         : Primary key, auto increment
--   wp_user_id : WordPress user ID if logged in (nullable)
--   metric     : Name of the metric (LCP, CLS, INP, etc.)
--   value      : Numeric value of the metric
--   url        : URL path where the measurement occurred
--   created_at : Timestamp of when the metric was reported

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