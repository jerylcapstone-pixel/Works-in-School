<?php
/**
 * Announcements Management - List All Announcements
 * Admin view to manage all announcements
 */

require_once __DIR__ . '/../../config/config.php';
requireAuth([ROLE_ADMIN]);

$page_title = 'Announcements Management';
$pdo = getDBConnection();
$error = '';
$success = '';

// Check if announcements table exists
$table_exists = false;
$department_column_exists = false;
try {
    $stmt = $pdo->query("SHOW TABLES LIKE 'announcements'");
    $table_exists = $stmt->rowCount() > 0;
    
    // Check if department_id column exists
    if ($table_exists) {
        $stmt = $pdo->query("SHOW COLUMNS FROM announcements LIKE 'department_id'");
        $department_column_exists = $stmt->rowCount() > 0;
    }
} catch (PDOException $e) {
    $table_exists = false;
    $department_column_exists = false;
}

if (!$table_exists) {
    $error = 'The announcements table does not exist. Please run the migration: database/migration_announcements.sql';
} elseif (!$department_column_exists) {
    $error = 'The department_id column does not exist. Please run the migration: database/migration_announcements_department_filter.sql';
}

// Get filter parameters
$search = isset($_GET['search']) ? sanitizeInput($_GET['search']) : '';
$category_filter = isset($_GET['category']) ? sanitizeInput($_GET['category']) : '';
$visibility_filter = isset($_GET['visibility']) ? sanitizeInput($_GET['visibility']) : '';
$status_filter = isset($_GET['status']) ? sanitizeInput($_GET['status']) : '';

// Build query with filters
if ($department_column_exists) {
    $query = "SELECT a.*, u.username as created_by_username, d.department_name
              FROM announcements a
              LEFT JOIN users u ON a.created_by = u.user_id
              LEFT JOIN departments d ON a.department_id = d.department_id
              WHERE 1=1";
} else {
    $query = "SELECT a.*, u.username as created_by_username
              FROM announcements a
              LEFT JOIN users u ON a.created_by = u.user_id
              WHERE 1=1";
}
$params = [];

if (!empty($search)) {
    $query .= " AND (a.title LIKE ? OR a.content LIKE ?)";
    $search_param = "%{$search}%";
    $params = array_merge($params, [$search_param, $search_param]);
}

if (!empty($category_filter)) {
    $query .= " AND a.category = ?";
    $params[] = $category_filter;
}

if (!empty($visibility_filter)) {
    $query .= " AND a.visibility = ?";
    $params[] = $visibility_filter;
}

if ($status_filter === 'active') {
    $query .= " AND a.is_active = 1";
} elseif ($status_filter === 'inactive') {
    $query .= " AND a.is_active = 0";
}

$query .= " ORDER BY a.created_at DESC";

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$announcements = $stmt->fetchAll();

include __DIR__ . '/../../includes/header.php';
?>

