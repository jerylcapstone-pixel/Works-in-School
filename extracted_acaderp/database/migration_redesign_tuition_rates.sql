-- Migration: Redesign Tuition Rates to Program-Based with Lecture and Laboratory Rates
-- This migration updates the tuition_rates table to support separate lecture and laboratory rates per program

-- Add new columns for lecture and laboratory rates
-- Note: If columns already exist, you may need to comment out these lines or handle the error
ALTER TABLE tuition_rates
ADD COLUMN lecture_rate DECIMAL(10,2) NULL AFTER amount,
ADD COLUMN laboratory_rate DECIMAL(10,2) NULL AFTER lecture_rate;

-- Update existing records: if amount exists, set it as lecture_rate
UPDATE tuition_rates 
SET lecture_rate = amount 
WHERE lecture_rate IS NULL AND amount > 0;

-- Note: program_id is kept as nullable for backward compatibility with existing data
-- The application logic will enforce that new rates must have a program_id

-- Add index for active program rates lookup (if it doesn't exist)
-- Note: MySQL doesn't support IF NOT EXISTS for indexes, so this may fail if index exists
-- You can safely ignore the error if the index already exists
ALTER TABLE tuition_rates
ADD INDEX idx_program_status (program_id, status);

-- Note: The old 'amount' and 'rate_type' columns are kept for backward compatibility
-- but the new system will use lecture_rate and laboratory_rate

