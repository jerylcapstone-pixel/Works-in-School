-- Migration: Add School News & Announcements System
-- Date: 2025
-- Description: Creates system_settings and announcements tables for the announcements feature

-- Create system_settings table for feature toggles and system-wide settings
CREATE TABLE IF NOT EXISTS system_settings (
    setting_key VARCHAR(100) PRIMARY KEY,
    setting_value TEXT NOT NULL,
    description TEXT,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_setting_key (setting_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Create announcements table
CREATE TABLE IF NOT EXISTS announcements (
    announcement_id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(255) NOT NULL,
    content TEXT NOT NULL,
    category ENUM('General', 'Academic', 'Events', 'Financial', 'Faculty') DEFAULT 'General',
    visibility ENUM('All', 'Students', 'Faculty') DEFAULT 'All',
    expiration_date DATE NULL,
    is_active TINYINT(1) DEFAULT 1,
    created_by INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (created_by) REFERENCES users(user_id) ON DELETE RESTRICT,
    INDEX idx_category (category),
    INDEX idx_visibility (visibility),
    INDEX idx_is_active (is_active),
    INDEX idx_expiration_date (expiration_date),
    INDEX idx_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Insert default setting: Announcements feature enabled by default
INSERT INTO system_settings (setting_key, setting_value, description) 
VALUES ('announcements_enabled', '1', 'Enable/Disable the School News & Announcements feature (1 = enabled, 0 = disabled)')
ON DUPLICATE KEY UPDATE setting_value = '1';

