<?php
/**
 * Edit Examination
 * Form to edit existing examination
 */

require_once __DIR__ . '/../../config/config.php';
requireAuth([ROLE_ADMIN, ROLE_FACULTY]);

$page_title = 'Edit Examination';
$pdo = getDBConnection();
$error = '';
$exam_id = (int)($_GET['id'] ?? 0);

if (!$exam_id) {
    header('Location: ' . BASE_URL . 'modules/examinations/index.php?error=Invalid exam ID');
    exit;
}

// Get exam data
$exam = null;
try {
    $stmt = $pdo->prepare("SELECT * FROM examinations WHERE exam_id = ?");
    $stmt->execute([$exam_id]);
    $exam = $stmt->fetch();
} catch (PDOException $e) {
    $error = 'Failed to load exam.';
}

if (!$exam) {
    header('Location: ' . BASE_URL . 'modules/examinations/index.php?error=Exam not found');
    exit;
}

// Check permissions (faculty can only edit their own exams)
if (hasRole(ROLE_FACULTY) && !hasRole(ROLE_ADMIN)) {
    if ($exam['created_by'] != $_SESSION['user_id']) {
        // Check if faculty teaches the section
        $faculty_id = null;
        $stmt = $pdo->prepare("SELECT faculty_id FROM faculty WHERE user_id = ?");
        $stmt->execute([$_SESSION['user_id']]);
        $faculty = $stmt->fetch();
        if ($faculty && $exam['section_id']) {
            $stmt = $pdo->prepare("SELECT faculty_id FROM class_sections WHERE section_id = ?");
            $stmt->execute([$exam['section_id']]);
            $section = $stmt->fetch();
            if (!$section || $section['faculty_id'] != $faculty['faculty_id']) {
                header('Location: ' . BASE_URL . 'modules/examinations/index.php?error=Access denied');
                exit;
            }
        } else {
            header('Location: ' . BASE_URL . 'modules/examinations/index.php?error=Access denied');
            exit;
        }
    }
}

