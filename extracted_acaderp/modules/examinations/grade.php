<?php
/**
 * Grade Exam Attempt
 * Manual grading interface for essay questions and review
 */

require_once __DIR__ . '/../../config/config.php';
requireAuth([ROLE_ADMIN, ROLE_FACULTY]);

$page_title = 'Grade Exam';
$pdo = getDBConnection();
$attempt_id = (int)($_GET['attempt_id'] ?? 0);

if (!$attempt_id) {
    header('Location: ' . BASE_URL . 'modules/examinations/index.php?error=Invalid attempt ID');
    exit;
}

// Get attempt details
$stmt = $pdo->prepare("SELECT ea.*, e.title, e.total_points, e.exam_id, s.student_number, s.first_name, s.last_name
                      FROM exam_attempts ea
                      INNER JOIN examinations e ON ea.exam_id = e.exam_id
                      INNER JOIN students s ON ea.student_id = s.student_id
                      WHERE ea.attempt_id = ?");
$stmt->execute([$attempt_id]);
$attempt = $stmt->fetch();

if (!$attempt) {
    header('Location: ' . BASE_URL . 'modules/examinations/index.php?error=Attempt not found');
    exit;
}

// Get questions and answers
$stmt = $pdo->prepare("SELECT q.*, eq.points as exam_points, eq.question_order,
                      ea.answer_id, ea.answer_text, ea.option_id, ea.points_earned, ea.is_correct, ea.feedback
                      FROM exam_questions eq
                      INNER JOIN questions q ON eq.question_id = q.question_id
                      LEFT JOIN exam_answers ea ON eq.question_id = ea.question_id AND ea.attempt_id = ?
                      WHERE eq.exam_id = ?
                      ORDER BY eq.question_order");
$stmt->execute([$attempt_id, $attempt['exam_id']]);
$questions = $stmt->fetchAll();

// Get options for each question
foreach ($questions as &$question) {
    if (in_array($question['question_type'], ['Multiple Choice', 'True/False'])) {
        $stmt = $pdo->prepare("SELECT * FROM question_options WHERE question_id = ? ORDER BY option_order");
        $stmt->execute([$question['question_id']]);
        $question['options'] = $stmt->fetchAll();
    }
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $pdo->beginTransaction();
        
        $total_points = 0;
        
        // Process each answer
        if (isset($_POST['grades']) && is_array($_POST['grades'])) {
            foreach ($_POST['grades'] as $answer_id => $grade_data) {
                $answer_id = (int)$answer_id;
                $points_earned = isset($grade_data['points']) ? (float)$grade_data['points'] : 0;
                $feedback = isset($grade_data['feedback']) ? sanitizeInput($grade_data['feedback']) : null;
                
                // Get question points limit
                $stmt = $pdo->prepare("SELECT eq.points as exam_points 
                                      FROM exam_answers ea
                                      INNER JOIN exam_questions eq ON ea.question_id = eq.question_id
                                      WHERE ea.answer_id = ?");
                $stmt->execute([$answer_id]);
                $answer_info = $stmt->fetch();
                
                if ($answer_info) {
                    $max_points = (float)$answer_info['exam_points'];
                    $points_earned = min($points_earned, $max_points); // Cap at max points
                    
                    // Update answer
                    $stmt = $pdo->prepare("UPDATE exam_answers SET points_earned = ?, feedback = ?, 
                                          graded_at = NOW(), graded_by = ?, is_correct = ?
                                          WHERE answer_id = ?");
                    $is_correct = ($points_earned >= $max_points * 0.9) ? 1 : 0; // 90% or more = correct
                    $stmt->execute([$points_earned, $feedback, $_SESSION['user_id'], $is_correct, $answer_id]);
                    
                    $total_points += $points_earned;
                }
            }
        }
        
        // Calculate percentage
        $percentage = $attempt['total_points'] > 0 ? ($total_points / $attempt['total_points']) * 100 : 0;
        
        // Update attempt
        $stmt = $pdo->prepare("UPDATE exam_attempts SET score = ?, total_points = ?, percentage = ?, 
                              status = 'Graded', updated_at = NOW()
                              WHERE attempt_id = ?");
        $stmt->execute([$total_points, $total_points, $percentage, $attempt_id]);
        
        $pdo->commit();
        header('Location: ' . BASE_URL . 'modules/examinations/attempt_view.php?attempt_id=' . $attempt_id . '&success=Exam graded successfully');
        exit;
    } catch (PDOException $e) {
        $pdo->rollBack();
        $error = 'Failed to grade exam: ' . $e->getMessage();
    }
}

include __DIR__ . '/../../includes/header.php';
?>

<div class="page-container">
    <div class="page-header">
        <h1><i class="fas fa-check"></i> Grade Exam: <?php echo sanitizeOutput($attempt['title']); ?></h1>
        <a href="<?php echo BASE_URL; ?>modules/examinations/results.php?id=<?php echo $attempt['exam_id']; ?>" class="btn btn-secondary">
            <i class="fas fa-arrow-left"></i> Back to Results
        </a>
    </div>

    <?php if (isset($error)): ?>
        <div class="alert alert-error">
            <i class="fas fa-exclamation-circle"></i> <?php echo sanitizeOutput($error); ?>
        </div>
    <?php endif; ?>

    <div class="card" style="margin-bottom: 1.5rem;">
        <h2>Student: <?php echo sanitizeOutput($attempt['first_name'] . ' ' . $attempt['last_name']); ?> (<?php echo sanitizeOutput($attempt['student_number']); ?>)</h2>
        <p><strong>Total Points:</strong> <?php echo number_format($attempt['total_points'], 2); ?></p>
    </div>

    <form method="POST" action="" class="form">
        <div class="card">
            <h2>Grade Questions</h2>
            <?php foreach ($questions as $idx => $question): 
                $points_earned = (float)($question['points_earned'] ?? 0);
                $max_points = (float)$question['exam_points'];
            ?>
            <div style="border: 1px solid #ddd; border-radius: 5px; padding: 1.5rem; margin-bottom: 1.5rem; background: #fff;">
                <div style="display: flex; justify-content: space-between; align-items: start; margin-bottom: 1rem;">
                    <div>
                        <h3 style="margin: 0;">Question <?php echo $idx + 1; ?></h3>
                        <span class="badge badge-info"><?php echo sanitizeOutput($question['question_type']); ?></span>
                        <span class="badge badge-success">Max: <?php echo number_format($max_points, 2); ?> points</span>
                    </div>
                </div>
                
                <div style="margin-bottom: 1rem;">
                    <p style="font-weight: 500;"><?php echo nl2br(sanitizeOutput($question['question_text'])); ?></p>
                </div>

                <?php if (in_array($question['question_type'], ['Multiple Choice', 'True/False']) && !empty($question['options'])): ?>
                    <div style="margin-bottom: 1rem;">
                        <strong>Student Answer:</strong>
                        <div style="margin-top: 0.5rem;">
                            <?php 
                            $selected_option = null;
                            if ($question['option_id']) {
                                foreach ($question['options'] as $opt) {
                                    if ($opt['option_id'] == $question['option_id']) {
                                        $selected_option = $opt;
                                        break;
                                    }
                                }
                            }
                            ?>
                            <?php if ($selected_option): ?>
                                <div style="padding: 0.5rem; background: <?php echo $selected_option['is_correct'] ? '#d1fae5' : '#fee2e2'; ?>; border-radius: 3px; display: inline-block;">
                                    <?php echo sanitizeOutput($selected_option['option_text']); ?>
                                </div>
                            <?php else: ?>
                                <span class="text-muted">No answer provided</span>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php else: ?>
                    <div style="margin-bottom: 1rem;">
                        <strong>Student Answer:</strong>
                        <div style="margin-top: 0.5rem; padding: 1rem; background: #f0f0f0; border-radius: 3px;">
                            <?php echo $question['answer_text'] ? nl2br(sanitizeOutput($question['answer_text'])) : '<span class="text-muted">No answer provided</span>'; ?>
                        </div>
                    </div>
                <?php endif; ?>

                <?php if (!empty($question['correct_answer'])): ?>
                <div style="margin-bottom: 1rem;">
                    <strong>Correct Answer:</strong>
                    <div style="margin-top: 0.5rem; padding: 0.5rem; background: #d1fae5; border-radius: 3px; color: green; font-weight: bold;">
                        <?php echo nl2br(sanitizeOutput($question['correct_answer'])); ?>
                    </div>
                </div>
                <?php endif; ?>

                <div class="form-row">
                    <div class="form-group">
                        <label for="points_<?php echo $question['answer_id']; ?>">Points Earned (Max: <?php echo number_format($max_points, 2); ?>)</label>
                        <input type="number" 
                               id="points_<?php echo $question['answer_id']; ?>" 
                               name="grades[<?php echo $question['answer_id']; ?>][points]" 
                               step="0.01" 
                               min="0" 
                               max="<?php echo $max_points; ?>"
                               class="form-control"
                               value="<?php echo number_format($points_earned, 2); ?>"
                               required>
                    </div>
                    <div class="form-group" style="flex: 2;">
                        <label for="feedback_<?php echo $question['answer_id']; ?>">Feedback (Optional)</label>
                        <textarea id="feedback_<?php echo $question['answer_id']; ?>" 
                                  name="grades[<?php echo $question['answer_id']; ?>][feedback]" 
                                  class="form-control" 
                                  rows="2"
                                  placeholder="Enter feedback for the student"><?php echo sanitizeOutput($question['feedback'] ?? ''); ?></textarea>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>

        <div class="form-actions">
            <button type="submit" class="btn btn-primary">
                <i class="fas fa-save"></i> Save Grades
            </button>
            <a href="<?php echo BASE_URL; ?>modules/examinations/results.php?id=<?php echo $attempt['exam_id']; ?>" class="btn btn-secondary">
                <i class="fas fa-times"></i> Cancel
            </a>
        </div>
    </form>
</div>

<?php include __DIR__ . '/../../includes/footer.php'; ?>

