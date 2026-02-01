<?php
require_once __DIR__ . '/../../config/config.php';
requireAuth(ROLE_ADMIN);
$prog_id = (int)($_GET['id'] ?? 0);
if (!$prog_id) {
    header('Location: ' . BASE_URL . 'modules/curriculum/index.php?tab=programs&error=Invalid program ID');
    exit;
}

$pdo = getDBConnection();
$stmt = $pdo->prepare("SELECT COUNT(*) FROM students WHERE program_id = ?");
$stmt->execute([$prog_id]);
if ($stmt->fetchColumn() > 0) {
    header('Location: ' . BASE_URL . 'modules/curriculum/index.php?tab=programs&error=Cannot delete program with enrolled students.');
    exit;
}

$stmt = $pdo->prepare("DELETE FROM programs WHERE program_id = ?");
if ($stmt->execute([$prog_id])) {
    header('Location: ' . BASE_URL . 'modules/curriculum/index.php?tab=programs&success=Program deleted successfully');
    exit;
} else {
    header('Location: ' . BASE_URL . 'modules/curriculum/index.php?tab=programs&error=Error deleting program');
    exit;
}
?>
