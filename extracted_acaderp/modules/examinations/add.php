<?php
/**
 * Create New Examination
 * Form to create a new examination with questions
 */

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/feature_toggles.php';
requireAuth([ROLE_ADMIN, ROLE_FACULTY]);

$page_title = 'Create New Examination';
$pdo = getDBConnection();
$error = '';
$success = '';

// Check if examinations feature is enabled
if (!isExaminationsEnabled($pdo)) {
    header('Location: ' . BASE_URL . 'dashboard/index.php?error=The Examinations feature is currently disabled');
    exit;
}

// Check if examinations table exists
$table_exists = false;
try {
    $stmt = $pdo->query("SHOW TABLES LIKE 'examinations'");
    $table_exists = $stmt->rowCount() > 0;
} catch (PDOException $e) {
    $table_exists = false;
}

if (!$table_exists) {
    $error = 'The examinations table does not exist. Please run the migration: database/migration_online_examinations.sql';
}

// Get courses and sections for dropdowns
$courses = [];
$sections = [];
$question_banks = [];

if ($table_exists && $pdo) {
    try {
        // Get courses (filter by faculty's assigned sections if not admin)
        if (hasRole(ROLE_FACULTY) && !hasRole(ROLE_ADMIN)) {
            $faculty_id = null;
            $stmt = $pdo->prepare("SELECT faculty_id, department_id FROM faculty WHERE user_id = ?");
            $stmt->execute([$_SESSION['user_id']]);
            $faculty = $stmt->fetch();
            if ($faculty) {
                $faculty_id = $faculty['faculty_id'];
                
                // Get only courses where faculty is assigned to teach sections
                $stmt = $pdo->prepare("SELECT DISTINCT 
                                      c.course_id, 
                                      c.course_code, 
                                      c.course_name, 
                                      c.department_id
                                      FROM courses c
                                      INNER JOIN class_sections cs ON c.course_id = cs.course_id
                                      WHERE c.status = 'Active'
                                      AND cs.status = 'Open'
                                      AND cs.faculty_id = ?
                                      ORDER BY c.course_code, c.course_name");
                $stmt->execute([$faculty_id]);
                $courses = $stmt->fetchAll();
            }
        } else {
            // Admin sees all courses
            $stmt = $pdo->query("SELECT course_id, course_code, course_name, department_id FROM courses WHERE status = 'Active' ORDER BY course_name");
            $courses = $stmt->fetchAll();
        }
        
        // Get sections (filter by faculty's assigned sections if not admin)
        if (hasRole(ROLE_FACULTY) && !hasRole(ROLE_ADMIN)) {
            if (isset($faculty_id)) {
                // Only show sections where faculty is assigned to teach
                $stmt = $pdo->prepare("SELECT DISTINCT 
                                      cs.section_id, 
                                      cs.section_number, 
                                      cs.course_id, 
                                      c.course_name, 
                                      c.course_code, 
                                      c.department_id,
                                      cs.semester, 
                                      cs.academic_year,
                                      COUNT(DISTINCT e.student_id) as student_count
                                      FROM class_sections cs
                                      INNER JOIN courses c ON cs.course_id = c.course_id
                                      LEFT JOIN enrollments e ON cs.section_id = e.section_id AND e.status = 'Enrolled'
                                      WHERE cs.status = 'Open'
                                      AND c.status = 'Active'
                                      AND cs.faculty_id = ?
                                      GROUP BY cs.section_id, cs.section_number, cs.course_id, c.course_name, 
                                               c.course_code, c.department_id, cs.semester, cs.academic_year
                                      ORDER BY cs.academic_year DESC, cs.semester, c.course_code, cs.section_number");
                $stmt->execute([$faculty_id]);
                $sections = $stmt->fetchAll();
            } else {
                $sections = []; // No faculty found
            }
        } else {
            $stmt = $pdo->query("SELECT cs.section_id, cs.section_number, cs.course_id, c.course_name, c.course_code, cs.semester, cs.academic_year
                                FROM class_sections cs
                                LEFT JOIN courses c ON cs.course_id = c.course_id
                                WHERE cs.status = 'Open'
                                ORDER BY cs.academic_year DESC, cs.semester, c.course_name");
            $sections = $stmt->fetchAll();
        }
        
        // Get question banks
        $stmt = $pdo->query("SELECT question_bank_id, name FROM question_banks ORDER BY name");
        $question_banks = $stmt->fetchAll();
        
        // Get all questions from question banks
        $all_questions = [];
        try {
            $query = "SELECT q.question_id, q.question_text, q.question_type, q.points, 
                     qb.name as bank_name, qb.question_bank_id
                     FROM questions q
                     LEFT JOIN question_banks qb ON q.question_bank_id = qb.question_bank_id
                     WHERE q.question_bank_id IS NOT NULL";
            
            // Filter by creator if faculty
            if (hasRole(ROLE_FACULTY) && !hasRole(ROLE_ADMIN)) {
                $query .= " AND q.created_by = ?";
                $stmt = $pdo->prepare($query);
                $stmt->execute([$_SESSION['user_id']]);
            } else {
                $stmt = $pdo->query($query);
            }
            $all_questions = $stmt->fetchAll();
        } catch (PDOException $e) {
            // Questions table might not exist
        }
    } catch (PDOException $e) {
        // Tables might not exist yet
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = sanitizeInput($_POST['title'] ?? '');
    $description = sanitizeInput($_POST['description'] ?? '');
    $course_id = !empty($_POST['course_id']) ? (int)$_POST['course_id'] : null;
    $section_id = !empty($_POST['section_id']) ? (int)$_POST['section_id'] : null;
    $exam_type = sanitizeInput($_POST['exam_type'] ?? 'Quiz');
    $time_limit = !empty($_POST['time_limit']) ? (int)$_POST['time_limit'] : null;
    $start_date = $_POST['start_date'] ?? '';
    $end_date = $_POST['end_date'] ?? '';
    $allow_late_submission = isset($_POST['allow_late_submission']) ? 1 : 0;
    $show_results_immediately = isset($_POST['show_results_immediately']) ? 1 : 0;
    $randomize_questions = isset($_POST['randomize_questions']) ? 1 : 0;
    $randomize_options = isset($_POST['randomize_options']) ? 1 : 0;
    $require_password = isset($_POST['require_password']) ? 1 : 0;
    $exam_password = !empty($_POST['exam_password']) ? sanitizeInput($_POST['exam_password']) : null;
    $instructions = sanitizeInput($_POST['instructions'] ?? '');
    $is_active = isset($_POST['is_active']) ? 1 : 0;
    
    // Get question IDs from form
    $question_ids = isset($_POST['question_ids']) && is_array($_POST['question_ids']) 
        ? array_map('intval', $_POST['question_ids']) 
        : [];
    $question_points = isset($_POST['question_points']) && is_array($_POST['question_points'])
        ? $_POST['question_points']
        : [];

    if (empty($title)) {
        $error = 'Title is required.';
    } elseif (empty($start_date) || empty($end_date)) {
        $error = 'Start date and end date are required.';
    } elseif (strtotime($end_date) <= strtotime($start_date)) {
        $error = 'End date must be after start date.';
    } elseif (empty($question_ids)) {
        $error = 'At least one question is required.';
    } elseif ($require_password && empty($exam_password)) {
        $error = 'Exam password is required when password protection is enabled.';
    } elseif (hasRole(ROLE_FACULTY) && !hasRole(ROLE_ADMIN) && empty($section_id)) {
        // Faculty must select a section (they can only create exams for their assigned sections)
        $error = 'Section is required. You can only create examinations for sections you are assigned to teach.';
    } else {
        // Validate section access for faculty (assigned sections only)
        if (hasRole(ROLE_FACULTY) && !hasRole(ROLE_ADMIN)) {
            $faculty_id = null;
            $stmt = $pdo->prepare("SELECT faculty_id FROM faculty WHERE user_id = ?");
            $stmt->execute([$_SESSION['user_id']]);
            $faculty = $stmt->fetch();
            if ($faculty) {
                $faculty_id = $faculty['faculty_id'];
                
                // Check if faculty is assigned to this section
                $stmt = $pdo->prepare("SELECT cs.section_id, c.course_name, c.course_code
                                      FROM class_sections cs
                                      INNER JOIN courses c ON cs.course_id = c.course_id
                                      WHERE cs.section_id = ?
                                      AND cs.faculty_id = ?");
                $stmt->execute([$section_id, $faculty_id]);
                $result = $stmt->fetch();
                
                if (!$result) {
                    $error = 'You can only create examinations for sections you are assigned to teach.';
                }
            } else {
                $error = 'Faculty information not found.';
            }
        }
        
        if (empty($error)) {
        try {
            $pdo->beginTransaction();
            
            // Calculate total points
            $total_points = 0;
            foreach ($question_ids as $idx => $qid) {
                $points = isset($question_points[$qid]) && $question_points[$qid] > 0 
                    ? (float)$question_points[$qid] 
                    : 1.00;
                $total_points += $points;
            }
            
            // Insert examination
            $stmt = $pdo->prepare("INSERT INTO examinations (title, description, course_id, section_id, exam_type, 
                                  total_points, time_limit, start_date, end_date, allow_late_submission, 
                                  show_results_immediately, randomize_questions, randomize_options, 
                                  require_password, exam_password, instructions, is_active, created_by) 
                                  VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([
                $title, $description, $course_id, $section_id, $exam_type, $total_points, $time_limit,
                $start_date, $end_date, $allow_late_submission, $show_results_immediately,
                $randomize_questions, $randomize_options, $require_password, $exam_password,
                $instructions, $is_active, $_SESSION['user_id']
            ]);
            
            $exam_id = $pdo->lastInsertId();
            
            // Add questions to exam
            $order = 0;
            foreach ($question_ids as $qid) {
                $points = isset($question_points[$qid]) && $question_points[$qid] > 0 
                    ? (float)$question_points[$qid] 
                    : 1.00;
                
                $stmt = $pdo->prepare("INSERT INTO exam_questions (exam_id, question_id, points, question_order) 
                                      VALUES (?, ?, ?, ?)");
                $stmt->execute([$exam_id, $qid, $points, $order++]);
            }
            
            $pdo->commit();
            header('Location: ' . BASE_URL . 'modules/examinations/index.php?success=Examination created successfully');
            exit;
        } catch (PDOException $e) {
            $pdo->rollBack();
            $error = 'Failed to create examination: ' . $e->getMessage();
        }
        }
    }
}

include __DIR__ . '/../../includes/header.php';
?>

<div class="page-container">
    <div class="page-header">
        <h1><i class="fas fa-plus-circle"></i> Create New Examination</h1>
        <a href="<?php echo BASE_URL; ?>modules/examinations/index.php" class="btn btn-secondary">
            <i class="fas fa-arrow-left"></i> Back to List
        </a>
    </div>

    <?php if ($error): ?>
        <div class="alert alert-error">
            <i class="fas fa-exclamation-circle"></i> <?php echo sanitizeOutput($error); ?>
        </div>
    <?php endif; ?>

    <?php 
    // Show assigned courses info for faculty
    if (hasRole(ROLE_FACULTY) && !hasRole(ROLE_ADMIN) && $pdo): 
        // Get faculty_id first
        $stmt = $pdo->prepare("SELECT faculty_id FROM faculty WHERE user_id = ?");
        $stmt->execute([$_SESSION['user_id']]);
        $faculty_data = $stmt->fetch();
        
        if ($faculty_data && $faculty_data['faculty_id']) {
            $faculty_id = $faculty_data['faculty_id'];
            
            // Count how many assigned sections faculty has
            $stmt = $pdo->prepare("SELECT COUNT(DISTINCT cs.section_id) as section_count,
                                  COUNT(DISTINCT c.course_id) as course_count
                                  FROM class_sections cs
                                  INNER JOIN courses c ON cs.course_id = c.course_id
                                  WHERE cs.status = 'Open'
                                  AND c.status = 'Active'
                                  AND cs.faculty_id = ?");
            $stmt->execute([$faculty_id]);
            $assigned_info = $stmt->fetch();
        } else {
            $assigned_info = null;
        }
        
        // Also check if courses/sections are available in the arrays
        $has_courses = !empty($courses);
        $has_sections = !empty($sections);
        
        if (($assigned_info && ($assigned_info['section_count'] > 0 || $assigned_info['course_count'] > 0)) || $has_courses || $has_sections):
    ?>
        <div class="alert alert-info" style="background: linear-gradient(135deg, #e0f2fe 0%, #dbeafe 100%); border: 2px solid #0ea5e9; color: #0369a1;">
            <i class="fas fa-chalkboard-teacher"></i> 
            <strong>My Assigned Courses:</strong> 
            <?php if ($assigned_info && ($assigned_info['section_count'] > 0 || $assigned_info['course_count'] > 0)): ?>
                You are assigned to teach <?php echo $assigned_info['course_count']; ?> course(s) with <?php echo $assigned_info['section_count']; ?> section(s).
            <?php elseif ($has_courses || $has_sections): ?>
                You have <?php echo count($courses); ?> course(s) and <?php echo count($sections); ?> section(s) assigned.
            <?php endif; ?>
            <br>
            <small><i class="fas fa-info-circle"></i> You can only create examinations for these assigned sections.</small>
        </div>
    <?php 
        elseif ($faculty_data && $faculty_data['faculty_id']):
    ?>
        <div class="alert alert-warning">
            <i class="fas fa-exclamation-triangle"></i> 
            <strong>No Assigned Sections:</strong> You currently have no sections assigned to you. Please contact the administrator.
        </div>
    <?php 
        endif;
    endif; 
    ?>

    <?php if ($table_exists): ?>
    <div class="card">
        <form method="POST" action="" class="form" id="examForm">
            <div class="form-section">
                <h3>Basic Information</h3>
                
                <div class="form-group">
                    <label for="title">Exam Title <span class="required">*</span></label>
                    <input type="text" id="title" name="title" required class="form-control" 
                           placeholder="Enter exam title"
                           value="<?php echo sanitizeOutput($_POST['title'] ?? ''); ?>">
                </div>

                <div class="form-group">
                    <label for="description">Description</label>
                    <textarea id="description" name="description" class="form-control" rows="3"
                              placeholder="Enter exam description"><?php echo sanitizeOutput($_POST['description'] ?? ''); ?></textarea>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label for="exam_type">Exam Type <span class="required">*</span></label>
                        <select id="exam_type" name="exam_type" required class="form-control">
                            <option value="Quiz" <?php echo (($_POST['exam_type'] ?? 'Quiz') === 'Quiz') ? 'selected' : ''; ?>>Quiz</option>
                            <option value="Midterm" <?php echo (($_POST['exam_type'] ?? '') === 'Midterm') ? 'selected' : ''; ?>>Midterm</option>
                            <option value="Final" <?php echo (($_POST['exam_type'] ?? '') === 'Final') ? 'selected' : ''; ?>>Final</option>
                            <option value="Assignment" <?php echo (($_POST['exam_type'] ?? '') === 'Assignment') ? 'selected' : ''; ?>>Assignment</option>
                            <option value="Other" <?php echo (($_POST['exam_type'] ?? '') === 'Other') ? 'selected' : ''; ?>>Other</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="course_id">Course</label>
                        <select id="course_id" name="course_id" class="form-control" onchange="updateSections()">
                            <option value="">Select Course (Optional)</option>
                            <?php foreach ($courses as $course): ?>
                                <option value="<?php echo $course['course_id']; ?>" 
                                        <?php echo (isset($_POST['course_id']) && $_POST['course_id'] == $course['course_id']) ? 'selected' : ''; ?>>
                                    <?php echo sanitizeOutput($course['course_code'] . ' - ' . $course['course_name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="section_id">Section <?php if (hasRole(ROLE_FACULTY) && !hasRole(ROLE_ADMIN)): ?><span class="required">*</span><?php endif; ?></label>
                        <select id="section_id" name="section_id" class="form-control" <?php if (hasRole(ROLE_FACULTY) && !hasRole(ROLE_ADMIN)): ?>required<?php endif; ?>>
                            <option value=""><?php echo (hasRole(ROLE_FACULTY) && !hasRole(ROLE_ADMIN)) ? 'Select Advisory Section (Required)' : 'Select Section (Optional)'; ?></option>
                            <?php 
                            // Only show sections if no course is pre-selected, or show all sections initially
                            // The JavaScript will filter them dynamically
                            $preselected_course_id = isset($_POST['course_id']) ? (int)$_POST['course_id'] : 0;
                            foreach ($sections as $section): 
                                // If a course is pre-selected, only show sections for that course
                                if ($preselected_course_id && ($section['course_id'] ?? 0) != $preselected_course_id) {
                                    continue;
                                }
                            ?>
                                <option value="<?php echo $section['section_id']; ?>" 
                                        data-course-id="<?php echo $section['course_id'] ?? ''; ?>"
                                        <?php echo (isset($_POST['section_id']) && $_POST['section_id'] == $section['section_id']) ? 'selected' : ''; ?>>
                                    <?php 
                                    $sectionText = '';
                                    if ($section['course_code']) {
                                        $sectionText .= $section['course_code'];
                                    }
                                    if ($section['course_name']) {
                                        $sectionText .= ' - ' . $section['course_name'];
                                    }
                                    $sectionText .= ' - Section ' . $section['section_number'];
                                    if ($section['semester'] && $section['academic_year']) {
                                        $sectionText .= ' (' . $section['semester'] . ' ' . $section['academic_year'] . ')';
                                    }
                                    // Show student count for faculty
                                    if (hasRole(ROLE_FACULTY) && !hasRole(ROLE_ADMIN) && isset($section['student_count'])) {
                                        $sectionText .= ' - ' . $section['student_count'] . ' student(s) enrolled';
                                    }
                                    echo sanitizeOutput($sectionText);
                                    ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <?php if (hasRole(ROLE_FACULTY) && !hasRole(ROLE_ADMIN)): ?>
                            <small class="text-muted">
                                <i class="fas fa-info-circle"></i> 
                                You can only create examinations for sections you are assigned to teach.
                                <?php if (empty($sections)): ?>
                                    <span style="color: #f59e0b; font-weight: 600;">No assigned sections available.</span>
                                <?php endif; ?>
                            </small>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <div class="form-section">
                <h3>Schedule & Timing</h3>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="start_date">Start Date & Time <span class="required">*</span></label>
                        <input type="datetime-local" id="start_date" name="start_date" required class="form-control"
                               value="<?php echo sanitizeOutput($_POST['start_date'] ?? ''); ?>">
                    </div>

                    <div class="form-group">
                        <label for="end_date">End Date & Time <span class="required">*</span></label>
                        <input type="datetime-local" id="end_date" name="end_date" required class="form-control"
                               value="<?php echo sanitizeOutput($_POST['end_date'] ?? ''); ?>">
                    </div>

                    <div class="form-group">
                        <label for="time_limit">Time Limit (minutes)</label>
                        <input type="number" id="time_limit" name="time_limit" min="1" class="form-control"
                               placeholder="Leave empty for no limit"
                               value="<?php echo sanitizeOutput($_POST['time_limit'] ?? ''); ?>">
                        <small class="text-muted">Leave empty if there's no time limit</small>
                    </div>
                </div>
            </div>

            <div class="form-section">
                <h3>Settings</h3>
                
                <div class="form-row">
                    <div class="form-group">
                        <label>
                            <input type="checkbox" name="allow_late_submission" value="1" 
                                   <?php echo (!isset($_POST['allow_late_submission']) || $_POST['allow_late_submission'] == '1') ? 'checked' : ''; ?>>
                            Allow Late Submission
                        </label>
                    </div>

                    <div class="form-group">
                        <label>
                            <input type="checkbox" name="show_results_immediately" value="1" 
                                   <?php echo (!isset($_POST['show_results_immediately']) || $_POST['show_results_immediately'] == '1') ? 'checked' : ''; ?>>
                            Show Results Immediately
                        </label>
                    </div>

                    <div class="form-group">
                        <label>
                            <input type="checkbox" name="randomize_questions" value="1" 
                                   <?php echo (isset($_POST['randomize_questions']) && $_POST['randomize_questions'] == '1') ? 'checked' : ''; ?>>
                            Randomize Questions
                        </label>
                    </div>

                    <div class="form-group">
                        <label>
                            <input type="checkbox" name="randomize_options" value="1" 
                                   <?php echo (isset($_POST['randomize_options']) && $_POST['randomize_options'] == '1') ? 'checked' : ''; ?>>
                            Randomize Options
                        </label>
                    </div>

                    <div class="form-group">
                        <label>
                            <input type="checkbox" name="require_password" value="1" 
                                   id="require_password_checkbox"
                                   onchange="togglePasswordField()"
                                   <?php echo (isset($_POST['require_password']) && $_POST['require_password'] == '1') ? 'checked' : ''; ?>>
                            Require Password
                        </label>
                    </div>

                    <div class="form-group">
                        <label>
                            <input type="checkbox" name="is_active" value="1" 
                                   <?php echo (!isset($_POST['is_active']) || $_POST['is_active'] == '1') ? 'checked' : ''; ?>>
                            Active
                        </label>
                    </div>
                </div>

                <div class="form-group" id="password_field" style="display: none;">
                    <label for="exam_password">Exam Password <span class="required">*</span></label>
                    <input type="text" id="exam_password" name="exam_password" class="form-control"
                           placeholder="Enter exam password"
                           value="<?php echo sanitizeOutput($_POST['exam_password'] ?? ''); ?>">
                </div>

                <div class="form-group">
                    <label for="instructions">Instructions</label>
                    <textarea id="instructions" name="instructions" class="form-control" rows="4"
                              placeholder="Enter exam instructions for students"><?php echo sanitizeOutput($_POST['instructions'] ?? ''); ?></textarea>
                </div>
            </div>

            <div class="form-section">
                <h3>Questions <span class="required">*</span></h3>
                <p class="text-muted">Select questions from question banks or create new questions. At least one question is required.</p>
                
                <div style="margin-bottom: 1rem; display: flex; gap: 0.5rem; flex-wrap: wrap;">
                    <a href="<?php echo BASE_URL; ?>modules/examinations/question_banks.php" class="btn btn-secondary" target="_blank">
                        <i class="fas fa-book"></i> Manage Question Banks
                    </a>
                    <a href="<?php echo BASE_URL; ?>modules/examinations/question_add.php" class="btn btn-secondary" target="_blank">
                        <i class="fas fa-plus"></i> Add New Question
                    </a>
                    <button type="button" class="btn btn-info" onclick="selectAllQuestions()">
                        <i class="fas fa-check-square"></i> Select All
                    </button>
                    <button type="button" class="btn btn-info" onclick="deselectAllQuestions()">
                        <i class="fas fa-square"></i> Deselect All
                    </button>
                </div>

                <div id="questions_container" style="max-height: 600px; overflow-y: auto; border: 2px solid #e2e8f0; border-radius: 12px; padding: 1rem;">
                    <?php if (empty($all_questions)): ?>
                        <p class="text-muted">No questions available. Please create question banks and add questions first.</p>
                    <?php else: ?>
                        <?php 
                        // Group questions by bank
                        $questions_by_bank = [];
                        foreach ($all_questions as $q) {
                            $bank_id = $q['question_bank_id'] ?? 'none';
                            if (!isset($questions_by_bank[$bank_id])) {
                                $questions_by_bank[$bank_id] = [
                                    'bank_name' => $q['bank_name'] ?? 'Unassigned',
                                    'questions' => []
                                ];
                            }
                            $questions_by_bank[$bank_id]['questions'][] = $q;
                        }
                        ?>
                        <?php foreach ($questions_by_bank as $bank_id => $bank_data): ?>
                            <div style="margin-bottom: 2rem; padding-bottom: 1.5rem; border-bottom: 2px solid #e2e8f0;">
                                <h4 style="color: #1e40af; margin-bottom: 1rem; display: flex; align-items: center; gap: 0.5rem;">
                                    <i class="fas fa-book"></i>
                                    <?php echo sanitizeOutput($bank_data['bank_name']); ?>
                                    <span style="font-size: 0.875rem; color: #64748b; font-weight: normal;">
                                        (<?php echo count($bank_data['questions']); ?> questions)
                                    </span>
                                </h4>
                                <div style="display: flex; flex-direction: column; gap: 1rem;">
                                    <?php foreach ($bank_data['questions'] as $q): ?>
                                        <div style="padding: 1rem; background: #f8fafc; border-radius: 8px; border: 2px solid #e2e8f0; transition: all 0.3s;" 
                                             class="question-item"
                                             onmouseover="this.style.borderColor='#2563eb'; this.style.background='#f1f5f9';"
                                             onmouseout="this.style.borderColor='#e2e8f0'; this.style.background='#f8fafc';">
                                            <div style="display: flex; align-items: start; gap: 1rem;">
                                                <div style="flex-shrink: 0; margin-top: 0.25rem;">
                                                    <input type="checkbox" 
                                                           name="question_ids[]" 
                                                           value="<?php echo $q['question_id']; ?>"
                                                           id="question_<?php echo $q['question_id']; ?>"
                                                           class="question-checkbox"
                                                           onchange="updateQuestionPoints(<?php echo $q['question_id']; ?>)"
                                                           style="width: 20px; height: 20px; cursor: pointer;">
                                                </div>
                                                <div style="flex: 1;">
                                                    <label for="question_<?php echo $q['question_id']; ?>" style="cursor: pointer; display: block;">
                                                        <div style="display: flex; align-items: center; gap: 0.5rem; margin-bottom: 0.5rem;">
                                                            <span class="badge badge-info" style="font-size: 0.75rem;">
                                                                <?php echo sanitizeOutput($q['question_type']); ?>
                                                            </span>
                                                            <span style="font-size: 0.875rem; color: #64748b;">
                                                                Default: <?php echo number_format($q['points'], 2); ?> points
                                                            </span>
                                                        </div>
                                                        <div style="color: #1e293b; font-weight: 500;">
                                                            <?php echo nl2br(sanitizeOutput($q['question_text'])); ?>
                                                        </div>
                                                    </label>
                                                    <div style="margin-top: 0.75rem; display: none;" 
                                                         id="points_container_<?php echo $q['question_id']; ?>"
                                                         class="points-container">
                                                        <label style="font-size: 0.875rem; color: #64748b; display: flex; align-items: center; gap: 0.5rem;">
                                                            <i class="fas fa-star"></i>
                                                            Points for this exam:
                                                            <input type="number" 
                                                                   name="question_points[<?php echo $q['question_id']; ?>]"
                                                                   value="<?php echo number_format($q['points'], 2); ?>"
                                                                   min="0.01" 
                                                                   step="0.01"
                                                                   style="width: 100px; padding: 0.375rem; border: 1px solid #cbd5e1; border-radius: 6px; margin-left: 0.5rem;"
                                                                   onchange="validatePoints(this)">
                                                        </label>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
                <div id="selected_count" style="margin-top: 1rem; padding: 1rem; background: #e0f2fe; border-radius: 8px; border: 2px solid #0ea5e9;">
                    <strong style="color: #0369a1;">
                        <i class="fas fa-check-circle"></i> 
                        <span id="selected_count_text">0 questions selected</span>
                    </strong>
                </div>
            </div>

            <div class="form-actions">
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-save"></i> Create Examination
                </button>
                <a href="<?php echo BASE_URL; ?>modules/examinations/index.php" class="btn btn-secondary">
                    <i class="fas fa-times"></i> Cancel
                </a>
            </div>
        </form>
    </div>
    <?php endif; ?>
</div>

<script>
function togglePasswordField() {
    const checkbox = document.getElementById('require_password_checkbox');
    const passwordField = document.getElementById('password_field');
    const passwordInput = document.getElementById('exam_password');
    
    if (checkbox.checked) {
        passwordField.style.display = 'block';
        passwordInput.required = true;
    } else {
        passwordField.style.display = 'none';
        passwordInput.required = false;
        passwordInput.value = '';
    }
}

function updateSections() {
    const courseId = document.getElementById('course_id').value;
    const sectionSelect = document.getElementById('section_id');
    const currentValue = sectionSelect.value; // Save current selection
    
    // Clear existing options except the first one
    sectionSelect.innerHTML = '<option value="">Select Section (Optional)</option>';
    
    if (!courseId) {
        // If no course selected, show all sections
        loadAllSections();
        return;
    }
    
    // Show loading state
    sectionSelect.disabled = true;
    sectionSelect.innerHTML = '<option value="">Loading sections...</option>';
    
    // Fetch sections for the selected course via AJAX
    // Construct URL dynamically based on current page location
    const currentPath = window.location.pathname;
    const sectionsUrl = currentPath.replace(/add\.php$/, 'get_sections.php');
    const fullUrl = sectionsUrl + '?course_id=' + courseId;
    
    console.log('Fetching sections from:', fullUrl); // Debug log
    
    fetch(fullUrl)
        .then(response => {
            if (!response.ok) {
                throw new Error('Network response was not ok: ' + response.status);
            }
            return response.text(); // Get as text first to check for errors
        })
        .then(text => {
            // Check if response is HTML (likely a redirect or error page)
            if (text.trim().startsWith('<!DOCTYPE') || text.trim().startsWith('<html')) {
                throw new Error('Received HTML instead of JSON. Possible redirect or error page.');
            }
            
            try {
                const data = JSON.parse(text);
                sectionSelect.disabled = false;
                sectionSelect.innerHTML = '<option value="">Select Section (Optional)</option>';
                
                if (!data.success) {
                    // Server returned an error
                    const errorOption = document.createElement('option');
                    errorOption.value = '';
                    errorOption.textContent = data.message || 'Error loading sections';
                    errorOption.disabled = true;
                    sectionSelect.appendChild(errorOption);
                    return;
                }
                
                if (data.sections && data.sections.length > 0) {
                    // Add sections to dropdown
                    data.sections.forEach(section => {
                        const option = document.createElement('option');
                        option.value = section.section_id;
                        option.textContent = section.text;
                        // Restore previous selection if it matches
                        if (currentValue && currentValue == section.section_id) {
                            option.selected = true;
                        }
                        sectionSelect.appendChild(option);
                    });
                } else {
                    // No sections available
                    const option = document.createElement('option');
                    option.value = '';
                    option.textContent = data.message || 'No sections available for this course';
                    option.disabled = true;
                    sectionSelect.appendChild(option);
                }
            } catch (parseError) {
                console.error('JSON Parse Error:', parseError);
                console.error('Response text:', text);
                throw new Error('Invalid JSON response. Check console for details.');
            }
        })
        .catch(error => {
            sectionSelect.disabled = false;
            sectionSelect.innerHTML = '<option value="">Error loading sections</option>';
            console.error('Error loading sections:', error);
            // Show a more helpful error message
            const errorOption = document.createElement('option');
            errorOption.value = '';
            errorOption.textContent = 'Error: ' + error.message;
            errorOption.disabled = true;
            sectionSelect.appendChild(errorOption);
        });
}

function loadAllSections() {
    const sectionSelect = document.getElementById('section_id');
    const currentValue = sectionSelect.value;
    
    // Restore all sections from the original data
    const allSections = <?php echo json_encode(array_map(function($s) {
        $text = '';
        if ($s['course_code']) {
            $text .= $s['course_code'] . ' - ';
        }
        $text .= 'Section ' . $s['section_number'];
        if ($s['semester'] && $s['academic_year']) {
            $text .= ' (' . $s['semester'] . ' ' . $s['academic_year'] . ')';
        }
        return [
            'section_id' => $s['section_id'],
            'text' => $text,
            'course_id' => $s['course_id'] ?? ''
        ];
    }, $sections)); ?>;
    
    sectionSelect.innerHTML = '<option value="">Select Section (Optional)</option>';
    
    allSections.forEach(section => {
        const option = document.createElement('option');
        option.value = section.section_id;
        option.textContent = section.text;
        if (currentValue && currentValue == section.section_id) {
            option.selected = true;
        }
        sectionSelect.appendChild(option);
    });
}

// Question selection functions
function updateQuestionPoints(questionId) {
    const checkbox = document.getElementById('question_' + questionId);
    const pointsContainer = document.getElementById('points_container_' + questionId);
    
    if (checkbox.checked) {
        pointsContainer.style.display = 'block';
    } else {
        pointsContainer.style.display = 'none';
    }
    updateSelectedCount();
}

function updateSelectedCount() {
    const checkboxes = document.querySelectorAll('.question-checkbox:checked');
    const count = checkboxes.length;
    const countText = document.getElementById('selected_count_text');
    
    if (count === 0) {
        countText.textContent = '0 questions selected';
        document.getElementById('selected_count').style.background = '#fee2e2';
        document.getElementById('selected_count').style.borderColor = '#ef4444';
        countText.style.color = '#991b1b';
    } else {
        countText.textContent = count + ' question' + (count !== 1 ? 's' : '') + ' selected';
        document.getElementById('selected_count').style.background = '#dcfce7';
        document.getElementById('selected_count').style.borderColor = '#22c55e';
        countText.style.color = '#166534';
    }
}

function selectAllQuestions() {
    const checkboxes = document.querySelectorAll('.question-checkbox');
    checkboxes.forEach(checkbox => {
        checkbox.checked = true;
        const questionId = checkbox.value;
        updateQuestionPoints(questionId);
    });
    updateSelectedCount();
}

function deselectAllQuestions() {
    const checkboxes = document.querySelectorAll('.question-checkbox');
    checkboxes.forEach(checkbox => {
        checkbox.checked = false;
        const questionId = checkbox.value;
        updateQuestionPoints(questionId);
    });
    updateSelectedCount();
}

function validatePoints(input) {
    const value = parseFloat(input.value);
    if (isNaN(value) || value <= 0) {
        input.value = '1.00';
        alert('Points must be a positive number. Defaulting to 1.00.');
    } else {
        input.value = value.toFixed(2);
    }
}

// Form validation
document.getElementById('examForm').addEventListener('submit', function(e) {
    const checkedQuestions = document.querySelectorAll('.question-checkbox:checked');
    if (checkedQuestions.length === 0) {
        e.preventDefault();
        alert('Please select at least one question.');
        return false;
    }
});

// Initialize
document.addEventListener('DOMContentLoaded', function() {
    togglePasswordField();
    
    // Initialize sections based on pre-selected course (if any)
    const courseSelect = document.getElementById('course_id');
    if (courseSelect && courseSelect.value) {
        // If a course is already selected (from POST data), load its sections
        updateSections();
    } else {
        // Otherwise, show all sections
        loadAllSections();
    }
    
    updateSelectedCount();
    
    // Initialize points containers for pre-selected questions (if any)
    const checkboxes = document.querySelectorAll('.question-checkbox');
    checkboxes.forEach(checkbox => {
        if (checkbox.checked) {
            updateQuestionPoints(checkbox.value);
        }
    });
});
</script>

<?php include __DIR__ . '/../../includes/footer.php'; ?>


