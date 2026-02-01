<?php
/**
 * Student Grades View
 * Allows students to view their own grades
 */
require_once __DIR__ . '/../../config/config.php';
requireAuth(ROLE_STUDENT);
$page_title = 'My Grades';

$pdo = getDBConnection();
$student_id = null;

// Get student_id from user_id
$stmt = $pdo->prepare("SELECT student_id FROM students WHERE user_id = ?");
$stmt->execute([$_SESSION['user_id']]);
$student = $stmt->fetch();
if (!$student) {
    header('Location: ' . BASE_URL . 'dashboard/index.php?error=Student record not found');
    exit;
}
$student_id = $student['student_id'];

// Get all enrollments with grades
$stmt = $pdo->prepare("SELECT e.*, cs.section_number, cs.semester, cs.academic_year,
                      c.course_code, c.course_name, c.credits,
                      f.first_name as faculty_first, f.last_name as faculty_last
                      FROM enrollments e
                      LEFT JOIN class_sections cs ON e.section_id = cs.section_id
                      LEFT JOIN courses c ON cs.course_id = c.course_id
                      LEFT JOIN faculty f ON cs.faculty_id = f.faculty_id
                      WHERE e.student_id = ?
                      ORDER BY cs.academic_year DESC, cs.semester, c.course_name");
$stmt->execute([$student_id]);
$enrollments = $stmt->fetchAll();

// Get detailed grades for each enrollment
$enrollment_grades = [];
foreach ($enrollments as $enroll) {
    $stmt = $pdo->prepare("SELECT * FROM grades WHERE enrollment_id = ? ORDER BY due_date DESC, graded_at DESC");
    $stmt->execute([$enroll['enrollment_id']]);
    $enrollment_grades[$enroll['enrollment_id']] = $stmt->fetchAll();
}

// Calculate GWA (General Weighted Average) - same as GPA
$total_points = 0;
$total_credits = 0;
$grade_points = [
    'A+' => 4.0, 'A' => 4.0, 'A-' => 3.7,
    'B+' => 3.5, 'B' => 3.0, 'B-' => 2.7,
    'C+' => 2.5, 'C' => 2.0, 'C-' => 1.7,
    'D+' => 1.3, 'D' => 1.0, 'F' => 0.0
];

foreach ($enrollments as $enroll) {
    if (!empty($enroll['final_grade']) && isset($grade_points[$enroll['final_grade']])) {
        $credits = (float)($enroll['credits'] ?? 0);
        $total_points += $grade_points[$enroll['final_grade']] * $credits;
        $total_credits += $credits;
    }
}
$gpa = $total_credits > 0 ? $total_points / $total_credits : 0;

include __DIR__ . '/../../includes/header.php';
?>

<div class="page-container">
    <div class="page-header">
        <h1><i class="fas fa-clipboard-list"></i> My Grades</h1>
    </div>

    <div class="card" style="margin-bottom: 1.5rem;">
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon green">
                    <i class="fas fa-chart-line"></i>
                </div>
                <div class="stat-info">
                    <h3><?php echo number_format($gpa, 2); ?></h3>
                    <p>GWA (General Weighted Average)</p>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon blue">
                    <i class="fas fa-book"></i>
                </div>
                <div class="stat-info">
                    <h3><?php echo count($enrollments); ?></h3>
                    <p>Total Courses</p>
                </div>
            </div>
        </div>
    </div>

    <div class="card">
        <h2 style="margin-bottom: 1rem;">Course Grades</h2>
        <?php if (empty($enrollments)): ?>
            <div class="alert alert-info">No course enrollments found.</div>
        <?php else: ?>
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Course Code</th>
                        <th>Course Name</th>
                        <th>Section</th>
                        <th>Semester</th>
                        <th>Academic Year</th>
                        <th>Credits</th>
                        <th>Instructor</th>
                        <th>Final Grade</th>
                        <th>Points</th>
                        <th>Status</th>
                        <th>Details</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($enrollments as $enroll): 
                        $grades = $enrollment_grades[$enroll['enrollment_id']] ?? [];
                        $has_details = !empty($grades);
                    ?>
                    <tr>
                        <td><strong><?php echo sanitizeOutput($enroll['course_code']); ?></strong></td>
                        <td><?php echo sanitizeOutput($enroll['course_name']); ?></td>
                        <td><?php echo sanitizeOutput($enroll['section_number']); ?></td>
                        <td><?php echo sanitizeOutput($enroll['semester']); ?></td>
                        <td><?php echo sanitizeOutput($enroll['academic_year']); ?></td>
                        <td><?php echo sanitizeOutput($enroll['credits'] ?? 'N/A'); ?></td>
                        <td><?php echo sanitizeOutput(($enroll['faculty_first'] ?? '') . ' ' . ($enroll['faculty_last'] ?? '') ?: 'TBA'); ?></td>
                        <td>
                            <?php if (!empty($enroll['final_grade'])): ?>
                                <span class="badge badge-<?php echo in_array($enroll['final_grade'], ['A', 'A+', 'A-', 'B+', 'B']) ? 'success' : (in_array($enroll['final_grade'], ['B-', 'C+', 'C']) ? 'warning' : 'danger'); ?>">
                                    <?php echo sanitizeOutput($enroll['final_grade']); ?>
                                </span>
                            <?php else: ?>
                                <span class="text-muted">Pending</span>
                            <?php endif; ?>
                        </td>
                        <td><?php echo !empty($enroll['points']) ? number_format($enroll['points'], 2) : 'N/A'; ?></td>
                        <td><span class="badge badge-info"><?php echo sanitizeOutput($enroll['status']); ?></span></td>
                        <td>
                            <?php if ($has_details): ?>
                                <button type="button" class="btn btn-sm btn-info" onclick="openGradeModal(<?php echo htmlspecialchars(json_encode([
                                    'enrollment_id' => $enroll['enrollment_id'],
                                    'course_code' => $enroll['course_code'],
                                    'course_name' => $enroll['course_name'],
                                    'section' => $enroll['section_number'],
                                    'semester' => $enroll['semester'],
                                    'academic_year' => $enroll['academic_year'],
                                    'instructor' => trim(($enroll['faculty_first'] ?? '') . ' ' . ($enroll['faculty_last'] ?? '')),
                                    'final_grade' => $enroll['final_grade'] ?? 'Pending',
                                    'points' => $enroll['points'] ?? 0,
                                    'grades' => $grades
                                ]), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>)">
                                    <i class="fas fa-eye"></i> View Details
                                </button>
                            <?php else: ?>
                                <span class="text-muted">No details</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
