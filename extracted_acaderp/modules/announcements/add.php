<?php
/**
 * Add New Announcement
 * Form to create a new announcement
 */

require_once __DIR__ . '/../../config/config.php';
requireAuth([ROLE_ADMIN]);

$page_title = 'Add New Announcement';
$pdo = getDBConnection();
$error = '';
$success = '';

// Check if announcements table exists
$table_exists = false;
try {
    $stmt = $pdo->query("SHOW TABLES LIKE 'announcements'");
    $table_exists = $stmt->rowCount() > 0;
} catch (PDOException $e) {
    $table_exists = false;
}

if (!$table_exists) {
    $error = 'The announcements table does not exist. Please run the migration: database/migration_announcements.sql';
}

// Get departments for dropdown
$departments = [];
if ($table_exists && $pdo) {
    try {
        $stmt = $pdo->query("SELECT department_id, department_name FROM departments ORDER BY department_name");
        $departments = $stmt->fetchAll();
    } catch (PDOException $e) {
        $departments = [];
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = sanitizeInput($_POST['title'] ?? '');
    $content = sanitizeInput($_POST['content'] ?? '');
    $category = sanitizeInput($_POST['category'] ?? 'General');
    $visibility = sanitizeInput($_POST['visibility'] ?? 'All');
    $department_id = !empty($_POST['department_id']) ? (int)$_POST['department_id'] : null;
    $expiration_date = !empty($_POST['expiration_date']) ? $_POST['expiration_date'] : null;
    $is_active = isset($_POST['is_active']) ? 1 : 0;

    if (empty($title) || empty($content)) {
        $error = 'Title and content are required.';
    } elseif (!in_array($category, ['General', 'Academic', 'Events', 'Financial', 'Faculty'])) {
        $error = 'Invalid category selected.';
    } elseif (!in_array($visibility, ['All', 'All_Students', 'All_Faculty', 'Students_Department', 'Faculty_Department'])) {
        $error = 'Invalid visibility option selected.';
    } elseif (in_array($visibility, ['Students_Department', 'Faculty_Department']) && empty($department_id)) {
        $error = 'Department is required when targeting by department.';
    } else {
        $stmt = $pdo->prepare("INSERT INTO announcements (title, content, category, visibility, department_id, expiration_date, is_active, created_by) 
                              VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        if ($stmt->execute([$title, $content, $category, $visibility, $department_id, $expiration_date, $is_active, $_SESSION['user_id']])) {
            header('Location: ' . BASE_URL . 'modules/announcements/index.php?success=Announcement created successfully');
            exit;
        } else {
            $error = 'Failed to create announcement. Please try again.';
        }
    }
}

include __DIR__ . '/../../includes/header.php';
?>

<div class="page-container">
    <div class="page-header">
        <h1><i class="fas fa-plus-circle"></i> Add New Announcement</h1>
        <a href="<?php echo BASE_URL; ?>modules/announcements/index.php" class="btn btn-secondary">
            <i class="fas fa-arrow-left"></i> Back to List
        </a>
    </div>

    <?php if ($error): ?>
        <div class="alert alert-error">
            <i class="fas fa-exclamation-circle"></i> <?php echo sanitizeOutput($error); ?>
        </div>
    <?php endif; ?>

    <?php if ($table_exists): ?>
    <div class="card">
        <form method="POST" action="" class="form">
            <div class="form-section">
                <h3>Announcement Details</h3>
                
                <div class="form-group">
                    <label for="title">Title <span class="required">*</span></label>
                    <input type="text" id="title" name="title" required class="form-control" 
                           placeholder="Enter announcement title"
                           value="<?php echo sanitizeOutput($_POST['title'] ?? ''); ?>">
                </div>

                <div class="form-group">
                    <label for="content">Content <span class="required">*</span></label>
                    <textarea id="content" name="content" required class="form-control" rows="8"
                              placeholder="Enter announcement content"><?php echo sanitizeOutput($_POST['content'] ?? ''); ?></textarea>
                    <small class="text-muted">You can use plain text or basic HTML formatting</small>
                </div>
            </div>

            <div class="form-section">
                <h3>Settings</h3>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="category">Category <span class="required">*</span></label>
                        <select id="category" name="category" required class="form-control">
                            <option value="General" <?php echo (($_POST['category'] ?? 'General') === 'General') ? 'selected' : ''; ?>>General</option>
                            <option value="Academic" <?php echo (($_POST['category'] ?? '') === 'Academic') ? 'selected' : ''; ?>>Academic</option>
                            <option value="Events" <?php echo (($_POST['category'] ?? '') === 'Events') ? 'selected' : ''; ?>>Events</option>
                            <option value="Financial" <?php echo (($_POST['category'] ?? '') === 'Financial') ? 'selected' : ''; ?>>Financial</option>
                            <option value="Faculty" <?php echo (($_POST['category'] ?? '') === 'Faculty') ? 'selected' : ''; ?>>Faculty</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="visibility">Target Audience <span class="required">*</span></label>
                        <select id="visibility" name="visibility" required class="form-control" onchange="toggleDepartmentField()">
                            <option value="All" <?php echo (($_POST['visibility'] ?? 'All') === 'All') ? 'selected' : ''; ?>>All (Students & Faculty)</option>
                            <option value="All_Students" <?php echo (($_POST['visibility'] ?? '') === 'All_Students') ? 'selected' : ''; ?>>All Students</option>
                            <option value="All_Faculty" <?php echo (($_POST['visibility'] ?? '') === 'All_Faculty') ? 'selected' : ''; ?>>All Faculty</option>
                            <option value="Students_Department" <?php echo (($_POST['visibility'] ?? '') === 'Students_Department') ? 'selected' : ''; ?>>Students by Department</option>
                            <option value="Faculty_Department" <?php echo (($_POST['visibility'] ?? '') === 'Faculty_Department') ? 'selected' : ''; ?>>Faculty by Department</option>
                        </select>
                    </div>
                </div>

                <div class="form-row" id="department_row" style="display: none;">
                    <div class="form-group">
                        <label for="department_id">Department <span class="required">*</span></label>
                        <select id="department_id" name="department_id" class="form-control">
                            <option value="">Select Department</option>
                            <?php foreach ($departments as $dept): ?>
                                <option value="<?php echo $dept['department_id']; ?>" 
                                        <?php echo (isset($_POST['department_id']) && $_POST['department_id'] == $dept['department_id']) ? 'selected' : ''; ?>>
                                    <?php echo sanitizeOutput($dept['department_name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <small class="text-muted">Required when targeting by department</small>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label for="expiration_date">Expiration Date</label>
                        <input type="date" id="expiration_date" name="expiration_date" class="form-control"
                               value="<?php echo sanitizeOutput($_POST['expiration_date'] ?? ''); ?>">
                        <small class="text-muted">Leave empty if announcement should not expire</small>
                    </div>

                    <div class="form-group">
                        <label>
                            <input type="checkbox" name="is_active" value="1" 
                                   <?php echo (!isset($_POST['is_active']) || $_POST['is_active'] == '1') ? 'checked' : ''; ?>>
                            Active (Show on dashboards)
                        </label>
                        <small class="text-muted">Uncheck to hide this announcement temporarily</small>
                    </div>
                </div>
            </div>

            <div class="form-actions">
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-save"></i> Create Announcement
                </button>
                <a href="<?php echo BASE_URL; ?>modules/announcements/index.php" class="btn btn-secondary">
                    <i class="fas fa-times"></i> Cancel
                </a>
            </div>
        </form>
    </div>
    <?php endif; ?>
</div>

<script>
function toggleDepartmentField() {
    const visibility = document.getElementById('visibility').value;
    const departmentRow = document.getElementById('department_row');
    const departmentSelect = document.getElementById('department_id');
    
    if (visibility === 'Students_Department' || visibility === 'Faculty_Department') {
        departmentRow.style.display = 'flex';
        departmentSelect.required = true;
    } else {
        departmentRow.style.display = 'none';
        departmentSelect.required = false;
        departmentSelect.value = '';
    }
}

// Initialize on page load
document.addEventListener('DOMContentLoaded', function() {
    toggleDepartmentField();
});
</script>

<?php include __DIR__ . '/../../includes/footer.php'; ?>

