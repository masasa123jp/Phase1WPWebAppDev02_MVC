-- 01_add_magazine_image_url.sql
--
-- RORO_MAGAZINE_PAGE テーブルに `image_url` 列を追加します。
-- MySQL は `ALTER TABLE ... ADD COLUMN IF NOT EXISTS` をサポートしていないため、
-- このスクリプトではデータディクショナリを確認してから ALTER 文を実行します。

-- image_url 列が既に存在するかを確認します。存在しない場合は、
-- ALTER TABLE 文を構築して列を追加します。
SET @col_exists := (
  SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'RORO_MAGAZINE_PAGE'
    AND COLUMN_NAME = 'image_url'
);

SET @sql := IF(@col_exists = 0,
  'ALTER TABLE `RORO_MAGAZINE_PAGE` ADD COLUMN `image_url` VARCHAR(255) DEFAULT NULL AFTER `title`;',
  'SELECT 1;'
);

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
