<?php
/**
 * Digital Library - Manage Materials
 * Admin/Faculty view to manage library materials
 */

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/feature_toggles.php';
requireAuth([ROLE_ADMIN, ROLE_FACULTY]);

$page_title = 'Digital Library Management';
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

if (!$table_exists) {
    $error = 'The library_materials table does not exist. Please run the migration: database/migration_digital_library.sql';
}

// Get filter parameters
$search = isset($_GET['search']) ? sanitizeInput($_GET['search']) : '';
$category_filter = isset($_GET['category']) ? sanitizeInput($_GET['category']) : '';
$access_filter = isset($_GET['access_level']) ? sanitizeInput($_GET['access_level']) : '';

// Build query with filters
$query = "SELECT lm.*, lc.category_name, d.department_name, p.program_name, u.username as uploaded_by_username
          FROM library_materials lm
          LEFT JOIN library_categories lc ON lm.category_id = lc.category_id
          LEFT JOIN departments d ON lm.department_id = d.department_id
          LEFT JOIN programs p ON lm.program_id = p.program_id
          LEFT JOIN users u ON lm.uploaded_by = u.user_id
          WHERE 1=1";
$params = [];

if (!empty($search)) {
    $query .= " AND (lm.title LIKE ? OR lm.description LIKE ? OR lm.keywords LIKE ?)";
    $search_param = "%{$search}%";
    $params = array_merge($params, [$search_param, $search_param, $search_param]);
}

if (!empty($category_filter)) {
    $query .= " AND lm.category_id = ?";
    $params[] = (int)$category_filter;
}

if (!empty($access_filter)) {
    $query .= " AND lm.access_level = ?";
    $params[] = $access_filter;
}

$query .= " ORDER BY lm.created_at DESC";

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$materials = $stmt->fetchAll();

// Get categories for filter
$categories = [];
if ($table_exists) {
    try {
        $stmt = $pdo->query("SELECT category_id, category_name FROM library_categories ORDER BY category_name");
        $categories = $stmt->fetchAll();
    } catch (PDOException $e) {
        // Table might not exist
    }
}

include __DIR__ . '/../../includes/header.php';
?>

