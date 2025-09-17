-- 02_add_customer_indexes_and_fks.sql
--
-- Adds indexes and foreign key constraints to customer-related tables.
-- 元のマイグレーションでは `CREATE INDEX IF NOT EXISTS` を使っていましたが、
-- MySQL 5.7 では未サポートのため、INFORMATION_SCHEMA を参照して存在確認を行い、
-- 冪等に実行できるようにしています。
-- 今回の環境では親テーブル RORO_CUSTOMER の主キーは `id` ではなく
-- `customer_id` であるため、参照先は `RORO_CUSTOMER(customer_id)` に統一します。

/* ======================================================================
 * 1) 子テーブル側の customer_id にインデックスを付与（無ければ作成）
 * ====================================================================== */

-- Index on RORO_PET.customer_id
SET @idx_exists := (
  SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.STATISTICS
   WHERE TABLE_SCHEMA = DATABASE()
     AND TABLE_NAME = 'RORO_PET'
     AND INDEX_NAME = 'idx_roro_pet_customer_id'
);
SET @sql := IF(@idx_exists = 0,
  'CREATE INDEX `idx_roro_pet_customer_id` ON `RORO_PET`(`customer_id`);',
  'SELECT 1;'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- Index on RORO_ADDRESS.customer_id
SET @idx_exists := (
  SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.STATISTICS
   WHERE TABLE_SCHEMA = DATABASE()
     AND TABLE_NAME = 'RORO_ADDRESS'
     AND INDEX_NAME = 'idx_roro_address_customer_id'
);
SET @sql := IF(@idx_exists = 0,
  'CREATE INDEX `idx_roro_address_customer_id` ON `RORO_ADDRESS`(`customer_id`);',
  'SELECT 1;'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- Index on RORO_USER_LINK_WP.customer_id
SET @idx_exists := (
  SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.STATISTICS
   WHERE TABLE_SCHEMA = DATABASE()
     AND TABLE_NAME = 'RORO_USER_LINK_WP'
     AND INDEX_NAME = 'idx_roro_user_link_wp_customer_id'
);
SET @sql := IF(@idx_exists = 0,
  'CREATE INDEX `idx_roro_user_link_wp_customer_id` ON `RORO_USER_LINK_WP`(`customer_id`);',
  'SELECT 1;'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

/* ======================================================================
 * 2) 親テーブル RORO_CUSTOMER 側の参照列にインデックスがあることを保証
 *    （PRIMARY KEY が既にあればヒットします。無ければ通常INDEXを追加）
 * ====================================================================== */

SET @parent_idx_exists := (
  SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.STATISTICS
   WHERE TABLE_SCHEMA = DATABASE()
     AND TABLE_NAME   = 'RORO_CUSTOMER'
     AND COLUMN_NAME  = 'customer_id'
     AND SEQ_IN_INDEX = 1
   LIMIT 1
);
SET @sql := IF(@parent_idx_exists = 0,
  'ALTER TABLE `RORO_CUSTOMER` ADD INDEX `idx_roro_customer_customer_id` (`customer_id`);',
  'SELECT 1;'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

/* ======================================================================
 * 3) 外部キー制約の作成（存在しない場合のみ追加）
 *    参照先は RORO_CUSTOMER(customer_id) に統一
 * ====================================================================== */

-- Foreign key for RORO_PET.customer_id -> RORO_CUSTOMER.customer_id
SET @fk_exists := (
  SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.TABLE_CONSTRAINTS
   WHERE TABLE_SCHEMA   = DATABASE()
     AND TABLE_NAME     = 'RORO_PET'
     AND CONSTRAINT_TYPE= 'FOREIGN KEY'
     AND CONSTRAINT_NAME= 'fk_roro_pet_customer'
);
SET @sql := IF(@fk_exists = 0,
  'ALTER TABLE `RORO_PET` '                                  --
  'ADD CONSTRAINT `fk_roro_pet_customer` '                   --
  'FOREIGN KEY (`customer_id`) '                             --
  'REFERENCES `RORO_CUSTOMER`(`customer_id`) '               --
  'ON DELETE CASCADE;',
  'SELECT 1;'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- Foreign key for RORO_ADDRESS.customer_id -> RORO_CUSTOMER.customer_id
SET @fk_exists := (
  SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.TABLE_CONSTRAINTS
   WHERE TABLE_SCHEMA   = DATABASE()
     AND TABLE_NAME     = 'RORO_ADDRESS'
     AND CONSTRAINT_TYPE= 'FOREIGN KEY'
     AND CONSTRAINT_NAME= 'fk_roro_address_customer'
);
SET @sql := IF(@fk_exists = 0,
  'ALTER TABLE `RORO_ADDRESS` '                              --
  'ADD CONSTRAINT `fk_roro_address_customer` '               --
  'FOREIGN KEY (`customer_id`) '                             --
  'REFERENCES `RORO_CUSTOMER`(`customer_id`) '               --
  'ON DELETE CASCADE;',
  'SELECT 1;'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

/* ======================================================================
 * 4) 備考
 *  - RORO_USER_LINK_WP.customer_id についてはアプリ側で削除制御を行うため、
 *    本スクリプトでは外部キーを作成しません（既存方針を踏襲）。
 *  - 本スクリプトは複数回実行しても安全（既存オブジェクトがあれば NO-OP）。
 * ====================================================================== */
