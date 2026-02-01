-- Migration: Make program_code auto-generated
-- The code will be auto-generated in PHP based on program_id (format: PROG-001, PROG-002, etc.)

-- Step 1: Remove UNIQUE constraint temporarily (if it exists)
ALTER TABLE programs DROP INDEX IF EXISTS idx_program_code;

-- Step 2: Update existing records to have auto-generated codes if they're empty or NULL
UPDATE programs 
SET program_code = CONCAT('PROG-', LPAD(program_id, 3, '0'))
WHERE program_code IS NULL OR program_code = '';

-- Step 3: Add back UNIQUE constraint with new values
ALTER TABLE programs 
ADD UNIQUE INDEX idx_program_code (program_code);

-- Note: The program_code column will remain NOT NULL as per original schema
-- New programs will be inserted with auto-generated code via PHP

