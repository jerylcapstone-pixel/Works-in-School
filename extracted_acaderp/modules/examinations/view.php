<?php
/**
 * View Examination Details
 * Display examination information and questions
 */

require_once __DIR__ . '/../../config/config.php';
requireAuth([ROLE_ADMIN, ROLE_FACULTY]);

$page_title = 'View Examination';
$pdo = getDBConnection();
$exam_id = (int)($_GET['id'] ?? 0);

if (!$exam_id) {
    header('Location: ' . BASE_URL . 'modules/examinations/index.php?error=Invalid exam ID');
    exit;
}

// Get exam details
$stmt = $pdo->prepare("SELECT e.*, c.course_name, c.course_code, cs.section_number, cs.semester, cs.academic_year,
                      u.username as created_by_username
                      FROM examinations e
                      LEFT JOIN courses c ON e.course_id = c.course_id
                      LEFT JOIN class_sections cs ON e.section_id = cs.section_id
                      LEFT JOIN users u ON e.created_by = u.user_id
                      WHERE e.exam_id = ?");
$stmt->execute([$exam_id]);
$exam = $stmt->fetch();

if (!$exam) {
    header('Location: ' . BASE_URL . 'modules/examinations/index.php?error=Exam not found');
    exit;
}

// Get questions
$stmt = $pdo->prepare("SELECT q.*, eq.points as exam_points, eq.question_order
                      FROM exam_questions eq
                      INNER JOIN questions q ON eq.question_id = q.question_id
                      WHERE eq.exam_id = ?
                      ORDER BY eq.question_order");
$stmt->execute([$exam_id]);
$questions = $stmt->fetchAll();

// Get options for each question
foreach ($questions as &$question) {
    if (in_array($question['question_type'], ['Multiple Choice', 'True/False'])) {
        $stmt = $pdo->prepare("SELECT * FROM question_options WHERE question_id = ? ORDER BY option_order");
        $stmt->execute([$question['question_id']]);
        $question['options'] = $stmt->fetchAll();
    }
}

// Get attempt statistics
$stmt = $pdo->prepare("SELECT COUNT(*) as total_attempts,
                      COUNT(CASE WHEN status = 'Submitted' THEN 1 END) as submitted,
                      COUNT(CASE WHEN status = 'Graded' THEN 1 END) as graded,
                      AVG(percentage) as avg_score
                      FROM exam_attempts
                      WHERE exam_id = ?");
$stmt->execute([$exam_id]);
$stats = $stmt->fetch();

include __DIR__ . '/../../includes/header.php';

// Determine status
$is_active = $exam['is_active'] ?? 0;
$now = time();
$start_time = strtotime($exam['start_date']);
$end_time = strtotime($exam['end_date']);
$status = 'Scheduled';
if ($now < $start_time) {
    $status = 'Scheduled';
} elseif ($now >= $start_time && $now <= $end_time) {
    $status = 'Active';
} else {
    $status = 'Ended';
}
?>

<style>
.exam-header-card {
    background: linear-gradient(180deg, #1e40af 0%, #2563eb 100%);
    border-radius: 24px;
    padding: 2.5rem;
    color: white;
    margin-bottom: 2rem;
    position: relative;
    overflow: hidden;
    box-shadow: 0 10px 40px rgba(30, 64, 175, 0.3);
}

.exam-header-card::before {
    content: '';
    position: absolute;
    top: -50%;
    left: -50%;
    width: 200%;
    height: 200%;
    background: 
        radial-gradient(circle at 30% 50%, rgba(255, 215, 0, 0.15) 0%, transparent 50%),
        radial-gradient(circle at 70% 80%, rgba(255, 255, 255, 0.08) 0%, transparent 40%);
}

.exam-header-content {
    position: relative;
    z-index: 2;
}

.exam-header-title {
    display: flex;
    align-items: center;
    gap: 1rem;
    margin-bottom: 1rem;
}

.exam-header-title i {
    font-size: 2.5rem;
    color: #ffd700;
    filter: drop-shadow(0 2px 8px rgba(0, 0, 0, 0.2));
}

.exam-header-title h1 {
    font-size: 2rem;
    font-weight: 800;
    margin: 0;
    text-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
}

.exam-status-badge {
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    padding: 0.75rem 1.5rem;
    background: rgba(255, 255, 255, 0.2);
    backdrop-filter: blur(10px);
    border-radius: 12px;
    font-weight: 700;
    font-size: 1rem;
    border: 2px solid rgba(255, 255, 255, 0.3);
    margin-top: 1rem;
}

.detail-cards {
    display: grid;
    gap: 1.5rem;
    margin-bottom: 2rem;
}

.detail-card {
    background: white;
    border-radius: 20px;
    padding: 2rem;
    box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
    border: 2px solid #e2e8f0;
    transition: all 0.3s;
}

.detail-card:hover {
    box-shadow: 0 8px 30px rgba(30, 64, 175, 0.15);
    transform: translateY(-2px);
}

.detail-card h3 {
    font-size: 1.25rem;
    font-weight: 700;
    color: #1e40af;
    margin: 0 0 1.5rem 0;
    display: flex;
    align-items: center;
    gap: 0.75rem;
    padding-bottom: 1rem;
    border-bottom: 2px solid #e2e8f0;
}

.detail-card h3 i {
    color: #2563eb;
}

.detail-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 1.5rem;
}

.detail-item {
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
}

.detail-item label {
    font-size: 0.875rem;
    font-weight: 600;
    color: #64748b;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.detail-item .value {
    font-size: 1.05rem;
    font-weight: 600;
    color: #1e293b;
}

.stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 1.5rem;
}

.stat-card {
    background: linear-gradient(135deg, #f8fafc 0%, #ffffff 100%);
    border: 2px solid #e2e8f0;
    border-radius: 16px;
    padding: 1.5rem;
    text-align: center;
    transition: all 0.3s;
}

.stat-card:hover {
    transform: translateY(-4px);
    box-shadow: 0 8px 24px rgba(30, 64, 175, 0.15);
    border-color: #2563eb;
}

.stat-card .stat-icon {
    width: 60px;
    height: 60px;
    background: linear-gradient(180deg, #1e40af 0%, #2563eb 100%);
    border-radius: 16px;
    display: flex;
    align-items: center;
    justify-content: center;
    margin: 0 auto 1rem;
    box-shadow: 0 4px 12px rgba(30, 64, 175, 0.3);
}

.stat-card .stat-icon i {
    font-size: 1.75rem;
    color: #ffd700;
}

.stat-card h4 {
    font-size: 2rem;
    font-weight: 800;
    color: #1e40af;
    margin: 0 0 0.5rem 0;
}

.stat-card p {
    font-size: 0.875rem;
    color: #64748b;
    margin: 0;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    font-weight: 600;
}

.question-card {
    background: white;
    border: 2px solid #e2e8f0;
    border-radius: 16px;
    padding: 2rem;
    transition: all 0.3s;
    position: relative;
}

.question-card:hover {
    border-color: #2563eb;
    box-shadow: 0 8px 24px rgba(30, 64, 175, 0.1);
    transform: translateY(-2px);
}

.question-card::before {
    content: '';
    position: absolute;
    left: 0;
    top: 0;
    bottom: 0;
    width: 6px;
    background: linear-gradient(180deg, #1e40af 0%, #2563eb 100%);
    border-radius: 16px 0 0 16px;
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
    font-weight: 800;
    color: #1e40af;
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.question-badges {
    display: flex;
    gap: 0.5rem;
    flex-wrap: wrap;
}

.question-text {
    font-size: 1.1rem;
    color: #1e293b;
    font-weight: 500;
    line-height: 1.7;
    margin-bottom: 1.5rem;
}

.options-list {
    list-style: none;
    padding: 0;
    margin: 1rem 0;
}

.options-list li {
    padding: 1rem;
    margin-bottom: 0.75rem;
    background: #f8fafc;
    border-radius: 12px;
    border: 2px solid #e2e8f0;
    transition: all 0.3s;
}

.options-list li.correct-option {
    background: linear-gradient(135deg, #dcfce7 0%, #bbf7d0 100%);
    border-color: #22c55e;
    color: #166534;
    font-weight: 700;
}

.explanation-box {
    margin-top: 1.5rem;
    padding: 1.5rem;
    background: linear-gradient(135deg, #f0f9ff 0%, #e0f2fe 100%);
    border-left: 4px solid #2563eb;
    border-radius: 12px;
}

.explanation-box strong {
    color: #1e40af;
    display: flex;
    align-items: center;
    gap: 0.5rem;
    margin-bottom: 0.75rem;
}

.explanation-box p {
    color: #475569;
    line-height: 1.7;
    margin: 0;
}

@media (max-width: 768px) {
    .exam-header-card {
        padding: 1.5rem;
    }
    
    .exam-header-title h1 {
        font-size: 1.5rem;
    }
    
    .detail-card {
        padding: 1.5rem;
    }
    
    .detail-grid {
        grid-template-columns: 1fr;
    }
    
    .stats-grid {
        grid-template-columns: repeat(2, 1fr);
    }
}
</style>

<div class="page-container">
    <div class="page-header" style="margin-bottom: 1.5rem;">
        <div style="display: flex; gap: 0.5rem; flex-wrap: wrap;">
            <a href="<?php echo BASE_URL; ?>modules/examinations/edit.php?id=<?php echo $exam_id; ?>" class="btn btn-warning">
                <i class="fas fa-edit"></i> Edit
            </a>
            <a href="<?php echo BASE_URL; ?>modules/examinations/results.php?id=<?php echo $exam_id; ?>" class="btn btn-primary">
                <i class="fas fa-chart-bar"></i> Results
            </a>
            <a href="<?php echo BASE_URL; ?>modules/examinations/index.php" class="btn btn-secondary">
                <i class="fas fa-arrow-left"></i> Back
            </a>
        </div>
    </div>

    <!-- Exam Header Card -->
    <div class="exam-header-card">
        <div class="exam-header-content">
            <div class="exam-header-title">
                <i class="fas fa-clipboard-list"></i>
                <h1><?php echo sanitizeOutput($exam['title']); ?></h1>
            </div>
            <div class="exam-status-badge">
                <i class="fas fa-<?php echo $status === 'Active' ? 'play-circle' : ($status === 'Ended' ? 'check-circle' : 'clock'); ?>"></i>
                <span><?php echo $status; ?></span>
            </div>
        </div>
    </div>

    <div class="detail-cards">
        <!-- Exam Information Card -->
        <div class="detail-card">
            <h3><i class="fas fa-info-circle"></i> Exam Information</h3>
            <div class="detail-grid">
                <div class="detail-item">
                    <label>Exam Type</label>
                    <div class="value">
                        <span class="badge badge-info"><?php echo sanitizeOutput($exam['exam_type']); ?></span>
                    </div>
                </div>
                <div class="detail-item">
                    <label>Course</label>
                    <div class="value"><?php echo $exam['course_name'] ? sanitizeOutput($exam['course_code'] . ' - ' . $exam['course_name']) : 'N/A'; ?></div>
                </div>
                <div class="detail-item">
                    <label>Section</label>
                    <div class="value"><?php echo $exam['section_number'] ? 'Section ' . sanitizeOutput($exam['section_number']) . ' (' . sanitizeOutput($exam['semester'] . ' ' . $exam['academic_year']) . ')' : 'N/A'; ?></div>
                </div>
                <div class="detail-item">
                    <label>Total Points</label>
                    <div class="value"><?php echo number_format($exam['total_points'], 2); ?></div>
                </div>
                <div class="detail-item">
                    <label>Number of Questions</label>
                    <div class="value"><?php echo count($questions); ?></div>
                </div>
                <div class="detail-item">
                    <label>Time Limit</label>
                    <div class="value"><?php echo $exam['time_limit'] ? $exam['time_limit'] . ' minutes' : 'No limit'; ?></div>
                </div>
                <div class="detail-item">
                    <label>Start Date</label>
                    <div class="value"><?php echo date('M d, Y g:i A', strtotime($exam['start_date'])); ?></div>
                </div>
                <div class="detail-item">
                    <label>End Date</label>
                    <div class="value"><?php echo date('M d, Y g:i A', strtotime($exam['end_date'])); ?></div>
                </div>
                <div class="detail-item">
                    <label>Created By</label>
                    <div class="value"><?php echo sanitizeOutput($exam['created_by_username'] ?? 'N/A'); ?></div>
                </div>
            </div>

            <?php if (!empty($exam['description'])): ?>
            <div style="margin-top: 1.5rem; padding-top: 1.5rem; border-top: 2px solid #e2e8f0;">
                <h4 style="color: #1e40af; margin-bottom: 0.75rem; display: flex; align-items: center; gap: 0.5rem;">
                    <i class="fas fa-align-left"></i> Description
                </h4>
                <p style="color: #475569; line-height: 1.7; margin: 0;"><?php echo nl2br(sanitizeOutput($exam['description'])); ?></p>
            </div>
            <?php endif; ?>

            <?php if (!empty($exam['instructions'])): ?>
            <div style="margin-top: 1.5rem; padding-top: 1.5rem; border-top: 2px solid #e2e8f0;">
                <h4 style="color: #1e40af; margin-bottom: 0.75rem; display: flex; align-items: center; gap: 0.5rem;">
                    <i class="fas fa-list-ol"></i> Instructions
                </h4>
                <p style="color: #475569; line-height: 1.7; margin: 0;"><?php echo nl2br(sanitizeOutput($exam['instructions'])); ?></p>
            </div>
            <?php endif; ?>
        </div>

        <!-- Statistics Card -->
        <div class="detail-card">
            <h3><i class="fas fa-chart-bar"></i> Statistics</h3>
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-icon">
                        <i class="fas fa-users"></i>
                    </div>
                    <h4><?php echo (int)$stats['total_attempts']; ?></h4>
                    <p>Total Attempts</p>
                </div>
                <div class="stat-card">
                    <div class="stat-icon">
                        <i class="fas fa-paper-plane"></i>
                    </div>
                    <h4><?php echo (int)$stats['submitted']; ?></h4>
                    <p>Submitted</p>
                </div>
                <div class="stat-card">
                    <div class="stat-icon">
                        <i class="fas fa-check-double"></i>
                    </div>
                    <h4><?php echo (int)$stats['graded']; ?></h4>
                    <p>Graded</p>
                </div>
                <div class="stat-card">
                    <div class="stat-icon">
                        <i class="fas fa-percentage"></i>
                    </div>
                    <h4><?php echo $stats['avg_score'] ? number_format($stats['avg_score'], 1) . '%' : 'N/A'; ?></h4>
                    <p>Average Score</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Questions Card -->
    <div class="detail-card">
        <h3><i class="fas fa-question-circle"></i> Questions (<?php echo count($questions); ?>)</h3>
        <?php if (empty($questions)): ?>
            <div style="text-align: center; padding: 3rem; color: #64748b;">
                <i class="fas fa-inbox" style="font-size: 3rem; margin-bottom: 1rem; opacity: 0.5;"></i>
                <p>No questions added to this exam yet.</p>
            </div>
        <?php else: ?>
            <div style="display: flex; flex-direction: column; gap: 2rem;">
                <?php foreach ($questions as $idx => $question): ?>
                <div class="question-card">
                    <div class="question-header">
                        <div class="question-number">
                            <i class="fas fa-question"></i>
                            <span>Question <?php echo $idx + 1; ?></span>
                        </div>
                        <div class="question-badges">
                            <span class="badge badge-info"><?php echo sanitizeOutput($question['question_type']); ?></span>
                            <span class="badge badge-success">
                                <i class="fas fa-star"></i> <?php echo number_format($question['exam_points'], 2); ?> points
                            </span>
                        </div>
                    </div>
                    
                    <div class="question-text">
                        <?php echo nl2br(sanitizeOutput($question['question_text'])); ?>
                    </div>

                    <?php if (!empty($question['options'])): ?>
                        <div style="margin-top: 1.5rem;">
                            <h4 style="color: #1e40af; margin-bottom: 1rem; display: flex; align-items: center; gap: 0.5rem;">
                                <i class="fas fa-list-ul"></i> Options
                            </h4>
                            <ul class="options-list">
                                <?php foreach ($question['options'] as $option): ?>
                                    <li class="<?php echo $option['is_correct'] ? 'correct-option' : ''; ?>">
                                        <div style="display: flex; align-items: center; justify-content: space-between;">
                                            <span><?php echo sanitizeOutput($option['option_text']); ?></span>
                                            <?php if ($option['is_correct']): ?>
                                                <span class="badge badge-success">
                                                    <i class="fas fa-check"></i> Correct Answer
                                                </span>
                                            <?php endif; ?>
                                        </div>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    <?php elseif (!empty($question['correct_answer'])): ?>
                        <div style="margin-top: 1.5rem; padding: 1.5rem; background: linear-gradient(135deg, #dcfce7 0%, #bbf7d0 100%); border-radius: 12px; border: 2px solid #22c55e;">
                            <h4 style="color: #166534; margin-bottom: 0.75rem; display: flex; align-items: center; gap: 0.5rem;">
                                <i class="fas fa-check-circle"></i> Correct Answer
                            </h4>
                            <p style="color: #166534; font-weight: 600; margin: 0; line-height: 1.7;"><?php echo nl2br(sanitizeOutput($question['correct_answer'])); ?></p>
                        </div>
                    <?php endif; ?>

                    <?php if (!empty($question['explanation'])): ?>
                        <div class="explanation-box">
                            <strong>
                                <i class="fas fa-lightbulb"></i> Explanation
                            </strong>
                            <p><?php echo nl2br(sanitizeOutput($question['explanation'])); ?></p>
                        </div>
                    <?php endif; ?>
                </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php include __DIR__ . '/../../includes/footer.php'; ?>

