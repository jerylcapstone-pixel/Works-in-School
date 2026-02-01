-- Migration: Add major_id to courses table
-- Courses can be optionally associated with a major

ALTER TABLE courses
ADD COLUMN major_id INT NULL AFTER department_id,
ADD FOREIGN KEY (major_id) REFERENCES majors(major_id) ON DELETE SET NULL,
ADD INDEX idx_major (major_id);

