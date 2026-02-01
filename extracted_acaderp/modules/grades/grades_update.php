<?php
require_once __DIR__ . '/../../config/config.php';
requireAuth([ROLE_FACULTY]);
$section_id = (int)($_POST['section_id'] ?? 0);
$grades = $_POST['grade'] ?? [];
$points = $_POST['points'] ?? [];

$pdo = getDBConnection();

// Verify faculty can only update grades for their own sections
$stmt = $pdo->prepare("SELECT f.faculty_id FROM class_sections cs
                       LEFT JOIN faculty f ON cs.faculty_id = f.faculty_id
                       WHERE cs.section_id = ? AND f.user_id = ?");
$stmt->execute([$section_id, $_SESSION['user_id']]);
$section = $stmt->fetch();
if (!$section) {
    header('Location: ' . BASE_URL . 'modules/grades/index.php?error=Access denied');
    exit;
}
foreach ($grades as $enrollment_id => $grade) {
    $enrollment_id = (int)$enrollment_id;
    $grade = sanitizeInput($grade);
    $pts = isset($points[$enrollment_id]) ? (float)$points[$enrollment_id] : null;
    
    $stmt = $pdo->prepare("UPDATE enrollments SET final_grade = ?, points = ? WHERE enrollment_id = ?");
    $stmt->execute([$grade ?: null, $pts, $enrollment_id]);
}

header('Location: ' . BASE_URL . 'modules/grades/index.php?section_id=' . $section_id . '&success=Grades updated');
exit;
?>
