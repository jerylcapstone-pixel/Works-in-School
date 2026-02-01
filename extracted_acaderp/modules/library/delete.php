<?php
/**
 * Delete Library Material
 * Handles deletion of library materials and files
 */

require_once __DIR__ . '/../../config/config.php';
requireAuth([ROLE_ADMIN, ROLE_FACULTY]);

$pdo = getDBConnection();
$material_id = (int)($_GET['id'] ?? 0);

if (!$material_id) {
    header('Location: ' . BASE_URL . 'modules/library/index.php?error=Invalid material ID');
    exit;
}

// Get material details
$stmt = $pdo->prepare("SELECT * FROM library_materials WHERE material_id = ?");
$stmt->execute([$material_id]);
$material = $stmt->fetch();

if (!$material) {
    header('Location: ' . BASE_URL . 'modules/library/index.php?error=Material not found');
    exit;
}

// Delete file
if (file_exists($material['file_path'])) {
    unlink($material['file_path']);
}

// Delete from database
$stmt = $pdo->prepare("DELETE FROM library_materials WHERE material_id = ?");
if ($stmt->execute([$material_id])) {
    header('Location: ' . BASE_URL . 'modules/library/index.php?success=Material deleted successfully');
    exit;
} else {
    header('Location: ' . BASE_URL . 'modules/library/index.php?error=Failed to delete material');
    exit;
}

