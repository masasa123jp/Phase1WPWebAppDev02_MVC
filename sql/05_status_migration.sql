-- Adds `status` and `status_updated_at` columns to core RORO tables and
-- creates a status change history table.  Existing visible rows are
-- promoted to `published` status; all others default to `draft`.

-- events: add status/datetime; default all existing visible rows to published
ALTER TABLE `RORO_EVENTS_MASTER`
    ADD COLUMN `status` VARCHAR(20) NOT NULL DEFAULT 'draft' AFTER `isVisible`,
    ADD COLUMN `status_updated_at` DATETIME NULL AFTER `status`;
-- Migrate current data: visible events become published; set status_updated_at to updated_at or created_at
UPDATE `RORO_EVENTS_MASTER`
SET `status` = CASE WHEN `isVisible` = 1 THEN 'published' ELSE 'draft' END,
    `status_updated_at` = COALESCE(`updated_at`, `created_at`, NOW());

-- spots: add status/datetime; default visible rows to published
ALTER TABLE `RORO_TRAVEL_SPOT_MASTER`
    ADD COLUMN `status` VARCHAR(20) NOT NULL DEFAULT 'draft' AFTER `isVisible`,
    ADD COLUMN `status_updated_at` DATETIME NULL AFTER `status`;
UPDATE `RORO_TRAVEL_SPOT_MASTER`
SET `status` = CASE WHEN `isVisible` = 1 THEN 'published' ELSE 'draft' END,
    `status_updated_at` = COALESCE(`updated_at`, `created_at`, NOW());

-- one‑point advice: add status/datetime; default visible rows to published
ALTER TABLE `RORO_ONE_POINT_ADVICE_MASTER`
    ADD COLUMN `status` VARCHAR(20) NOT NULL DEFAULT 'draft' AFTER `isVisible`,
    ADD COLUMN `status_updated_at` DATETIME NULL AFTER `status`;
UPDATE `RORO_ONE_POINT_ADVICE_MASTER`
SET `status` = CASE WHEN `isVisible` = 1 THEN 'published' ELSE 'draft' END,
    `status_updated_at` = COALESCE(`updated_at`, `created_at`, NOW());

-- Create a status change history table to audit status transitions.  This table
-- stores the table name, record identifier (numeric or string), the old and
-- new status values, and the user and timestamp of change.  An index on
-- (table_name, record_id) accelerates lookups.
CREATE TABLE IF NOT EXISTS `RORO_STATUS_HISTORY` (
    `id` INT NOT NULL AUTO_INCREMENT,
    `table_name` VARCHAR(128) NOT NULL,
    `record_id` VARCHAR(255) NOT NULL,
    `old_status` VARCHAR(20) DEFAULT NULL,
    `new_status` VARCHAR(20) NOT NULL,
    `changed_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `changed_by` BIGINT NULL,
    PRIMARY KEY (`id`),
    KEY `idx_table_record` (`table_name`, `record_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;