<div class="page-container">
    <div class="page-header">
        <h1><i class="fas fa-book"></i> Digital Library Management</h1>
        <a href="<?php echo BASE_URL; ?>modules/library/add.php" class="btn btn-primary">
            <i class="fas fa-plus"></i> Upload Material
        </a>
    </div>

    <?php if ($error): ?>
        <div class="alert alert-error">
            <i class="fas fa-exclamation-circle"></i> <?php echo sanitizeOutput($error); ?>
        </div>
    <?php endif; ?>

    <?php if ($success || isset($_GET['success'])): ?>
        <div class="alert alert-success">
            <i class="fas fa-check-circle"></i> <?php echo sanitizeOutput($success ?: $_GET['success']); ?>
        </div>
    <?php endif; ?>

    <?php if ($table_exists): ?>
    <div class="card" style="margin-bottom: 1.5rem;">
        <h2>Filters</h2>
        <form method="GET" action="" class="form" style="display: flex; gap: 1rem; flex-wrap: wrap; align-items: flex-end;">
            <div class="form-group" style="flex: 1; min-width: 200px;">
                <label for="search">Search</label>
                <input type="text" id="search" name="search" class="form-control" 
                       placeholder="Search materials..." 
                       value="<?php echo sanitizeOutput($search); ?>">
            </div>
            <div class="form-group" style="flex: 1; min-width: 150px;">
                <label for="category">Category</label>
                <select id="category" name="category" class="form-control">
                    <option value="">All Categories</option>
                    <?php foreach ($categories as $cat): ?>
                        <option value="<?php echo $cat['category_id']; ?>" 
                                <?php echo $category_filter == $cat['category_id'] ? 'selected' : ''; ?>>
                            <?php echo sanitizeOutput($cat['category_name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group" style="flex: 1; min-width: 150px;">
                <label for="access_level">Access Level</label>
                <select id="access_level" name="access_level" class="form-control">
                    <option value="">All Access Levels</option>
                    <option value="Public" <?php echo $access_filter === 'Public' ? 'selected' : ''; ?>>Public</option>
                    <option value="Students" <?php echo $access_filter === 'Students' ? 'selected' : ''; ?>>Students</option>
                    <option value="Faculty" <?php echo $access_filter === 'Faculty' ? 'selected' : ''; ?>>Faculty</option>
                    <option value="Restricted" <?php echo $access_filter === 'Restricted' ? 'selected' : ''; ?>>Restricted</option>
                </select>
            </div>
            <div class="form-group" style="flex: 0 0 auto;">
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-search"></i> Filter
                </button>
                <a href="<?php echo BASE_URL; ?>modules/library/index.php" class="btn btn-secondary">
                    <i class="fas fa-times"></i> Clear
                </a>
            </div>
        </form>
    </div>

    <div class="card">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem;">
            <h2 style="margin: 0;">Library Materials (<?php echo count($materials); ?>)</h2>
        </div>
        <div style="overflow-x: auto;">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Title</th>
                        <th>Category</th>
                        <th>File Type</th>
                        <th>Size</th>
                        <th>Access Level</th>
                        <th>Downloads</th>
                        <th>Views</th>
                        <th>Uploaded By</th>
                        <th>Uploaded</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($materials)): ?>
                        <tr><td colspan="11" class="text-center">No materials found.</td></tr>
                    <?php else: ?>
                        <?php foreach ($materials as $material): 
                            // Format file size
                            $file_size = (int)$material['file_size'];
                            $size_formatted = '';
                            if ($file_size < 1024) {
                                $size_formatted = $file_size . ' B';
                            } elseif ($file_size < 1048576) {
                                $size_formatted = number_format($file_size / 1024, 2) . ' KB';
                            } else {
                                $size_formatted = number_format($file_size / 1048576, 2) . ' MB';
                            }
                        ?>
                        <tr>
                            <td><strong><?php echo sanitizeOutput($material['title']); ?></strong></td>
                            <td><?php echo sanitizeOutput($material['category_name'] ?? 'N/A'); ?></td>
                            <td>
                                <span class="badge badge-info"><?php echo strtoupper($material['file_extension']); ?></span>
                            </td>
                            <td><?php echo $size_formatted; ?></td>
                            <td>
                                <span class="badge badge-primary"><?php echo sanitizeOutput($material['access_level']); ?></span>
                            </td>
                            <td><?php echo (int)$material['download_count']; ?></td>
                            <td><?php echo (int)$material['view_count']; ?></td>
                            <td><?php echo sanitizeOutput($material['uploaded_by_username'] ?? 'N/A'); ?></td>
                            <td><?php echo date('M d, Y', strtotime($material['created_at'])); ?></td>
                            <td>
                                <span class="badge badge-<?php echo $material['is_active'] ? 'success' : 'secondary'; ?>">
                                    <?php echo $material['is_active'] ? 'Active' : 'Inactive'; ?>
                                </span>
                            </td>
                            <td class="actions">
                                <a href="<?php echo BASE_URL; ?>modules/library/view.php?id=<?php echo $material['material_id']; ?>" 
                                   class="btn btn-sm btn-info" title="View">
                                    <i class="fas fa-eye"></i>
                                </a>
                                <a href="<?php echo BASE_URL; ?>modules/library/edit.php?id=<?php echo $material['material_id']; ?>" 
                                   class="btn btn-sm btn-warning" title="Edit">
                                    <i class="fas fa-edit"></i>
                                </a>
                                <a href="<?php echo BASE_URL; ?>modules/library/delete.php?id=<?php echo $material['material_id']; ?>" 
                                   class="btn btn-sm btn-danger" 
                                   onclick="return confirm('Are you sure you want to delete this material? This will also delete the file.');"
                                   title="Delete">
                                    <i class="fas fa-trash"></i>
                                </a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php endif; ?>
</div>

<?php include __DIR__ . '/../../includes/footer.php'; ?>

