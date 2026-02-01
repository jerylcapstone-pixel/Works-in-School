<?php
require_once __DIR__ . '/../../config/config.php';
requireAuth([ROLE_ADMIN, ROLE_FACULTY]);
$page_title = 'Student Advisory';
$pdo = getDBConnection();

$advisory_assignments = [];
$faculty_courses = [];
$faculty_id = null;

// Check if major_id column exists
$major_column_exists = false;
try {
    $check_stmt = $pdo->query("SHOW COLUMNS FROM courses LIKE 'major_id'");
    $major_column_exists = $check_stmt->rowCount() > 0;
} catch (PDOException $e) {
    $major_column_exists = false;
}

// Get faculty_id if user is faculty
if (hasRole(ROLE_FACULTY) && !hasRole(ROLE_ADMIN)) {
    $stmt = $pdo->prepare("SELECT faculty_id FROM faculty WHERE user_id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $faculty = $stmt->fetch();
    $faculty_id = $faculty['faculty_id'] ?? null;
    
    // Debug: Check if faculty_id was found
    if (!$faculty_id) {
        error_log("Advisory: Faculty ID not found for user_id: " . $_SESSION['user_id']);
    }
    
    // Get courses assigned to this faculty, organized by department/program/major
    if ($faculty_id) {
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

if ($faculty_id) {
    // Get students from two sources:
    // 1. Explicit advisory assignments
    // 2. Students enrolled in sections taught by this faculty
    $query = "SELECT DISTINCT 
              COALESCE(aa.advisory_id, 0) as advisory_id,
              s.student_id,
              s.student_number, 
              s.first_name as student_first, 
              s.last_name as student_last,
              s.program_id, 
              COALESCE(p.program_name, 'General') as program_name, 
              COALESCE(p.department_id, 0) as department_id,
              f.first_name as advisor_first, 
              f.last_name as advisor_last,
              COALESCE(aa.assigned_date, CURDATE()) as assigned_date,
              COALESCE(aa.status, 'Active') as status,
              'Advisory' as source
              FROM students s
              LEFT JOIN programs p ON s.program_id = p.program_id
              LEFT JOIN faculty f ON f.faculty_id = ?
              LEFT JOIN advisory_assignments aa ON aa.student_id = s.student_id AND aa.advisor_id = ? AND aa.status = 'Active'
              LEFT JOIN enrollments e ON e.student_id = s.student_id AND e.status = 'Enrolled'
              LEFT JOIN class_sections cs ON cs.section_id = e.section_id AND cs.faculty_id = ?
              WHERE s.status = 'Active' 
              AND (aa.advisory_id IS NOT NULL OR cs.section_id IS NOT NULL)
              ORDER BY COALESCE(p.department_id, 0), COALESCE(p.program_name, 'General'), s.last_name, s.first_name";
    $stmt = $pdo->prepare($query);
    $stmt->execute([$faculty_id, $faculty_id, $faculty_id]);
    $advisory_assignments = $stmt->fetchAll();
    
    // Debug: Log query results
    error_log("Advisory query for faculty_id=$faculty_id returned " . count($advisory_assignments) . " assignments");
} else {
    $query = "SELECT aa.*, s.student_number, s.first_name as student_first, s.last_name as student_last,
              s.program_id, COALESCE(p.program_name, 'General') as program_name, COALESCE(p.department_id, 0) as department_id,
              f.first_name as advisor_first, f.last_name as advisor_last
              FROM advisory_assignments aa
              INNER JOIN students s ON aa.student_id = s.student_id
              LEFT JOIN programs p ON s.program_id = p.program_id
              LEFT JOIN faculty f ON aa.advisor_id = f.faculty_id
              WHERE aa.status = 'Active'
              ORDER BY COALESCE(p.department_id, 0), COALESCE(p.program_name, 'General'), s.last_name, s.first_name";
    $advisory_assignments = $pdo->query($query)->fetchAll();
}

include __DIR__ . '/../../includes/header.php';
?>

<div class="page-container">
    <div class="page-header">
        <h1><i class="fas fa-user-friends"></i> Student Advisory Management</h1>
        <?php if (hasRole(ROLE_ADMIN)): ?>
            <a href="<?php echo BASE_URL; ?>modules/advisory/assign.php" class="btn btn-primary">
                <i class="fas fa-plus"></i> Assign Advisor
            </a>
        <?php endif; ?>
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
                                        <a href="<?php echo BASE_URL; ?>modules/advisory/students.php?section_id=<?php echo $course['section_id']; ?>" class="btn btn-sm btn-primary">
                                            <i class="fas fa-users"></i> View Students
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
    <?php endif; ?>
</div>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
