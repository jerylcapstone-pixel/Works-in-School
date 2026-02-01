-- Migration: Add Department Filtering to Announcements
-- Date: 2025
-- Description: Adds department_id field and updates visibility enum to support department-based filtering

-- Add department_id column to announcements table
ALTER TABLE announcements
ADD COLUMN department_id INT NULL AFTER visibility,
ADD FOREIGN KEY (department_id) REFERENCES departments(department_id) ON DELETE SET NULL,
ADD INDEX idx_department (department_id);

-- Update visibility enum to support department-based filtering
-- Note: MySQL doesn't support ALTER ENUM directly, so we need to recreate the column
ALTER TABLE announcements
MODIFY COLUMN visibility ENUM('All', 'All_Students', 'All_Faculty', 'Students_Department', 'Faculty_Department') DEFAULT 'All';

