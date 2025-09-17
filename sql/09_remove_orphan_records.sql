-- 09_remove_orphan_records.sql
--
-- Deletes records that refer to missing parent rows (so‑called
-- "orphan" records).  This script is purely DML and requires no
-- structural changes, so it runs the same regardless of whether it
-- has been executed previously.  Always back up the database before
-- deleting data in bulk.

-- ------------------------------------------------------------------
-- Remove favorites pointing to non‑existent events
DELETE f
FROM `RORO_MAP_FAVORITE` AS f
LEFT JOIN `RORO_EVENTS_MASTER` AS e
  ON (f.`target_type` = 'event' AND f.`target_id` = e.`id`)
WHERE f.`target_type` = 'event' AND e.`id` IS NULL;

-- Remove favorites pointing to non‑existent spots
DELETE f
FROM `RORO_MAP_FAVORITE` AS f
LEFT JOIN `RORO_TRAVEL_SPOT_MASTER` AS s
  ON (f.`target_type` = 'spot' AND f.`target_id` = s.`id`)
WHERE f.`target_type` = 'spot' AND s.`id` IS NULL;

-- Remove favorites pointing to non‑existent advice
DELETE f
FROM `RORO_MAP_FAVORITE` AS f
LEFT JOIN `RORO_ONE_POINT_ADVICE_MASTER` AS a
  ON (f.`target_type` = 'advice' AND f.`target_id` = a.`id`)
WHERE f.`target_type` = 'advice' AND a.`id` IS NULL;

-- ------------------------------------------------------------------
-- Remove status history referencing deleted events
DELETE h
FROM `RORO_STATUS_HISTORY` AS h
LEFT JOIN `RORO_EVENTS_MASTER` AS e
  ON (h.`table_name` = 'RORO_EVENTS_MASTER' AND CAST(h.`record_id` AS UNSIGNED) = e.`id`)
WHERE h.`table_name` = 'RORO_EVENTS_MASTER' AND e.`id` IS NULL;

-- Remove status history referencing deleted spots
DELETE h
FROM `RORO_STATUS_HISTORY` AS h
LEFT JOIN `RORO_TRAVEL_SPOT_MASTER` AS s
  ON (h.`table_name` = 'RORO_TRAVEL_SPOT_MASTER' AND CAST(h.`record_id` AS UNSIGNED) = s.`id`)
WHERE h.`table_name` = 'RORO_TRAVEL_SPOT_MASTER' AND s.`id` IS NULL;

-- Remove status history referencing deleted advice
DELETE h
FROM `RORO_STATUS_HISTORY` AS h
LEFT JOIN `RORO_ONE_POINT_ADVICE_MASTER` AS a
  ON (h.`table_name` = 'RORO_ONE_POINT_ADVICE_MASTER' AND CAST(h.`record_id` AS UNSIGNED) = a.`id`)
WHERE h.`table_name` = 'RORO_ONE_POINT_ADVICE_MASTER' AND a.`id` IS NULL;

-- Extend the pattern above for any additional target types present in your schema.