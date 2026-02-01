<?php
/**
 * Download Document
 * Handles secure document downloads for students and admins
 */

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/feature_toggles.php';
requireAuth([ROLE_STUDENT, ROLE_ADMIN]);

$pdo = getDBConnection();
$request_id = (int)($_GET['id'] ?? 0);
$is_admin = isset($_GET['admin']) && $_GET['admin'] == '1' && hasRole(ROLE_ADMIN);

if (!$request_id) {
    header('Location: ' . BASE_URL . 'dashboard/index.php?error=Invalid request ID');
    exit;
}

// Check if document requests feature is enabled
if (!isDocumentRequestsEnabled($pdo)) {
    header('Location: ' . BASE_URL . 'dashboard/index.php?error=The Document Request feature is currently disabled');
    exit;
}

// Get request details
$stmt = $pdo->prepare("SELECT dr.*, s.student_number, s.user_id as student_user_id
                      FROM document_requests dr
                      INNER JOIN students s ON dr.student_id = s.student_id
                      WHERE dr.request_id = ?");
$stmt->execute([$request_id]);
$request = $stmt->fetch();

if (!$request) {
    header('Location: ' . BASE_URL . 'dashboard/index.php?error=Request not found');
    exit;
}

// Check access permissions
if (hasRole(ROLE_STUDENT)) {
    if ($request['student_user_id'] != $_SESSION['user_id']) {
        header('Location: ' . BASE_URL . 'modules/documents/my_requests.php?error=Access denied');
        exit;
    }
}

if ($request['status'] !== 'Ready for Download' && $request['status'] !== 'Completed') {
    header('Location: ' . BASE_URL . ($is_admin ? 'modules/documents/view.php?id=' . $request_id : 'modules/documents/my_requests.php') . '?error=Document is not ready for download');
    exit;
}

if (empty($request['file_path']) || !file_exists($request['file_path'])) {
    header('Location: ' . BASE_URL . ($is_admin ? 'modules/documents/view.php?id=' . $request_id : 'modules/documents/my_requests.php') . '&error=File not found');
    exit;
}

// Increment download count
try {
    $stmt = $pdo->prepare("UPDATE document_requests SET download_count = download_count + 1 WHERE request_id = ?");
    $stmt->execute([$request_id]);
} catch (PDOException $e) {
    // Continue even if update fails
}

// Get original filename
$original_filename = $request['file_name'] ?: 'document_' . $request_id . '.pdf';
$original_filename = preg_replace('/[^a-zA-Z0-9._-]/', '_', $original_filename);

// Set headers for download
header('Content-Type: application/pdf');
header('Content-Disposition: attachment; filename="' . $original_filename . '"');
header('Content-Length: ' . filesize($request['file_path']));
header('Cache-Control: must-revalidate');
header('Pragma: public');

// Output file
readfile($request['file_path']);
exit;

