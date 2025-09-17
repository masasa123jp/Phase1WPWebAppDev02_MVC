-- 04_add_spots_numeric_id.sql
--
-- Introduces a numeric auto‑increment `id` column on the RORO_TRAVEL_SPOT_MASTER table and
-- ensures that the existing string identifier `TSM_ID` has a unique index.  Because
-- MySQL lacks native support for `ADD COLUMN IF NOT EXISTS`, we perform explicit
-- existence checks before running the ALTER statements.  Running this script
-- repeatedly will not raise errors.

-- ------------------------------------------------------------------
-- 1. Add numeric `id` column if it does not exist
SET @col_exists := (
  SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'RORO_TRAVEL_SPOT_MASTER'
    AND COLUMN_NAME = 'id'
);
SET @sql := IF(@col_exists = 0,
  'ALTER TABLE `RORO_TRAVEL_SPOT_MASTER` ADD COLUMN `id` INT NOT NULL AUTO_INCREMENT FIRST;',
  'SELECT 1;'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- 2. Add unique key on TSM_ID if it does not exist
SET @idx_exists := (
  SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'RORO_TRAVEL_SPOT_MASTER'
    AND INDEX_NAME = 'uniq_tsm_id'
);
SET @sql := IF(@idx_exists = 0,
  'ALTER TABLE `RORO_TRAVEL_SPOT_MASTER` ADD UNIQUE KEY `uniq_tsm_id` (`TSM_ID`);',
  'SELECT 1;'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- Note: If you require the `id` column to be the primary key, you may
-- perform an additional `ALTER TABLE ... ADD PRIMARY KEY (id)` after
-- existing primary keys are resolved.  This script adds the column and
-- unique index only, matching the original migration.