-- =====================================================================
-- 09_03_remove_orphan_records.sql
--
-- 概要:
-- このスクリプトは、親テーブルに存在しない行を参照している
-- 「孤児レコード（orphan records）」を削除します。
-- 本スクリプトは DML（データ操作）専用であり、スキーマ変更は行いません。
-- そのため、過去に実行済みかどうかに関わらず常に同じ結果を返します。
-- 大量削除を伴う可能性があるため、本番環境で実行する前には
-- 必ずデータベースのバックアップを取得してください。
--
-- 主な処理内容:
--  ・存在しないイベントに紐づくお気に入りを削除
--  ・存在しないスポットに紐づくお気に入りを削除
--  ・存在しないアドバイスに紐づくお気に入りを削除
--  ・削除済みイベントに紐づくステータス履歴を削除
--  ・削除済みスポットに紐づくステータス履歴を削除
--  ・削除済みアドバイスに紐づくステータス履歴を削除
--
-- =====================================================================

-- ------------------------------------------------------------------
-- 存在しないイベントに紐づくお気に入りを削除
DELETE f
FROM `RORO_MAP_FAVORITE` AS f
LEFT JOIN `RORO_EVENTS_MASTER` AS e
  ON (f.`target_type` = 'event' AND f.`target_id` = e.`id`)
WHERE f.`target_type` = 'event' AND e.`id` IS NULL;

-- 存在しないスポットに紐づくお気に入りを削除
DELETE f
FROM `RORO_MAP_FAVORITE` AS f
LEFT JOIN `RORO_TRAVEL_SPOT_MASTER` AS s
  ON (f.`target_type` = 'spot' AND f.`target_id` = s.`id`)
WHERE f.`target_type` = 'spot' AND s.`id` IS NULL;

-- 存在しないアドバイスに紐づくお気に入りを削除
DELETE f
FROM `RORO_MAP_FAVORITE` AS f
LEFT JOIN `RORO_ONE_POINT_ADVICE_MASTER` AS a
  ON (f.`target_type` = 'advice' AND f.`target_id` = a.`id`)
WHERE f.`target_type` = 'advice' AND a.`id` IS NULL;

-- ------------------------------------------------------------------
-- 削除済みイベントに紐づくステータス履歴を削除
DELETE h
FROM `RORO_STATUS_HISTORY` AS h
LEFT JOIN `RORO_EVENTS_MASTER` AS e
  ON (h.`table_name` = 'RORO_EVENTS_MASTER' AND CAST(h.`record_id` AS UNSIGNED) = e.`id`)
WHERE h.`table_name` = 'RORO_EVENTS_MASTER' AND e.`id` IS NULL;

-- 削除済みスポットに紐づくステータス履歴を削除
DELETE h
FROM `RORO_STATUS_HISTORY` AS h
LEFT JOIN `RORO_TRAVEL_SPOT_MASTER` AS s
  ON (h.`table_name` = 'RORO_TRAVEL_SPOT_MASTER' AND CAST(h.`record_id` AS UNSIGNED) = s.`id`)
WHERE h.`table_name` = 'RORO_TRAVEL_SPOT_MASTER' AND s.`id` IS NULL;

-- 削除済みアドバイスに紐づくステータス履歴を削除
DELETE h
FROM `RORO_STATUS_HISTORY` AS h
LEFT JOIN `RORO_ONE_POINT_ADVICE_MASTER` AS a
  ON (h.`table_name` = 'RORO_ONE_POINT_ADVICE_MASTER' AND CAST(h.`record_id` AS UNSIGNED) = a.`id`)
WHERE h.`table_name` = 'RORO_ONE_POINT_ADVICE_MASTER' AND a.`id` IS NULL;

-- 上記のパターンを拡張することで、スキーマ内の他の対象タイプにも対応可能です。
