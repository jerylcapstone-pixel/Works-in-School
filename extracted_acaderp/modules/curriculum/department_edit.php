<?php
/**
 * Curriculum Management - Edit Department
 */

require_once __DIR__ . '/../../config/config.php';
requireAuth(ROLE_ADMIN);

$page_title = 'Edit Department';

$dept_id = (int)($_GET['id'] ?? 0);
if (!$dept_id) {
    header('Location: ' . BASE_URL . 'modules/curriculum/index.php?tab=departments&error=Invalid department ID');
    exit;
}

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

// Get department data
$stmt = $pdo->prepare("SELECT * FROM departments WHERE department_id = ?");
$stmt->execute([$dept_id]);
$department = $stmt->fetch();

if (!$department) {
    header('Location: ' . BASE_URL . 'modules/curriculum/index.php?tab=departments&error=Department not found');
    exit;
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
    $logo_path = $department['logo_path']; // Keep existing logo if no new one uploaded
    if (isset($_FILES['logo']) && $_FILES['logo']['error'] === UPLOAD_ERR_OK) {
        $logo_result = handleLogoUpload($_FILES['logo']);
        if ($logo_result === false) {
            $error = 'Invalid logo file. Please upload JPG, PNG, GIF, or SVG only.';
        } elseif ($logo_result !== null) {
            // Delete old logo if exists
            if (!empty($department['logo_path']) && file_exists(__DIR__ . '/../../' . $department['logo_path'])) {
                @unlink(__DIR__ . '/../../' . $department['logo_path']);
            }
            $logo_path = $logo_result;
        }
    }

    if (empty($error) && empty($department_name)) {
        $error = 'Department name is required.';
    } elseif (empty($error)) {
        // Update department (code is auto-generated, so we don't update it)
        $sql = "UPDATE departments SET department_name = ?, logo_path = ?, head_faculty_id = ?, description = ?
                WHERE department_id = ?";
        $stmt = $pdo->prepare($sql);
        if ($stmt->execute([$department_name, $logo_path, $head_faculty_id, $description ?: null, $dept_id])) {
            header('Location: ' . BASE_URL . 'modules/curriculum/index.php?tab=departments&success=Department updated successfully');
            exit;
        } else {
            $error = 'Error updating department.';
        }
    }
    $department = array_merge($department, $_POST);
    $department['logo_path'] = $logo_path; // Update with new logo path
}

include __DIR__ . '/../../includes/header.php';
?>

<div class="page-container">
    <div class="page-header">
        <h1><i class="fas fa-edit"></i> Edit Department</h1>
        <a href="<?php echo BASE_URL; ?>modules/curriculum/index.php?tab=departments" class="btn btn-secondary">
            <i class="fas fa-arrow-left"></i> Back
        </a>
    </div>

    <?php if ($error): ?>
        <div class="alert alert-error"><?php echo sanitizeOutput($error); ?></div>
    <?php endif; ?>

    <div class="card">
        <form method="POST" action="" enctype="multipart/form-data" class="form">
            <?php if (!empty($department['logo_path'])): ?>
            <div class="form-group" style="text-align: center;">
                <label>Current Logo</label><br>
                <img src="<?php echo BASE_URL . $department['logo_path']; ?>" alt="Department Logo" 
                     style="max-width: 200px; max-height: 200px; border: 2px solid #ddd; border-radius: 5px; padding: 5px;">
            </div>
            <?php endif; ?>
            
            <div class="form-row">
                <div class="form-group">
                    <label>Department Code</label>
                    <input type="text" class="form-control" 
                           value="<?php echo sanitizeOutput($department['department_code']); ?>" 
                           readonly style="background-color: #f5f5f5; cursor: not-allowed;">
                    <small class="text-muted">Department code is auto-generated and cannot be changed</small>
                </div>
                <div class="form-group">
                    <label>Department Name <span class="required">*</span></label>
                    <input type="text" name="department_name" required class="form-control" 
                           value="<?php echo sanitizeOutput($department['department_name']); ?>">
                </div>
            </div>
            
            <div class="form-group">
                <label>Department Logo</label>
                <input type="file" name="logo" accept="image/jpeg,image/jpg,image/png,image/gif,image/svg+xml" class="form-control">
                <small class="text-muted"><?php echo !empty($department['logo_path']) ? 'Upload a new logo to replace the current one. ' : ''; ?>Accepted formats: JPG, PNG, GIF, or SVG - Max 5MB</small>
            </div>

            <div class="form-group">
                <label>Head of Department</label>
                <select name="head_faculty_id" class="form-control">
                    <option value="">Select Faculty Member (Optional)</option>
                    <?php foreach ($faculty_list as $faculty): ?>
                        <option value="<?php echo $faculty['faculty_id']; ?>"
                                <?php echo (($department['head_faculty_id'] ?? '') == $faculty['faculty_id']) ? 'selected' : ''; ?>>
                            <?php echo sanitizeOutput($faculty['first_name'] . ' ' . $faculty['last_name'] . ' (' . $faculty['employee_id'] . ')'); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group">
                <label>Description</label>
                <textarea name="description" class="form-control" rows="4"><?php echo sanitizeOutput($department['description'] ?? ''); ?></textarea>
            </div>

            <div class="form-actions">
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-save"></i> Update Department
                </button>
                <a href="<?php echo BASE_URL; ?>modules/curriculum/index.php?tab=departments" class="btn btn-secondary">Cancel</a>
            </div>
        </form>
    </div>
</div>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
