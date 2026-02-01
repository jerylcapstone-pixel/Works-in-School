-- =====================================================
-- Migration Script: Add suffix column to faculty table
-- Run this script if you have an existing database
-- =====================================================

USE academic_management_system;

-- Add suffix column to faculty table
ALTER TABLE faculty 
ADD COLUMN suffix VARCHAR(20) NULL AFTER last_name;

-- Note: The suffix field is optional, so existing records will have NULL values
-- which is perfectly fine.
