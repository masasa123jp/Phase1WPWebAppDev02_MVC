-- 06_drop_unused_tables.sql
--
-- RORO スキーマから非推奨または未使用のテーブルを削除します。
-- 各 DROP 文には IF EXISTS 句を含めているため、このマイグレーションを
-- 繰り返し実行してもエラーになりません。
-- 本番環境でこのスクリプトを実行する前に、必ずデータベースをバックアップしてください。

DROP TABLE IF EXISTS `RORO_AI_INTERACTION_LOG`;
DROP TABLE IF EXISTS `RORO_AUTH_LEGACY`;
DROP TABLE IF EXISTS `RORO_CATEGORY_OLD`;
DROP TABLE IF EXISTS `RORO_PHOTO_STORE`;

-- 他にも未使用のテーブルを削除する必要がある場合は、ここに DROP TABLE 文を追加してください。
