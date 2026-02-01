<?php
/**
 * Upload Library Material
 * Form to upload new library materials
 */

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/feature_toggles.php';
requireAuth([ROLE_ADMIN, ROLE_FACULTY]);

$page_title = 'Upload Library Material';
$pdo = getDBConnection();
$error = '';
$success = '';

// Check if library feature is enabled
if (!isLibraryEnabled($pdo)) {
    header('Location: ' . BASE_URL . 'dashboard/index.php?error=The Library feature is currently disabled');
    exit;
}

// Check if library_materials table exists
$table_exists = false;
try {
    $stmt = $pdo->query("SHOW TABLES LIKE 'library_materials'");
    $table_exists = $stmt->rowCount() > 0;
} catch (PDOException $e) {
    $table_exists = false;
}

// Get categories, departments, and programs
$categories = [];
$departments = [];
$programs = [];

if ($table_exists && $pdo) {
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
}

// Create uploads directory if it doesn't exist
$upload_dir = __DIR__ . '/../../uploads/library/';
if (!file_exists($upload_dir)) {
    mkdir($upload_dir, 0755, true);
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
    
    // Handle file upload
    $file_uploaded = false;
    $file_name = '';
    $file_path = '';
    $file_size = 0;
    $file_type = '';
    $file_extension = '';
    
    if (isset($_FILES['file']) && $_FILES['file']['error'] === UPLOAD_ERR_OK) {
        $uploaded_file = $_FILES['file'];
        $file_name = $uploaded_file['name'];
        $file_size = $uploaded_file['size'];
        $file_type = $uploaded_file['type'];
        $tmp_name = $uploaded_file['tmp_name'];
        
        // Get file extension
        $file_extension = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
        
        // Validate file type (allow common document types)
        $allowed_extensions = ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx', 'txt', 'zip', 'rar', 'jpg', 'jpeg', 'png', 'mp4', 'mp3'];
        if (!in_array($file_extension, $allowed_extensions)) {
            $error = 'Invalid file type. Allowed types: ' . implode(', ', $allowed_extensions);
        } elseif ($file_size > 100 * 1024 * 1024) { // 100MB limit
            $error = 'File size exceeds 100MB limit.';
        } else {
            // Generate unique filename
            $unique_name = uniqid() . '_' . time() . '.' . $file_extension;
            $file_path = $upload_dir . $unique_name;
            
            if (move_uploaded_file($tmp_name, $file_path)) {
                $file_uploaded = true;
                $file_name = $unique_name; // Store unique name in database
            } else {
                $error = 'Failed to upload file.';
            }
        }
    } else {
        $error = 'No file uploaded or upload error occurred.';
    }
    
    if (empty($title)) {
        $error = 'Title is required.';
    } elseif (!$file_uploaded) {
        // Error already set above
    } else {
        try {
            $stmt = $pdo->prepare("INSERT INTO library_materials (title, description, category_id, file_name, file_path, 
                                  file_size, file_type, file_extension, author, publisher, publication_date, isbn, 
                                  keywords, access_level, department_id, program_id, is_active, uploaded_by) 
                                  VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([
                $title, $description, $category_id, $file_name, $file_path, $file_size, $file_type, $file_extension,
                $author, $publisher, $publication_date, $isbn, $keywords, $access_level, $department_id, 
                $program_id, $is_active, $_SESSION['user_id']
            ]);
            
            header('Location: ' . BASE_URL . 'modules/library/index.php?success=Material uploaded successfully');
            exit;
        } catch (PDOException $e) {
            // Delete uploaded file on error
            if (file_exists($file_path)) {
                unlink($file_path);
            }
            $error = 'Failed to save material: ' . $e->getMessage();
        }
    }
}

include __DIR__ . '/../../includes/header.php';
?>

