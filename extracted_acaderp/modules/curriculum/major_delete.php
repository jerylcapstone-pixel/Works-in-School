<?php
require_once __DIR__ . '/../../config/config.php';
requireAuth(ROLE_ADMIN);

$major_id = (int)($_GET['id'] ?? 0);
if (!$major_id) {
    header('Location: ' . BASE_URL . 'modules/curriculum/index.php?tab=majors&error=Invalid major ID');
    exit;
}

$pdo = getDBConnection();
$stmt = $pdo->prepare("DELETE FROM majors WHERE major_id = ?");
if ($stmt->execute([$major_id])) {
    header('Location: ' . BASE_URL . 'modules/curriculum/index.php?tab=majors&success=Major deleted successfully');
} else {
    header('Location: ' . BASE_URL . 'modules/curriculum/index.php?tab=majors&error=Error deleting major');
}
exit;

