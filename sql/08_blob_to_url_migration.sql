-- 08_blob_to_url_migration.sql
--
-- このスクリプトは、画像等のBLOBカラムからURLカラムへの移行のための例示です。
-- 例えば、旧テーブルの BLOB カラム image_blob から新設した image_url へデータを移行します。
-- 実際のファイル保存先やURL生成はアプリケーション側で別途実装する必要があります。

-- 1. 新しいカラムの追加（既に存在する場合はスキップ）
ALTER TABLE `RORO_MAGAZINE_PAGE`
  ADD COLUMN IF NOT EXISTS `image_url` TEXT;

-- 2. BLOB データをファイルとして保存し、そのURLを設定する処理はアプリケーションで行います。
--   ここでは一旦ダミーのURLをセットする例を示します。
UPDATE `RORO_MAGAZINE_PAGE` SET `image_url` = CONCAT('/wp-content/uploads/magazine_images/', `id`, '.jpg') WHERE `image_url` IS NULL;

-- 3. 旧 BLOB カラムを削除する場合（移行完了後に実行）
-- ALTER TABLE `RORO_MAGAZINE_PAGE` DROP COLUMN `image_blob`;

-- ご利用環境に応じてテーブル名やカラム名を調整してください。