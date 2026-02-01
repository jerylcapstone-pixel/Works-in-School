<?php
/**
 * Detailed Grade Entry - Bulk Entry for All Students
 * Allows faculty to enter grades for all students at once
 */
require_once __DIR__ . '/../../config/config.php';
requireAuth([ROLE_FACULTY]);
$page_title = 'Enter Grades';
$pdo = getDBConnection();

$section_id = (int)($_GET['section_id'] ?? 0);

// Get faculty_id
$stmt = $pdo->prepare("SELECT faculty_id FROM faculty WHERE user_id = ?");
$stmt->execute([$_SESSION['user_id']]);
$faculty = $stmt->fetch();
$faculty_id = $faculty['faculty_id'] ?? null;

// Verify access
if ($section_id > 0 && $faculty_id) {
    $stmt = $pdo->prepare("SELECT faculty_id FROM class_sections WHERE section_id = ?");
    $stmt->execute([$section_id]);
    $section = $stmt->fetch();
    if (!$section || $section['faculty_id'] != $faculty_id) {
        header('Location: ' . BASE_URL . 'modules/grades/index.php?error=Access denied');
        exit;
    }
}

// Get section and course info
$section_info = null;
$enrollments = [];
if ($section_id > 0) {
    $stmt = $pdo->prepare("SELECT cs.*, c.course_name, c.course_code, c.credits
                          FROM class_sections cs
                          LEFT JOIN courses c ON cs.course_id = c.course_id
                          WHERE cs.section_id = ?");
    $stmt->execute([$section_id]);
    $section_info = $stmt->fetch();
    
    // Get all enrollments for this section
    $stmt = $pdo->prepare("SELECT e.*, s.student_number, s.first_name, s.last_name
                          FROM enrollments e
                          INNER JOIN students s ON e.student_id = s.student_id
                          WHERE e.section_id = ? AND e.status = 'Enrolled'
                          ORDER BY s.last_name, s.first_name");
    $stmt->execute([$section_id]);
    $enrollments = $stmt->fetchAll();
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $section_id_post = (int)($_POST['section_id'] ?? 0);
    $assignment_type = sanitizeInput($_POST['assignment_type'] ?? '');
    $assignment_name = sanitizeInput($_POST['assignment_name'] ?? '');
    $points_possible = (float)($_POST['points_possible'] ?? 0);
    $student_scores = $_POST['student_scores'] ?? [];
    
    if (empty($assignment_type) || empty($assignment_name) || $points_possible <= 0) {
        $error = 'Please fill in all required fields: Category, Assignment Name, and Total Score.';
    } else {
        try {
            $pdo->beginTransaction();
            
            foreach ($student_scores as $enrollment_id => $score_data) {
                $enrollment_id = (int)$enrollment_id;
                $points_earned = (float)($score_data['points_earned'] ?? 0);
                
                // Skip if no score entered (allow empty for optional entries)
                if ($points_earned <= 0 && empty($score_data['points_earned'])) {
                    continue;
                }
                
                // Calculate percentage
                $percentage = $points_possible > 0 ? ($points_earned / $points_possible) * 100 : 0;
                
                // Calculate letter grade
                $letter_grade = '';
                if ($percentage >= 97) $letter_grade = 'A+';
                elseif ($percentage >= 93) $letter_grade = 'A';
                elseif ($percentage >= 90) $letter_grade = 'A-';
                elseif ($percentage >= 87) $letter_grade = 'B+';
                elseif ($percentage >= 83) $letter_grade = 'B';
                elseif ($percentage >= 80) $letter_grade = 'B-';
                elseif ($percentage >= 77) $letter_grade = 'C+';
                elseif ($percentage >= 73) $letter_grade = 'C';
                elseif ($percentage >= 70) $letter_grade = 'C-';
                elseif ($percentage >= 67) $letter_grade = 'D+';
                elseif ($percentage >= 65) $letter_grade = 'D';
                else $letter_grade = 'F';
                
                // Insert new grade
                $stmt = $pdo->prepare("INSERT INTO grades 
                                      (enrollment_id, assignment_type, assignment_name, 
                                       points_earned, points_possible, percentage, letter_grade, 
                                       graded_by)
                                      VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
                $stmt->execute([
                    $enrollment_id, $assignment_type, $assignment_name,
                    $points_earned, $points_possible, $percentage, $letter_grade,
                    $_SESSION['user_id']
                ]);
            }
            
            // Update final grades for all students with new grades
            foreach ($student_scores as $enrollment_id => $score_data) {
                $enrollment_id = (int)$enrollment_id;
                $points_earned = (float)($score_data['points_earned'] ?? 0);
                
                if ($points_earned <= 0 && empty($score_data['points_earned'])) {
                    continue;
                }
                
                // Calculate and update final grade in enrollments table
                $stmt = $pdo->prepare("SELECT AVG(percentage) as avg_percentage, 
                                  SUM(points_earned) as total_earned,
                                  SUM(points_possible) as total_possible
                                  FROM grades 
                                  WHERE enrollment_id = ?");
                $stmt->execute([$enrollment_id]);
                $grade_summary = $stmt->fetch();
                
                if ($grade_summary && $grade_summary['avg_percentage'] !== null) {
                    $final_percentage = $grade_summary['avg_percentage'];
                    $final_letter = '';
                    if ($final_percentage >= 97) $final_letter = 'A+';
                    elseif ($final_percentage >= 93) $final_letter = 'A';
                    elseif ($final_percentage >= 90) $final_letter = 'A-';
                    elseif ($final_percentage >= 87) $final_letter = 'B+';
                    elseif ($final_percentage >= 83) $final_letter = 'B';
                    elseif ($final_percentage >= 80) $final_letter = 'B-';
                    elseif ($final_percentage >= 77) $final_letter = 'C+';
                    elseif ($final_percentage >= 73) $final_letter = 'C';
                    elseif ($final_percentage >= 70) $final_letter = 'C-';
                    elseif ($final_percentage >= 67) $final_letter = 'D+';
                    elseif ($final_percentage >= 65) $final_letter = 'D';
                    else $final_letter = 'F';
                    
                    // Update enrollment with calculated final grade
                    $stmt = $pdo->prepare("UPDATE enrollments SET 
                                          final_grade = ?, points = ? 
                                          WHERE enrollment_id = ?");
                    $stmt->execute([
                        $final_letter,
                        $grade_summary['total_earned'] ?? 0,
                        $enrollment_id
                    ]);
                }
            }
            
            $pdo->commit();
            header('Location: ' . BASE_URL . 'modules/grades/detailed.php?section_id=' . $section_id_post . '&success=Grades saved successfully');
            exit;
            
        } catch (Exception $e) {
            $pdo->rollBack();
            $error = 'Error saving grades: ' . $e->getMessage();
        }
    }
}

// Get existing grades for display (latest entries)
$existing_grades_by_student = [];
if ($section_id > 0 && !empty($enrollments)) {
    foreach ($enrollments as $enroll) {
        $stmt = $pdo->prepare("SELECT * FROM grades WHERE enrollment_id = ? ORDER BY graded_at DESC LIMIT 10");
        $stmt->execute([$enroll['enrollment_id']]);
        $existing_grades_by_student[$enroll['enrollment_id']] = $stmt->fetchAll();
    }
}

include __DIR__ . '/../../includes/header.php';
?>

<div class="page-container">
    <div class="page-header">
        <h1><i class="fas fa-edit"></i> Enter Grades</h1>
        <a href="<?php echo BASE_URL; ?>modules/grades/index.php?section_id=<?php echo $section_id; ?>" class="btn btn-secondary">
            <i class="fas fa-arrow-left"></i> Back
        </a>
    </div>

    <?php if (isset($error)): ?>
        <div class="alert alert-error"><?php echo sanitizeOutput($error); ?></div>
    <?php endif; ?>
    
    <?php if (isset($_GET['success'])): ?>
        <div class="alert alert-success"><?php echo sanitizeOutput($_GET['success']); ?></div>
    <?php endif; ?>

    <?php if ($section_info && !empty($enrollments)): ?>
        <div class="card" style="margin-bottom: 1.5rem;">
            <h2 style="margin-bottom: 0.5rem;"><?php echo sanitizeOutput($section_info['course_code'] . ' - ' . $section_info['course_name']); ?></h2>
            <p style="margin: 0; color: #666;">
                Section <?php echo sanitizeOutput($section_info['section_number']); ?> | 
                <?php echo sanitizeOutput($section_info['semester']); ?> Semester, <?php echo sanitizeOutput($section_info['academic_year']); ?>
            </p>
        </div>

        <div class="card" style="margin-bottom: 1.5rem;">
            <h2 style="margin-bottom: 1rem;">Enter New Grade</h2>
            
            <form method="POST" action="" id="gradeForm">
                <input type="hidden" name="section_id" value="<?php echo $section_id; ?>">
                
                <div class="form-row">
                    <div class="form-group" style="flex: 1;">
                        <label>Category <span class="text-danger">*</span></label>
                        <select name="assignment_type" class="form-control" required>
                            <option value="">-- Select Category --</option>
                            <option value="Quiz">Quiz</option>
                            <option value="Assignment">Assignment</option>
                            <option value="Project">Project</option>
                            <option value="Midterm">Midterm Exam</option>
                            <option value="Final">Final Exam</option>
                            <option value="Participation">Participation</option>
                            <option value="Other">Other</option>
                        </select>
                    </div>
                    <div class="form-group" style="flex: 2;">
                        <label>Assignment Name <span class="text-danger">*</span></label>
                        <input type="text" name="assignment_name" class="form-control" 
                               placeholder="e.g., Quiz 1, Midterm Exam, Assignment 3" required>
                    </div>
                    <div class="form-group" style="flex: 1;">
                        <label>Total Score <span class="text-danger">*</span></label>
                        <input type="number" step="0.01" name="points_possible" class="form-control" 
                               placeholder="e.g., 100" min="0.01" required>
                    </div>
                </div>
                
                <div style="margin-top: 1.5rem; padding-top: 1.5rem; border-top: 1px solid var(--border-color);">
                    <h3 style="margin-bottom: 1rem;">Student Scores</h3>
                    
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th style="width: 15%;">Student Number</th>
                                <th style="width: 25%;">Name</th>
                                <th style="width: 20%;">Score</th>
                                <th style="width: 15%;">Percentage</th>
                                <th style="width: 15%;">Grade</th>
                                <th style="width: 10%;">Current Final</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($enrollments as $enroll): ?>
                            <tr>
                                <td><strong><?php echo sanitizeOutput($enroll['student_number']); ?></strong></td>
                                <td><?php echo sanitizeOutput($enroll['first_name'] . ' ' . $enroll['last_name']); ?></td>
                                <td>
                                    <input type="number" 
                                           step="0.01" 
                                           name="student_scores[<?php echo $enroll['enrollment_id']; ?>][points_earned]" 
                                           class="form-control score-input" 
                                           data-enrollment-id="<?php echo $enroll['enrollment_id']; ?>"
                                           placeholder="Enter score" 
                                           min="0">
                                </td>
                                <td>
                                    <span class="percentage-display" data-enrollment-id="<?php echo $enroll['enrollment_id']; ?>">-</span>
                                </td>
                                <td>
                                    <span class="grade-display" data-enrollment-id="<?php echo $enroll['enrollment_id']; ?>">-</span>
                                </td>
                                <td>
                                    <span class="badge badge-info">
                                        <?php echo sanitizeOutput($enroll['final_grade'] ?? 'N/A'); ?>
                                    </span>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                
                <div style="margin-top: 1.5rem; padding-top: 1.5rem; border-top: 1px solid var(--border-color);">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save"></i> Save Grades
                    </button>
                    <button type="reset" class="btn btn-secondary">
                        <i class="fas fa-undo"></i> Clear Form
                    </button>
                </div>
            </form>
        </div>

        <!-- Display existing grades -->
        <div class="card">
            <h2>Recent Grade Entries</h2>
            <?php 
            $has_grades = false;
            foreach ($existing_grades_by_student as $enrollment_id => $grades) {
                if (!empty($grades)) {
                    $has_grades = true;
                    break;
                }
            }
            ?>
            
            <?php if ($has_grades): ?>
                <div style="overflow-x: auto;">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Student</th>
                                <th>Category</th>
                                <th>Assignment</th>
                                <th>Score</th>
                                <th>Total</th>
                                <th>Percentage</th>
                                <th>Grade</th>
                                <th>Graded Date</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            $shown_assignments = [];
                            foreach ($enrollments as $enroll): 
                                $grades = $existing_grades_by_student[$enroll['enrollment_id']] ?? [];
                                foreach ($grades as $grade): 
                                    $assignment_key = $grade['assignment_type'] . '|' . $grade['assignment_name'];
                                    if (!in_array($assignment_key, $shown_assignments)) {
                                        $shown_assignments[] = $assignment_key;
                                    }
                            ?>
                            <tr>
                                <td><strong><?php echo sanitizeOutput($enroll['student_number']); ?></strong><br>
                                    <small><?php echo sanitizeOutput($enroll['first_name'] . ' ' . $enroll['last_name']); ?></small>
                                </td>
                                <td><?php echo sanitizeOutput($grade['assignment_type']); ?></td>
                                <td><?php echo sanitizeOutput($grade['assignment_name']); ?></td>
                                <td><?php echo number_format($grade['points_earned'], 2); ?></td>
                                <td><?php echo number_format($grade['points_possible'], 2); ?></td>
                                <td><?php echo number_format($grade['percentage'], 2); ?>%</td>
                                <td>
                                    <span class="badge badge-<?php echo in_array($grade['letter_grade'], ['A+', 'A', 'A-', 'B+', 'B']) ? 'success' : (in_array($grade['letter_grade'], ['B-', 'C+', 'C']) ? 'warning' : 'danger'); ?>">
                                        <?php echo $grade['letter_grade']; ?>
                                    </span>
                                </td>
                                <td><?php echo $grade['graded_at'] ? date('M d, Y', strtotime($grade['graded_at'])) : 'N/A'; ?></td>
                            </tr>
                            <?php 
                                endforeach;
                            endforeach; 
                            ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div class="alert alert-info">No grades entered yet. Use the form above to enter grades for students.</div>
            <?php endif; ?>
        </div>
    <?php elseif ($section_id > 0): ?>
        <div class="alert alert-info">No enrolled students found for this section.</div>
    <?php else: ?>
        <div class="alert alert-info">Please select a section from the grades index page.</div>
    <?php endif; ?>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const form = document.getElementById('gradeForm');
    const totalScoreInput = form.querySelector('input[name="points_possible"]');
    const scoreInputs = form.querySelectorAll('.score-input');
    
    function calculateGrade(pointsEarned, pointsPossible) {
        if (!pointsPossible || pointsPossible <= 0) return { percentage: 0, grade: '-' };
        
        const percentage = (pointsEarned / pointsPossible) * 100;
        let grade = '';
        
        if (percentage >= 97) grade = 'A+';
        else if (percentage >= 93) grade = 'A';
        else if (percentage >= 90) grade = 'A-';
        else if (percentage >= 87) grade = 'B+';
        else if (percentage >= 83) grade = 'B';
        else if (percentage >= 80) grade = 'B-';
        else if (percentage >= 77) grade = 'C+';
        else if (percentage >= 73) grade = 'C';
        else if (percentage >= 70) grade = 'C-';
        else if (percentage >= 67) grade = 'D+';
        else if (percentage >= 65) grade = 'D';
        else grade = 'F';
        
        return { percentage: percentage.toFixed(2), grade: grade };
    }
    
    function updateDisplay(enrollmentId) {
        const scoreInput = form.querySelector(`input[data-enrollment-id="${enrollmentId}"]`);
        const percentageDisplay = form.querySelector(`.percentage-display[data-enrollment-id="${enrollmentId}"]`);
        const gradeDisplay = form.querySelector(`.grade-display[data-enrollment-id="${enrollmentId}"]`);
        
        const pointsEarned = parseFloat(scoreInput.value) || 0;
        const pointsPossible = parseFloat(totalScoreInput.value) || 0;
        
        if (pointsEarned > 0 && pointsPossible > 0) {
            const result = calculateGrade(pointsEarned, pointsPossible);
            percentageDisplay.textContent = result.percentage + '%';
            gradeDisplay.textContent = result.grade;
            gradeDisplay.className = 'grade-display badge ' + 
                (in_array(result.grade, ['A+', 'A', 'A-', 'B+', 'B']) ? 'badge-success' : 
                 (in_array(result.grade, ['B-', 'C+', 'C']) ? 'badge-warning' : 'badge-danger'));
        } else {
            percentageDisplay.textContent = '-';
            gradeDisplay.textContent = '-';
            gradeDisplay.className = 'grade-display';
        }
    }
    
    function in_array(needle, haystack) {
        return haystack.indexOf(needle) !== -1;
    }
    
    // Update all displays when total score changes
    totalScoreInput.addEventListener('input', function() {
        scoreInputs.forEach(input => {
            updateDisplay(input.dataset.enrollmentId);
        });
    });
    
    // Update individual display when score changes
    scoreInputs.forEach(input => {
        input.addEventListener('input', function() {
            updateDisplay(this.dataset.enrollmentId);
        });
    });
});
</script>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
