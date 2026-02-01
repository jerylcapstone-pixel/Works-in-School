<?php
/**
 * Course Registration - Add Class Section
 * FIXED: Programs and Majors retrieval
 */
require_once __DIR__ . '/../../config/config.php';
requireAuth(ROLE_ADMIN);
$page_title = 'Add Class Section';
$pdo = getDBConnection();
$error = '';
$success = false;

// Get filter parameters from POST/GET (for dropdown filtering)
$department_filter = !empty($_POST['filter_department_id']) ? (int)$_POST['filter_department_id'] : (!empty($_GET['filter_department_id']) ? (int)$_GET['filter_department_id'] : null);
$program_filter = !empty($_POST['filter_program_id']) ? (int)$_POST['filter_program_id'] : (!empty($_GET['filter_program_id']) ? (int)$_GET['filter_program_id'] : null);

// DEBUG: Log filter values
error_log("FILTER DEBUG - POST data: " . print_r($_POST, true));
error_log("FILTER DEBUG - department_filter: " . ($department_filter ?? 'NULL'));
error_log("FILTER DEBUG - program_filter: " . ($program_filter ?? 'NULL'));

// Get departments, programs, majors, faculty, and rooms for dropdowns
$departments = [];
$programs = [];
$majors = [];
$courses = [];
$faculty = [];
$rooms = [];

$departments = $pdo->query("SELECT department_id, department_name FROM departments ORDER BY department_name")->fetchAll();

