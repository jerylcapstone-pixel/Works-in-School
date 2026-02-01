<?php
/**
 * Update Theme Preference
 * AJAX endpoint to save user theme preference
 */

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/feature_toggles.php';
requireAuth([ROLE_STUDENT]);

header('Content-Type: application/json');

$pdo = getDBConnection();

// Check if theme customization feature is enabled
if (!isThemeCustomizationEnabled($pdo)) {
    echo json_encode(['success' => false, 'error' => 'Theme customization is disabled']);
    exit;
}

// Check if user_preferences table exists
$table_exists = false;
try {
    $stmt = $pdo->query("SHOW TABLES LIKE 'user_preferences'");
    $table_exists = $stmt->rowCount() > 0;
} catch (PDOException $e) {
    $table_exists = false;
}

if (!$table_exists) {
    echo json_encode(['success' => false, 'error' => 'user_preferences table does not exist']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $theme_mode = sanitizeInput($_POST['theme_mode'] ?? 'light');
    
    if (!in_array($theme_mode, ['light', 'dark'])) {
        echo json_encode(['success' => false, 'error' => 'Invalid theme mode']);
        exit;
    }
    
    try {
        $stmt = $pdo->prepare("INSERT INTO user_preferences (user_id, theme_mode) 
                              VALUES (?, ?)
                              ON DUPLICATE KEY UPDATE theme_mode = ?, updated_at = CURRENT_TIMESTAMP");
        $stmt->execute([$_SESSION['user_id'], $theme_mode, $theme_mode]);
        
        echo json_encode(['success' => true, 'theme_mode' => $theme_mode]);
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'error' => 'Failed to save preference: ' . $e->getMessage()]);
    }
} else {
    echo json_encode(['success' => false, 'error' => 'Invalid request method']);
}

