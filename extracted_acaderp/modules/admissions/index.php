<?php
/**
 * Online Admissions & Enrollment - Main Page
 * Lists all admission applications with filtering
 */

require_once __DIR__ . '/../../config/config.php';
requireAuth(ROLE_ADMIN);

$page_title = 'Admissions & Enrollment';

$pdo = getDBConnection();
$applications = [];
$search = sanitizeInput($_GET['search'] ?? '');
$status_filter = sanitizeInput($_GET['status'] ?? '');

// Pagination
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$per_page = 10;
$offset = ($page - 1) * $per_page;

// Build base WHERE clause for both count and data queries
$whereClause = "WHERE 1=1";
$params = [];

if (!empty($search)) {
    $whereClause .= " AND (a.first_name LIKE ? OR a.last_name LIKE ? OR a.email LIKE ? OR a.student_number LIKE ?)";
    $searchParam = "%$search%";
    $params = array_merge($params, [$searchParam, $searchParam, $searchParam, $searchParam]);
}

if (!empty($status_filter)) {
    $whereClause .= " AND a.status = ?";
    $params[] = $status_filter;
}

// Get total count for pagination
$countQuery = "SELECT COUNT(*) as total
               FROM admission_applications a
               LEFT JOIN programs p ON a.program_id = p.program_id
               LEFT JOIN users u ON a.reviewed_by = u.user_id
               $whereClause";
$countStmt = $pdo->prepare($countQuery);
$countStmt->execute($params);
$total_applications = $countStmt->fetch()['total'];
$total_pages = ceil($total_applications / $per_page);

// Build query with filters and pagination
$query = "SELECT a.*, p.program_name,
          u.username as reviewed_by_name
          FROM admission_applications a
          LEFT JOIN programs p ON a.program_id = p.program_id
          LEFT JOIN users u ON a.reviewed_by = u.user_id
          $whereClause
          ORDER BY a.created_at DESC
          LIMIT $per_page OFFSET $offset";

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$applications = $stmt->fetchAll();

include __DIR__ . '/../../includes/header.php';
?>

