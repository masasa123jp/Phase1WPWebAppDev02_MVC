-- 06_cleanup_unused_tables.sql
--
-- このスクリプトは、不要となった旧テーブル群を削除するためのDDLを提供します。
-- 運用環境で適用する際には、必ずバックアップを取得し、アプリケーション側で参照されていないことを確認してください。

-- 例として、AI 関連のログテーブルや旧認証テーブル、未使用のカテゴリ／写真テーブルを削除します。
-- テーブル名は実際の環境に合わせて変更してください。

DROP TABLE IF EXISTS `RORO_AI_INTERACTION_LOG`;
DROP TABLE IF EXISTS `RORO_AUTH_LEGACY`;
DROP TABLE IF EXISTS `RORO_CATEGORY_OLD`;
DROP TABLE IF EXISTS `RORO_PHOTO_STORE`;

-- 他に不要なテーブルがある場合は追記してください。