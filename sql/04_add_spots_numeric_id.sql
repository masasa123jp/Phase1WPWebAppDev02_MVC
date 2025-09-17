-- 04_add_spots_numeric_id.sql (revised)
--
-- 目的:
--   1) RORO_TRAVEL_SPOT_MASTER に数値オートインクリメント列 `id` を追加
--   2) `TSM_ID` の一意性が保てる場合のみ UNIQUE を付与
--      重複がある場合は通常 INDEX を付与（UNIQUE は作らない）
-- ポイント:
--   - MySQL 5.7 では AUTO_INCREMENT 列にはインデックスが必須のため、
--     `id` を追加する際に UNIQUE を併記して満たす。
--   - すべて INFORMATION_SCHEMA で存在確認し、冪等に実行できる。

/* ------------------------------------------------------------------
 * 1) `id` 列の追加（存在しなければ追加）
 * ------------------------------------------------------------------ */
SET @col_exists := (
  SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
   WHERE TABLE_SCHEMA = DATABASE()
     AND TABLE_NAME   = 'RORO_TRAVEL_SPOT_MASTER'
     AND COLUMN_NAME  = 'id'
);

-- AUTO_INCREMENT 列には索引が必要なため、UNIQUE を併記して追加する
SET @sql := IF(@col_exists = 0,
  'ALTER TABLE `RORO_TRAVEL_SPOT_MASTER` '                                                   --
  'ADD COLUMN `id` INT UNSIGNED NOT NULL AUTO_INCREMENT UNIQUE FIRST;',
  'SELECT 1;'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

/* ------------------------------------------------------------------
 * 2) `TSM_ID` の一意化/インデックス付与
 *    - 重複が 0 件なら UNIQUE を作成
 *    - 重複があるなら UNIQUE は作らず、通常の INDEX を作成
 * ------------------------------------------------------------------ */

-- 2-1) 既に UNIQUE あり？（uniq_tsm_id）
SET @uniq_exists := (
  SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS
   WHERE TABLE_SCHEMA = DATABASE()
     AND TABLE_NAME   = 'RORO_TRAVEL_SPOT_MASTER'
     AND INDEX_NAME   = 'uniq_tsm_id'
);

-- 2-2) `TSM_ID` の重複件数を取得
SET @tsm_dups := (
  SELECT COUNT(*) FROM (
    SELECT `TSM_ID`
      FROM `RORO_TRAVEL_SPOT_MASTER`
     GROUP BY `TSM_ID`
    HAVING COUNT(*) > 1
  ) AS d
);

-- 2-3) 重複 0 かつ UNIQUE 未作成なら UNIQUE を作成
SET @sql := IF(@uniq_exists = 0 AND @tsm_dups = 0,
  'ALTER TABLE `RORO_TRAVEL_SPOT_MASTER` ADD UNIQUE KEY `uniq_tsm_id` (`TSM_ID`);',
  'SELECT 1;'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- 2-4) 重複がある場合は通常 INDEX（idx_tsm_id）を作成（無ければ）
SET @idx_exists := (
  SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS
   WHERE TABLE_SCHEMA = DATABASE()
     AND TABLE_NAME   = 'RORO_TRAVEL_SPOT_MASTER'
     AND INDEX_NAME   = 'idx_tsm_id'
);
SET @sql := IF(@tsm_dups > 0 AND @idx_exists = 0,
  'CREATE INDEX `idx_tsm_id` ON `RORO_TRAVEL_SPOT_MASTER`(`TSM_ID`);',
  'SELECT 1;'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- 実行状況の参考情報を返す（phpMyAdmin で結果を確認できます）
SELECT
  @col_exists   AS existed_id_column_before,
  @tsm_dups     AS tsm_id_duplicate_count,
  @uniq_exists  AS uniq_tsm_id_index_existed_before;
