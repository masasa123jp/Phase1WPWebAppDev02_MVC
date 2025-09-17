-- 03_create_cleanup_event.sql
--
-- Defines a MySQL event to automatically purge aged analytics logs from the
-- magazine view, click and daily summary tables.  The event runs once
-- per week.  If the event already exists it is dropped and recreated.
--
-- Note: To use MySQL events you must enable the event scheduler:
--   SET GLOBAL event_scheduler = ON;

DROP EVENT IF EXISTS roro_analytics_cleanup;

CREATE EVENT roro_analytics_cleanup
ON SCHEDULE EVERY 1 WEEK
STARTS CURRENT_TIMESTAMP + INTERVAL 1 WEEK
DO
BEGIN
  -- Remove view logs older than 180 days
  DELETE FROM `RORO_MAGAZINE_VIEW`
    WHERE `viewed_at` < (CURRENT_DATE - INTERVAL 180 DAY);

  -- Remove click logs older than 180 days
  DELETE FROM `RORO_MAGAZINE_CLICK`
    WHERE `clicked_at` < (CURRENT_DATE - INTERVAL 180 DAY);

  -- Remove daily aggregates older than 365 days
  DELETE FROM `RORO_MAGAZINE_DAILY`
    WHERE `date_key` < (CURRENT_DATE - INTERVAL 365 DAY);
END;