-- 07_add_misc_indexes_and_foreign_keys.sql
--
-- Adds a handful of indexes and foreign key constraints across several
-- tables.  Because MySQL does not support `ADD INDEX IF NOT EXISTS` or
-- `ADD CONSTRAINT IF NOT EXISTS`, we query INFORMATION_SCHEMA before
-- executing each DDL.  This allows the script to be re-run safely.

-- ------------------------------------------------------------------
-- RORO_EVENTS_MASTER: index on created_by
SET @idx_exists := (
  SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'RORO_EVENTS_MASTER'
    AND INDEX_NAME = 'idx_roro_events_created_by'
);
SET @sql := IF(@idx_exists = 0,
  'ALTER TABLE `RORO_EVENTS_MASTER` ADD INDEX `idx_roro_events_created_by` (`created_by`);',
  'SELECT 1;'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- RORO_EVENTS_MASTER: foreign key linking created_by to wp_users.ID
SET @fk_exists := (
  SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLE_CONSTRAINTS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'RORO_EVENTS_MASTER'
    AND CONSTRAINT_NAME = 'fk_roro_events_created_by_users'
    AND CONSTRAINT_TYPE = 'FOREIGN KEY'
);
SET @sql := IF(@fk_exists = 0,
  'ALTER TABLE `RORO_EVENTS_MASTER` ADD CONSTRAINT `fk_roro_events_created_by_users` FOREIGN KEY (`created_by`) REFERENCES `wp_users`(`ID`) ON DELETE SET NULL;',
  'SELECT 1;'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- ------------------------------------------------------------------
-- RORO_TRAVEL_SPOT_MASTER: index on prefecture
SET @idx_exists := (
  SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'RORO_TRAVEL_SPOT_MASTER'
    AND INDEX_NAME = 'idx_roro_spots_prefecture'
);
SET @sql := IF(@idx_exists = 0,
  'ALTER TABLE `RORO_TRAVEL_SPOT_MASTER` ADD INDEX `idx_roro_spots_prefecture` (`prefecture`);',
  'SELECT 1;'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- RORO_TRAVEL_SPOT_MASTER: index on status
SET @idx_exists := (
  SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'RORO_TRAVEL_SPOT_MASTER'
    AND INDEX_NAME = 'idx_roro_spots_status'
);
SET @sql := IF(@idx_exists = 0,
  'ALTER TABLE `RORO_TRAVEL_SPOT_MASTER` ADD INDEX `idx_roro_spots_status` (`status`);',
  'SELECT 1;'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- ------------------------------------------------------------------
-- RORO_ONE_POINT_ADVICE_MASTER: index on created_by
SET @idx_exists := (
  SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'RORO_ONE_POINT_ADVICE_MASTER'
    AND INDEX_NAME = 'idx_roro_advice_created_by'
);
SET @sql := IF(@idx_exists = 0,
  'ALTER TABLE `RORO_ONE_POINT_ADVICE_MASTER` ADD INDEX `idx_roro_advice_created_by` (`created_by`);',
  'SELECT 1;'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- RORO_ONE_POINT_ADVICE_MASTER: foreign key linking created_by to wp_users.ID
SET @fk_exists := (
  SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLE_CONSTRAINTS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'RORO_ONE_POINT_ADVICE_MASTER'
    AND CONSTRAINT_NAME = 'fk_roro_advice_created_by_users'
    AND CONSTRAINT_TYPE = 'FOREIGN KEY'
);
SET @sql := IF(@fk_exists = 0,
  'ALTER TABLE `RORO_ONE_POINT_ADVICE_MASTER` ADD CONSTRAINT `fk_roro_advice_created_by_users` FOREIGN KEY (`created_by`) REFERENCES `wp_users`(`ID`) ON DELETE SET NULL;',
  'SELECT 1;'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;