-- Migration: Update tuition_rates table to include year_level and semester
-- This adds support for program-specific tuition rates by year level and semester

-- Check if year_level column exists, if not add it
SET @dbname = DATABASE();
SET @tablename = 'tuition_rates';
SET @columnname = 'year_level';
SET @preparedStatement = (SELECT IF(
    (
        SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
        WHERE
            (TABLE_SCHEMA = @dbname)
            AND (TABLE_NAME = @tablename)
            AND (COLUMN_NAME = @columnname)
    ) > 0,
    'SELECT 1',
    CONCAT('ALTER TABLE ', @tablename, ' ADD COLUMN ', @columnname, ' INT AFTER program_id')
));
PREPARE alterIfNotExists FROM @preparedStatement;
EXECUTE alterIfNotExists;
DEALLOCATE PREPARE alterIfNotExists;

-- Check if semester column exists, if not add it
SET @columnname = 'semester';
SET @preparedStatement = (SELECT IF(
    (
        SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
        WHERE
            (TABLE_SCHEMA = @dbname)
            AND (TABLE_NAME = @tablename)
            AND (COLUMN_NAME = @columnname)
    ) > 0,
    'SELECT 1',
    CONCAT('ALTER TABLE ', @tablename, ' ADD COLUMN ', @columnname, " VARCHAR(20) AFTER year_level")
));
PREPARE alterIfNotExists FROM @preparedStatement;
EXECUTE alterIfNotExists;
DEALLOCATE PREPARE alterIfNotExists;

-- Add unique constraint for program_id, year_level, and semester combination
-- First, drop the constraint if it exists
SET @constraint_name = 'unique_tuition_rate';
SET @preparedStatement = (SELECT IF(
    (
        SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLE_CONSTRAINTS
        WHERE
            (TABLE_SCHEMA = @dbname)
            AND (TABLE_NAME = @tablename)
            AND (CONSTRAINT_NAME = @constraint_name)
    ) > 0,
    CONCAT('ALTER TABLE ', @tablename, ' DROP INDEX ', @constraint_name),
    'SELECT 1'
));
PREPARE alterIfNotExists FROM @preparedStatement;
EXECUTE alterIfNotExists;
DEALLOCATE PREPARE alterIfNotExists;

-- Add the unique constraint
ALTER TABLE tuition_rates 
ADD UNIQUE KEY unique_tuition_rate (program_id, year_level, semester, effective_date);

