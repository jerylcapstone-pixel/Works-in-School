<?php
require_once __DIR__ . '/../../config/config.php';
requireAuth([ROLE_ADMIN, ROLE_FACULTY]);
$section_id = (int)($_POST['section_id'] ?? 0);
$date = $_POST['date'] ?? '';
$attendance = $_POST['attendance'] ?? [];

$pdo = getDBConnection();

// Verify access control for faculty
if (hasRole(ROLE_FACULTY) && !hasRole(ROLE_ADMIN)) {
    $stmt = $pdo->prepare("SELECT faculty_id FROM faculty WHERE user_id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $faculty = $stmt->fetch();
    $faculty_id = $faculty['faculty_id'] ?? null;
    
    if ($faculty_id) {
        // Verify faculty has access to this section
        $stmt = $pdo->prepare("SELECT faculty_id FROM class_sections WHERE section_id = ?");
        $stmt->execute([$section_id]);
        $section = $stmt->fetch();
        if (!$section || $section['faculty_id'] != $faculty_id) {
            header('Location: ' . BASE_URL . 'modules/attendance/index.php?error=Access denied');
            exit;
        }
    }
}

foreach ($attendance as $enrollment_id => $status) {
    $enrollment_id = (int)$enrollment_id;
    $status = sanitizeInput($status);
    
    // Check if record exists
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM attendance WHERE enrollment_id = ? AND attendance_date = ?");
    $stmt->execute([$enrollment_id, $date]);
    
    if ($stmt->fetchColumn() > 0) {
        $stmt = $pdo->prepare("UPDATE attendance SET status = ?, marked_by = ? WHERE enrollment_id = ? AND attendance_date = ?");
        $stmt->execute([$status, $_SESSION['user_id'], $enrollment_id, $date]);
    } else {
        $stmt = $pdo->prepare("INSERT INTO attendance (enrollment_id, attendance_date, status, marked_by) VALUES (?, ?, ?, ?)");
        $stmt->execute([$enrollment_id, $date, $status, $_SESSION['user_id']]);
    }
}

header('Location: ' . BASE_URL . 'modules/attendance/check.php?section_id=' . $section_id . '&date=' . $date . '&success=Attendance saved');
exit;
?>
