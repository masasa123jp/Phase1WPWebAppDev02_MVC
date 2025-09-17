-- 06_drop_unused_tables.sql
--
-- Drops deprecated or unused tables from the RORO schema.  Each DROP
-- statement includes an IF EXISTS clause so that repeated executions
-- of this migration succeed without error.  Be sure to back up your
-- database before running this script in a production environment.

DROP TABLE IF EXISTS `RORO_AI_INTERACTION_LOG`;
DROP TABLE IF EXISTS `RORO_AUTH_LEGACY`;
DROP TABLE IF EXISTS `RORO_CATEGORY_OLD`;
DROP TABLE IF EXISTS `RORO_PHOTO_STORE`;

-- Add additional DROP TABLE statements here if other unused tables need
-- to be removed.