<?php
/**
 * Advisory - View Students in Section
 * Displays all students enrolled in a specific section with their details
 */
require_once __DIR__ . '/../../config/config.php';
requireAuth([ROLE_ADMIN, ROLE_FACULTY]);
$page_title = 'Section Students';
$pdo = getDBConnection();

$section_id = (int)($_GET['section_id'] ?? 0);
if (!$section_id) {
    header('Location: ' . BASE_URL . 'modules/advisory/index.php?error=Invalid section ID');
    exit;
}

$faculty_id = null;
if (hasRole(ROLE_FACULTY) && !hasRole(ROLE_ADMIN)) {
    $stmt = $pdo->prepare("SELECT faculty_id FROM faculty WHERE user_id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $faculty = $stmt->fetch();
    $faculty_id = $faculty['faculty_id'] ?? null;
}

// Check if major_id column exists
$major_column_exists = false;
try {
    $check_stmt = $pdo->query("SHOW COLUMNS FROM courses LIKE 'major_id'");
    $major_column_exists = $check_stmt->rowCount() > 0;
} catch (PDOException $e) {
    $major_column_exists = false;
}

// Check if majors table exists
$majors_table_exists = false;
try {
    $check_stmt = $pdo->query("SHOW TABLES LIKE 'majors'");
    $majors_table_exists = $check_stmt->rowCount() > 0;
} catch (PDOException $e) {
    $majors_table_exists = false;
}

// Get section details
$stmt = $pdo->prepare("SELECT cs.*, c.course_name, c.course_code, c.credits,
                     f.first_name as faculty_first, f.last_name as faculty_last,
                     r.room_number, r.building_name
                     FROM class_sections cs
                     LEFT JOIN courses c ON cs.course_id = c.course_id
                     LEFT JOIN faculty f ON cs.faculty_id = f.faculty_id
                     LEFT JOIN rooms r ON cs.room_id = r.room_id
                     WHERE cs.section_id = ?");
$stmt->execute([$section_id]);
$section = $stmt->fetch();

if (!$section) {
    header('Location: ' . BASE_URL . 'modules/advisory/index.php?error=Section not found');
    exit;
}

// Check if faculty member has access to this section
if ($faculty_id && $section['faculty_id'] != $faculty_id) {
    header('Location: ' . BASE_URL . 'modules/advisory/index.php?error=You do not have access to this section');
    exit;
}

// Get enrolled students with their details
$query = "SELECT s.student_id, s.student_number, s.first_name, s.middle_name, s.last_name,
          s.enrollment_date, s.gpa, s.total_credits, s.status as student_status,
          p.program_id, p.program_name, p.duration_years,
          d.department_name,
          " . ($majors_table_exists ? "m.major_name," : "NULL as major_name,") . "
          e.enrollment_date as enrolled_date, e.enrollment_type, e.status as enrollment_status,
          e.final_grade, e.points
          FROM enrollments e
          INNER JOIN students s ON e.student_id = s.student_id
          LEFT JOIN programs p ON s.program_id = p.program_id
          LEFT JOIN departments d ON p.department_id = d.department_id
          " . ($majors_table_exists ? "LEFT JOIN admission_applications aa ON aa.student_number = s.student_number
          LEFT JOIN majors m ON aa.major_id = m.major_id" : "") . "
          WHERE e.section_id = ? AND e.status = 'Enrolled'
          ORDER BY s.last_name, s.first_name";

$stmt = $pdo->prepare($query);
$stmt->execute([$section_id]);
$students = $stmt->fetchAll();

// Calculate year level for each student
foreach ($students as &$student) {
    if ($student['enrollment_date'] && $student['duration_years']) {
        $enrollment_date = new DateTime($student['enrollment_date']);
        $current_date = new DateTime();
        $years_diff = $enrollment_date->diff($current_date)->y;
        $months_diff = $enrollment_date->diff($current_date)->m;
        
        // Calculate year level (1st year = 0-11 months, 2nd year = 12-23 months, etc.)
        $year_level = min(floor(($years_diff * 12 + $months_diff) / 12) + 1, $student['duration_years']);
        
        // Format year level
        if ($year_level == 1) {
            $student['year_level'] = '1st Year';
        } elseif ($year_level == 2) {
            $student['year_level'] = '2nd Year';
        } elseif ($year_level == 3) {
            $student['year_level'] = '3rd Year';
        } elseif ($year_level == 4) {
            $student['year_level'] = '4th Year';
        } elseif ($year_level == 5) {
            $student['year_level'] = '5th Year';
        } else {
            $student['year_level'] = $year_level . 'th Year';
        }
    } else {
        $student['year_level'] = 'N/A';
    }
}
unset($student);

include __DIR__ . '/../../includes/header.php';
?>

<style>
.section-header-card {
    background: linear-gradient(135deg, #1e40af 0%, #2563eb 100%);
    border-radius: 20px;
    padding: 2rem;
    color: white;
    margin-bottom: 2rem;
    box-shadow: 0 8px 24px rgba(30, 64, 175, 0.3);
}

.section-header-title {
    display: flex;
    align-items: center;
    gap: 1rem;
    margin-bottom: 1.5rem;
}

.section-header-title i {
    font-size: 2.5rem;
    color: #ffd700;
}

.section-header-title h1 {
    margin: 0;
    font-size: 2rem;
    font-weight: 700;
}

.section-info-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 1.5rem;
    margin-top: 1.5rem;
}

.section-info-item {
    background: rgba(255, 255, 255, 0.15);
    backdrop-filter: blur(10px);
    border-radius: 12px;
    padding: 1rem 1.25rem;
    border: 2px solid rgba(255, 255, 255, 0.3);
}

.section-info-item label {
    display: block;
    font-size: 0.75rem;
    opacity: 0.9;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    margin-bottom: 0.5rem;
}

.section-info-item .value {
    font-size: 1.1rem;
    font-weight: 600;
}

.students-table-card {
    background: white;
    border: 2px solid #e2e8f0;
    border-radius: 16px;
    overflow: hidden;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
}

.students-table-header {
    background: linear-gradient(135deg, #1e40af 0%, #2563eb 100%);
    padding: 1.5rem 2rem;
    color: white;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.students-table-header h2 {
    margin: 0;
    font-size: 1.5rem;
    font-weight: 700;
    display: flex;
    align-items: center;
    gap: 0.75rem;
}

.students-table {
    width: 100%;
    border-collapse: collapse;
}

.students-table thead {
    background: #f8fafc;
}

.students-table th {
    padding: 1.25rem 1.5rem;
    text-align: left;
    font-weight: 600;
    color: #1e293b;
    border-bottom: 2px solid #e2e8f0;
    font-size: 0.875rem;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.students-table td {
    padding: 1.25rem 1.5rem;
    border-bottom: 1px solid #e2e8f0;
}

.students-table tbody tr {
    transition: background 0.2s;
}

.students-table tbody tr:hover {
    background: #f8fafc;
}

.student-number {
    font-weight: 700;
    color: #1e40af;
    font-family: 'Courier New', monospace;
}

.student-name {
    font-weight: 600;
    color: #1e293b;
}

.gpa-badge {
    padding: 0.375rem 0.75rem;
    border-radius: 6px;
    font-weight: 600;
    font-size: 0.875rem;
}

.gpa-badge.excellent {
    background: linear-gradient(135deg, #22c55e 0%, #16a34a 100%);
    color: white;
}

.gpa-badge.good {
    background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%);
    color: white;
}

.gpa-badge.fair {
    background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);
    color: white;
}

.gpa-badge.poor {
    background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);
    color: white;
}
</style>

<div class="page-container">
    <div class="page-header">
        <h1><i class="fas fa-users"></i> Section Students</h1>
        <a href="<?php echo BASE_URL; ?>modules/advisory/index.php" class="btn btn-secondary">
            <i class="fas fa-arrow-left"></i> Back to Advisory
        </a>
    </div>

    <div class="section-header-card">
        <div class="section-header-title">
            <i class="fas fa-chalkboard"></i>
            <h1><?php echo sanitizeOutput($section['course_name']); ?></h1>
        </div>
        
        <div class="section-info-grid">
            <div class="section-info-item">
                <label><i class="fas fa-book"></i> Course Code</label>
                <div class="value"><?php echo sanitizeOutput($section['course_code'] ?? 'N/A'); ?></div>
            </div>
            <div class="section-info-item">
                <label><i class="fas fa-layer-group"></i> Section</label>
                <div class="value"><?php echo sanitizeOutput($section['section_number']); ?></div>
            </div>
            <div class="section-info-item">
                <label><i class="fas fa-calendar-alt"></i> Semester</label>
                <div class="value"><?php echo sanitizeOutput($section['semester']); ?></div>
            </div>
            <div class="section-info-item">
                <label><i class="fas fa-graduation-cap"></i> Academic Year</label>
                <div class="value"><?php echo sanitizeOutput($section['academic_year']); ?></div>
            </div>
            <div class="section-info-item">
                <label><i class="fas fa-clock"></i> Schedule</label>
                <div class="value"><?php echo sanitizeOutput(formatScheduleDisplay($section['schedule_day'], $section['start_time'], $section['end_time'])); ?></div>
            </div>
            <?php if ($section['room_number']): ?>
            <div class="section-info-item">
                <label><i class="fas fa-door-open"></i> Room</label>
                <div class="value">
                    <?php echo sanitizeOutput($section['room_number']); ?>
                    <?php if ($section['building_name']): ?>
                        <span style="opacity: 0.8;"> - <?php echo sanitizeOutput($section['building_name']); ?></span>
                    <?php endif; ?>
                </div>
            </div>
            <?php endif; ?>
            <div class="section-info-item">
                <label><i class="fas fa-chalkboard-teacher"></i> Faculty</label>
                <div class="value"><?php echo sanitizeOutput(trim($section['faculty_first'] . ' ' . $section['faculty_last'])); ?></div>
            </div>
            <div class="section-info-item">
                <label><i class="fas fa-user-graduate"></i> Enrollment</label>
                <div class="value">
                    <strong><?php echo count($students); ?></strong> / <?php echo $section['max_enrollment']; ?>
                    <span style="opacity: 0.8; font-size: 0.875rem; margin-left: 0.5rem;">
                        (<?php echo round((count($students) / $section['max_enrollment']) * 100, 1); ?>%)
                    </span>
                </div>
            </div>
        </div>
    </div>

    <?php if (empty($students)): ?>
        <div class="detail-cards">
            <div class="detail-card">
                <div style="text-align: center; padding: 3rem; color: #64748b;">
                    <i class="fas fa-users-slash" style="font-size: 4rem; margin-bottom: 1rem; opacity: 0.5;"></i>
                    <p style="font-size: 1.25rem; font-weight: 600; color: #1e293b; margin: 0;">No students enrolled in this section.</p>
                </div>
            </div>
        </div>
    <?php else: ?>
        <div class="students-table-card">
            <div class="students-table-header">
                <h2>
                    <i class="fas fa-user-graduate"></i>
                    Enrolled Students (<?php echo count($students); ?>)
                </h2>
                <div style="display: flex; align-items: center; gap: 1rem;">
                    <span style="opacity: 0.9; font-size: 0.875rem;">
                        <i class="fas fa-info-circle"></i> Total: <?php echo count($students); ?> students
                    </span>
                </div>
            </div>
            
            <div style="overflow-x: auto;">
                <table class="students-table">
                    <thead>
                        <tr>
                            <th style="width: 12%;">Student Number</th>
                            <th style="width: 20%;">Name</th>
                            <th style="width: 15%;">Program</th>
                            <?php if ($majors_table_exists): ?>
                                <th style="width: 12%;">Major</th>
                            <?php endif; ?>
                            <th style="width: 10%;">Year Level</th>
                            <th style="width: 12%;">Department</th>
                            <th style="width: 8%;">GPA</th>
                            <th style="width: 8%;">Credits</th>
                            <th style="width: 10%;">Type</th>
                            <th style="width: 8%;">Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($students as $student): 
                            $full_name = $student['first_name'];
                            if ($student['middle_name']) {
                                $full_name .= ' ' . $student['middle_name'];
                            }
                            $full_name .= ' ' . $student['last_name'];
                            
                            // Determine GPA badge class
                            $gpa = (float)($student['gpa'] ?? 0);
                            $gpa_class = 'poor';
                            if ($gpa >= 3.5) {
                                $gpa_class = 'excellent';
                            } elseif ($gpa >= 3.0) {
                                $gpa_class = 'good';
                            } elseif ($gpa >= 2.0) {
                                $gpa_class = 'fair';
                            }
                        ?>
                        <tr>
                            <td>
                                <span class="student-number"><?php echo sanitizeOutput($student['student_number']); ?></span>
                            </td>
                            <td>
                                <span class="student-name"><?php echo sanitizeOutput($full_name); ?></span>
                            </td>
                            <td>
                                <span style="color: #1e293b;"><?php echo sanitizeOutput($student['program_name'] ?? 'N/A'); ?></span>
                            </td>
                            <?php if ($majors_table_exists): ?>
                                <td>
                                    <span style="color: #64748b;"><?php echo sanitizeOutput($student['major_name'] ?? 'N/A'); ?></span>
                                </td>
                            <?php endif; ?>
                            <td>
                                <span class="badge badge-info"><?php echo sanitizeOutput($student['year_level']); ?></span>
                            </td>
                            <td>
                                <span style="color: #64748b; font-size: 0.875rem;"><?php echo sanitizeOutput($student['department_name'] ?? 'N/A'); ?></span>
                            </td>
                            <td>
                                <span class="gpa-badge <?php echo $gpa_class; ?>">
                                    <?php echo number_format($gpa, 2); ?>
                                </span>
                            </td>
                            <td>
                                <span style="color: #1e293b; font-weight: 600;"><?php echo $student['total_credits'] ?? 0; ?></span>
                            </td>
                            <td>
                                <span class="badge badge-<?php 
                                    echo $student['enrollment_type'] === 'Regular' ? 'success' : 
                                        ($student['enrollment_type'] === 'Waitlist' ? 'warning' : 'info'); 
                                ?>">
                                    <?php echo sanitizeOutput($student['enrollment_type']); ?>
                                </span>
                            </td>
                            <td>
                                <span class="badge badge-<?php 
                                    echo $student['student_status'] === 'Active' ? 'success' : 
                                        ($student['student_status'] === 'Graduated' ? 'info' : 'warning'); 
                                ?>">
                                    <?php echo sanitizeOutput($student['student_status']); ?>
                                </span>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    <?php endif; ?>
</div>

<?php include __DIR__ . '/../../includes/footer.php'; ?>

