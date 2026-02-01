<?php
/**
 * Feature Toggle Helper Functions
 * Provides functions to check if features are enabled
 */

/**
 * Check if a feature is enabled
 * @param PDO $pdo Database connection
 * @param string $feature_key Setting key (e.g., 'examinations_enabled', 'library_enabled')
 * @param bool $default Default value if setting doesn't exist (default: true)
 * @return bool True if feature is enabled, false otherwise
 */
function isFeatureEnabled($pdo, $feature_key, $default = true) {
    if (!$pdo) {
        return $default;
    }
    
    try {
        $stmt = $pdo->query("SHOW TABLES LIKE 'system_settings'");
        if ($stmt->rowCount() === 0) {
            return $default;
        }
        
        $stmt = $pdo->prepare("SELECT setting_value FROM system_settings WHERE setting_key = ?");
        $stmt->execute([$feature_key]);
        $setting = $stmt->fetch();
        
        if ($setting) {
            return $setting['setting_value'] == '1';
        }
        
        return $default;
    } catch (PDOException $e) {
        return $default;
    }
}

/**
 * Check if Examinations feature is enabled
 * @param PDO $pdo Database connection
 * @return bool
 */
function isExaminationsEnabled($pdo) {
    return isFeatureEnabled($pdo, 'examinations_enabled', true);
}

/**
 * Check if Library feature is enabled
 * @param PDO $pdo Database connection
 * @return bool
 */
function isLibraryEnabled($pdo) {
    return isFeatureEnabled($pdo, 'library_enabled', true);
}

/**
 * Check if Announcements feature is enabled
 * @param PDO $pdo Database connection
 * @return bool
 */
function isAnnouncementsEnabled($pdo) {
    return isFeatureEnabled($pdo, 'announcements_enabled', true);
}

/**
 * Check if Document Requests feature is enabled
 * @param PDO $pdo Database connection
 * @return bool
 */
function isDocumentRequestsEnabled($pdo) {
    return isFeatureEnabled($pdo, 'document_requests_enabled', true);
}

/**
 * Check if Theme Customization feature is enabled
 * @param PDO $pdo Database connection
 * @return bool
 */
function isThemeCustomizationEnabled($pdo) {
    return isFeatureEnabled($pdo, 'theme_customization_enabled', true);
}

