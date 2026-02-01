<?php
require_once __DIR__ . '/../../config/config.php';
requireAuth([ROLE_FACULTY]);
$page_title = 'Grades & Transcripts';
$pdo = getDBConnection();

$faculty_id = null;
$faculty_courses = [];

// Get faculty_id
$stmt = $pdo->prepare("SELECT faculty_id FROM faculty WHERE user_id = ?");
$stmt->execute([$_SESSION['user_id']]);
$faculty = $stmt->fetch();
$faculty_id = $faculty['faculty_id'] ?? null;

// Check if major_id column exists
$major_column_exists = false;
try {
    $check_stmt = $pdo->query("SHOW COLUMNS FROM courses LIKE 'major_id'");
    $major_column_exists = $check_stmt->rowCount() > 0;
} catch (PDOException $e) {
    $major_column_exists = false;
}

// Get courses assigned to faculty with department/program/major info
if ($faculty_id) {
    $courses_query = "SELECT DISTINCT cs.section_id, c.course_id, c.course_code, c.course_name, c.credits,
                     cs.section_number, cs.semester, cs.academic_year,
                     d.department_name, d.department_code,
                     p.program_name, p.program_id,
                     " . ($major_column_exists ? "m.major_name," : "") . "
                     r.room_number,
                     (SELECT COUNT(*) FROM enrollments e WHERE e.section_id = cs.section_id AND e.status = 'Enrolled') as student_count
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
} else {
    $faculty_courses = [];
}

include __DIR__ . '/../../includes/header.php';
?>

<div class="page-container">
    <div class="page-header">
        <h1><i class="fas fa-clipboard-list"></i> Grades & Transcripts</h1>
    </div>

    <?php if (isset($_GET['error'])): ?>
        <div class="alert alert-error"><?php echo sanitizeOutput($_GET['error']); ?></div>
    <?php endif; ?>
    
    <?php if (isset($_GET['success'])): ?>
        <div class="alert alert-success"><?php echo sanitizeOutput($_GET['success']); ?></div>
    <?php endif; ?>

    <?php if (!empty($faculty_courses)): ?>
    <div class="card">
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
                                <h5 style="margin-bottom: 0.5rem; color: #666; font-size: 1rem;">
                                    <i class="fas fa-book"></i> Major: <?php echo sanitizeOutput($major_name); ?>
                                </h5>
                            <?php endif; ?>
                            
                            <div style="margin-left: <?php echo $major_name !== 'General' ? '2rem' : '1rem'; ?>;">
                                <?php foreach ($course_list as $course): ?>
                                    <div style="background-color: #f9f9f9; padding: 1rem; margin-bottom: 0.75rem; border-radius: 4px; border-left: 3px solid var(--primary-color);">
                                        <div style="display: flex; justify-content: space-between; align-items: center;">
                                            <div style="flex: 1;">
                                                <h5 style="margin: 0 0 0.25rem 0; font-size: 1rem;">
                                                    <strong><?php echo sanitizeOutput($course['course_code'] . ' - ' . $course['course_name']); ?></strong>
                                                </h5>
                                                <p style="margin: 0; color: #666; font-size: 0.9rem;">
                                                    Section <?php echo sanitizeOutput($course['section_number']); ?> | 
                                                    <?php echo sanitizeOutput($course['semester']); ?> Semester, <?php echo sanitizeOutput($course['academic_year']); ?>
                                                    <?php if ($course['room_number']): ?>
                                                        | Room: <?php echo sanitizeOutput($course['room_number']); ?>
                                                    <?php endif; ?>
                                                    | <?php echo sanitizeOutput($course['student_count']); ?> student(s)
                                                </p>
                                            </div>
                                            <div style="margin-left: 1rem;">
                                                <a href="<?php echo BASE_URL; ?>modules/grades/detailed.php?section_id=<?php echo $course['section_id']; ?>" 
                                                   class="btn btn-primary">
                                                    <i class="fas fa-edit"></i> Enter Grades
                                                </a>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
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
    <?php else: ?>
        <div class="alert alert-info">
            <i class="fas fa-info-circle"></i> No courses assigned yet. Contact the administrator to be assigned to courses.
        </div>
    <?php endif; ?>
</div>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
