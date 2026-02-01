<?php
/**
 * Student Enrolled Courses View
 * Allows students to view all their enrolled courses
 */
require_once __DIR__ . '/../../config/config.php';
requireAuth(ROLE_STUDENT);
$page_title = 'My Courses';

$pdo = getDBConnection();
$student_id = null;

// Get student_id from user_id
$stmt = $pdo->prepare("SELECT student_id FROM students WHERE user_id = ?");
$stmt->execute([$_SESSION['user_id']]);
$student = $stmt->fetch();
if (!$student) {
    header('Location: ' . BASE_URL . 'dashboard/index.php?error=Student record not found');
    exit;
}
$student_id = $student['student_id'];

// Get all enrollments with course and section details
$stmt = $pdo->prepare("SELECT e.*, cs.section_number, cs.semester, cs.academic_year, cs.schedule_day, cs.start_time, cs.end_time,
                      c.course_code, c.course_name, c.credits, c.course_type,
                      r.room_number, r.building_name,
                      f.first_name as faculty_first, f.last_name as faculty_last,
                      p.program_name, d.department_name
                      FROM enrollments e
                      LEFT JOIN class_sections cs ON e.section_id = cs.section_id
                      LEFT JOIN courses c ON cs.course_id = c.course_id
                      LEFT JOIN rooms r ON cs.room_id = r.room_id
                      LEFT JOIN faculty f ON cs.faculty_id = f.faculty_id
                      LEFT JOIN students s ON e.student_id = s.student_id
                      LEFT JOIN programs p ON s.program_id = p.program_id
                      LEFT JOIN departments d ON p.department_id = d.department_id
                      WHERE e.student_id = ? AND e.status = 'Enrolled'
                      ORDER BY cs.academic_year DESC, 
                               CASE cs.semester 
                                   WHEN 'First' THEN 1
                                   WHEN '2nd' THEN 2
                                   WHEN 'Summer' THEN 3
                                   ELSE 4
                               END,
                               c.course_name");
$stmt->execute([$student_id]);
$enrollments = $stmt->fetchAll();

// Calculate total credits
$total_credits = 0;
foreach ($enrollments as $enroll) {
    $total_credits += (int)($enroll['credits'] ?? 0);
}

// Group by semester/academic year
$enrollments_by_term = [];
foreach ($enrollments as $enroll) {
    $key = $enroll['academic_year'] . ' - ' . $enroll['semester'];
    if (!isset($enrollments_by_term[$key])) {
        $enrollments_by_term[$key] = [];
    }
    $enrollments_by_term[$key][] = $enroll;
}

include __DIR__ . '/../../includes/header.php';
?>

<div class="page-container">
    <div class="page-header">
        <h1><i class="fas fa-book"></i> My Enrolled Courses</h1>
    </div>

    <div class="card" style="margin-bottom: 1.5rem;">
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon blue">
                    <i class="fas fa-book-open"></i>
                </div>
                <div class="stat-info">
                    <h3><?php echo count($enrollments); ?></h3>
                    <p>Total Courses</p>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon green">
                    <i class="fas fa-graduation-cap"></i>
                </div>
                <div class="stat-info">
                    <h3><?php echo $total_credits; ?></h3>
                    <p>Total Credits</p>
                </div>
            </div>
        </div>
    </div>

    <?php if (empty($enrollments)): ?>
        <div class="card">
            <div class="alert alert-info">No enrolled courses found.</div>
        </div>
    <?php else: ?>
        <?php foreach ($enrollments_by_term as $term => $term_enrollments): ?>
            <div class="card" style="margin-bottom: 1.5rem;">
                <h2 style="margin-bottom: 1rem;"><?php echo sanitizeOutput($term); ?></h2>
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Course Code</th>
                            <th>Course Name</th>
                            <th>Section</th>
                            <th>Credits</th>
                            <th>Schedule</th>
                            <th>Room</th>
                            <th>Instructor</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($term_enrollments as $enroll): 
                            // Format schedule
                            $schedule_display = 'TBA';
                            if (!empty($enroll['schedule_day']) && !empty($enroll['start_time']) && !empty($enroll['end_time'])) {
                                try {
                                    $schedule_json = json_decode($enroll['schedule_day'], true);
                                    if (json_last_error() === JSON_ERROR_NONE && is_array($schedule_json)) {
                                        // New JSON format
                                        $schedule_parts = [];
                                        foreach ($schedule_json as $day => $times) {
                                            $schedule_parts[] = $day . ' ' . date('g:i A', strtotime($times['start'])) . ' - ' . date('g:i A', strtotime($times['end']));
                                        }
                                        $schedule_display = implode('<br>', $schedule_parts);
                                    } else {
                                        // Old format
                                        $schedule_display = $enroll['schedule_day'] . '<br>' . date('g:i A', strtotime($enroll['start_time'])) . ' - ' . date('g:i A', strtotime($enroll['end_time']));
                                    }
                                } catch (Exception $e) {
                                    $schedule_display = $enroll['schedule_day'] . ' ' . ($enroll['start_time'] ?? '') . ' - ' . ($enroll['end_time'] ?? '');
                                }
                            }
                        ?>
                        <tr>
                            <td><strong><?php echo sanitizeOutput($enroll['course_code']); ?></strong></td>
                            <td><?php echo sanitizeOutput($enroll['course_name']); ?></td>
                            <td><?php echo sanitizeOutput($enroll['section_number']); ?></td>
                            <td><?php echo sanitizeOutput($enroll['credits'] ?? 'N/A'); ?></td>
                            <td><?php echo $schedule_display; ?></td>
                            <td>
                                <?php if (!empty($enroll['room_number'])): ?>
                                    <?php echo sanitizeOutput($enroll['room_number']); ?>
                                    <?php if (!empty($enroll['building_name'])): ?>
                                        <br><small class="text-muted"><?php echo sanitizeOutput($enroll['building_name']); ?></small>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <span class="text-muted">TBA</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if (!empty($enroll['faculty_first']) || !empty($enroll['faculty_last'])): ?>
                                    <?php echo sanitizeOutput(($enroll['faculty_first'] ?? '') . ' ' . ($enroll['faculty_last'] ?? '')); ?>
                                <?php else: ?>
                                    <span class="text-muted">TBA</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <span class="badge badge-<?php echo $enroll['status'] === 'Enrolled' ? 'success' : ($enroll['status'] === 'Completed' ? 'info' : 'warning'); ?>">
                                    <?php echo sanitizeOutput($enroll['status']); ?>
                                </span>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

<?php include __DIR__ . '/../../includes/footer.php'; ?>

