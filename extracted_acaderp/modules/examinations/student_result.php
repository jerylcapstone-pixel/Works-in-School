<?php
/**
 * Student Exam Result
 * View exam results and feedback
 */

require_once __DIR__ . '/../../config/config.php';
requireAuth([ROLE_STUDENT]);

$page_title = 'Exam Results';
$pdo = getDBConnection();
$exam_id = (int)($_GET['exam_id'] ?? 0);
$attempt_id = (int)($_GET['attempt_id'] ?? 0);

if (!$exam_id) {
    header('Location: ' . BASE_URL . 'modules/examinations/student_exams.php?error=Invalid exam ID');
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
    header('Location: ' . BASE_URL . 'modules/examinations/student_exams.php?error=Student record not found');
    exit;
}

// Get attempt details - if attempt_id is provided, use it; otherwise get the latest attempt
if ($attempt_id) {
    $stmt = $pdo->prepare("SELECT ea.*, e.title, e.show_results_immediately, e.total_points
                          FROM exam_attempts ea
                          INNER JOIN examinations e ON ea.exam_id = e.exam_id
                          WHERE ea.attempt_id = ? AND ea.student_id = ? AND ea.exam_id = ?");
    $stmt->execute([$attempt_id, $student_id, $exam_id]);
    $attempt = $stmt->fetch();
} else {
    // Get the latest attempt for this exam
    $stmt = $pdo->prepare("SELECT ea.*, e.title, e.show_results_immediately, e.total_points
                          FROM exam_attempts ea
                          INNER JOIN examinations e ON ea.exam_id = e.exam_id
                          WHERE ea.exam_id = ? AND ea.student_id = ?
                          ORDER BY ea.started_at DESC
                          LIMIT 1");
    $stmt->execute([$exam_id, $student_id]);
    $attempt = $stmt->fetch();
    
    if ($attempt) {
        $attempt_id = $attempt['attempt_id'];
    }
}

if (!$attempt) {
    header('Location: ' . BASE_URL . 'modules/examinations/student_exams.php?error=No exam attempt found. You must complete an exam before viewing results.');
    exit;
}

// Get exam details
$stmt = $pdo->prepare("SELECT e.*, c.course_name, cs.section_number
                      FROM examinations e
                      LEFT JOIN courses c ON e.course_id = c.course_id
                      LEFT JOIN class_sections cs ON e.section_id = cs.section_id
                      WHERE e.exam_id = ?");
$stmt->execute([$exam_id]);
$exam = $stmt->fetch();

// Get questions and answers
$stmt = $pdo->prepare("SELECT q.*, eq.points as exam_points, eq.question_order,
                      ea.answer_text, ea.option_id, ea.points_earned, ea.is_correct, ea.feedback
                      FROM exam_questions eq
                      INNER JOIN questions q ON eq.question_id = q.question_id
                      LEFT JOIN exam_answers ea ON eq.question_id = ea.question_id AND ea.attempt_id = ?
                      WHERE eq.exam_id = ?
                      ORDER BY eq.question_order");
$stmt->execute([$attempt_id, $exam_id]);
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

<style>
.result-header-card {
    background: linear-gradient(135deg, #1e40af 0%, #2563eb 100%);
    border-radius: 20px;
    padding: 2rem;
    color: white;
    margin-bottom: 2rem;
    box-shadow: 0 8px 24px rgba(30, 64, 175, 0.3);
}

.result-header-title {
    display: flex;
    align-items: center;
    gap: 1rem;
    margin-bottom: 1.5rem;
}

.result-header-title i {
    font-size: 2.5rem;
    color: #ffd700;
}

.result-header-title h1 {
    margin: 0;
    font-size: 2rem;
    font-weight: 700;
}

.stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 1.5rem;
    margin-top: 1.5rem;
}

.stat-card {
    background: rgba(255, 255, 255, 0.15);
    backdrop-filter: blur(10px);
    border-radius: 16px;
    padding: 1.5rem;
    text-align: center;
    border: 2px solid rgba(255, 255, 255, 0.3);
    transition: transform 0.3s, box-shadow 0.3s;
}

.stat-card:hover {
    transform: translateY(-4px);
    box-shadow: 0 8px 16px rgba(0, 0, 0, 0.2);
}

.stat-value {
    font-size: 2.5rem;
    font-weight: 800;
    margin-bottom: 0.5rem;
    color: #ffd700;
}

.stat-label {
    font-size: 0.875rem;
    opacity: 0.9;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.result-info {
    margin-top: 1.5rem;
    padding-top: 1.5rem;
    border-top: 2px solid rgba(255, 255, 255, 0.3);
    display: flex;
    gap: 2rem;
    flex-wrap: wrap;
}

.result-info-item {
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.result-info-item strong {
    opacity: 0.9;
}

.question-card {
    background: white;
    border: 2px solid #e2e8f0;
    border-radius: 16px;
    padding: 2rem;
    margin-bottom: 2rem;
    transition: all 0.3s;
    position: relative;
}

.question-card:hover {
    border-color: #2563eb;
    box-shadow: 0 4px 12px rgba(30, 64, 175, 0.1);
}

.question-card.correct {
    border-left: 6px solid #22c55e;
}

.question-card.incorrect {
    border-left: 6px solid #ef4444;
}

.question-header {
    display: flex;
    justify-content: space-between;
    align-items: start;
    margin-bottom: 1.5rem;
    padding-bottom: 1rem;
    border-bottom: 2px solid #e2e8f0;
}

.question-number {
    font-size: 1.5rem;
    font-weight: 700;
    color: #1e40af;
}

.question-points {
    font-size: 1.25rem;
    font-weight: 700;
    color: #22c55e;
}

.answer-section {
    margin-top: 1.5rem;
    padding: 1.5rem;
    background: #f8fafc;
    border-radius: 12px;
    border: 2px solid #e2e8f0;
}

.answer-section.your-answer {
    background: #fef2f2;
    border-color: #fecaca;
}

.answer-section.your-answer.correct {
    background: #f0fdf4;
    border-color: #bbf7d0;
}

.answer-section.correct-answer {
    background: #f0fdf4;
    border-color: #22c55e;
}

.answer-label {
    font-weight: 600;
    color: #1e293b;
    margin-bottom: 0.75rem;
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.explanation-box {
    margin-top: 1.5rem;
    padding: 1.5rem;
    background: linear-gradient(135deg, #e0f2fe 0%, #bae6fd 100%);
    border-radius: 12px;
    border: 2px solid #0ea5e9;
}

.feedback-box {
    margin-top: 1.5rem;
    padding: 1.5rem;
    background: linear-gradient(135deg, #fef3c7 0%, #fde68a 100%);
    border-radius: 12px;
    border: 2px solid #f59e0b;
}
</style>

<div class="page-container">
    <div class="page-header">
        <h1><i class="fas fa-chart-line"></i> Exam Results</h1>
        <a href="<?php echo BASE_URL; ?>modules/examinations/student_exams.php" class="btn btn-secondary">
            <i class="fas fa-arrow-left"></i> Back to Exams
        </a>
    </div>

    <div class="result-header-card">
        <div class="result-header-title">
            <i class="fas fa-trophy"></i>
            <h1><?php echo sanitizeOutput($exam['title']); ?></h1>
        </div>
        
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-value"><?php echo number_format($attempt['percentage'], 1); ?>%</div>
                <div class="stat-label">Percentage</div>
            </div>
            <div class="stat-card">
                <div class="stat-value"><?php echo number_format($attempt['score'], 2); ?></div>
                <div class="stat-label">Points Earned</div>
            </div>
            <div class="stat-card">
                <div class="stat-value"><?php echo number_format($exam['total_points'], 2); ?></div>
                <div class="stat-label">Total Points</div>
            </div>
            <div class="stat-card">
                <div class="stat-value"><?php echo $attempt['time_taken'] ? gmdate('H:i:s', $attempt['time_taken']) : 'N/A'; ?></div>
                <div class="stat-label">Time Taken</div>
            </div>
        </div>
        
        <div class="result-info">
            <div class="result-info-item">
                <strong><i class="fas fa-info-circle"></i> Status:</strong>
                <span class="badge badge-<?php echo $attempt['status'] === 'Graded' ? 'success' : 'info'; ?>" style="background: rgba(255, 255, 255, 0.2); color: white; border: 1px solid rgba(255, 255, 255, 0.3);">
                    <?php echo sanitizeOutput($attempt['status']); ?>
                </span>
            </div>
            <div class="result-info-item">
                <strong><i class="fas fa-calendar-check"></i> Submitted:</strong>
                <span><?php echo date('M d, Y g:i A', strtotime($attempt['submitted_at'])); ?></span>
            </div>
            <?php if ($exam['course_name']): ?>
            <div class="result-info-item">
                <strong><i class="fas fa-book"></i> Course:</strong>
                <span><?php echo sanitizeOutput($exam['course_name']); ?></span>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <div class="detail-cards">
        <div class="detail-card">
            <h2><i class="fas fa-list-ul"></i> Question Review (<?php echo count($questions); ?> questions)</h2>
            
            <?php foreach ($questions as $idx => $question): 
                $points_earned = (float)($question['points_earned'] ?? 0);
                $is_correct = (int)($question['is_correct'] ?? 0);
            ?>
            <div class="question-card <?php echo $is_correct ? 'correct' : 'incorrect'; ?>">
                <div class="question-header">
                    <div>
                        <div class="question-number">
                            <i class="fas fa-question-circle"></i>
                            Question <?php echo $idx + 1; ?>
                        </div>
                        <div style="margin-top: 0.5rem;">
                            <span class="badge badge-info"><?php echo sanitizeOutput($question['question_type']); ?></span>
                            <?php if ($is_correct): ?>
                                <span class="badge badge-success">
                                    <i class="fas fa-check"></i> Correct
                                </span>
                            <?php else: ?>
                                <span class="badge badge-danger">
                                    <i class="fas fa-times"></i> Incorrect
                                </span>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="question-points">
                        <?php echo number_format($points_earned, 2); ?> / <?php echo number_format($question['exam_points'], 2); ?> pts
                    </div>
                </div>
                
                <div style="margin-bottom: 1.5rem;">
                    <p style="font-size: 1.1rem; font-weight: 500; color: #1e293b; line-height: 1.7;">
                        <?php echo nl2br(sanitizeOutput($question['question_text'])); ?>
                    </p>
                </div>

                <?php if (in_array($question['question_type'], ['Multiple Choice', 'True/False']) && !empty($question['options'])): ?>
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
                    
                    $correct_option = null;
                    foreach ($question['options'] as $opt) {
                        if ($opt['is_correct']) {
                            $correct_option = $opt;
                            break;
                        }
                    }
                    ?>
                    
                    <div class="answer-section your-answer <?php echo $is_correct ? 'correct' : ''; ?>">
                        <div class="answer-label">
                            <i class="fas fa-user"></i> Your Answer:
                        </div>
                        <?php if ($selected_option): ?>
                            <div style="font-size: 1rem; color: #1e293b;">
                                <?php echo sanitizeOutput($selected_option['option_text']); ?>
                            </div>
                        <?php else: ?>
                            <div style="color: #64748b; font-style: italic;">
                                <i class="fas fa-exclamation-circle"></i> No answer provided
                            </div>
                        <?php endif; ?>
                    </div>
                    
                    <?php if ($correct_option): ?>
                    <div class="answer-section correct-answer">
                        <div class="answer-label">
                            <i class="fas fa-check-circle"></i> Correct Answer:
                        </div>
                        <div style="font-size: 1rem; color: #166534; font-weight: 600;">
                            <?php echo sanitizeOutput($correct_option['option_text']); ?>
                        </div>
                    </div>
                    <?php endif; ?>
                <?php else: ?>
                    <div class="answer-section your-answer <?php echo $is_correct ? 'correct' : ''; ?>">
                        <div class="answer-label">
                            <i class="fas fa-user"></i> Your Answer:
                        </div>
                        <?php if ($question['answer_text']): ?>
                            <div style="font-size: 1rem; color: #1e293b; line-height: 1.7;">
                                <?php echo nl2br(sanitizeOutput($question['answer_text'])); ?>
                            </div>
                        <?php else: ?>
                            <div style="color: #64748b; font-style: italic;">
                                <i class="fas fa-exclamation-circle"></i> No answer provided
                            </div>
                        <?php endif; ?>
                    </div>
                    
                    <?php if (!empty($question['correct_answer'])): ?>
                    <div class="answer-section correct-answer">
                        <div class="answer-label">
                            <i class="fas fa-check-circle"></i> Correct Answer:
                        </div>
                        <div style="font-size: 1rem; color: #166534; font-weight: 600; line-height: 1.7;">
                            <?php echo nl2br(sanitizeOutput($question['correct_answer'])); ?>
                        </div>
                    </div>
                    <?php endif; ?>
                <?php endif; ?>

                <?php if (!empty($question['explanation'])): ?>
                    <div class="explanation-box">
                        <div class="answer-label" style="color: #0c4a6e;">
                            <i class="fas fa-lightbulb"></i> Explanation:
                        </div>
                        <p style="margin: 0.5rem 0 0 0; color: #0c4a6e; line-height: 1.7;">
                            <?php echo nl2br(sanitizeOutput($question['explanation'])); ?>
                        </p>
                    </div>
                <?php endif; ?>

                <?php if (!empty($question['feedback'])): ?>
                    <div class="feedback-box">
                        <div class="answer-label" style="color: #92400e;">
                            <i class="fas fa-comment-dots"></i> Instructor Feedback:
                        </div>
                        <p style="margin: 0.5rem 0 0 0; color: #92400e; line-height: 1.7;">
                            <?php echo nl2br(sanitizeOutput($question['feedback'])); ?>
                        </p>
                    </div>
                <?php endif; ?>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../../includes/footer.php'; ?>