<div class="page-container">
    <div class="page-header">
        <h1><i class="fas fa-file-alt"></i> Admissions & Enrollment</h1>
        <a href="<?php echo BASE_URL; ?>modules/admissions/add.php" class="btn btn-primary">
            <i class="fas fa-plus"></i> New Application
        </a>
    </div>

    <div class="filters-card">
        <form method="GET" action="" class="filters-form">
            <div class="form-group">
                <input type="text" name="search" placeholder="Search by name, email, or student number..." 
                       value="<?php echo sanitizeOutput($search); ?>" class="form-control">
            </div>
            <div class="form-group">
                <select name="status" class="form-control">
                    <option value="">All Statuses</option>
                    <option value="Pending" <?php echo $status_filter === 'Pending' ? 'selected' : ''; ?>>Pending</option>
                    <option value="Approved" <?php echo $status_filter === 'Approved' ? 'selected' : ''; ?>>Approved</option>
                    <option value="Rejected" <?php echo $status_filter === 'Rejected' ? 'selected' : ''; ?>>Rejected</option>
                </select>
            </div>
            <button type="submit" class="btn btn-secondary"><i class="fas fa-search"></i> Search</button>
            <a href="<?php echo BASE_URL; ?>modules/admissions/index.php" class="btn btn-outline">Clear</a>
        </form>
    </div>

    <div class="card">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Student Number</th>
                    <th>Name</th>
                    <th>Email</th>
                    <th>Program</th>
                    <th>Application Date</th>
                    <th>Status</th>
                    <th>Reviewed By</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($applications)): ?>
                <tr>
                    <td colspan="8" class="text-center">
                        <p>No applications found.</p>
                        <a href="<?php echo BASE_URL; ?>modules/admissions/add.php" class="btn btn-primary">
                            <i class="fas fa-plus"></i> Add First Application
                        </a>
                    </td>
                </tr>
                <?php else: ?>
                    <?php foreach ($applications as $app): ?>
                    <tr>
                        <td><strong><?php echo sanitizeOutput($app['student_number'] ?? 'N/A'); ?></strong></td>
                        <td><?php echo sanitizeOutput($app['first_name'] . ' ' . ($app['middle_name'] ? $app['middle_name'] . ' ' : '') . $app['last_name']); ?></td>
                        <td><?php echo sanitizeOutput($app['email']); ?></td>
                        <td><?php echo sanitizeOutput($app['program_name'] ?? 'N/A'); ?></td>
                        <td><?php echo date('M d, Y', strtotime($app['application_date'])); ?></td>
                        <td>
                            <span class="badge badge-<?php
                                echo $app['status'] === 'Approved' ? 'success' :
                                    ($app['status'] === 'Rejected' ? 'danger' :
                                    ($app['status'] === 'Pending' ? 'warning' : 'info'));
                            ?>">
                                <?php echo sanitizeOutput($app['status']); ?>
                            </span>
                        </td>
                        <td><?php echo sanitizeOutput($app['reviewed_by_name'] ?? 'N/A'); ?></td>
                        <td class="actions">
                            <a href="<?php echo BASE_URL; ?>modules/admissions/view.php?id=<?php echo $app['application_id']; ?>"
                                class="btn btn-sm btn-info" title="View">
                                <i class="fas fa-eye"></i>
                            </a>
                            <?php if ($app['status'] !== 'Approved' && $app['status'] !== 'Rejected'): ?>
                            <a href="<?php echo BASE_URL; ?>modules/admissions/edit.php?id=<?php echo $app['application_id']; ?>"
                                class="btn btn-sm btn-warning" title="Edit">
                                <i class="fas fa-edit"></i>
                            </a>
                            <?php else: ?>
                            <span class="btn btn-sm btn-secondary" title="Cannot edit <?php echo strtolower($app['status']); ?> applications" style="cursor: not-allowed; opacity: 0.6;">
                                <i class="fas fa-lock"></i>
                            </span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <?php if ($total_pages > 1): ?>
    <div class="pagination-container">
        <div class="pagination-info">
            Showing <?php echo $total_applications > 0 ? $offset + 1 : 0; ?> to 
            <?php echo min($offset + $per_page, $total_applications); ?> of 
            <?php echo $total_applications; ?> applications
        </div>
        <div class="pagination">
            <?php
            // Build query string for pagination links
            $query_params = [];
            if (!empty($search)) $query_params['search'] = $search;
            if (!empty($status_filter)) $query_params['status'] = $status_filter;
            
            // Previous button
            if ($page > 1):
                $prev_params = array_merge($query_params, ['page' => $page - 1]);
            ?>
                <a href="?<?php echo http_build_query($prev_params); ?>" class="btn btn-sm btn-secondary">
                    <i class="fas fa-chevron-left"></i> Previous
                </a>
            <?php endif; ?>

            <?php
            // Page numbers
            $range = 2; // Number of pages to show on each side of current page
            $start = max(1, $page - $range);
            $end = min($total_pages, $page + $range);
            
            // First page
            if ($start > 1):
                $first_params = array_merge($query_params, ['page' => 1]);
            ?>
                <a href="?<?php echo http_build_query($first_params); ?>" class="btn btn-sm btn-secondary">1</a>
                <?php if ($start > 2): ?>
                    <span class="pagination-ellipsis">...</span>
                <?php endif; ?>
            <?php endif; ?>

            <?php for ($i = $start; $i <= $end; $i++): 
                $page_params = array_merge($query_params, ['page' => $i]);
            ?>
                <a href="?<?php echo http_build_query($page_params); ?>" 
                   class="btn btn-sm <?php echo $i == $page ? 'btn-primary' : 'btn-secondary'; ?>">
                    <?php echo $i; ?>
                </a>
            <?php endfor; ?>

            <?php
            // Last page
            if ($end < $total_pages):
                if ($end < $total_pages - 1): ?>
                    <span class="pagination-ellipsis">...</span>
                <?php endif;
                $last_params = array_merge($query_params, ['page' => $total_pages]);
            ?>
                <a href="?<?php echo http_build_query($last_params); ?>" class="btn btn-sm btn-secondary">
                    <?php echo $total_pages; ?>
                </a>
            <?php endif; ?>

            <?php
            // Next button
            if ($page < $total_pages):
                $next_params = array_merge($query_params, ['page' => $page + 1]);
            ?>
                <a href="?<?php echo http_build_query($next_params); ?>" class="btn btn-sm btn-secondary">
                    Next <i class="fas fa-chevron-right"></i>
                </a>
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>
</div>

<style>
.pagination-container {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 1.5rem;
    background: white;
    border-radius: 8px;
    margin-top: 1rem;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
}

.pagination-info {
    color: #666;
    font-size: 0.9rem;
}

.pagination {
    display: flex;
    gap: 0.5rem;
    align-items: center;
}

.pagination-ellipsis {
    padding: 0 0.5rem;
    color: #999;
}

.pagination .btn {
    min-width: 40px;
    justify-content: center;
}

.pagination .btn-primary {
    pointer-events: none;
    font-weight: 600;
}

@media (max-width: 768px) {
    .pagination-container {
        flex-direction: column;
        gap: 1rem;
    }
}
</style>

<script>
(function() {
    const searchInput = document.querySelector('input[name="search"]');
    const statusSelect = document.querySelector('select[name="status"]');
    
    // Reset to page 1 when filters change
    if (searchInput) {
        const form = searchInput.closest('form');
        form.addEventListener('submit', function(e) {
            // Remove page parameter to reset to page 1
            const url = new URL(window.location.href);
            url.searchParams.delete('page');
            
            // Add current form values
            if (searchInput.value) {
                url.searchParams.set('search', searchInput.value);
            } else {
                url.searchParams.delete('search');
            }
            
            if (statusSelect && statusSelect.value) {
                url.searchParams.set('status', statusSelect.value);
            } else {
                url.searchParams.delete('status');
            }
            
            e.preventDefault();
            window.location.href = url.toString();
        });
    }
})();
</script>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
