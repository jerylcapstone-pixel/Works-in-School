<?php
/**
 * Student Library Portal
 * Browse and download library materials
 */

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/feature_toggles.php';
requireAuth([ROLE_STUDENT, ROLE_FACULTY]);

$page_title = 'Digital Library';
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

// Get user role
$user_role = $_SESSION['user_role'];

// Get filter parameters
$search = isset($_GET['search']) ? sanitizeInput($_GET['search']) : '';
$category_filter = isset($_GET['category']) ? sanitizeInput($_GET['category']) : '';

// Build query with access level filter
$query = "SELECT lm.*, lc.category_name, d.department_name, p.program_name
          FROM library_materials lm
          LEFT JOIN library_categories lc ON lm.category_id = lc.category_id
          LEFT JOIN departments d ON lm.department_id = d.department_id
          LEFT JOIN programs p ON lm.program_id = p.program_id
          WHERE lm.is_active = 1";

// Filter by access level
if ($user_role === ROLE_STUDENT) {
    $query .= " AND (lm.access_level = 'Public' OR lm.access_level = 'Students')";
} elseif ($user_role === ROLE_FACULTY) {
    $query .= " AND (lm.access_level = 'Public' OR lm.access_level = 'Faculty')";
}

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
        <h1><i class="fas fa-book-open"></i> Digital Library & Research Hub</h1>
    </div>

    <?php if ($error): ?>
        <div class="alert alert-error">
            <i class="fas fa-exclamation-circle"></i> <?php echo sanitizeOutput($error); ?>
        </div>
    <?php endif; ?>

    <?php if ($table_exists): ?>
    <div class="card" style="margin-bottom: 1.5rem;">
        <h2>Search & Filter</h2>
        <form method="GET" action="" class="form" style="display: flex; gap: 1rem; flex-wrap: wrap; align-items: flex-end;">
            <div class="form-group" style="flex: 2; min-width: 250px;">
                <label for="search">Search</label>
                <input type="text" id="search" name="search" class="form-control" 
                       placeholder="Search by title, description, or keywords..." 
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
            <div class="form-group" style="flex: 0 0 auto;">
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-search"></i> Search
                </button>
                <a href="<?php echo BASE_URL; ?>modules/library/student_portal.php" class="btn btn-secondary">
                    <i class="fas fa-times"></i> Clear
                </a>
            </div>
        </form>
    </div>

    <div class="card">
        <h2>Available Materials (<?php echo count($materials); ?>)</h2>
        <?php if (empty($materials)): ?>
            <p class="text-center" style="padding: 2rem;">No materials found matching your criteria.</p>
        <?php else: ?>
            <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(300px, 1fr)); gap: 1.5rem;">
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
                <div style="border: 1px solid #ddd; border-radius: 5px; padding: 1.5rem; background: #fff; display: flex; flex-direction: column;">
                    <div style="flex: 1;">
                        <h3 style="margin: 0 0 0.5rem 0; font-size: 1.1rem;"><?php echo sanitizeOutput($material['title']); ?></h3>
                        <div style="margin-bottom: 0.5rem;">
                            <span class="badge badge-info"><?php echo sanitizeOutput($material['category_name'] ?? 'Uncategorized'); ?></span>
                            <span class="badge badge-secondary"><?php echo strtoupper($material['file_extension']); ?></span>
                        </div>
                        <?php if (!empty($material['description'])): ?>
                            <p style="font-size: 0.9rem; color: #666; margin: 0.5rem 0;">
                                <?php echo sanitizeOutput(substr($material['description'], 0, 100)); ?>
                                <?php if (strlen($material['description']) > 100): ?>...<?php endif; ?>
                            </p>
                        <?php endif; ?>
                        <div style="font-size: 0.85rem; color: #999; margin-top: 0.5rem;">
                            <div><i class="fas fa-file"></i> <?php echo $size_formatted; ?></div>
                            <div><i class="fas fa-download"></i> <?php echo (int)$material['download_count']; ?> downloads</div>
                            <?php if ($material['author']): ?>
                                <div><i class="fas fa-user"></i> <?php echo sanitizeOutput($material['author']); ?></div>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div style="margin-top: 1rem; padding-top: 1rem; border-top: 1px solid #eee;">
                        <a href="<?php echo BASE_URL; ?>modules/library/download.php?id=<?php echo $material['material_id']; ?>" 
                           class="btn btn-primary btn-sm" style="width: 100%;">
                            <i class="fas fa-download"></i> Download
                        </a>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
    <?php endif; ?>
</div>

<?php include __DIR__ . '/../../includes/footer.php'; ?>

