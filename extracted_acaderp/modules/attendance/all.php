<?php
/**
 * Attendance - View All Attendance Records
 */
require_once __DIR__ . '/../../config/config.php';
requireAuth([ROLE_ADMIN, ROLE_FACULTY]);
$page_title = 'All Attendance Records';
$pdo = getDBConnection();

$faculty_id = null;
if (hasRole(ROLE_FACULTY) && !hasRole(ROLE_ADMIN)) {
    $stmt = $pdo->prepare("SELECT faculty_id FROM faculty WHERE user_id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $faculty = $stmt->fetch();
    $faculty_id = $faculty['faculty_id'] ?? null;
}

// Get filters
$section_filter = !empty($_GET['section_id']) ? (int)$_GET['section_id'] : null;
$date_filter = !empty($_GET['date']) ? $_GET['date'] : null;

// Check if major_id column exists
$major_column_exists = false;
try {
    $check_stmt = $pdo->query("SHOW COLUMNS FROM courses LIKE 'major_id'");
    $major_column_exists = $check_stmt->rowCount() > 0;
} catch (PDOException $e) {
    $major_column_exists = false;
}

// Get sections for filter dropdown
$sections = [];
if ($faculty_id) {
    $stmt = $pdo->prepare("SELECT cs.section_id, c.course_name, cs.section_number, cs.semester, cs.academic_year,
                          d.department_name, p.program_name, " . ($major_column_exists ? "m.major_name," : "") . "
                          cs.faculty_id
                          FROM class_sections cs
                          LEFT JOIN courses c ON cs.course_id = c.course_id
                          LEFT JOIN departments d ON c.department_id = d.department_id
                          LEFT JOIN program_courses pc ON c.course_id = pc.course_id
                          LEFT JOIN programs p ON pc.program_id = p.program_id
                          " . ($major_column_exists ? "LEFT JOIN majors m ON c.major_id = m.major_id" : "") . "
                          WHERE cs.faculty_id = ?
                          ORDER BY d.department_name, p.program_name, " . ($major_column_exists ? "m.major_name, " : "") . "c.course_name, cs.academic_year DESC");
    $stmt->execute([$faculty_id]);
    $sections = $stmt->fetchAll();
} else {
    $stmt = $pdo->query("SELECT cs.section_id, c.course_name, cs.section_number, cs.semester, cs.academic_year,
                         d.department_name, p.program_name, " . ($major_column_exists ? "m.major_name," : "") . "
                         cs.faculty_id
                         FROM class_sections cs
                         LEFT JOIN courses c ON cs.course_id = c.course_id
                         LEFT JOIN departments d ON c.department_id = d.department_id
                         LEFT JOIN program_courses pc ON c.course_id = pc.course_id
                         LEFT JOIN programs p ON pc.program_id = p.program_id
                         " . ($major_column_exists ? "LEFT JOIN majors m ON c.major_id = m.major_id" : "") . "
                         ORDER BY d.department_name, p.program_name, " . ($major_column_exists ? "m.major_name, " : "") . "c.course_name, cs.academic_year DESC");
    $sections = $stmt->fetchAll();
}

// Build query for attendance records
$query = "SELECT a.*, e.enrollment_id, e.student_id, e.section_id,
          s.student_number, s.first_name, s.last_name,
          cs.section_number, cs.semester, cs.academic_year,
          c.course_name, c.course_code,
          u.username as marked_by_name
          FROM attendance a
          INNER JOIN enrollments e ON a.enrollment_id = e.enrollment_id
          INNER JOIN students s ON e.student_id = s.student_id
          INNER JOIN class_sections cs ON e.section_id = cs.section_id
          INNER JOIN courses c ON cs.course_id = c.course_id
          LEFT JOIN users u ON a.marked_by = u.user_id
          WHERE 1=1";

$params = [];

// Filter by faculty if needed
if ($faculty_id) {
    $query .= " AND cs.faculty_id = ?";
    $params[] = $faculty_id;
    // Note: For attendance, faculty can see ALL students in their sections, not just advisees
}

// Filter by section
if ($section_filter) {
    $query .= " AND e.section_id = ?";
    $params[] = $section_filter;
}

// Filter by date
if ($date_filter) {
    $query .= " AND a.attendance_date = ?";
    $params[] = $date_filter;
}

$query .= " ORDER BY a.attendance_date DESC, c.course_name, s.last_name, s.first_name";

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$attendance_records = $stmt->fetchAll();

include __DIR__ . '/../../includes/header.php';
?>

<div class="page-container">
    <div class="page-header">
        <h1><i class="fas fa-list"></i> All Attendance Records</h1>
        <a href="<?php echo BASE_URL; ?>modules/attendance/index.php" class="btn btn-secondary">
            <i class="fas fa-arrow-left"></i> Back to Attendance
        </a>
    </div>

    <div class="card" style="margin-bottom: 1.5rem;">
        <h2>Filters</h2>
        <form method="GET" action="" style="display: flex; gap: 1rem; flex-wrap: wrap; align-items: end;">
            <div class="form-group" style="flex: 1; min-width: 200px;">
                <label>Section</label>
                <select name="section_id" class="form-control">
                    <option value="">All Sections</option>
                    <?php 
                    $current_dept = '';
                    $current_prog = '';
                    $current_major = '';
                    foreach ($sections as $sec): 
                        $dept_name = $sec['department_name'] ?? 'General/Minor';
                        $prog_name = $sec['program_name'] ?? 'General';
                        $major_name = ($major_column_exists && !empty($sec['major_name'])) ? $sec['major_name'] : '';
                        
                        if ($dept_name !== $current_dept) {
                            if ($current_dept !== '') echo '</optgroup>';
                            echo '<optgroup label="' . sanitizeOutput($dept_name) . '">';
                            $current_dept = $dept_name;
                            $current_prog = '';
                            $current_major = '';
                        }
                        if ($prog_name !== $current_prog && $prog_name !== 'General') {
                            if ($current_prog !== '' && $current_prog !== 'General') echo '</optgroup>';
                            echo '<optgroup label="&nbsp;&nbsp;' . sanitizeOutput($prog_name) . '">';
                            $current_prog = $prog_name;
                            $current_major = '';
                        }
                        if ($major_name && $major_name !== $current_major) {
                            if ($current_major !== '') echo '</optgroup>';
                            echo '<optgroup label="&nbsp;&nbsp;&nbsp;&nbsp;Major: ' . sanitizeOutput($major_name) . '">';
                            $current_major = $major_name;
                        }
                    ?>
                        <option value="<?php echo $sec['section_id']; ?>" <?php echo $section_filter == $sec['section_id'] ? 'selected' : ''; ?>>
                            <?php echo sanitizeOutput($sec['course_name'] . ' - Section ' . $sec['section_number'] . ' (' . $sec['semester'] . ' ' . $sec['academic_year'] . ')'); ?>
                        </option>
                    <?php endforeach; ?>
                    <?php if ($current_major !== '') echo '</optgroup>'; ?>
                    <?php if ($current_prog !== '' && $current_prog !== 'General') echo '</optgroup>'; ?>
                    <?php if ($current_dept !== '') echo '</optgroup>'; ?>
                </select>
            </div>
            <div class="form-group" style="flex: 1; min-width: 150px;">
                <label>Date</label>
                <input type="date" name="date" value="<?php echo sanitizeOutput($date_filter ?? ''); ?>" class="form-control">
            </div>
            <div class="form-group">
                <button type="submit" class="btn btn-secondary"><i class="fas fa-search"></i> Filter</button>
                <a href="?" class="btn btn-outline">Clear</a>
            </div>
        </form>
    </div>

    <div class="card">
        <h2>Attendance Records (<?php echo count($attendance_records); ?>)</h2>
        
        <?php if (empty($attendance_records)): ?>
            <div class="alert alert-info">No attendance records found.</div>
        <?php else: ?>
            <div style="overflow-x: auto;">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Course</th>
                            <th>Section</th>
                            <th>Student Number</th>
                            <th>Student Name</th>
                            <th>Status</th>
                            <th>Marked By</th>
                            <th>Marked At</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($attendance_records as $record): ?>
                        <tr>
                            <td><?php echo date('M d, Y', strtotime($record['attendance_date'])); ?></td>
                            <td>
                                <strong><?php echo sanitizeOutput($record['course_name']); ?></strong>
                                <?php if ($record['course_code']): ?>
                                    <br><small class="text-muted"><?php echo sanitizeOutput($record['course_code']); ?></small>
                                <?php endif; ?>
                            </td>
                            <td><?php echo sanitizeOutput($record['section_number']); ?><br>
                                <small class="text-muted"><?php echo sanitizeOutput($record['semester'] . ' ' . $record['academic_year']); ?></small>
                            </td>
                            <td><strong><?php echo sanitizeOutput($record['student_number']); ?></strong></td>
                            <td><?php echo sanitizeOutput($record['first_name'] . ' ' . $record['last_name']); ?></td>
                            <td>
                                <span class="badge badge-<?php 
                                    echo $record['status'] === 'Present' ? 'success' : 
                                        ($record['status'] === 'Late' ? 'warning' : 
                                        ($record['status'] === 'Excused' ? 'info' : 'error')); 
                                ?>">
                                    <?php echo sanitizeOutput($record['status']); ?>
                                </span>
                            </td>
                            <td><?php echo sanitizeOutput($record['marked_by_name'] ?? 'N/A'); ?></td>
                            <td><?php echo date('M d, Y g:i A', strtotime($record['marked_at'])); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php include __DIR__ . '/../../includes/footer.php'; ?>

