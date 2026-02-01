<?php
/**
 * Edit Announcement
 * Form to edit existing announcement
 */

require_once __DIR__ . '/../../config/config.php';
requireAuth([ROLE_ADMIN]);

$page_title = 'Edit Announcement';
$pdo = getDBConnection();
$error = '';
$announcement_id = (int)($_GET['id'] ?? 0);

if (!$announcement_id) {
    header('Location: ' . BASE_URL . 'modules/announcements/index.php?error=Invalid announcement ID');
    exit;
}

// Get departments for dropdown
$departments = [];
if ($pdo) {
    try {
        $stmt = $pdo->query("SELECT department_id, department_name FROM departments ORDER BY department_name");
        $departments = $stmt->fetchAll();
    } catch (PDOException $e) {
        $departments = [];
    }
}

// Get announcement data
$announcement = null;
try {
    $stmt = $pdo->prepare("SELECT * FROM announcements WHERE announcement_id = ?");
    $stmt->execute([$announcement_id]);
    $announcement = $stmt->fetch();
} catch (PDOException $e) {
    $error = 'Failed to load announcement.';
}

if (!$announcement) {
    header('Location: ' . BASE_URL . 'modules/announcements/index.php?error=Announcement not found');
    exit;
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
        $stmt = $pdo->prepare("UPDATE announcements SET title = ?, content = ?, category = ?, visibility = ?, 
                              department_id = ?, expiration_date = ?, is_active = ?, updated_at = CURRENT_TIMESTAMP 
                              WHERE announcement_id = ?");
        if ($stmt->execute([$title, $content, $category, $visibility, $department_id, $expiration_date, $is_active, $announcement_id])) {
            header('Location: ' . BASE_URL . 'modules/announcements/index.php?success=Announcement updated successfully');
            exit;
        } else {
            $error = 'Failed to update announcement. Please try again.';
        }
    }
    
    // Update $announcement with POST data for form re-display
    $announcement = array_merge($announcement, $_POST);
}

include __DIR__ . '/../../includes/header.php';
?>

<div class="page-container">
    <div class="page-header">
        <h1><i class="fas fa-edit"></i> Edit Announcement</h1>
        <a href="<?php echo BASE_URL; ?>modules/announcements/index.php" class="btn btn-secondary">
            <i class="fas fa-arrow-left"></i> Back to List
        </a>
    </div>

    <?php if ($error): ?>
        <div class="alert alert-error">
            <i class="fas fa-exclamation-circle"></i> <?php echo sanitizeOutput($error); ?>
        </div>
    <?php endif; ?>

    <div class="card">
        <form method="POST" action="" class="form">
            <div class="form-section">
                <h3>Announcement Details</h3>
                
                <div class="form-group">
                    <label for="title">Title <span class="required">*</span></label>
                    <input type="text" id="title" name="title" required class="form-control" 
                           placeholder="Enter announcement title"
                           value="<?php echo sanitizeOutput($announcement['title']); ?>">
                </div>

                <div class="form-group">
                    <label for="content">Content <span class="required">*</span></label>
                    <textarea id="content" name="content" required class="form-control" rows="8"
                              placeholder="Enter announcement content"><?php echo sanitizeOutput($announcement['content']); ?></textarea>
                    <small class="text-muted">You can use plain text or basic HTML formatting</small>
                </div>
            </div>

            <div class="form-section">
                <h3>Settings</h3>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="category">Category <span class="required">*</span></label>
                        <select id="category" name="category" required class="form-control">
                            <option value="General" <?php echo $announcement['category'] === 'General' ? 'selected' : ''; ?>>General</option>
                            <option value="Academic" <?php echo $announcement['category'] === 'Academic' ? 'selected' : ''; ?>>Academic</option>
                            <option value="Events" <?php echo $announcement['category'] === 'Events' ? 'selected' : ''; ?>>Events</option>
                            <option value="Financial" <?php echo $announcement['category'] === 'Financial' ? 'selected' : ''; ?>>Financial</option>
                            <option value="Faculty" <?php echo $announcement['category'] === 'Faculty' ? 'selected' : ''; ?>>Faculty</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="visibility">Target Audience <span class="required">*</span></label>
                        <select id="visibility" name="visibility" required class="form-control" onchange="toggleDepartmentField()">
                            <option value="All" <?php echo $announcement['visibility'] === 'All' ? 'selected' : ''; ?>>All (Students & Faculty)</option>
                            <option value="All_Students" <?php echo $announcement['visibility'] === 'All_Students' ? 'selected' : ''; ?>>All Students</option>
                            <option value="All_Faculty" <?php echo $announcement['visibility'] === 'All_Faculty' ? 'selected' : ''; ?>>All Faculty</option>
                            <option value="Students_Department" <?php echo $announcement['visibility'] === 'Students_Department' ? 'selected' : ''; ?>>Students by Department</option>
                            <option value="Faculty_Department" <?php echo $announcement['visibility'] === 'Faculty_Department' ? 'selected' : ''; ?>>Faculty by Department</option>
                        </select>
                    </div>
                </div>

                <div class="form-row" id="department_row" style="display: <?php echo in_array($announcement['visibility'], ['Students_Department', 'Faculty_Department']) ? 'flex' : 'none'; ?>;">
                    <div class="form-group">
                        <label for="department_id">Department <span class="required">*</span></label>
                        <select id="department_id" name="department_id" class="form-control">
                            <option value="">Select Department</option>
                            <?php foreach ($departments as $dept): ?>
                                <option value="<?php echo $dept['department_id']; ?>" 
                                        <?php echo ($announcement['department_id'] == $dept['department_id']) ? 'selected' : ''; ?>>
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
                               value="<?php echo $announcement['expiration_date'] ? date('Y-m-d', strtotime($announcement['expiration_date'])) : ''; ?>">
                        <small class="text-muted">Leave empty if announcement should not expire</small>
                    </div>

                    <div class="form-group">
                        <label>
                            <input type="checkbox" name="is_active" value="1" 
                                   <?php echo $announcement['is_active'] ? 'checked' : ''; ?>>
                            Active (Show on dashboards)
                        </label>
                        <small class="text-muted">Uncheck to hide this announcement temporarily</small>
                    </div>
                </div>
            </div>

            <div class="form-actions">
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-save"></i> Update Announcement
                </button>
                <a href="<?php echo BASE_URL; ?>modules/announcements/index.php" class="btn btn-secondary">
                    <i class="fas fa-times"></i> Cancel
                </a>
            </div>
        </form>
    </div>
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
</script>

<?php include __DIR__ . '/../../includes/footer.php'; ?>

