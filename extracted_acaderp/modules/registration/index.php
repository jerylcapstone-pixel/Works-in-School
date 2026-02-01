<?php
/**
 * Course Registration (Admin-Led) - Main Page
 */
require_once __DIR__ . '/../../config/config.php';
requireAuth(ROLE_ADMIN);
$page_title = 'Course Registration';
$pdo = getDBConnection();
$tab = sanitizeInput($_GET['tab'] ?? 'sections');

$sections = [];
$enrollments = [];

// Check if major_id column exists in courses table
$major_column_exists = false;
try {
    $check_stmt = $pdo->query("SHOW COLUMNS FROM courses LIKE 'major_id'");
    $major_column_exists = $check_stmt->rowCount() > 0;
} catch (PDOException $e) {
    $major_column_exists = false;
}

$department_filter = !empty($_GET['department_id']) ? (int)$_GET['department_id'] : null;
$program_filter = !empty($_GET['program_id']) ? (int)$_GET['program_id'] : null;
$major_filter = !empty($_GET['major_id']) ? (int)$_GET['major_id'] : null;

if ($tab === 'sections') {
    $query = "SELECT cs.*, c.course_code, c.course_name, c.credits, c.department_id as course_department_id,
              " . ($major_column_exists ? "c.major_id as course_major_id," : "") . "
              f.first_name as faculty_first, f.last_name as faculty_last,
              r.room_number,
              d.department_name, d.department_code,
              " . ($major_column_exists ? "m.major_name," : "") . "
              p.program_name, p.program_id
              FROM class_sections cs
              LEFT JOIN courses c ON cs.course_id = c.course_id
              LEFT JOIN faculty f ON cs.faculty_id = f.faculty_id
              LEFT JOIN rooms r ON cs.room_id = r.room_id
              LEFT JOIN departments d ON c.department_id = d.department_id
              " . ($major_column_exists ? "LEFT JOIN majors m ON c.major_id = m.major_id" : "") . "
              LEFT JOIN program_courses pc ON c.course_id = pc.course_id
              LEFT JOIN programs p ON pc.program_id = p.program_id
              WHERE 1=1";
    
    $params = [];
    
    if ($department_filter) {
        $query .= " AND (c.department_id = ? OR d.department_id = ?)";
        $params[] = $department_filter;
        $params[] = $department_filter;
    }
    if ($program_filter) {
        $query .= " AND p.program_id = ?";
        $params[] = $program_filter;
    }
    if ($major_filter && $major_column_exists) {
        $query .= " AND c.major_id = ?";
        $params[] = $major_filter;
    }
    
    $query .= " ORDER BY d.department_name, p.program_name, " . ($major_column_exists ? "m.major_name, " : "") . "c.course_name, cs.academic_year DESC, cs.semester";
    
    if (!empty($params)) {
        $stmt = $pdo->prepare($query);
        $stmt->execute($params);
        $sections = $stmt->fetchAll();
    } else {
        $sections = $pdo->query($query)->fetchAll();
    }
} else {
    $query = "SELECT e.*, s.student_number, s.first_name, s.last_name, s.program_id as student_program_id,
              c.course_code, c.course_name, cs.section_number,
              d.department_name, p.program_name" . ($major_column_exists ? ", m.major_name" : "") . "
              FROM enrollments e
              LEFT JOIN students s ON e.student_id = s.student_id
              LEFT JOIN class_sections cs ON e.section_id = cs.section_id
              LEFT JOIN courses c ON cs.course_id = c.course_id
              LEFT JOIN departments d ON c.department_id = d.department_id
              LEFT JOIN programs p ON s.program_id = p.program_id
              " . ($major_column_exists ? "LEFT JOIN majors m ON c.major_id = m.major_id" : "") . "
              WHERE 1=1";
    
    $params = [];
    
    if ($department_filter) {
        $query .= " AND (c.department_id = ? OR d.department_id = ?)";
        $params[] = $department_filter;
        $params[] = $department_filter;
    }
    if ($program_filter) {
        $query .= " AND s.program_id = ?";
        $params[] = $program_filter;
    }
    if ($major_filter && $major_column_exists) {
        $query .= " AND c.major_id = ?";
        $params[] = $major_filter;
    }
    
    $query .= " ORDER BY d.department_name, p.program_name, " . ($major_column_exists ? "m.major_name, " : "") . "s.last_name, s.first_name LIMIT 100";
    
    if (!empty($params)) {
        $stmt = $pdo->prepare($query);
        $stmt->execute($params);
        $enrollments = $stmt->fetchAll();
    } else {
        $enrollments = $pdo->query($query)->fetchAll();
    }
}

