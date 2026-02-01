<?php
/**
 * Curriculum Management - Delete Department
 */

require_once __DIR__ . '/../../config/config.php';
requireAuth(ROLE_ADMIN);

$dept_id = (int)($_GET['id'] ?? 0);
if (!$dept_id) {
    header('Location: ' . BASE_URL . 'modules/curriculum/index.php?tab=departments&error=Invalid department ID');
    exit;
}

$pdo = getDBConnection();

// Check if department has programs or courses
$stmt = $pdo->prepare("SELECT COUNT(*) FROM programs WHERE department_id = ?");
$stmt->execute([$dept_id]);
$programs_count = $stmt->fetchColumn();

$stmt = $pdo->prepare("SELECT COUNT(*) FROM courses WHERE department_id = ?");
$stmt->execute([$dept_id]);
$courses_count = $stmt->fetchColumn();

if ($programs_count > 0 || $courses_count > 0) {
    header('Location: ' . BASE_URL . 'modules/curriculum/index.php?tab=departments&error=Cannot delete department with existing programs or courses. Please remove them first.');
    exit;
}

$stmt = $pdo->prepare("DELETE FROM departments WHERE department_id = ?");
if ($stmt->execute([$dept_id])) {
    header('Location: ' . BASE_URL . 'modules/curriculum/index.php?tab=departments&success=Department deleted successfully');
    exit;
} else {
    header('Location: ' . BASE_URL . 'modules/curriculum/index.php?tab=departments&error=Error deleting department');
    exit;
}
?>
