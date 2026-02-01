<?php
/**
 * My Document Requests
 * Students can view their document requests and download approved documents
 */

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/feature_toggles.php';
requireAuth([ROLE_STUDENT]);

$page_title = 'My Document Requests';
$pdo = getDBConnection();

// Check if document requests feature is enabled
if (!isDocumentRequestsEnabled($pdo)) {
    header('Location: ' . BASE_URL . 'dashboard/index.php?error=The Document Request feature is currently disabled');
    exit;
}
$error = '';
$success = '';

// Get student ID
$student_id = null;
$stmt = $pdo->prepare("SELECT student_id FROM students WHERE user_id = ?");
$stmt->execute([$_SESSION['user_id']]);
$student = $stmt->fetch();
if ($student) {
    $student_id = $student['student_id'];
}

if (!$student_id) {
    $error = 'Student record not found.';
}

// Get all requests for this student
$requests = [];
if ($student_id) {
    try {
        $stmt = $pdo->prepare("SELECT dr.*, u.username as approved_by_username, u2.username as rejected_by_username, u3.username as uploaded_by_username
                              FROM document_requests dr
                              LEFT JOIN users u ON dr.approved_by = u.user_id
                              LEFT JOIN users u2 ON dr.rejected_by = u2.user_id
                              LEFT JOIN users u3 ON dr.uploaded_by = u3.user_id
                              WHERE dr.student_id = ?
                              ORDER BY dr.request_date DESC");
        $stmt->execute([$student_id]);
        $requests = $stmt->fetchAll();
    } catch (PDOException $e) {
        $error = 'Error loading requests: ' . $e->getMessage();
    }
}

include __DIR__ . '/../../includes/header.php';
?>

<div class="page-container">
    <div class="page-header">
        <h1><i class="fas fa-file-alt"></i> My Document Requests</h1>
        <a href="<?php echo BASE_URL; ?>modules/documents/request.php" class="btn btn-primary">
            <i class="fas fa-plus"></i> New Request
        </a>
    </div>

    <?php if ($error): ?>
        <div class="alert alert-error">
            <i class="fas fa-exclamation-circle"></i> <?php echo sanitizeOutput($error); ?>
        </div>
    <?php endif; ?>

    <?php if (isset($_GET['success'])): ?>
        <div class="alert alert-success">
            <i class="fas fa-check-circle"></i> <?php echo sanitizeOutput($_GET['success']); ?>
        </div>
    <?php endif; ?>

    <?php if ($student_id): ?>
    <div class="card">
        <h2>My Requests (<?php echo count($requests); ?>)</h2>
        <?php if (empty($requests)): ?>
            <p class="text-center" style="padding: 2rem;">You have not submitted any document requests yet. <a href="<?php echo BASE_URL; ?>modules/documents/request.php">Submit your first request</a></p>
        <?php else: ?>
            <div style="overflow-x: auto;">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Document Type</th>
                            <th>Request Date</th>
                            <th>Status</th>
                            <th>Approved/Rejected By</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($requests as $request): 
                            $status_badge = [
                                'Pending' => 'warning',
                                'Approved' => 'info',
                                'Rejected' => 'danger',
                                'Ready for Download' => 'success',
                                'Completed' => 'secondary'
                            ];
                            $badge = $status_badge[$request['status']] ?? 'secondary';
                        ?>
                        <tr>
                            <td><strong><?php echo sanitizeOutput($request['document_type']); ?></strong></td>
                            <td><?php echo date('M d, Y g:i A', strtotime($request['request_date'])); ?></td>
                            <td>
                                <span class="badge badge-<?php echo $badge; ?>">
                                    <?php echo sanitizeOutput($request['status']); ?>
                                </span>
                            </td>
                            <td>
                                <?php if ($request['approved_by_username']): ?>
                                    <?php echo sanitizeOutput($request['approved_by_username']); ?>
                                    <br><small class="text-muted"><?php echo date('M d, Y', strtotime($request['approved_at'])); ?></small>
                                <?php elseif ($request['rejected_by_username']): ?>
                                    <?php echo sanitizeOutput($request['rejected_by_username']); ?>
                                    <br><small class="text-muted"><?php echo date('M d, Y', strtotime($request['rejected_at'])); ?></small>
                                <?php else: ?>
                                    <span class="text-muted">-</span>
                                <?php endif; ?>
                            </td>
                            <td class="actions">
                                <a href="<?php echo BASE_URL; ?>modules/documents/view_request.php?id=<?php echo $request['request_id']; ?>" 
                                   class="btn btn-sm btn-info" title="View Details">
                                    <i class="fas fa-eye"></i>
                                </a>
                                <?php if ($request['status'] === 'Ready for Download' && !empty($request['file_path'])): ?>
                                <a href="<?php echo BASE_URL; ?>modules/documents/download.php?id=<?php echo $request['request_id']; ?>" 
                                   class="btn btn-sm btn-success" title="Download">
                                    <i class="fas fa-download"></i>
                                </a>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
    <?php endif; ?>
</div>

<?php include __DIR__ . '/../../includes/footer.php'; ?>

