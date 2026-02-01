-- Migration: Online Examination & Assessments System
-- Date: 2025
-- Description: Creates tables for online examinations, questions, question banks, and exam results

-- Table for exam question banks (reusable questions)
CREATE TABLE IF NOT EXISTS question_banks (
    question_bank_id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    description TEXT,
    created_by INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (created_by) REFERENCES users(user_id) ON DELETE RESTRICT,
    INDEX idx_created_by (created_by)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table for questions (can belong to question bank or exam directly)
CREATE TABLE IF NOT EXISTS questions (
    question_id INT AUTO_INCREMENT PRIMARY KEY,
    question_bank_id INT NULL,
    question_text TEXT NOT NULL,
    question_type ENUM('Multiple Choice', 'True/False', 'Short Answer', 'Essay', 'Fill in the Blank') NOT NULL,
    points DECIMAL(5,2) DEFAULT 1.00,
    correct_answer TEXT,
    explanation TEXT,
    created_by INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (question_bank_id) REFERENCES question_banks(question_bank_id) ON DELETE SET NULL,
    FOREIGN KEY (created_by) REFERENCES users(user_id) ON DELETE RESTRICT,
    INDEX idx_question_bank (question_bank_id),
    INDEX idx_question_type (question_type),
    INDEX idx_created_by (created_by)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table for question options (for multiple choice, true/false)
CREATE TABLE IF NOT EXISTS question_options (
    option_id INT AUTO_INCREMENT PRIMARY KEY,
    question_id INT NOT NULL,
    option_text TEXT NOT NULL,
    is_correct TINYINT(1) DEFAULT 0,
    option_order INT DEFAULT 0,
    FOREIGN KEY (question_id) REFERENCES questions(question_id) ON DELETE CASCADE,
    INDEX idx_question (question_id),
    INDEX idx_order (option_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table for examinations
CREATE TABLE IF NOT EXISTS examinations (
    exam_id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(255) NOT NULL,
    description TEXT,
    course_id INT NULL,
    section_id INT NULL,
    exam_type ENUM('Quiz', 'Midterm', 'Final', 'Assignment', 'Other') DEFAULT 'Quiz',
    total_points DECIMAL(8,2) DEFAULT 0.00,
    time_limit INT NULL COMMENT 'Time limit in minutes, NULL for no limit',
    start_date DATETIME NOT NULL,
    end_date DATETIME NOT NULL,
    allow_late_submission TINYINT(1) DEFAULT 0,
    show_results_immediately TINYINT(1) DEFAULT 1,
    randomize_questions TINYINT(1) DEFAULT 0,
    randomize_options TINYINT(1) DEFAULT 0,
    require_password TINYINT(1) DEFAULT 0,
    exam_password VARCHAR(255) NULL,
    instructions TEXT,
    created_by INT NOT NULL,
    is_active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (course_id) REFERENCES courses(course_id) ON DELETE SET NULL,
    FOREIGN KEY (section_id) REFERENCES class_sections(section_id) ON DELETE SET NULL,
    FOREIGN KEY (created_by) REFERENCES users(user_id) ON DELETE RESTRICT,
    INDEX idx_course (course_id),
    INDEX idx_section (section_id),
    INDEX idx_exam_type (exam_type),
    INDEX idx_dates (start_date, end_date),
    INDEX idx_created_by (created_by)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table linking examinations to questions
CREATE TABLE IF NOT EXISTS exam_questions (
    exam_question_id INT AUTO_INCREMENT PRIMARY KEY,
    exam_id INT NOT NULL,
    question_id INT NOT NULL,
    points DECIMAL(5,2) NOT NULL COMMENT 'Points for this question in this exam (can override default)',
    question_order INT DEFAULT 0,
    FOREIGN KEY (exam_id) REFERENCES examinations(exam_id) ON DELETE CASCADE,
    FOREIGN KEY (question_id) REFERENCES questions(question_id) ON DELETE CASCADE,
    UNIQUE KEY unique_exam_question (exam_id, question_id),
    INDEX idx_exam (exam_id),
    INDEX idx_question (question_id),
    INDEX idx_order (question_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table for student exam attempts
CREATE TABLE IF NOT EXISTS exam_attempts (
    attempt_id INT AUTO_INCREMENT PRIMARY KEY,
    exam_id INT NOT NULL,
    student_id INT NOT NULL,
    started_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    submitted_at TIMESTAMP NULL,
    time_taken INT NULL COMMENT 'Time taken in seconds',
    total_points DECIMAL(8,2) DEFAULT 0.00,
    score DECIMAL(8,2) DEFAULT 0.00,
    percentage DECIMAL(5,2) DEFAULT 0.00,
    status ENUM('In Progress', 'Submitted', 'Graded', 'Expired') DEFAULT 'In Progress',
    ip_address VARCHAR(45),
    user_agent TEXT,
    FOREIGN KEY (exam_id) REFERENCES examinations(exam_id) ON DELETE CASCADE,
    FOREIGN KEY (student_id) REFERENCES students(student_id) ON DELETE CASCADE,
    INDEX idx_exam (exam_id),
    INDEX idx_student (student_id),
    INDEX idx_status (status),
    INDEX idx_submitted (submitted_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table for student answers
CREATE TABLE IF NOT EXISTS exam_answers (
    answer_id INT AUTO_INCREMENT PRIMARY KEY,
    attempt_id INT NOT NULL,
    question_id INT NOT NULL,
    answer_text TEXT,
    option_id INT NULL COMMENT 'For multiple choice questions',
    points_earned DECIMAL(5,2) DEFAULT 0.00,
    is_correct TINYINT(1) DEFAULT 0,
    graded_at TIMESTAMP NULL,
    graded_by INT NULL,
    feedback TEXT,
    FOREIGN KEY (attempt_id) REFERENCES exam_attempts(attempt_id) ON DELETE CASCADE,
    FOREIGN KEY (question_id) REFERENCES questions(question_id) ON DELETE CASCADE,
    FOREIGN KEY (option_id) REFERENCES question_options(option_id) ON DELETE SET NULL,
    FOREIGN KEY (graded_by) REFERENCES users(user_id) ON DELETE SET NULL,
    INDEX idx_attempt (attempt_id),
    INDEX idx_question (question_id),
    UNIQUE KEY unique_attempt_question (attempt_id, question_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

