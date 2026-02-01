-- Migration: Add department_id to admission_applications table
-- Date: 2024

ALTER TABLE admission_applications
ADD COLUMN department_id INT NULL AFTER program_id,
ADD FOREIGN KEY (department_id) REFERENCES departments(department_id) ON DELETE SET NULL,
ADD INDEX idx_department (department_id);

