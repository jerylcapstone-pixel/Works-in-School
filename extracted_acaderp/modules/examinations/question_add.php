<?php
/**
 * Add New Question
 * Form to create questions for question banks or exams
 */

require_once __DIR__ . '/../../config/config.php';
requireAuth([ROLE_ADMIN, ROLE_FACULTY]);

$page_title = 'Add Question';
$pdo = getDBConnection();
$error = '';
$question_bank_id = isset($_GET['bank_id']) ? (int)$_GET['bank_id'] : null;

// Check if questions table exists
$table_exists = false;
try {
    $stmt = $pdo->query("SHOW TABLES LIKE 'questions'");
    $table_exists = $stmt->rowCount() > 0;
} catch (PDOException $e) {
    $table_exists = false;
}

// Get question banks
$question_banks = [];
if ($table_exists) {
    try {
        $query = "SELECT question_bank_id, name FROM question_banks";
        if (hasRole(ROLE_FACULTY) && !hasRole(ROLE_ADMIN)) {
            $query .= " WHERE created_by = ?";
            $stmt = $pdo->prepare($query);
            $stmt->execute([$_SESSION['user_id']]);
        } else {
            $stmt = $pdo->query($query);
        }
        $question_banks = $stmt->fetchAll();
    } catch (PDOException $e) {
        // Table might not exist
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $question_bank_id = !empty($_POST['question_bank_id']) ? (int)$_POST['question_bank_id'] : null;
    $question_text = sanitizeInput($_POST['question_text'] ?? '');
    $question_type = sanitizeInput($_POST['question_type'] ?? 'Multiple Choice');
    $points = !empty($_POST['points']) ? (float)$_POST['points'] : 1.00;
    $correct_answer = sanitizeInput($_POST['correct_answer'] ?? '');
    $explanation = sanitizeInput($_POST['explanation'] ?? '');
    
    // Get options for multiple choice
    $options = [];
    if ($question_type === 'Multiple Choice' || $question_type === 'True/False') {
        if (isset($_POST['options']) && is_array($_POST['options'])) {
            foreach ($_POST['options'] as $idx => $option) {
                $option_text = sanitizeInput($option['text'] ?? '');
                $is_correct = isset($option['is_correct']) ? 1 : 0;
                if (!empty($option_text)) {
                    $options[] = ['text' => $option_text, 'is_correct' => $is_correct, 'order' => $idx];
                }
            }
        }
    }

    if (empty($question_text)) {
        $error = 'Question text is required.';
    } elseif (in_array($question_type, ['Multiple Choice', 'True/False']) && empty($options)) {
        $error = 'At least one option is required for ' . $question_type . ' questions.';
    } elseif (in_array($question_type, ['Multiple Choice', 'True/False']) && 
              !in_array(1, array_column($options, 'is_correct'))) {
        $error = 'At least one correct option is required.';
    } elseif (!in_array($question_type, ['Multiple Choice', 'True/False']) && empty($correct_answer)) {
        $error = 'Correct answer is required for ' . $question_type . ' questions.';
    } else {
        try {
            $pdo->beginTransaction();
            
            // Insert question
            $stmt = $pdo->prepare("INSERT INTO questions (question_bank_id, question_text, question_type, points, correct_answer, explanation, created_by) 
                                  VALUES (?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$question_bank_id, $question_text, $question_type, $points, $correct_answer, $explanation, $_SESSION['user_id']]);
            $question_id = $pdo->lastInsertId();
            
            // Insert options for multiple choice/true false
            if (!empty($options)) {
                $stmt = $pdo->prepare("INSERT INTO question_options (question_id, option_text, is_correct, option_order) 
                                      VALUES (?, ?, ?, ?)");
                foreach ($options as $option) {
                    $stmt->execute([$question_id, $option['text'], $option['is_correct'], $option['order']]);
                }
            }
            
            $pdo->commit();
            
            if ($question_bank_id) {
                header('Location: ' . BASE_URL . 'modules/examinations/questions.php?bank_id=' . $question_bank_id . '&success=Question added successfully');
            } else {
                header('Location: ' . BASE_URL . 'modules/examinations/question_banks.php?success=Question added successfully');
            }
            exit;
        } catch (PDOException $e) {
            $pdo->rollBack();
            $error = 'Failed to create question: ' . $e->getMessage();
        }
    }
}

include __DIR__ . '/../../includes/header.php';
?>

<div class="page-container">
    <div class="page-header">
        <h1><i class="fas fa-plus-circle"></i> Add Question</h1>
        <a href="<?php echo BASE_URL; ?>modules/examinations/question_banks.php" class="btn btn-secondary">
            <i class="fas fa-arrow-left"></i> Back to Question Banks
        </a>
    </div>

    <?php if ($error): ?>
        <div class="alert alert-error">
            <i class="fas fa-exclamation-circle"></i> <?php echo sanitizeOutput($error); ?>
        </div>
    <?php endif; ?>

    <?php if ($table_exists): ?>
    <div class="card">
        <form method="POST" action="" class="form" id="questionForm">
            <div class="form-section">
                <h3>Question Details</h3>
                
                <div class="form-group">
                    <label for="question_bank_id">Question Bank</label>
                    <select id="question_bank_id" name="question_bank_id" class="form-control">
                        <option value="">None (Standalone Question)</option>
                        <?php foreach ($question_banks as $bank): ?>
                            <option value="<?php echo $bank['question_bank_id']; ?>" 
                                    <?php echo ($question_bank_id == $bank['question_bank_id']) ? 'selected' : ''; ?>>
                                <?php echo sanitizeOutput($bank['name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <small class="text-muted">Select a question bank or leave empty for standalone question</small>
                </div>

                <div class="form-group">
                    <label for="question_type">Question Type <span class="required">*</span></label>
                    <select id="question_type" name="question_type" required class="form-control" onchange="toggleQuestionFields()">
                        <option value="Multiple Choice" <?php echo (($_POST['question_type'] ?? 'Multiple Choice') === 'Multiple Choice') ? 'selected' : ''; ?>>Multiple Choice</option>
                        <option value="True/False" <?php echo (($_POST['question_type'] ?? '') === 'True/False') ? 'selected' : ''; ?>>True/False</option>
                        <option value="Short Answer" <?php echo (($_POST['question_type'] ?? '') === 'Short Answer') ? 'selected' : ''; ?>>Short Answer</option>
                        <option value="Essay" <?php echo (($_POST['question_type'] ?? '') === 'Essay') ? 'selected' : ''; ?>>Essay</option>
                        <option value="Fill in the Blank" <?php echo (($_POST['question_type'] ?? '') === 'Fill in the Blank') ? 'selected' : ''; ?>>Fill in the Blank</option>
                    </select>
                </div>

                <div class="form-group">
                    <label for="question_text">Question Text <span class="required">*</span></label>
                    <textarea id="question_text" name="question_text" required class="form-control" rows="4"
                              placeholder="Enter the question"><?php echo sanitizeOutput($_POST['question_text'] ?? ''); ?></textarea>
                </div>

                <div class="form-group">
                    <label for="points">Points <span class="required">*</span></label>
                    <input type="number" id="points" name="points" required step="0.01" min="0.01" class="form-control"
                           value="<?php echo sanitizeOutput($_POST['points'] ?? '1.00'); ?>">
                </div>
            </div>

            <div class="form-section" id="options_section">
                <h3>Options</h3>
                <div id="options_container">
                    <div class="option-item" style="margin-bottom: 1rem; padding: 1rem; border: 1px solid #ddd; border-radius: 5px;">
                        <div style="display: flex; gap: 1rem; align-items: center;">
                            <input type="text" name="options[0][text]" class="form-control" placeholder="Option text" required>
                            <label style="display: flex; align-items: center; gap: 0.5rem; white-space: nowrap;">
                                <input type="checkbox" name="options[0][is_correct]" value="1"> Correct
                            </label>
                            <button type="button" class="btn btn-sm btn-danger" onclick="removeOption(this)">
                                <i class="fas fa-times"></i>
                            </button>
                        </div>
                    </div>
                </div>
                <button type="button" class="btn btn-secondary" onclick="addOption()">
                    <i class="fas fa-plus"></i> Add Option
                </button>
            </div>

            <div class="form-section" id="answer_section" style="display: none;">
                <h3>Correct Answer</h3>
                <div class="form-group">
                    <label for="correct_answer">Correct Answer <span class="required">*</span></label>
                    <textarea id="correct_answer" name="correct_answer" class="form-control" rows="3"
                              placeholder="Enter the correct answer"><?php echo sanitizeOutput($_POST['correct_answer'] ?? ''); ?></textarea>
                </div>
            </div>

            <div class="form-section">
                <h3>Additional Information</h3>
                <div class="form-group">
                    <label for="explanation">Explanation</label>
                    <textarea id="explanation" name="explanation" class="form-control" rows="3"
                              placeholder="Enter explanation for the answer (shown after exam)"><?php echo sanitizeOutput($_POST['explanation'] ?? ''); ?></textarea>
                </div>
            </div>

            <div class="form-actions">
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-save"></i> Save Question
                </button>
                <a href="<?php echo BASE_URL; ?>modules/examinations/question_banks.php" class="btn btn-secondary">
                    <i class="fas fa-times"></i> Cancel
                </a>
            </div>
        </form>
    </div>
    <?php endif; ?>
</div>

<script>
let optionCount = 1;

function addOption() {
    const container = document.getElementById('options_container');
    const newOption = document.createElement('div');
    newOption.className = 'option-item';
    newOption.style.cssText = 'margin-bottom: 1rem; padding: 1rem; border: 1px solid #ddd; border-radius: 5px;';
    newOption.innerHTML = `
        <div style="display: flex; gap: 1rem; align-items: center;">
            <input type="text" name="options[${optionCount}][text]" class="form-control" placeholder="Option text" required>
            <label style="display: flex; align-items: center; gap: 0.5rem; white-space: nowrap;">
                <input type="checkbox" name="options[${optionCount}][is_correct]" value="1"> Correct
            </label>
            <button type="button" class="btn btn-sm btn-danger" onclick="removeOption(this)">
                <i class="fas fa-times"></i>
            </button>
        </div>
    `;
    container.appendChild(newOption);
    optionCount++;
}

function removeOption(btn) {
    const container = document.getElementById('options_container');
    if (container.children.length > 1) {
        btn.closest('.option-item').remove();
    } else {
        alert('At least one option is required.');
    }
}

function toggleQuestionFields() {
    const questionType = document.getElementById('question_type').value;
    const optionsSection = document.getElementById('options_section');
    const answerSection = document.getElementById('answer_section');
    const correctAnswerInput = document.getElementById('correct_answer');
    
    if (questionType === 'Multiple Choice' || questionType === 'True/False') {
        optionsSection.style.display = 'block';
        answerSection.style.display = 'none';
        correctAnswerInput.required = false;
        
        // For True/False, set up default options
        if (questionType === 'True/False' && optionCount === 1) {
            const container = document.getElementById('options_container');
            container.innerHTML = `
                <div class="option-item" style="margin-bottom: 1rem; padding: 1rem; border: 1px solid #ddd; border-radius: 5px;">
                    <div style="display: flex; gap: 1rem; align-items: center;">
                        <input type="text" name="options[0][text]" class="form-control" value="True" required>
                        <label style="display: flex; align-items: center; gap: 0.5rem; white-space: nowrap;">
                            <input type="checkbox" name="options[0][is_correct]" value="1"> Correct
                        </label>
                    </div>
                </div>
                <div class="option-item" style="margin-bottom: 1rem; padding: 1rem; border: 1px solid #ddd; border-radius: 5px;">
                    <div style="display: flex; gap: 1rem; align-items: center;">
                        <input type="text" name="options[1][text]" class="form-control" value="False" required>
                        <label style="display: flex; align-items: center; gap: 0.5rem; white-space: nowrap;">
                            <input type="checkbox" name="options[1][is_correct]" value="1"> Correct
                        </label>
                    </div>
                </div>
            `;
            optionCount = 2;
        }
    } else {
        optionsSection.style.display = 'none';
        answerSection.style.display = 'block';
        correctAnswerInput.required = true;
    }
}

// Initialize
document.addEventListener('DOMContentLoaded', function() {
    toggleQuestionFields();
});
</script>

<?php include __DIR__ . '/../../includes/footer.php'; ?>

