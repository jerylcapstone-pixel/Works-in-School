<?php
/**
 * Course Registration - Delete Class Section
 */
require_once __DIR__ . '/../../config/config.php';
requireAuth(ROLE_ADMIN);

$section_id = (int)($_GET['id'] ?? 0);
if (!$section_id) {
    header('Location: ' . BASE_URL . 'modules/registration/index.php?error=Invalid section ID');
    exit;
}

$pdo = getDBConnection();

// Get section details
$stmt = $pdo->prepare("SELECT cs.*, c.course_name
                      FROM class_sections cs
                      LEFT JOIN courses c ON cs.course_id = c.course_id
                      WHERE cs.section_id = ?");
$stmt->execute([$section_id]);
$section = $stmt->fetch();

if (!$section) {
    header('Location: ' . BASE_URL . 'modules/registration/index.php?error=Section not found');
    exit;
}

// Check if there are enrollments
$stmt = $pdo->prepare("SELECT COUNT(*) FROM enrollments WHERE section_id = ?");
$stmt->execute([$section_id]);
$enrollment_count = $stmt->fetchColumn();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['confirm'])) {
    try {
        $pdo->beginTransaction();
        
        if ($enrollment_count > 0) {
            // Delete enrollments first
            $stmt = $pdo->prepare("DELETE FROM enrollments WHERE section_id = ?");
            $stmt->execute([$section_id]);
        }
        
        // Delete section
        $stmt = $pdo->prepare("DELETE FROM class_sections WHERE section_id = ?");
        $stmt->execute([$section_id]);
        
        $pdo->commit();
        header('Location: ' . BASE_URL . 'modules/registration/index.php?success=' . urlencode('Section deleted successfully.'));
        exit;
    } catch (PDOException $e) {
        $pdo->rollBack();
        header('Location: ' . BASE_URL . 'modules/registration/index.php?error=' . urlencode('Error deleting section: ' . $e->getMessage()));
        exit;
    }
}

$page_title = 'Delete Section';
include __DIR__ . '/../../includes/header.php';
?>

<div class="page-container">
    <div class="page-header">
        <h1><i class="fas fa-trash"></i> Delete Class Section</h1>
        <a href="<?php echo BASE_URL; ?>modules/registration/index.php" class="btn btn-secondary">
            <i class="fas fa-arrow-left"></i> Back
        </a>
    </div>

    <div class="card">
        <div class="alert alert-warning">
            <i class="fas fa-exclamation-triangle"></i> <strong>Warning:</strong> This action cannot be undone. 
            <?php if ($enrollment_count > 0): ?>
                This will also delete <?php echo $enrollment_count; ?> enrollment(s) associated with this section.
            <?php endif; ?>
        </div>

        <h2>Section Information</h2>
        <div class="info-grid">
            <div><strong>Course:</strong> <?php echo sanitizeOutput($section['course_name']); ?></div>
            <div><strong>Section:</strong> <?php echo sanitizeOutput($section['section_number']); ?></div>
            <div><strong>Semester:</strong> <?php echo sanitizeOutput($section['semester']); ?></div>
            <div><strong>Academic Year:</strong> <?php echo sanitizeOutput($section['academic_year']); ?></div>
            <div><strong>Current Enrollment:</strong> <?php echo $section['current_enrollment']; ?></div>
            <div><strong>Status:</strong> <span class="badge badge-<?php echo $section['status'] === 'Open' ? 'success' : 'warning'; ?>"><?php echo sanitizeOutput($section['status']); ?></span></div>
        </div>

        <form method="POST" action="" style="margin-top: 20px;">
            <div class="form-actions">
                <button type="submit" name="confirm" value="1" class="btn btn-danger" onclick="return confirm('Are you sure you want to delete this section? This action cannot be undone.');">
                    <i class="fas fa-trash"></i> Delete Section
                </button>
                <a href="<?php echo BASE_URL; ?>modules/registration/index.php" class="btn btn-secondary">Cancel</a>
            </div>
        </form>
    </div>
</div>

<?php include __DIR__ . '/../../includes/footer.php'; ?>

