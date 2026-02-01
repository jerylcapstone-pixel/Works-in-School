<?php
/**
 * View Exam Attempt
 * Faculty/Admin view of individual student attempt
 */

require_once __DIR__ . '/../../config/config.php';
requireAuth([ROLE_ADMIN, ROLE_FACULTY]);

$page_title = 'View Exam Attempt';
$pdo = getDBConnection();
$attempt_id = (int)($_GET['attempt_id'] ?? 0);

if (!$attempt_id) {
    header('Location: ' . BASE_URL . 'modules/examinations/index.php?error=Invalid attempt ID');
    exit;
}

// Get attempt details
$stmt = $pdo->prepare("SELECT ea.*, e.title, e.total_points, s.student_number, s.first_name, s.last_name
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

// Get exam details
$stmt = $pdo->prepare("SELECT e.*, c.course_name, cs.section_number
                      FROM examinations e
                      LEFT JOIN courses c ON e.course_id = c.course_id
                      LEFT JOIN class_sections cs ON e.section_id = cs.section_id
                      WHERE e.exam_id = ?");
$stmt->execute([$attempt['exam_id']]);
$exam = $stmt->fetch();

// Get questions and answers
$stmt = $pdo->prepare("SELECT q.*, eq.points as exam_points, eq.question_order,
                      ea.answer_text, ea.option_id, ea.points_earned, ea.is_correct, ea.feedback
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

include __DIR__ . '/../../includes/header.php';
?>

<div class="page-container">
    <div class="page-header">
        <h1><i class="fas fa-eye"></i> Exam Attempt: <?php echo sanitizeOutput($exam['title']); ?></h1>
        <div style="display: flex; gap: 0.5rem;">
            <a href="<?php echo BASE_URL; ?>modules/examinations/results.php?id=<?php echo $attempt['exam_id']; ?>" class="btn btn-secondary">
                <i class="fas fa-arrow-left"></i> Back to Results
            </a>
            <?php if ($attempt['status'] === 'Submitted'): ?>
            <a href="<?php echo BASE_URL; ?>modules/examinations/grade.php?attempt_id=<?php echo $attempt_id; ?>" class="btn btn-warning">
                <i class="fas fa-check"></i> Grade
            </a>
            <?php endif; ?>
        </div>
    </div>

    <div class="card" style="margin-bottom: 1.5rem;">
        <h2>Student Information</h2>
        <div class="info-grid">
            <div>
                <strong>Student:</strong>
                <span><?php echo sanitizeOutput($attempt['first_name'] . ' ' . $attempt['last_name']); ?></span>
            </div>
            <div>
                <strong>Student Number:</strong>
                <span><?php echo sanitizeOutput($attempt['student_number']); ?></span>
            </div>
            <div>
                <strong>Score:</strong>
                <span><strong><?php echo number_format($attempt['score'], 2); ?> / <?php echo number_format($attempt['total_points'], 2); ?></strong></span>
            </div>
            <div>
                <strong>Percentage:</strong>
                <span class="badge badge-<?php echo $attempt['percentage'] >= 70 ? 'success' : ($attempt['percentage'] >= 50 ? 'warning' : 'danger'); ?>">
                    <?php echo number_format($attempt['percentage'], 2); ?>%
                </span>
            </div>
            <div>
                <strong>Status:</strong>
                <span class="badge badge-<?php echo $attempt['status'] === 'Graded' ? 'success' : 'info'; ?>">
                    <?php echo sanitizeOutput($attempt['status']); ?>
                </span>
            </div>
            <div>
                <strong>Time Taken:</strong>
                <span><?php echo $attempt['time_taken'] ? gmdate('H:i:s', $attempt['time_taken']) : 'N/A'; ?></span>
            </div>
        </div>
    </div>

    <div class="card">
        <h2>Answers Review</h2>
        <?php foreach ($questions as $idx => $question): 
            $points_earned = (float)($question['points_earned'] ?? 0);
            $is_correct = (int)($question['is_correct'] ?? 0);
        ?>
        <div style="border: 1px solid #ddd; border-radius: 5px; padding: 1.5rem; margin-bottom: 1.5rem; background: #fff;
                    border-left: 4px solid <?php echo $is_correct ? 'green' : 'red'; ?>;">
            <div style="display: flex; justify-content: space-between; align-items: start; margin-bottom: 1rem;">
                <div>
                    <h3 style="margin: 0;">Question <?php echo $idx + 1; ?></h3>
                    <span class="badge badge-info"><?php echo sanitizeOutput($question['question_type']); ?></span>
                </div>
                <div style="text-align: right;">
                    <div style="font-size: 1.2rem; font-weight: bold; color: <?php echo $is_correct ? 'green' : 'red'; ?>;">
                        <?php echo number_format($points_earned, 2); ?> / <?php echo number_format($question['exam_points'], 2); ?> points
                    </div>
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
                            <div style="padding: 0.5rem; background: <?php echo $is_correct ? '#d1fae5' : '#fee2e2'; ?>; border-radius: 3px; display: inline-block;">
                                <?php echo sanitizeOutput($selected_option['option_text']); ?>
                            </div>
                        <?php else: ?>
                            <span class="text-muted">No answer provided</span>
                        <?php endif; ?>
                    </div>
                </div>
                
                <div style="margin-bottom: 1rem;">
                    <strong>Correct Answer:</strong>
                    <div style="margin-top: 0.5rem;">
                        <?php 
                        $correct_option = null;
                        foreach ($question['options'] as $opt) {
                            if ($opt['is_correct']) {
                                $correct_option = $opt;
                                break;
                            }
                        }
                        ?>
                        <?php if ($correct_option): ?>
                            <div style="padding: 0.5rem; background: #d1fae5; border-radius: 3px; display: inline-block; color: green; font-weight: bold;">
                                <?php echo sanitizeOutput($correct_option['option_text']); ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            <?php else: ?>
                <div style="margin-bottom: 1rem;">
                    <strong>Student Answer:</strong>
                    <div style="margin-top: 0.5rem; padding: 0.5rem; background: #f0f0f0; border-radius: 3px;">
                        <?php echo $question['answer_text'] ? nl2br(sanitizeOutput($question['answer_text'])) : '<span class="text-muted">No answer provided</span>'; ?>
                    </div>
                </div>
                
                <?php if (!empty($question['correct_answer'])): ?>
                <div style="margin-bottom: 1rem;">
                    <strong>Correct Answer:</strong>
                    <div style="margin-top: 0.5rem; padding: 0.5rem; background: #d1fae5; border-radius: 3px; color: green; font-weight: bold;">
                        <?php echo nl2br(sanitizeOutput($question['correct_answer'])); ?>
                    </div>
                </div>
                <?php endif; ?>
            <?php endif; ?>

            <?php if (!empty($question['explanation'])): ?>
                <div style="margin-top: 1rem; padding-top: 1rem; border-top: 1px solid #eee;">
                    <strong>Explanation:</strong>
                    <p style="margin-top: 0.5rem; color: #666;"><?php echo nl2br(sanitizeOutput($question['explanation'])); ?></p>
                </div>
            <?php endif; ?>

            <?php if (!empty($question['feedback'])): ?>
                <div style="margin-top: 1rem; padding-top: 1rem; border-top: 1px solid #eee;">
                    <strong>Feedback:</strong>
                    <p style="margin-top: 0.5rem; color: var(--primary-color);"><?php echo nl2br(sanitizeOutput($question['feedback'])); ?></p>
                </div>
            <?php endif; ?>
        </div>
        <?php endforeach; ?>
    </div>
</div>

<?php include __DIR__ . '/../../includes/footer.php'; ?>

