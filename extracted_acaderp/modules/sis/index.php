<?php
/**
 * Student Information System (SIS) - Main Page
 * Lists all students with search and filter capabilities
 */

require_once __DIR__ . '/../../config/config.php';
requireAuth([ROLE_ADMIN, ROLE_FACULTY]);

$page_title = 'Student Information System';

$pdo = getDBConnection();
$students = [];
$search = sanitizeInput($_GET['search'] ?? '');
$program_filter = !empty($_GET['program_id']) ? (int)$_GET['program_id'] : null;

// Pagination
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$per_page = 10;
$offset = ($page - 1) * $per_page;

// Get all programs for the filter dropdown
$programs = [];
try {
    $stmt = $pdo->query("SELECT program_id, program_name FROM programs WHERE status = 'Active' ORDER BY program_name");
    $programs = $stmt->fetchAll();
} catch (PDOException $e) {
    // If status column doesn't exist, get all programs
    $stmt = $pdo->query("SELECT program_id, program_name FROM programs ORDER BY program_name");
    $programs = $stmt->fetchAll();
}

// Build base WHERE clause for both count and data queries
$whereClause = "WHERE 1=1";
$params = [];

if (!empty($search)) {
    $whereClause .= " AND (s.student_number LIKE ? OR s.first_name LIKE ? OR s.last_name LIKE ? OR u.email LIKE ? OR aa.email LIKE ?)";
    $searchParam = "%$search%";
    $params = array_merge($params, [$searchParam, $searchParam, $searchParam, $searchParam, $searchParam]);
}

if (!empty($program_filter)) {
    $whereClause .= " AND s.program_id = ?";
    $params[] = $program_filter;
}

// Get total count for pagination
$countQuery = "SELECT COUNT(DISTINCT s.student_id) as total
               FROM students s 
               LEFT JOIN programs p ON s.program_id = p.program_id 
               LEFT JOIN faculty f ON s.advisor_id = f.faculty_id 
               LEFT JOIN users u ON s.user_id = u.user_id
               LEFT JOIN admission_applications aa ON aa.student_number = s.student_number
               $whereClause";
$countStmt = $pdo->prepare($countQuery);
$countStmt->execute($params);
$total_students = $countStmt->fetch()['total'];
$total_pages = ceil($total_students / $per_page);

// Build query with filters and pagination
$query = "SELECT s.*, p.program_name, f.first_name as advisor_first, f.last_name as advisor_last,
          u.email as user_email, aa.email as application_email
          FROM students s 
          LEFT JOIN programs p ON s.program_id = p.program_id 
          LEFT JOIN faculty f ON s.advisor_id = f.faculty_id 
          LEFT JOIN users u ON s.user_id = u.user_id
          LEFT JOIN admission_applications aa ON aa.student_number = s.student_number
          $whereClause
          ORDER BY s.created_at DESC 
          LIMIT $per_page OFFSET $offset";

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$students = $stmt->fetchAll();

include __DIR__ . '/../../includes/header.php';
?>