// ============================================================================
// CLIENT-SIDE FILTERING: Get ALL programs with department associations
// ============================================================================
try {
    // Get ALL programs (will filter client-side)
    try {
        $stmt = $pdo->query("SELECT program_id, program_name, department_id FROM programs WHERE status = 'Active' ORDER BY program_name");
        $programs = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        // If status column doesn't exist, get all programs
        $stmt = $pdo->query("SELECT program_id, program_name, department_id FROM programs ORDER BY program_name");
        $programs = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    error_log("Programs retrieved: " . count($programs));
    
} catch (PDOException $e) {
    error_log("Error fetching programs: " . $e->getMessage());
    $programs = [];
}

// ============================================================================
// CLIENT-SIDE FILTERING: Get ALL majors with program associations
// ============================================================================
$majors_table_exists = false;
$majors = [];
$major_column_exists = false;

try {
    // Check if majors table exists
    $check_stmt = $pdo->query("SHOW TABLES LIKE 'majors'");
    $majors_table_exists = $check_stmt->rowCount() > 0;
    
    if ($majors_table_exists) {
        // Get ALL majors (will filter client-side)
        try {
            $stmt = $pdo->query("SELECT m.major_id, m.major_name, m.program_id, p.department_id 
                         FROM majors m
                         LEFT JOIN programs p ON m.program_id = p.program_id
                         WHERE m.status = 'Active' 
                         ORDER BY m.major_name");
            $majors = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            // If status filter fails, get all majors
            $stmt = $pdo->query("SELECT m.major_id, m.major_name, m.program_id, p.department_id 
                               FROM majors m
                               LEFT JOIN programs p ON m.program_id = p.program_id
                               ORDER BY m.major_name");
            $majors = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }
        
        error_log("Majors retrieved: " . count($majors));
    }
} catch (PDOException $e) {
    error_log("Error fetching majors: " . $e->getMessage());
    $majors_table_exists = false;
    $majors = [];
}

// Check if major_id column exists in courses table
try {
    $check_stmt = $pdo->query("SHOW COLUMNS FROM courses LIKE 'major_id'");
    $major_column_exists = $check_stmt->rowCount() > 0;
} catch (PDOException $e) {
    $major_column_exists = false;
}

// Get all courses with their associations
$courses_query = "SELECT DISTINCT c.course_id, c.course_code, c.course_name, c.department_id, 
                  " . ($major_column_exists ? "c.major_id" : "NULL as major_id") . "
                  FROM courses c
                  WHERE c.status = 'Active'
                  ORDER BY c.course_name";
$all_courses = $pdo->query($courses_query)->fetchAll();

// Get program associations for each course
$course_programs = [];
foreach ($all_courses as $course) {
    $course_programs[$course['course_id']] = [];
    $stmt = $pdo->prepare("SELECT program_id FROM program_courses WHERE course_id = ?");
    $stmt->execute([$course['course_id']]);
    $programs_list = $stmt->fetchAll();
    $course_programs[$course['course_id']] = array_column($programs_list, 'program_id');
}

$faculty = $pdo->query("SELECT f.faculty_id, f.first_name, f.last_name, f.department_id, d.department_name
                        FROM faculty f
                        LEFT JOIN departments d ON f.department_id = d.department_id
                        WHERE f.status = 'Active' 
                        ORDER BY d.department_name, f.last_name, f.first_name")->fetchAll();

// Get rooms
try {
    $rooms = $pdo->query("SELECT room_id, room_number, building_name FROM rooms WHERE status = 'Available' ORDER BY room_number")->fetchAll();
} catch (PDOException $e) {
    $rooms = [];
}

// Only process form submission if it's an actual section creation (not a filter update)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_section'])) {
    $course_id = !empty($_POST['course_id']) ? (int)$_POST['course_id'] : null;
    $faculty_id = !empty($_POST['faculty_id']) ? (int)$_POST['faculty_id'] : null;
    $semester = sanitizeInput($_POST['semester'] ?? '');
    $academic_year = sanitizeInput($_POST['academic_year'] ?? '');
    // Get selected day combination (single selection)
    $schedule_day_combination = sanitizeInput($_POST['schedule_day_combination'] ?? '');
    $schedule_start_time = $_POST['schedule_start_time'] ?? '';
    $schedule_end_time = $_POST['schedule_end_time'] ?? '';
    
    // Define day combinations mapping
    $day_combinations_map = [
        'MW' => ['Monday', 'Wednesday'],
        'TTH' => ['Tuesday', 'Thursday'],
        'F' => ['Friday'],
        'S' => ['Saturday']
    ];
    
    // Parse the day combination code to get individual days
    $schedule_days = [];
    $schedule_data = [];
    
    if (!empty($schedule_day_combination)) {
        // Get the days array from the combination code
        if (isset($day_combinations_map[$schedule_day_combination])) {
            $schedule_days = $day_combinations_map[$schedule_day_combination];
            
            // Build schedule data with same time for all days in the combination
            if (!empty($schedule_start_time) && !empty($schedule_end_time) && !empty($schedule_days)) {
                foreach ($schedule_days as $day) {
                    $schedule_data[$day] = [
                        'start' => $schedule_start_time,
                        'end' => $schedule_end_time
                    ];
                }
            }
        }
    }
    
    // Store the combination code (MW, TTH, F, S) in schedule_day field
    // Times are stored separately in start_time and end_time fields
    $schedule_day = $schedule_day_combination;
    $room_id = !empty($_POST['room_id']) ? (int)$_POST['room_id'] : null;
    $max_enrollment = !empty($_POST['max_enrollment']) ? (int)$_POST['max_enrollment'] : 0;
    $waitlist_max = !empty($_POST['waitlist_max']) ? (int)$_POST['waitlist_max'] : 0;
    $status = sanitizeInput($_POST['status'] ?? 'Open');

    if (empty($course_id) || empty($semester) || empty($academic_year) || 
        empty($schedule_day_combination) || empty($schedule_start_time) || empty($schedule_end_time) || empty($schedule_data) || $max_enrollment <= 0) {
        $error = 'Please fill in all required fields, including schedule days combination and start/end times.';
    } else {
        try {
            // Auto-generate section code
            // Get course code
            $stmt = $pdo->prepare("SELECT course_code FROM courses WHERE course_id = ?");
            $stmt->execute([$course_id]);
            $course = $stmt->fetch();
            $course_code = $course['course_code'] ?? 'COURSE';
            
            // Get the next section number for this course, semester, and academic year
            $stmt = $pdo->prepare("SELECT COUNT(*) + 1 as next_num FROM class_sections 
                                  WHERE course_id = ? AND semester = ? AND academic_year = ?");
            $stmt->execute([$course_id, $semester, $academic_year]);
            $next_number = $stmt->fetchColumn();
            
            // Generate section code: COURSECODE-###
            $section_code = $course_code . '-' . str_pad($next_number, 3, '0', STR_PAD_LEFT);
            
            // Get default times from first schedule (needed for validation)
            $default_start = '';
            $default_end = '';
            if (!empty($schedule_data)) {
                $first_schedule = reset($schedule_data);
                $default_start = $first_schedule['start'];
                $default_end = $first_schedule['end'];
            }
            
            // Check for duplicate section code (just in case)
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM class_sections WHERE course_id = ? AND section_number = ? AND semester = ? AND academic_year = ?");
            $stmt->execute([$course_id, $section_code, $semester, $academic_year]);
            if ($stmt->fetchColumn() > 0) {
                $error = 'A section with this code already exists. Please try again.';
            } 
            // Check for faculty schedule conflicts
            elseif (!empty($faculty_id)) {
                $stmt = $pdo->prepare("SELECT cs.section_id, cs.section_number, c.course_name, c.course_code
                                      FROM class_sections cs
                                      LEFT JOIN courses c ON cs.course_id = c.course_id
                                      WHERE cs.faculty_id = ? 
                                      AND cs.semester = ? 
                                      AND cs.academic_year = ?
                                      AND cs.schedule_day = ?
                                      AND cs.start_time = ?
                                      AND cs.end_time = ?
                                      AND cs.status != 'Cancelled'");
                $stmt->execute([$faculty_id, $semester, $academic_year, $schedule_day, $default_start, $default_end]);
                $conflict = $stmt->fetch();
                
                if ($conflict) {
                    // Get faculty name for error message
                    $stmt = $pdo->prepare("SELECT first_name, last_name FROM faculty WHERE faculty_id = ?");
                    $stmt->execute([$faculty_id]);
                    $faculty_info = $stmt->fetch();
                    $faculty_name = $faculty_info ? $faculty_info['first_name'] . ' ' . $faculty_info['last_name'] : 'Selected faculty';
                    
                    $error = 'Faculty conflict: ' . $faculty_name . ' is already teaching ' . 
                             $conflict['course_code'] . ' (' . $conflict['course_name'] . ') - Section ' . 
                             $conflict['section_number'] . ' at this time (' . $schedule_day . ' ' . 
                             $default_start . '-' . $default_end . ').';
                }
            }
            
            // Check for room conflicts (if room is selected)
            if (empty($error) && !empty($room_id)) {
                $stmt = $pdo->prepare("SELECT cs.section_id, cs.section_number, c.course_name, c.course_code
                                      FROM class_sections cs
                                      LEFT JOIN courses c ON cs.course_id = c.course_id
                                      WHERE cs.room_id = ? 
                                      AND cs.semester = ? 
                                      AND cs.academic_year = ?
                                      AND cs.schedule_day = ?
                                      AND cs.start_time = ?
                                      AND cs.end_time = ?
                                      AND cs.status != 'Cancelled'");
                $stmt->execute([$room_id, $semester, $academic_year, $schedule_day, $default_start, $default_end]);
                $conflict = $stmt->fetch();
                
                if ($conflict) {
                    // Get room info for error message
                    $stmt = $pdo->prepare("SELECT room_number, building_name FROM rooms WHERE room_id = ?");
                    $stmt->execute([$room_id]);
                    $room_info = $stmt->fetch();
                    $room_name = $room_info ? $room_info['room_number'] . ($room_info['building_name'] ? ' (' . $room_info['building_name'] . ')' : '') : 'Selected room';
                    
                    $error = 'Room conflict: ' . $room_name . ' is already being used for ' . 
                             $conflict['course_code'] . ' (' . $conflict['course_name'] . ') - Section ' . 
                             $conflict['section_number'] . ' at this time (' . $schedule_day . ' ' . 
                             $default_start . '-' . $default_end . ').';
                }
            }
            
            if (empty($error)) {
                $stmt = $pdo->prepare("INSERT INTO class_sections (course_id, section_number, faculty_id, semester, academic_year, 
                                      schedule_day, start_time, end_time, room_id, max_enrollment, waitlist_max, status)
                                      VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                $stmt->execute([
                    $course_id,
                    $section_code,
                    $faculty_id ?: null,
                    $semester,
                    $academic_year,
                    $schedule_day,
                    $default_start,
                    $default_end,
                    $room_id ?: null,
                    $max_enrollment,
                    $waitlist_max,
                    $status
                ]);
                
                $success = true;
                header('Location: ' . BASE_URL . 'modules/registration/index.php?success=' . urlencode('Section created successfully with code: ' . $section_code));
                exit;
            }
        } catch (PDOException $e) {
            $error = 'Error creating section: ' . $e->getMessage();
        }
    }
}

include __DIR__ . '/../../includes/header.php';
?>

<div class="page-container">
    <div class="page-header">
        <h1><i class="fas fa-plus"></i> Add Class Section</h1>
        <a href="<?php echo BASE_URL; ?>modules/registration/index.php" class="btn btn-secondary">
            <i class="fas fa-arrow-left"></i> Back
        </a>
    </div>

    <?php if ($error): ?>
        <div class="alert alert-error"><?php echo sanitizeOutput($error); ?></div>
    <?php endif; ?>

    <div class="card">
        <form method="POST" action="" class="form">
            <div class="form-section">
                <h3><i class="fas fa-filter"></i> Filter by Department, Program, Major (Optional)</h3>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="filter_department_id">Department (Optional)</label>
                        <select id="filter_department_id" name="filter_department_id" class="form-control" onchange="updatePrograms()">
                            <option value="">All Departments</option>
                            <?php foreach ($departments as $dept): ?>
                                <option value="<?php echo $dept['department_id']; ?>" 
                                        <?php echo $department_filter == $dept['department_id'] ? 'selected' : ''; ?>>
                                    <?php echo sanitizeOutput($dept['department_name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="filter_program_id">Program (Optional)</label>
                        <select id="filter_program_id" name="filter_program_id" class="form-control" onchange="updateMajors(); updateCourses(); updateFaculty();">
                            <option value="">All Programs</option>
                            <?php foreach ($programs as $prog): ?>
                                <option value="<?php echo $prog['program_id']; ?>" 
                                        data-dept-id="<?php echo !empty($prog['department_id']) ? (int)$prog['department_id'] : ''; ?>"
                                        <?php echo $program_filter == $prog['program_id'] ? 'selected' : ''; ?>>
                                    <?php echo sanitizeOutput($prog['program_name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <small class="text-muted" id="program-count-msg">All programs</small>
                    </div>
                    
                    <?php if ($majors_table_exists): ?>
                        <div class="form-group">
                            <label for="filter_major_id">Major (Optional)</label>
                            <select id="filter_major_id" name="filter_major_id" class="form-control" onchange="updateCourses(); updateFaculty();">
                                <option value="">All Majors</option>
                                <?php foreach ($majors as $major): ?>
                                    <option value="<?php echo $major['major_id']; ?>" 
                                            data-prog-id="<?php echo $major['program_id'] ?? ''; ?>"
                                            <?php echo (isset($_POST['filter_major_id']) && $_POST['filter_major_id'] == $major['major_id']) ? 'selected' : ''; ?>>
                                        <?php echo sanitizeOutput($major['major_name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <small class="text-muted" id="major-count-msg">All majors</small>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <div class="form-section">
                <h3><i class="fas fa-book"></i> Course Information</h3>
                
                <div class="form-row">
                    <div class="form-group required">
                        <label for="course_id">Course</label>
                        <select id="course_id" name="course_id" required>
                            <option value="">Select Course</option>
                            <?php foreach ($all_courses as $course): 
                                $program_ids = $course_programs[$course['course_id']] ?? [];
                                $first_program_id = !empty($program_ids) ? $program_ids[0] : '';
                                $all_program_ids = implode(',', $program_ids);
                            ?>
                                <option value="<?php echo $course['course_id']; ?>" 
                                        data-department-id="<?php echo $course['department_id'] ?? ''; ?>"
                                        data-program-id="<?php echo $first_program_id; ?>"
                                        data-all-program-ids="<?php echo $all_program_ids; ?>"
                                        data-major-id="<?php echo ($major_column_exists ? ($course['major_id'] ?? '') : ''); ?>"
                                        <?php echo (isset($_POST['course_id']) && $_POST['course_id'] == $course['course_id']) ? 'selected' : ''; ?>>
                                    <?php echo sanitizeOutput($course['course_name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label>Section Code</label>
                        <div style="padding: 0.75rem; background: #f0f9ff; border: 2px solid #0ea5e9; border-radius: 8px; color: #0369a1;">
                            <i class="fas fa-magic"></i> 
                            <strong>Auto-generated</strong>
                            <br>
                            <small style="color: #64748b;">Section code will be automatically created (e.g., COURSE-001, COURSE-002)</small>
                        </div>
                    </div>
                </div>
            </div>

            <div class="form-section">
                <h3><i class="fas fa-user-tie"></i> Faculty Assignment</h3>
                
                <div class="form-group">
                    <label for="faculty_id">Faculty</label>
                    <select id="faculty_id" name="faculty_id">
                        <option value="">Select Faculty</option>
                        <?php 
                        $current_dept = '';
                        foreach ($faculty as $fac): 
                            $dept_name = $fac['department_name'] ?? 'No Department';
                            if ($dept_name !== $current_dept) {
                                if ($current_dept !== '') echo '</optgroup>';
                                echo '<optgroup label="' . sanitizeOutput($dept_name) . '">';
                                $current_dept = $dept_name;
                            }
                        ?>
                            <option value="<?php echo $fac['faculty_id']; ?>" 
                                    data-department-id="<?php echo $fac['department_id'] ?? ''; ?>"
                                    <?php echo (isset($_POST['faculty_id']) && $_POST['faculty_id'] == $fac['faculty_id']) ? 'selected' : ''; ?>>
                                <?php echo sanitizeOutput($fac['first_name'] . ' ' . $fac['last_name']); ?>
                            </option>
                        <?php endforeach; ?>
                        <?php if ($current_dept !== '') echo '</optgroup>'; ?>
                    </select>
                </div>
            </div>

            <div class="form-section">
                <h3><i class="fas fa-calendar"></i> Schedule Information</h3>
                
                <div class="form-row">
                    <div class="form-group required">
                        <label for="semester">Semester</label>
                        <select id="semester" name="semester" required>
                            <option value="">Select Semester</option>
                            <option value="First" <?php echo (isset($_POST['semester']) && $_POST['semester'] === 'First') ? 'selected' : ''; ?>>First</option>
                            <option value="2nd" <?php echo (isset($_POST['semester']) && $_POST['semester'] === '2nd') ? 'selected' : ''; ?>>2nd</option>
                            <option value="Summer" <?php echo (isset($_POST['semester']) && $_POST['semester'] === 'Summer') ? 'selected' : ''; ?>>Summer</option>
                        </select>
                    </div>
                    
                    <div class="form-group required">
                        <label for="academic_year">Academic Year</label>
                        <input type="text" id="academic_year" name="academic_year" 
                               value="<?php echo sanitizeOutput($_POST['academic_year'] ?? date('Y') . '-' . (date('Y') + 1)); ?>" 
                               placeholder="e.g., 2024-2025" required>
                    </div>
                </div>
                
                <div class="form-group required">
                    <label for="schedule_day_combination">Schedule Days <span style="font-size: 0.9em; color: #666;">(Select one combination)</span></label>
                    <?php
                    // Define day combinations: MW, TTH, F, S
                    $day_combinations = [
                        'MW' => ['Monday', 'Wednesday'],
                        'TTH' => ['Tuesday', 'Thursday'],
                        'F' => ['Friday'],
                        'S' => ['Saturday']
                    ];
                    
                    $selected_combination = isset($_POST['schedule_day_combination']) ? $_POST['schedule_day_combination'] : '';
                    ?>
                    <select id="schedule_day_combination" name="schedule_day_combination" class="form-control" required onchange="updateScheduleTimes()">
                        <option value="">Select Day Combination</option>
                        <?php foreach ($day_combinations as $combination_label => $days_in_combo): ?>
                            <option value="<?php echo htmlspecialchars($combination_label); ?>" 
                                    data-days="<?php echo htmlspecialchars(implode(',', $days_in_combo)); ?>"
                                    <?php echo $selected_combination === $combination_label ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($combination_label); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <small class="text-muted">Select one day combination for this section</small>
                    
                    <div id="schedule-times-container" style="margin-top: 20px; padding: 15px; background: #f9f9f9; border-radius: 4px; border: 1px solid #ddd; display: none;">
                        <div style="display: flex; gap: 20px; align-items: flex-end;">
                            <div style="flex: 1;">
                                <label for="schedule_start_time" style="font-size: 0.9em; color: #666; font-weight: 500; display: block; margin-bottom: 5px;">
                                    Start Time <span style="color: red;">*</span>
                                </label>
                                <input type="time" id="schedule_start_time" name="schedule_start_time" 
                                       value="<?php echo sanitizeOutput($_POST['schedule_start_time'] ?? ''); ?>" 
                                       class="form-control"
                                       style="min-width: 150px;"
                                       disabled>
                            </div>
                            <div style="flex: 1;">
                                <label for="schedule_end_time" style="font-size: 0.9em; color: #666; font-weight: 500; display: block; margin-bottom: 5px;">
                                    End Time <span style="color: red;">*</span>
                                </label>
                                <input type="time" id="schedule_end_time" name="schedule_end_time" 
                                       value="<?php echo sanitizeOutput($_POST['schedule_end_time'] ?? ''); ?>" 
                                       class="form-control"
                                       style="min-width: 150px;"
                                       disabled>
                            </div>
                        </div>
                        <small class="text-muted" style="display: block; margin-top: 10px;">
                            The same time will apply to all days in the selected combination.
                        </small>
                    </div>
                </div>
            </div>

            <div class="form-section">
                <h3><i class="fas fa-door-open"></i> Room & Enrollment</h3>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="room_id">Room</label>
                        <select id="room_id" name="room_id">
                            <option value="">Select Room</option>
                            <?php foreach ($rooms as $room): ?>
                                <option value="<?php echo $room['room_id']; ?>" 
                                        <?php echo (isset($_POST['room_id']) && $_POST['room_id'] == $room['room_id']) ? 'selected' : ''; ?>>
                                    <?php echo sanitizeOutput($room['room_number'] . ($room['building_name'] ? ' (' . $room['building_name'] . ')' : '')); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="form-group required">
                        <label for="max_enrollment">Max Enrollment</label>
                        <input type="number" id="max_enrollment" name="max_enrollment" 
                               value="<?php echo sanitizeOutput($_POST['max_enrollment'] ?? '30'); ?>" 
                               min="1" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="waitlist_max">Waitlist Max</label>
                        <input type="number" id="waitlist_max" name="waitlist_max" 
                               value="<?php echo sanitizeOutput($_POST['waitlist_max'] ?? '5'); ?>" 
                               min="0">
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="status">Status</label>
                    <select id="status" name="status">
                        <option value="Open" <?php echo (!isset($_POST['status']) || $_POST['status'] === 'Open') ? 'selected' : ''; ?>>Open</option>
                        <option value="Full" <?php echo (isset($_POST['status']) && $_POST['status'] === 'Full') ? 'selected' : ''; ?>>Full</option>
                        <option value="Closed" <?php echo (isset($_POST['status']) && $_POST['status'] === 'Closed') ? 'selected' : ''; ?>>Closed</option>
                        <option value="Cancelled" <?php echo (isset($_POST['status']) && $_POST['status'] === 'Cancelled') ? 'selected' : ''; ?>>Cancelled</option>
                    </select>
                </div>
            </div>

            <div class="form-actions">
                <input type="hidden" name="create_section" value="1">
                <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Create Section</button>
                <a href="<?php echo BASE_URL; ?>modules/registration/index.php" class="btn btn-secondary">Cancel</a>
            </div>
        </form>
    </div>
</div>

<script>
// ============================================================================
// CASCADING DROPDOWNS - CLEAN IMPLEMENTATION
// ============================================================================

// Store all original options
let allPrograms = [];
let allMajors = [];
let allCourses = [];
let allFaculty = [];

// Update programs dropdown based on selected department
function updatePrograms() {
    const deptSelect = document.getElementById('filter_department_id');
    const programSelect = document.getElementById('filter_program_id');
    
    if (!programSelect || allPrograms.length === 0) return;
    
    const selectedDeptId = deptSelect ? deptSelect.value : '';
    const currentValue = programSelect.value;
    
    // Clear dropdown
    programSelect.innerHTML = '<option value="">All Programs</option>';
    
    // Filter and add programs
    let count = 0;
    allPrograms.forEach(function(prog) {
        if (!selectedDeptId || prog.deptId == selectedDeptId) {
            const option = document.createElement('option');
            option.value = prog.value;
            option.textContent = prog.text;
            option.setAttribute('data-dept-id', prog.deptId || '');
            if (prog.value == currentValue) {
                option.selected = true;
            }
            programSelect.appendChild(option);
            count++;
        }
    });
    
    // Update message
    const msg = document.getElementById('program-count-msg');
    if (msg) {
        msg.textContent = selectedDeptId ? (count + ' program(s)') : 'All programs';
    }
    
    // Reset and update majors, courses, and faculty
    updateMajors();
    updateCourses();
    updateFaculty();
}

// Update majors dropdown based on selected program
function updateMajors() {
    const programSelect = document.getElementById('filter_program_id');
    const majorSelect = document.getElementById('filter_major_id');
    
    if (!majorSelect || allMajors.length === 0) return;
    
    const selectedProgId = programSelect ? programSelect.value : '';
    const currentValue = majorSelect.value;
    
    // Clear dropdown
    majorSelect.innerHTML = '<option value="">All Majors</option>';
    
    // Filter and add majors
    let count = 0;
    allMajors.forEach(function(major) {
        if (!selectedProgId || major.progId == selectedProgId) {
            const option = document.createElement('option');
            option.value = major.value;
            option.textContent = major.text;
            option.setAttribute('data-prog-id', major.progId || '');
            if (major.value == currentValue) {
                option.selected = true;
            }
            majorSelect.appendChild(option);
            count++;
        }
    });
    
    // Update message
    const msg = document.getElementById('major-count-msg');
    if (msg) {
        msg.textContent = selectedProgId ? (count + ' major(s)') : 'All majors';
    }
    
    // Update courses and faculty
    updateCourses();
    updateFaculty();
}

// Update courses dropdown based on filters
function updateCourses() {
    const deptSelect = document.getElementById('filter_department_id');
    const programSelect = document.getElementById('filter_program_id');
    const majorSelect = document.getElementById('filter_major_id');
    const courseSelect = document.getElementById('course_id');
    
    if (!courseSelect || allCourses.length === 0) return;
    
    const selectedDeptId = deptSelect ? deptSelect.value : '';
    const selectedProgId = programSelect ? programSelect.value : '';
    const selectedMajorId = majorSelect ? majorSelect.value : '';
    const currentValue = courseSelect.value;
    
    // Clear dropdown
    courseSelect.innerHTML = '<option value="">Select Course</option>';
    
    // Filter and add courses
    let count = 0;
    allCourses.forEach(function(course) {
        let show = true;
        
        // Filter by department
        if (selectedDeptId && course.deptId && String(course.deptId) !== String(selectedDeptId)) {
            show = false;
        }
        
        // Filter by program
        if (show && selectedProgId) {
            let hasProgram = false;
            // Check if course has multiple program associations
            if (course.progIds && course.progIds.length > 0) {
                hasProgram = course.progIds.some(id => String(id) === String(selectedProgId));
            } else if (course.progId) {
                hasProgram = String(course.progId) === String(selectedProgId);
            }
            // If no program filter is set, show all courses (unless department/major filters apply)
            if (selectedProgId && !hasProgram) {
                show = false;
            }
        }
        
        // Filter by major
        if (show && selectedMajorId && course.majorId && String(course.majorId) !== String(selectedMajorId)) {
            show = false;
        }
        
        if (show) {
            const option = document.createElement('option');
            option.value = course.value;
            option.textContent = course.text;
            option.setAttribute('data-department-id', course.deptId || '');
            option.setAttribute('data-program-id', course.progId || '');
            option.setAttribute('data-all-program-ids', course.progIds ? course.progIds.join(',') : '');
            option.setAttribute('data-major-id', course.majorId || '');
            if (course.value == currentValue) {
                option.selected = true;
            }
            courseSelect.appendChild(option);
            count++;
        }
    });
    
    console.log('Filtered courses:', count);
}

// Update faculty dropdown based on selected department
function updateFaculty() {
    const deptSelect = document.getElementById('filter_department_id');
    const facultySelect = document.getElementById('faculty_id');
    
    if (!facultySelect || allFaculty.length === 0) return;
    
    const selectedDeptId = deptSelect ? deptSelect.value : '';
    const currentValue = facultySelect.value;
    
    // Clear dropdown but preserve optgroups structure
    facultySelect.innerHTML = '<option value="">Select Faculty</option>';
    
    // Filter and add faculty
    let currentDept = '';
    let currentGroup = null;
    let count = 0;
    
    allFaculty.forEach(function(fac) {
        if (!selectedDeptId || String(fac.deptId) === String(selectedDeptId)) {
            // Check if we need a new optgroup
            if (fac.deptName && fac.deptName !== currentDept) {
                if (currentGroup) {
                    facultySelect.appendChild(currentGroup);
                }
                currentGroup = document.createElement('optgroup');
                currentGroup.label = fac.deptName || 'No Department';
                currentDept = fac.deptName;
            }
            
            const option = document.createElement('option');
            option.value = fac.value;
            option.textContent = fac.text;
            option.setAttribute('data-department-id', fac.deptId || '');
            if (fac.value == currentValue) {
                option.selected = true;
            }
            
            if (currentGroup) {
                currentGroup.appendChild(option);
            } else {
                facultySelect.appendChild(option);
            }
            count++;
        }
    });
    
    // Append last group
    if (currentGroup) {
        facultySelect.appendChild(currentGroup);
    }
    
    console.log('Filtered faculty:', count);
}

// Handle schedule day combination dropdown - show/hide time inputs
function updateScheduleTimes() {
    const dayCombinationSelect = document.getElementById('schedule_day_combination');
    const timeContainer = document.getElementById('schedule-times-container');
    const startTimeInput = document.getElementById('schedule_start_time');
    const endTimeInput = document.getElementById('schedule_end_time');
    
    if (dayCombinationSelect && timeContainer && startTimeInput && endTimeInput) {
        const hasSelection = dayCombinationSelect.value && dayCombinationSelect.value !== '';
        
        if (hasSelection) {
            // Show container and enable inputs
            timeContainer.style.display = 'block';
            startTimeInput.disabled = false;
            endTimeInput.disabled = false;
            startTimeInput.required = true;
            endTimeInput.required = true;
        } else {
            // Hide container and disable/clear inputs
            timeContainer.style.display = 'none';
            startTimeInput.disabled = true;
            endTimeInput.disabled = true;
            startTimeInput.required = false;
            endTimeInput.required = false;
            startTimeInput.value = '';
            endTimeInput.value = '';
        }
    }
}

// Initialize schedule times on page load
function setupScheduleDays() {
    updateScheduleTimes();
}

// Initialize on page load
document.addEventListener('DOMContentLoaded', function() {
    // Load programs data
    const programSelect = document.getElementById('filter_program_id');
    if (programSelect) {
        programSelect.querySelectorAll('option[value]').forEach(function(opt) {
            if (opt.value) {
                allPrograms.push({
                    value: opt.value,
                    text: opt.textContent.trim(),
                    deptId: opt.getAttribute('data-dept-id') || ''
                });
            }
        });
    }
    
    // Load majors data
    const majorSelect = document.getElementById('filter_major_id');
    if (majorSelect) {
        majorSelect.querySelectorAll('option[value]').forEach(function(opt) {
            if (opt.value) {
                allMajors.push({
                    value: opt.value,
                    text: opt.textContent.trim(),
                    progId: opt.getAttribute('data-prog-id') || ''
                });
            }
        });
    }
    
    // Load courses data
    const courseSelect = document.getElementById('course_id');
    if (courseSelect) {
        courseSelect.querySelectorAll('option[value]').forEach(function(opt) {
            if (opt.value) {
                const progIdsStr = opt.getAttribute('data-all-program-ids') || '';
                const progIdsArray = progIdsStr ? progIdsStr.split(',').filter(id => id) : [];
                allCourses.push({
                    value: opt.value,
                    text: opt.textContent.trim(),
                    deptId: opt.getAttribute('data-department-id') || '',
                    progId: opt.getAttribute('data-program-id') || '',
                    progIds: progIdsArray,
                    majorId: opt.getAttribute('data-major-id') || ''
                });
            }
        });
    }
    
    // Load faculty data
    const facultySelect = document.getElementById('faculty_id');
    if (facultySelect) {
        let currentDeptName = '';
        facultySelect.querySelectorAll('option, optgroup').forEach(function(element) {
            if (element.tagName === 'OPTGROUP') {
                currentDeptName = element.label || '';
            } else if (element.value) {
                allFaculty.push({
                    value: element.value,
                    text: element.textContent.trim(),
                    deptId: element.getAttribute('data-department-id') || '',
                    deptName: currentDeptName
                });
            }
        });
    }
    
    // Setup schedule day handlers
    setupScheduleDays();
    
    // Initialize filters if department is pre-selected
    const deptSelect = document.getElementById('filter_department_id');
    if (deptSelect && deptSelect.value) {
        updatePrograms();
    }
});
</script>