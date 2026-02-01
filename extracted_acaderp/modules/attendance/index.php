<?php
require_once __DIR__ . '/../../config/config.php';
requireAuth([ROLE_ADMIN, ROLE_FACULTY]);
$page_title = 'Attendance Tracking';
$pdo = getDBConnection();

$faculty_id = null;
$faculty_courses = [];

// Get faculty_id if user is faculty
if (hasRole(ROLE_FACULTY) && !hasRole(ROLE_ADMIN)) {
    $stmt = $pdo->prepare("SELECT faculty_id FROM faculty WHERE user_id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $faculty = $stmt->fetch();
    $faculty_id = $faculty['faculty_id'] ?? null;
    
    // Get courses assigned to this faculty, organized by department/program/major
    if ($faculty_id) {
        // Check if major_id column exists
        $major_column_exists = false;
        try {
            $check_stmt = $pdo->query("SHOW COLUMNS FROM courses LIKE 'major_id'");
            $major_column_exists = $check_stmt->rowCount() > 0;
        } catch (PDOException $e) {
            $major_column_exists = false;
        }
        
        $courses_query = "SELECT DISTINCT cs.section_id, c.course_id, c.course_code, c.course_name, c.credits,
                         cs.section_number, cs.semester, cs.academic_year,
                         d.department_name, d.department_code,
                         p.program_name, p.program_id,
                         " . ($major_column_exists ? "m.major_name," : "") . "
                         r.room_number
                         FROM class_sections cs
                         LEFT JOIN courses c ON cs.course_id = c.course_id
                         LEFT JOIN departments d ON c.department_id = d.department_id
                         LEFT JOIN program_courses pc ON c.course_id = pc.course_id
                         LEFT JOIN programs p ON pc.program_id = p.program_id
                         " . ($major_column_exists ? "LEFT JOIN majors m ON c.major_id = m.major_id" : "") . "
                         LEFT JOIN rooms r ON cs.room_id = r.room_id
                         WHERE cs.faculty_id = ?
                         ORDER BY d.department_name, p.program_name, " . ($major_column_exists ? "m.major_name, " : "") . "c.course_name, cs.academic_year DESC, cs.semester";
        $courses_stmt = $pdo->prepare($courses_query);
        $courses_stmt->execute([$faculty_id]);
        $faculty_courses = $courses_stmt->fetchAll();
    }
}

// Check if major_id column exists
$major_column_exists = false;
try {
    $check_stmt = $pdo->query("SHOW COLUMNS FROM courses LIKE 'major_id'");
    $major_column_exists = $check_stmt->rowCount() > 0;
} catch (PDOException $e) {
    $major_column_exists = false;
}

include __DIR__ . '/../../includes/header.php';
?>

<div class="page-container">
    <div class="page-header">
        <h1><i class="fas fa-calendar-check"></i> Attendance Tracking</h1>
        <a href="<?php echo BASE_URL; ?>modules/attendance/all.php" class="btn btn-primary">
            <i class="fas fa-list"></i> All Attendance
        </a>
    </div>

    <?php if ($faculty_id && !empty($faculty_courses)): ?>
    <div class="card" style="margin-bottom: 1.5rem;">
        <h2 style="margin-bottom: 1rem;"><i class="fas fa-chalkboard-teacher"></i> My Assigned Courses</h2>
        <?php
        // Organize courses by department/program/major
        $organized_courses = [];
        foreach ($faculty_courses as $course) {
            $dept_key = $course['department_name'] ?? 'General/Minor Courses';
            $prog_key = $course['program_name'] ?? 'General';
            $major_key = ($major_column_exists && !empty($course['major_name'])) ? $course['major_name'] : 'General';
            
            if (!isset($organized_courses[$dept_key])) {
                $organized_courses[$dept_key] = [];
            }
            if (!isset($organized_courses[$dept_key][$prog_key])) {
                $organized_courses[$dept_key][$prog_key] = [];
            }
            if (!isset($organized_courses[$dept_key][$prog_key][$major_key])) {
                $organized_courses[$dept_key][$prog_key][$major_key] = [];
            }
            $organized_courses[$dept_key][$prog_key][$major_key][] = $course;
        }
        ?>
        
        <?php foreach ($organized_courses as $dept_name => $programs_group): ?>
            <div style="margin-bottom: 1.5rem;">
                <h3 style="margin-bottom: 0.75rem; padding-bottom: 0.5rem; border-bottom: 2px solid var(--primary-color); color: var(--primary-color);">
                    <i class="fas fa-building"></i> <?php echo sanitizeOutput($dept_name); ?>
                </h3>
                <?php foreach ($programs_group as $prog_name => $majors_group): ?>
                    <div style="margin-left: 1.5rem; margin-bottom: 1rem;">
                        <h4 style="margin-bottom: 0.5rem; color: var(--text-color); font-size: 1.1rem;">
                            <i class="fas fa-graduation-cap"></i> <?php echo sanitizeOutput($prog_name); ?>
                        </h4>
                        <?php foreach ($majors_group as $major_name => $course_list): ?>
                            <?php if ($major_name !== 'General'): ?>
                            <div style="margin-left: 1rem; margin-bottom: 0.5rem;">
                                <h5 style="margin-bottom: 0.5rem; color: var(--text-color-secondary); font-weight: 600; font-size: 1rem;">
                                    <i class="fas fa-bookmark"></i> Major: <?php echo sanitizeOutput($major_name); ?>
                                </h5>
                            <?php endif; ?>
                            <div style="margin-left: <?php echo $major_name !== 'General' ? '1.5rem' : '0'; ?>;">
                                <ul style="list-style: none; padding: 0;">
                                    <?php foreach ($course_list as $course): ?>
                                    <li style="padding: 0.5rem 0; border-bottom: 1px solid #eee; display: flex; justify-content: space-between; align-items: center;">
                                        <div>
                                            <strong><?php echo sanitizeOutput($course['course_name']); ?></strong>
                                            <?php if ($course['course_code']): ?>
                                                <span class="text-muted">(<?php echo sanitizeOutput($course['course_code']); ?>)</span>
                                            <?php endif; ?>
                                            - Section <?php echo sanitizeOutput($course['section_number']); ?>
                                            <span class="text-muted">(<?php echo sanitizeOutput($course['semester'] . ' ' . $course['academic_year']); ?>)</span>
                                            <?php if ($course['room_number']): ?>
                                                <span class="badge badge-info">Room: <?php echo sanitizeOutput($course['room_number']); ?></span>
                                            <?php endif; ?>
                                        </div>
                                        <a href="<?php echo BASE_URL; ?>modules/attendance/check.php?section_id=<?php echo $course['section_id']; ?>" class="btn btn-sm btn-primary">
                                            <i class="fas fa-check-circle"></i> Check Attendance
                                        </a>
                                    </li>
                                    <?php endforeach; ?>
                                </ul>
                            </div>
                            <?php if ($major_name !== 'General'): ?>
                            </div>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endforeach; ?>
    </div>
    <?php elseif ($faculty_id && empty($faculty_courses)): ?>
        <div class="alert alert-info">You have no assigned courses. Contact administration to be assigned to course sections.</div>
    <?php else: ?>
        <?php
        // Admin view - show all sections in dropdown
        $all_sections = [];
        $stmt = $pdo->query("SELECT cs.section_id, c.course_name, cs.section_number, cs.semester, cs.academic_year,
                             d.department_name, p.program_name, " . ($major_column_exists ? "m.major_name," : "") . "
                             cs.faculty_id, f.first_name as faculty_first, f.last_name as faculty_last
                             FROM class_sections cs
                             LEFT JOIN courses c ON cs.course_id = c.course_id
                             LEFT JOIN departments d ON c.department_id = d.department_id
                             LEFT JOIN program_courses pc ON c.course_id = pc.course_id
                             LEFT JOIN programs p ON pc.program_id = p.program_id
                             " . ($major_column_exists ? "LEFT JOIN majors m ON c.major_id = m.major_id" : "") . "
                             LEFT JOIN faculty f ON cs.faculty_id = f.faculty_id
                             ORDER BY d.department_name, p.program_name, " . ($major_column_exists ? "m.major_name, " : "") . "c.course_name, cs.academic_year DESC");
        $all_sections = $stmt->fetchAll();
        ?>
        <div class="card">
            <h2>Select Section to Check Attendance</h2>
            <form method="GET" action="check.php" style="display: flex; gap: 1rem; align-items: end;">
                <div class="form-group" style="flex: 1;">
                    <label>Section</label>
                    <select name="section_id" class="form-control" required>
                        <option value="">-- Select Section --</option>
                        <?php 
                        $current_dept = '';
                        $current_prog = '';
                        $current_major = '';
                        foreach ($all_sections as $sec): 
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
                            <option value="<?php echo $sec['section_id']; ?>">
                                <?php echo sanitizeOutput($sec['course_name'] . ' - Section ' . $sec['section_number'] . ' (' . $sec['semester'] . ' ' . $sec['academic_year'] . ')' . ($sec['faculty_first'] ? ' - ' . $sec['faculty_first'] . ' ' . $sec['faculty_last'] : '')); ?>
                            </option>
                        <?php endforeach; ?>
                        <?php if ($current_major !== '') echo '</optgroup>'; ?>
                        <?php if ($current_prog !== '' && $current_prog !== 'General') echo '</optgroup>'; ?>
                        <?php if ($current_dept !== '') echo '</optgroup>'; ?>
                    </select>
                </div>
                <div class="form-group">
                    <button type="submit" class="btn btn-primary"><i class="fas fa-check-circle"></i> Check Attendance</button>
                </div>
            </form>
        </div>
    <?php endif; ?>
</div>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
