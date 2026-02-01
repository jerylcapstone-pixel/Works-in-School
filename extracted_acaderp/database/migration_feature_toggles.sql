-- Migration: Add Feature Toggle Settings
-- Date: 2025
-- Description: Adds default settings for specialized modules (Examinations, Library)

-- Ensure system_settings table exists (should already exist from announcements migration)
CREATE TABLE IF NOT EXISTS system_settings (
    setting_key VARCHAR(100) PRIMARY KEY,
    setting_value TEXT NOT NULL,
    description TEXT,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_setting_key (setting_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Insert default settings for specialized modules (enabled by default)
INSERT INTO system_settings (setting_key, setting_value, description) VALUES
('examinations_enabled', '1', 'Enable/Disable the Online Examination & Assessments feature (1 = enabled, 0 = disabled)'),
('library_enabled', '1', 'Enable/Disable the Digital Library & Research Hub feature (1 = enabled, 0 = disabled)'),
('document_requests_enabled', '1', 'Enable/Disable the Document Request System feature (1 = enabled, 0 = disabled)'),
('theme_customization_enabled', '1', 'Enable/Disable the Student Theme Customization feature (1 = enabled, 0 = disabled)')
ON DUPLICATE KEY UPDATE setting_value = setting_value;

