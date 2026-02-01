-- Migration: Update course_type ENUM values to correct spelling and add more types
-- Date: 2025-11-06

SET @db_name = DATABASE();

-- Check current enum definition
SELECT COLUMN_TYPE INTO @current_type
FROM INFORMATION_SCHEMA.COLUMNS
WHERE TABLE_SCHEMA = @db_name
  AND TABLE_NAME = 'courses'
  AND COLUMN_NAME = 'course_type';

-- Update only if definition differs
SET @desired_type = "enum('Lecture','Laboratory','Seminar','Workshop','Online','Hybrid')";

SET @sql = IF(@current_type = @desired_type,
    'SELECT "course_type already up to date" AS message',
    'ALTER TABLE courses MODIFY course_type ENUM(''Lecture'',''Laboratory'',''Seminar'',''Workshop'',''Online'',''Hybrid'') DEFAULT ''Lecture''');

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SELECT 'Migration completed: course_type ENUM updated' AS status;

