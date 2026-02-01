-- Migration: Remove level column from courses table
-- The level field (Undergraduate, Graduate, Both) is being removed from courses
-- Run this migration to remove the level column from existing databases

-- Note: If the column doesn't exist, this will show an error but won't break anything
-- For MySQL 8.0.23+, you can use: ALTER TABLE courses DROP COLUMN IF EXISTS level;

ALTER TABLE courses DROP COLUMN level;

