<?php
/**
 * Attendance - Check Attendance for a Section
 */
require_once __DIR__ . '/../../config/config.php';
requireAuth([ROLE_ADMIN, ROLE_FACULTY]);
$page_title = 'Check Attendance';
$pdo = getDBConnection();

$section_id = (int)($_GET['section_id'] ?? 0);
$date = $_GET['date'] ?? date('Y-m-d');

if (!$section_id) {
    header('Location: ' . BASE_URL . 'modules/attendance/index.php?error=Invalid section ID');
    exit;
}

$faculty_id = null;
if (hasRole(ROLE_FACULTY) && !hasRole(ROLE_ADMIN)) {
    $stmt = $pdo->prepare("SELECT faculty_id FROM faculty WHERE user_id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $faculty = $stmt->fetch();
    $faculty_id = $faculty['faculty_id'] ?? null;
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
    header('Location: ' . BASE_URL . 'modules/attendance/index.php?error=Section not found');
    exit;
}

// Check if faculty member has access to this section
if ($faculty_id && $section['faculty_id'] != $faculty_id) {
    header('Location: ' . BASE_URL . 'modules/attendance/index.php?error=You do not have access to this section');
    exit;
}

// Get enrolled students
// For faculty teaching a section, show ALL enrolled students (not just advisees)
// This is different from advisory where they only see their advisees
$stmt = $pdo->prepare("SELECT e.*, s.student_number, s.first_name, s.last_name
                      FROM enrollments e
                      INNER JOIN students s ON e.student_id = s.student_id
                      WHERE e.section_id = ? AND e.status = 'Enrolled'
                      ORDER BY s.last_name");
$stmt->execute([$section_id]);
$enrollments = $stmt->fetchAll();

// Get existing attendance for the date
$attendance_records = [];
if (!empty($enrollments)) {
    $enrollment_ids = array_column($enrollments, 'enrollment_id');
    $placeholders = str_repeat('?,', count($enrollment_ids) - 1) . '?';
    $stmt = $pdo->prepare("SELECT enrollment_id, status FROM attendance WHERE enrollment_id IN ($placeholders) AND attendance_date = ?");
    $stmt->execute(array_merge($enrollment_ids, [$date]));
    $existing = $stmt->fetchAll();
    foreach ($existing as $rec) {
        $attendance_records[$rec['enrollment_id']] = $rec['status'];
    }
}

include __DIR__ . '/../../includes/header.php';
?>

<style>
.attendance-header-card {
    background: linear-gradient(135deg, #1e40af 0%, #2563eb 100%);
    border-radius: 20px;
    padding: 2rem;
    color: white;
    margin-bottom: 2rem;
    box-shadow: 0 8px 24px rgba(30, 64, 175, 0.3);
}

.attendance-header-title {
    display: flex;
    align-items: center;
    gap: 1rem;
    margin-bottom: 1.5rem;
}

.attendance-header-title i {
    font-size: 2.5rem;
    color: #ffd700;
}

.attendance-header-title h1 {
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

.date-selector-card {
    background: white;
    border: 2px solid #e2e8f0;
    border-radius: 16px;
    padding: 1.5rem;
    margin-bottom: 2rem;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
}

.date-selector-form {
    display: flex;
    align-items: center;
    gap: 1rem;
    flex-wrap: wrap;
}

.date-selector-form label {
    font-weight: 600;
    color: #1e293b;
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.date-selector-form input[type="date"] {
    padding: 0.75rem 1rem;
    border: 2px solid #e2e8f0;
    border-radius: 8px;
    font-size: 1rem;
    transition: all 0.3s;
}

.date-selector-form input[type="date"]:focus {
    outline: none;
    border-color: #2563eb;
    box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1);
}

.attendance-table-card {
    background: white;
    border: 2px solid #e2e8f0;
    border-radius: 16px;
    overflow: hidden;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
}

.attendance-table-header {
    background: linear-gradient(135deg, #1e40af 0%, #2563eb 100%);
    padding: 1.5rem 2rem;
    color: white;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.attendance-table-header h2 {
    margin: 0;
    font-size: 1.5rem;
    font-weight: 700;
    display: flex;
    align-items: center;
    gap: 0.75rem;
}

.attendance-table {
    width: 100%;
    border-collapse: collapse;
}

.attendance-table thead {
    background: #f8fafc;
}

.attendance-table th {
    padding: 1.25rem 1.5rem;
    text-align: left;
    font-weight: 600;
    color: #1e293b;
    border-bottom: 2px solid #e2e8f0;
    font-size: 0.875rem;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.attendance-table td {
    padding: 1.25rem 1.5rem;
    border-bottom: 1px solid #e2e8f0;
}

.attendance-table tbody tr {
    transition: background 0.2s;
}

.attendance-table tbody tr:hover {
    background: #f8fafc;
}

.student-name {
    font-weight: 600;
    color: #1e293b;
}

.student-number {
    color: #64748b;
    font-size: 0.875rem;
}

.status-select {
    padding: 0.625rem 1rem;
    border: 2px solid #e2e8f0;
    border-radius: 8px;
    font-size: 0.95rem;
    font-weight: 500;
    background: white;
    color: #1e293b;
    cursor: pointer;
    transition: all 0.3s;
    min-width: 140px;
}

.status-select:focus {
    outline: none;
    border-color: #2563eb;
    box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1);
}

.status-select option[value="Present"] {
    color: #22c55e;
    font-weight: 600;
}

.status-select option[value="Absent"] {
    color: #ef4444;
    font-weight: 600;
}

.status-select option[value="Late"] {
    color: #f59e0b;
    font-weight: 600;
}

.status-select option[value="Excused"] {
    color: #3b82f6;
    font-weight: 600;
}

.status-select option[value="Tardy"] {
    color: #f97316;
    font-weight: 600;
}

.attendance-footer {
    padding: 1.5rem 2rem;
    background: #f8fafc;
    border-top: 2px solid #e2e8f0;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.stats-summary {
    display: flex;
    gap: 2rem;
    flex-wrap: wrap;
}

.stat-item {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    font-size: 0.875rem;
    color: #64748b;
}

.stat-item strong {
    color: #1e293b;
    font-weight: 600;
}
</style>

<div class="page-container">
    <div class="page-header">
        <h1><i class="fas fa-calendar-check"></i> Check Attendance</h1>
        <a href="<?php echo BASE_URL; ?>modules/attendance/index.php" class="btn btn-secondary">
            <i class="fas fa-arrow-left"></i> Back to Attendance
        </a>
    </div>

    <?php if (isset($_GET['success'])): ?>
        <div class="alert alert-success">
            <i class="fas fa-check-circle"></i> <?php echo sanitizeOutput($_GET['success']); ?>
        </div>
    <?php endif; ?>
    
    <?php if (isset($_GET['error'])): ?>
        <div class="alert alert-error">
            <i class="fas fa-exclamation-circle"></i> <?php echo sanitizeOutput($_GET['error']); ?>
        </div>
    <?php endif; ?>

    <div class="attendance-header-card">
        <div class="attendance-header-title">
            <i class="fas fa-users"></i>
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
        </div>
    </div>

    <?php if (empty($enrollments)): ?>
        <div class="detail-cards">
            <div class="detail-card">
                <div style="text-align: center; padding: 3rem; color: #64748b;">
                    <i class="fas fa-users-slash" style="font-size: 4rem; margin-bottom: 1rem; opacity: 0.5;"></i>
                    <p style="font-size: 1.25rem; font-weight: 600; color: #1e293b; margin: 0;">No students enrolled in this section.</p>
                </div>
            </div>
        </div>
    <?php else: ?>
        <div class="date-selector-card">
            <form method="GET" action="" class="date-selector-form">
                <input type="hidden" name="section_id" value="<?php echo $section_id; ?>">
                <label>
                    <i class="fas fa-calendar-alt"></i> Select Date:
                </label>
                <input type="date" name="date" value="<?php echo sanitizeOutput($date); ?>">
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-search"></i> Load Attendance
                </button>
            </form>
        </div>

        <div class="attendance-table-card">
            <div class="attendance-table-header">
                <h2>
                    <i class="fas fa-clipboard-list"></i>
                    Attendance for <?php echo date('F d, Y', strtotime($date)); ?>
                </h2>
                <div style="display: flex; align-items: center; gap: 1rem;">
                    <span style="opacity: 0.9; font-size: 0.875rem;">
                        <i class="fas fa-user-graduate"></i> <?php echo count($enrollments); ?> Students
                    </span>
                </div>
            </div>
            
            <form method="POST" action="attendance_save.php">
                <input type="hidden" name="section_id" value="<?php echo $section_id; ?>">
                <input type="hidden" name="date" value="<?php echo sanitizeOutput($date); ?>">
                
                <table class="attendance-table">
                    <thead>
                        <tr>
                            <th style="width: 20%;">Student Number</th>
                            <th style="width: 40%;">Name</th>
                            <th style="width: 40%;">Attendance Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        $status_counts = ['Present' => 0, 'Absent' => 0, 'Late' => 0, 'Excused' => 0, 'Tardy' => 0];
                        foreach ($enrollments as $enroll): 
                            $current_status = $attendance_records[$enroll['enrollment_id']] ?? '';
                            if ($current_status && isset($status_counts[$current_status])) {
                                $status_counts[$current_status]++;
                            }
                        ?>
                        <tr>
                            <td>
                                <span class="student-number"><?php echo sanitizeOutput($enroll['student_number']); ?></span>
                            </td>
                            <td>
                                <span class="student-name"><?php echo sanitizeOutput($enroll['first_name'] . ' ' . $enroll['last_name']); ?></span>
                            </td>
                            <td>
                                <select name="attendance[<?php echo $enroll['enrollment_id']; ?>]" class="status-select">
                                    <option value="Present" <?php echo ($current_status === 'Present') ? 'selected' : ''; ?>>✓ Present</option>
                                    <option value="Absent" <?php echo ($current_status === 'Absent') ? 'selected' : ''; ?>>✗ Absent</option>
                                    <option value="Late" <?php echo ($current_status === 'Late') ? 'selected' : ''; ?>>⏰ Late</option>
                                    <option value="Excused" <?php echo ($current_status === 'Excused') ? 'selected' : ''; ?>>📝 Excused</option>
                                    <option value="Tardy" <?php echo ($current_status === 'Tardy') ? 'selected' : ''; ?>>⚠️ Tardy</option>
                                </select>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                
                <div class="attendance-footer">
                    <div class="stats-summary">
                        <div class="stat-item">
                            <i class="fas fa-check-circle" style="color: #22c55e;"></i>
                            <strong>Present:</strong> <span><?php echo $status_counts['Present']; ?></span>
                        </div>
                        <div class="stat-item">
                            <i class="fas fa-times-circle" style="color: #ef4444;"></i>
                            <strong>Absent:</strong> <span><?php echo $status_counts['Absent']; ?></span>
                        </div>
                        <div class="stat-item">
                            <i class="fas fa-clock" style="color: #f59e0b;"></i>
                            <strong>Late:</strong> <span><?php echo $status_counts['Late']; ?></span>
                        </div>
                        <div class="stat-item">
                            <i class="fas fa-file-alt" style="color: #3b82f6;"></i>
                            <strong>Excused:</strong> <span><?php echo $status_counts['Excused']; ?></span>
                        </div>
                        <div class="stat-item">
                            <i class="fas fa-exclamation-triangle" style="color: #f97316;"></i>
                            <strong>Tardy:</strong> <span><?php echo $status_counts['Tardy']; ?></span>
                        </div>
                    </div>
                    <button type="submit" class="btn btn-primary btn-lg">
                        <i class="fas fa-save"></i> Save Attendance
                    </button>
                </div>
            </form>
        </div>
    <?php endif; ?>
</div>

<?php include __DIR__ . '/../../includes/footer.php'; ?>

