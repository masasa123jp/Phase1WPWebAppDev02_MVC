-- =====================================================================
-- 09_02_survey_orphan_records.sql
--
-- 概要:
-- このスクリプトは、親テーブルに対応する行が存在しないレコード
-- （いわゆる「孤児レコード」）を **抽出** するための調査用クエリ集です。
-- データを削除せず、孤児候補を一覧表示するのみの安全なスクリプトです。
--
-- 目的:
--  - RORO_MAP_FAVORITE が参照するイベント／スポット／アドバイスの整合性を点検
--  - RORO_STATUS_HISTORY が参照する各テーブル（イベント／スポット／アドバイス）との
--    参照整合性を点検
--
-- 出力:
--  - お気に入り（event / spot / advice）で、親が存在しない行の一覧
--  - ステータス履歴（events / spots / advice）で、親が存在しない行の一覧
--
-- 注意:
--  - 本スクリプトは SELECT のみを実行します（DML/DDL は含みません）。
--  - MAP_FAVORITE の参照列が `target_id` と `source_id` のどちらを採用しているか
--    自動判定してから実行します。両方存在しない場合はエラーを返します。
-- =====================================================================

/* どの列名を使うか自動判定（target_id / source_id） */
SET @fav_id_col := (
  SELECT CASE
    WHEN EXISTS (
      SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS
      WHERE TABLE_SCHEMA = DATABASE()
        AND TABLE_NAME = 'RORO_MAP_FAVORITE'
        AND COLUMN_NAME = 'target_id'
    ) THEN 'target_id'
    WHEN EXISTS (
      SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS
      WHERE TABLE_SCHEMA = DATABASE()
        AND TABLE_NAME = 'RORO_MAP_FAVORITE'
        AND COLUMN_NAME = 'source_id'
    ) THEN 'source_id'
    ELSE NULL
  END
);

/* 安全チェック：列が見つからない場合はエラーを返す */
SELECT IF(@fav_id_col IS NULL,
  'ERROR: RORO_MAP_FAVORITE に target_id / source_id が見つかりません。スキーマを確認してください。',
  CONCAT('USING FAV COL = ', @fav_id_col)
) AS info;

/* 1) お気に入り（イベント）：親が存在しない行を抽出（event_id基準） */
SET @sql_evt = CONCAT(
  'SELECT f.* ',
  'FROM `RORO_MAP_FAVORITE` AS f ',
  'LEFT JOIN `RORO_EVENTS_MASTER` AS e ',
  '  ON (f.`target_type` = ''event'' AND f.`', @fav_id_col, '` = e.`event_id`) ',
  'WHERE f.`target_type` = ''event'' AND e.`event_id` IS NULL'
);
PREPARE stmt FROM @sql_evt; EXECUTE stmt; DEALLOCATE PREPARE stmt;

/* 2) お気に入り（スポット）：親が存在しない行を抽出（TSM_ID基準） */
SET @sql_spt = CONCAT(
  'SELECT f.* ',
  'FROM `RORO_MAP_FAVORITE` AS f ',
  'LEFT JOIN `RORO_TRAVEL_SPOT_MASTER` AS s ',
  '  ON (f.`target_type` = ''spot'' AND f.`', @fav_id_col, '` = s.`TSM_ID`) ',
  'WHERE f.`target_type` = ''spot'' AND s.`TSM_ID` IS NULL'
);
PREPARE stmt FROM @sql_spt; EXECUTE stmt; DEALLOCATE PREPARE stmt;

/* 3) お気に入り（アドバイス）：親が存在しない行を抽出（OPAM_ID基準） */
SET @sql_adv = CONCAT(
  'SELECT f.* ',
  'FROM `RORO_MAP_FAVORITE` AS f ',
  'LEFT JOIN `RORO_ONE_POINT_ADVICE_MASTER` AS a ',
  '  ON (f.`target_type` = ''advice'' AND f.`', @fav_id_col, '` = a.`OPAM_ID`) ',
  'WHERE f.`target_type` = ''advice'' AND a.`OPAM_ID` IS NULL'
);
PREPARE stmt FROM @sql_adv; EXECUTE stmt; DEALLOCATE PREPARE stmt;

/* 4) ステータス履歴（イベント）：親が存在しない行を抽出 */
SELECT h.*
FROM `RORO_STATUS_HISTORY` AS h
LEFT JOIN `RORO_EVENTS_MASTER` AS e
  ON (h.`table_name` = 'RORO_EVENTS_MASTER'
      AND (h.`record_id` = e.`event_id` OR CAST(h.`record_id` AS UNSIGNED) = e.`id`))
WHERE h.`table_name` = 'RORO_EVENTS_MASTER' AND e.`event_id` IS NULL AND e.`id` IS NULL;

/* 5) ステータス履歴（スポット）：親が存在しない行を抽出 */
SELECT h.*
FROM `RORO_STATUS_HISTORY` AS h
LEFT JOIN `RORO_TRAVEL_SPOT_MASTER` AS s
  ON (h.`table_name` = 'RORO_TRAVEL_SPOT_MASTER'
      AND (h.`record_id` = s.`TSM_ID` OR CAST(h.`record_id` AS UNSIGNED) = s.`id`))
WHERE h.`table_name` = 'RORO_TRAVEL_SPOT_MASTER' AND s.`TSM_ID` IS NULL AND s.`id` IS NULL;

/* 6) ステータス履歴（アドバイス）：親が存在しない行を抽出 */
SELECT h.*
FROM `RORO_STATUS_HISTORY` AS h
LEFT JOIN `RORO_ONE_POINT_ADVICE_MASTER` AS a
  ON (h.`table_name` = 'RORO_ONE_POINT_ADVICE_MASTER'
      AND (h.`record_id` = a.`OPAM_ID`))
WHERE h.`table_name` = 'RORO_ONE_POINT_ADVICE_MASTER' AND a.`OPAM_ID` IS NULL;
