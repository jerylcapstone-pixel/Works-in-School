-- Migration: Add support for minor/general course rates
-- This migration allows tuition_rates to have NULL program_id for general/minor course rates
-- General courses are those that don't belong to any department, program, or major

-- Modify program_id to allow NULL (for general/minor course rates)
ALTER TABLE tuition_rates MODIFY COLUMN program_id INT NULL;

-- Modify year_level to allow NULL (not applicable for general courses)
-- Note: year_level column may not exist if previous migration wasn't run
-- This will fail gracefully if column doesn't exist
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
    CONCAT('ALTER TABLE ', @tablename, ' MODIFY COLUMN ', @columnname, ' INT NULL'),
    'SELECT 1'
));
PREPARE alterIfNotExists FROM @preparedStatement;
EXECUTE alterIfNotExists;
DEALLOCATE PREPARE alterIfNotExists;

-- Modify semester to allow NULL (general courses can apply to all semesters)
SET @columnname = 'semester';
SET @preparedStatement = (SELECT IF(
    (
        SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
        WHERE
            (TABLE_SCHEMA = @dbname)
            AND (TABLE_NAME = @tablename)
            AND (COLUMN_NAME = @columnname)
    ) > 0,
    CONCAT('ALTER TABLE ', @tablename, ' MODIFY COLUMN ', @columnname, ' VARCHAR(20) NULL'),
    'SELECT 1'
));
PREPARE alterIfNotExists FROM @preparedStatement;
EXECUTE alterIfNotExists;
DEALLOCATE PREPARE alterIfNotExists;

-- Add rate_category column to distinguish between program-specific and general rates
SET @columnname = 'rate_category';
SET @preparedStatement = (SELECT IF(
    (
        SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
        WHERE
            (TABLE_SCHEMA = @dbname)
            AND (TABLE_NAME = @tablename)
            AND (COLUMN_NAME = @columnname)
    ) > 0,
    'SELECT 1',
    CONCAT('ALTER TABLE ', @tablename, ' ADD COLUMN ', @columnname, " ENUM('Program', 'General') DEFAULT 'Program' AFTER program_id")
));
PREPARE alterIfNotExists FROM @preparedStatement;
EXECUTE alterIfNotExists;
DEALLOCATE PREPARE alterIfNotExists;

-- Update existing records
UPDATE tuition_rates SET rate_category = 'Program' WHERE program_id IS NOT NULL AND (rate_category IS NULL OR rate_category = '');
UPDATE tuition_rates SET rate_category = 'General' WHERE program_id IS NULL;
