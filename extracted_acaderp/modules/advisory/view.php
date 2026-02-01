<?php
/**
 * Student Advisory - View Advisory Notes
 */
require_once __DIR__ . '/../../config/config.php';
requireAuth([ROLE_ADMIN, ROLE_FACULTY]);
$page_title = 'Advisory Details';
$pdo = getDBConnection();

$advisory_id = (int)($_GET['id'] ?? 0);
if (!$advisory_id) {
    header('Location: ' . BASE_URL . 'modules/advisory/index.php?error=Invalid advisory ID');
    exit;
}

// Get advisory assignment details
$stmt = $pdo->prepare("SELECT aa.*, s.student_id, s.student_number, s.first_name as student_first, s.last_name as student_last,
                      s.email, s.phone, p.program_name,
                      f.faculty_id, f.first_name as advisor_first, f.last_name as advisor_last, f.email as advisor_email, f.phone as advisor_phone
                      FROM advisory_assignments aa
                      LEFT JOIN students s ON aa.student_id = s.student_id
                      LEFT JOIN programs p ON s.program_id = p.program_id
                      LEFT JOIN faculty f ON aa.advisor_id = f.faculty_id
                      WHERE aa.advisory_id = ?");
$stmt->execute([$advisory_id]);
$advisory = $stmt->fetch();

if (!$advisory) {
    header('Location: ' . BASE_URL . 'modules/advisory/index.php?error=Advisory assignment not found');
    exit;
}

// Verify faculty can only access their own advisory assignments
if (hasRole(ROLE_FACULTY) && !hasRole(ROLE_ADMIN)) {
    $stmt = $pdo->prepare("SELECT faculty_id FROM faculty WHERE user_id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $faculty = $stmt->fetch();
    $faculty_id = $faculty['faculty_id'] ?? null;
    
    if (!$faculty_id || $advisory['advisor_id'] != $faculty_id) {
        header('Location: ' . BASE_URL . 'modules/advisory/index.php?error=Access denied');
        exit;
    }
}

// Get advisory notes
$notes = [];
try {
    $stmt = $pdo->prepare("SELECT an.*, u.first_name as created_first, u.last_name as created_last
                         FROM advisory_notes an
                         LEFT JOIN users u ON an.created_by = u.user_id
                         WHERE an.advisory_id = ?
                         ORDER BY an.created_at DESC");
    $stmt->execute([$advisory_id]);
    $notes = $stmt->fetchAll();
} catch (PDOException $e) {
    // Table might not exist yet
    $notes = [];
}

$error = '';
$success = '';

// Handle note creation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_note'])) {
    $note_text = sanitizeInput($_POST['note_text'] ?? '');
    
    if (empty($note_text)) {
        $error = 'Note text is required.';
    } else {
        try {
            $stmt = $pdo->prepare("INSERT INTO advisory_notes (advisory_id, note_text, created_by) VALUES (?, ?, ?)");
            $stmt->execute([$advisory_id, $note_text, $_SESSION['user_id']]);
            $success = 'Note added successfully.';
            header('Location: ' . BASE_URL . 'modules/advisory/view.php?id=' . $advisory_id . '&success=' . urlencode($success));
            exit;
        } catch (PDOException $e) {
            $error = 'Error adding note: ' . $e->getMessage();
        }
    }
}

include __DIR__ . '/../../includes/header.php';
?>

<div class="page-container">
    <div class="page-header">
        <h1><i class="fas fa-user-friends"></i> Advisory Details</h1>
        <a href="<?php echo BASE_URL; ?>modules/advisory/index.php" class="btn btn-secondary">
            <i class="fas fa-arrow-left"></i> Back
        </a>
    </div>

    <?php if ($error): ?>
        <div class="alert alert-error"><?php echo sanitizeOutput($error); ?></div>
    <?php endif; ?>
    
    <?php if ($success || isset($_GET['success'])): ?>
        <div class="alert alert-success"><?php echo sanitizeOutput($success ?: $_GET['success']); ?></div>
    <?php endif; ?>

    <div class="card">
        <h2>Assignment Information</h2>
        <div class="info-grid">
            <div><strong>Student:</strong> <?php echo sanitizeOutput($advisory['student_number'] . ' - ' . $advisory['student_first'] . ' ' . $advisory['student_last']); ?></div>
            <div><strong>Program:</strong> <?php echo sanitizeOutput($advisory['program_name'] ?? 'N/A'); ?></div>
            <div><strong>Advisor:</strong> <?php echo sanitizeOutput($advisory['advisor_first'] . ' ' . $advisory['advisor_last']); ?></div>
            <div><strong>Assigned Date:</strong> <?php echo date('F d, Y', strtotime($advisory['assigned_date'])); ?></div>
            <div><strong>Status:</strong> <span class="badge badge-<?php echo $advisory['status'] === 'Active' ? 'success' : 'warning'; ?>"><?php echo sanitizeOutput($advisory['status']); ?></span></div>
        </div>
    </div>

    <div class="card">
        <h2>Advisory Notes</h2>
        
        <?php if (hasRole(ROLE_ADMIN) || $_SESSION['user_id'] == $advisory['faculty_id']): ?>
            <form method="POST" action="" style="margin-bottom: 20px;">
                <div class="form-group">
                    <label for="note_text">Add Note</label>
                    <textarea id="note_text" name="note_text" class="form-control" rows="4" placeholder="Enter advisory note..." required></textarea>
                </div>
                <button type="submit" name="add_note" class="btn btn-primary"><i class="fas fa-plus"></i> Add Note</button>
            </form>
        <?php endif; ?>

        <div style="max-height: 400px; overflow-y: auto;">
            <?php if (empty($notes)): ?>
                <p class="text-center text-muted">No notes yet.</p>
            <?php else: ?>
                <?php foreach ($notes as $note): ?>
                    <div style="border-left: 3px solid #2196F3; padding: 10px; margin-bottom: 10px; background: #f9f9f9;">
                        <p style="margin: 0 0 5px 0;"><?php echo nl2br(sanitizeOutput($note['note_text'])); ?></p>
                        <small class="text-muted">
                            By <?php echo sanitizeOutput(($note['created_first'] ?? '') . ' ' . ($note['created_last'] ?? 'System')); ?> 
                            on <?php echo date('M d, Y g:i A', strtotime($note['created_at'])); ?>
                        </small>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../../includes/footer.php'; ?>