</div>

<style>
/* Grade Modal Styles */
.grade-modal {
    display: none;
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: rgba(0, 0, 0, 0.3);
    backdrop-filter: blur(4px);
    z-index: 1000;
    padding: 1rem;
    overflow-y: auto;
}

.grade-modal.active {
    display: flex;
    align-items: center;
    justify-content: center;
    animation: fadeIn 0.3s ease;
}

@keyframes fadeIn {
    from { opacity: 0; }
    to { opacity: 1; }
}

.grade-modal-content {
    background: white;
    border-radius: 24px;
    padding: 0;
    max-width: 900px;
    width: 100%;
    max-height: 90vh;
    position: relative;
    animation: slideUp 0.4s ease;
    box-shadow: 0 25px 80px rgba(0, 0, 0, 0.4);
    overflow: hidden;
    display: flex;
    flex-direction: column;
}

.grade-modal-content::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    height: 6px;
    background: linear-gradient(90deg, #1e40af 0%, #2563eb 50%, #ffd700 100%);
    z-index: 1;
}

@keyframes slideUp {
    from {
        opacity: 0;
        transform: translateY(30px) scale(0.95);
    }
    to {
        opacity: 1;
        transform: translateY(0) scale(1);
    }
}

.grade-modal-close {
    position: absolute;
    top: 1rem;
    right: 1rem;
    background: rgba(30, 64, 175, 0.08);
    border: 2px solid rgba(30, 64, 175, 0.1);
    width: 40px;
    height: 40px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    transition: all 0.3s;
    color: #64748b;
    font-size: 1.125rem;
    z-index: 10;
}

