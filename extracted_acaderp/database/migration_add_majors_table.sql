-- Migration: Add majors table
-- Majors are connected to programs (e.g., Bachelor in Elementary Education can have majors like English, Math, Science, etc.)

CREATE TABLE IF NOT EXISTS majors (
    major_id INT AUTO_INCREMENT PRIMARY KEY,
    program_id INT NOT NULL,
    major_name VARCHAR(200) NOT NULL,
    major_code VARCHAR(20),
    description TEXT,
    status ENUM('Active', 'Inactive') DEFAULT 'Active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (program_id) REFERENCES programs(program_id) ON DELETE CASCADE,
    INDEX idx_program (program_id),
    INDEX idx_status (status),
    UNIQUE KEY unique_program_major (program_id, major_name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Update admission_applications to use major_id instead of text field
ALTER TABLE admission_applications
ADD COLUMN major_id INT NULL AFTER year_level,
ADD FOREIGN KEY (major_id) REFERENCES majors(major_id) ON DELETE SET NULL,
ADD INDEX idx_major (major_id);

-- Note: The old 'major' text field will remain for backward compatibility
-- You can remove it later if needed with: ALTER TABLE admission_applications DROP COLUMN major;

