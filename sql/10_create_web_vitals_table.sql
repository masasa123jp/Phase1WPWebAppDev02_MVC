-- 10_create_web_vitals_table.sql
--
-- Introduces a table for storing Core Web Vitals measurements from
-- the front end.  Each row represents a single metric sample and
-- references the WordPress user if the visitor was logged in.  If the
-- table already exists, the CREATE TABLE IF NOT EXISTS clause will
-- prevent errors.

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