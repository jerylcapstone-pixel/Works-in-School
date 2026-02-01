<?php
/**
 * Course Registration - Enroll Students in Section
 */
require_once __DIR__ . '/../../config/config.php';
requireAuth(ROLE_ADMIN);
$page_title = 'Enroll Students';
$pdo = getDBConnection();

$section_id = (int)($_GET['section_id'] ?? 0);
if (!$section_id) {
    header('Location: ' . BASE_URL . 'modules/registration/index.php?error=Invalid section ID');
    exit;
}

// Get section details with course information
$stmt = $pdo->prepare("SELECT cs.*, c.course_name, c.credits, c.department_id, c.course_id
                      FROM class_sections cs
                      LEFT JOIN courses c ON cs.course_id = c.course_id
                      WHERE cs.section_id = ?");
$stmt->execute([$section_id]);
$section = $stmt->fetch();

if (!$section) {
    header('Location: ' . BASE_URL . 'modules/registration/index.php?error=Section not found');
    exit;
}

$error = '';
$success = '';

// Check if major_id column exists in courses table
$major_column_exists = false;
try {
    $check_stmt = $pdo->query("SHOW COLUMNS FROM courses LIKE 'major_id'");
    $major_column_exists = $check_stmt->rowCount() > 0;
} catch (PDOException $e) {
    $major_column_exists = false;
}

// Get course associations
$course_department_id = $section['department_id'] ?? null;
$course_major_id = null;
if ($major_column_exists && $section['course_id']) {
    $stmt = $pdo->prepare("SELECT major_id FROM courses WHERE course_id = ?");
    $stmt->execute([$section['course_id']]);
    $result = $stmt->fetch();
    $course_major_id = $result['major_id'] ?? null;
}

// Get programs associated with this course
$course_program_ids = [];
if ($section['course_id']) {
    $stmt = $pdo->prepare("SELECT program_id FROM program_courses WHERE course_id = ?");
    $stmt->execute([$section['course_id']]);
    $course_program_ids = array_column($stmt->fetchAll(), 'program_id');
}

// Build student query with filters based on course associations
// If course has no associations (minor/general course), show all students
// Otherwise, filter by department, program, and major (all must match)
$student_query = "SELECT DISTINCT s.student_id, s.student_number, s.first_name, s.last_name, p.program_name, p.department_id as program_department_id
                  FROM students s
                  LEFT JOIN programs p ON s.program_id = p.program_id
                  WHERE s.status = 'Active'";

$conditions = [];
$params = [];

// If course has associations, apply filters (all conditions must match)
if ($course_department_id || !empty($course_program_ids) || $course_major_id) {
    // Filter by department if course has department
    if ($course_department_id) {
        $conditions[] = "p.department_id = ?";
        $params[] = $course_department_id;
    }
    
    // Filter by program if course has program associations
    if (!empty($course_program_ids)) {
        $placeholders = implode(',', array_fill(0, count($course_program_ids), '?'));
        $conditions[] = "s.program_id IN ($placeholders)";
        $params = array_merge($params, $course_program_ids);
    }
    
    // Filter by major if course has major
    if ($course_major_id) {
        $conditions[] = "EXISTS (SELECT 1 FROM majors m WHERE m.major_id = ? AND m.program_id = s.program_id AND m.status = 'Active')";
        $params[] = $course_major_id;
    }
    
    // All conditions must match (AND logic)
    if (!empty($conditions)) {
        $student_query .= " AND " . implode(" AND ", $conditions);
    }
}

$student_query .= " ORDER BY s.last_name, s.first_name";

$stmt = $pdo->prepare($student_query);
$stmt->execute($params);
$students = $stmt->fetchAll();

// Get already enrolled students
$enrolled_students = [];
$stmt = $pdo->prepare("SELECT student_id FROM enrollments WHERE section_id = ? AND status = 'Enrolled'");
$stmt->execute([$section_id]);
$enrolled_students = array_column($stmt->fetchAll(), 'student_id');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $student_ids = $_POST['student_ids'] ?? [];
    $enrollment_type = sanitizeInput($_POST['enrollment_type'] ?? 'Regular');
    
    if (empty($student_ids)) {
        $error = 'Please select at least one student.';
    } else {
        try {
            $pdo->beginTransaction();
            $enrolled_count = 0;
            $skipped_count = 0;
            
            foreach ($student_ids as $student_id) {
                $student_id = (int)$student_id;
                
                // Check if already enrolled
                $stmt = $pdo->prepare("SELECT COUNT(*) FROM enrollments WHERE student_id = ? AND section_id = ?");
                $stmt->execute([$student_id, $section_id]);
                if ($stmt->fetchColumn() > 0) {
                    $skipped_count++;
                    continue;
                }
                
                // Check if section is full
                if ($section['current_enrollment'] >= $section['max_enrollment']) {
                    $error = 'Section is full. Cannot enroll more students.';
                    $pdo->rollBack();
                    break;
                }
                
                // Enroll student
                $stmt = $pdo->prepare("INSERT INTO enrollments (student_id, section_id, enrollment_date, enrollment_type, status, registered_by)
                                      VALUES (?, ?, CURDATE(), ?, 'Enrolled', ?)");
                $stmt->execute([$student_id, $section_id, $enrollment_type, $_SESSION['user_id']]);
                
                // Update section enrollment count
                $stmt = $pdo->prepare("UPDATE class_sections SET current_enrollment = current_enrollment + 1 WHERE section_id = ?");
                $stmt->execute([$section_id]);
                
                // Update section status if full
                $stmt = $pdo->prepare("UPDATE class_sections SET status = 'Full' 
                                      WHERE section_id = ? AND current_enrollment >= max_enrollment");
                $stmt->execute([$section_id]);
                
                // Automatically create invoice for this student if not exists
                require_once __DIR__ . '/../billing/invoice_helper.php';
                createInvoiceForStudent($pdo, $student_id, $section['semester'], $section['academic_year']);
                
                $enrolled_count++;
            }
            
            if ($enrolled_count > 0) {
                $pdo->commit();
                $success = "Successfully enrolled $enrolled_count student(s).";
                if ($skipped_count > 0) {
                    $success .= " $skipped_count student(s) were already enrolled.";
                }
            } else {
                $pdo->rollBack();
                $error = 'No students were enrolled. All selected students may already be enrolled.';
            }
        } catch (PDOException $e) {
            $pdo->rollBack();
            $error = 'Error enrolling students: ' . $e->getMessage();
        }
    }
}

include __DIR__ . '/../../includes/header.php';
?>

<div class="page-container">
    <div class="page-header">
        <h1><i class="fas fa-user-plus"></i> Enroll Students</h1>
        <div class="header-actions">
            <a href="<?php echo BASE_URL; ?>modules/registration/index.php" class="btn btn-secondary">
                <i class="fas fa-arrow-left"></i> Back to Sections
            </a>
        </div>
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

    <div class="detail-cards">
        <!-- Section Information Card -->
        <div class="detail-card" style="background: linear-gradient(135deg, #2563eb 0%, #1e40af 100%); color: white;">
            <h3 style="color: white; border-bottom-color: rgba(255, 255, 255, 0.3);">
                <i class="fas fa-book-open"></i> Section Information
            </h3>
            <div class="detail-grid">
                <div class="detail-item">
                    <label style="color: rgba(255, 255, 255, 0.9);">Course</label>
                    <span style="color: white; font-size: 1.1rem; font-weight: 600;">
                        <?php echo sanitizeOutput($section['course_name']); ?>
                    </span>
                </div>
                <div class="detail-item">
                    <label style="color: rgba(255, 255, 255, 0.9);">Section</label>
                    <span style="color: white; font-size: 1rem;"><?php echo sanitizeOutput($section['section_number']); ?></span>
                </div>
                <div class="detail-item">
                    <label style="color: rgba(255, 255, 255, 0.9);">Semester</label>
                    <span style="color: white; font-size: 1rem;"><?php echo sanitizeOutput($section['semester']); ?></span>
                </div>
                <div class="detail-item">
                    <label style="color: rgba(255, 255, 255, 0.9);">Academic Year</label>
                    <span style="color: white; font-size: 1rem;"><?php echo sanitizeOutput($section['academic_year']); ?></span>
                </div>
                <div class="detail-item">
                    <label style="color: rgba(255, 255, 255, 0.9);">Schedule</label>
                    <span style="color: white; font-size: 1rem;">
                        <?php echo sanitizeOutput(formatScheduleDisplay($section['schedule_day'], $section['start_time'], $section['end_time'])); ?>
                    </span>
                </div>
                <div class="detail-item">
                    <label style="color: rgba(255, 255, 255, 0.9);">Enrollment</label>
                    <span style="color: white; font-size: 1.1rem; font-weight: 600;">
                        <?php echo $section['current_enrollment'] . ' / ' . $section['max_enrollment']; ?>
                    </span>
                </div>
                <div class="detail-item">
                    <label style="color: rgba(255, 255, 255, 0.9);">Status</label>
                    <span style="color: white; font-size: 1rem;">
                        <span class="badge badge-<?php echo $section['status'] === 'Open' ? 'success' : 'warning'; ?>">
                            <?php echo sanitizeOutput($section['status']); ?>
                        </span>
                    </span>
                </div>
            </div>
        </div>

        <!-- Enrollment Form Card -->
        <div class="form-card">
            <form method="POST" action="">
                <div class="form-section">
                    <h3><i class="fas fa-cog"></i> Enrollment Settings</h3>
                    
                    <div class="form-group">
                        <label for="enrollment_type">
                            <i class="fas fa-tag" style="color: #2563eb;"></i> Enrollment Type
                        </label>
                        <select id="enrollment_type" name="enrollment_type" class="form-control" style="font-size: 1rem; padding: 0.75rem;">
                            <option value="Regular" selected>Regular</option>
                            <option value="Waitlist">Waitlist</option>
                            <option value="Audit">Audit</option>
                        </select>
                        <small class="text-muted" style="display: block; margin-top: 0.5rem; color: #64748b;">
                            <i class="fas fa-info-circle"></i> Select the type of enrollment for the selected students
                        </small>
                    </div>
                </div>

                <?php
                // Display filtering information
                $filter_info = [];
                if ($course_department_id) {
                    $stmt = $pdo->prepare("SELECT department_name FROM departments WHERE department_id = ?");
                    $stmt->execute([$course_department_id]);
                    $dept = $stmt->fetch();
                    $filter_info[] = "Department: " . ($dept['department_name'] ?? 'N/A');
                }
                if (!empty($course_program_ids)) {
                    $placeholders = implode(',', array_fill(0, count($course_program_ids), '?'));
                    $stmt = $pdo->prepare("SELECT program_name FROM programs WHERE program_id IN ($placeholders)");
                    $stmt->execute($course_program_ids);
                    $programs = $stmt->fetchAll();
                    $program_names = array_column($programs, 'program_name');
                    $filter_info[] = "Program(s): " . implode(', ', $program_names);
                }
                if ($course_major_id) {
                    $stmt = $pdo->prepare("SELECT major_name FROM majors WHERE major_id = ?");
                    $stmt->execute([$course_major_id]);
                    $major = $stmt->fetch();
                    $filter_info[] = "Major: " . ($major['major_name'] ?? 'N/A');
                }
                
                if (!empty($filter_info)):
                ?>
                    <div class="form-section" style="background: linear-gradient(135deg, #dbeafe 0%, #bfdbfe 100%); border: none; border-left: 4px solid #2563eb; padding: 1.5rem; margin-bottom: 1.5rem;">
                        <h4 style="margin-top: 0; margin-bottom: 1rem; color: #1e40af; display: flex; align-items: center; gap: 0.5rem;">
                            <i class="fas fa-filter" style="color: #2563eb;"></i> Enrollment Restrictions
                        </h4>
                        <p style="margin: 0; color: #1e40af; line-height: 1.7;">
                            This course is restricted. Only students matching the following criteria can enroll:
                        </p>
                        <ul style="margin: 0.75rem 0 0 0; padding-left: 1.5rem; color: #1e40af; line-height: 1.8;">
                            <?php foreach ($filter_info as $info): ?>
                                <li><?php echo sanitizeOutput($info); ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php else: ?>
                    <div class="form-section" style="background: linear-gradient(135deg, #f0fdf4 0%, #dcfce7 100%); border: none; border-left: 4px solid #10b981; padding: 1.5rem; margin-bottom: 1.5rem;">
                        <h4 style="margin-top: 0; margin-bottom: 0.5rem; color: #065f46; display: flex; align-items: center; gap: 0.5rem;">
                            <i class="fas fa-check-circle" style="color: #10b981;"></i> General/Minor Course
                        </h4>
                        <p style="margin: 0; color: #065f46; line-height: 1.7;">
                            This course has no specific department, program, or major restrictions. All active students can enroll.
                        </p>
                    </div>
                <?php endif; ?>

                <div class="form-section">
                    <h3><i class="fas fa-users"></i> Select Students to Enroll</h3>
                    
                    <div style="background: #f8fafc; border: 2px solid #e2e8f0; border-radius: 12px; padding: 1rem; margin-bottom: 1rem;">
                        <label style="display: flex; align-items: center; gap: 0.5rem; font-weight: 600; color: #1e293b; margin-bottom: 0.5rem;">
                            <input type="checkbox" id="select-all" style="width: 18px; height: 18px; cursor: pointer;">
                            <span>Select All Available Students</span>
                        </label>
                        <small class="text-muted" style="display: block; margin-top: 0.25rem; color: #64748b; margin-left: 1.75rem;">
                            <i class="fas fa-info-circle"></i> Already enrolled students are automatically excluded
                        </small>
                    </div>

                    <div style="max-height: 500px; overflow-y: auto; border: 2px solid #e2e8f0; border-radius: 12px; background: white;">
                        <table class="data-table" style="margin: 0;">
                            <thead style="position: sticky; top: 0; background: #f8fafc; z-index: 10;">
                                <tr>
                                    <th style="width: 50px; padding: 1rem 0.75rem; text-align: center;">
                                        <i class="fas fa-check-square" style="color: #2563eb;"></i>
                                    </th>
                                    <th style="padding: 1rem 0.75rem;">Student Number</th>
                                    <th style="padding: 1rem 0.75rem;">Name</th>
                                    <th style="padding: 1rem 0.75rem;">Program</th>
                                    <th style="padding: 1rem 0.75rem; text-align: center;">Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($students)): ?>
                                    <tr>
                                        <td colspan="5" class="text-center" style="padding: 2rem; color: #64748b;">
                                            <i class="fas fa-user-slash" style="font-size: 2rem; margin-bottom: 0.5rem; display: block; color: #94a3b8;"></i>
                                            No active students found matching the enrollment criteria.
                                        </td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($students as $student): ?>
                                    <tr style="transition: background-color 0.2s;" 
                                        onmouseover="this.style.backgroundColor='#f8fafc'" 
                                        onmouseout="this.style.backgroundColor=''">
                                        <td style="text-align: center; padding: 1rem 0.75rem;">
                                            <input type="checkbox" name="student_ids[]" value="<?php echo $student['student_id']; ?>" 
                                                   class="student-checkbox" 
                                                   style="width: 18px; height: 18px; cursor: pointer;"
                                                   <?php echo in_array($student['student_id'], $enrolled_students) ? 'disabled' : ''; ?>>
                                        </td>
                                        <td style="padding: 1rem 0.75rem;">
                                            <strong style="color: #1e293b;"><?php echo sanitizeOutput($student['student_number']); ?></strong>
                                        </td>
                                        <td style="padding: 1rem 0.75rem; color: #334155;">
                                            <?php echo sanitizeOutput($student['first_name'] . ' ' . $student['last_name']); ?>
                                        </td>
                                        <td style="padding: 1rem 0.75rem; color: #64748b;">
                                            <?php echo sanitizeOutput($student['program_name'] ?? 'N/A'); ?>
                                        </td>
                                        <td style="padding: 1rem 0.75rem; text-align: center;">
                                            <?php if (in_array($student['student_id'], $enrolled_students)): ?>
                                                <span class="badge badge-success">
                                                    <i class="fas fa-check"></i> Enrolled
                                                </span>
                                            <?php else: ?>
                                                <span class="badge badge-info">
                                                    <i class="fas fa-user-plus"></i> Available
                                                </span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <div class="form-actions" style="margin-top: 2rem; padding-top: 1.5rem; border-top: 2px solid #e2e8f0; display: flex; gap: 1rem; justify-content: flex-end;">
                    <button type="submit" class="btn btn-primary" style="min-width: 200px;">
                        <i class="fas fa-user-plus"></i> Enroll Selected Students
                    </button>
                    <a href="<?php echo BASE_URL; ?>modules/registration/index.php" class="btn btn-secondary" style="min-width: 120px;">
                        <i class="fas fa-times"></i> Cancel
                    </a>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const selectAllCheckbox = document.getElementById('select-all');
    const studentCheckboxes = document.querySelectorAll('.student-checkbox:not(:disabled)');
    
    if (selectAllCheckbox) {
        selectAllCheckbox.addEventListener('change', function() {
            studentCheckboxes.forEach(cb => {
                cb.checked = this.checked;
                // Add visual feedback
                const row = cb.closest('tr');
                if (this.checked) {
                    row.style.backgroundColor = '#eff6ff';
                } else {
                    row.style.backgroundColor = '';
                }
            });
        });
    }
    
    // Update select-all checkbox when individual checkboxes change
    studentCheckboxes.forEach(cb => {
        cb.addEventListener('change', function() {
            const allChecked = Array.from(studentCheckboxes).every(cb => cb.checked);
            const someChecked = Array.from(studentCheckboxes).some(cb => cb.checked);
            
            if (selectAllCheckbox) {
                selectAllCheckbox.checked = allChecked;
                selectAllCheckbox.indeterminate = someChecked && !allChecked;
            }
            
            // Visual feedback
            const row = this.closest('tr');
            if (this.checked) {
                row.style.backgroundColor = '#eff6ff';
            } else {
                row.style.backgroundColor = '';
            }
        });
    });
});
</script>

<?php include __DIR__ . '/../../includes/footer.php'; ?>

