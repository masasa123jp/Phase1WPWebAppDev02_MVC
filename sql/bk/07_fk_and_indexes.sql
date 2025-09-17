-- 07_fk_and_indexes.sql
--
-- このスクリプトは、主要テーブルに対する外部キーおよびインデックスの追加を定義します。
-- 既に適用済みのものはエラーになりますので、IF NOT EXISTS を利用して実行してください。

-- イベントテーブル：ユーザーID に外部キーを追加（例）
ALTER TABLE `RORO_EVENTS_MASTER`
  ADD INDEX IF NOT EXISTS `idx_roro_events_created_by` (`created_by`),
  ADD CONSTRAINT `fk_roro_events_created_by_users` FOREIGN KEY (`created_by`) REFERENCES `wp_users` (`ID`) ON DELETE SET NULL;

-- スポットテーブル：prefecture にインデックスを追加
ALTER TABLE `RORO_TRAVEL_SPOT_MASTER`
  ADD INDEX IF NOT EXISTS `idx_roro_spots_prefecture` (`prefecture`),
  ADD INDEX IF NOT EXISTS `idx_roro_spots_status` (`status`);

-- アドバイステーブル：作成者ID に外部キーを追加
ALTER TABLE `RORO_ONE_POINT_ADVICE_MASTER`
  ADD INDEX IF NOT EXISTS `idx_roro_advice_created_by` (`created_by`),
  ADD CONSTRAINT `fk_roro_advice_created_by_users` FOREIGN KEY (`created_by`) REFERENCES `wp_users` (`ID`) ON DELETE SET NULL;