// Get departments, programs, and majors for filters
$departments = [];
$stmt = $pdo->query("SELECT department_id, department_name FROM departments ORDER BY department_name");
$departments = $stmt->fetchAll();

$programs = [];
if ($department_filter) {
    $stmt = $pdo->prepare("SELECT program_id, program_name FROM programs WHERE department_id = ? AND status = 'Active' ORDER BY program_name");
    $stmt->execute([$department_filter]);
    $programs = $stmt->fetchAll();
} else {
    $stmt = $pdo->query("SELECT program_id, program_name FROM programs WHERE status = 'Active' ORDER BY program_name");
    $programs = $stmt->fetchAll();
}

$majors = [];
if ($major_column_exists) {
    try {
        if ($program_filter) {
            $stmt = $pdo->prepare("SELECT major_id, major_name FROM majors WHERE program_id = ? AND status = 'Active' ORDER BY major_name");
            $stmt->execute([$program_filter]);
            $majors = $stmt->fetchAll();
        } else {
            $stmt = $pdo->query("SELECT major_id, major_name FROM majors WHERE status = 'Active' ORDER BY major_name");
            $majors = $stmt->fetchAll();
        }
    } catch (PDOException $e) {
        $majors = [];
    }
}

include __DIR__ . '/../../includes/header.php';
?>

