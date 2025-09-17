-- 05_add_status_to_core_tables.sql
--
-- Adds `status` and `status_updated_at` columns to the primary RORO tables
-- and populates them based on the existing `isVisible` flag.  This migration
-- also creates a status history table if it does not already exist.  Because
-- MySQL does not understand `ADD COLUMN IF NOT EXISTS`, each ALTER is
-- guarded by a test against INFORMATION_SCHEMA.  The UPDATE statements
-- afterwards are idempotent and safe to run multiple times.

-- ------------------------------------------------------------------
-- RORO_EVENTS_MASTER: add status columns if missing
SET @col_exists := (
  SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'RORO_EVENTS_MASTER'
    AND COLUMN_NAME = 'status'
);
SET @sql := IF(@col_exists = 0,
  'ALTER TABLE `RORO_EVENTS_MASTER` '
  'ADD COLUMN `status` VARCHAR(20) NOT NULL DEFAULT ''draft'' AFTER `isVisible`, '
  'ADD COLUMN `status_updated_at` DATETIME NULL AFTER `status`;',
  'SELECT 1;'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- Populate status and status_updated_at for events
UPDATE `RORO_EVENTS_MASTER`
SET `status` = CASE WHEN `isVisible` = 1 THEN 'published' ELSE 'draft' END,
    `status_updated_at` = COALESCE(`updated_at`, `created_at`, NOW());

-- ------------------------------------------------------------------
-- RORO_TRAVEL_SPOT_MASTER: add status columns if missing
SET @col_exists := (
  SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'RORO_TRAVEL_SPOT_MASTER'
    AND COLUMN_NAME = 'status'
);
SET @sql := IF(@col_exists = 0,
  'ALTER TABLE `RORO_TRAVEL_SPOT_MASTER` '
  'ADD COLUMN `status` VARCHAR(20) NOT NULL DEFAULT ''draft'' AFTER `isVisible`, '
  'ADD COLUMN `status_updated_at` DATETIME NULL AFTER `status`;',
  'SELECT 1;'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- Populate status and status_updated_at for travel spots
UPDATE `RORO_TRAVEL_SPOT_MASTER`
SET `status` = CASE WHEN `isVisible` = 1 THEN 'published' ELSE 'draft' END,
    `status_updated_at` = COALESCE(`updated_at`, `created_at`, NOW());

-- ------------------------------------------------------------------
-- RORO_ONE_POINT_ADVICE_MASTER: add status columns if missing
SET @col_exists := (
  SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'RORO_ONE_POINT_ADVICE_MASTER'
    AND COLUMN_NAME = 'status'
);
SET @sql := IF(@col_exists = 0,
  'ALTER TABLE `RORO_ONE_POINT_ADVICE_MASTER` '
  'ADD COLUMN `status` VARCHAR(20) NOT NULL DEFAULT ''draft'' AFTER `isVisible`, '
  'ADD COLUMN `status_updated_at` DATETIME NULL AFTER `status`;',
  'SELECT 1;'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- Populate status and status_updated_at for one‑point advice
UPDATE `RORO_ONE_POINT_ADVICE_MASTER`
SET `status` = CASE WHEN `isVisible` = 1 THEN 'published' ELSE 'draft' END,
    `status_updated_at` = COALESCE(`updated_at`, `created_at`, NOW());

-- ------------------------------------------------------------------
-- Create history table if it does not exist
CREATE TABLE IF NOT EXISTS `RORO_STATUS_HISTORY` (
    `id` INT NOT NULL AUTO_INCREMENT,
    `table_name` VARCHAR(128) NOT NULL,
    `record_id` VARCHAR(255) NOT NULL,
    `old_status` VARCHAR(20) DEFAULT NULL,
    `new_status` VARCHAR(20) NOT NULL,
    `changed_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `changed_by` BIGINT NULL,
    PRIMARY KEY (`id`),
    KEY `idx_table_record` (`table_name`, `record_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;