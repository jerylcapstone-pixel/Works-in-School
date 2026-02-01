<?php
require_once __DIR__ . '/../../config/config.php';
requireAuth(ROLE_ADMIN);
$course_id = (int)($_GET['id'] ?? 0);
if (!$course_id) {
    header('Location: ' . BASE_URL . 'modules/curriculum/index.php?tab=courses&error=Invalid course ID');
    exit;
}

$pdo = getDBConnection();
$stmt = $pdo->prepare("SELECT COUNT(*) FROM class_sections WHERE course_id = ?");
$stmt->execute([$course_id]);
if ($stmt->fetchColumn() > 0) {
    header('Location: ' . BASE_URL . 'modules/curriculum/index.php?tab=courses&error=Cannot delete course with existing sections.');
    exit;
}

$stmt = $pdo->prepare("DELETE FROM courses WHERE course_id = ?");
if ($stmt->execute([$course_id])) {
    header('Location: ' . BASE_URL . 'modules/curriculum/index.php?tab=courses&success=Course deleted successfully');
    exit;
} else {
    header('Location: ' . BASE_URL . 'modules/curriculum/index.php?tab=courses&error=Error deleting course');
    exit;
}
?>
