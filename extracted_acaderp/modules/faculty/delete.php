<?php
/**
 * Faculty & Staff Management - Delete Faculty
 * Handles deletion of faculty member
 */

require_once __DIR__ . '/../../config/config.php';
requireAuth(ROLE_ADMIN);

$faculty_id = (int)($_GET['id'] ?? 0);
if (!$faculty_id) {
    header('Location: ' . BASE_URL . 'modules/faculty/index.php?error=Invalid faculty ID');
    exit;
}

$pdo = getDBConnection();

// Check if faculty is assigned as advisor to any students
$stmt = $pdo->prepare("SELECT COUNT(*) FROM students WHERE advisor_id = ?");
$stmt->execute([$faculty_id]);
$advisor_count = $stmt->fetchColumn();

// Check if faculty is teaching any sections
$stmt = $pdo->prepare("SELECT COUNT(*) FROM class_sections WHERE faculty_id = ?");
$stmt->execute([$faculty_id]);
$sections_count = $stmt->fetchColumn();

if ($advisor_count > 0 || $sections_count > 0) {
    header('Location: ' . BASE_URL . 'modules/faculty/index.php?error=Cannot delete faculty member who is assigned as advisor or teaching sections. Please reassign or remove assignments first.');
    exit;
}

$stmt = $pdo->prepare("DELETE FROM faculty WHERE faculty_id = ?");
if ($stmt->execute([$faculty_id])) {
    header('Location: ' . BASE_URL . 'modules/faculty/index.php?success=Faculty member deleted successfully');
    exit;
} else {
    header('Location: ' . BASE_URL . 'modules/faculty/index.php?error=Error deleting faculty member');
    exit;
}
?>
