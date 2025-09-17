-- SQL 01: Add image_url column to magazine page table
-- Adds an image_url VARCHAR(255) field to RORO_MAGAZINE_PAGE if it does not already exist.
ALTER TABLE RORO_MAGAZINE_PAGE
ADD COLUMN IF NOT EXISTS image_url VARCHAR(255) DEFAULT NULL AFTER title;