<div class="page-container">
    <div class="page-header">
        <h1><i class="fas fa-bullhorn"></i> Announcements Management</h1>
        <a href="<?php echo BASE_URL; ?>modules/announcements/add.php" class="btn btn-primary">
            <i class="fas fa-plus"></i> Add New Announcement
        </a>
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
    <div class="card" style="margin-bottom: 1.5rem;">
        <h2>Filters</h2>
        <form method="GET" action="" class="form" style="display: flex; gap: 1rem; flex-wrap: wrap; align-items: flex-end;">
            <div class="form-group" style="flex: 1; min-width: 200px;">
                <label for="search">Search</label>
                <input type="text" id="search" name="search" class="form-control" 
                       placeholder="Search title or content..." 
                       value="<?php echo sanitizeOutput($search); ?>">
            </div>
            <div class="form-group" style="flex: 1; min-width: 150px;">
                <label for="category">Category</label>
                <select id="category" name="category" class="form-control">
                    <option value="">All Categories</option>
                    <option value="General" <?php echo $category_filter === 'General' ? 'selected' : ''; ?>>General</option>
                    <option value="Academic" <?php echo $category_filter === 'Academic' ? 'selected' : ''; ?>>Academic</option>
                    <option value="Events" <?php echo $category_filter === 'Events' ? 'selected' : ''; ?>>Events</option>
                    <option value="Financial" <?php echo $category_filter === 'Financial' ? 'selected' : ''; ?>>Financial</option>
                    <option value="Faculty" <?php echo $category_filter === 'Faculty' ? 'selected' : ''; ?>>Faculty</option>
                </select>
            </div>
            <div class="form-group" style="flex: 1; min-width: 150px;">
                <label for="visibility">Target Audience</label>
                <select id="visibility" name="visibility" class="form-control">
                    <option value="">All</option>
                    <option value="All" <?php echo $visibility_filter === 'All' ? 'selected' : ''; ?>>All (Students & Faculty)</option>
                    <option value="All_Students" <?php echo $visibility_filter === 'All_Students' ? 'selected' : ''; ?>>All Students</option>
                    <option value="All_Faculty" <?php echo $visibility_filter === 'All_Faculty' ? 'selected' : ''; ?>>All Faculty</option>
                    <option value="Students_Department" <?php echo $visibility_filter === 'Students_Department' ? 'selected' : ''; ?>>Students by Department</option>
                    <option value="Faculty_Department" <?php echo $visibility_filter === 'Faculty_Department' ? 'selected' : ''; ?>>Faculty by Department</option>
                </select>
            </div>
            <div class="form-group" style="flex: 1; min-width: 150px;">
                <label for="status">Status</label>
                <select id="status" name="status" class="form-control">
                    <option value="">All Status</option>
                    <option value="active" <?php echo $status_filter === 'active' ? 'selected' : ''; ?>>Active</option>
                    <option value="inactive" <?php echo $status_filter === 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                </select>
            </div>
            <div class="form-group" style="flex: 0 0 auto;">
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-search"></i> Filter
                </button>
                <a href="<?php echo BASE_URL; ?>modules/announcements/index.php" class="btn btn-secondary">
                    <i class="fas fa-times"></i> Clear
                </a>
            </div>
        </form>
    </div>

    <div class="card">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem;">
            <h2 style="margin: 0;">Announcements (<?php echo count($announcements); ?>)</h2>
        </div>
        <div style="overflow-x: auto;">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Title</th>
                        <th>Category</th>
                        <th>Target Audience</th>
                        <?php if ($department_column_exists): ?>
                        <th>Department</th>
                        <?php endif; ?>
                        <th>Expiration Date</th>
                        <th>Status</th>
                        <th>Created By</th>
                        <th>Created At</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($announcements)): ?>
                        <tr><td colspan="<?php echo $department_column_exists ? '9' : '8'; ?>" class="text-center">No announcements found.</td></tr>
                    <?php else: ?>
                        <?php foreach ($announcements as $ann): 
                            $is_expired = !empty($ann['expiration_date']) && strtotime($ann['expiration_date']) < time();
                            $status_badge = $ann['is_active'] ? 'success' : 'secondary';
                            if ($is_expired && $ann['is_active']) {
                                $status_badge = 'warning';
                            }
                            
                            // Format visibility display
                            $visibility_display = [
                                'All' => 'All (Students & Faculty)',
                                'All_Students' => 'All Students',
                                'All_Faculty' => 'All Faculty',
                                'Students_Department' => 'Students by Department',
                                'Faculty_Department' => 'Faculty by Department'
                            ];
                            $visibility_text = $visibility_display[$ann['visibility']] ?? $ann['visibility'];
                        ?>
                        <tr>
                            <td><strong><?php echo sanitizeOutput($ann['title']); ?></strong></td>
                            <td>
                                <span class="badge badge-info"><?php echo sanitizeOutput($ann['category']); ?></span>
                            </td>
                            <td>
                                <span class="badge badge-primary"><?php echo sanitizeOutput($visibility_text); ?></span>
                            </td>
                            <?php if ($department_column_exists): ?>
                            <td>
                                <?php if (!empty($ann['department_name'])): ?>
                                    <span class="badge badge-secondary"><?php echo sanitizeOutput($ann['department_name']); ?></span>
                                <?php else: ?>
                                    <span class="text-muted">N/A</span>
                                <?php endif; ?>
                            </td>
                            <?php endif; ?>
                            <td>
                                <?php if ($ann['expiration_date']): ?>
                                    <?php echo date('M d, Y', strtotime($ann['expiration_date'])); ?>
                                    <?php if ($is_expired): ?>
                                        <br><small class="text-warning">(Expired)</small>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <span class="text-muted">No expiration</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <span class="badge badge-<?php echo $status_badge; ?>">
                                    <?php echo $ann['is_active'] ? 'Active' : 'Inactive'; ?>
                                    <?php if ($is_expired && $ann['is_active']): ?>
                                        (Expired)
                                    <?php endif; ?>
                                </span>
                            </td>
                            <td><?php echo sanitizeOutput($ann['created_by_username'] ?? 'N/A'); ?></td>
                            <td><?php echo date('M d, Y', strtotime($ann['created_at'])); ?></td>
                            <td class="actions">
                                <a href="<?php echo BASE_URL; ?>modules/announcements/view.php?id=<?php echo $ann['announcement_id']; ?>" 
                                   class="btn btn-sm btn-info" title="View">
                                    <i class="fas fa-eye"></i>
                                </a>
                                <a href="<?php echo BASE_URL; ?>modules/announcements/edit.php?id=<?php echo $ann['announcement_id']; ?>" 
                                   class="btn btn-sm btn-warning" title="Edit">
                                    <i class="fas fa-edit"></i>
                                </a>
                                <a href="<?php echo BASE_URL; ?>modules/announcements/delete.php?id=<?php echo $ann['announcement_id']; ?>" 
                                   class="btn btn-sm btn-danger" 
                                   onclick="return confirm('Are you sure you want to delete this announcement?');"
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

