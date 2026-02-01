<?php
/**
 * Faculty & Staff Management - Main Page
 * Lists all faculty with search and filter capabilities
 */

require_once __DIR__ . '/../../config/config.php';
requireAuth(ROLE_ADMIN);

$page_title = 'Faculty & Staff Management';

$pdo = getDBConnection();
$faculty_list = [];
$search = sanitizeInput($_GET['search'] ?? '');
$status_filter = sanitizeInput($_GET['status'] ?? '');

// Build query with filters
$query = "SELECT f.*, d.department_name, d.department_code
          FROM faculty f 
          LEFT JOIN departments d ON f.department_id = d.department_id 
          WHERE 1=1";
$params = [];

if (!empty($search)) {
    $query .= " AND (f.employee_id LIKE ? OR f.first_name LIKE ? OR f.last_name LIKE ? OR f.email LIKE ?)";
    $searchParam = "%$search%";
    $params = array_merge($params, [$searchParam, $searchParam, $searchParam, $searchParam]);
}

if (!empty($status_filter)) {
    $query .= " AND f.status = ?";
    $params[] = $status_filter;
}

$query .= " ORDER BY f.created_at DESC LIMIT 100";

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$faculty_list = $stmt->fetchAll();

include __DIR__ . '/../../includes/header.php';
?>

<div class="page-container">
    <div class="page-header">
        <h1><i class="fas fa-chalkboard-teacher"></i> Faculty & Staff Management</h1>
        <a href="<?php echo BASE_URL; ?>modules/faculty/add.php" class="btn btn-primary">
            <i class="fas fa-plus"></i> Add New Faculty
        </a>
    </div>

    <div class="filters-card">
        <form method="GET" action="" class="filters-form">
            <div class="form-group">
                <input type="text" name="search" placeholder="Search by Employee ID, Name, or Email..." 
                       value="<?php echo sanitizeOutput($search); ?>" class="form-control">
            </div>
            <div class="form-group">
                <select name="status" class="form-control">
                    <option value="">All Statuses</option>
                    <option value="Active" <?php echo $status_filter === 'Active' ? 'selected' : ''; ?>>Active</option>
                    <option value="On Leave" <?php echo $status_filter === 'On Leave' ? 'selected' : ''; ?>>On Leave</option>
                    <option value="Retired" <?php echo $status_filter === 'Retired' ? 'selected' : ''; ?>>Retired</option>
                    <option value="Terminated" <?php echo $status_filter === 'Terminated' ? 'selected' : ''; ?>>Terminated</option>
                </select>
            </div>
            <button type="submit" class="btn btn-secondary"><i class="fas fa-search"></i> Search</button>
            <a href="<?php echo BASE_URL; ?>modules/faculty/index.php" class="btn btn-outline">Clear</a>
        </form>
    </div>

    <div class="card">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Image</th>
                    <th>Employee ID</th>
                    <th>Name</th>
                    <th>Department</th>
                    <th>Position</th>
                    <th>Email</th>
                    <th>Phone</th>
                    <th>Hire Date</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($faculty_list)): ?>
                <tr>
                    <td colspan="10" class="text-center">
                        <p>No faculty found.</p>
                        <a href="<?php echo BASE_URL; ?>modules/faculty/add.php" class="btn btn-primary">
                            <i class="fas fa-plus"></i> Add First Faculty Member
                        </a>
                    </td>
                </tr>
                <?php else: ?>
                    <?php foreach ($faculty_list as $faculty): ?>
                    <tr>
                        <td style="text-align: center;">
                            <?php if (!empty($faculty['photo_path'])): ?>
                                <img src="<?php echo BASE_URL . $faculty['photo_path']; ?>" alt="Faculty Photo" 
                                     style="max-width: 60px; max-height: 60px; border: 1px solid #ddd; border-radius: 4px; padding: 5px; object-fit: cover;">
                            <?php else: ?>
                                <span style="color: #999; font-size: 12px;">No photo</span>
                            <?php endif; ?>
                        </td>
                        <td><strong><?php echo sanitizeOutput($faculty['employee_id']); ?></strong></td>
                        <td>
                            <?php 
                                $full_name = $faculty['first_name'];
                                if (!empty($faculty['middle_name'])) {
                                    $full_name .= ' ' . $faculty['middle_name'];
                                }
                                $full_name .= ' ' . $faculty['last_name'];
                                if (!empty($faculty['suffix'])) {
                                    $full_name .= ', ' . $faculty['suffix'];
                                }
                                echo sanitizeOutput($full_name);
                            ?>
                        </td>
                        <td><?php echo sanitizeOutput($faculty['department_name'] ?? 'N/A'); ?></td>
                        <td><?php echo sanitizeOutput($faculty['position'] ?? 'N/A'); ?></td>
                        <td><?php echo sanitizeOutput($faculty['email'] ?? 'N/A'); ?></td>
                        <td><?php echo sanitizeOutput($faculty['phone'] ?? 'N/A'); ?></td>
                        <td><?php echo $faculty['hire_date'] ? date('M d, Y', strtotime($faculty['hire_date'])) : 'N/A'; ?></td>
                        <td>
                            <span class="badge badge-<?php
                                echo $faculty['status'] === 'Active' ? 'success' :
                                    ($faculty['status'] === 'Retired' ? 'info' : 'warning');
                            ?>">
                                <?php echo sanitizeOutput($faculty['status']); ?>
                            </span>
                        </td>
                        <td class="actions">
                            <a href="<?php echo BASE_URL; ?>modules/faculty/view.php?id=<?php echo $faculty['faculty_id']; ?>"
                                class="btn btn-sm btn-info" title="View">
                                <i class="fas fa-eye"></i>
                            </a>
                            <a href="<?php echo BASE_URL; ?>modules/faculty/edit.php?id=<?php echo $faculty['faculty_id']; ?>"
                                class="btn btn-sm btn-warning" title="Edit">
                                <i class="fas fa-edit"></i>
                            </a>
                            <a href="<?php echo BASE_URL; ?>modules/faculty/delete.php?id=<?php echo $faculty['faculty_id']; ?>"
                                class="btn btn-sm btn-danger"
                                onclick="return confirm('Are you sure you want to delete this faculty member?');"
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

<?php include __DIR__ . '/../../includes/footer.php'; ?>