// Get courses and sections
$courses = [];
$sections = [];
try {
    $stmt = $pdo->query("SELECT course_id, course_code, course_name FROM courses WHERE status = 'Active' ORDER BY course_name");
    $courses = $stmt->fetchAll();
    
    if (hasRole(ROLE_FACULTY) && !hasRole(ROLE_ADMIN)) {
        $faculty_id = null;
        $stmt = $pdo->prepare("SELECT faculty_id FROM faculty WHERE user_id = ?");
        $stmt->execute([$_SESSION['user_id']]);
        $faculty = $stmt->fetch();
        if ($faculty) {
            $faculty_id = $faculty['faculty_id'];
            $stmt = $pdo->prepare("SELECT cs.section_id, cs.section_number, c.course_name, cs.semester, cs.academic_year
                                  FROM class_sections cs
                                  LEFT JOIN courses c ON cs.course_id = c.course_id
                                  WHERE cs.faculty_id = ? AND cs.status = 'Open'
                                  ORDER BY cs.academic_year DESC, cs.semester, c.course_name");
            $stmt->execute([$faculty_id]);
            $sections = $stmt->fetchAll();
        }
    } else {
        $stmt = $pdo->query("SELECT cs.section_id, cs.section_number, c.course_name, cs.semester, cs.academic_year
                            FROM class_sections cs
                            LEFT JOIN courses c ON cs.course_id = c.course_id
                            WHERE cs.status = 'Open'
                            ORDER BY cs.academic_year DESC, cs.semester, c.course_name");
        $sections = $stmt->fetchAll();
    }
} catch (PDOException $e) {
    // Tables might not exist
}

// Get current questions
$current_questions = [];
$current_question_ids = [];
try {
    $stmt = $pdo->prepare("SELECT q.*, eq.points as exam_points, eq.question_order
                          FROM exam_questions eq
                          INNER JOIN questions q ON eq.question_id = q.question_id
                          WHERE eq.exam_id = ?
                          ORDER BY eq.question_order");
    $stmt->execute([$exam_id]);
    $current_questions = $stmt->fetchAll();
    $current_question_ids = array_column($current_questions, 'question_id');
} catch (PDOException $e) {
    // Error loading questions
}

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
    
    // Get question IDs
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
    } else {
        try {
            $pdo->beginTransaction();
            
            // Calculate total points
            $total_points = 0;
            foreach ($question_ids as $qid) {
                $points = isset($question_points[$qid]) && $question_points[$qid] > 0 
                    ? (float)$question_points[$qid] 
                    : 1.00;
                $total_points += $points;
            }
            
            // Update examination
            $stmt = $pdo->prepare("UPDATE examinations SET title = ?, description = ?, course_id = ?, section_id = ?, 
                                  exam_type = ?, total_points = ?, time_limit = ?, start_date = ?, end_date = ?, 
                                  allow_late_submission = ?, show_results_immediately = ?, randomize_questions = ?, 
                                  randomize_options = ?, require_password = ?, exam_password = ?, instructions = ?, 
                                  is_active = ?, updated_at = CURRENT_TIMESTAMP 
                                  WHERE exam_id = ?");
            $stmt->execute([
                $title, $description, $course_id, $section_id, $exam_type, $total_points, $time_limit,
                $start_date, $end_date, $allow_late_submission, $show_results_immediately,
                $randomize_questions, $randomize_options, $require_password, $exam_password,
                $instructions, $is_active, $exam_id
            ]);
            
            // Remove old questions
            $stmt = $pdo->prepare("DELETE FROM exam_questions WHERE exam_id = ?");
            $stmt->execute([$exam_id]);
            
            // Add new questions
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
            header('Location: ' . BASE_URL . 'modules/examinations/index.php?success=Examination updated successfully');
            exit;
        } catch (PDOException $e) {
            $pdo->rollBack();
            $error = 'Failed to update examination: ' . $e->getMessage();
        }
    }
    
    // Update $exam with POST data for form re-display
    $exam = array_merge($exam, $_POST);
}

include __DIR__ . '/../../includes/header.php';
?>

<div class="page-container">
    <div class="page-header">
        <h1><i class="fas fa-edit"></i> Edit Examination</h1>
        <a href="<?php echo BASE_URL; ?>modules/examinations/index.php" class="btn btn-secondary">
            <i class="fas fa-arrow-left"></i> Back to List
        </a>
    </div>

    <?php if ($error): ?>
        <div class="alert alert-error">
            <i class="fas fa-exclamation-circle"></i> <?php echo sanitizeOutput($error); ?>
        </div>
    <?php endif; ?>

    <div class="card">
        <form method="POST" action="" class="form" id="examForm">
            <div class="form-section">
                <h3>Basic Information</h3>
                
                <div class="form-group">
                    <label for="title">Exam Title <span class="required">*</span></label>
                    <input type="text" id="title" name="title" required class="form-control" 
                           placeholder="Enter exam title"
                           value="<?php echo sanitizeOutput($exam['title']); ?>">
                </div>

                <div class="form-group">
                    <label for="description">Description</label>
                    <textarea id="description" name="description" class="form-control" rows="3"
                              placeholder="Enter exam description"><?php echo sanitizeOutput($exam['description']); ?></textarea>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label for="exam_type">Exam Type <span class="required">*</span></label>
                        <select id="exam_type" name="exam_type" required class="form-control">
                            <option value="Quiz" <?php echo $exam['exam_type'] === 'Quiz' ? 'selected' : ''; ?>>Quiz</option>
                            <option value="Midterm" <?php echo $exam['exam_type'] === 'Midterm' ? 'selected' : ''; ?>>Midterm</option>
                            <option value="Final" <?php echo $exam['exam_type'] === 'Final' ? 'selected' : ''; ?>>Final</option>
                            <option value="Assignment" <?php echo $exam['exam_type'] === 'Assignment' ? 'selected' : ''; ?>>Assignment</option>
                            <option value="Other" <?php echo $exam['exam_type'] === 'Other' ? 'selected' : ''; ?>>Other</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="course_id">Course</label>
                        <select id="course_id" name="course_id" class="form-control" onchange="updateSections()">
                            <option value="">Select Course (Optional)</option>
                            <?php foreach ($courses as $course): ?>
                                <option value="<?php echo $course['course_id']; ?>" 
                                        <?php echo $exam['course_id'] == $course['course_id'] ? 'selected' : ''; ?>>
                                    <?php echo sanitizeOutput($course['course_code'] . ' - ' . $course['course_name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="section_id">Section</label>
                        <select id="section_id" name="section_id" class="form-control">
                            <option value="">Select Section (Optional)</option>
                            <?php foreach ($sections as $section): ?>
                                <option value="<?php echo $section['section_id']; ?>" 
                                        data-course-id="<?php echo $section['course_id'] ?? ''; ?>"
                                        <?php echo $exam['section_id'] == $section['section_id'] ? 'selected' : ''; ?>>
                                    <?php echo sanitizeOutput($section['course_name'] . ' - Section ' . $section['section_number'] . ' (' . $section['semester'] . ' ' . $section['academic_year'] . ')'); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
            </div>

            <div class="form-section">
                <h3>Schedule & Timing</h3>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="start_date">Start Date & Time <span class="required">*</span></label>
                        <input type="datetime-local" id="start_date" name="start_date" required class="form-control"
                               value="<?php echo date('Y-m-d\TH:i', strtotime($exam['start_date'])); ?>">
                    </div>

                    <div class="form-group">
                        <label for="end_date">End Date & Time <span class="required">*</span></label>
                        <input type="datetime-local" id="end_date" name="end_date" required class="form-control"
                               value="<?php echo date('Y-m-d\TH:i', strtotime($exam['end_date'])); ?>">
                    </div>

                    <div class="form-group">
                        <label for="time_limit">Time Limit (minutes)</label>
                        <input type="number" id="time_limit" name="time_limit" min="1" class="form-control"
                               placeholder="Leave empty for no limit"
                               value="<?php echo $exam['time_limit'] ?: ''; ?>">
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
                                   <?php echo $exam['allow_late_submission'] ? 'checked' : ''; ?>>
                            Allow Late Submission
                        </label>
                    </div>

                    <div class="form-group">
                        <label>
                            <input type="checkbox" name="show_results_immediately" value="1" 
                                   <?php echo $exam['show_results_immediately'] ? 'checked' : ''; ?>>
                            Show Results Immediately
                        </label>
                    </div>

                    <div class="form-group">
                        <label>
                            <input type="checkbox" name="randomize_questions" value="1" 
                                   <?php echo $exam['randomize_questions'] ? 'checked' : ''; ?>>
                            Randomize Questions
                        </label>
                    </div>

                    <div class="form-group">
                        <label>
                            <input type="checkbox" name="randomize_options" value="1" 
                                   <?php echo $exam['randomize_options'] ? 'checked' : ''; ?>>
                            Randomize Options
                        </label>
                    </div>

                    <div class="form-group">
                        <label>
                            <input type="checkbox" name="require_password" value="1" 
                                   id="require_password_checkbox"
                                   onchange="togglePasswordField()"
                                   <?php echo $exam['require_password'] ? 'checked' : ''; ?>>
                            Require Password
                        </label>
                    </div>

                    <div class="form-group">
                        <label>
                            <input type="checkbox" name="is_active" value="1" 
                                   <?php echo $exam['is_active'] ? 'checked' : ''; ?>>
                            Active
                        </label>
                    </div>
                </div>

                <div class="form-group" id="password_field" style="display: <?php echo $exam['require_password'] ? 'block' : 'none'; ?>;">
                    <label for="exam_password">Exam Password <span class="required">*</span></label>
                    <input type="text" id="exam_password" name="exam_password" class="form-control"
                           placeholder="Enter exam password"
                           value="<?php echo sanitizeOutput($exam['exam_password'] ?? ''); ?>">
                </div>

                <div class="form-group">
                    <label for="instructions">Instructions</label>
                    <textarea id="instructions" name="instructions" class="form-control" rows="4"
                              placeholder="Enter exam instructions for students"><?php echo sanitizeOutput($exam['instructions']); ?></textarea>
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
                                    <?php foreach ($bank_data['questions'] as $q): 
                                        $is_selected = in_array($q['question_id'], $current_question_ids);
                                        $current_points = '';
                                        if ($is_selected) {
                                            foreach ($current_questions as $cq) {
                                                if ($cq['question_id'] == $q['question_id']) {
                                                    $current_points = number_format($cq['exam_points'], 2);
                                                    break;
                                                }
                                            }
                                        }
                                        if (empty($current_points)) {
                                            $current_points = number_format($q['points'], 2);
                                        }
                                    ?>
                                        <div style="padding: 1rem; background: <?php echo $is_selected ? '#e0f2fe' : '#f8fafc'; ?>; border-radius: 8px; border: 2px solid <?php echo $is_selected ? '#2563eb' : '#e2e8f0'; ?>; transition: all 0.3s;" 
                                             class="question-item"
                                             onmouseover="this.style.borderColor='#2563eb'; this.style.background='#f1f5f9';"
                                             onmouseout="this.style.borderColor='<?php echo $is_selected ? '#2563eb' : '#e2e8f0'; ?>'; this.style.background='<?php echo $is_selected ? '#e0f2fe' : '#f8fafc'; ?>';">
                                            <div style="display: flex; align-items: start; gap: 1rem;">
                                                <div style="flex-shrink: 0; margin-top: 0.25rem;">
                                                    <input type="checkbox" 
                                                           name="question_ids[]" 
                                                           value="<?php echo $q['question_id']; ?>"
                                                           id="question_<?php echo $q['question_id']; ?>"
                                                           class="question-checkbox"
                                                           onchange="updateQuestionPoints(<?php echo $q['question_id']; ?>)"
                                                           <?php echo $is_selected ? 'checked' : ''; ?>
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
                                                            <?php if ($is_selected): ?>
                                                                <span class="badge badge-success" style="font-size: 0.75rem;">
                                                                    <i class="fas fa-check"></i> Currently Selected
                                                                </span>
                                                            <?php endif; ?>
                                                        </div>
                                                        <div style="color: #1e293b; font-weight: 500;">
                                                            <?php echo nl2br(sanitizeOutput($q['question_text'])); ?>
                                                        </div>
                                                    </label>
                                                    <div style="margin-top: 0.75rem; display: <?php echo $is_selected ? 'block' : 'none'; ?>;" 
                                                         id="points_container_<?php echo $q['question_id']; ?>"
                                                         class="points-container">
                                                        <label style="font-size: 0.875rem; color: #64748b; display: flex; align-items: center; gap: 0.5rem;">
                                                            <i class="fas fa-star"></i>
                                                            Points for this exam:
                                                            <input type="number" 
                                                                   name="question_points[<?php echo $q['question_id']; ?>]"
                                                                   value="<?php echo $current_points; ?>"
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
                    <i class="fas fa-save"></i> Update Examination
                </button>
                <a href="<?php echo BASE_URL; ?>modules/examinations/index.php" class="btn btn-secondary">
                    <i class="fas fa-times"></i> Cancel
                </a>
            </div>
        </form>
    </div>
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
    const options = sectionSelect.querySelectorAll('option');
    
    options.forEach(option => {
        if (option.value === '') {
            option.style.display = 'block';
        } else {
            const dataCourseId = option.getAttribute('data-course-id');
            if (courseId && dataCourseId && dataCourseId != courseId) {
                option.style.display = 'none';
            } else {
                option.style.display = 'block';
            }
        }
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
    updateSections();
    updateSelectedCount();
    
    // Initialize points containers for pre-selected questions
    const checkboxes = document.querySelectorAll('.question-checkbox');
    checkboxes.forEach(checkbox => {
        if (checkbox.checked) {
            updateQuestionPoints(checkbox.value);
        }
    });
});
</script>

<?php include __DIR__ . '/../../includes/footer.php'; ?>

