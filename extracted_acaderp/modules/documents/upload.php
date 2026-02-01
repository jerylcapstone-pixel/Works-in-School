<?php
/**
 * Upload Document
 * Handles file upload for approved document requests
 */

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/feature_toggles.php';
requireAuth([ROLE_ADMIN]);

$pdo = getDBConnection();
$request_id = (int)($_POST['request_id'] ?? 0);

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

if ($request['status'] !== 'Approved') {
    header('Location: ' . BASE_URL . 'modules/documents/view.php?id=' . $request_id . '&error=Request must be approved before uploading');
    exit;
}

// Create uploads directory if it doesn't exist
$upload_dir = __DIR__ . '/../../uploads/documents/';
if (!file_exists($upload_dir)) {
    mkdir($upload_dir, 0755, true);
}

// Handle file upload
if (isset($_FILES['file']) && $_FILES['file']['error'] === UPLOAD_ERR_OK) {
    $uploaded_file = $_FILES['file'];
    $file_name = $uploaded_file['name'];
    $file_size = $uploaded_file['size'];
    $tmp_name = $uploaded_file['tmp_name'];
    
    // Validate file type (PDF only)
    $file_extension = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
    if ($file_extension !== 'pdf') {
        header('Location: ' . BASE_URL . 'modules/documents/view.php?id=' . $request_id . '&error=Only PDF files are allowed');
        exit;
    }
    
    // Validate file size (10MB limit)
    if ($file_size > 10 * 1024 * 1024) {
        header('Location: ' . BASE_URL . 'modules/documents/view.php?id=' . $request_id . '&error=File size exceeds 10MB limit');
        exit;
    }
    
    // Generate unique filename
    $unique_name = 'doc_' . $request_id . '_' . uniqid() . '_' . time() . '.pdf';
    $file_path = $upload_dir . $unique_name;
    
    if (move_uploaded_file($tmp_name, $file_path)) {
        $admin_notes = sanitizeInput($_POST['admin_notes'] ?? '');
        
        // Update request
        $stmt = $pdo->prepare("UPDATE document_requests SET status = 'Ready for Download', 
                              file_path = ?, file_name = ?, uploaded_at = NOW(), uploaded_by = ?, admin_notes = ?
                              WHERE request_id = ?");
        if ($stmt->execute([$file_path, $file_name, $_SESSION['user_id'], $admin_notes, $request_id])) {
            header('Location: ' . BASE_URL . 'modules/documents/view.php?id=' . $request_id . '&success=Document uploaded successfully');
            exit;
        } else {
            // Delete uploaded file on error
            if (file_exists($file_path)) {
                unlink($file_path);
            }
            header('Location: ' . BASE_URL . 'modules/documents/view.php?id=' . $request_id . '&error=Failed to save document information');
            exit;
        }
    } else {
        header('Location: ' . BASE_URL . 'modules/documents/view.php?id=' . $request_id . '&error=Failed to upload file');
        exit;
    }
} else {
    header('Location: ' . BASE_URL . 'modules/documents/view.php?id=' . $request_id . '&error=No file uploaded or upload error occurred');
    exit;
}