<div class="page-container">
    <div class="page-header">
        <h1><i class="fas fa-user-graduate"></i> Student Information System</h1>
        <a href="<?php echo BASE_URL; ?>modules/sis/add.php" class="btn btn-primary">
            <i class="fas fa-plus"></i> Add New Student
        </a>
    </div>

    <div class="filters-card">
        <form method="GET" action="" class="filter-form" id="filterForm">
            <div class="form-row">
                <div class="form-group">
                    <label for="search">Search</label>
                    <input type="text" id="search" name="search" placeholder="Search by name, ID, or email..." 
                           value="<?php echo sanitizeOutput($search); ?>" autocomplete="off">
                </div>
                <div class="form-group">
                    <label for="program_id">Program</label>
                    <select id="program_id" name="program_id">
                        <option value="">All Programs</option>
                        <?php foreach ($programs as $program): ?>
                            <option value="<?php echo $program['program_id']; ?>" 
                                    <?php echo $program_filter == $program['program_id'] ? 'selected' : ''; ?>>
                                <?php echo sanitizeOutput($program['program_name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <a href="<?php echo BASE_URL; ?>modules/sis/index.php" class="btn btn-secondary">
                        <i class="fas fa-redo"></i> Reset
                    </a>
                </div>
            </div>
        </form>
    </div>

    <div class="table-card">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Student Number</th>
                    <th>Name</th>
                    <th>Program</th>
                    <th>Enrollment Date</th>
                    <th>GPA</th>
                    <th>Credits</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($students)): ?>
                <tr>
                    <td colspan="8" class="text-center">
                        <p>No students found.</p>
                        <a href="<?php echo BASE_URL; ?>modules/sis/add.php" class="btn btn-primary">
                            <i class="fas fa-plus"></i> Add First Student
                        </a>
                    </td>
                </tr>
                <?php else: ?>
                    <?php foreach ($students as $student): ?>
                    <tr>
                        <td><strong><?php echo sanitizeOutput($student['student_number']); ?></strong></td>
                        <td>
                            <?php echo sanitizeOutput($student['first_name'] . ' ' . ($student['middle_name'] ? $student['middle_name'] . ' ' : '') . $student['last_name']); ?>
                        </td>
                        <td><?php echo sanitizeOutput($student['program_name'] ?? 'N/A'); ?></td>
                        <td><?php echo date('M d, Y', strtotime($student['enrollment_date'])); ?></td>
                        <td><span class="badge badge-info"><?php echo number_format($student['gpa'], 2); ?></span></td>
                        <td><?php echo $student['total_credits']; ?></td>
                        <td>
                            <span class="badge badge-<?php 
                                echo $student['status'] === 'Active' ? 'success' : 
                                    ($student['status'] === 'Graduated' ? 'info' : 'warning'); 
                            ?>">
                                <?php echo sanitizeOutput($student['status']); ?>
                            </span>
                        </td>
                        <td class="actions">
                            <a href="<?php echo BASE_URL; ?>modules/sis/view.php?id=<?php echo $student['student_id']; ?>" 
                               class="btn btn-sm btn-info" title="View">
                                <i class="fas fa-eye"></i>
                            </a>
                            <a href="<?php echo BASE_URL; ?>modules/sis/edit.php?id=<?php echo $student['student_id']; ?>" 
                               class="btn btn-sm btn-warning" title="Edit">
                                <i class="fas fa-edit"></i>
                            </a>
                            <a href="<?php echo BASE_URL; ?>modules/sis/delete.php?id=<?php echo $student['student_id']; ?>" 
                               class="btn btn-sm btn-danger" 
                               onclick="return confirm('Are you sure you want to delete this student?');" 
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

    <?php if ($total_pages > 1): ?>
    <div class="pagination-container">
        <div class="pagination-info">
            Showing <?php echo $total_students > 0 ? $offset + 1 : 0; ?> to 
            <?php echo min($offset + $per_page, $total_students); ?> of 
            <?php echo $total_students; ?> students
        </div>
        <div class="pagination">
            <?php
            // Build query string for pagination links
            $query_params = [];
            if (!empty($search)) $query_params['search'] = $search;
            if (!empty($program_filter)) $query_params['program_id'] = $program_filter;
            
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
    const searchInput = document.getElementById('search');
    const programSelect = document.getElementById('program_id');
    const filterForm = document.getElementById('filterForm');
    let searchTimeout;

    // Live search with debouncing
    if (searchInput) {
        searchInput.addEventListener('input', function() {
            clearTimeout(searchTimeout);
            
            // Debounce: wait 500ms after user stops typing
            searchTimeout = setTimeout(function() {
                // Remove page parameter to reset to page 1 when searching
                const url = new URL(window.location.href);
                url.searchParams.delete('page');
                url.searchParams.set('search', searchInput.value);
                window.location.href = url.toString();
            }, 500);
        });

        // Show loading indicator while searching
        searchInput.addEventListener('input', function() {
            if (this.value.length > 0) {
                this.style.backgroundImage = 'url("data:image/svg+xml,%3Csvg xmlns=\'http://www.w3.org/2000/svg\' width=\'16\' height=\'16\' viewBox=\'0 0 24 24\' fill=\'none\' stroke=\'%23999\' stroke-width=\'2\'%3E%3Ccircle cx=\'11\' cy=\'11\' r=\'8\'/%3E%3Cpath d=\'m21 21-4.35-4.35\'/%3E%3C/svg%3E")';
                this.style.backgroundRepeat = 'no-repeat';
                this.style.backgroundPosition = 'right 0.75rem center';
                this.style.paddingRight = '2.5rem';
            } else {
                this.style.backgroundImage = '';
                this.style.paddingRight = '';
            }
        });
    }

    // Auto-submit on program filter change
    if (programSelect) {
        programSelect.addEventListener('change', function() {
            // Remove page parameter to reset to page 1 when filtering
            const url = new URL(window.location.href);
            url.searchParams.delete('page');
            if (this.value) {
                url.searchParams.set('program_id', this.value);
            } else {
                url.searchParams.delete('program_id');
            }
            window.location.href = url.toString();
        });
    }
})();
</script>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
