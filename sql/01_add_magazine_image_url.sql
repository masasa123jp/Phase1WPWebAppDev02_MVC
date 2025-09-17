-- 01_add_magazine_image_url.sql
--
-- Adds an `image_url` column to the RORO_MAGAZINE_PAGE table.  Because
-- MySQL does not support `ALTER TABLE ... ADD COLUMN IF NOT EXISTS`,
-- this script guards the ALTER statement by checking the data dictionary.

-- Check whether the image_url column already exists.  If it does not,
-- construct and execute an ALTER TABLE statement to add the column.
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