<div class="page-container">
    <div class="page-header">
        <h1><i class="fas fa-upload"></i> Upload Library Material</h1>
        <a href="<?php echo BASE_URL; ?>modules/library/index.php" class="btn btn-secondary">
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
        <form method="POST" action="" enctype="multipart/form-data" class="form">
            <div class="form-section">
                <h3>File Upload</h3>
                
                <div class="form-group">
                    <label for="file">File <span class="required">*</span></label>
                    <input type="file" id="file" name="file" required class="form-control" accept=".pdf,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.txt,.zip,.rar,.jpg,.jpeg,.png,.mp4,.mp3">
                    <small class="text-muted">Maximum file size: 100MB. Allowed types: PDF, DOC, DOCX, XLS, XLSX, PPT, PPTX, TXT, ZIP, RAR, JPG, PNG, MP4, MP3</small>
                </div>
            </div>

            <div class="form-section">
                <h3>Material Information</h3>
                
                <div class="form-group">
                    <label for="title">Title <span class="required">*</span></label>
                    <input type="text" id="title" name="title" required class="form-control" 
                           placeholder="Enter material title"
                           value="<?php echo sanitizeOutput($_POST['title'] ?? ''); ?>">
                </div>

                <div class="form-group">
                    <label for="description">Description</label>
                    <textarea id="description" name="description" class="form-control" rows="4"
                              placeholder="Enter material description"><?php echo sanitizeOutput($_POST['description'] ?? ''); ?></textarea>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label for="category_id">Category</label>
                        <select id="category_id" name="category_id" class="form-control">
                            <option value="">Select Category</option>
                            <?php foreach ($categories as $cat): ?>
                                <option value="<?php echo $cat['category_id']; ?>" 
                                        <?php echo (isset($_POST['category_id']) && $_POST['category_id'] == $cat['category_id']) ? 'selected' : ''; ?>>
                                    <?php echo sanitizeOutput($cat['category_name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="access_level">Access Level <span class="required">*</span></label>
                        <select id="access_level" name="access_level" required class="form-control">
                            <option value="Public" <?php echo (($_POST['access_level'] ?? 'Public') === 'Public') ? 'selected' : ''; ?>>Public</option>
                            <option value="Students" <?php echo (($_POST['access_level'] ?? '') === 'Students') ? 'selected' : ''; ?>>Students Only</option>
                            <option value="Faculty" <?php echo (($_POST['access_level'] ?? '') === 'Faculty') ? 'selected' : ''; ?>>Faculty Only</option>
                            <option value="Restricted" <?php echo (($_POST['access_level'] ?? '') === 'Restricted') ? 'selected' : ''; ?>>Restricted</option>
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
                               placeholder="Enter author name"
                               value="<?php echo sanitizeOutput($_POST['author'] ?? ''); ?>">
                    </div>

                    <div class="form-group">
                        <label for="publisher">Publisher</label>
                        <input type="text" id="publisher" name="publisher" class="form-control" 
                               placeholder="Enter publisher"
                               value="<?php echo sanitizeOutput($_POST['publisher'] ?? ''); ?>">
                    </div>

                    <div class="form-group">
                        <label for="publication_date">Publication Date</label>
                        <input type="date" id="publication_date" name="publication_date" class="form-control"
                               value="<?php echo sanitizeOutput($_POST['publication_date'] ?? ''); ?>">
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label for="isbn">ISBN</label>
                        <input type="text" id="isbn" name="isbn" class="form-control" 
                               placeholder="Enter ISBN"
                               value="<?php echo sanitizeOutput($_POST['isbn'] ?? ''); ?>">
                    </div>

                    <div class="form-group">
                        <label for="department_id">Department</label>
                        <select id="department_id" name="department_id" class="form-control" onchange="filterProgramsByDepartment()">
                            <option value="">Select Department (Optional)</option>
                            <?php foreach ($departments as $dept): ?>
                                <option value="<?php echo $dept['department_id']; ?>" 
                                        <?php echo (isset($_POST['department_id']) && $_POST['department_id'] == $dept['department_id']) ? 'selected' : ''; ?>>
                                    <?php echo sanitizeOutput($dept['department_name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="program_id">Program</label>
                        <select id="program_id" name="program_id" class="form-control">
                            <option value="">Select Program (Optional)</option>
                            <?php foreach ($programs as $prog): ?>
                                <option value="<?php echo $prog['program_id']; ?>" 
                                        data-department-id="<?php echo $prog['department_id'] ?? ''; ?>"
                                        <?php echo (isset($_POST['program_id']) && $_POST['program_id'] == $prog['program_id']) ? 'selected' : ''; ?>>
                                    <?php echo sanitizeOutput($prog['program_name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div class="form-group">
                    <label for="keywords">Keywords</label>
                    <input type="text" id="keywords" name="keywords" class="form-control" 
                           placeholder="Enter keywords (comma-separated)"
                           value="<?php echo sanitizeOutput($_POST['keywords'] ?? ''); ?>">
                    <small class="text-muted">Separate keywords with commas for better searchability</small>
                </div>

                <div class="form-group">
                    <label>
                        <input type="checkbox" name="is_active" value="1" 
                               <?php echo (!isset($_POST['is_active']) || $_POST['is_active'] == '1') ? 'checked' : ''; ?>>
                        Active (Visible to users)
                    </label>
                </div>
            </div>

            <div class="form-actions">
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-upload"></i> Upload Material
                </button>
                <a href="<?php echo BASE_URL; ?>modules/library/index.php" class="btn btn-secondary">
                    <i class="fas fa-times"></i> Cancel
                </a>
            </div>
        </form>
    </div>
    <?php endif; ?>
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

