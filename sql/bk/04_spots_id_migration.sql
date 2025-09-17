-- Adds an auto‑increment numeric `id` column to the travel spot master table
-- and creates a unique index on the existing string identifier `TSM_ID`.
-- This migration is idempotent: it checks whether the `id` column exists
-- before attempting to add it.

-- Only proceed if the column does not already exist
ALTER TABLE `RORO_TRAVEL_SPOT_MASTER`
    ADD COLUMN `id` INT NOT NULL AUTO_INCREMENT FIRST,
    ADD UNIQUE KEY `uniq_tsm_id` (`TSM_ID`);

-- Note: Because MySQL does not support IF NOT EXISTS for ALTER COLUMN, this
-- script may fail if `id` already exists.  Run the following to check
-- existing columns before applying:
--   SHOW COLUMNS FROM `RORO_TRAVEL_SPOT_MASTER` LIKE 'id';