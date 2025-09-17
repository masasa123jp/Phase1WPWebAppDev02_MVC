-- 07_add_misc_indexes_and_foreign_keys.sql (fixed: remove '||' concatenation)

-- 0) created_by 列の追加（無ければ）
SET @col_exists := (
  SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
   WHERE TABLE_SCHEMA = DATABASE()
     AND TABLE_NAME   = 'RORO_EVENTS_MASTER'
     AND COLUMN_NAME  = 'created_by'
);
SET @sql := IF(@col_exists = 0,
  'ALTER TABLE `RORO_EVENTS_MASTER` ADD COLUMN `created_by` BIGINT(20) UNSIGNED NULL;',
  'SELECT 1;'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists := (
  SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
   WHERE TABLE_SCHEMA = DATABASE()
     AND TABLE_NAME   = 'RORO_ONE_POINT_ADVICE_MASTER'
     AND COLUMN_NAME  = 'created_by'
);
SET @sql := IF(@col_exists = 0,
  'ALTER TABLE `RORO_ONE_POINT_ADVICE_MASTER` ADD COLUMN `created_by` BIGINT(20) UNSIGNED NULL;',
  'SELECT 1;'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- 1) RORO_EVENTS_MASTER: index + FK
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

SET @fk_exists := (
  SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLE_CONSTRAINTS
   WHERE TABLE_SCHEMA   = DATABASE()
     AND TABLE_NAME     = 'RORO_EVENTS_MASTER'
     AND CONSTRAINT_NAME = 'fk_roro_events_created_by_users'
     AND CONSTRAINT_TYPE = 'FOREIGN KEY'
);
SET @sql := IF(@fk_exists = 0,
  'ALTER TABLE `RORO_EVENTS_MASTER` ' 
  'ADD CONSTRAINT `fk_roro_events_created_by_users` '
  'FOREIGN KEY (`created_by`) REFERENCES `wp_users`(`ID`) ON DELETE SET NULL;',
  'SELECT 1;'
);
-- ↑ MySQL は隣接文字列を1つに結合して扱うため、このままでも1つの文字列になります。
--   CONCAT を使う場合は下のコメントを参考に。
--   SET @sql := IF(@fk_exists = 0, CONCAT(
--     'ALTER TABLE `RORO_EVENTS_MASTER` ',
--     'ADD CONSTRAINT `fk_roro_events_created_by_users` ',
--     'FOREIGN KEY (`created_by`) REFERENCES `wp_users`(`ID`) ON DELETE SET NULL;'
--   ), 'SELECT 1;');

PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- 2) RORO_TRAVEL_SPOT_MASTER: indexes
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

-- 3) RORO_ONE_POINT_ADVICE_MASTER: index + FK
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

SET @fk_exists := (
  SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLE_CONSTRAINTS
   WHERE TABLE_SCHEMA   = DATABASE()
     AND TABLE_NAME     = 'RORO_ONE_POINT_ADVICE_MASTER'
     AND CONSTRAINT_NAME = 'fk_roro_advice_created_by_users'
     AND CONSTRAINT_TYPE = 'FOREIGN KEY'
);
SET @sql := IF(@fk_exists = 0,
  'ALTER TABLE `RORO_ONE_POINT_ADVICE_MASTER` '
  'ADD CONSTRAINT `fk_roro_advice_created_by_users` '
  'FOREIGN KEY (`created_by`) REFERENCES `wp_users`(`ID`) ON DELETE SET NULL;',
  'SELECT 1;'
);
-- （こちらも CONCAT にするなら上と同様に書き換え可能）

PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- 実行結果の確認
SELECT
  (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME   = 'RORO_EVENTS_MASTER'
      AND COLUMN_NAME  = 'created_by') AS evt_created_by_column_exists,
  (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME   = 'RORO_ONE_POINT_ADVICE_MASTER'
      AND COLUMN_NAME  = 'created_by') AS adv_created_by_column_exists;
