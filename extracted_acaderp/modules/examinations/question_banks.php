<?php
/**
 * Question Banks Management
 * Manage reusable question banks
 */

require_once __DIR__ . '/../../config/config.php';
requireAuth([ROLE_ADMIN, ROLE_FACULTY]);

$page_title = 'Question Banks';
$pdo = getDBConnection();
$error = '';
$success = '';

// Check if question_banks table exists
$table_exists = false;
try {
    $stmt = $pdo->query("SHOW TABLES LIKE 'question_banks'");
    $table_exists = $stmt->rowCount() > 0;
} catch (PDOException $e) {
    $table_exists = false;
}

// Get all question banks
$question_banks = [];
if ($table_exists) {
    try {
        $query = "SELECT qb.*, u.username as created_by_username,
                  (SELECT COUNT(*) FROM questions q WHERE q.question_bank_id = qb.question_bank_id) as question_count
                  FROM question_banks qb
                  LEFT JOIN users u ON qb.created_by = u.user_id";
        
        // Filter by creator if faculty
        if (hasRole(ROLE_FACULTY) && !hasRole(ROLE_ADMIN)) {
            $query .= " WHERE qb.created_by = ?";
            $stmt = $pdo->prepare($query);
            $stmt->execute([$_SESSION['user_id']]);
        } else {
            $stmt = $pdo->query($query);
        }
        
        $question_banks = $stmt->fetchAll();
    } catch (PDOException $e) {
        $error = 'Error loading question banks: ' . $e->getMessage();
    }
}

// Handle form submission for creating new question bank
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_bank'])) {
    $name = sanitizeInput($_POST['name'] ?? '');
    $description = sanitizeInput($_POST['description'] ?? '');
    
    if (empty($name)) {
        $error = 'Question bank name is required.';
    } else {
        try {
            $stmt = $pdo->prepare("INSERT INTO question_banks (name, description, created_by) VALUES (?, ?, ?)");
            $stmt->execute([$name, $description, $_SESSION['user_id']]);
            $success = 'Question bank created successfully.';
            // Reload page to show new bank
            header('Location: ' . BASE_URL . 'modules/examinations/question_banks.php?success=' . urlencode($success));
            exit;
        } catch (PDOException $e) {
            $error = 'Failed to create question bank: ' . $e->getMessage();
        }
    }
}

include __DIR__ . '/../../includes/header.php';
?>

<div class="page-container">
    <div class="page-header">
        <h1><i class="fas fa-book"></i> Question Banks</h1>
        <a href="<?php echo BASE_URL; ?>modules/examinations/index.php" class="btn btn-secondary">
            <i class="fas fa-arrow-left"></i> Back to Examinations
        </a>
    </div>

    <?php if ($error): ?>
        <div class="alert alert-error">
            <i class="fas fa-exclamation-circle"></i> <?php echo sanitizeOutput($error); ?>
        </div>
    <?php endif; ?>

    <?php if ($success || isset($_GET['success'])): ?>
        <div class="alert alert-success">
            <i class="fas fa-check-circle"></i> <?php echo sanitizeOutput($success ?: $_GET['success']); ?>
        </div>
    <?php endif; ?>

    <?php if ($table_exists): ?>
    <div class="card" style="margin-bottom: 1.5rem;">
        <h2>Create New Question Bank</h2>
        <form method="POST" action="" class="form">
            <div class="form-row">
                <div class="form-group" style="flex: 2;">
                    <label for="name">Question Bank Name <span class="required">*</span></label>
                    <input type="text" id="name" name="name" required class="form-control" 
                           placeholder="Enter question bank name">
                </div>
                <div class="form-group" style="flex: 3;">
                    <label for="description">Description</label>
                    <input type="text" id="description" name="description" class="form-control" 
                           placeholder="Enter description (optional)">
                </div>
                <div class="form-group" style="flex: 0 0 auto; align-self: flex-end;">
                    <button type="submit" name="create_bank" class="btn btn-primary">
                        <i class="fas fa-plus"></i> Create
                    </button>
                </div>
            </div>
        </form>
    </div>

    <div class="card">
        <h2>Question Banks (<?php echo count($question_banks); ?>)</h2>
        <div style="overflow-x: auto;">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Description</th>
                        <th>Questions</th>
                        <th>Created By</th>
                        <th>Created At</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($question_banks)): ?>
                        <tr><td colspan="6" class="text-center">No question banks found. Create one to get started.</td></tr>
                    <?php else: ?>
                        <?php foreach ($question_banks as $bank): ?>
                        <tr>
                            <td><strong><?php echo sanitizeOutput($bank['name']); ?></strong></td>
                            <td><?php echo sanitizeOutput($bank['description'] ?: 'N/A'); ?></td>
                            <td><?php echo (int)$bank['question_count']; ?></td>
                            <td><?php echo sanitizeOutput($bank['created_by_username'] ?? 'N/A'); ?></td>
                            <td><?php echo date('M d, Y', strtotime($bank['created_at'])); ?></td>
                            <td class="actions">
                                <a href="<?php echo BASE_URL; ?>modules/examinations/questions.php?bank_id=<?php echo $bank['question_bank_id']; ?>" 
                                   class="btn btn-sm btn-primary" title="View Questions">
                                    <i class="fas fa-list"></i> Questions
                                </a>
                                <a href="<?php echo BASE_URL; ?>modules/examinations/question_add.php?bank_id=<?php echo $bank['question_bank_id']; ?>" 
                                   class="btn btn-sm btn-success" title="Add Question">
                                    <i class="fas fa-plus"></i> Add
                                </a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php endif; ?>
</div>

<?php include __DIR__ . '/../../includes/footer.php'; ?>

