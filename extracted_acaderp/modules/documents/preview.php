<?php
/**
 * Preview Document
 * Handles secure document preview for students and admins
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
    header('Location: ' . BASE_URL . ($is_admin ? 'modules/documents/view.php?id=' . $request_id : 'modules/documents/my_requests.php') . '?error=Document is not ready for preview');
    exit;
}

if (empty($request['file_path']) || !file_exists($request['file_path'])) {
    header('Location: ' . BASE_URL . ($is_admin ? 'modules/documents/view.php?id=' . $request_id : 'modules/documents/my_requests.php') . '&error=File not found');
    exit;
}

// Check if file is PDF
$file_extension = strtolower(pathinfo($request['file_path'], PATHINFO_EXTENSION));
if ($file_extension !== 'pdf') {
    header('Location: ' . BASE_URL . ($is_admin ? 'modules/documents/view.php?id=' . $request_id : 'modules/documents/my_requests.php') . '&error=Preview is only available for PDF files');
    exit;
}

// Increment download count (for tracking purposes)
try {
    $stmt = $pdo->prepare("UPDATE document_requests SET download_count = download_count + 1 WHERE request_id = ?");
    $stmt->execute([$request_id]);
} catch (PDOException $e) {
    // Continue even if update fails
}

$page_title = 'Preview Document';
include __DIR__ . '/../../includes/header.php';
?>

<style>
.preview-container {
    width: 100%;
    height: calc(100vh - 200px);
    min-height: 600px;
    border: 2px solid #e2e8f0;
    border-radius: 12px;
    overflow: hidden;
    background: #f8fafc;
}

.preview-iframe {
    width: 100%;
    height: 100%;
    border: none;
}

.preview-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 1rem 1.5rem;
    background: white;
    border-bottom: 2px solid #e2e8f0;
    margin-bottom: 1rem;
    border-radius: 12px;
}

.preview-header h3 {
    margin: 0;
    color: #1e293b;
    font-size: 1.25rem;
}

.preview-actions {
    display: flex;
    gap: 1rem;
}
</style>

<div class="page-container">
    <div class="preview-header">
        <h3><i class="fas fa-file-pdf" style="color: #dc2626;"></i> <?php echo sanitizeOutput($request['file_name']); ?></h3>
        <div class="preview-actions">
            <a href="<?php echo BASE_URL; ?>modules/documents/download.php?id=<?php echo $request_id; ?><?php echo $is_admin ? '&admin=1' : ''; ?>" 
               class="btn btn-primary">
                <i class="fas fa-download"></i> Download
            </a>
            <a href="<?php echo BASE_URL; ?>modules/documents/<?php echo $is_admin ? 'view.php?id=' . $request_id : 'my_requests.php'; ?>" 
               class="btn btn-secondary">
                <i class="fas fa-arrow-left"></i> Back
            </a>
        </div>
    </div>

    <div class="preview-container">
        <iframe src="<?php echo BASE_URL . $request['file_path']; ?>#toolbar=1&navpanes=1&scrollbar=1" 
                class="preview-iframe"
                title="Document Preview">
            <p>Your browser does not support PDF preview. 
               <a href="<?php echo BASE_URL; ?>modules/documents/download.php?id=<?php echo $request_id; ?><?php echo $is_admin ? '&admin=1' : ''; ?>">Download the document</a> instead.
            </p>
        </iframe>
    </div>
</div>

<?php include __DIR__ . '/../../includes/footer.php'; ?>

