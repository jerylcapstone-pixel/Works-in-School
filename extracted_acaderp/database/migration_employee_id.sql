-- =====================================================
-- Migration Script: Update employee_id to AUTO_INCREMENT
-- Run this script if you have an existing database
-- =====================================================

USE academic_management_system;

-- Step 1: If there are existing records, we need to convert employee_id from VARCHAR to INT
-- First, backup your data if needed!

-- Step 2: Drop the UNIQUE index on employee_id (temporarily)
ALTER TABLE faculty DROP INDEX idx_employee_id;

-- Step 3: Modify the column from VARCHAR(20) to INT
-- Note: This will fail if there are non-numeric values in employee_id
-- If you have string-based employee IDs like "EMP-001", you'll need to convert them first
ALTER TABLE faculty MODIFY COLUMN employee_id INT NOT NULL;

-- Step 4: Add back the UNIQUE index
ALTER TABLE faculty ADD UNIQUE INDEX idx_employee_id (employee_id);

-- Step 5: Update existing records to have sequential employee_id values
-- This assigns sequential IDs starting from 1 based on faculty_id
SET @row_number = 0;
UPDATE faculty 
SET employee_id = (@row_number := @row_number + 1)
ORDER BY faculty_id;

-- Note: From now on, employee_id will be auto-generated when adding new faculty members
-- The PHP code in modules/faculty/add.php will handle this automatically
