-- SQL 03: Maintenance and cleanup policies

-- This script defines a MySQL event to automatically remove old analytics logs.  It runs weekly and keeps:
--   * raw view/click logs for 180 days
--   * daily aggregates for 365 days

-- You must have the EVENT scheduler enabled in MySQL for this to work.  Alternatively, WordPress handles cleanup via WP Cron.

DROP EVENT IF EXISTS roro_analytics_cleanup;

CREATE EVENT roro_analytics_cleanup
ON SCHEDULE EVERY 1 WEEK
STARTS CURRENT_TIMESTAMP + INTERVAL 1 WEEK
DO
BEGIN
  -- Remove view logs older than 180 days
  DELETE FROM RORO_MAGAZINE_VIEW WHERE viewed_at < (CURRENT_DATE - INTERVAL 180 DAY);
  -- Remove click logs older than 180 days
  DELETE FROM RORO_MAGAZINE_CLICK WHERE clicked_at < (CURRENT_DATE - INTERVAL 180 DAY);
  -- Remove daily aggregates older than 365 days
  DELETE FROM RORO_MAGAZINE_DAILY WHERE date_key < (CURRENT_DATE - INTERVAL 365 DAY);
END;
