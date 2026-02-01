<?php
/**
 * Reject Document Request
 * Handles rejection of document requests
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

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $rejection_reason = sanitizeInput($_POST['rejection_reason'] ?? '');
    
    if (empty($rejection_reason)) {
        header('Location: ' . BASE_URL . 'modules/documents/view.php?id=' . $request_id . '&error=Rejection reason is required');
        exit;
    }
    
    $stmt = $pdo->prepare("UPDATE document_requests SET status = 'Rejected', rejected_by = ?, rejected_at = NOW(), rejection_reason = ? WHERE request_id = ?");
    if ($stmt->execute([$_SESSION['user_id'], $rejection_reason, $request_id])) {
        header('Location: ' . BASE_URL . 'modules/documents/view.php?id=' . $request_id . '&success=Request rejected');
        exit;
    } else {
        header('Location: ' . BASE_URL . 'modules/documents/view.php?id=' . $request_id . '&error=Failed to reject request');
        exit;
    }
}

// Show rejection form
$page_title = 'Reject Document Request';
include __DIR__ . '/../../includes/header.php';
?>

<div class="page-container">
    <div class="page-header">
        <h1><i class="fas fa-times-circle"></i> Reject Document Request</h1>
        <a href="<?php echo BASE_URL; ?>modules/documents/view.php?id=<?php echo $request_id; ?>" class="btn btn-secondary">
            <i class="fas fa-arrow-left"></i> Back
        </a>
    </div>

    <div class="card">
        <form method="POST" action="" class="form">
            <div class="form-group">
                <label for="rejection_reason">Rejection Reason <span class="required">*</span></label>
                <textarea id="rejection_reason" name="rejection_reason" required class="form-control" rows="5"
                          placeholder="Please provide a reason for rejecting this request"></textarea>
            </div>

            <div class="form-actions">
                <button type="submit" class="btn btn-danger">
                    <i class="fas fa-times"></i> Reject Request
                </button>
                <a href="<?php echo BASE_URL; ?>modules/documents/view.php?id=<?php echo $request_id; ?>" class="btn btn-secondary">
                    <i class="fas fa-times"></i> Cancel
                </a>
            </div>
        </form>
    </div>
</div>

<?php include __DIR__ . '/../../includes/footer.php'; ?>

