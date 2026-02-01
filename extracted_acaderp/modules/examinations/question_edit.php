<?php
/**
 * Edit Question
 * Form to edit existing question
 */

require_once __DIR__ . '/../../config/config.php';
requireAuth([ROLE_ADMIN, ROLE_FACULTY]);

$page_title = 'Edit Question';
$pdo = getDBConnection();
$error = '';
$question_id = (int)($_GET['id'] ?? 0);

if (!$question_id) {
    header('Location: ' . BASE_URL . 'modules/examinations/question_banks.php?error=Invalid question ID');
    exit;
}

// Get question data
$question = null;
try {
    $stmt = $pdo->prepare("SELECT * FROM questions WHERE question_id = ?");
    $stmt->execute([$question_id]);
    $question = $stmt->fetch();
} catch (PDOException $e) {
    $error = 'Failed to load question.';
}

if (!$question) {
    header('Location: ' . BASE_URL . 'modules/examinations/question_banks.php?error=Question not found');
    exit;
}

// Check permissions
if (hasRole(ROLE_FACULTY) && !hasRole(ROLE_ADMIN)) {
    if ($question['created_by'] != $_SESSION['user_id']) {
        header('Location: ' . BASE_URL . 'modules/examinations/question_banks.php?error=Access denied');
        exit;
    }
}

// Get options
$options = [];
if (in_array($question['question_type'], ['Multiple Choice', 'True/False'])) {
    $stmt = $pdo->prepare("SELECT * FROM question_options WHERE question_id = ? ORDER BY option_order");
    $stmt->execute([$question_id]);
    $options = $stmt->fetchAll();
}

