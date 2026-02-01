<?php
/**
 * Curriculum Management - Add Department
 */

require_once __DIR__ . '/../../config/config.php';
requireAuth(ROLE_ADMIN);

$page_title = 'Add Department';

$pdo = getDBConnection();
$error = '';

// Handle logo upload
function handleLogoUpload($file) {
    if (!isset($file) || $file['error'] !== UPLOAD_ERR_OK) {
        return null;
    }
    
    $allowed = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/svg+xml'];
    if (!in_array($file['type'], $allowed)) {
        return false; // Error: invalid file type
    }
    
    $uploadDir = __DIR__ . '/../../uploads/department_logos/';
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }
    
    $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
    $filename = 'logo_' . time() . '_' . uniqid() . '.' . $extension;
    $filepath = $uploadDir . $filename;
    
    if (move_uploaded_file($file['tmp_name'], $filepath)) {
        return 'uploads/department_logos/' . $filename;
    }
    
    return false;
}

// Get faculty for dropdown
$faculty_list = [];
$stmt = $pdo->query("SELECT faculty_id, first_name, last_name, employee_id FROM faculty WHERE status = 'Active' ORDER BY last_name");
$faculty_list = $stmt->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $department_name = sanitizeInput($_POST['department_name'] ?? '');
    $head_faculty_id = !empty($_POST['head_faculty_id']) ? (int)$_POST['head_faculty_id'] : null;
    $description = sanitizeInput($_POST['description'] ?? '');
    
    // Handle logo upload
    $logo_path = null;
    if (isset($_FILES['logo']) && $_FILES['logo']['error'] === UPLOAD_ERR_OK) {
        $logo_result = handleLogoUpload($_FILES['logo']);
        if ($logo_result === false) {
            $error = 'Invalid logo file. Please upload JPG, PNG, GIF, or SVG only.';
        } elseif ($logo_result !== null) {
            $logo_path = $logo_result;
        }
    }

    if (empty($error) && empty($department_name)) {
        $error = 'Department name is required.';
    } elseif (empty($error)) {
        // Get next department_id to generate code before insert
        $stmt = $pdo->query("SELECT COALESCE(MAX(department_id), 0) + 1 as next_id FROM departments");
        $result = $stmt->fetch();
        $next_id = $result['next_id'];
        
        // Generate auto-increment department code: DEPT-001, DEPT-002, etc.
        $department_code = 'DEPT-' . str_pad($next_id, 3, '0', STR_PAD_LEFT);
        
        // Insert department with the generated code
        $sql = "INSERT INTO departments (department_code, logo_path, department_name, head_faculty_id, description)
                VALUES (?, ?, ?, ?, ?)";
        $stmt = $pdo->prepare($sql);
        if ($stmt->execute([$department_code, $logo_path, $department_name, $head_faculty_id, $description ?: null])) {
            header('Location: ' . BASE_URL . 'modules/curriculum/index.php?tab=departments&success=Department added successfully with code: ' . $department_code);
            exit;
        } else {
            $error = 'Error adding department.';
        }
    }
}

include __DIR__ . '/../../includes/header.php';
?>

<div class="page-container">
    <div class="page-header">
        <h1><i class="fas fa-building"></i> Add Department</h1>
        <a href="<?php echo BASE_URL; ?>modules/curriculum/index.php?tab=departments" class="btn btn-secondary">
            <i class="fas fa-arrow-left"></i> Back
        </a>
    </div>

    <?php if ($error): ?>
        <div class="alert alert-error"><?php echo sanitizeOutput($error); ?></div>
    <?php endif; ?>

    <div class="card">
        <form method="POST" action="" enctype="multipart/form-data" class="form">
            <div class="form-row">
                <div class="form-group">
                    <label>Department Code</label>
                    <input type="text" class="form-control" value="Auto-generated" readonly style="background-color: #f5f5f5; cursor: not-allowed;">
                    <small class="text-muted">Department code will be auto-generated (e.g., DEPT-001, DEPT-002)</small>
                </div>
                <div class="form-group">
                    <label>Department Name <span class="required">*</span></label>
                    <input type="text" name="department_name" required class="form-control" 
                           value="<?php echo sanitizeOutput($_POST['department_name'] ?? ''); ?>"
                           placeholder="e.g., Computer Science">
                </div>
            </div>
            
            <div class="form-group">
                <label>Department Logo</label>
                <input type="file" name="logo" accept="image/jpeg,image/jpg,image/png,image/gif,image/svg+xml" class="form-control">
                <small class="text-muted">Upload department logo (JPG, PNG, GIF, or SVG - Max 5MB)</small>
            </div>

            <div class="form-group">
                <label>Head of Department</label>
                <select name="head_faculty_id" class="form-control">
                    <option value="">Select Faculty Member (Optional)</option>
                    <?php foreach ($faculty_list as $faculty): ?>
                        <option value="<?php echo $faculty['faculty_id']; ?>"
                                <?php echo (($_POST['head_faculty_id'] ?? '') == $faculty['faculty_id']) ? 'selected' : ''; ?>>
                            <?php echo sanitizeOutput($faculty['first_name'] . ' ' . $faculty['last_name'] . ' (' . $faculty['employee_id'] . ')'); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group">
                <label>Description</label>
                <textarea name="description" class="form-control" rows="4"><?php echo sanitizeOutput($_POST['description'] ?? ''); ?></textarea>
            </div>

            <div class="form-actions">
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-save"></i> Add Department
                </button>
                <a href="<?php echo BASE_URL; ?>modules/curriculum/index.php?tab=departments" class="btn btn-secondary">Cancel</a>
            </div>
        </form>
    </div>
</div>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
