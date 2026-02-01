<?php
/**
 * System Settings Page
 * Allows administrators to manage system-wide settings including feature toggles
 */

require_once __DIR__ . '/../../config/config.php';
requireAuth([ROLE_ADMIN]);

$page_title = 'System Settings';
$pdo = getDBConnection();
$error = '';
$success = '';

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
}

// Get all settings
$settings = [];
if ($table_exists) {
    $stmt = $pdo->query("SELECT * FROM system_settings ORDER BY setting_key");
    $settings = $stmt->fetchAll();
    
    // Convert to associative array for easier access
    $settings_array = [];
    foreach ($settings as $setting) {
        $settings_array[$setting['setting_key']] = $setting;
    }
    $settings = $settings_array;
}

// Get feature toggle settings (default to enabled if not set)
$announcements_enabled = isset($settings['announcements_enabled']) ? $settings['announcements_enabled']['setting_value'] : '1';
$examinations_enabled = isset($settings['examinations_enabled']) ? $settings['examinations_enabled']['setting_value'] : '1';
$library_enabled = isset($settings['library_enabled']) ? $settings['library_enabled']['setting_value'] : '1';
$document_requests_enabled = isset($settings['document_requests_enabled']) ? $settings['document_requests_enabled']['setting_value'] : '1';
$theme_customization_enabled = isset($settings['theme_customization_enabled']) ? $settings['theme_customization_enabled']['setting_value'] : '1';

include __DIR__ . '/../../includes/header.php';
?>

<div class="page-container">
    <div class="page-header">
        <h1><i class="fas fa-cog"></i> System Settings</h1>
    </div>

    <?php if ($error): ?>
        <div class="alert alert-error">
            <i class="fas fa-exclamation-circle"></i> <?php echo sanitizeOutput($error); ?>
        </div>
    <?php endif; ?>

    <?php if ($success): ?>
        <div class="alert alert-success">
            <i class="fas fa-check-circle"></i> <?php echo sanitizeOutput($success); ?>
        </div>
    <?php endif; ?>

    <?php if ($table_exists): ?>
    <div class="card">
        <h2>Feature Settings</h2>
        <form method="POST" action="<?php echo BASE_URL; ?>modules/settings/update.php" class="form" id="settingsForm">
            <div class="form-section">
                <h3>School News & Announcements</h3>
                <div class="form-group">
                    <div class="toggle-container">
                        <label class="toggle-label">
                            <span class="toggle-text">Enable Announcements Feature</span>
                            <input type="checkbox" name="announcements_enabled" value="1" 
                                   class="toggle-switch" id="announcements_toggle"
                                   <?php echo $announcements_enabled == '1' ? 'checked' : ''; ?>>
                            <span class="toggle-slider"></span>
                        </label>
                        <small class="text-muted">
                            When enabled, administrators can post announcements that will appear on student and faculty dashboards.
                        </small>
                    </div>
                </div>
            </div>

            <div class="form-section">
                <h3>Online Examination & Assessments</h3>
                <div class="form-group">
                    <div class="toggle-container">
                        <label class="toggle-label">
                            <span class="toggle-text">Enable Examinations Feature</span>
                            <input type="checkbox" name="examinations_enabled" value="1" 
                                   class="toggle-switch" id="examinations_toggle"
                                   <?php echo $examinations_enabled == '1' ? 'checked' : ''; ?>>
                            <span class="toggle-slider"></span>
                        </label>
                        <small class="text-muted">
                            When enabled, faculty can create and manage online exams, quizzes, and assessments. Students can take exams and view results.
                        </small>
                    </div>
                </div>
            </div>

            <div class="form-section">
                <h3>Digital Library & Research Hub</h3>
                <div class="form-group">
                    <div class="toggle-container">
                        <label class="toggle-label">
                            <span class="toggle-text">Enable Library Feature</span>
                            <input type="checkbox" name="library_enabled" value="1" 
                                   class="toggle-switch" id="library_toggle"
                                   <?php echo $library_enabled == '1' ? 'checked' : ''; ?>>
                            <span class="toggle-slider"></span>
                        </label>
                        <small class="text-muted">
                            When enabled, administrators and faculty can upload materials, documents, and resources. Students and faculty can browse and download materials.
                        </small>
                    </div>
                </div>
            </div>

            <div class="form-section">
                <h3>Document Request System</h3>
                <div class="form-group">
                    <div class="toggle-container">
                        <label class="toggle-label">
                            <span class="toggle-text">Enable Document Requests Feature</span>
                            <input type="checkbox" name="document_requests_enabled" value="1" 
                                   class="toggle-switch" id="document_requests_toggle"
                                   <?php echo $document_requests_enabled == '1' ? 'checked' : ''; ?>>
                            <span class="toggle-slider"></span>
                        </label>
                        <small class="text-muted">
                            When enabled, students can request documents (Transcript, Certificate of Enrollment, Certificate of Good Moral Character). Admins can approve and upload documents.
                        </small>
                    </div>
                </div>
            </div>

            <div class="form-section">
                <h3>Student Theme Customization</h3>
                <div class="form-group">
                    <div class="toggle-container">
                        <label class="toggle-label">
                            <span class="toggle-text">Enable Theme Customization Feature</span>
                            <input type="checkbox" name="theme_customization_enabled" value="1" 
                                   class="toggle-switch" id="theme_customization_toggle"
                                   <?php echo $theme_customization_enabled == '1' ? 'checked' : ''; ?>>
                            <span class="toggle-slider"></span>
                        </label>
                        <small class="text-muted">
                            When enabled, students can customize their portal appearance with dark/light mode toggle for the entire student portal.
                        </small>
                    </div>
                </div>
            </div>

            <div class="form-actions">
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-save"></i> Save Settings
                </button>
            </div>
        </form>
    </div>


    <?php endif; ?>
</div>

<?php include __DIR__ . '/../../includes/footer.php'; ?>

