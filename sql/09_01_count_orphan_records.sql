/* =============================
   0) 前処理：参照列の自動判定
   ============================= */
SET @fav_id_col := (
  SELECT CASE
    WHEN EXISTS (
      SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS
      WHERE TABLE_SCHEMA = DATABASE()
        AND TABLE_NAME   = 'RORO_MAP_FAVORITE'
        AND COLUMN_NAME  = 'target_id'
    ) THEN 'target_id'
    WHEN EXISTS (
      SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS
      WHERE TABLE_SCHEMA = DATABASE()
        AND TABLE_NAME   = 'RORO_MAP_FAVORITE'
        AND COLUMN_NAME  = 'source_id'
    ) THEN 'source_id'
    ELSE NULL
  END
);

/* 列が見つからない場合はエラーメッセージ（SELECT）を返して終了 */
SELECT IF(@fav_id_col IS NULL,
  'ERROR: RORO_MAP_FAVORITE に target_id / source_id が見つかりません。テーブル構造を確認してください。',
  CONCAT('USING FAV COL = ', @fav_id_col)
) AS info;

/* EVENTS / SPOTS 側の id 列の有無（履歴結合の最適化用） */
SET @has_evt_id := (
  SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'RORO_EVENTS_MASTER' AND COLUMN_NAME = 'id'
);
SET @has_spt_id := (
  SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'RORO_TRAVEL_SPOT_MASTER' AND COLUMN_NAME = 'id'
);

/* ===========================================
   1) お気に入り（event / spot / advice）の孤児数
   =========================================== */
SET @sql_evt = CONCAT(
  'SELECT ''favorites_event'' AS bucket, COUNT(*) AS orphan_count ',
  'FROM `RORO_MAP_FAVORITE` f ',
  'LEFT JOIN `RORO_EVENTS_MASTER` e ',
  '  ON (f.`target_type` = ''event'' AND BINARY f.`', @fav_id_col, '` = BINARY e.`event_id`) ',
  'WHERE f.`target_type` = ''event'' AND e.`event_id` IS NULL'
);

SET @sql_spt = CONCAT(
  'SELECT ''favorites_spot'' AS bucket, COUNT(*) AS orphan_count ',
  'FROM `RORO_MAP_FAVORITE` f ',
  'LEFT JOIN `RORO_TRAVEL_SPOT_MASTER` s ',
  '  ON (f.`target_type` = ''spot'' AND BINARY f.`', @fav_id_col, '` = BINARY s.`TSM_ID`) ',
  'WHERE f.`target_type` = ''spot'' AND s.`TSM_ID` IS NULL'
);

SET @sql_adv = CONCAT(
  'SELECT ''favorites_advice'' AS bucket, COUNT(*) AS orphan_count ',
  'FROM `RORO_MAP_FAVORITE` f ',
  'LEFT JOIN `RORO_ONE_POINT_ADVICE_MASTER` a ',
  '  ON (f.`target_type` = ''advice'' AND BINARY f.`', @fav_id_col, '` = BINARY a.`OPAM_ID`) ',
  'WHERE f.`target_type` = ''advice'' AND a.`OPAM_ID` IS NULL'
);

/* ===========================================
   2) ステータス履歴（event / spot / advice）の孤児数
   =========================================== */
SET @sql_hist_evt = CONCAT(
  'SELECT ''history_event'' AS bucket, COUNT(*) AS orphan_count ',
  'FROM `RORO_STATUS_HISTORY` h ',
  'LEFT JOIN `RORO_EVENTS_MASTER` e ',
  '  ON (h.`table_name` = ''RORO_EVENTS_MASTER'' ',
  '      AND (BINARY h.`record_id` = BINARY e.`event_id`',
  IF(@has_evt_id > 0,
     '           OR (h.`record_id` REGEXP ''^[0-9]+$'' AND CAST(h.`record_id` AS UNSIGNED)=e.`id`)',
     ''),
  '      )) ',
  'WHERE h.`table_name` = ''RORO_EVENTS_MASTER'' AND e.`event_id` IS NULL',
  IF(@has_evt_id > 0, ' AND e.`id` IS NULL', '')
);

SET @sql_hist_spt = CONCAT(
  'SELECT ''history_spot'' AS bucket, COUNT(*) AS orphan_count ',
  'FROM `RORO_STATUS_HISTORY` h ',
  'LEFT JOIN `RORO_TRAVEL_SPOT_MASTER` s ',
  '  ON (h.`table_name` = ''RORO_TRAVEL_SPOT_MASTER'' ',
  '      AND (BINARY h.`record_id` = BINARY s.`TSM_ID`',
  IF(@has_spt_id > 0,
     '           OR (h.`record_id` REGEXP ''^[0-9]+$'' AND CAST(h.`record_id` AS UNSIGNED)=s.`id`)',
     ''),
  '      )) ',
  'WHERE h.`table_name` = ''RORO_TRAVEL_SPOT_MASTER'' AND s.`TSM_ID` IS NULL',
  IF(@has_spt_id > 0, ' AND s.`id` IS NULL', '')
);

SET @sql_hist_adv = CONCAT(
  'SELECT ''history_advice'' AS bucket, COUNT(*) AS orphan_count ',
  'FROM `RORO_STATUS_HISTORY` h ',
  'LEFT JOIN `RORO_ONE_POINT_ADVICE_MASTER` a ',
  '  ON (h.`table_name` = ''RORO_ONE_POINT_ADVICE_MASTER'' ',
  '      AND BINARY h.`record_id` = BINARY a.`OPAM_ID`) ',
  'WHERE h.`table_name` = ''RORO_ONE_POINT_ADVICE_MASTER'' AND a.`OPAM_ID` IS NULL'
);

/* 3) すべてまとめて結果表示（UNION ALL） */
SET @sql_all = CONCAT(
  @sql_evt, ' UNION ALL ', @sql_spt, ' UNION ALL ', @sql_adv, ' UNION ALL ',
  @sql_hist_evt, ' UNION ALL ', @sql_hist_spt, ' UNION ALL ', @sql_hist_adv
);

PREPARE stmt FROM @sql_all; EXECUTE stmt; DEALLOCATE PREPARE stmt;
