-- 05_add_status_to_core_tables.sql
--
-- 主要な RORO テーブルに `status` および `status_updated_at` 列を追加し、
-- 既存の `isVisible` フラグに基づいて値を設定します。
-- また、このマイグレーションでは、まだ存在しない場合に
-- ステータス履歴テーブルを作成します。
-- MySQL は `ADD COLUMN IF NOT EXISTS` を理解しないため、
-- 各 ALTER 文は INFORMATION_SCHEMA を用いた存在確認でガードしています。
-- その後の UPDATE 文は冪等であり、複数回実行しても安全です。

-- ------------------------------------------------------------------
-- RORO_EVENTS_MASTER: status 列を追加（存在しない場合のみ）
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

-- イベントテーブルの status および status_updated_at を補完
UPDATE `RORO_EVENTS_MASTER`
SET `status` = CASE WHEN `isVisible` = 1 THEN 'published' ELSE 'draft' END,
    `status_updated_at` = COALESCE(`updated_at`, `created_at`, NOW());

-- ------------------------------------------------------------------
-- RORO_TRAVEL_SPOT_MASTER: status 列を追加（存在しない場合のみ）
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

-- トラベルスポットテーブルの status および status_updated_at を補完
UPDATE `RORO_TRAVEL_SPOT_MASTER`
SET `status` = CASE WHEN `isVisible` = 1 THEN 'published' ELSE 'draft' END,
    `status_updated_at` = COALESCE(`updated_at`, `created_at`, NOW());

-- ------------------------------------------------------------------
-- RORO_ONE_POINT_ADVICE_MASTER: status 列を追加（存在しない場合のみ）
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

-- ワンポイントアドバイステーブルの status および status_updated_at を補完
UPDATE `RORO_ONE_POINT_ADVICE_MASTER`
SET `status` = CASE WHEN `isVisible` = 1 THEN 'published' ELSE 'draft' END,
    `status_updated_at` = COALESCE(`updated_at`, `created_at`, NOW());

-- ------------------------------------------------------------------
-- ステータス履歴テーブルの作成（存在しない場合のみ）
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
