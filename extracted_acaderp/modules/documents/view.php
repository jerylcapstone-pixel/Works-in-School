<?php
/**
 * View Document Request
 * Admin view to see request details, approve/reject, and upload documents
 */

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/feature_toggles.php';
requireAuth([ROLE_ADMIN]);

$page_title = 'View Document Request';
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

// Check if document_requests table exists
$table_exists = false;
try {
    $stmt = $pdo->query("SHOW TABLES LIKE 'document_requests'");
    $table_exists = $stmt->rowCount() > 0;
} catch (PDOException $e) {
    $table_exists = false;
}

if (!$table_exists) {
    header('Location: ' . BASE_URL . 'modules/documents/index.php?error=The document_requests table does not exist. Please run the migration: database/migration_document_requests.sql');
    exit;
}

// Get request details
try {
    $stmt = $pdo->prepare("SELECT dr.*, s.student_number, s.first_name, s.middle_name, s.last_name,
                          u_student.email as student_email,
                          u_requested.username as requested_by_username, 
                          u_approved.username as approved_by_username,
                          u_rejected.username as rejected_by_username, 
                          u_uploaded.username as uploaded_by_username
                          FROM document_requests dr
                          INNER JOIN students s ON dr.student_id = s.student_id
                          LEFT JOIN users u_student ON s.user_id = u_student.user_id
                          LEFT JOIN users u_requested ON dr.requested_by = u_requested.user_id
                          LEFT JOIN users u_approved ON dr.approved_by = u_approved.user_id
                          LEFT JOIN users u_rejected ON dr.rejected_by = u_rejected.user_id
                          LEFT JOIN users u_uploaded ON dr.uploaded_by = u_uploaded.user_id
                          WHERE dr.request_id = ?");
    $stmt->execute([$request_id]);
    $request = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    // Log the error for debugging
    error_log("Error fetching document request: " . $e->getMessage());
    error_log("SQL Error Code: " . $e->getCode());
    error_log("Request ID: " . $request_id);
    
    // Show the actual error message for debugging (you can remove this later)
    $error_message = 'Database error: ' . htmlspecialchars($e->getMessage());
    
    header('Location: ' . BASE_URL . 'modules/documents/index.php?error=' . urlencode($error_message));
    exit;
}

if (!$request) {
    header('Location: ' . BASE_URL . 'modules/documents/index.php?error=Request not found');
    exit;
}

// Ensure required fields exist with defaults
$request['status'] = $request['status'] ?? 'Pending';
$request['document_type'] = $request['document_type'] ?? 'Unknown';
$request['request_date'] = $request['request_date'] ?? date('Y-m-d H:i:s');
$request['student_number'] = $request['student_number'] ?? 'N/A';
$request['first_name'] = $request['first_name'] ?? '';
$request['middle_name'] = $request['middle_name'] ?? '';
$request['last_name'] = $request['last_name'] ?? '';
$request['student_email'] = $request['student_email'] ?? 'N/A';
$request['download_count'] = $request['download_count'] ?? 0;

include __DIR__ . '/../../includes/header.php';
?>

<div class="page-container">
    <div class="page-header">
        <h1><i class="fas fa-file-invoice"></i> Document Request Details</h1>
        <div class="header-actions">
            <a href="<?php echo BASE_URL; ?>modules/documents/index.php" class="btn btn-secondary">
                <i class="fas fa-arrow-left"></i> Back to List
            </a>
        </div>
    </div>

    <?php if (isset($_GET['error'])): ?>
        <div class="alert alert-error">
            <i class="fas fa-exclamation-circle"></i> <?php echo sanitizeOutput($_GET['error']); ?>
        </div>
    <?php endif; ?>

    <?php if (isset($_GET['success'])): ?>
        <div class="alert alert-success">
            <i class="fas fa-check-circle"></i> <?php echo sanitizeOutput($_GET['success']); ?>
        </div>
    <?php endif; ?>

    <div class="detail-cards">
        <!-- Request Header Card -->
        <div class="detail-card" style="background: linear-gradient(135deg, #2563eb 0%, #1e40af 100%); color: white;">
            <h3 style="color: white; border-bottom-color: rgba(255, 255, 255, 0.3); font-size: 1.75rem;">
                <i class="fas fa-file-alt"></i> <?php echo sanitizeOutput($request['document_type']); ?>
            </h3>
            <div class="detail-grid" style="margin-top: 1.5rem;">
                <div class="detail-item">
                    <label style="color: rgba(255, 255, 255, 0.9);">Status</label>
                    <span style="color: white; font-size: 1.1rem;">
                        <span class="badge badge-<?php 
                            echo $request['status'] === 'Pending' ? 'warning' : 
                                ($request['status'] === 'Approved' ? 'info' : 
                                ($request['status'] === 'Rejected' ? 'danger' : 
                                ($request['status'] === 'Ready for Download' ? 'success' : 'secondary'))); 
                        ?>">
                            <?php echo sanitizeOutput($request['status']); ?>
                        </span>
                    </span>
                </div>
                <div class="detail-item">
                    <label style="color: rgba(255, 255, 255, 0.9);">Request Date</label>
                    <span style="color: white; font-size: 1rem;">
                        <i class="fas fa-calendar-alt" style="margin-right: 0.5rem;"></i>
                        <?php echo date('F d, Y', strtotime($request['request_date'])); ?>
                    </span>
                </div>
                <div class="detail-item">
                    <label style="color: rgba(255, 255, 255, 0.9);">Request Time</label>
                    <span style="color: white; font-size: 1rem;">
                        <i class="fas fa-clock" style="margin-right: 0.5rem;"></i>
                        <?php echo date('g:i A', strtotime($request['request_date'])); ?>
                    </span>
                </div>
            </div>
        </div>

        <!-- Student Information -->
        <div class="detail-card">
            <h3><i class="fas fa-user-graduate"></i> Student Information</h3>
            <div class="detail-grid">
                <div class="detail-item">
                    <label>Student Number</label>
                    <span><strong><?php echo sanitizeOutput($request['student_number']); ?></strong></span>
                </div>
                <div class="detail-item">
                    <label>Full Name</label>
                    <span><?php echo sanitizeOutput($request['first_name'] . ' ' . ($request['middle_name'] ? $request['middle_name'] . ' ' : '') . $request['last_name']); ?></span>
                </div>
                <div class="detail-item">
                    <label>Email</label>
                    <span><?php echo sanitizeOutput($request['student_email'] ?? 'N/A'); ?></span>
                </div>
                <div class="detail-item">
                    <label>Document Type</label>
                    <span>
                        <span class="badge badge-info" style="font-size: 0.9rem; padding: 0.5rem 0.75rem;">
                            <?php echo sanitizeOutput($request['document_type']); ?>
                        </span>
                    </span>
                </div>
            </div>
        </div>

        <!-- Request Details -->
        <div class="detail-card">
            <h3><i class="fas fa-info-circle"></i> Request Details</h3>
            <div class="detail-grid">
                <div class="detail-item">
                    <label>Requested By</label>
                    <span><?php echo sanitizeOutput($request['requested_by_username'] ?? 'N/A'); ?></span>
                </div>
                <div class="detail-item">
                    <label>Request Date</label>
                    <span><?php echo date('F d, Y g:i A', strtotime($request['request_date'])); ?></span>
                </div>
                <?php if (!empty($request['approved_by_username'])): ?>
                <div class="detail-item">
                    <label>Approved By</label>
                    <span><strong style="color: #059669;"><?php echo sanitizeOutput($request['approved_by_username']); ?></strong></span>
                </div>
                <?php endif; ?>
                <?php if (!empty($request['approved_at'])): ?>
                <div class="detail-item">
                    <label>Approved At</label>
                    <span><?php echo date('F d, Y g:i A', strtotime($request['approved_at'])); ?></span>
                </div>
                <?php endif; ?>
                <?php if (!empty($request['uploaded_by_username'])): ?>
                <div class="detail-item">
                    <label>Uploaded By</label>
                    <span><strong style="color: #2563eb;"><?php echo sanitizeOutput($request['uploaded_by_username']); ?></strong></span>
                </div>
                <?php endif; ?>
                <?php if (!empty($request['uploaded_at'])): ?>
                <div class="detail-item">
                    <label>Uploaded At</label>
                    <span><?php echo date('F d, Y g:i A', strtotime($request['uploaded_at'])); ?></span>
                </div>
                <?php endif; ?>
                <?php if ($request['status'] === 'Ready for Download' && !empty($request['file_path'])): ?>
                <div class="detail-item">
                    <label>Download Count</label>
                    <span>
                        <i class="fas fa-download" style="color: #2563eb;"></i> 
                        <strong><?php echo (int)$request['download_count']; ?></strong> time(s)
                    </span>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Notes Section -->
        <?php if (!empty($request['notes']) || !empty($request['rejection_reason']) || !empty($request['admin_notes'])): ?>
        <div class="detail-card">
            <h3><i class="fas fa-sticky-note"></i> Notes & Comments</h3>
            
            <?php if (!empty($request['notes'])): ?>
            <div style="margin-bottom: 1.5rem;">
                <label style="display: block; font-weight: 600; color: #475569; margin-bottom: 0.75rem; font-size: 0.875rem; text-transform: uppercase; letter-spacing: 0.05em;">
                    <i class="fas fa-comment" style="color: #2563eb;"></i> Student Notes
                </label>
                <div style="background: #f8fafc; padding: 1.25rem; border-radius: 8px; border-left: 4px solid #2563eb; line-height: 1.7; color: #475569;">
                    <?php echo nl2br(sanitizeOutput($request['notes'])); ?>
                </div>
            </div>
            <?php endif; ?>

            <?php if (!empty($request['rejection_reason'])): ?>
            <div style="margin-bottom: 1.5rem;">
                <label style="display: block; font-weight: 600; color: #475569; margin-bottom: 0.75rem; font-size: 0.875rem; text-transform: uppercase; letter-spacing: 0.05em;">
                    <i class="fas fa-times-circle" style="color: #dc2626;"></i> Rejection Reason
                </label>
                <div style="background: #fef2f2; padding: 1.25rem; border-radius: 8px; border-left: 4px solid #dc2626; line-height: 1.7; color: #991b1b;">
                    <?php echo nl2br(sanitizeOutput($request['rejection_reason'])); ?>
                </div>
            </div>
            <?php endif; ?>

            <?php if (!empty($request['admin_notes'])): ?>
            <div>
                <label style="display: block; font-weight: 600; color: #475569; margin-bottom: 0.75rem; font-size: 0.875rem; text-transform: uppercase; letter-spacing: 0.05em;">
                    <i class="fas fa-user-shield" style="color: #f59e0b;"></i> Admin Notes
                </label>
                <div style="background: #fffbeb; padding: 1.25rem; border-radius: 8px; border-left: 4px solid #f59e0b; line-height: 1.7; color: #78350f;">
                    <?php echo nl2br(sanitizeOutput($request['admin_notes'])); ?>
                </div>
            </div>
            <?php endif; ?>
        </div>
        <?php endif; ?>

        <!-- Actions Section -->
        <div class="detail-card">
            <h3><i class="fas fa-cog"></i> Actions</h3>
            
            <?php if ($request['status'] === 'Pending'): ?>
                <div style="display: flex; gap: 1rem; flex-wrap: wrap; margin-top: 1rem;">
                    <a href="<?php echo BASE_URL; ?>modules/documents/approve.php?id=<?php echo $request_id; ?>" 
                       class="btn btn-success"
                       style="flex: 1; min-width: 200px; display: flex; align-items: center; justify-content: center; gap: 0.5rem; padding: 1rem;"
                       onclick="return confirm('Are you sure you want to approve this request?');">
                        <i class="fas fa-check-circle"></i> Approve Request
                    </a>
                    <a href="<?php echo BASE_URL; ?>modules/documents/reject.php?id=<?php echo $request_id; ?>" 
                       class="btn btn-danger"
                       style="flex: 1; min-width: 200px; display: flex; align-items: center; justify-content: center; gap: 0.5rem; padding: 1rem;">
                        <i class="fas fa-times-circle"></i> Reject Request
                    </a>
                </div>
            <?php endif; ?>

            <?php if ($request['status'] === 'Approved'): ?>
                <div class="form-card" style="background: linear-gradient(135deg, #dbeafe 0%, #bfdbfe 100%); border: 2px solid #2563eb; border-left: 4px solid #2563eb; margin-top: 1rem;">
                    <h4 style="margin-top: 0; margin-bottom: 1.5rem; color: #1e40af; display: flex; align-items: center; gap: 0.5rem;">
                        <i class="fas fa-upload" style="color: #2563eb;"></i> Upload Document
                    </h4>
                    <form method="POST" action="<?php echo BASE_URL; ?>modules/documents/upload.php" enctype="multipart/form-data" class="form">
                        <input type="hidden" name="request_id" value="<?php echo $request_id; ?>">
                        <div class="form-group">
                            <label for="file">
                                <i class="fas fa-file-pdf" style="color: #dc2626;"></i> Document File (PDF only) <span class="required">*</span>
                            </label>
                            <input type="file" id="file" name="file" required class="form-control" accept=".pdf"
                                   style="font-size: 1rem; padding: 0.75rem;">
                            <small class="text-muted" style="display: block; margin-top: 0.5rem; color: #64748b;">
                                <i class="fas fa-info-circle"></i> Maximum file size: 10MB. Only PDF files are allowed.
                            </small>
                        </div>
                        <div class="form-group">
                            <label for="admin_notes">
                                <i class="fas fa-sticky-note" style="color: #2563eb;"></i> Admin Notes (Optional)
                            </label>
                            <textarea id="admin_notes" name="admin_notes" class="form-control" rows="4"
                                      placeholder="Add any notes about this document"
                                      style="font-size: 1rem; padding: 0.75rem;"><?php echo sanitizeOutput($request['admin_notes'] ?? ''); ?></textarea>
                        </div>
                        <div class="form-actions" style="margin-top: 1.5rem; padding-top: 1.5rem; border-top: 2px solid #e2e8f0;">
                            <button type="submit" class="btn btn-primary" style="min-width: 180px;">
                                <i class="fas fa-upload"></i> Upload Document
                            </button>
                        </div>
                    </form>
                </div>
            <?php endif; ?>

            <?php if ($request['status'] === 'Ready for Download' && !empty($request['file_path'])): ?>
                <div class="detail-card" style="background: #f0fdf4; border: 2px solid #10b981; border-left: 4px solid #10b981; margin-top: 1rem;">
                    <h4 style="margin-top: 0; margin-bottom: 1rem; color: #065f46; display: flex; align-items: center; gap: 0.5rem;">
                        <i class="fas fa-check-circle" style="color: #10b981;"></i> Document Ready
                    </h4>
                    <div class="detail-grid" style="margin-bottom: 1.5rem;">
                        <div class="detail-item">
                            <label>File Name</label>
                            <span><strong><?php echo sanitizeOutput($request['file_name'] ?? 'N/A'); ?></strong></span>
                        </div>
                        <?php if (!empty($request['uploaded_at'])): ?>
                        <div class="detail-item">
                            <label>Uploaded</label>
                            <span><?php echo date('F d, Y g:i A', strtotime($request['uploaded_at'])); ?></span>
                        </div>
                        <?php endif; ?>
                        <div class="detail-item">
                            <label>Downloads</label>
                            <span>
                                <i class="fas fa-download" style="color: #2563eb;"></i> 
                                <strong><?php echo (int)($request['download_count'] ?? 0); ?></strong>
                            </span>
                        </div>
                    </div>
                    <div style="display: flex; gap: 1rem; flex-wrap: wrap; margin-top: 1.5rem;">
                        <a href="<?php echo BASE_URL; ?>modules/documents/download.php?id=<?php echo $request_id; ?>&admin=1" 
                           class="btn btn-primary"
                           style="flex: 1; min-width: 200px; display: flex; align-items: center; justify-content: center; gap: 0.5rem; padding: 1rem;">
                            <i class="fas fa-download"></i> Download Document
                        </a>
                        <a href="<?php echo BASE_URL; ?>modules/documents/preview.php?id=<?php echo $request_id; ?>&admin=1" 
                           class="btn btn-info"
                           target="_blank"
                           style="flex: 1; min-width: 200px; display: flex; align-items: center; justify-content: center; gap: 0.5rem; padding: 1rem;">
                            <i class="fas fa-eye"></i> Preview Document
                        </a>
                    </div>
                    <div style="margin-top: 1rem; padding: 1rem; background: #eff6ff; border-radius: 8px; border-left: 4px solid #2563eb;">
                        <p style="margin: 0; color: #1e40af; font-size: 0.9rem;">
                            <i class="fas fa-info-circle"></i> 
                            <strong>Note:</strong> Downloading or previewing this document will increment the download count.
                        </p>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../../includes/footer.php'; ?>

