<?php
/**
 * Exam Results
 * View all student attempts and results for an exam
 */

require_once __DIR__ . '/../../config/config.php';
requireAuth([ROLE_ADMIN, ROLE_FACULTY]);

$page_title = 'Exam Results';
$pdo = getDBConnection();
$exam_id = (int)($_GET['id'] ?? 0);

if (!$exam_id) {
    header('Location: ' . BASE_URL . 'modules/examinations/index.php?error=Invalid exam ID');
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

if (!$exam) {
    header('Location: ' . BASE_URL . 'modules/examinations/index.php?error=Exam not found');
    exit;
}

// Get all attempts
$stmt = $pdo->prepare("SELECT ea.*, s.student_number, s.first_name, s.last_name
                      FROM exam_attempts ea
                      INNER JOIN students s ON ea.student_id = s.student_id
                      WHERE ea.exam_id = ?
                      ORDER BY ea.submitted_at DESC, ea.started_at DESC");
$stmt->execute([$exam_id]);
$attempts = $stmt->fetchAll();

// Calculate statistics
$stats = [
    'total' => count($attempts),
    'submitted' => 0,
    'graded' => 0,
    'in_progress' => 0,
    'avg_score' => 0,
    'avg_percentage' => 0
];

$total_percentage = 0;
$total_score = 0;
$graded_count = 0;

foreach ($attempts as $attempt) {
    if ($attempt['status'] === 'Submitted') $stats['submitted']++;
    if ($attempt['status'] === 'Graded') {
        $stats['graded']++;
        $total_percentage += (float)$attempt['percentage'];
        $total_score += (float)$attempt['score'];
        $graded_count++;
    }
    if ($attempt['status'] === 'In Progress') $stats['in_progress']++;
}

if ($graded_count > 0) {
    $stats['avg_percentage'] = $total_percentage / $graded_count;
    $stats['avg_score'] = $total_score / $graded_count;
}

include __DIR__ . '/../../includes/header.php';
?>

<style>
.results-header-card {
    background: linear-gradient(180deg, #1e40af 0%, #2563eb 100%);
    border-radius: 24px;
    padding: 2.5rem;
    color: white;
    margin-bottom: 2rem;
    position: relative;
    overflow: hidden;
    box-shadow: 0 10px 40px rgba(30, 64, 175, 0.3);
}

.results-header-card::before {
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

.results-header-content {
    position: relative;
    z-index: 2;
}

.results-header-title {
    display: flex;
    align-items: center;
    gap: 1rem;
    margin-bottom: 1rem;
}

.results-header-title i {
    font-size: 2.5rem;
    color: #ffd700;
    filter: drop-shadow(0 2px 8px rgba(0, 0, 0, 0.2));
}

.results-header-title h1 {
    font-size: 2rem;
    font-weight: 800;
    margin: 0;
    text-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
}

.exam-info {
    display: flex;
    flex-wrap: wrap;
    gap: 1.5rem;
    margin-top: 1rem;
    position: relative;
}

.exam-info-item {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    font-size: 0.95rem;
    color: rgba(255, 255, 255, 0.95);
}

.exam-info-item i {
    color: #ffd700;
    font-size: 1rem;
}

.stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 1.5rem;
    margin-bottom: 2rem;
}

.stat-card {
    background: white;
    border-radius: 20px;
    padding: 2rem;
    box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
    border: 2px solid #e2e8f0;
    transition: all 0.3s;
    text-align: center;
}

.stat-card:hover {
    box-shadow: 0 8px 30px rgba(30, 64, 175, 0.15);
    transform: translateY(-4px);
    border-color: #2563eb;
}

.stat-card .stat-icon {
    width: 70px;
    height: 70px;
    background: linear-gradient(180deg, #1e40af 0%, #2563eb 100%);
    border-radius: 18px;
    display: flex;
    align-items: center;
    justify-content: center;
    margin: 0 auto 1.25rem;
    box-shadow: 0 6px 20px rgba(30, 64, 175, 0.3);
}

.stat-card .stat-icon i {
    font-size: 2rem;
    color: #ffd700;
}

.stat-card h4 {
    font-size: 2.25rem;
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

.results-card {
    background: white;
    border-radius: 20px;
    padding: 2rem;
    box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
    border: 2px solid #e2e8f0;
}

.results-card h3 {
    font-size: 1.5rem;
    font-weight: 700;
    color: #1e40af;
    margin: 0 0 1.5rem 0;
    display: flex;
    align-items: center;
    gap: 0.75rem;
    padding-bottom: 1rem;
    border-bottom: 2px solid #e2e8f0;
}

.results-card h3 i {
    color: #2563eb;
}

.results-table {
    width: 100%;
    border-collapse: collapse;
    margin-top: 1rem;
}

.results-table thead {
    background: linear-gradient(180deg, #1e40af 0%, #2563eb 100%);
    color: white;
}

.results-table th {
    padding: 1.125rem 1rem;
    text-align: left;
    font-weight: 700;
    font-size: 0.9rem;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.results-table td {
    padding: 1.125rem 1rem;
    border-bottom: 1px solid #e2e8f0;
    vertical-align: middle;
}

.results-table tbody tr {
    transition: all 0.3s;
}

.results-table tbody tr:hover {
    background: #f8fafc;
    transform: scale(1.01);
}

.results-table .student-name {
    font-weight: 600;
    color: #1e293b;
}

.results-table .score-cell {
    font-weight: 700;
    color: #1e40af;
    font-size: 1.05rem;
}

.results-table .actions {
    display: flex;
    gap: 0.5rem;
}

.empty-state {
    text-align: center;
    padding: 4rem 2rem;
    color: #64748b;
}

.empty-state i {
    font-size: 4rem;
    margin-bottom: 1rem;
    opacity: 0.3;
}

@media (max-width: 768px) {
    .results-header-card {
        padding: 1.5rem;
    }
    
    .results-header-title h1 {
        font-size: 1.5rem;
    }
    
    .stats-grid {
        grid-template-columns: repeat(2, 1fr);
    }
    
    .stat-card {
        padding: 1.5rem;
    }
    
    .results-table {
        font-size: 0.875rem;
    }
    
    .results-table th,
    .results-table td {
        padding: 0.75rem 0.5rem;
    }
}
</style>

<div class="page-container">
    <div class="page-header" style="margin-bottom: 1.5rem;">
        <a href="<?php echo BASE_URL; ?>modules/examinations/index.php" class="btn btn-secondary">
            <i class="fas fa-arrow-left"></i> Back to Exams
        </a>
    </div>

    <!-- Results Header Card -->
    <div class="results-header-card">
        <div class="results-header-content">
            <div class="results-header-title">
                <i class="fas fa-chart-bar"></i>
                <h1>Exam Results</h1>
            </div>
            <div class="exam-info">
                <div class="exam-info-item">
                    <i class="fas fa-clipboard-list"></i>
                    <span><?php echo sanitizeOutput($exam['title']); ?></span>
                </div>
                <?php if ($exam['course_name']): ?>
                <div class="exam-info-item">
                    <i class="fas fa-book"></i>
                    <span><?php echo sanitizeOutput($exam['course_name']); ?></span>
                </div>
                <?php endif; ?>
                <?php if ($exam['section_number']): ?>
                <div class="exam-info-item">
                    <i class="fas fa-users"></i>
                    <span>Section <?php echo sanitizeOutput($exam['section_number']); ?></span>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Statistics Cards -->
    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-icon">
                <i class="fas fa-users"></i>
            </div>
            <h4><?php echo $stats['total']; ?></h4>
            <p>Total Attempts</p>
        </div>
        <div class="stat-card">
            <div class="stat-icon">
                <i class="fas fa-paper-plane"></i>
            </div>
            <h4><?php echo $stats['submitted']; ?></h4>
            <p>Submitted</p>
        </div>
        <div class="stat-card">
            <div class="stat-icon">
                <i class="fas fa-check-double"></i>
            </div>
            <h4><?php echo $stats['graded']; ?></h4>
            <p>Graded</p>
        </div>
        <div class="stat-card">
            <div class="stat-icon">
                <i class="fas fa-clock"></i>
            </div>
            <h4><?php echo $stats['in_progress']; ?></h4>
            <p>In Progress</p>
        </div>
        <div class="stat-card">
            <div class="stat-icon">
                <i class="fas fa-star"></i>
            </div>
            <h4><?php echo number_format($stats['avg_score'], 1); ?></h4>
            <p>Avg Score / <?php echo number_format($exam['total_points'], 0); ?></p>
        </div>
        <div class="stat-card">
            <div class="stat-icon">
                <i class="fas fa-percentage"></i>
            </div>
            <h4><?php echo number_format($stats['avg_percentage'], 1); ?>%</h4>
            <p>Average Percentage</p>
        </div>
    </div>

    <!-- Results Table Card -->
    <div class="results-card">
        <h3><i class="fas fa-list"></i> Student Attempts (<?php echo count($attempts); ?>)</h3>
        <?php if (empty($attempts)): ?>
            <div class="empty-state">
                <i class="fas fa-inbox"></i>
                <p style="font-size: 1.1rem; margin: 0;">No attempts yet.</p>
            </div>
        <?php else: ?>
            <div style="overflow-x: auto;">
                <table class="results-table">
                    <thead>
                        <tr>
                            <th>Student</th>
                            <th>Student Number</th>
                            <th>Started</th>
                            <th>Submitted</th>
                            <th>Time Taken</th>
                            <th>Score</th>
                            <th>Percentage</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($attempts as $attempt): 
                            $status_badge = [
                                'In Progress' => 'warning',
                                'Submitted' => 'info',
                                'Graded' => 'success',
                                'Expired' => 'danger'
                            ];
                            $badge = $status_badge[$attempt['status']] ?? 'secondary';
                            
                            // Determine percentage badge color
                            $percentage_badge = 'danger';
                            if ($attempt['percentage'] >= 70) {
                                $percentage_badge = 'success';
                            } elseif ($attempt['percentage'] >= 50) {
                                $percentage_badge = 'warning';
                            }
                        ?>
                        <tr>
                            <td class="student-name">
                                <i class="fas fa-user-graduate" style="color: #2563eb; margin-right: 0.5rem;"></i>
                                <?php echo sanitizeOutput($attempt['first_name'] . ' ' . $attempt['last_name']); ?>
                            </td>
                            <td><?php echo sanitizeOutput($attempt['student_number']); ?></td>
                            <td><?php echo date('M d, Y g:i A', strtotime($attempt['started_at'])); ?></td>
                            <td><?php echo $attempt['submitted_at'] ? date('M d, Y g:i A', strtotime($attempt['submitted_at'])) : '<span style="color: #94a3b8;">N/A</span>'; ?></td>
                            <td><?php echo $attempt['time_taken'] ? gmdate('H:i:s', $attempt['time_taken']) : '<span style="color: #94a3b8;">N/A</span>'; ?></td>
                            <td class="score-cell">
                                <?php echo number_format($attempt['score'], 2); ?> / <?php echo number_format($exam['total_points'], 2); ?>
                            </td>
                            <td>
                                <span class="badge badge-<?php echo $percentage_badge; ?>">
                                    <?php echo number_format($attempt['percentage'], 2); ?>%
                                </span>
                            </td>
                            <td>
                                <span class="badge badge-<?php echo $badge; ?>">
                                    <?php echo sanitizeOutput($attempt['status']); ?>
                                </span>
                            </td>
                            <td class="actions">
                                <a href="<?php echo BASE_URL; ?>modules/examinations/attempt_view.php?attempt_id=<?php echo $attempt['attempt_id']; ?>" 
                                   class="btn btn-sm btn-info" title="View Details">
                                    <i class="fas fa-eye"></i>
                                </a>
                                <?php if ($attempt['status'] === 'Submitted'): ?>
                                <a href="<?php echo BASE_URL; ?>modules/examinations/grade.php?attempt_id=<?php echo $attempt['attempt_id']; ?>" 
                                   class="btn btn-sm btn-warning" title="Grade">
                                    <i class="fas fa-check"></i>
                                </a>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php include __DIR__ . '/../../includes/footer.php'; ?>

