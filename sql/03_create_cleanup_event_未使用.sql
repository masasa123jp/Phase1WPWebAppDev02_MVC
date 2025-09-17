-- イベントスケジューラの状態
SHOW VARIABLES LIKE 'event_scheduler';
-- OFF の場合は管理権限があれば ON に
-- SET GLOBAL event_scheduler = ON;   -- 共有サーバでは権限上実行できないことがあります


-- 03_create_cleanup_event.sql
--
-- 目的:
--   雑誌の閲覧/クリック/日次集計ログの古いレコードを週次で自動削除する MySQL EVENT を作成します。
-- ポイント:
--   ・イベント本文に複数の DELETE を書くため BEGIN ... END を使用（= 複合文）
--   ・phpMyAdmin では複合文を正しく送るため DELIMITER を切り替える必要があります
--   ・既存イベントがあれば DROP してから再作成（冪等）
--   ・Xserver 等の共有環境では SET GLOBAL は権限で拒否されることがあります。
--     その場合でも event_scheduler が ON であれば本イベントは動作します。

/* 既存イベントの削除（存在しない場合は何もしない） */
DROP EVENT IF EXISTS `roro_analytics_cleanup`;

-- ここから複合文を送るため、文末デリミタを $$ に変更
DELIMITER $$

/* 週次の自動削除イベントを作成 */
CREATE EVENT `roro_analytics_cleanup`
ON SCHEDULE EVERY 1 WEEK
STARTS CURRENT_TIMESTAMP + INTERVAL 1 WEEK
DO
BEGIN
  -- 1) 180日より前の閲覧ログを削除
  DELETE FROM `RORO_MAGAZINE_VIEW`
   WHERE `viewed_at` < (CURRENT_DATE - INTERVAL 180 DAY);

  -- 2) 180日より前のクリックログを削除
  DELETE FROM `RORO_MAGAZINE_CLICK`
   WHERE `clicked_at` < (CURRENT_DATE - INTERVAL 180 DAY);

  -- 3) 365日より前の日次集計を削除
  DELETE FROM `RORO_MAGAZINE_DAILY`
   WHERE `date_key` < (CURRENT_DATE - INTERVAL 365 DAY);
END$$

-- 複合文の送信が終わったのでデリミタを元に戻す
DELIMITER ;

-- 補足:
-- ・event_scheduler が OFF の場合、このイベントは実行されません。
--   SHOW VARIABLES LIKE 'event_scheduler'; が OFF の場合はサーバ側設定で ON にする必要があります。
-- ・権限不足でイベント作成に失敗する場合は、作成ユーザに EVENT 権限が必要です。
--   例: GRANT EVENT ON `your_db`.* TO 'your_user'@'%';