<div class="page-container">
    <div class="page-header">
        <h1><i class="fas fa-calendar-alt"></i> Course Registration</h1>
        <?php if (hasRole(ROLE_ADMIN)): ?>
            <a href="<?php echo BASE_URL; ?>modules/registration/section_add.php" class="btn btn-primary">
                <i class="fas fa-plus"></i> Add Section
            </a>
        <?php endif; ?>
    </div>

    <div class="card">
        <div class="tabs">
            <a href="?tab=sections" class="tab <?php echo $tab === 'sections' ? 'active' : ''; ?>">
                <i class="fas fa-chalkboard"></i> Class Sections
            </a>
            <a href="?tab=enrollments" class="tab <?php echo $tab === 'enrollments' ? 'active' : ''; ?>">
                <i class="fas fa-user-check"></i> Enrollments
            </a>
        </div>

        <div class="tab-content">
            <?php if ($tab === 'sections'): ?>
                <div style="padding: 1.5rem;">
                    <!-- Filters -->
                    <form method="GET" action="" style="margin-bottom: 1.5rem; display: flex; gap: 1rem; flex-wrap: wrap; align-items: end;">
                        <input type="hidden" name="tab" value="sections">
                        <div class="form-group" style="flex: 1; min-width: 200px;">
                            <label>Department</label>
                            <select name="department_id" class="form-control" onchange="this.form.submit()">
                                <option value="">All Departments</option>
                                <?php foreach ($departments as $dept): ?>
                                    <option value="<?php echo $dept['department_id']; ?>" <?php echo $department_filter == $dept['department_id'] ? 'selected' : ''; ?>>
                                        <?php echo sanitizeOutput($dept['department_name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group" style="flex: 1; min-width: 200px;">
                            <label>Program</label>
                            <select name="program_id" class="form-control" onchange="this.form.submit()">
                                <option value="">All Programs</option>
                                <?php foreach ($programs as $prog): ?>
                                    <option value="<?php echo $prog['program_id']; ?>" <?php echo $program_filter == $prog['program_id'] ? 'selected' : ''; ?>>
                                        <?php echo sanitizeOutput($prog['program_name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <?php if ($major_column_exists && !empty($majors)): ?>
                        <div class="form-group" style="flex: 1; min-width: 200px;">
                            <label>Major</label>
                            <select name="major_id" class="form-control" onchange="this.form.submit()">
                                <option value="">All Majors</option>
                                <?php foreach ($majors as $major): ?>
                                    <option value="<?php echo $major['major_id']; ?>" <?php echo $major_filter == $major['major_id'] ? 'selected' : ''; ?>>
                                        <?php echo sanitizeOutput($major['major_name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <?php endif; ?>
                        <div class="form-group">
                            <a href="?tab=sections" class="btn btn-secondary">Clear Filters</a>
                        </div>
                    </form>

                    <?php
                    // Organize sections by department/program/major
                    $organized_sections = [];
                    foreach ($sections as $sec) {
                        $dept_key = $sec['department_name'] ?? 'General/Minor Courses';
                        $prog_key = $sec['program_name'] ?? 'General';
                        $major_key = ($major_column_exists && !empty($sec['major_name'])) ? $sec['major_name'] : 'General';
                        
                        if (!isset($organized_sections[$dept_key])) {
                            $organized_sections[$dept_key] = [];
                        }
                        if (!isset($organized_sections[$dept_key][$prog_key])) {
                            $organized_sections[$dept_key][$prog_key] = [];
                        }
                        if (!isset($organized_sections[$dept_key][$prog_key][$major_key])) {
                            $organized_sections[$dept_key][$prog_key][$major_key] = [];
                        }
                        $organized_sections[$dept_key][$prog_key][$major_key][] = $sec;
                    }
                    ?>

                    <?php if (empty($organized_sections)): ?>
                        <div class="alert alert-info">No sections found.</div>
                    <?php else: ?>
                        <?php foreach ($organized_sections as $dept_name => $programs_group): ?>
                            <div style="margin-bottom: 2rem;">
                                <h3 style="margin-bottom: 1rem; padding-bottom: 0.5rem; border-bottom: 2px solid var(--primary-color); color: var(--primary-color);">
                                    <i class="fas fa-building"></i> <?php echo sanitizeOutput($dept_name); ?>
                                </h3>
                                <?php foreach ($programs_group as $prog_name => $majors_group): ?>
                                    <div style="margin-left: 1.5rem; margin-bottom: 1.5rem;">
                                        <h4 style="margin-bottom: 0.75rem; color: var(--text-color);">
                                            <i class="fas fa-graduation-cap"></i> <?php echo sanitizeOutput($prog_name); ?>
                                        </h4>
                                        <?php foreach ($majors_group as $major_name => $section_list): ?>
                                            <?php if ($major_name !== 'General'): ?>
                                            <div style="margin-left: 1.5rem; margin-bottom: 1rem;">
                                                <h5 style="margin-bottom: 0.5rem; color: var(--text-color-secondary); font-weight: 600;">
                                                    <i class="fas fa-bookmark"></i> Major: <?php echo sanitizeOutput($major_name); ?>
                                                </h5>
                                            <?php endif; ?>
                                            <table class="data-table" style="margin-bottom: 1rem;">
                                                <thead>
                                                    <tr>
                                                        <th>Course</th>
                                                        <th>Section</th>
                                                        <th>Semester</th>
                                                        <th>Year</th>
                                                        <th>Room</th>
                                                        <th>Enrollment</th>
                                                        <th>Status</th>
                                                        <th>Actions</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <?php foreach ($section_list as $sec): ?>
                                                    <tr>
                                                        <td><strong><?php echo sanitizeOutput($sec['course_name']); ?></strong></td>
                                                        <td><?php echo sanitizeOutput($sec['section_number']); ?></td>
                                                        <td><?php echo sanitizeOutput($sec['semester']); ?></td>
                                                        <td><?php echo sanitizeOutput($sec['academic_year']); ?></td>
                                                        <td><?php echo sanitizeOutput($sec['room_number'] ?? 'TBA'); ?></td>
                                                        <td><?php echo $sec['current_enrollment'] . '/' . $sec['max_enrollment']; ?></td>
                                                        <td><span class="badge badge-<?php echo $sec['status'] === 'Open' ? 'success' : 'warning'; ?>"><?php echo sanitizeOutput($sec['status']); ?></span></td>
                                                        <td class="actions">
                                                            <a href="<?php echo BASE_URL; ?>modules/registration/enroll.php?section_id=<?php echo $sec['section_id']; ?>" class="btn btn-sm btn-primary" title="Enroll Students">
                                                                <i class="fas fa-user-plus"></i>
                                                            </a>
                                                            <?php if (hasRole(ROLE_ADMIN)): ?>
                                                                <a href="<?php echo BASE_URL; ?>modules/registration/section_edit.php?id=<?php echo $sec['section_id']; ?>" class="btn btn-sm btn-info" title="Edit Section">
                                                                    <i class="fas fa-edit"></i>
                                                                </a>
                                                                <a href="<?php echo BASE_URL; ?>modules/registration/section_delete.php?id=<?php echo $sec['section_id']; ?>" class="btn btn-sm btn-danger" title="Delete Section" onclick="return confirm('Are you sure you want to delete this section?');">
                                                                    <i class="fas fa-trash"></i>
                                                                </a>
                                                            <?php endif; ?>
                                                        </td>
                                                    </tr>
                                                    <?php endforeach; ?>
                                                </tbody>
                                            </table>
                                            <?php if ($major_name !== 'General'): ?>
                                            </div>
                                            <?php endif; ?>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            <?php else: ?>
                <div style="padding: 1.5rem;">
                    <!-- Filters for Enrollments -->
                    <form method="GET" action="" style="margin-bottom: 1.5rem; display: flex; gap: 1rem; flex-wrap: wrap; align-items: end;">
                        <input type="hidden" name="tab" value="enrollments">
                        <div class="form-group" style="flex: 1; min-width: 200px;">
                            <label>Department</label>
                            <select name="department_id" class="form-control" onchange="this.form.submit()">
                                <option value="">All Departments</option>
                                <?php foreach ($departments as $dept): ?>
                                    <option value="<?php echo $dept['department_id']; ?>" <?php echo $department_filter == $dept['department_id'] ? 'selected' : ''; ?>>
                                        <?php echo sanitizeOutput($dept['department_name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group" style="flex: 1; min-width: 200px;">
                            <label>Program</label>
                            <select name="program_id" class="form-control" onchange="this.form.submit()">
                                <option value="">All Programs</option>
                                <?php foreach ($programs as $prog): ?>
                                    <option value="<?php echo $prog['program_id']; ?>" <?php echo $program_filter == $prog['program_id'] ? 'selected' : ''; ?>>
                                        <?php echo sanitizeOutput($prog['program_name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <?php if ($major_column_exists && !empty($majors)): ?>
                        <div class="form-group" style="flex: 1; min-width: 200px;">
                            <label>Major</label>
                            <select name="major_id" class="form-control" onchange="this.form.submit()">
                                <option value="">All Majors</option>
                                <?php foreach ($majors as $major): ?>
                                    <option value="<?php echo $major['major_id']; ?>" <?php echo $major_filter == $major['major_id'] ? 'selected' : ''; ?>>
                                        <?php echo sanitizeOutput($major['major_name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <?php endif; ?>
                        <div class="form-group">
                            <a href="?tab=enrollments" class="btn btn-secondary">Clear Filters</a>
                        </div>
                    </form>

                    <?php
                    // Organize enrollments by department/program/major
                    $organized_enrollments = [];
                    foreach ($enrollments as $enroll) {
                        $dept_key = $enroll['department_name'] ?? 'General/Minor Courses';
                        $prog_key = $enroll['program_name'] ?? 'General';
                        $major_key = ($major_column_exists && !empty($enroll['major_name'])) ? $enroll['major_name'] : 'General';
                        
                        if (!isset($organized_enrollments[$dept_key])) {
                            $organized_enrollments[$dept_key] = [];
                        }
                        if (!isset($organized_enrollments[$dept_key][$prog_key])) {
                            $organized_enrollments[$dept_key][$prog_key] = [];
                        }
                        if (!isset($organized_enrollments[$dept_key][$prog_key][$major_key])) {
                            $organized_enrollments[$dept_key][$prog_key][$major_key] = [];
                        }
                        $organized_enrollments[$dept_key][$prog_key][$major_key][] = $enroll;
                    }
                    ?>

                    <?php if (empty($organized_enrollments)): ?>
                        <div class="alert alert-info">No enrollments found.</div>
                    <?php else: ?>
                        <?php foreach ($organized_enrollments as $dept_name => $programs_group): ?>
                            <div style="margin-bottom: 2rem;">
                                <h3 style="margin-bottom: 1rem; padding-bottom: 0.5rem; border-bottom: 2px solid var(--primary-color); color: var(--primary-color);">
                                    <i class="fas fa-building"></i> <?php echo sanitizeOutput($dept_name); ?>
                                </h3>
                                <?php foreach ($programs_group as $prog_name => $majors_group): ?>
                                    <div style="margin-left: 1.5rem; margin-bottom: 1.5rem;">
                                        <h4 style="margin-bottom: 0.75rem; color: var(--text-color);">
                                            <i class="fas fa-graduation-cap"></i> <?php echo sanitizeOutput($prog_name); ?>
                                        </h4>
                                        <?php foreach ($majors_group as $major_name => $enrollment_list): ?>
                                            <?php if ($major_name !== 'General'): ?>
                                            <div style="margin-left: 1.5rem; margin-bottom: 1rem;">
                                                <h5 style="margin-bottom: 0.5rem; color: var(--text-color-secondary); font-weight: 600;">
                                                    <i class="fas fa-bookmark"></i> Major: <?php echo sanitizeOutput($major_name); ?>
                                                </h5>
                                            <?php endif; ?>
                                            <table class="data-table" style="margin-bottom: 1rem;">
                                                <thead>
                                                    <tr>
                                                        <th>Student</th>
                                                        <th>Course</th>
                                                        <th>Section</th>
                                                        <th>Enrollment Date</th>
                                                        <th>Status</th>
                                                        <th>Grade</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <?php foreach ($enrollment_list as $enroll): ?>
                                                    <tr>
                                                        <td><strong><?php echo sanitizeOutput($enroll['student_number']); ?></strong><br>
                                                            <small><?php echo sanitizeOutput($enroll['first_name'] . ' ' . $enroll['last_name']); ?></small></td>
                                                        <td><?php echo sanitizeOutput($enroll['course_name']); ?></td>
                                                        <td><?php echo sanitizeOutput($enroll['section_number']); ?></td>
                                                        <td><?php echo date('M d, Y', strtotime($enroll['enrollment_date'])); ?></td>
                                                        <td><span class="badge badge-info"><?php echo sanitizeOutput($enroll['status']); ?></span></td>
                                                        <td><?php echo sanitizeOutput($enroll['final_grade'] ?? 'N/A'); ?></td>
                                                    </tr>
                                                    <?php endforeach; ?>
                                                </tbody>
                                            </table>
                                            <?php if ($major_name !== 'General'): ?>
                                            </div>
                                            <?php endif; ?>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