.grade-modal-close:hover {
    background: linear-gradient(180deg, #1e40af 0%, #2563eb 100%);
    border-color: transparent;
    color: white;
    transform: rotate(90deg);
}

.grade-modal-header {
    background: linear-gradient(180deg, #1e40af 0%, #2563eb 100%);
    padding: 2rem;
    color: white;
    position: relative;
    overflow: hidden;
}

.grade-modal-header::before {
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

.grade-modal-header h2 {
    font-size: 1.75rem;
    font-weight: 800;
    margin: 0 0 0.5rem 0;
    text-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
    position: relative;
}

.grade-modal-header .course-info {
    display: flex;
    flex-wrap: wrap;
    gap: 1.5rem;
    margin-top: 1rem;
    position: relative;
}

.grade-modal-header .info-item {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    font-size: 0.95rem;
    color: rgba(255, 255, 255, 0.95);
}

.grade-modal-header .info-item i {
    color: #ffd700;
    font-size: 1rem;
}

.grade-modal-body {
    padding: 2rem;
    overflow-y: auto;
    flex: 1;
}

.grade-summary {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 1rem;
    margin-bottom: 2rem;
}

.grade-summary-card {
    background: linear-gradient(135deg, #f8fafc 0%, #ffffff 100%);
    border: 2px solid #e2e8f0;
    border-radius: 16px;
    padding: 1.5rem;
    text-align: center;
}

.grade-summary-card h4 {
    font-size: 0.875rem;
    font-weight: 600;
    color: #64748b;
    margin: 0 0 0.5rem 0;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.grade-summary-card .value {
    font-size: 2rem;
    font-weight: 800;
    color: #1e40af;
    margin: 0;
}

.grade-breakdown-table {
    width: 100%;
    border-collapse: collapse;
    margin-top: 1rem;
}

.grade-breakdown-table thead {
    background: linear-gradient(180deg, #1e40af 0%, #2563eb 100%);
    color: white;
}

.grade-breakdown-table th {
    padding: 1rem;
    text-align: left;
    font-weight: 700;
    font-size: 0.9rem;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.grade-breakdown-table td {
    padding: 1rem;
    border-bottom: 1px solid #e2e8f0;
}

.grade-breakdown-table tbody tr:hover {
    background: #f8fafc;
}

.grade-breakdown-table tfoot {
    background: linear-gradient(135deg, #f1f5f9 0%, #e2e8f0 100%);
    font-weight: 800;
}

.grade-breakdown-table tfoot td {
    border-top: 2px solid #1e40af;
    color: #1e40af;
}

@media (max-width: 768px) {
    .grade-modal-content {
        max-width: 100%;
        max-height: 95vh;
        border-radius: 20px;
    }
    
    .grade-modal-header {
        padding: 1.5rem;
    }
    
    .grade-modal-header h2 {
        font-size: 1.5rem;
    }
    
    .grade-modal-body {
        padding: 1.5rem;
    }
    
    .grade-breakdown-table {
        font-size: 0.875rem;
    }
    
    .grade-breakdown-table th,
    .grade-breakdown-table td {
        padding: 0.75rem 0.5rem;
    }
}
</style>

<!-- Grade Details Modal -->
<div id="gradeModal" class="grade-modal">
    <div class="grade-modal-content">
        <button class="grade-modal-close" onclick="closeGradeModal()">
            <i class="fas fa-times"></i>
        </button>
        
        <div class="grade-modal-header">
            <h2 id="modalCourseName">Course Grade Details</h2>
            <div class="course-info" id="modalCourseInfo"></div>
        </div>
        
        <div class="grade-modal-body">
            <div class="grade-summary" id="modalGradeSummary"></div>
            <div id="modalGradeTable"></div>
        </div>
    </div>
</div>

<script>
function openGradeModal(data) {
    const modal = document.getElementById('gradeModal');
    const courseName = document.getElementById('modalCourseName');
    const courseInfo = document.getElementById('modalCourseInfo');
    const gradeSummary = document.getElementById('modalGradeSummary');
    const gradeTable = document.getElementById('modalGradeTable');
    
    // Set course name
    courseName.textContent = data.course_code + ' - ' + data.course_name;
    
    // Set course info
    courseInfo.innerHTML = `
        <div class="info-item">
            <i class="fas fa-book"></i>
            <span>Section: ${data.section}</span>
        </div>
        <div class="info-item">
            <i class="fas fa-calendar"></i>
            <span>${data.semester} ${data.academic_year}</span>
        </div>
        <div class="info-item">
            <i class="fas fa-chalkboard-teacher"></i>
            <span>${data.instructor || 'TBA'}</span>
        </div>
    `;
    
    // Calculate totals
    let totalEarned = 0;
    let totalPossible = 0;
    data.grades.forEach(grade => {
        totalEarned += parseFloat(grade.points_earned || 0);
        totalPossible += parseFloat(grade.points_possible || 0);
    });
    const totalPercentage = totalPossible > 0 ? ((totalEarned / totalPossible) * 100).toFixed(2) : 0;
    
    // Set grade summary
    gradeSummary.innerHTML = `
        <div class="grade-summary-card">
            <h4>Final Grade</h4>
            <p class="value">${data.final_grade}</p>
        </div>
        <div class="grade-summary-card">
            <h4>Total Points</h4>
            <p class="value">${totalEarned.toFixed(2)} / ${totalPossible.toFixed(2)}</p>
        </div>
        <div class="grade-summary-card">
            <h4>Overall Percentage</h4>
            <p class="value">${totalPercentage}%</p>
        </div>
    `;
    
    // Build grade breakdown table
    let tableHTML = '<h3 style="margin-bottom: 1rem; color: #1e293b;">Grade Breakdown</h3>';
    tableHTML += '<table class="grade-breakdown-table">';
    tableHTML += '<thead><tr>';
    tableHTML += '<th>Type</th>';
    tableHTML += '<th>Assignment</th>';
    tableHTML += '<th>Points Earned</th>';
    tableHTML += '<th>Points Possible</th>';
    tableHTML += '<th>Percentage</th>';
    tableHTML += '<th>Letter Grade</th>';
    tableHTML += '</tr></thead><tbody>';
    
    data.grades.forEach(grade => {
        tableHTML += '<tr>';
        tableHTML += `<td>${escapeHtml(grade.assignment_type || 'N/A')}</td>`;
        tableHTML += `<td>${escapeHtml(grade.assignment_name || 'N/A')}</td>`;
        tableHTML += `<td>${parseFloat(grade.points_earned || 0).toFixed(2)}</td>`;
        tableHTML += `<td>${parseFloat(grade.points_possible || 0).toFixed(2)}</td>`;
        tableHTML += `<td>${parseFloat(grade.percentage || 0).toFixed(2)}%</td>`;
        tableHTML += `<td>${escapeHtml(grade.letter_grade || 'N/A')}</td>`;
        tableHTML += '</tr>';
    });
    
    tableHTML += '</tbody><tfoot><tr>';
    tableHTML += '<td colspan="2"><strong>Total</strong></td>';
    tableHTML += `<td><strong>${totalEarned.toFixed(2)}</strong></td>`;
    tableHTML += `<td><strong>${totalPossible.toFixed(2)}</strong></td>`;
    tableHTML += `<td><strong>${totalPercentage}%</strong></td>`;
    tableHTML += '<td></td>';
    tableHTML += '</tr></tfoot></table>';
    
    gradeTable.innerHTML = tableHTML;
    
    // Show modal
    modal.classList.add('active');
    document.body.style.overflow = 'hidden';
}

function closeGradeModal() {
    const modal = document.getElementById('gradeModal');
    modal.classList.remove('active');
    document.body.style.overflow = '';
}

function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

// Close modal when clicking outside
document.getElementById('gradeModal')?.addEventListener('click', function(e) {
    if (e.target === this) {
        closeGradeModal();
    }
});

// Close modal on Escape key
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        closeGradeModal();
    }
});
</script>

<?php include __DIR__ . '/../../includes/footer.php'; ?>

