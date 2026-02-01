-- Migration: Add logo support for departments and photo support for faculty
-- Date: 2024

-- Add logo_path to departments table
ALTER TABLE departments
ADD COLUMN logo_path VARCHAR(255) NULL AFTER department_code;

-- Add photo_path to faculty table
ALTER TABLE faculty
ADD COLUMN photo_path VARCHAR(255) NULL AFTER email;

