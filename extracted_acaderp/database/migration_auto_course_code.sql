-- Migration: Make course_code auto-generated
-- The code will be auto-generated in PHP based on course_id (format: COURSE-001, COURSE-002, etc.)

-- Step 1: Remove UNIQUE constraint temporarily (if it exists)
ALTER TABLE courses DROP INDEX IF EXISTS idx_course_code;

-- Step 2: Update existing records to have auto-generated codes if they're empty or NULL
UPDATE courses 
SET course_code = CONCAT('COURSE-', LPAD(course_id, 3, '0'))
WHERE course_code IS NULL OR course_code = '';

-- Step 3: Add back UNIQUE constraint with new values
ALTER TABLE courses 
ADD UNIQUE INDEX idx_course_code (course_code);

-- Note: The course_code column will remain NOT NULL as per original schema
-- New courses will be inserted with auto-generated code via PHP

