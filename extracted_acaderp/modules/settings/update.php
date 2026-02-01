<?php
/**
 * Update System Settings
 * Handles POST requests to update system settings
 */

require_once __DIR__ . '/../../config/config.php';
requireAuth([ROLE_ADMIN]);

$pdo = getDBConnection();
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Check if system_settings table exists
    $table_exists = false;
    try {
        $stmt = $pdo->query("SHOW TABLES LIKE 'system_settings'");
        $table_exists = $stmt->rowCount() > 0;
    } catch (PDOException $e) {
        $table_exists = false;
    }

    if (!$table_exists) {
        $error = 'The system_settings table does not exist. Please run the migration: database/migration_announcements.sql';
        header('Location: ' . BASE_URL . 'modules/settings/index.php?error=' . urlencode($error));
        exit;
    }

    // Get feature toggle values (checkbox: checked = 1, unchecked = 0)
    $announcements_enabled = isset($_POST['announcements_enabled']) ? '1' : '0';
    $examinations_enabled = isset($_POST['examinations_enabled']) ? '1' : '0';
    $library_enabled = isset($_POST['library_enabled']) ? '1' : '0';
    $document_requests_enabled = isset($_POST['document_requests_enabled']) ? '1' : '0';
    $theme_customization_enabled = isset($_POST['theme_customization_enabled']) ? '1' : '0';

    // Update or insert settings
    $settings_to_update = [
        [
            'key' => 'announcements_enabled',
            'value' => $announcements_enabled,
            'description' => 'Enable/Disable the School News & Announcements feature (1 = enabled, 0 = disabled)'
        ],
        [
            'key' => 'examinations_enabled',
            'value' => $examinations_enabled,
            'description' => 'Enable/Disable the Online Examination & Assessments feature (1 = enabled, 0 = disabled)'
        ],
        [
            'key' => 'library_enabled',
            'value' => $library_enabled,
            'description' => 'Enable/Disable the Digital Library & Research Hub feature (1 = enabled, 0 = disabled)'
        ],
        [
            'key' => 'document_requests_enabled',
            'value' => $document_requests_enabled,
            'description' => 'Enable/Disable the Document Request System feature (1 = enabled, 0 = disabled)'
        ],
        [
            'key' => 'theme_customization_enabled',
            'value' => $theme_customization_enabled,
            'description' => 'Enable/Disable the Student Theme Customization feature (1 = enabled, 0 = disabled)'
        ]
    ];

    $stmt = $pdo->prepare("INSERT INTO system_settings (setting_key, setting_value, description) 
                          VALUES (?, ?, ?)
                          ON DUPLICATE KEY UPDATE setting_value = ?, updated_at = CURRENT_TIMESTAMP");
    
    $success = true;
    foreach ($settings_to_update as $setting) {
        if (!$stmt->execute([$setting['key'], $setting['value'], $setting['description'], $setting['value']])) {
            $success = false;
            break;
        }
    }
    
    if ($success) {
        header('Location: ' . BASE_URL . 'modules/settings/index.php?success=Settings updated successfully');
        exit;
    } else {
        $error = 'Failed to update settings. Please try again.';
        header('Location: ' . BASE_URL . 'modules/settings/index.php?error=' . urlencode($error));
        exit;
    }
} else {
    header('Location: ' . BASE_URL . 'modules/settings/index.php');
    exit;
}

