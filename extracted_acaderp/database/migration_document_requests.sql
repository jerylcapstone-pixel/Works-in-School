-- Migration: Document Request System
-- Date: 2025
-- Description: Creates table for student document requests (Transcript, Certificate of Enrollment, Certificate of Good Moral Character)

CREATE TABLE IF NOT EXISTS document_requests (
    request_id INT AUTO_INCREMENT PRIMARY KEY,
    student_id INT NOT NULL,
    document_type ENUM('Transcript', 'Certificate of Enrollment', 'Certificate of Good Moral Character') NOT NULL,
    request_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    status ENUM('Pending', 'Approved', 'Rejected', 'Ready for Download', 'Completed') DEFAULT 'Pending',
    requested_by INT NOT NULL COMMENT 'Student user_id',
    approved_by INT NULL COMMENT 'Admin user_id',
    approved_at TIMESTAMP NULL,
    rejected_by INT NULL COMMENT 'Admin user_id',
    rejected_at TIMESTAMP NULL,
    rejection_reason TEXT NULL,
    file_path VARCHAR(500) NULL,
    file_name VARCHAR(255) NULL,
    uploaded_at TIMESTAMP NULL,
    uploaded_by INT NULL COMMENT 'Admin user_id',
    notes TEXT NULL COMMENT 'Student notes',
    admin_notes TEXT NULL COMMENT 'Admin notes',
    download_count INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (student_id) REFERENCES students(student_id) ON DELETE CASCADE,
    FOREIGN KEY (requested_by) REFERENCES users(user_id) ON DELETE RESTRICT,
    FOREIGN KEY (approved_by) REFERENCES users(user_id) ON DELETE SET NULL,
    FOREIGN KEY (rejected_by) REFERENCES users(user_id) ON DELETE SET NULL,
    FOREIGN KEY (uploaded_by) REFERENCES users(user_id) ON DELETE SET NULL,
    INDEX idx_student (student_id),
    INDEX idx_status (status),
    INDEX idx_document_type (document_type),
    INDEX idx_request_date (request_date),
    INDEX idx_requested_by (requested_by)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

