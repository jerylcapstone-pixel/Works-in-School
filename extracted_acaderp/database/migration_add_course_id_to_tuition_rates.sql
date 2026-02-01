-- Migration: Add course_id to tuition_rates table
-- This allows setting tuition rates for specific general courses
-- Date: 2024

-- Add course_id column to tuition_rates table
ALTER TABLE tuition_rates
ADD COLUMN course_id INT NULL AFTER semester,
ADD FOREIGN KEY (course_id) REFERENCES courses(course_id) ON DELETE SET NULL,
ADD INDEX idx_course (course_id);

