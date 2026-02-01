<?php
/**
 * Student Exams - List Available Exams
 * Students can view and take exams here
 */

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/feature_toggles.php';
requireAuth([ROLE_STUDENT]);

$page_title = 'My Examinations';
$pdo = getDBConnection();
$error = '';
$success = '';

// Check if examinations feature is enabled
if (!isExaminationsEnabled($pdo)) {
    header('Location: ' . BASE_URL . 'dashboard/index.php?error=The Examinations feature is currently disabled');
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
    $error = 'Student record not found.';
}

// Set timezone for accurate date comparisons (set early to ensure all date operations use it)
date_default_timezone_set('Asia/Manila');

// Get available exams for this student
$exams = [];
$upcoming_exams = [];
$past_exams = [];
$completed_exams = []; // Exams that have been submitted/completed
$debug_info = [];

if ($student_id) {
    try {
        // First, get all sections the student is enrolled in, with their course_ids
        $stmt = $pdo->prepare("SELECT e.section_id, cs.course_id 
                              FROM enrollments e
                              INNER JOIN class_sections cs ON e.section_id = cs.section_id
                              WHERE e.student_id = ? AND e.status = 'Enrolled'");
        $stmt->execute([$student_id]);
        $enrollments = $stmt->fetchAll();
        
        $enrolled_section_ids = array_column($enrollments, 'section_id');
        $enrolled_course_ids = array_unique(array_filter(array_column($enrollments, 'course_id')));
        
        $debug_info['enrolled_sections'] = count($enrolled_section_ids);
        $debug_info['enrolled_courses'] = count($enrolled_course_ids);
        
        // Build query to get exams for enrolled sections OR enrolled courses OR all active exams (if no section/course filter)
        $query = "SELECT DISTINCT e.*, c.course_name, c.course_code, cs.section_number,
                  (SELECT COUNT(*) FROM exam_attempts ea WHERE ea.exam_id = e.exam_id AND ea.student_id = ?) as attempt_count,
                  (SELECT MAX(ea.status) FROM exam_attempts ea WHERE ea.exam_id = e.exam_id AND ea.student_id = ?) as last_status
                  FROM examinations e
                  LEFT JOIN courses c ON e.course_id = c.course_id
                  LEFT JOIN class_sections cs ON e.section_id = cs.section_id
                  WHERE e.is_active = 1";
        
        $params = [$student_id, $student_id];
        
        // If student is enrolled in sections, filter by section or course
        if (!empty($enrolled_section_ids)) {
            $placeholders = implode(',', array_fill(0, count($enrolled_section_ids), '?'));
            $query .= " AND (e.section_id IN ($placeholders)";
            $params = array_merge($params, $enrolled_section_ids);
            
            // Also include exams for courses the student is enrolled in (even if no specific section)
            if (!empty($enrolled_course_ids)) {
                $course_placeholders = implode(',', array_fill(0, count($enrolled_course_ids), '?'));
                $query .= " OR (e.course_id IN ($course_placeholders) AND e.section_id IS NULL)";
                $params = array_merge($params, $enrolled_course_ids);
            }
            
            $query .= ")";
        } else {
            // If no enrollments, show all active exams (for testing/debugging)
            // In production, you might want to restrict this
            $query .= " AND (e.section_id IS NULL OR e.course_id IS NULL OR 1=1)";
        }
        
        $query .= " ORDER BY e.start_date DESC";
        
        $stmt = $pdo->prepare($query);
        $stmt->execute($params);
        $all_exams = $stmt->fetchAll();
        
        $debug_info['total_exams_found'] = count($all_exams);
        
        // Separate exams by status
        // Timezone already set at top of file
        // Get current timestamp directly from PHP (using Asia/Manila timezone)
        $compare_ts = time(); // Current Unix timestamp
        $current_datetime = date('Y-m-d H:i:s', $compare_ts); // For display/debug
        
        $debug_info['current_time'] = $current_datetime;
        $debug_info['current_timestamp'] = $compare_ts;
        $debug_info['server_timezone'] = date_default_timezone_get();
        
        foreach ($all_exams as $exam) {
            try {
                // Get date strings from database
                $start_str = $exam['start_date'];
                $end_str = $exam['end_date'];
                
                // Convert exam dates to timestamps for accurate comparison
                $start_ts = strtotime($start_str);
                $end_ts = strtotime($end_str);
                
                // Validate timestamps
                if ($compare_ts === false || $start_ts === false || $end_ts === false) {
                    error_log('Invalid timestamp conversion for exam ' . $exam['exam_id'] . 
                             ' - compare: ' . var_export($compare_ts, true) . 
                             ', start: ' . var_export($start_ts, true) . 
                             ', end: ' . var_export($end_ts, true));
                    $debug_info['date_errors'][] = 'Exam ' . $exam['exam_id'] . ': Invalid timestamp conversion';
                    continue;
                }
                
                // Debug for first exam
                if (count($upcoming_exams) + count($exams) + count($past_exams) === 0) {
                    $debug_info['sample_exam'] = [
                        'exam_id' => $exam['exam_id'],
                        'title' => $exam['title'],
                        'start_date_db' => $exam['start_date'],
                        'start_ts' => $start_ts,
                        'start_formatted' => date('Y-m-d H:i:s', $start_ts),
                        'end_date_db' => $exam['end_date'],
                        'end_ts' => $end_ts,
                        'end_formatted' => date('Y-m-d H:i:s', $end_ts),
                        'current_time' => $current_datetime,
                        'compare_ts' => $compare_ts,
                        'compare_formatted' => date('Y-m-d H:i:s', $compare_ts),
                        'comparison' => [
                            'compare_ts < start_ts' => $compare_ts < $start_ts ? 'true' : 'false',
                            'compare_ts >= start_ts' => $compare_ts >= $start_ts ? 'true' : 'false',
                            'compare_ts <= end_ts' => $compare_ts <= $end_ts ? 'true' : 'false',
                            'compare_ts > end_ts' => $compare_ts > $end_ts ? 'true' : 'false'
                        ],
                        'status' => $compare_ts < $start_ts ? 'upcoming' : ($compare_ts <= $end_ts ? 'available' : 'past')
                    ];
                }
                
                // Check if exam has been submitted/completed
                $is_completed = (int)$exam['attempt_count'] > 0 && 
                               in_array($exam['last_status'], ['Submitted', 'Graded']);
                
                // Compare using timestamps (accurate and timezone-safe)
                if ($is_completed) {
                    // Exam has been completed - add to completed exams
                    $completed_exams[] = $exam;
                } elseif ($compare_ts < $start_ts) {
                    // Upcoming exam - hasn't started yet
                    $upcoming_exams[] = $exam;
                } elseif ($compare_ts <= $end_ts) {
                    // Available exam - started and not ended yet (and not completed)
                    $exams[] = $exam;
                } elseif ($exam['allow_late_submission']) {
                    // Past but late submission allowed (and not completed)
                    $exams[] = $exam;
                } else {
                    // Past exam - ended and no late submission
                    $past_exams[] = $exam;
                }
            } catch (Exception $e) {
                // Skip exams with invalid dates
                error_log('Invalid date for exam ' . $exam['exam_id'] . ': ' . $e->getMessage());
                $debug_info['date_errors'][] = 'Exam ' . $exam['exam_id'] . ': ' . $e->getMessage();
            }
        }
        
        $debug_info['available'] = count($exams);
        $debug_info['upcoming'] = count($upcoming_exams);
        $debug_info['past'] = count($past_exams);
        $debug_info['completed'] = count($completed_exams);
        
    } catch (PDOException $e) {
        $error = 'Error loading exams: ' . $e->getMessage();
        error_log('Exam loading error: ' . $e->getMessage());
        $debug_info['error'] = $e->getMessage();
    }
}

include __DIR__ . '/../../includes/header.php';
?>

<div class="page-container">
    <div class="page-header">
        <h1><i class="fas fa-clipboard-list"></i> My Examinations</h1>
    </div>

    <?php if ($error): ?>
        <div class="alert alert-error">
            <i class="fas fa-exclamation-circle"></i> <?php echo sanitizeOutput($error); ?>
        </div>
    <?php endif; ?>

    <?php if ($success): ?>
        <div class="alert alert-success">
            <i class="fas fa-check-circle"></i> <?php echo sanitizeOutput($success); ?>
        </div>
    <?php endif; ?>

<style>
.exam-card {
    background: white;
    border: 2px solid #e2e8f0;
    border-radius: 20px;
    padding: 2rem;
    transition: all 0.3s;
    position: relative;
    overflow: hidden;
}

.exam-card::before {
    content: '';
    position: absolute;
    left: 0;
    top: 0;
    bottom: 0;
    width: 6px;
    background: linear-gradient(180deg, #1e40af 0%, #2563eb 100%);
    border-radius: 20px 0 0 20px;
}

.exam-card:hover {
    border-color: #2563eb;
    box-shadow: 0 8px 24px rgba(30, 64, 175, 0.15);
    transform: translateY(-2px);
}

.exam-card.available {
    border-left-color: #22c55e;
}

.exam-card.available::before {
    background: linear-gradient(180deg, #22c55e 0%, #16a34a 100%);
}

.exam-card.upcoming {
    border-left-color: #f59e0b;
}

.exam-card.upcoming::before {
    background: linear-gradient(180deg, #f59e0b 0%, #d97706 100%);
}

.exam-card.past {
    border-left-color: #94a3b8;
    opacity: 0.8;
}

.exam-card.past::before {
    background: linear-gradient(180deg, #94a3b8 0%, #64748b 100%);
}

.exam-header {
    display: flex;
    justify-content: space-between;
    align-items: start;
    margin-bottom: 1.5rem;
    padding-bottom: 1.5rem;
    border-bottom: 2px solid #e2e8f0;
}

.exam-title {
    flex: 1;
}

.exam-title h3 {
    font-size: 1.5rem;
    font-weight: 700;
    color: #1e293b;
    margin: 0 0 0.75rem 0;
}

.exam-badges {
    display: flex;
    gap: 0.5rem;
    flex-wrap: wrap;
}

.exam-info-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 1.5rem;
    margin-top: 1rem;
}

.exam-info-item {
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
}

.exam-info-item label {
    font-size: 0.875rem;
    font-weight: 600;
    color: #64748b;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.exam-info-item .value {
    font-size: 1.05rem;
    font-weight: 600;
    color: #1e293b;
}

.exam-description {
    margin-top: 1.5rem;
    padding-top: 1.5rem;
    border-top: 2px solid #e2e8f0;
    color: #475569;
    line-height: 1.7;
}

.section-header {
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

.section-header i {
    color: #2563eb;
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
</style>

    <?php if ($student_id): ?>
    <!-- Available Examinations -->
    <div class="card" style="margin-bottom: 2rem;">
        <h2 class="section-header">
            <i class="fas fa-play-circle"></i> Available Examinations (<?php echo count($exams); ?>)
        </h2>
        <?php if (empty($exams) && empty($upcoming_exams) && empty($past_exams)): ?>
            <div class="empty-state">
                <i class="fas fa-clipboard-list"></i>
                <p style="font-size: 1.1rem; margin: 0 0 1rem 0; font-weight: 600;">No examinations found.</p>
                <div style="text-align: left; max-width: 600px; margin: 0 auto; padding: 1.5rem; background: #f8fafc; border-radius: 12px; border: 2px solid #e2e8f0;">
                    <p style="margin: 0 0 1rem 0; color: #475569;">This could be because:</p>
                    <ul style="margin: 0; padding-left: 1.5rem; color: #64748b; line-height: 1.8;">
                        <li>You are not enrolled in any course sections</li>
                        <li>No exams have been created for your enrolled sections</li>
                        <li>All exams are currently inactive</li>
                        <li>The exam dates haven't started yet (check "Upcoming Examinations" below)</li>
                    </ul>
                    <p style="margin: 1rem 0 0 0; color: #475569; font-weight: 500;">
                        <i class="fas fa-info-circle"></i> Please contact your instructor or administrator if you believe you should see exams here.
                    </p>
                </div>
            </div>
        <?php elseif (empty($exams)): ?>
            <div class="empty-state">
                <i class="fas fa-clock"></i>
                <p style="font-size: 1.1rem; margin: 0;">No examinations available at this time.</p>
                <p style="color: #64748b; margin-top: 0.5rem;">Check "Upcoming Examinations" below for scheduled exams.</p>
            </div>
        <?php else: ?>
            <div style="display: flex; flex-direction: column; gap: 1.5rem;">
                <?php 
                // Get current timestamp (same as categorization logic - using PHP time())
                $display_current_ts = time();
                
                foreach ($exams as $exam): 
                    // Skip if exam has been submitted/completed (should not appear here, but double-check)
                    $is_completed = (int)$exam['attempt_count'] > 0 && 
                                   in_array($exam['last_status'], ['Submitted', 'Graded']);
                    if ($is_completed) {
                        continue; // Skip completed exams
                    }
                    
                    // Use timestamp comparison (same as categorization logic)
                    $start_ts = strtotime($exam['start_date']);
                    $end_ts = strtotime($exam['end_date']);
                    $can_take = ($display_current_ts >= $start_ts && ($display_current_ts <= $end_ts || $exam['allow_late_submission']));
                    $has_attempt = (int)$exam['attempt_count'] > 0;
                ?>
                <div class="exam-card available">
                    <div class="exam-header">
                        <div class="exam-title">
                            <h3><?php echo sanitizeOutput($exam['title']); ?></h3>
                            <div class="exam-badges">
                                <span class="badge badge-info"><?php echo sanitizeOutput($exam['exam_type']); ?></span>
                                <?php if ($exam['course_name']): ?>
                                    <span class="badge badge-secondary"><?php echo sanitizeOutput($exam['course_code']); ?></span>
                                <?php endif; ?>
                                <?php if ($exam['section_number']): ?>
                                    <span class="badge badge-secondary">Section <?php echo sanitizeOutput($exam['section_number']); ?></span>
                                <?php endif; ?>
                                <span class="badge badge-success">Available</span>
                            </div>
                        </div>
                        <div>
                            <?php if ($has_attempt && $exam['last_status'] === 'Submitted'): ?>
                                <a href="<?php echo BASE_URL; ?>modules/examinations/student_result.php?exam_id=<?php echo $exam['exam_id']; ?>" 
                                   class="btn btn-primary">
                                    <i class="fas fa-eye"></i> View Results
                                </a>
                            <?php elseif ($has_attempt && $exam['last_status'] === 'In Progress'): ?>
                                <a href="<?php echo BASE_URL; ?>modules/examinations/student_take.php?exam_id=<?php echo $exam['exam_id']; ?>" 
                                   class="btn btn-warning">
                                    <i class="fas fa-play"></i> Continue Exam
                                </a>
                            <?php elseif ($can_take): ?>
                                <a href="<?php echo BASE_URL; ?>modules/examinations/student_take.php?exam_id=<?php echo $exam['exam_id']; ?>" 
                                   class="btn btn-success">
                                    <i class="fas fa-play"></i> Take Exam
                                </a>
                            <?php else: ?>
                                <span class="badge badge-warning">Not Available</span>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <div class="exam-info-grid">
                        <div class="exam-info-item">
                            <label><i class="fas fa-calendar-alt"></i> Start Date</label>
                            <div class="value"><?php echo date('M d, Y g:i A', strtotime($exam['start_date'])); ?></div>
                        </div>
                        <div class="exam-info-item">
                            <label><i class="fas fa-calendar-times"></i> End Date</label>
                            <div class="value"><?php echo date('M d, Y g:i A', strtotime($exam['end_date'])); ?></div>
                        </div>
                        <div class="exam-info-item">
                            <label><i class="fas fa-clock"></i> Time Limit</label>
                            <div class="value"><?php echo $exam['time_limit'] ? $exam['time_limit'] . ' minutes' : 'No limit'; ?></div>
                        </div>
                        <div class="exam-info-item">
                            <label><i class="fas fa-star"></i> Total Points</label>
                            <div class="value"><?php echo number_format($exam['total_points'], 2); ?></div>
                        </div>
                    </div>

                    <?php if (!empty($exam['description'])): ?>
                        <div class="exam-description">
                            <?php echo nl2br(sanitizeOutput($exam['description'])); ?>
                        </div>
                    <?php endif; ?>
                </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <!-- Completed Examinations -->
    <?php if (!empty($completed_exams)): ?>
    <div class="card" style="margin-bottom: 2rem;">
        <h2 class="section-header">
            <i class="fas fa-check-circle"></i> Completed Examinations (<?php echo count($completed_exams); ?>)
        </h2>
        <div style="display: flex; flex-direction: column; gap: 1.5rem;">
            <?php foreach ($completed_exams as $exam): ?>
            <div class="exam-card past" style="border-left-color: #22c55e;">
                <div class="exam-header">
                    <div class="exam-title">
                        <h3><?php echo sanitizeOutput($exam['title']); ?></h3>
                        <div class="exam-badges">
                            <span class="badge badge-info"><?php echo sanitizeOutput($exam['exam_type']); ?></span>
                            <?php if ($exam['course_name']): ?>
                                <span class="badge badge-secondary"><?php echo sanitizeOutput($exam['course_code']); ?></span>
                            <?php endif; ?>
                            <?php if ($exam['section_number']): ?>
                                <span class="badge badge-secondary">Section <?php echo sanitizeOutput($exam['section_number']); ?></span>
                            <?php endif; ?>
                            <span class="badge badge-success">
                                <i class="fas fa-check"></i> Done/Taken
                            </span>
                        </div>
                    </div>
                    <div>
                        <a href="<?php echo BASE_URL; ?>modules/examinations/student_result.php?exam_id=<?php echo $exam['exam_id']; ?>" 
                           class="btn btn-primary">
                            <i class="fas fa-eye"></i> View Results
                        </a>
                    </div>
                </div>
                
                <div class="exam-info-grid">
                    <div class="exam-info-item">
                        <label><i class="fas fa-calendar-alt"></i> Start Date</label>
                        <div class="value"><?php echo date('M d, Y g:i A', strtotime($exam['start_date'])); ?></div>
                    </div>
                    <div class="exam-info-item">
                        <label><i class="fas fa-calendar-times"></i> End Date</label>
                        <div class="value"><?php echo date('M d, Y g:i A', strtotime($exam['end_date'])); ?></div>
                    </div>
                    <div class="exam-info-item">
                        <label><i class="fas fa-star"></i> Total Points</label>
                        <div class="value"><?php echo number_format($exam['total_points'], 2); ?></div>
                    </div>
                    <div class="exam-info-item">
                        <label><i class="fas fa-info-circle"></i> Status</label>
                        <div class="value">
                            <span class="badge badge-<?php echo $exam['last_status'] === 'Graded' ? 'success' : 'info'; ?>">
                                <?php echo sanitizeOutput($exam['last_status']); ?>
                            </span>
                        </div>
                    </div>
                </div>

                <?php if (!empty($exam['description'])): ?>
                    <div class="exam-description">
                        <?php echo nl2br(sanitizeOutput($exam['description'])); ?>
                    </div>
                <?php endif; ?>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>

    <!-- Upcoming Examinations -->
    <?php if (!empty($upcoming_exams)): ?>
    <div class="card" style="margin-bottom: 2rem;">
        <h2 class="section-header">
            <i class="fas fa-calendar-plus"></i> Upcoming Examinations (<?php echo count($upcoming_exams); ?>)
        </h2>
        <div style="display: flex; flex-direction: column; gap: 1.5rem;">
            <?php foreach ($upcoming_exams as $exam): ?>
            <div class="exam-card upcoming">
                <div class="exam-header">
                    <div class="exam-title">
                        <h3><?php echo sanitizeOutput($exam['title']); ?></h3>
                        <div class="exam-badges">
                            <span class="badge badge-info"><?php echo sanitizeOutput($exam['exam_type']); ?></span>
                            <?php if ($exam['course_name']): ?>
                                <span class="badge badge-secondary"><?php echo sanitizeOutput($exam['course_code']); ?></span>
                            <?php endif; ?>
                            <?php if ($exam['section_number']): ?>
                                <span class="badge badge-secondary">Section <?php echo sanitizeOutput($exam['section_number']); ?></span>
                            <?php endif; ?>
                            <span class="badge badge-warning">Upcoming</span>
                        </div>
                    </div>
                </div>
                
                <div class="exam-info-grid">
                    <div class="exam-info-item">
                        <label><i class="fas fa-calendar-alt"></i> Start Date</label>
                        <div class="value"><?php echo date('M d, Y g:i A', strtotime($exam['start_date'])); ?></div>
                    </div>
                    <div class="exam-info-item">
                        <label><i class="fas fa-calendar-times"></i> End Date</label>
                        <div class="value"><?php echo date('M d, Y g:i A', strtotime($exam['end_date'])); ?></div>
                    </div>
                    <div class="exam-info-item">
                        <label><i class="fas fa-clock"></i> Time Limit</label>
                        <div class="value"><?php echo $exam['time_limit'] ? $exam['time_limit'] . ' minutes' : 'No limit'; ?></div>
                    </div>
                    <div class="exam-info-item">
                        <label><i class="fas fa-star"></i> Total Points</label>
                        <div class="value"><?php echo number_format($exam['total_points'], 2); ?></div>
                    </div>
                </div>

                <?php if (!empty($exam['description'])): ?>
                    <div class="exam-description">
                        <?php echo nl2br(sanitizeOutput($exam['description'])); ?>
                    </div>
                <?php endif; ?>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>

    <!-- Past Examinations -->
    <?php if (!empty($past_exams)): ?>
    <div class="card">
        <h2 class="section-header">
            <i class="fas fa-history"></i> Past Examinations (<?php echo count($past_exams); ?>)
        </h2>
        <div style="display: flex; flex-direction: column; gap: 1.5rem;">
            <?php foreach ($past_exams as $exam): 
                $has_attempt = (int)$exam['attempt_count'] > 0;
            ?>
            <div class="exam-card past">
                <div class="exam-header">
                    <div class="exam-title">
                        <h3><?php echo sanitizeOutput($exam['title']); ?></h3>
                        <div class="exam-badges">
                            <span class="badge badge-info"><?php echo sanitizeOutput($exam['exam_type']); ?></span>
                            <?php if ($exam['course_name']): ?>
                                <span class="badge badge-secondary"><?php echo sanitizeOutput($exam['course_code']); ?></span>
                            <?php endif; ?>
                            <span class="badge badge-secondary">Ended</span>
                        </div>
                    </div>
                    <div>
                        <?php if ($has_attempt): ?>
                            <a href="<?php echo BASE_URL; ?>modules/examinations/student_result.php?exam_id=<?php echo $exam['exam_id']; ?>" 
                               class="btn btn-primary">
                                <i class="fas fa-eye"></i> View Results
                            </a>
                        <?php else: ?>
                            <span class="badge badge-secondary">No Attempt</span>
                        <?php endif; ?>
                    </div>
                </div>
                
                <div class="exam-info-grid">
                    <div class="exam-info-item">
                        <label><i class="fas fa-calendar-alt"></i> Start Date</label>
                        <div class="value"><?php echo date('M d, Y g:i A', strtotime($exam['start_date'])); ?></div>
                    </div>
                    <div class="exam-info-item">
                        <label><i class="fas fa-calendar-times"></i> End Date</label>
                        <div class="value"><?php echo date('M d, Y g:i A', strtotime($exam['end_date'])); ?></div>
                    </div>
                    <div class="exam-info-item">
                        <label><i class="fas fa-star"></i> Total Points</label>
                        <div class="value"><?php echo number_format($exam['total_points'], 2); ?></div>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>
    <?php endif; ?>
</div>

<?php include __DIR__ . '/../../includes/footer.php'; ?>

