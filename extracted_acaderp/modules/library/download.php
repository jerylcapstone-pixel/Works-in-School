<?php
/**
 * Download Library Material
 * Handles file downloads and tracks download count
 */

require_once __DIR__ . '/../../config/config.php';
requireAuth([ROLE_ADMIN, ROLE_FACULTY, ROLE_STUDENT]);

$pdo = getDBConnection();
$material_id = (int)($_GET['id'] ?? 0);

if (!$material_id) {
    header('Location: ' . BASE_URL . 'modules/library/student_portal.php?error=Invalid material ID');
    exit;
}

// Get material details
$stmt = $pdo->prepare("SELECT * FROM library_materials WHERE material_id = ? AND is_active = 1");
$stmt->execute([$material_id]);
$material = $stmt->fetch();

if (!$material) {
    header('Location: ' . BASE_URL . 'modules/library/student_portal.php?error=Material not found or not available');
    exit;
}

// Check access permissions
$user_role = $_SESSION['user_role'];
$has_access = false;

if ($material['access_level'] === 'Public') {
    $has_access = true;
} elseif ($material['access_level'] === 'Students' && $user_role === ROLE_STUDENT) {
    $has_access = true;
} elseif ($material['access_level'] === 'Faculty' && ($user_role === ROLE_FACULTY || $user_role === ROLE_ADMIN)) {
    $has_access = true;
} elseif ($material['access_level'] === 'Restricted' && ($user_role === ROLE_ADMIN || $user_role === ROLE_FACULTY)) {
    $has_access = true;
}

if (!$has_access) {
    header('Location: ' . BASE_URL . 'modules/library/student_portal.php?error=Access denied');
    exit;
}

// Check if file exists
if (!file_exists($material['file_path'])) {
    header('Location: ' . BASE_URL . 'modules/library/student_portal.php?error=File not found');
    exit;
}

// Increment download count
try {
    $stmt = $pdo->prepare("UPDATE library_materials SET download_count = download_count + 1 WHERE material_id = ?");
    $stmt->execute([$material_id]);
} catch (PDOException $e) {
    // Continue even if update fails
}

// Get original filename (stored separately or reconstruct from title)
$original_filename = $material['title'] . '.' . $material['file_extension'];
$original_filename = preg_replace('/[^a-zA-Z0-9._-]/', '_', $original_filename);

// Set headers for download
header('Content-Type: ' . $material['file_type']);
header('Content-Disposition: attachment; filename="' . $original_filename . '"');
header('Content-Length: ' . $material['file_size']);
header('Cache-Control: must-revalidate');
header('Pragma: public');

// Output file
readfile($material['file_path']);
exit;

