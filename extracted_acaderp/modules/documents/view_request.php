<?php
/**
 * View My Document Request (Student View)
 * Students can view details of their own document requests
 */

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/feature_toggles.php';
requireAuth([ROLE_STUDENT]);

$page_title = 'View Document Request';
$pdo = getDBConnection();
$error = '';
$success = '';
$request_id = (int)($_GET['id'] ?? 0);

if (!$request_id) {
    header('Location: ' . BASE_URL . 'modules/documents/my_requests.php?error=Invalid request ID');
    exit;
}

// Check if document requests feature is enabled
if (!isDocumentRequestsEnabled($pdo)) {
    header('Location: ' . BASE_URL . 'dashboard/index.php?error=The Document Request feature is currently disabled');
    exit;
}

// Get student ID
$student_id = null;
$stmt = $pdo->prepare("SELECT student_id FROM students WHERE user_id = ?");
$stmt->execute([$_SESSION['user_id']]);
$student = $stmt->fetch();
if ($student) {
    $student_id = $student['student_id'];
}

if (!$student_id) {
    header('Location: ' . BASE_URL . 'modules/documents/my_requests.php?error=Student record not found');
    exit;
}

// Get request details
$stmt = $pdo->prepare("SELECT dr.*, u.username as approved_by_username, u2.username as rejected_by_username, u3.username as uploaded_by_username
                      FROM document_requests dr
                      LEFT JOIN users u ON dr.approved_by = u.user_id
                      LEFT JOIN users u2 ON dr.rejected_by = u2.user_id
                      LEFT JOIN users u3 ON dr.uploaded_by = u3.user_id
                      WHERE dr.request_id = ? AND dr.student_id = ?");
$stmt->execute([$request_id, $student_id]);
$request = $stmt->fetch();

if (!$request) {
    header('Location: ' . BASE_URL . 'modules/documents/my_requests.php?error=Request not found');
    exit;
}

include __DIR__ . '/../../includes/header.php';

// Determine status badge class
$status_badge_class = 'secondary';
if ($request['status'] === 'Pending') {
    $status_badge_class = 'warning';
} elseif ($request['status'] === 'Approved') {
    $status_badge_class = 'info';
} elseif ($request['status'] === 'Rejected') {
    $status_badge_class = 'danger';
} elseif ($request['status'] === 'Ready for Download') {
    $status_badge_class = 'success';
}
?>

<style>
.request-header-card {
    background: linear-gradient(180deg, #1e40af 0%, #2563eb 100%);
    border-radius: 24px;
    padding: 2.5rem;
    color: white;
    margin-bottom: 2rem;
    position: relative;
    overflow: hidden;
    box-shadow: 0 10px 40px rgba(30, 64, 175, 0.3);
}

.request-header-card::before {
    content: '';
    position: absolute;
    top: -50%;
    left: -50%;
    width: 200%;
    height: 200%;
    background: 
        radial-gradient(circle at 30% 50%, rgba(255, 215, 0, 0.15) 0%, transparent 50%),
        radial-gradient(circle at 70% 80%, rgba(255, 255, 255, 0.08) 0%, transparent 40%);
}

.request-header-content {
    position: relative;
    z-index: 2;
}

.request-header-title {
    display: flex;
    align-items: center;
    gap: 1rem;
    margin-bottom: 1rem;
}

.request-header-title i {
    font-size: 2.5rem;
    color: #ffd700;
    filter: drop-shadow(0 2px 8px rgba(0, 0, 0, 0.2));
}

.request-header-title h1 {
    font-size: 2rem;
    font-weight: 800;
    margin: 0;
    text-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
}

.request-status-badge {
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    padding: 0.75rem 1.5rem;
    background: rgba(255, 255, 255, 0.2);
    backdrop-filter: blur(10px);
    border-radius: 12px;
    font-weight: 700;
    font-size: 1rem;
    border: 2px solid rgba(255, 255, 255, 0.3);
}

.detail-cards {
    display: grid;
    gap: 1.5rem;
    margin-bottom: 2rem;
}

.detail-card {
    background: white;
    border-radius: 20px;
    padding: 2rem;
    box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
    border: 2px solid #e2e8f0;
    transition: all 0.3s;
}

.detail-card:hover {
    box-shadow: 0 8px 30px rgba(30, 64, 175, 0.15);
    transform: translateY(-2px);
}

.detail-card h3 {
    font-size: 1.25rem;
    font-weight: 700;
    color: #1e40af;
    margin: 0 0 1.5rem 0;
    display: flex;
    align-items: center;
    gap: 0.75rem;
    padding-bottom: 1rem;
    border-bottom: 2px solid #e2e8f0;
}

.detail-card h3 i {
    color: #2563eb;
}

.detail-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 1.5rem;
}

.detail-item {
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
}

.detail-item label {
    font-size: 0.875rem;
    font-weight: 600;
    color: #64748b;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.detail-item .value {
    font-size: 1.05rem;
    font-weight: 600;
    color: #1e293b;
}

.download-card {
    background: linear-gradient(135deg, #f0fdf4 0%, #dcfce7 100%);
    border: 2px solid #86efac;
    border-radius: 20px;
    padding: 2.5rem;
    text-align: center;
    box-shadow: 0 8px 30px rgba(34, 197, 94, 0.2);
}

.download-card h3 {
    font-size: 1.5rem;
    font-weight: 800;
    color: #166534;
    margin: 0 0 1rem 0;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 0.75rem;
}

.download-card h3 i {
    color: #22c55e;
}

.download-card p {
    color: #15803d;
    margin-bottom: 2rem;
    font-size: 1.05rem;
}

.btn-download {
    display: inline-flex;
    align-items: center;
    gap: 0.75rem;
    padding: 1.125rem 2.5rem;
    background: linear-gradient(180deg, #22c55e 0%, #16a34a 100%);
    color: white;
    border: none;
    border-radius: 14px;
    font-size: 1.1rem;
    font-weight: 700;
    text-decoration: none;
    box-shadow: 0 8px 24px rgba(34, 197, 94, 0.4);
    transition: all 0.3s;
}

.btn-download:hover {
    transform: translateY(-3px);
    box-shadow: 0 12px 32px rgba(34, 197, 94, 0.5);
    background: linear-gradient(180deg, #16a34a 0%, #15803d 100%);
}

.file-info {
    margin-top: 1.5rem;
    padding-top: 1.5rem;
    border-top: 2px solid rgba(34, 197, 94, 0.2);
    color: #166534;
    font-size: 0.95rem;
}

.notes-section {
    background: linear-gradient(135deg, #f8fafc 0%, #ffffff 100%);
    border-left: 4px solid #2563eb;
    border-radius: 12px;
    padding: 1.5rem;
    margin-top: 1rem;
}

.notes-section h4 {
    font-size: 1rem;
    font-weight: 700;
    color: #1e40af;
    margin: 0 0 0.75rem 0;
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.notes-section p {
    color: #475569;
    line-height: 1.7;
    margin: 0;
}

.rejection-alert {
    background: linear-gradient(135deg, #fef2f2 0%, #fee2e2 100%);
    border: 2px solid #fecaca;
    border-left: 4px solid #ef4444;
    border-radius: 12px;
    padding: 1.5rem;
    margin-top: 1rem;
}

.rejection-alert h4 {
    font-size: 1rem;
    font-weight: 700;
    color: #991b1b;
    margin: 0 0 0.75rem 0;
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.rejection-alert p {
    color: #7f1d1d;
    line-height: 1.7;
    margin: 0;
}

@media (max-width: 768px) {
    .request-header-card {
        padding: 1.5rem;
    }
    
    .request-header-title h1 {
        font-size: 1.5rem;
    }
    
    .detail-card {
        padding: 1.5rem;
    }
    
    .detail-grid {
        grid-template-columns: 1fr;
    }
}
</style>

<div class="page-container">
    <div class="page-header" style="margin-bottom: 1.5rem;">
        <a href="<?php echo BASE_URL; ?>modules/documents/my_requests.php" class="btn btn-secondary">
            <i class="fas fa-arrow-left"></i> Back to My Requests
        </a>
    </div>

    <!-- Request Header Card -->
    <div class="request-header-card">
        <div class="request-header-content">
            <div class="request-header-title">
                <i class="fas fa-file-alt"></i>
                <h1><?php echo sanitizeOutput($request['document_type']); ?></h1>
            </div>
            <div class="request-status-badge">
                <i class="fas fa-<?php 
                    echo $request['status'] === 'Pending' ? 'clock' : 
                        ($request['status'] === 'Approved' ? 'check-circle' : 
                        ($request['status'] === 'Rejected' ? 'times-circle' : 
                        ($request['status'] === 'Ready for Download' ? 'download' : 'info-circle'))); 
                ?>"></i>
                <span><?php echo sanitizeOutput($request['status']); ?></span>
            </div>
        </div>
    </div>

    <div class="detail-cards">
        <!-- Request Information Card -->
        <div class="detail-card">
            <h3><i class="fas fa-info-circle"></i> Request Information</h3>
            <div class="detail-grid">
                <div class="detail-item">
                    <label>Document Type</label>
                    <div class="value"><?php echo sanitizeOutput($request['document_type']); ?></div>
                </div>
                <div class="detail-item">
                    <label>Request Date</label>
                    <div class="value"><?php echo date('M d, Y g:i A', strtotime($request['request_date'])); ?></div>
                </div>
                <?php if ($request['approved_by_username']): ?>
                <div class="detail-item">
                    <label>Approved By</label>
                    <div class="value"><?php echo sanitizeOutput($request['approved_by_username']); ?></div>
                </div>
                <div class="detail-item">
                    <label>Approved Date</label>
                    <div class="value"><?php echo date('M d, Y g:i A', strtotime($request['approved_at'])); ?></div>
                </div>
                <?php endif; ?>
                <?php if ($request['rejected_by_username']): ?>
                <div class="detail-item">
                    <label>Rejected By</label>
                    <div class="value"><?php echo sanitizeOutput($request['rejected_by_username']); ?></div>
                </div>
                <div class="detail-item">
                    <label>Rejected Date</label>
                    <div class="value"><?php echo date('M d, Y g:i A', strtotime($request['rejected_at'])); ?></div>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Notes Section -->
        <?php if (!empty($request['notes'])): ?>
        <div class="detail-card">
            <h3><i class="fas fa-sticky-note"></i> Your Notes</h3>
            <div class="notes-section">
                <p><?php echo nl2br(sanitizeOutput($request['notes'])); ?></p>
            </div>
        </div>
        <?php endif; ?>

        <!-- Admin Notes -->
        <?php if (!empty($request['admin_notes'])): ?>
        <div class="detail-card">
            <h3><i class="fas fa-user-shield"></i> Admin Notes</h3>
            <div class="notes-section">
                <p><?php echo nl2br(sanitizeOutput($request['admin_notes'])); ?></p>
            </div>
        </div>
        <?php endif; ?>

        <!-- Rejection Reason -->
        <?php if (!empty($request['rejection_reason'])): ?>
        <div class="detail-card">
            <h3><i class="fas fa-exclamation-triangle"></i> Rejection Reason</h3>
            <div class="rejection-alert">
                <p><?php echo nl2br(sanitizeOutput($request['rejection_reason'])); ?></p>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <!-- Download Card -->
    <?php if ($request['status'] === 'Ready for Download' && !empty($request['file_path'])): ?>
    <div class="download-card">
        <h3><i class="fas fa-check-circle"></i> Document Ready</h3>
        <p>Your document has been processed and is ready for download.</p>
        <a href="<?php echo BASE_URL; ?>modules/documents/download.php?id=<?php echo $request_id; ?>" 
           class="btn-download">
            <i class="fas fa-download"></i>
            <span>Download Document</span>
        </a>
        <div class="file-info">
            <div><strong>File:</strong> <?php echo sanitizeOutput($request['file_name']); ?></div>
            <div style="margin-top: 0.5rem;"><strong>Uploaded:</strong> <?php echo date('M d, Y g:i A', strtotime($request['uploaded_at'])); ?></div>
        </div>
    </div>
    <?php endif; ?>
</div>

<?php include __DIR__ . '/../../includes/footer.php'; ?>

