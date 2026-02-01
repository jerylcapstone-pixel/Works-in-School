<?php
/**
 * Online Examinations - List All Exams
 * Admin/Faculty view to manage all examinations
 */

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/feature_toggles.php';
requireAuth([ROLE_ADMIN, ROLE_FACULTY]);

$page_title = 'Online Examinations';
$pdo = getDBConnection();
$error = '';
$success = '';

// Check if examinations feature is enabled
if (!isExaminationsEnabled($pdo)) {
    header('Location: ' . BASE_URL . 'dashboard/index.php?error=The Examinations feature is currently disabled');
    exit;
}

// Check if examinations table exists
$table_exists = false;
try {
    $stmt = $pdo->query("SHOW TABLES LIKE 'examinations'");
    $table_exists = $stmt->rowCount() > 0;
} catch (PDOException $e) {
    $table_exists = false;
}

if (!$table_exists) {
    $error = 'The examinations table does not exist. Please run the migration: database/migration_online_examinations.sql';
}

// Get filter parameters
$search = isset($_GET['search']) ? sanitizeInput($_GET['search']) : '';
$exam_type_filter = isset($_GET['exam_type']) ? sanitizeInput($_GET['exam_type']) : '';
$status_filter = isset($_GET['status']) ? sanitizeInput($_GET['status']) : '';

// Build query with filters
$query = "SELECT e.*, c.course_name, cs.section_number, u.username as created_by_username,
          (SELECT COUNT(*) FROM exam_attempts ea WHERE ea.exam_id = e.exam_id) as total_attempts,
          (SELECT COUNT(*) FROM exam_questions eq WHERE eq.exam_id = e.exam_id) as total_questions
          FROM examinations e
          LEFT JOIN courses c ON e.course_id = c.course_id
          LEFT JOIN class_sections cs ON e.section_id = cs.section_id
          LEFT JOIN users u ON e.created_by = u.user_id
          WHERE 1=1";

