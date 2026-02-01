<?php
/**
 * Course Registration - Edit Class Section
 */
require_once __DIR__ . '/../../config/config.php';
requireAuth(ROLE_ADMIN);
$page_title = 'Edit Class Section';
$pdo = getDBConnection();

$section_id = (int)($_GET['id'] ?? 0);
if (!$section_id) {
    header('Location: ' . BASE_URL . 'modules/registration/index.php?error=Invalid section ID');
    exit;
}

// Get section details
$stmt = $pdo->prepare("SELECT * FROM class_sections WHERE section_id = ?");
$stmt->execute([$section_id]);
$section = $stmt->fetch();

if (!$section) {
    header('Location: ' . BASE_URL . 'modules/registration/index.php?error=Section not found');
    exit;
}

// Check if major_id column exists in courses table (needed before querying course info)
$major_column_exists = false;
try {
    $check_stmt = $pdo->query("SHOW COLUMNS FROM courses LIKE 'major_id'");
    $major_column_exists = $check_stmt->rowCount() > 0;
} catch (PDOException $e) {
    $major_column_exists = false;
}

// Get course information to determine department, program, and major for filters
$current_course_dept = null;
$current_course_program = null;
$current_course_major = null;

if ($section['course_id']) {
    // Get course department and major
    $stmt = $pdo->prepare("SELECT department_id, " . ($major_column_exists ? "major_id" : "NULL as major_id") . " 
                           FROM courses WHERE course_id = ?");
    $stmt->execute([$section['course_id']]);
    $course_info = $stmt->fetch();
    if ($course_info) {
        $current_course_dept = $course_info['department_id'] ?? null;
        if ($major_column_exists) {
            $current_course_major = $course_info['major_id'] ?? null;
        }
    }
    
    // Get first program associated with this course
    $stmt = $pdo->prepare("SELECT program_id FROM program_courses WHERE course_id = ? LIMIT 1");
    $stmt->execute([$section['course_id']]);
    $program_course = $stmt->fetch();
    if ($program_course) {
        $current_course_program = $program_course['program_id'] ?? null;
    }
}

// Get filter parameters from POST/GET, or use current course's values
$department_filter = !empty($_POST['filter_department_id']) ? (int)$_POST['filter_department_id'] : 
                     (!empty($_GET['filter_department_id']) ? (int)$_GET['filter_department_id'] : 
                     ($current_course_dept ? (int)$current_course_dept : null));
$program_filter = !empty($_POST['filter_program_id']) ? (int)$_POST['filter_program_id'] : 
                  (!empty($_GET['filter_program_id']) ? (int)$_GET['filter_program_id'] : 
                  ($current_course_program ? (int)$current_course_program : null));
$major_filter = !empty($_POST['filter_major_id']) ? (int)$_POST['filter_major_id'] : 
                (!empty($_GET['filter_major_id']) ? (int)$_GET['filter_major_id'] : 
                ($current_course_major ? (int)$current_course_major : null));

// Get departments, programs, majors, faculty, and rooms for dropdowns
$departments = [];
$programs = [];
$majors = [];
$all_courses = [];
$course_programs = [];
$faculty = [];
$rooms = [];

$departments = $pdo->query("SELECT department_id, department_name FROM departments ORDER BY department_name")->fetchAll();

// Get ALL programs (will filter client-side)
try {
    try {
        $stmt = $pdo->query("SELECT program_id, program_name, department_id FROM programs WHERE status = 'Active' ORDER BY program_name");
        $programs = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        $stmt = $pdo->query("SELECT program_id, program_name, department_id FROM programs ORDER BY program_name");
        $programs = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
} catch (PDOException $e) {
    $programs = [];
}

// Get ALL majors (will filter client-side)
$majors_table_exists = false;
$majors = [];
$major_column_exists = false;

try {
    $check_stmt = $pdo->query("SHOW TABLES LIKE 'majors'");
    $majors_table_exists = $check_stmt->rowCount() > 0;
    
    if ($majors_table_exists) {
        try {
            $stmt = $pdo->query("SELECT m.major_id, m.major_name, m.program_id, p.department_id 
                         FROM majors m
                         LEFT JOIN programs p ON m.program_id = p.program_id
                         WHERE m.status = 'Active' 
                         ORDER BY m.major_name");
            $majors = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            $stmt = $pdo->query("SELECT m.major_id, m.major_name, m.program_id, p.department_id 
                               FROM majors m
                               LEFT JOIN programs p ON m.program_id = p.program_id
                               ORDER BY m.major_name");
            $majors = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }
    }
} catch (PDOException $e) {
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

// Get faculty - filter by course department if course is selected
if ($section['course_id'] && $current_course_dept) {
    // Only show faculty from the course's department
    $stmt = $pdo->prepare("SELECT f.faculty_id, f.first_name, f.last_name, f.department_id, d.department_name
                            FROM faculty f
                            LEFT JOIN departments d ON f.department_id = d.department_id
                            WHERE f.status = 'Active' 
                            AND f.department_id = ?
                            ORDER BY d.department_name, f.last_name, f.first_name");
    $stmt->execute([$current_course_dept]);
    $faculty = $stmt->fetchAll();
} else {
    // No course selected or no department found - show all faculty
    $faculty = $pdo->query("SELECT f.faculty_id, f.first_name, f.last_name, f.department_id, d.department_name
                            FROM faculty f
                            LEFT JOIN departments d ON f.department_id = d.department_id
                            WHERE f.status = 'Active' 
                            ORDER BY d.department_name, f.last_name, f.first_name")->fetchAll();
}

try {
    $rooms = $pdo->query("SELECT room_id, room_number, building_name FROM rooms WHERE status = 'Available' ORDER BY room_number")->fetchAll();
} catch (PDOException $e) {
    $rooms = [];
}

$error = '';
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $course_id = !empty($_POST['course_id']) ? (int)$_POST['course_id'] : null;
    $section_number = sanitizeInput($_POST['section_number'] ?? '');
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
    $start_time = ''; // Keep for backward compatibility but not used
    $end_time = ''; // Keep for backward compatibility but not used
    $room_id = !empty($_POST['room_id']) ? (int)$_POST['room_id'] : null;
    $max_enrollment = !empty($_POST['max_enrollment']) ? (int)$_POST['max_enrollment'] : 0;
    $waitlist_max = !empty($_POST['waitlist_max']) ? (int)$_POST['waitlist_max'] : 0;
    $status = sanitizeInput($_POST['status'] ?? 'Open');

    if (empty($course_id) || empty($section_number) || empty($semester) || empty($academic_year) || 
        empty($schedule_day_combination) || empty($schedule_start_time) || empty($schedule_end_time) || empty($schedule_data) || $max_enrollment <= 0) {
        $error = 'Please fill in all required fields, including schedule days combination and start/end times.';
    } else {
        try {
            // Check for duplicate section (excluding current)
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM class_sections WHERE course_id = ? AND section_number = ? AND semester = ? AND academic_year = ? AND section_id != ?");
            $stmt->execute([$course_id, $section_number, $semester, $academic_year, $section_id]);
            if ($stmt->fetchColumn() > 0) {
                $error = 'A section with this number already exists for this course, semester, and year.';
            } else {
                // Ensure max_enrollment is not less than current enrollment
                if ($max_enrollment < $section['current_enrollment']) {
                    $error = 'Max enrollment cannot be less than current enrollment (' . $section['current_enrollment'] . ').';
                } else {
                    // For backward compatibility, use first schedule's time as default start_time and end_time
                    $default_start = '';
                    $default_end = '';
                    if (!empty($schedule_data)) {
                        $first_schedule = reset($schedule_data);
                        $default_start = $first_schedule['start'];
                        $default_end = $first_schedule['end'];
                    }
                    
                    $stmt = $pdo->prepare("UPDATE class_sections SET course_id = ?, section_number = ?, faculty_id = ?, semester = ?, 
                                          academic_year = ?, schedule_day = ?, start_time = ?, end_time = ?, room_id = ?, 
                                          max_enrollment = ?, waitlist_max = ?, status = ?
                                          WHERE section_id = ?");
                    $stmt->execute([
                        $course_id,
                        $section_number,
                        $faculty_id ?: null,
                        $semester,
                        $academic_year,
                        $schedule_day,
                        $default_start,
                        $default_end,
                        $room_id ?: null,
                        $max_enrollment,
                        $waitlist_max,
                        $status,
                        $section_id
                    ]);
                    
                    // Update status if full
                    $stmt = $pdo->prepare("UPDATE class_sections SET status = 'Full' 
                                          WHERE section_id = ? AND current_enrollment >= max_enrollment AND status != 'Cancelled'");
                    $stmt->execute([$section_id]);
                    
                    $success = true;
                    header('Location: ' . BASE_URL . 'modules/registration/index.php?success=' . urlencode('Section updated successfully.'));
                    exit;
                }
            }
        } catch (PDOException $e) {
            $error = 'Error updating section: ' . $e->getMessage();
        }
    }
}

include __DIR__ . '/../../includes/header.php';
?>

<div class="page-container">
    <div class="page-header">
        <h1><i class="fas fa-edit"></i> Edit Class Section</h1>
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
                        <select id="filter_program_id" name="filter_program_id" class="form-control" onchange="updateMajors(); updateCourses();">
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
                            <select id="filter_major_id" name="filter_major_id" class="form-control" onchange="updateCourses();">
                                <option value="">All Majors</option>
                                <?php foreach ($majors as $major): ?>
                                    <option value="<?php echo $major['major_id']; ?>" 
                                            data-prog-id="<?php echo $major['program_id'] ?? ''; ?>"
                                            <?php echo ($major_filter == $major['major_id']) ? 'selected' : ''; ?>>
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
                        <select id="course_id" name="course_id" required onchange="updateFacultyByCourse()">
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
                                        <?php echo ($section['course_id'] == $course['course_id']) ? 'selected' : ''; ?>>
                                    <?php echo sanitizeOutput($course['course_name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="form-group required">
                        <label for="section_number">Section Number</label>
                        <input type="text" id="section_number" name="section_number" 
                               value="<?php echo sanitizeOutput($section['section_number']); ?>" 
                               placeholder="e.g., A, B, 1, 2" required>
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
                                    <?php echo ($section['faculty_id'] == $fac['faculty_id']) ? 'selected' : ''; ?>>
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
                            <option value="First" <?php echo $section['semester'] === 'First' ? 'selected' : ''; ?>>First</option>
                            <option value="2nd" <?php echo $section['semester'] === '2nd' ? 'selected' : ''; ?>>2nd</option>
                            <option value="Summer" <?php echo $section['semester'] === 'Summer' ? 'selected' : ''; ?>>Summer</option>
                        </select>
                    </div>
                    
                    <div class="form-group required">
                        <label for="academic_year">Academic Year</label>
                        <input type="text" id="academic_year" name="academic_year" 
                               value="<?php echo sanitizeOutput($section['academic_year']); ?>" 
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
                    
                    // Parse existing schedule_day to determine selected combination
                    $schedule_day_value = $section['schedule_day'] ?? null;
                    $selected_combination = '';
                    
                    // Get times from database (initialize with database values first)
                    // Improved time extraction to handle all TIME format variations
                    $schedule_start_time = '';
                    $schedule_end_time = '';
                    
                    // Helper function to extract time in HH:MM format
                    $extractTime = function($time_value) {
                        if (empty($time_value)) {
                            return '';
                        }
                        
                        // Handle string format (HH:MM:SS or HH:MM)
                        if (is_string($time_value)) {
                            $time_str = trim($time_value);
                            // Remove any whitespace
                            $time_str = preg_replace('/\s+/', '', $time_str);
                            
                            // Match HH:MM or HH:MM:SS format
                            if (preg_match('/^(\d{1,2}):(\d{2})(?::(\d{2}))?$/', $time_str, $matches)) {
                                $hour = str_pad($matches[1], 2, '0', STR_PAD_LEFT);
                                $minute = str_pad($matches[2], 2, '0', STR_PAD_LEFT);
                                return $hour . ':' . $minute;
                            }
                            // If already in correct format, return as is
                            if (strlen($time_str) >= 5 && substr($time_str, 2, 1) === ':') {
                                return substr($time_str, 0, 5);
                            }
                            return $time_str;
                        }
                        
                        // Handle DateTime objects
                        if (is_object($time_value)) {
                            if (method_exists($time_value, 'format')) {
                                return $time_value->format('H:i');
                            }
                            // Handle DateTimeInterface (check if class exists first)
                            if (interface_exists('DateTimeInterface') && $time_value instanceof DateTimeInterface) {
                                return $time_value->format('H:i');
                            }
                        }
                        
                        return '';
                    };
                    
                    // Extract start time
                    if (isset($section['start_time'])) {
                        $schedule_start_time = $extractTime($section['start_time']);
                    }
                    
                    // Extract end time
                    if (isset($section['end_time'])) {
                        $schedule_end_time = $extractTime($section['end_time']);
                    }
                    
                    // Try to determine the selected combination from schedule_day
                    // Improved detection with fallback logic
                    if (isset($_POST['schedule_day_combination'])) {
                        // Use POST value (form error scenario)
                        $selected_combination = sanitizeInput($_POST['schedule_day_combination']);
                    } elseif (!empty($schedule_day_value)) {
                        $trimmed_value = trim($schedule_day_value);
                        
                        // First, try to decode as JSON (backward compatibility with old format)
                        $decoded = json_decode($trimmed_value, true);
                        if (json_last_error() === JSON_ERROR_NONE && is_array($decoded) && !empty($decoded)) {
                            // Extract days from JSON and find matching combination
                            $existing_days = array_keys($decoded);
                            sort($existing_days); // Sort for consistent comparison
                            
                            // Try to find a matching combination
                            foreach ($day_combinations as $combo_label => $combo_days) {
                                $combo_days_sorted = $combo_days;
                                sort($combo_days_sorted); // Sort for consistent comparison
                                
                                if (count($existing_days) == count($combo_days_sorted)) {
                                    $match = true;
                                    foreach ($combo_days_sorted as $day) {
                                        if (!in_array($day, $existing_days)) {
                                            $match = false;
                                            break;
                                        }
                                    }
                                    if ($match) {
                                        $selected_combination = $combo_label;
                                        // Get times from JSON (override database times with JSON times if available)
                                        $first_day = reset($decoded);
                                        if (is_array($first_day) && isset($first_day['start']) && isset($first_day['end'])) {
                                            $extracted_start = $extractTime($first_day['start']);
                                            $extracted_end = $extractTime($first_day['end']);
                                            if (!empty($extracted_start)) {
                                                $schedule_start_time = $extracted_start;
                                            }
                                            if (!empty($extracted_end)) {
                                                $schedule_end_time = $extracted_end;
                                            }
                                        }
                                        break;
                                    }
                                }
                            }
                        } else {
                            // Check if it's a combination code directly (MW, TTH, F, S)
                            if (isset($day_combinations[$trimmed_value])) {
                                $selected_combination = $trimmed_value;
                            } else {
                                // Might be a full day name (old ENUM format like "Monday", "Friday")
                                // Map single days to combination codes
                                $day_to_combo = [
                                    'Monday' => 'MW',
                                    'Tuesday' => 'TTH',
                                    'Wednesday' => 'MW',
                                    'Thursday' => 'TTH',
                                    'Friday' => 'F',
                                    'Saturday' => 'S'
                                ];
                                
                                // Check if it's a single day that matches our combinations
                                if (isset($day_to_combo[$trimmed_value])) {
                                    $potential_combo = $day_to_combo[$trimmed_value];
                                    // For single days, map directly (F, S) or guess (MW, TTH)
                                    if ($potential_combo === 'F' || $potential_combo === 'S') {
                                        $selected_combination = $potential_combo;
                                    } else {
                                        // For MW or TTH, guess based on the day
                                        if ($trimmed_value === 'Monday' || $trimmed_value === 'Wednesday') {
                                            $selected_combination = 'MW';
                                        } elseif ($trimmed_value === 'Tuesday' || $trimmed_value === 'Thursday') {
                                            $selected_combination = 'TTH';
                                        }
                                    }
                                }
                            }
                        }
                    }
                    
                    // Fallback: If times exist but combination isn't detected, still show times
                    // This ensures times are visible even if day combination detection fails
                    if (empty($selected_combination) && (!empty($schedule_start_time) || !empty($schedule_end_time))) {
                        // Try to infer combination from times existing
                        // At minimum, show the time container so user can see/edit times
                    }
                    
                    // Use POST times if available (form error scenario), otherwise use database times (already set above)
                    if (isset($_POST['schedule_start_time']) && !empty($_POST['schedule_start_time'])) {
                        $schedule_start_time = sanitizeOutput($_POST['schedule_start_time']);
                    }
                    if (isset($_POST['schedule_end_time']) && !empty($_POST['schedule_end_time'])) {
                        $schedule_end_time = sanitizeOutput($_POST['schedule_end_time']);
                    }
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
                    
                    <?php 
                    // Show time container if day combination is selected OR if times exist in database
                    // This ensures times are visible even if day combination detection fails
                    $show_time_container = !empty($selected_combination) || !empty($schedule_start_time) || !empty($schedule_end_time);
                    ?>
                    <div id="schedule-times-container" style="margin-top: 20px; padding: 15px; background: #f9f9f9; border-radius: 4px; border: 1px solid #ddd; <?php echo $show_time_container ? 'display: block;' : 'display: none;'; ?>">
                        <div style="display: flex; gap: 20px; align-items: flex-end;">
                            <div style="flex: 1;">
                                <label for="schedule_start_time" style="font-size: 0.9em; color: #666; font-weight: 500; display: block; margin-bottom: 5px;">
                                    Start Time <span style="color: red;">*</span>
                                </label>
                                <input type="time" id="schedule_start_time" name="schedule_start_time" 
                                       value="<?php echo htmlspecialchars($schedule_start_time); ?>" 
                                       class="form-control"
                                       style="min-width: 150px;"
                                       <?php echo $show_time_container ? 'required' : 'disabled'; ?>>
                            </div>
                            <div style="flex: 1;">
                                <label for="schedule_end_time" style="font-size: 0.9em; color: #666; font-weight: 500; display: block; margin-bottom: 5px;">
                                    End Time <span style="color: red;">*</span>
                                </label>
                                <input type="time" id="schedule_end_time" name="schedule_end_time" 
                                       value="<?php echo htmlspecialchars($schedule_end_time); ?>" 
                                       class="form-control"
                                       style="min-width: 150px;"
                                       <?php echo $show_time_container ? 'required' : 'disabled'; ?>>
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
                                <option value="<?php echo $room['room_id']; ?>" <?php echo ($section['room_id'] == $room['room_id']) ? 'selected' : ''; ?>>
                                    <?php echo sanitizeOutput($room['room_number'] . ($room['building_name'] ? ' (' . $room['building_name'] . ')' : '')); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="form-group required">
                        <label for="max_enrollment">Max Enrollment</label>
                        <input type="number" id="max_enrollment" name="max_enrollment" 
                               value="<?php echo $section['max_enrollment']; ?>" 
                               min="<?php echo $section['current_enrollment']; ?>" required>
                        <small class="text-muted">Current enrollment: <?php echo $section['current_enrollment']; ?></small>
                    </div>
                    
                    <div class="form-group">
                        <label for="waitlist_max">Waitlist Max</label>
                        <input type="number" id="waitlist_max" name="waitlist_max" 
                               value="<?php echo $section['waitlist_max']; ?>" 
                               min="0">
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="status">Status</label>
                    <select id="status" name="status">
                        <option value="Open" <?php echo ($section['status'] === 'Open') ? 'selected' : ''; ?>>Open</option>
                        <option value="Full" <?php echo ($section['status'] === 'Full') ? 'selected' : ''; ?>>Full</option>
                        <option value="Closed" <?php echo ($section['status'] === 'Closed') ? 'selected' : ''; ?>>Closed</option>
                        <option value="Cancelled" <?php echo ($section['status'] === 'Cancelled') ? 'selected' : ''; ?>>Cancelled</option>
                    </select>
                </div>
            </div>

            <div class="form-actions">
                <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Update Section</button>
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
    
    // Reset and update majors and courses
    updateMajors();
    updateCourses();
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
    
    // Update courses
    updateCourses();
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
            if (course.progIds && course.progIds.length > 0) {
                hasProgram = course.progIds.some(id => String(id) === String(selectedProgId));
            } else if (course.progId) {
                hasProgram = String(course.progId) === String(selectedProgId);
            }
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
}

// Update faculty dropdown based on selected department filter
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
}

// Update faculty dropdown based on selected course's department
function updateFacultyByCourse() {
    const courseSelect = document.getElementById('course_id');
    const facultySelect = document.getElementById('faculty_id');
    
    if (!facultySelect || allFaculty.length === 0) return;
    
    // Get the department of the selected course
    let courseDeptId = '';
    if (courseSelect && courseSelect.value) {
        const selectedOption = courseSelect.options[courseSelect.selectedIndex];
        if (selectedOption) {
            courseDeptId = selectedOption.getAttribute('data-department-id') || '';
        }
    }
    
    const currentValue = facultySelect.value;
    
    // Clear dropdown but preserve optgroups structure
    facultySelect.innerHTML = '<option value="">Select Faculty</option>';
    
    // Filter and add faculty only from the course's department
    let currentDept = '';
    let currentGroup = null;
    let count = 0;
    
    allFaculty.forEach(function(fac) {
        // Only show faculty from the same department as the selected course
        if (courseDeptId && String(fac.deptId) === String(courseDeptId)) {
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
    
    // If no faculty found for this department, show a message
    if (count === 0 && courseDeptId) {
        const option = document.createElement('option');
        option.value = '';
        option.textContent = 'No faculty available in this department';
        option.disabled = true;
        facultySelect.appendChild(option);
    }
}

// Handle schedule day combination dropdown - show/hide time inputs
function updateScheduleTimes() {
    const dayCombinationSelect = document.getElementById('schedule_day_combination');
    const timeContainer = document.getElementById('schedule-times-container');
    const startTimeInput = document.getElementById('schedule_start_time');
    const endTimeInput = document.getElementById('schedule_end_time');
    
    if (dayCombinationSelect && timeContainer && startTimeInput && endTimeInput) {
        const hasSelection = dayCombinationSelect.value && dayCombinationSelect.value !== '';
        
        // Preserve existing values before making changes
        const existingStartTime = startTimeInput.value || '';
        const existingEndTime = endTimeInput.value || '';
        const hasExistingTimes = existingStartTime || existingEndTime;
        
        if (hasSelection) {
            // Show container and enable inputs
            timeContainer.style.display = 'block';
            startTimeInput.disabled = false;
            endTimeInput.disabled = false;
            startTimeInput.required = true;
            endTimeInput.required = true;
            
            // Preserve existing values if they exist, don't clear them
            if (existingStartTime && !startTimeInput.value) {
                startTimeInput.value = existingStartTime;
            }
            if (existingEndTime && !endTimeInput.value) {
                endTimeInput.value = existingEndTime;
            }
        } else {
            // If user explicitly deselects (no selection AND no existing times), hide and clear
            // But if times exist, keep container visible so user can see/edit them
            if (!hasExistingTimes) {
                // Hide container and disable/clear inputs only if no times exist
                timeContainer.style.display = 'none';
                startTimeInput.disabled = true;
                startTimeInput.required = false;
                endTimeInput.disabled = true;
                endTimeInput.required = false;
                startTimeInput.value = '';
                endTimeInput.value = '';
            } else {
                // Keep container visible if times exist, but disable inputs
                timeContainer.style.display = 'block';
                startTimeInput.disabled = true;
                startTimeInput.required = false;
                endTimeInput.disabled = true;
                endTimeInput.required = false;
                // Don't clear values - preserve them
            }
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
                const progIdsArray = progIdsStr ? progIdsStr.split(',').filter(id => id && id.trim() !== '') : [];
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
    
    // Setup schedule day combination handler
    setupScheduleDays();
    
    // Also add direct event listener for immediate feedback
    const dayCombinationSelect = document.getElementById('schedule_day_combination');
    if (dayCombinationSelect) {
        dayCombinationSelect.addEventListener('change', updateScheduleTimes);
    }
    
    // Initialize filters based on pre-selected values
    const deptSelect = document.getElementById('filter_department_id');
    const programSelect = document.getElementById('filter_program_id');
    const majorSelect = document.getElementById('filter_major_id');
    
    // If department is pre-selected, update programs
    if (deptSelect && deptSelect.value) {
        updatePrograms();
    }
    // If program is pre-selected (but department might not be), update majors
    if (programSelect && programSelect.value) {
        updateMajors();
    }
    // If major is pre-selected, update courses
    if (majorSelect && majorSelect.value) {
        updateCourses();
    }
    // If no filters are set but we have a course selected, update courses
    if (!deptSelect.value && !programSelect.value && !majorSelect.value) {
        updateCourses();
    }
    
    // Initialize faculty filter based on selected course (for edit mode)
    const courseSelect = document.getElementById('course_id');
    if (courseSelect && courseSelect.value) {
        updateFacultyByCourse();
    }
});
</script>

<?php include __DIR__ . '/../../includes/footer.php'; ?>

