-- Migration: Make department_code auto-generated
-- The code will be auto-generated in PHP based on department_id (format: DEPT-001, DEPT-002, etc.)

-- Step 1: Remove UNIQUE constraint temporarily (if it exists)
ALTER TABLE departments DROP INDEX IF EXISTS idx_code;

-- Step 2: Update existing records to have auto-generated codes if they're empty or NULL
UPDATE departments 
SET department_code = CONCAT('DEPT-', LPAD(department_id, 3, '0'))
WHERE department_code IS NULL OR department_code = '';

-- Step 3: Add back UNIQUE constraint with new values
ALTER TABLE departments 
ADD UNIQUE INDEX idx_code (department_code);

-- Note: The department_code column will remain NOT NULL as per original schema
-- New departments will be inserted without code, then updated with auto-generated code via PHP

