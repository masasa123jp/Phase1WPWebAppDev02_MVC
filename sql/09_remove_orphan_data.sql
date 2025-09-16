-- 09_remove_orphan_data.sql
--
-- このスクリプトは、外部キーが設定されていないために残存してしまった
-- 「孤児データ」を削除するための DML を提供します。孤児データとは、
-- 関連する親レコードがすでに削除されているにもかかわらず、参照元テーブルに
-- 残っているデータを指します。例えば削除済みイベントを指すお気に入りや、
-- 存在しないレコード ID を記録しているステータス履歴などが該当します。
--
-- 実行する際は必ずバックアップを取得し、必要に応じて WHERE 句の対象を調整してください。

-- お気に入りテーブルから孤児レコードを削除
-- `target_type` が `event` の場合、イベントIDが存在しない行を削除します。
DELETE f
FROM `RORO_MAP_FAVORITE` AS f
LEFT JOIN `RORO_EVENTS_MASTER` AS e
  ON (f.`target_type` = 'event' AND f.`target_id` = e.`id`)
WHERE f.`target_type` = 'event' AND e.`id` IS NULL;

-- `target_type` が `spot` の場合、スポットIDが存在しない行を削除します。
DELETE f
FROM `RORO_MAP_FAVORITE` AS f
LEFT JOIN `RORO_TRAVEL_SPOT_MASTER` AS s
  ON (f.`target_type` = 'spot' AND f.`target_id` = s.`id`)
WHERE f.`target_type` = 'spot' AND s.`id` IS NULL;

-- `target_type` が `advice` の場合、アドバイスIDが存在しない行を削除します。
DELETE f
FROM `RORO_MAP_FAVORITE` AS f
LEFT JOIN `RORO_ONE_POINT_ADVICE_MASTER` AS a
  ON (f.`target_type` = 'advice' AND f.`target_id` = a.`id`)
WHERE f.`target_type` = 'advice' AND a.`id` IS NULL;

-- ステータス履歴テーブルから孤児レコードを削除
-- RORO_EVENTS_MASTER のレコードが存在しない履歴行を削除
DELETE h
FROM `RORO_STATUS_HISTORY` AS h
LEFT JOIN `RORO_EVENTS_MASTER` AS e
  ON (h.`table_name` = 'RORO_EVENTS_MASTER' AND CAST(h.`record_id` AS UNSIGNED) = e.`id`)
WHERE h.`table_name` = 'RORO_EVENTS_MASTER' AND e.`id` IS NULL;

-- RORO_TRAVEL_SPOT_MASTER のレコードが存在しない履歴行を削除
DELETE h
FROM `RORO_STATUS_HISTORY` AS h
LEFT JOIN `RORO_TRAVEL_SPOT_MASTER` AS s
  ON (h.`table_name` = 'RORO_TRAVEL_SPOT_MASTER' AND CAST(h.`record_id` AS UNSIGNED) = s.`id`)
WHERE h.`table_name` = 'RORO_TRAVEL_SPOT_MASTER' AND s.`id` IS NULL;

-- RORO_ONE_POINT_ADVICE_MASTER のレコードが存在しない履歴行を削除
DELETE h
FROM `RORO_STATUS_HISTORY` AS h
LEFT JOIN `RORO_ONE_POINT_ADVICE_MASTER` AS a
  ON (h.`table_name` = 'RORO_ONE_POINT_ADVICE_MASTER' AND CAST(h.`record_id` AS UNSIGNED) = a.`id`)
WHERE h.`table_name` = 'RORO_ONE_POINT_ADVICE_MASTER' AND a.`id` IS NULL;

-- 必要に応じて他のターゲット型やテーブルについても同様のパターンで追記してください。
