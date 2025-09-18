-- =====================================================================
-- 07_add_misc_indexes_and_foreign_keys.sql
--
-- 概要:
-- このスクリプトは、主要な RORO テーブルに対してインデックスおよび外部キーを追加し、
-- 検索性能とデータ整合性を向上させることを目的としています。
-- また、必要に応じて `created_by` 列を追加し、WordPress ユーザー（wp_users.ID）との
-- 外部キー参照を設定します。
--
-- 主な処理内容:
--  0) RORO_EVENTS_MASTER と RORO_ONE_POINT_ADVICE_MASTER に created_by 列を追加
--  1) RORO_EVENTS_MASTER にインデックスと外部キーを追加
--  2) RORO_TRAVEL_SPOT_MASTER にインデックスを追加
--  3) RORO_ONE_POINT_ADVICE_MASTER にインデックスと外部キーを追加
--
-- 各処理は INFORMATION_SCHEMA を参照して既存かどうかを確認し、
-- 存在しない場合のみ ALTER 文を実行するため、再実行しても安全です。
--
-- =====================================================================

-- 0) created_by 列の追加（存在しない場合のみ）
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

-- 1) RORO_EVENTS_MASTER: インデックスと外部キーの追加
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
-- ※MySQL は隣接する文字列を 1 つに結合して扱うため CONCAT は不要。
--   CONCAT を使う場合はコメントのサンプルコードを参照。

PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- 2) RORO_TRAVEL_SPOT_MASTER: インデックスの追加
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

-- 3) RORO_ONE_POINT_ADVICE_MASTER: インデックスと外部キーの追加
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
-- ※こちらも CONCAT を使う場合は上記と同様に書き換え可能。

PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- 実行結果の確認クエリ
SELECT
  (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME   = 'RORO_EVENTS_MASTER'
      AND COLUMN_NAME  = 'created_by') AS evt_created_by_column_exists,
  (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME   = 'RORO_ONE_POINT_ADVICE_MASTER'
      AND COLUMN_NAME  = 'created_by') AS adv_created_by_column_exists;
