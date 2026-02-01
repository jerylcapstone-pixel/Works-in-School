-- Migration: Digital Library & Research Hub
-- Date: 2025
-- Description: Creates tables for digital library, file management, and research materials

-- Table for library categories
CREATE TABLE IF NOT EXISTS library_categories (
    category_id INT AUTO_INCREMENT PRIMARY KEY,
    category_name VARCHAR(100) NOT NULL UNIQUE,
    description TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_category_name (category_name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table for library materials (files, documents, resources)
CREATE TABLE IF NOT EXISTS library_materials (
    material_id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(255) NOT NULL,
    description TEXT,
    category_id INT NULL,
    file_name VARCHAR(255) NOT NULL,
    file_path VARCHAR(500) NOT NULL,
    file_size BIGINT NOT NULL COMMENT 'File size in bytes',
    file_type VARCHAR(100) NOT NULL COMMENT 'MIME type',
    file_extension VARCHAR(10) NOT NULL,
    author VARCHAR(255),
    publisher VARCHAR(255),
    publication_date DATE,
    isbn VARCHAR(50),
    keywords TEXT,
    access_level ENUM('Public', 'Students', 'Faculty', 'Restricted') DEFAULT 'Public',
    department_id INT NULL,
    program_id INT NULL,
    download_count INT DEFAULT 0,
    view_count INT DEFAULT 0,
    is_active TINYINT(1) DEFAULT 1,
    uploaded_by INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (category_id) REFERENCES library_categories(category_id) ON DELETE SET NULL,
    FOREIGN KEY (department_id) REFERENCES departments(department_id) ON DELETE SET NULL,
    FOREIGN KEY (program_id) REFERENCES programs(program_id) ON DELETE SET NULL,
    FOREIGN KEY (uploaded_by) REFERENCES users(user_id) ON DELETE RESTRICT,
    INDEX idx_category (category_id),
    INDEX idx_access_level (access_level),
    INDEX idx_department (department_id),
    INDEX idx_program (program_id),
    INDEX idx_is_active (is_active),
    INDEX idx_created_at (created_at),
    FULLTEXT INDEX idx_search (title, description, keywords)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table for library catalog (for books, journals, etc.)
CREATE TABLE IF NOT EXISTS library_catalog (
    catalog_id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(255) NOT NULL,
    author VARCHAR(255),
    isbn VARCHAR(50),
    publisher VARCHAR(255),
    publication_date DATE,
    edition VARCHAR(50),
    description TEXT,
    category_id INT NULL,
    call_number VARCHAR(50),
    location VARCHAR(100),
    total_copies INT DEFAULT 1,
    available_copies INT DEFAULT 1,
    is_digital TINYINT(1) DEFAULT 0,
    digital_material_id INT NULL COMMENT 'Link to library_materials if digital copy available',
    is_active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (category_id) REFERENCES library_categories(category_id) ON DELETE SET NULL,
    FOREIGN KEY (digital_material_id) REFERENCES library_materials(material_id) ON DELETE SET NULL,
    INDEX idx_category (category_id),
    INDEX idx_isbn (isbn),
    INDEX idx_is_active (is_active),
    FULLTEXT INDEX idx_search (title, author, description)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table for research databases/journals
CREATE TABLE IF NOT EXISTS research_databases (
    database_id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    description TEXT,
    url VARCHAR(500),
    access_type ENUM('Free', 'Subscription', 'Institutional') DEFAULT 'Free',
    requires_authentication TINYINT(1) DEFAULT 0,
    is_active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_access_type (access_type),
    INDEX idx_is_active (is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Insert default categories
INSERT INTO library_categories (category_name, description) VALUES
('Textbooks', 'Course textbooks and required reading materials'),
('Research Papers', 'Academic research papers and publications'),
('Journals', 'Academic journals and periodicals'),
('Reference Materials', 'Dictionaries, encyclopedias, and reference books'),
('Multimedia', 'Videos, audio files, and interactive materials'),
('Lecture Notes', 'Faculty lecture notes and presentations'),
('Past Exams', 'Previous examination papers and solutions'),
('Study Guides', 'Study guides and review materials')
ON DUPLICATE KEY UPDATE category_name = category_name;

