<?php
/**
 * Request Document
 * Students can request documents (Transcript, Certificate of Enrollment, Certificate of Good Moral Character)
 */

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/feature_toggles.php';
requireAuth([ROLE_STUDENT]);

$page_title = 'Request Document';
$pdo = getDBConnection();

// Check if document requests feature is enabled
if (!isDocumentRequestsEnabled($pdo)) {
    header('Location: ' . BASE_URL . 'dashboard/index.php?error=The Document Request feature is currently disabled');
    exit;
}
$error = '';
$success = '';

// Check if document_requests table exists
$table_exists = false;
try {
    $stmt = $pdo->query("SHOW TABLES LIKE 'document_requests'");
    $table_exists = $stmt->rowCount() > 0;
} catch (PDOException $e) {
    $table_exists = false;
}

if (!$table_exists) {
    $error = 'The document_requests table does not exist. Please run the migration: database/migration_document_requests.sql';
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
    $error = 'Student record not found.';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $document_type = sanitizeInput($_POST['document_type'] ?? '');
    $notes = sanitizeInput($_POST['notes'] ?? '');
    
    if (empty($document_type)) {
        $error = 'Please select a document type.';
    } elseif (!in_array($document_type, ['Transcript', 'Certificate of Enrollment', 'Certificate of Good Moral Character'])) {
        $error = 'Invalid document type.';
    } else {
        try {
            $stmt = $pdo->prepare("INSERT INTO document_requests (student_id, document_type, notes, requested_by, status) 
                                  VALUES (?, ?, ?, ?, 'Pending')");
            $stmt->execute([$student_id, $document_type, $notes, $_SESSION['user_id']]);
            
            header('Location: ' . BASE_URL . 'modules/documents/my_requests.php?success=Document request submitted successfully');
            exit;
        } catch (PDOException $e) {
            $error = 'Failed to submit request: ' . $e->getMessage();
        }
    }
}

include __DIR__ . '/../../includes/header.php';
?>

<div class="page-container">
    <div class="page-header">
        <h1><i class="fas fa-file-alt"></i> Request Document</h1>
        <a href="<?php echo BASE_URL; ?>modules/documents/my_requests.php" class="btn btn-secondary">
            <i class="fas fa-arrow-left"></i> My Requests
        </a>
    </div>

    <?php if ($error): ?>
        <div class="alert alert-error">
            <i class="fas fa-exclamation-circle"></i> <?php echo sanitizeOutput($error); ?>
        </div>
    <?php endif; ?>

    <?php if ($table_exists && $student_id): ?>
    <div class="card">
        <form method="POST" action="" class="form">
            <div class="form-section">
                <h3>Document Request</h3>
                
                <div class="form-group">
                    <label for="document_type">Document Type <span class="required">*</span></label>
                    <select id="document_type" name="document_type" required class="form-control">
                        <option value="">Select Document Type</option>
                        <option value="Transcript">Transcript</option>
                        <option value="Certificate of Enrollment">Certificate of Enrollment</option>
                        <option value="Certificate of Good Moral Character">Certificate of Good Moral Character</option>
                    </select>
                </div>

                <div class="form-group">
                    <label for="notes">Additional Notes (Optional)</label>
                    <textarea id="notes" name="notes" class="form-control" rows="4"
                              placeholder="Add any additional information or special instructions for your request"><?php echo sanitizeOutput($_POST['notes'] ?? ''); ?></textarea>
                </div>
            </div>

            <div class="form-actions">
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-paper-plane"></i> Submit Request
                </button>
                <a href="<?php echo BASE_URL; ?>modules/documents/my_requests.php" class="btn btn-secondary">
                    <i class="fas fa-times"></i> Cancel
                </a>
            </div>
        </form>
    </div>
    <?php endif; ?>
</div>

<?php include __DIR__ . '/../../includes/footer.php'; ?>

