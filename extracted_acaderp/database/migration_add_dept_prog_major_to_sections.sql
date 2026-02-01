-- Migration: Add department, program, and major to class_sections
-- This allows tracking which department/program/major each section belongs to
-- Date: 2025-11-06

-- Check if columns already exist before adding them
SET @db_name = DATABASE();

-- Add department_id column
SET @col_exists = (
    SELECT COUNT(*) 
    FROM INFORMATION_SCHEMA.COLUMNS 
    WHERE TABLE_SCHEMA = @db_name 
    AND TABLE_NAME = 'class_sections' 
    AND COLUMN_NAME = 'department_id'
);

SET @sql = IF(@col_exists = 0,
    'ALTER TABLE class_sections ADD COLUMN department_id INT NULL AFTER course_id',
    'SELECT "Column department_id already exists" AS message'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Add program_id column
SET @col_exists = (
    SELECT COUNT(*) 
    FROM INFORMATION_SCHEMA.COLUMNS 
    WHERE TABLE_SCHEMA = @db_name 
    AND TABLE_NAME = 'class_sections' 
    AND COLUMN_NAME = 'program_id'
);

SET @sql = IF(@col_exists = 0,
    'ALTER TABLE class_sections ADD COLUMN program_id INT NULL AFTER department_id',
    'SELECT "Column program_id already exists" AS message'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Add major_id column
SET @col_exists = (
    SELECT COUNT(*) 
    FROM INFORMATION_SCHEMA.COLUMNS 
    WHERE TABLE_SCHEMA = @db_name 
    AND TABLE_NAME = 'class_sections' 
    AND COLUMN_NAME = 'major_id'
);

SET @sql = IF(@col_exists = 0,
    'ALTER TABLE class_sections ADD COLUMN major_id INT NULL AFTER program_id',
    'SELECT "Column major_id already exists" AS message'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Add foreign key for department_id (if it doesn't exist)
SET @fk_exists = (
    SELECT COUNT(*) 
    FROM INFORMATION_SCHEMA.TABLE_CONSTRAINTS 
    WHERE TABLE_SCHEMA = @db_name 
    AND TABLE_NAME = 'class_sections' 
    AND CONSTRAINT_NAME = 'fk_section_department'
);

SET @sql = IF(@fk_exists = 0,
    'ALTER TABLE class_sections ADD CONSTRAINT fk_section_department 
     FOREIGN KEY (department_id) REFERENCES departments(department_id) ON DELETE SET NULL',
    'SELECT "Foreign key fk_section_department already exists" AS message'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Add foreign key for program_id (if it doesn't exist)
SET @fk_exists = (
    SELECT COUNT(*) 
    FROM INFORMATION_SCHEMA.TABLE_CONSTRAINTS 
    WHERE TABLE_SCHEMA = @db_name 
    AND TABLE_NAME = 'class_sections' 
    AND CONSTRAINT_NAME = 'fk_section_program'
);

SET @sql = IF(@fk_exists = 0,
    'ALTER TABLE class_sections ADD CONSTRAINT fk_section_program 
     FOREIGN KEY (program_id) REFERENCES programs(program_id) ON DELETE SET NULL',
    'SELECT "Foreign key fk_section_program already exists" AS message'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Add foreign key for major_id (if it doesn't exist and majors table exists)
SET @majors_table_exists = (
    SELECT COUNT(*) 
    FROM INFORMATION_SCHEMA.TABLES 
    WHERE TABLE_SCHEMA = @db_name 
    AND TABLE_NAME = 'majors'
);

SET @fk_exists = (
    SELECT COUNT(*) 
    FROM INFORMATION_SCHEMA.TABLE_CONSTRAINTS 
    WHERE TABLE_SCHEMA = @db_name 
    AND TABLE_NAME = 'class_sections' 
    AND CONSTRAINT_NAME = 'fk_section_major'
);

SET @sql = IF(@majors_table_exists > 0 AND @fk_exists = 0,
    'ALTER TABLE class_sections ADD CONSTRAINT fk_section_major 
     FOREIGN KEY (major_id) REFERENCES majors(major_id) ON DELETE SET NULL',
    'SELECT "Foreign key fk_section_major skipped or already exists" AS message'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Add indexes for better query performance
SET @idx_exists = (
    SELECT COUNT(*) 
    FROM INFORMATION_SCHEMA.STATISTICS 
    WHERE TABLE_SCHEMA = @db_name 
    AND TABLE_NAME = 'class_sections' 
    AND INDEX_NAME = 'idx_department'
);

SET @sql = IF(@idx_exists = 0,
    'ALTER TABLE class_sections ADD INDEX idx_department (department_id)',
    'SELECT "Index idx_department already exists" AS message'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @idx_exists = (
    SELECT COUNT(*) 
    FROM INFORMATION_SCHEMA.STATISTICS 
    WHERE TABLE_SCHEMA = @db_name 
    AND TABLE_NAME = 'class_sections' 
    AND INDEX_NAME = 'idx_program'
);

SET @sql = IF(@idx_exists = 0,
    'ALTER TABLE class_sections ADD INDEX idx_program (program_id)',
    'SELECT "Index idx_program already exists" AS message'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @idx_exists = (
    SELECT COUNT(*) 
    FROM INFORMATION_SCHEMA.STATISTICS 
    WHERE TABLE_SCHEMA = @db_name 
    AND TABLE_NAME = 'class_sections' 
    AND INDEX_NAME = 'idx_major'
);

SET @sql = IF(@idx_exists = 0,
    'ALTER TABLE class_sections ADD INDEX idx_major (major_id)',
    'SELECT "Index idx_major already exists" AS message'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SELECT 'Migration completed: Added department_id, program_id, and major_id columns to class_sections table' AS status;

