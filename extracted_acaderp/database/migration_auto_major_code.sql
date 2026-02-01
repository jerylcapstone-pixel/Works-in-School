-- Migration: Auto-generate major codes
-- This migration updates existing majors with auto-generated codes and ensures uniqueness

-- First, update existing majors that don't have codes
UPDATE majors 
SET major_code = CONCAT('MAJOR-', LPAD(major_id, 3, '0'))
WHERE major_code IS NULL OR major_code = '';

-- Update all majors to ensure they follow the pattern (in case some were manually entered)
UPDATE majors 
SET major_code = CONCAT('MAJOR-', LPAD(major_id, 3, '0'))
WHERE major_code NOT LIKE 'MAJOR-%' OR major_code IS NULL;

-- Ensure major_code column is unique (if not already)
-- First, check if there are any duplicates and handle them
-- Note: This assumes major_id is sequential and unique
ALTER TABLE majors 
MODIFY COLUMN major_code VARCHAR(20) NOT NULL;

-- Add unique constraint if it doesn't exist
-- Note: If duplicates exist, this will fail. You may need to handle duplicates first.
ALTER TABLE majors 
ADD UNIQUE KEY unique_major_code (major_code);

