-- 02_add_customer_indexes_and_fks.sql
--
-- Adds indexes and foreign key constraints to customer‑related tables.
-- The original migration used `CREATE INDEX IF NOT EXISTS`, which
-- MySQL does not support.  Here we consult INFORMATION_SCHEMA before
-- executing each DDL statement so that the script can run safely
-- multiple times without error.

-- ------------------------------------------------------------------
-- Index on RORO_PET.customer_id
SET @idx_exists := (
  SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'RORO_PET'
    AND INDEX_NAME = 'idx_roro_pet_customer_id'
);
SET @sql := IF(@idx_exists = 0,
  'CREATE INDEX `idx_roro_pet_customer_id` ON `RORO_PET`(`customer_id`);',
  'SELECT 1;'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- Index on RORO_ADDRESS.customer_id
SET @idx_exists := (
  SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'RORO_ADDRESS'
    AND INDEX_NAME = 'idx_roro_address_customer_id'
);
SET @sql := IF(@idx_exists = 0,
  'CREATE INDEX `idx_roro_address_customer_id` ON `RORO_ADDRESS`(`customer_id`);',
  'SELECT 1;'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- Index on RORO_USER_LINK_WP.customer_id
SET @idx_exists := (
  SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'RORO_USER_LINK_WP'
    AND INDEX_NAME = 'idx_roro_user_link_wp_customer_id'
);
SET @sql := IF(@idx_exists = 0,
  'CREATE INDEX `idx_roro_user_link_wp_customer_id` ON `RORO_USER_LINK_WP`(`customer_id`);',
  'SELECT 1;'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- ------------------------------------------------------------------
-- Foreign key for RORO_PET.customer_id referencing RORO_CUSTOMER.id
SET @fk_exists := (
  SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLE_CONSTRAINTS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'RORO_PET'
    AND CONSTRAINT_NAME = 'fk_roro_pet_customer'
    AND CONSTRAINT_TYPE = 'FOREIGN KEY'
);
SET @sql := IF(@fk_exists = 0,
  'ALTER TABLE `RORO_PET` ADD CONSTRAINT `fk_roro_pet_customer` FOREIGN KEY (`customer_id`) REFERENCES `RORO_CUSTOMER`(`id`) ON DELETE CASCADE;',
  'SELECT 1;'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- Foreign key for RORO_ADDRESS.customer_id referencing RORO_CUSTOMER.id
SET @fk_exists := (
  SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLE_CONSTRAINTS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'RORO_ADDRESS'
    AND CONSTRAINT_NAME = 'fk_roro_address_customer'
    AND CONSTRAINT_TYPE = 'FOREIGN KEY'
);
SET @sql := IF(@fk_exists = 0,
  'ALTER TABLE `RORO_ADDRESS` ADD CONSTRAINT `fk_roro_address_customer` FOREIGN KEY (`customer_id`) REFERENCES `RORO_CUSTOMER`(`id`) ON DELETE CASCADE;',
  'SELECT 1;'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- Note: There is intentionally no foreign key on RORO_USER_LINK_WP.customer_id, because
-- the application handles deletion logic explicitly.