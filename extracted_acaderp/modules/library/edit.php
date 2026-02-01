<?php
/**
 * Edit Library Material
 * Form to edit existing library material
 */

require_once __DIR__ . '/../../config/config.php';
requireAuth([ROLE_ADMIN, ROLE_FACULTY]);

$page_title = 'Edit Library Material';
$pdo = getDBConnection();
$error = '';
$material_id = (int)($_GET['id'] ?? 0);

if (!$material_id) {
    header('Location: ' . BASE_URL . 'modules/library/index.php?error=Invalid material ID');
    exit;
}

// Get material data
$material = null;
try {
    $stmt = $pdo->prepare("SELECT * FROM library_materials WHERE material_id = ?");
    $stmt->execute([$material_id]);
    $material = $stmt->fetch();
} catch (PDOException $e) {
    $error = 'Failed to load material.';
}

if (!$material) {
    header('Location: ' . BASE_URL . 'modules/library/index.php?error=Material not found');
    exit;
}

// Get categories, departments, and programs
$categories = [];
$departments = [];
$programs = [];

try {
    $stmt = $pdo->query("SELECT category_id, category_name FROM library_categories ORDER BY category_name");
    $categories = $stmt->fetchAll();
    
    $stmt = $pdo->query("SELECT department_id, department_name FROM departments ORDER BY department_name");
    $departments = $stmt->fetchAll();
    
    // Get ALL programs with department_id for client-side filtering
    try {
        $stmt = $pdo->query("SELECT program_id, program_name, department_id FROM programs WHERE status = 'Active' ORDER BY program_name");
        $programs = $stmt->fetchAll();
    } catch (PDOException $e) {
        // If status column doesn't exist, get all programs
        $stmt = $pdo->query("SELECT program_id, program_name, department_id FROM programs ORDER BY program_name");
        $programs = $stmt->fetchAll();
    }
} catch (PDOException $e) {
    // Tables might not exist
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = sanitizeInput($_POST['title'] ?? '');
    $description = sanitizeInput($_POST['description'] ?? '');
    $category_id = !empty($_POST['category_id']) ? (int)$_POST['category_id'] : null;
    $author = sanitizeInput($_POST['author'] ?? '');
    $publisher = sanitizeInput($_POST['publisher'] ?? '');
    $publication_date = !empty($_POST['publication_date']) ? $_POST['publication_date'] : null;
    $isbn = sanitizeInput($_POST['isbn'] ?? '');
    $keywords = sanitizeInput($_POST['keywords'] ?? '');
    $access_level = sanitizeInput($_POST['access_level'] ?? 'Public');
    $department_id = !empty($_POST['department_id']) ? (int)$_POST['department_id'] : null;
    $program_id = !empty($_POST['program_id']) ? (int)$_POST['program_id'] : null;
    $is_active = isset($_POST['is_active']) ? 1 : 0;
    
    // Handle file replacement if new file uploaded
    $file_updated = false;
    $new_file_name = $material['file_name'];
    $new_file_path = $material['file_path'];
    $new_file_size = $material['file_size'];
    $new_file_type = $material['file_type'];
    $new_file_extension = $material['file_extension'];
    
    if (isset($_FILES['file']) && $_FILES['file']['error'] === UPLOAD_ERR_OK) {
        $uploaded_file = $_FILES['file'];
        $tmp_name = $uploaded_file['tmp_name'];
        $new_file_size = $uploaded_file['size'];
        $new_file_type = $uploaded_file['type'];
        $new_file_extension = strtolower(pathinfo($uploaded_file['name'], PATHINFO_EXTENSION));
        
        $allowed_extensions = ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx', 'txt', 'zip', 'rar', 'jpg', 'jpeg', 'png', 'mp4', 'mp3'];
        if (!in_array($new_file_extension, $allowed_extensions)) {
            $error = 'Invalid file type.';
        } elseif ($new_file_size > 100 * 1024 * 1024) {
            $error = 'File size exceeds 100MB limit.';
        } else {
            $upload_dir = __DIR__ . '/../../uploads/library/';
            $unique_name = uniqid() . '_' . time() . '.' . $new_file_extension;
            $new_file_path = $upload_dir . $unique_name;
            
            if (move_uploaded_file($tmp_name, $new_file_path)) {
                // Delete old file
                if (file_exists($material['file_path'])) {
                    unlink($material['file_path']);
                }
                $new_file_name = $unique_name;
                $file_updated = true;
            } else {
                $error = 'Failed to upload new file.';
            }
        }
    }
    
    if (empty($title)) {
        $error = 'Title is required.';
    } else {
        try {
            $stmt = $pdo->prepare("UPDATE library_materials SET title = ?, description = ?, category_id = ?, 
                                  file_name = ?, file_path = ?, file_size = ?, file_type = ?, file_extension = ?,
                                  author = ?, publisher = ?, publication_date = ?, isbn = ?, keywords = ?, 
                                  access_level = ?, department_id = ?, program_id = ?, is_active = ?, 
                                  updated_at = CURRENT_TIMESTAMP 
                                  WHERE material_id = ?");
            $stmt->execute([
                $title, $description, $category_id, $new_file_name, $new_file_path, $new_file_size, 
                $new_file_type, $new_file_extension, $author, $publisher, $publication_date, $isbn, 
                $keywords, $access_level, $department_id, $program_id, $is_active, $material_id
            ]);
            
            header('Location: ' . BASE_URL . 'modules/library/index.php?success=Material updated successfully');
            exit;
        } catch (PDOException $e) {
            if ($file_updated && file_exists($new_file_path)) {
                unlink($new_file_path);
            }
            $error = 'Failed to update material: ' . $e->getMessage();
        }
    }
    
    // Update $material with POST data
    $material = array_merge($material, $_POST);
}

include __DIR__ . '/../../includes/header.php';
?>

<div class="page-container">
    <div class="page-header">
        <h1><i class="fas fa-edit"></i> Edit Library Material</h1>
        <a href="<?php echo BASE_URL; ?>modules/library/index.php" class="btn btn-secondary">
            <i class="fas fa-arrow-left"></i> Back to List
        </a>
    </div>

    <?php if ($error): ?>
        <div class="alert alert-error">
            <i class="fas fa-exclamation-circle"></i> <?php echo sanitizeOutput($error); ?>
        </div>
    <?php endif; ?>

    <div class="card">
        <form method="POST" action="" enctype="multipart/form-data" class="form">
            <div class="form-section">
                <h3>File</h3>
                
                <div class="form-group">
                    <label>Current File:</label>
                    <p><?php echo sanitizeOutput($material['file_name']); ?> 
                       (<?php echo number_format($material['file_size'] / 1024, 2); ?> KB)</p>
                </div>
                
                <div class="form-group">
                    <label for="file">Replace File (Optional)</label>
                    <input type="file" id="file" name="file" class="form-control" accept=".pdf,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.txt,.zip,.rar,.jpg,.jpeg,.png,.mp4,.mp3">
                    <small class="text-muted">Leave empty to keep current file. Maximum: 100MB</small>
                </div>
            </div>

            <div class="form-section">
                <h3>Material Information</h3>
                
                <div class="form-group">
                    <label for="title">Title <span class="required">*</span></label>
                    <input type="text" id="title" name="title" required class="form-control" 
                           value="<?php echo sanitizeOutput($material['title']); ?>">
                </div>

                <div class="form-group">
                    <label for="description">Description</label>
                    <textarea id="description" name="description" class="form-control" rows="4"><?php echo sanitizeOutput($material['description']); ?></textarea>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label for="category_id">Category</label>
                        <select id="category_id" name="category_id" class="form-control">
                            <option value="">Select Category</option>
                            <?php foreach ($categories as $cat): ?>
                                <option value="<?php echo $cat['category_id']; ?>" 
                                        <?php echo $material['category_id'] == $cat['category_id'] ? 'selected' : ''; ?>>
                                    <?php echo sanitizeOutput($cat['category_name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="access_level">Access Level <span class="required">*</span></label>
                        <select id="access_level" name="access_level" required class="form-control">
                            <option value="Public" <?php echo $material['access_level'] === 'Public' ? 'selected' : ''; ?>>Public</option>
                            <option value="Students" <?php echo $material['access_level'] === 'Students' ? 'selected' : ''; ?>>Students Only</option>
                            <option value="Faculty" <?php echo $material['access_level'] === 'Faculty' ? 'selected' : ''; ?>>Faculty Only</option>
                            <option value="Restricted" <?php echo $material['access_level'] === 'Restricted' ? 'selected' : ''; ?>>Restricted</option>
                        </select>
                    </div>
                </div>
            </div>

            <div class="form-section">
                <h3>Additional Details</h3>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="author">Author</label>
                        <input type="text" id="author" name="author" class="form-control" 
                               value="<?php echo sanitizeOutput($material['author'] ?? ''); ?>">
                    </div>

                    <div class="form-group">
                        <label for="publisher">Publisher</label>
                        <input type="text" id="publisher" name="publisher" class="form-control" 
                               value="<?php echo sanitizeOutput($material['publisher'] ?? ''); ?>">
                    </div>

                    <div class="form-group">
                        <label for="publication_date">Publication Date</label>
                        <input type="date" id="publication_date" name="publication_date" class="form-control"
                               value="<?php echo $material['publication_date'] ? date('Y-m-d', strtotime($material['publication_date'])) : ''; ?>">
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label for="isbn">ISBN</label>
                        <input type="text" id="isbn" name="isbn" class="form-control" 
                               value="<?php echo sanitizeOutput($material['isbn'] ?? ''); ?>">
                    </div>

                    <div class="form-group">
                        <label for="department_id">Department</label>
                        <select id="department_id" name="department_id" class="form-control" onchange="filterProgramsByDepartment()">
                            <option value="">Select Department</option>
                            <?php foreach ($departments as $dept): ?>
                                <option value="<?php echo $dept['department_id']; ?>" 
                                        <?php echo $material['department_id'] == $dept['department_id'] ? 'selected' : ''; ?>>
                                    <?php echo sanitizeOutput($dept['department_name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="program_id">Program</label>
                        <select id="program_id" name="program_id" class="form-control">
                            <option value="">Select Program</option>
                            <?php foreach ($programs as $prog): ?>
                                <option value="<?php echo $prog['program_id']; ?>" 
                                        data-department-id="<?php echo $prog['department_id'] ?? ''; ?>"
                                        <?php echo $material['program_id'] == $prog['program_id'] ? 'selected' : ''; ?>>
                                    <?php echo sanitizeOutput($prog['program_name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div class="form-group">
                    <label for="keywords">Keywords</label>
                    <input type="text" id="keywords" name="keywords" class="form-control" 
                           value="<?php echo sanitizeOutput($material['keywords'] ?? ''); ?>">
                </div>

                <div class="form-group">
                    <label>
                        <input type="checkbox" name="is_active" value="1" 
                               <?php echo $material['is_active'] ? 'checked' : ''; ?>>
                        Active (Visible to users)
                    </label>
                </div>
            </div>

            <div class="form-actions">
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-save"></i> Update Material
                </button>
                <a href="<?php echo BASE_URL; ?>modules/library/index.php" class="btn btn-secondary">
                    <i class="fas fa-times"></i> Cancel
                </a>
            </div>
        </form>
    </div>
</div>

<script>
function filterProgramsByDepartment() {
    const departmentSelect = document.getElementById('department_id');
    const programSelect = document.getElementById('program_id');
    const selectedDepartmentId = departmentSelect.value;
    const options = programSelect.querySelectorAll('option');
    
    // Store currently selected program value
    const currentProgramValue = programSelect.value;
    
    options.forEach(option => {
        if (option.value === '') {
            // Always show the "Select Program" option
            option.style.display = 'block';
        } else {
            const dataDepartmentId = option.getAttribute('data-department-id');
            if (selectedDepartmentId && dataDepartmentId && dataDepartmentId != selectedDepartmentId) {
                // Hide programs that don't match the selected department
                option.style.display = 'none';
                // If this was the selected program, clear the selection
                if (option.value === currentProgramValue) {
                    programSelect.value = '';
                }
            } else {
                // Show programs that match or if no department is selected
                option.style.display = 'block';
            }
        }
    });
}

// Initialize on page load
document.addEventListener('DOMContentLoaded', function() {
    filterProgramsByDepartment();
});
</script>

<?php include __DIR__ . '/../../includes/footer.php'; ?>

