<?php
/**
 * Approve Document Request
 * Handles approval of document requests
 */

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/feature_toggles.php';
requireAuth([ROLE_ADMIN]);

$pdo = getDBConnection();
$request_id = (int)($_GET['id'] ?? 0);

if (!$request_id) {
    header('Location: ' . BASE_URL . 'modules/documents/index.php?error=Invalid request ID');
    exit;
}

// Check if document requests feature is enabled
if (!isDocumentRequestsEnabled($pdo)) {
    header('Location: ' . BASE_URL . 'dashboard/index.php?error=The Document Request feature is currently disabled');
    exit;
}

// Get request details
$stmt = $pdo->prepare("SELECT * FROM document_requests WHERE request_id = ?");
$stmt->execute([$request_id]);
$request = $stmt->fetch();

if (!$request) {
    header('Location: ' . BASE_URL . 'modules/documents/index.php?error=Request not found');
    exit;
}

if ($request['status'] !== 'Pending') {
    header('Location: ' . BASE_URL . 'modules/documents/view.php?id=' . $request_id . '&error=Request is not pending');
    exit;
}

// Approve request
$stmt = $pdo->prepare("UPDATE document_requests SET status = 'Approved', approved_by = ?, approved_at = NOW() WHERE request_id = ?");
if ($stmt->execute([$_SESSION['user_id'], $request_id])) {
    header('Location: ' . BASE_URL . 'modules/documents/view.php?id=' . $request_id . '&success=Request approved successfully');
    exit;
} else {
    header('Location: ' . BASE_URL . 'modules/documents/view.php?id=' . $request_id . '&error=Failed to approve request');
    exit;
}

