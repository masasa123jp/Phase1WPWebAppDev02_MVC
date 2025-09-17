-- 08_migrate_blob_to_url.sql
--
-- Demonstrates how to migrate image data stored in a BLOB column to a
-- dedicated URL field.  The RORO_MAGAZINE_PAGE table gains a TEXT
-- column named `image_url` if it does not already exist.  After
-- adding the column, this script populates it with a placeholder
-- value constructed from the row's primary key.  Actual image
-- export and upload must be handled by application code.

-- ------------------------------------------------------------------
-- Add the image_url column if needed
SET @col_exists := (
  SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'RORO_MAGAZINE_PAGE'
    AND COLUMN_NAME = 'image_url'
);
SET @sql := IF(@col_exists = 0,
  'ALTER TABLE `RORO_MAGAZINE_PAGE` ADD COLUMN `image_url` TEXT;',
  'SELECT 1;'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- Populate image_url for rows where it is currently NULL.  The path
-- concatenation below is just an example; adjust it as appropriate
-- for your storage layout.
UPDATE `RORO_MAGAZINE_PAGE`
SET `image_url` = CONCAT('/wp-content/uploads/magazine_images/', `id`, '.jpg')
WHERE `image_url` IS NULL;

-- To remove the old BLOB column after a successful migration you may
-- manually issue a DROP COLUMN statement:
--   ALTER TABLE `RORO_MAGAZINE_PAGE` DROP COLUMN `image_blob`;