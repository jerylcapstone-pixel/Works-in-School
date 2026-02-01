<?php
/**
 * Questions List
 * View questions in a question bank
 */

require_once __DIR__ . '/../../config/config.php';
requireAuth([ROLE_ADMIN, ROLE_FACULTY]);

$page_title = 'Questions';
$pdo = getDBConnection();
$error = '';
$question_bank_id = isset($_GET['bank_id']) ? (int)$_GET['bank_id'] : null;

if (!$question_bank_id) {
    header('Location: ' . BASE_URL . 'modules/examinations/question_banks.php?error=Invalid question bank ID');
    exit;
}

// Get question bank info
$question_bank = null;
try {
    $stmt = $pdo->prepare("SELECT * FROM question_banks WHERE question_bank_id = ?");
    $stmt->execute([$question_bank_id]);
    $question_bank = $stmt->fetch();
    
    if (!$question_bank) {
        header('Location: ' . BASE_URL . 'modules/examinations/question_banks.php?error=Question bank not found');
        exit;
    }
} catch (PDOException $e) {
    $error = 'Error loading question bank.';
}

// Get questions
$questions = [];
if ($question_bank) {
    try {
        $stmt = $pdo->prepare("SELECT q.*, 
                              (SELECT COUNT(*) FROM question_options qo WHERE qo.question_id = q.question_id) as option_count
                              FROM questions q
                              WHERE q.question_bank_id = ?
                              ORDER BY q.created_at DESC");
        $stmt->execute([$question_bank_id]);
        $questions = $stmt->fetchAll();
        
        // Get options for each question
        foreach ($questions as &$question) {
            if (in_array($question['question_type'], ['Multiple Choice', 'True/False'])) {
                $stmt = $pdo->prepare("SELECT * FROM question_options WHERE question_id = ? ORDER BY option_order");
                $stmt->execute([$question['question_id']]);
                $question['options'] = $stmt->fetchAll();
            }
        }
    } catch (PDOException $e) {
        $error = 'Error loading questions: ' . $e->getMessage();
    }
}

include __DIR__ . '/../../includes/header.php';
?>

<div class="page-container">
    <div class="page-header">
        <h1><i class="fas fa-list"></i> Questions: <?php echo sanitizeOutput($question_bank['name'] ?? 'Unknown'); ?></h1>
        <div style="display: flex; gap: 0.5rem;">
            <a href="<?php echo BASE_URL; ?>modules/examinations/question_add.php?bank_id=<?php echo $question_bank_id; ?>" class="btn btn-primary">
                <i class="fas fa-plus"></i> Add Question
            </a>
            <a href="<?php echo BASE_URL; ?>modules/examinations/question_banks.php" class="btn btn-secondary">
                <i class="fas fa-arrow-left"></i> Back to Banks
            </a>
        </div>
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

    <?php if ($question_bank): ?>
    <div class="card">
        <div style="margin-bottom: 1rem;">
            <p><strong>Description:</strong> <?php echo sanitizeOutput($question_bank['description'] ?: 'No description'); ?></p>
            <p><strong>Total Questions:</strong> <?php echo count($questions); ?></p>
        </div>

        <?php if (empty($questions)): ?>
            <p class="text-center" style="padding: 2rem;">No questions in this bank yet. <a href="<?php echo BASE_URL; ?>modules/examinations/question_add.php?bank_id=<?php echo $question_bank_id; ?>">Add your first question</a></p>
        <?php else: ?>
            <div style="display: flex; flex-direction: column; gap: 1.5rem;">
                <?php foreach ($questions as $idx => $question): ?>
                <div style="border: 1px solid #ddd; border-radius: 5px; padding: 1.5rem; background: #fff;">
                    <div style="display: flex; justify-content: space-between; align-items: start; margin-bottom: 1rem;">
                        <div>
                            <h3 style="margin: 0;">Question <?php echo $idx + 1; ?></h3>
                            <span class="badge badge-info"><?php echo sanitizeOutput($question['question_type']); ?></span>
                            <span class="badge badge-success"><?php echo number_format($question['points'], 2); ?> points</span>
                        </div>
                        <div class="actions">
                            <a href="<?php echo BASE_URL; ?>modules/examinations/question_edit.php?id=<?php echo $question['question_id']; ?>" 
                               class="btn btn-sm btn-warning" title="Edit">
                                <i class="fas fa-edit"></i>
                            </a>
                            <a href="<?php echo BASE_URL; ?>modules/examinations/question_delete.php?id=<?php echo $question['question_id']; ?>&bank_id=<?php echo $question_bank_id; ?>" 
                               class="btn btn-sm btn-danger" 
                               onclick="return confirm('Are you sure you want to delete this question?');"
                               title="Delete">
                                <i class="fas fa-trash"></i>
                            </a>
                        </div>
                    </div>
                    
                    <div style="margin-bottom: 1rem;">
                        <p style="font-weight: 500; margin-bottom: 0.5rem;"><?php echo nl2br(sanitizeOutput($question['question_text'])); ?></p>
                    </div>

                    <?php if (!empty($question['options'])): ?>
                        <div style="margin-bottom: 1rem;">
                            <strong>Options:</strong>
                            <ul style="margin-top: 0.5rem; padding-left: 1.5rem;">
                                <?php foreach ($question['options'] as $option): ?>
                                    <li style="color: <?php echo $option['is_correct'] ? 'green' : '#666'; ?>; font-weight: <?php echo $option['is_correct'] ? 'bold' : 'normal'; ?>;">
                                        <?php echo sanitizeOutput($option['option_text']); ?>
                                        <?php if ($option['is_correct']): ?>
                                            <span class="badge badge-success" style="margin-left: 0.5rem;">Correct</span>
                                        <?php endif; ?>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    <?php elseif (!empty($question['correct_answer'])): ?>
                        <div style="margin-bottom: 1rem;">
                            <strong>Correct Answer:</strong>
                            <p style="color: green; font-weight: bold; margin-top: 0.5rem;"><?php echo nl2br(sanitizeOutput($question['correct_answer'])); ?></p>
                        </div>
                    <?php endif; ?>

                    <?php if (!empty($question['explanation'])): ?>
                        <div style="margin-top: 1rem; padding-top: 1rem; border-top: 1px solid #eee;">
                            <strong>Explanation:</strong>
                            <p style="margin-top: 0.5rem; color: #666;"><?php echo nl2br(sanitizeOutput($question['explanation'])); ?></p>
                        </div>
                    <?php endif; ?>
                </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
    <?php endif; ?>
</div>

<?php include __DIR__ . '/../../includes/footer.php'; ?>

