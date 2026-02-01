-- Migration: Add Standard Fees and Student Additional Fees Tables
-- This adds support for standard fees and student-specific additional fees

-- Table for standard fees (applied to all students)
CREATE TABLE IF NOT EXISTS standard_fees (
    fee_id INT AUTO_INCREMENT PRIMARY KEY,
    fee_category VARCHAR(100) UNIQUE NOT NULL,
    amount DECIMAL(10,2) DEFAULT 0.00,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_category (fee_category),
    INDEX idx_active (is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table for student-specific additional fees
CREATE TABLE IF NOT EXISTS student_additional_fees (
    additional_fee_id INT AUTO_INCREMENT PRIMARY KEY,
    student_id INT NOT NULL,
    fee_name VARCHAR(200) NOT NULL,
    amount DECIMAL(10,2) NOT NULL,
    description TEXT,
    semester VARCHAR(20),
    academic_year VARCHAR(10),
    status ENUM('Active', 'Paid', 'Cancelled') DEFAULT 'Active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (student_id) REFERENCES students(student_id) ON DELETE CASCADE,
    INDEX idx_student (student_id),
    INDEX idx_status (status),
    INDEX idx_semester (semester, academic_year)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Add old_account balance to invoices table (if not exists)
SET @dbname = DATABASE();
SET @tablename = 'invoices';
SET @columnname = 'old_account';
SET @preparedStatement = (SELECT IF(
    (
        SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
        WHERE
            (TABLE_SCHEMA = @dbname)
            AND (TABLE_NAME = @tablename)
            AND (COLUMN_NAME = @columnname)
    ) > 0,
    'SELECT 1',
    CONCAT('ALTER TABLE ', @tablename, ' ADD COLUMN ', @columnname, ' DECIMAL(10,2) DEFAULT 0.00 AFTER financial_aid_amount')
));
PREPARE alterIfNotExists FROM @preparedStatement;
EXECUTE alterIfNotExists;
DEALLOCATE PREPARE alterIfNotExists;