// If faculty, only show their exams
if (hasRole(ROLE_FACULTY) && !hasRole(ROLE_ADMIN)) {
    $faculty_id = null;
    $stmt = $pdo->prepare("SELECT faculty_id FROM faculty WHERE user_id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $faculty = $stmt->fetch();
    if ($faculty) {
        $faculty_id = $faculty['faculty_id'];
        // Get sections taught by this faculty
        $stmt = $pdo->prepare("SELECT section_id FROM class_sections WHERE faculty_id = ?");
        $stmt->execute([$faculty_id]);
        $section_ids = array_column($stmt->fetchAll(), 'section_id');
        if (!empty($section_ids)) {
            $placeholders = implode(',', array_fill(0, count($section_ids), '?'));
            $query .= " AND (e.created_by = ? OR e.section_id IN ($placeholders))";
        } else {
            $query .= " AND e.created_by = ?";
        }
    } else {
        $query .= " AND e.created_by = ?";
    }
}

$params = [];
if (hasRole(ROLE_FACULTY) && !hasRole(ROLE_ADMIN)) {
    $params[] = $_SESSION['user_id'];
    if (!empty($section_ids)) {
        $params = array_merge($params, $section_ids);
    }
}

if (!empty($search)) {
    $query .= " AND (e.title LIKE ? OR e.description LIKE ? OR c.course_name LIKE ?)";
    $search_param = "%{$search}%";
    $params = array_merge($params, [$search_param, $search_param, $search_param]);
}

if (!empty($exam_type_filter)) {
    $query .= " AND e.exam_type = ?";
    $params[] = $exam_type_filter;
}

if ($status_filter === 'active') {
    $query .= " AND e.is_active = 1 AND e.start_date <= NOW() AND e.end_date >= NOW()";
} elseif ($status_filter === 'upcoming') {
    $query .= " AND e.is_active = 1 AND e.start_date > NOW()";
} elseif ($status_filter === 'past') {
    $query .= " AND e.end_date < NOW()";
} elseif ($status_filter === 'inactive') {
    $query .= " AND e.is_active = 0";
}

$query .= " ORDER BY e.start_date DESC, e.created_at DESC";

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$exams = $stmt->fetchAll();

include __DIR__ . '/../../includes/header.php';
?>

<div class="page-container">
    <div class="page-header">
        <h1><i class="fas fa-clipboard-list"></i> Online Examinations</h1>
        <?php if (hasRole(ROLE_ADMIN) || hasRole(ROLE_FACULTY)): ?>
        <a href="<?php echo BASE_URL; ?>modules/examinations/add.php" class="btn btn-primary">
            <i class="fas fa-plus"></i> Create New Exam
        </a>
        <?php endif; ?>
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
                       placeholder="Search exams..." 
                       value="<?php echo sanitizeOutput($search); ?>">
            </div>
            <div class="form-group" style="flex: 1; min-width: 150px;">
                <label for="exam_type">Exam Type</label>
                <select id="exam_type" name="exam_type" class="form-control">
                    <option value="">All Types</option>
                    <option value="Quiz" <?php echo $exam_type_filter === 'Quiz' ? 'selected' : ''; ?>>Quiz</option>
                    <option value="Midterm" <?php echo $exam_type_filter === 'Midterm' ? 'selected' : ''; ?>>Midterm</option>
                    <option value="Final" <?php echo $exam_type_filter === 'Final' ? 'selected' : ''; ?>>Final</option>
                    <option value="Assignment" <?php echo $exam_type_filter === 'Assignment' ? 'selected' : ''; ?>>Assignment</option>
                    <option value="Other" <?php echo $exam_type_filter === 'Other' ? 'selected' : ''; ?>>Other</option>
                </select>
            </div>
            <div class="form-group" style="flex: 1; min-width: 150px;">
                <label for="status">Status</label>
                <select id="status" name="status" class="form-control">
                    <option value="">All Status</option>
                    <option value="active" <?php echo $status_filter === 'active' ? 'selected' : ''; ?>>Active</option>
                    <option value="upcoming" <?php echo $status_filter === 'upcoming' ? 'selected' : ''; ?>>Upcoming</option>
                    <option value="past" <?php echo $status_filter === 'past' ? 'selected' : ''; ?>>Past</option>
                    <option value="inactive" <?php echo $status_filter === 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                </select>
            </div>
            <div class="form-group" style="flex: 0 0 auto;">
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-search"></i> Filter
                </button>
                <a href="<?php echo BASE_URL; ?>modules/examinations/index.php" class="btn btn-secondary">
                    <i class="fas fa-times"></i> Clear
                </a>
            </div>
        </form>
    </div>

    <div class="card">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem;">
            <h2 style="margin: 0;">Examinations (<?php echo count($exams); ?>)</h2>
        </div>
        <div style="overflow-x: auto;">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Title</th>
                        <th>Course/Section</th>
                        <th>Type</th>
                        <th>Questions</th>
                        <th>Points</th>
                        <th>Start Date</th>
                        <th>End Date</th>
                        <th>Attempts</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($exams)): ?>
                        <tr><td colspan="10" class="text-center">No examinations found.</td></tr>
                    <?php else: ?>
                        <?php foreach ($exams as $exam): 
                            $now = new DateTime();
                            $start_date = new DateTime($exam['start_date']);
                            $end_date = new DateTime($exam['end_date']);
                            
                            if (!$exam['is_active']) {
                                $status_badge = 'secondary';
                                $status_text = 'Inactive';
                            } elseif ($now < $start_date) {
                                $status_badge = 'info';
                                $status_text = 'Upcoming';
                            } elseif ($now > $end_date) {
                                $status_badge = 'warning';
                                $status_text = 'Past';
                            } else {
                                $status_badge = 'success';
                                $status_text = 'Active';
                            }
                        ?>
                        <tr>
                            <td><strong><?php echo sanitizeOutput($exam['title']); ?></strong></td>
                            <td>
                                <?php if ($exam['course_name']): ?>
                                    <?php echo sanitizeOutput($exam['course_name']); ?>
                                    <?php if ($exam['section_number']): ?>
                                        <br><small class="text-muted">Section <?php echo sanitizeOutput($exam['section_number']); ?></small>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <span class="text-muted">N/A</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <span class="badge badge-info"><?php echo sanitizeOutput($exam['exam_type']); ?></span>
                            </td>
                            <td><?php echo (int)$exam['total_questions']; ?></td>
                            <td><?php echo number_format($exam['total_points'], 2); ?></td>
                            <td><?php echo date('M d, Y g:i A', strtotime($exam['start_date'])); ?></td>
                            <td><?php echo date('M d, Y g:i A', strtotime($exam['end_date'])); ?></td>
                            <td><?php echo (int)$exam['total_attempts']; ?></td>
                            <td>
                                <span class="badge badge-<?php echo $status_badge; ?>"><?php echo $status_text; ?></span>
                            </td>
                            <td class="actions">
                                <a href="<?php echo BASE_URL; ?>modules/examinations/view.php?id=<?php echo $exam['exam_id']; ?>" 
                                   class="btn btn-sm btn-info" title="View">
                                    <i class="fas fa-eye"></i>
                                </a>
                                <a href="<?php echo BASE_URL; ?>modules/examinations/edit.php?id=<?php echo $exam['exam_id']; ?>" 
                                   class="btn btn-sm btn-warning" title="Edit">
                                    <i class="fas fa-edit"></i>
                                </a>
                                <a href="<?php echo BASE_URL; ?>modules/examinations/results.php?id=<?php echo $exam['exam_id']; ?>" 
                                   class="btn btn-sm btn-primary" title="Results">
                                    <i class="fas fa-chart-bar"></i>
                                </a>
                                <a href="<?php echo BASE_URL; ?>modules/examinations/delete.php?id=<?php echo $exam['exam_id']; ?>" 
                                   class="btn btn-sm btn-danger" 
                                   onclick="return confirm('Are you sure you want to delete this exam? This will also delete all attempts and answers.');"
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