// Get question banks
$question_banks = [];
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

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $question_bank_id = !empty($_POST['question_bank_id']) ? (int)$_POST['question_bank_id'] : null;
    $question_text = sanitizeInput($_POST['question_text'] ?? '');
    $question_type = sanitizeInput($_POST['question_type'] ?? 'Multiple Choice');
    $points = !empty($_POST['points']) ? (float)$_POST['points'] : 1.00;
    $correct_answer = sanitizeInput($_POST['correct_answer'] ?? '');
    $explanation = sanitizeInput($_POST['explanation'] ?? '');
    
    // Get options
    $new_options = [];
    if ($question_type === 'Multiple Choice' || $question_type === 'True/False') {
        if (isset($_POST['options']) && is_array($_POST['options'])) {
            foreach ($_POST['options'] as $idx => $option) {
                $option_text = sanitizeInput($option['text'] ?? '');
                $is_correct = isset($option['is_correct']) ? 1 : 0;
                if (!empty($option_text)) {
                    $new_options[] = ['text' => $option_text, 'is_correct' => $is_correct, 'order' => $idx];
                }
            }
        }
    }

    if (empty($question_text)) {
        $error = 'Question text is required.';
    } elseif (in_array($question_type, ['Multiple Choice', 'True/False']) && empty($new_options)) {
        $error = 'At least one option is required for ' . $question_type . ' questions.';
    } elseif (in_array($question_type, ['Multiple Choice', 'True/False']) && 
              !in_array(1, array_column($new_options, 'is_correct'))) {
        $error = 'At least one correct option is required.';
    } elseif (!in_array($question_type, ['Multiple Choice', 'True/False']) && empty($correct_answer)) {
        $error = 'Correct answer is required for ' . $question_type . ' questions.';
    } else {
        try {
            $pdo->beginTransaction();
            
            // Update question
            $stmt = $pdo->prepare("UPDATE questions SET question_bank_id = ?, question_text = ?, question_type = ?, 
                                  points = ?, correct_answer = ?, explanation = ?, updated_at = CURRENT_TIMESTAMP 
                                  WHERE question_id = ?");
            $stmt->execute([$question_bank_id, $question_text, $question_type, $points, $correct_answer, $explanation, $question_id]);
            
            // Delete old options and insert new ones
            if (in_array($question_type, ['Multiple Choice', 'True/False'])) {
                $stmt = $pdo->prepare("DELETE FROM question_options WHERE question_id = ?");
                $stmt->execute([$question_id]);
                
                if (!empty($new_options)) {
                    $stmt = $pdo->prepare("INSERT INTO question_options (question_id, option_text, is_correct, option_order) 
                                          VALUES (?, ?, ?, ?)");
                    foreach ($new_options as $option) {
                        $stmt->execute([$question_id, $option['text'], $option['is_correct'], $option['order']]);
                    }
                }
            }
            
            $pdo->commit();
            
            if ($question_bank_id) {
                header('Location: ' . BASE_URL . 'modules/examinations/questions.php?bank_id=' . $question_bank_id . '&success=Question updated successfully');
            } else {
                header('Location: ' . BASE_URL . 'modules/examinations/question_banks.php?success=Question updated successfully');
            }
            exit;
        } catch (PDOException $e) {
            $pdo->rollBack();
            $error = 'Failed to update question: ' . $e->getMessage();
        }
    }
    
    // Update $question with POST data
    $question = array_merge($question, $_POST);
    if (!empty($new_options)) {
        $options = $new_options;
    }
}

include __DIR__ . '/../../includes/header.php';
?>

<div class="page-container">
    <div class="page-header">
        <h1><i class="fas fa-edit"></i> Edit Question</h1>
        <a href="<?php echo BASE_URL; ?>modules/examinations/question_banks.php" class="btn btn-secondary">
            <i class="fas fa-arrow-left"></i> Back to Question Banks
        </a>
    </div>

    <?php if ($error): ?>
        <div class="alert alert-error">
            <i class="fas fa-exclamation-circle"></i> <?php echo sanitizeOutput($error); ?>
        </div>
    <?php endif; ?>

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
                                    <?php echo ($question['question_bank_id'] == $bank['question_bank_id']) ? 'selected' : ''; ?>>
                                <?php echo sanitizeOutput($bank['name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label for="question_type">Question Type <span class="required">*</span></label>
                    <select id="question_type" name="question_type" required class="form-control" onchange="toggleQuestionFields()">
                        <option value="Multiple Choice" <?php echo $question['question_type'] === 'Multiple Choice' ? 'selected' : ''; ?>>Multiple Choice</option>
                        <option value="True/False" <?php echo $question['question_type'] === 'True/False' ? 'selected' : ''; ?>>True/False</option>
                        <option value="Short Answer" <?php echo $question['question_type'] === 'Short Answer' ? 'selected' : ''; ?>>Short Answer</option>
                        <option value="Essay" <?php echo $question['question_type'] === 'Essay' ? 'selected' : ''; ?>>Essay</option>
                        <option value="Fill in the Blank" <?php echo $question['question_type'] === 'Fill in the Blank' ? 'selected' : ''; ?>>Fill in the Blank</option>
                    </select>
                </div>

                <div class="form-group">
                    <label for="question_text">Question Text <span class="required">*</span></label>
                    <textarea id="question_text" name="question_text" required class="form-control" rows="4"
                              placeholder="Enter the question"><?php echo sanitizeOutput($question['question_text']); ?></textarea>
                </div>

                <div class="form-group">
                    <label for="points">Points <span class="required">*</span></label>
                    <input type="number" id="points" name="points" required step="0.01" min="0.01" class="form-control"
                           value="<?php echo sanitizeOutput($question['points']); ?>">
                </div>
            </div>

            <div class="form-section" id="options_section" style="display: <?php echo in_array($question['question_type'], ['Multiple Choice', 'True/False']) ? 'block' : 'none'; ?>;">
                <h3>Options</h3>
                <div id="options_container">
                    <?php if (!empty($options)): ?>
                        <?php foreach ($options as $idx => $option): ?>
                        <div class="option-item" style="margin-bottom: 1rem; padding: 1rem; border: 1px solid #ddd; border-radius: 5px;">
                            <div style="display: flex; gap: 1rem; align-items: center;">
                                <input type="text" name="options[<?php echo $idx; ?>][text]" class="form-control" 
                                       value="<?php echo sanitizeOutput($option['option_text']); ?>" required>
                                <label style="display: flex; align-items: center; gap: 0.5rem; white-space: nowrap;">
                                    <input type="checkbox" name="options[<?php echo $idx; ?>][is_correct]" value="1" 
                                           <?php echo $option['is_correct'] ? 'checked' : ''; ?>> Correct
                                </label>
                                <button type="button" class="btn btn-sm btn-danger" onclick="removeOption(this)">
                                    <i class="fas fa-times"></i>
                                </button>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    <?php else: ?>
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
                    <?php endif; ?>
                </div>
                <button type="button" class="btn btn-secondary" onclick="addOption()">
                    <i class="fas fa-plus"></i> Add Option
                </button>
            </div>

            <div class="form-section" id="answer_section" style="display: <?php echo !in_array($question['question_type'], ['Multiple Choice', 'True/False']) ? 'block' : 'none'; ?>;">
                <h3>Correct Answer</h3>
                <div class="form-group">
                    <label for="correct_answer">Correct Answer <span class="required">*</span></label>
                    <textarea id="correct_answer" name="correct_answer" class="form-control" rows="3"
                              placeholder="Enter the correct answer"><?php echo sanitizeOutput($question['correct_answer']); ?></textarea>
                </div>
            </div>

            <div class="form-section">
                <h3>Additional Information</h3>
                <div class="form-group">
                    <label for="explanation">Explanation</label>
                    <textarea id="explanation" name="explanation" class="form-control" rows="3"
                              placeholder="Enter explanation for the answer"><?php echo sanitizeOutput($question['explanation']); ?></textarea>
                </div>
            </div>

            <div class="form-actions">
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-save"></i> Update Question
                </button>
                <a href="<?php echo BASE_URL; ?>modules/examinations/question_banks.php" class="btn btn-secondary">
                    <i class="fas fa-times"></i> Cancel
                </a>
            </div>
        </form>
    </div>
</div>

<script>
let optionCount = <?php echo count($options); ?>;

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
    } else {
        optionsSection.style.display = 'none';
        answerSection.style.display = 'block';
        correctAnswerInput.required = true;
    }
}
</script>

<?php include __DIR__ . '/../../includes/footer.php'; ?>

