<?php
/**
 * Student Take Exam
 * Interface for students to take examinations
 */

require_once __DIR__ . '/../../config/config.php';
requireAuth([ROLE_STUDENT]);

// Set timezone for accurate date comparisons
date_default_timezone_set('Asia/Manila');

$page_title = 'Take Examination';
$pdo = getDBConnection();
$error = '';
$exam_id = (int)($_GET['exam_id'] ?? 0);

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

// Get exam details
$stmt = $pdo->prepare("SELECT e.*, c.course_name, cs.section_number
                      FROM examinations e
                      LEFT JOIN courses c ON e.course_id = c.course_id
                      LEFT JOIN class_sections cs ON e.section_id = cs.section_id
                      WHERE e.exam_id = ? AND e.is_active = 1");
$stmt->execute([$exam_id]);
$exam = $stmt->fetch();

if (!$exam) {
    header('Location: ' . BASE_URL . 'modules/examinations/student_exams.php?error=Exam not found or not available');
    exit;
}

// Check if student is enrolled (only if exam has a section_id)
if ($exam['section_id']) {
    $stmt = $pdo->prepare("SELECT * FROM enrollments WHERE student_id = ? AND section_id = ? AND status = 'Enrolled'");
    $stmt->execute([$student_id, $exam['section_id']]);
    $enrollment = $stmt->fetch();
    
    if (!$enrollment) {
        header('Location: ' . BASE_URL . 'modules/examinations/student_exams.php?error=You are not enrolled in this course section');
        exit;
    }
} else {
    // If exam has no section_id, check if student is enrolled in the course
    if ($exam['course_id']) {
        $stmt = $pdo->prepare("SELECT * FROM enrollments en
                              INNER JOIN class_sections cs ON en.section_id = cs.section_id
                              WHERE en.student_id = ? AND cs.course_id = ? AND en.status = 'Enrolled'");
        $stmt->execute([$student_id, $exam['course_id']]);
        $enrollment = $stmt->fetch();
        
        if (!$enrollment) {
            header('Location: ' . BASE_URL . 'modules/examinations/student_exams.php?error=You are not enrolled in this course');
            exit;
        }
    }
}

// Check exam availability using timestamp comparison (same as student_exams.php)
$current_ts = time(); // Current Unix timestamp (using Asia/Manila timezone)
$start_ts = strtotime($exam['start_date']);
$end_ts = strtotime($exam['end_date']);

// Validate timestamps
if ($start_ts === false || $end_ts === false) {
    $error = 'Invalid exam date configuration. Please contact your instructor.';
} elseif ($current_ts < $start_ts) {
    // Exam hasn't started yet
    $start_date_formatted = date('M d, Y g:i A', $start_ts);
    $error = 'This exam has not started yet. It will be available on ' . $start_date_formatted;
} elseif ($current_ts > $end_ts && !$exam['allow_late_submission']) {
    // Exam has ended and late submission is not allowed
    $error = 'This exam has ended. Late submissions are not allowed.';
}

// Check for existing attempt
$stmt = $pdo->prepare("SELECT * FROM exam_attempts WHERE exam_id = ? AND student_id = ? ORDER BY started_at DESC LIMIT 1");
$stmt->execute([$exam_id, $student_id]);
$existing_attempt = $stmt->fetch();

$attempt_id = null;
$is_new_attempt = false;

if ($existing_attempt) {
    if ($existing_attempt['status'] === 'In Progress') {
        $attempt_id = $existing_attempt['attempt_id'];
    } elseif ($existing_attempt['status'] === 'Submitted' || $existing_attempt['status'] === 'Graded') {
        // Check if multiple attempts allowed (for now, only one attempt)
        $error = 'You have already completed this exam.';
    }
} else {
    // Create new attempt
    if (empty($error)) {
        try {
            $stmt = $pdo->prepare("INSERT INTO exam_attempts (exam_id, student_id, status, ip_address, user_agent) 
                                  VALUES (?, ?, 'In Progress', ?, ?)");
            $stmt->execute([$exam_id, $student_id, $_SERVER['REMOTE_ADDR'] ?? '', $_SERVER['HTTP_USER_AGENT'] ?? '']);
            $attempt_id = $pdo->lastInsertId();
            $is_new_attempt = true;
        } catch (PDOException $e) {
            $error = 'Failed to start exam: ' . $e->getMessage();
        }
    }
}

// Get questions for this exam
$questions = [];
if ($attempt_id && empty($error)) {
    try {
        // Check if questions should be randomized
        $order_by = $exam['randomize_questions'] ? 'RAND()' : 'eq.question_order';
        
        $stmt = $pdo->prepare("SELECT q.*, eq.points as exam_points, eq.question_order, eq.exam_question_id
                              FROM exam_questions eq
                              INNER JOIN questions q ON eq.question_id = q.question_id
                              WHERE eq.exam_id = ?
                              ORDER BY $order_by");
        $stmt->execute([$exam_id]);
        $questions = $stmt->fetchAll();
        
        // Get options for each question (randomize if needed)
        foreach ($questions as &$question) {
            if (in_array($question['question_type'], ['Multiple Choice', 'True/False'])) {
                $order_by_options = $exam['randomize_options'] ? 'RAND()' : 'option_order';
                $stmt = $pdo->prepare("SELECT * FROM question_options WHERE question_id = ? ORDER BY $order_by_options");
                $stmt->execute([$question['question_id']]);
                $question['options'] = $stmt->fetchAll();
            }
            
            // Get existing answer if any
            $stmt = $pdo->prepare("SELECT * FROM exam_answers WHERE attempt_id = ? AND question_id = ?");
            $stmt->execute([$attempt_id, $question['question_id']]);
            $answer = $stmt->fetch();
            $question['existing_answer'] = $answer;
        }
    } catch (PDOException $e) {
        $error = 'Error loading questions: ' . $e->getMessage();
    }
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_exam']) && $attempt_id) {
    try {
        $pdo->beginTransaction();
        
        $total_points = 0;
        $answers_submitted = 0;
        
        // Process each answer
        if (isset($_POST['answers']) && is_array($_POST['answers'])) {
            foreach ($_POST['answers'] as $question_id => $answer_data) {
                $question_id = (int)$question_id;
                $answer_text = isset($answer_data['text']) ? sanitizeInput($answer_data['text']) : null;
                $option_id = isset($answer_data['option_id']) ? (int)$answer_data['option_id'] : null;
                
                // Get question details
                $stmt = $pdo->prepare("SELECT q.*, eq.points as exam_points 
                                      FROM questions q
                                      INNER JOIN exam_questions eq ON q.question_id = eq.question_id
                                      WHERE q.question_id = ? AND eq.exam_id = ?");
                $stmt->execute([$question_id, $exam_id]);
                $question = $stmt->fetch();
                
                if ($question) {
                    $points_earned = 0;
                    $is_correct = 0;
                    
                    // Auto-grade if possible
                    if (in_array($question['question_type'], ['Multiple Choice', 'True/False']) && $option_id) {
                        $stmt = $pdo->prepare("SELECT is_correct FROM question_options WHERE option_id = ?");
                        $stmt->execute([$option_id]);
                        $option = $stmt->fetch();
                        if ($option && $option['is_correct']) {
                            $is_correct = 1;
                            $points_earned = (float)$question['exam_points'];
                        }
                    } elseif (in_array($question['question_type'], ['Short Answer', 'Fill in the Blank']) && $answer_text) {
                        // Simple text matching (case-insensitive, trimmed)
                        $correct = trim(strtolower($question['correct_answer']));
                        $answer = trim(strtolower($answer_text));
                        if ($correct === $answer) {
                            $is_correct = 1;
                            $points_earned = (float)$question['exam_points'];
                        }
                    }
                    // Essay questions need manual grading
                    
                    // Insert or update answer
                    $stmt = $pdo->prepare("INSERT INTO exam_answers (attempt_id, question_id, answer_text, option_id, points_earned, is_correct) 
                                          VALUES (?, ?, ?, ?, ?, ?)
                                          ON DUPLICATE KEY UPDATE answer_text = VALUES(answer_text), option_id = VALUES(option_id), 
                                          points_earned = VALUES(points_earned), is_correct = VALUES(is_correct)");
                    $stmt->execute([$attempt_id, $question_id, $answer_text, $option_id, $points_earned, $is_correct]);
                    
                    $total_points += $points_earned;
                    $answers_submitted++;
                }
            }
        }
        
        // Calculate percentage
        $percentage = $exam['total_points'] > 0 ? ($total_points / $exam['total_points']) * 100 : 0;
        
        // Calculate time taken
        $stmt = $pdo->prepare("SELECT started_at FROM exam_attempts WHERE attempt_id = ?");
        $stmt->execute([$attempt_id]);
        $attempt = $stmt->fetch();
        $time_taken = $attempt ? (time() - strtotime($attempt['started_at'])) : null;
        
        // Update attempt
        $status = ($exam['show_results_immediately'] && !in_array('Essay', array_column($questions, 'question_type'))) ? 'Graded' : 'Submitted';
        $stmt = $pdo->prepare("UPDATE exam_attempts SET submitted_at = NOW(), time_taken = ?, 
                              total_points = ?, score = ?, percentage = ?, status = ?
                              WHERE attempt_id = ?");
        $stmt->execute([$time_taken, $total_points, $total_points, $percentage, $status, $attempt_id]);
        
        $pdo->commit();
        
        header('Location: ' . BASE_URL . 'modules/examinations/student_result.php?exam_id=' . $exam_id . '&attempt_id=' . $attempt_id);
        exit;
    } catch (PDOException $e) {
        $pdo->rollBack();
        $error = 'Failed to submit exam: ' . $e->getMessage();
    }
}

include __DIR__ . '/../../includes/header.php';
?>

<div class="page-container">
    <div class="page-header">
        <h1><i class="fas fa-clipboard-list"></i> <?php echo sanitizeOutput($exam['title']); ?></h1>
        <a href="<?php echo BASE_URL; ?>modules/examinations/student_exams.php" class="btn btn-secondary">
            <i class="fas fa-arrow-left"></i> Back to Exams
        </a>
    </div>

    <?php if ($error): ?>
        <div class="alert alert-error" style="padding: 1.5rem; border-radius: 12px; margin-bottom: 2rem;">
            <div style="display: flex; align-items: start; gap: 1rem;">
                <i class="fas fa-exclamation-circle" style="font-size: 1.5rem; color: #ef4444;"></i>
                <div style="flex: 1;">
                    <h3 style="margin: 0 0 0.5rem 0; color: #991b1b;">Cannot Access Exam</h3>
                    <p style="margin: 0; color: #7f1d1d; font-size: 1.05rem;"><?php echo sanitizeOutput($error); ?></p>
                    <?php if (hasRole(ROLE_ADMIN)): ?>
                        <div style="margin-top: 1rem; padding: 1rem; background: #fee2e2; border-radius: 8px; font-size: 0.875rem;">
                            <strong>Debug Info (Admin Only):</strong><br>
                            Current Time: <?php echo date('Y-m-d H:i:s'); ?><br>
                            Exam Start: <?php echo $exam['start_date'] ?? 'N/A'; ?><br>
                            Exam End: <?php echo $exam['end_date'] ?? 'N/A'; ?><br>
                            Section ID: <?php echo $exam['section_id'] ?? 'NULL'; ?><br>
                            Course ID: <?php echo $exam['course_id'] ?? 'NULL'; ?><br>
                            Student ID: <?php echo $student_id ?? 'N/A'; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <div style="text-align: center; margin-top: 2rem;">
            <a href="<?php echo BASE_URL; ?>modules/examinations/student_exams.php" class="btn btn-primary">
                <i class="fas fa-arrow-left"></i> Return to Exams
            </a>
        </div>
    <?php elseif ($attempt_id && !empty($questions)): ?>
<style>
.exam-header-card {
    background: linear-gradient(135deg, #1e40af 0%, #2563eb 100%);
    border-radius: 20px;
    padding: 2rem;
    color: white;
    margin-bottom: 2rem;
    box-shadow: 0 8px 24px rgba(30, 64, 175, 0.3);
}

.exam-header-info {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 1.5rem;
    margin-bottom: 1.5rem;
}

.exam-header-item {
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
}

.exam-header-item label {
    font-size: 0.875rem;
    opacity: 0.9;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.exam-header-item .value {
    font-size: 1.25rem;
    font-weight: 700;
}

.timer-container {
    background: rgba(255, 255, 255, 0.15);
    backdrop-filter: blur(10px);
    border-radius: 16px;
    padding: 1.5rem;
    text-align: center;
    border: 2px solid rgba(255, 255, 255, 0.3);
}

.timer-container.warning {
    background: rgba(245, 158, 11, 0.2);
    border-color: rgba(245, 158, 11, 0.5);
}

.timer-container.danger {
    background: rgba(239, 68, 68, 0.2);
    border-color: rgba(239, 68, 68, 0.5);
    animation: pulse 1s infinite;
}

@keyframes pulse {
    0%, 100% { transform: scale(1); }
    50% { transform: scale(1.02); }
}

#time_remaining {
    font-size: 2.5rem;
    font-weight: 800;
    font-family: 'Courier New', monospace;
    letter-spacing: 2px;
}

.instructions-box {
    background: rgba(255, 255, 255, 0.15);
    backdrop-filter: blur(10px);
    border-radius: 12px;
    padding: 1.5rem;
    border: 2px solid rgba(255, 255, 255, 0.3);
    margin-top: 1.5rem;
}

.instructions-box strong {
    display: block;
    margin-bottom: 0.75rem;
    font-size: 1.1rem;
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
    display: flex;
    align-items: center;
    gap: 0.75rem;
}

.question-number i {
    color: #2563eb;
}

.question-text {
    font-size: 1.15rem;
    font-weight: 500;
    color: #1e293b;
    line-height: 1.7;
    margin-bottom: 1.5rem;
}

.options-container {
    display: flex;
    flex-direction: column;
    gap: 1rem;
    margin-left: 0.5rem;
}

.option-item {
    padding: 1rem 1.25rem;
    background: #f8fafc;
    border: 2px solid #e2e8f0;
    border-radius: 12px;
    transition: all 0.3s;
    cursor: pointer;
}

.option-item:hover {
    background: #f1f5f9;
    border-color: #2563eb;
    transform: translateX(4px);
}

.option-item input[type="radio"] {
    width: 20px;
    height: 20px;
    margin-right: 1rem;
    cursor: pointer;
}

.option-item input[type="radio"]:checked + span {
    font-weight: 600;
    color: #1e40af;
}

.option-item:has(input[type="radio"]:checked) {
    background: #e0f2fe;
    border-color: #2563eb;
}

.answer-textarea {
    width: 100%;
    padding: 1rem;
    border: 2px solid #e2e8f0;
    border-radius: 12px;
    font-size: 1rem;
    font-family: inherit;
    resize: vertical;
    transition: all 0.3s;
}

.answer-textarea:focus {
    outline: none;
    border-color: #2563eb;
    box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1);
}

.submit-bar {
    position: sticky;
    bottom: 0;
    background: white;
    padding: 1.5rem 2rem;
    border-top: 3px solid #1e40af;
    box-shadow: 0 -4px 20px rgba(0, 0, 0, 0.1);
    z-index: 100;
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 1rem;
}

.auto-save-indicator {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    color: #64748b;
    font-size: 0.875rem;
}

.auto-save-indicator.saving {
    color: #2563eb;
}

.auto-save-indicator.saved {
    color: #22c55e;
}

.progress-indicator {
    position: fixed;
    top: 80px;
    right: 2rem;
    background: white;
    border: 2px solid #e2e8f0;
    border-radius: 16px;
    padding: 1.5rem;
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
    min-width: 200px;
    z-index: 50;
}

.progress-indicator h4 {
    margin: 0 0 1rem 0;
    color: #1e40af;
    font-size: 1rem;
}

.progress-questions {
    display: flex;
    flex-wrap: wrap;
    gap: 0.5rem;
}

.progress-question {
    width: 40px;
    height: 40px;
    border: 2px solid #e2e8f0;
    border-radius: 8px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.3s;
    background: white;
}

.progress-question:hover {
    border-color: #2563eb;
    transform: scale(1.1);
}

.progress-question.answered {
    background: #22c55e;
    border-color: #22c55e;
    color: white;
}

.progress-question.current {
    background: #2563eb;
    border-color: #2563eb;
    color: white;
    box-shadow: 0 0 0 4px rgba(37, 99, 235, 0.2);
}
</style>

        <div class="exam-header-card">
            <div class="exam-header-info">
                <div class="exam-header-item">
                    <label><i class="fas fa-book"></i> Course</label>
                    <div class="value"><?php echo sanitizeOutput($exam['course_name'] ?? 'N/A'); ?></div>
                </div>
                <div class="exam-header-item">
                    <label><i class="fas fa-clock"></i> Time Limit</label>
                    <div class="value"><?php echo $exam['time_limit'] ? $exam['time_limit'] . ' minutes' : 'No limit'; ?></div>
                </div>
                <div class="exam-header-item">
                    <label><i class="fas fa-star"></i> Total Points</label>
                    <div class="value"><?php echo number_format($exam['total_points'], 2); ?></div>
                </div>
                <div class="exam-header-item">
                    <label><i class="fas fa-question-circle"></i> Questions</label>
                    <div class="value"><?php echo count($questions); ?></div>
                </div>
            </div>
            
            <?php if ($exam['time_limit']): ?>
            <div id="timer" class="timer-container">
                <div style="font-size: 0.875rem; opacity: 0.9; margin-bottom: 0.5rem;">
                    <i class="fas fa-hourglass-half"></i> Time Remaining
                </div>
                <div id="time_remaining"><?php echo $exam['time_limit']; ?>:00</div>
            </div>
            <?php endif; ?>
            
            <?php if (!empty($exam['instructions'])): ?>
                <div class="instructions-box">
                    <strong><i class="fas fa-info-circle"></i> Instructions</strong>
                    <div style="opacity: 0.95; line-height: 1.7;"><?php echo nl2br(sanitizeOutput($exam['instructions'])); ?></div>
                </div>
            <?php endif; ?>
        </div>

        <!-- Progress Indicator -->
        <div class="progress-indicator">
            <h4><i class="fas fa-list"></i> Question Progress</h4>
            <div class="progress-questions" id="progressQuestions">
                <?php foreach ($questions as $idx => $q): ?>
                <div class="progress-question" 
                     id="progress_<?php echo $idx + 1; ?>"
                     onclick="scrollToQuestion(<?php echo $idx + 1; ?>)"
                     title="Question <?php echo $idx + 1; ?>">
                    <?php echo $idx + 1; ?>
                </div>
                <?php endforeach; ?>
            </div>
        </div>

        <form method="POST" action="" id="examForm" onsubmit="return confirm('Are you sure you want to submit this exam? You cannot change your answers after submission.');">
            <?php foreach ($questions as $idx => $question): ?>
            <div class="question-card" id="question_<?php echo $idx + 1; ?>">
                <div class="question-header">
                    <div class="question-number">
                        <i class="fas fa-question-circle"></i>
                        <span>Question <?php echo $idx + 1; ?> of <?php echo count($questions); ?></span>
                        <span class="badge badge-success" style="margin-left: 1rem;">
                            <i class="fas fa-star"></i> <?php echo number_format($question['exam_points'], 2); ?> points
                        </span>
                    </div>
                    <span class="badge badge-info"><?php echo sanitizeOutput($question['question_type']); ?></span>
                </div>
                
                <div class="question-text">
                    <?php echo nl2br(sanitizeOutput($question['question_text'])); ?>
                </div>

                <?php if (in_array($question['question_type'], ['Multiple Choice', 'True/False']) && !empty($question['options'])): ?>
                    <div class="options-container">
                        <?php foreach ($question['options'] as $option): ?>
                        <label class="option-item">
                            <input type="radio" 
                                   name="answers[<?php echo $question['question_id']; ?>][option_id]" 
                                   value="<?php echo $option['option_id']; ?>"
                                   <?php echo ($question['existing_answer'] && $question['existing_answer']['option_id'] == $option['option_id']) ? 'checked' : ''; ?>
                                   onchange="markQuestionAnswered(<?php echo $idx + 1; ?>)"
                                   required>
                            <span><?php echo sanitizeOutput($option['option_text']); ?></span>
                        </label>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div>
                        <textarea name="answers[<?php echo $question['question_id']; ?>][text]" 
                                  class="answer-textarea" 
                                  rows="<?php echo $question['question_type'] === 'Essay' ? '8' : '4'; ?>"
                                  placeholder="Enter your answer here..."
                                  oninput="markQuestionAnswered(<?php echo $idx + 1; ?>)"
                                  <?php echo $question['question_type'] !== 'Essay' ? 'required' : ''; ?>><?php echo $question['existing_answer'] ? sanitizeOutput($question['existing_answer']['answer_text']) : ''; ?></textarea>
                    </div>
                <?php endif; ?>
            </div>
            <?php endforeach; ?>

            <div class="submit-bar">
                <div class="auto-save-indicator" id="autoSaveIndicator">
                    <i class="fas fa-save"></i>
                    <span>Auto-saving...</span>
                </div>
                <div style="display: flex; gap: 1rem;">
                    <a href="<?php echo BASE_URL; ?>modules/examinations/student_exams.php" class="btn btn-secondary">
                        <i class="fas fa-times"></i> Cancel
                    </a>
                    <button type="submit" name="submit_exam" class="btn btn-primary btn-lg">
                        <i class="fas fa-check-circle"></i> Submit Examination
                    </button>
                </div>
            </div>
        </form>

        <script>
        // Question navigation
        function scrollToQuestion(num) {
            document.getElementById('question_' + num).scrollIntoView({ behavior: 'smooth', block: 'start' });
            // Update current question indicator
            document.querySelectorAll('.progress-question').forEach(el => el.classList.remove('current'));
            document.getElementById('progress_' + num).classList.add('current');
        }

        function markQuestionAnswered(num) {
            const progressEl = document.getElementById('progress_' + num);
            if (!progressEl.classList.contains('answered')) {
                progressEl.classList.add('answered');
            }
        }

        // Initialize answered questions
        document.addEventListener('DOMContentLoaded', function() {
            <?php foreach ($questions as $idx => $q): ?>
                <?php if ($q['existing_answer']): ?>
                markQuestionAnswered(<?php echo $idx + 1; ?>);
                <?php endif; ?>
            <?php endforeach; ?>
            
            // Mark first question as current
            if (document.getElementById('progress_1')) {
                document.getElementById('progress_1').classList.add('current');
            }
        });

        // Intersection Observer for current question tracking
        const observerOptions = {
            root: null,
            rootMargin: '-100px',
            threshold: 0.5
        };

        const observer = new IntersectionObserver(function(entries) {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    const questionNum = entry.target.id.split('_')[1];
                    document.querySelectorAll('.progress-question').forEach(el => el.classList.remove('current'));
                    const progressEl = document.getElementById('progress_' + questionNum);
                    if (progressEl) {
                        progressEl.classList.add('current');
                    }
                }
            });
        }, observerOptions);

        document.querySelectorAll('.question-card').forEach(card => {
            observer.observe(card);
        });

        <?php if ($exam['time_limit']): ?>
        // Timer functionality
        let timeLimit = <?php echo $exam['time_limit'] * 60; ?>; // Convert to seconds
        let timeRemaining = timeLimit;
        
        // Get time already spent if continuing
        <?php if ($existing_attempt && $existing_attempt['status'] === 'In Progress'): ?>
            const startedAt = new Date('<?php echo $existing_attempt['started_at']; ?>').getTime();
            const now = new Date().getTime();
            const elapsed = Math.floor((now - startedAt) / 1000);
            timeRemaining = Math.max(0, timeLimit - elapsed);
        <?php endif; ?>
        
        function updateTimer() {
            if (timeRemaining <= 0) {
                document.getElementById('time_remaining').textContent = '00:00';
                const timerContainer = document.getElementById('timer');
                timerContainer.classList.add('danger');
                alert('Time is up! Your exam will be submitted automatically.');
                document.getElementById('examForm').submit();
                return;
            }
            
            const minutes = Math.floor(timeRemaining / 60);
            const seconds = timeRemaining % 60;
            document.getElementById('time_remaining').textContent = 
                String(minutes).padStart(2, '0') + ':' + String(seconds).padStart(2, '0');
            
            const timerContainer = document.getElementById('timer');
            timerContainer.classList.remove('warning', 'danger');
            
            if (timeRemaining <= 300) { // 5 minutes
                timerContainer.classList.add('danger');
            } else if (timeRemaining <= 600) { // 10 minutes
                timerContainer.classList.add('warning');
            }
            
            timeRemaining--;
        }
        
        setInterval(updateTimer, 1000);
        updateTimer();
        <?php endif; ?>
        
        // Auto-save functionality
        let autoSaveInterval;
        const autoSaveIndicator = document.getElementById('autoSaveIndicator');
        
        function updateAutoSaveStatus(status) {
            autoSaveIndicator.className = 'auto-save-indicator ' + status;
            if (status === 'saving') {
                autoSaveIndicator.innerHTML = '<i class="fas fa-spinner fa-spin"></i> <span>Saving...</span>';
            } else if (status === 'saved') {
                autoSaveIndicator.innerHTML = '<i class="fas fa-check-circle"></i> <span>Saved</span>';
                setTimeout(() => {
                    autoSaveIndicator.className = 'auto-save-indicator';
                    autoSaveIndicator.innerHTML = '<i class="fas fa-save"></i> <span>Auto-saving...</span>';
                }, 2000);
            }
        }

        // Auto-save answers periodically
        autoSaveInterval = setInterval(function() {
            updateAutoSaveStatus('saving');
            
            // Save answers to localStorage as backup
            const formData = new FormData(document.getElementById('examForm'));
            const answers = {};
            for (let [key, value] of formData.entries()) {
                if (key.startsWith('answers[')) {
                    answers[key] = value;
                }
            }
            localStorage.setItem('exam_answers_' + <?php echo $exam_id; ?>, JSON.stringify(answers));
            
            // Simulate save delay
            setTimeout(() => updateAutoSaveStatus('saved'), 500);
        }, 30000); // Every 30 seconds

        // Save on input change
        document.getElementById('examForm').addEventListener('input', function() {
            clearTimeout(window.autoSaveTimeout);
            window.autoSaveTimeout = setTimeout(function() {
                updateAutoSaveStatus('saving');
                const formData = new FormData(document.getElementById('examForm'));
                const answers = {};
                for (let [key, value] of formData.entries()) {
                    if (key.startsWith('answers[')) {
                        answers[key] = value;
                    }
                }
                localStorage.setItem('exam_answers_' + <?php echo $exam_id; ?>, JSON.stringify(answers));
                setTimeout(() => updateAutoSaveStatus('saved'), 500);
            }, 2000); // Debounce: save 2 seconds after last input
        });

        // Warn before leaving page
        window.addEventListener('beforeunload', function(e) {
            e.preventDefault();
            e.returnValue = 'You have unsaved answers. Are you sure you want to leave?';
            return e.returnValue;
        });

        // Prevent accidental form submission
        document.getElementById('examForm').addEventListener('submit', function(e) {
            const unanswered = document.querySelectorAll('.question-card').length - 
                             document.querySelectorAll('.progress-question.answered').length;
            if (unanswered > 0) {
                if (!confirm('You have ' + unanswered + ' unanswered question(s). Are you sure you want to submit?')) {
                    e.preventDefault();
                    return false;
                }
            }
        });
        </script>
    <?php endif; ?>
</div>

<?php include __DIR__ . '/../../includes/footer.php'; ?>

