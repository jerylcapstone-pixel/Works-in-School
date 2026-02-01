<?php
/**
 * AJAX endpoint to get student data for invoice generation
 */
require_once __DIR__ . '/../../config/config.php';
requireAuth([ROLE_ADMIN]);
header('Content-Type: application/json');

$pdo = getDBConnection();
$student_id = isset($_GET['student_id']) ? (int)$_GET['student_id'] : 0;
$semester = isset($_GET['semester']) ? sanitizeInput($_GET['semester']) : '';
$academic_year = isset($_GET['academic_year']) ? sanitizeInput($_GET['academic_year']) : '';

if (!$student_id) {
    echo json_encode(['error' => 'Student ID is required']);
    exit;
}

try {
    // Get student info
    $stmt = $pdo->prepare("SELECT s.*, p.program_id, p.program_name, p.duration_years, 
                          d.department_id, d.department_name,
                          YEAR(s.enrollment_date) as enrollment_year
                          FROM students s
                          LEFT JOIN programs p ON s.program_id = p.program_id
                          LEFT JOIN departments d ON p.department_id = d.department_id
                          WHERE s.student_id = ? AND s.status = 'Active'");
    $stmt->execute([$student_id]);
    $student = $stmt->fetch();
    
    if (!$student) {
        echo json_encode(['error' => 'Student not found']);
        exit;
    }
    
    // Calculate year level based on enrollment date, academic year, and program duration
    $year_level = null;
    if ($student['enrollment_date'] && $student['duration_years'] && $academic_year) {
        // Parse academic year (e.g., "2024-25" -> 2024)
        $academic_year_start = null;
        if (preg_match('/^(\d{4})/', $academic_year, $matches)) {
            $academic_year_start = (int)$matches[1];
        }
        
        if ($academic_year_start) {
            $enrollment_date = new DateTime($student['enrollment_date']);
            $enrollment_year = (int)$enrollment_date->format('Y');
            
            // Calculate year level: difference between academic year and enrollment year + 1
            $years_diff = $academic_year_start - $enrollment_year;
            $year_level = max(1, min($years_diff + 1, $student['duration_years']));
        } else {
            // Fallback: use current date
            $enrollment_date = new DateTime($student['enrollment_date']);
            $current_date = new DateTime();
            $years_diff = $current_date->diff($enrollment_date)->y;
            $year_level = min($years_diff + 1, $student['duration_years']);
        }
    } elseif ($student['enrollment_date'] && $student['duration_years']) {
        // Fallback without academic year
        $enrollment_date = new DateTime($student['enrollment_date']);
        $current_date = new DateTime();
        $years_diff = $current_date->diff($enrollment_date)->y;
        $year_level = min($years_diff + 1, $student['duration_years']);
    }
    
    // Get enrolled units for the semester/academic year
    // Separate lecture and lab credits like in generate.php
    $total_units = 0;
    $lecture_units = 0;
    $lab_units = 0;
    $has_lab_courses = false;
    $semester_like = '%' . strtolower($semester) . '%';
    
    // First, try exact match with semester and academic year
    if ($semester && $academic_year) {
        $enrollment_query = "SELECT 
                            SUM(CASE WHEN COALESCE(LOWER(c.course_type), '') LIKE '%lab%' THEN c.credits ELSE 0 END) as lab_credits,
                            SUM(CASE WHEN COALESCE(LOWER(c.course_type), '') NOT LIKE '%lab%' THEN c.credits ELSE 0 END) as lecture_credits,
                            COUNT(CASE WHEN c.course_type IN ('Lab', 'laboratory') OR LOWER(c.course_type) LIKE '%lab%' THEN 1 END) as lab_count
                            FROM enrollments e
                            INNER JOIN class_sections cs ON e.section_id = cs.section_id
                            INNER JOIN courses c ON cs.course_id = c.course_id
                            WHERE e.student_id = ? 
                            AND e.status = 'Enrolled'
                            AND cs.academic_year = ?
                            AND LOWER(cs.semester) LIKE ?";
        $stmt = $pdo->prepare($enrollment_query);
        $stmt->execute([$student_id, $academic_year, $semester_like]);
        $enrollment = $stmt->fetch();
        
        $lecture_units = (float)($enrollment['lecture_credits'] ?? 0);
        $lab_units = (float)($enrollment['lab_credits'] ?? 0);
        $total_units = $lecture_units + $lab_units;
        $has_lab_courses = (int)($enrollment['lab_count'] ?? 0) > 0;
    }
    
    // If no units found with exact match, try without academic year constraint (in case format differs)
    if ($total_units == 0 && $semester) {
        $enrollment_query = "SELECT 
                            SUM(CASE WHEN COALESCE(LOWER(c.course_type), '') LIKE '%lab%' THEN c.credits ELSE 0 END) as lab_credits,
                            SUM(CASE WHEN COALESCE(LOWER(c.course_type), '') NOT LIKE '%lab%' THEN c.credits ELSE 0 END) as lecture_credits,
                            COUNT(CASE WHEN c.course_type IN ('Lab', 'laboratory') OR LOWER(c.course_type) LIKE '%lab%' THEN 1 END) as lab_count,
                            cs.academic_year
                            FROM enrollments e
                            INNER JOIN class_sections cs ON e.section_id = cs.section_id
                            INNER JOIN courses c ON cs.course_id = c.course_id
                            WHERE e.student_id = ? 
                            AND e.status = 'Enrolled'
                            AND cs.semester = ?
                            GROUP BY cs.academic_year
                            ORDER BY cs.academic_year DESC
                            LIMIT 1";
        $stmt = $pdo->prepare($enrollment_query);
        $stmt->execute([$student_id, $semester]);
        $enrollment = $stmt->fetch();
        
        if ($enrollment) {
            $lecture_units = (float)($enrollment['lecture_credits'] ?? 0);
            $lab_units = (float)($enrollment['lab_credits'] ?? 0);
            $total_units = $lecture_units + $lab_units;
            $has_lab_courses = (int)($enrollment['lab_count'] ?? 0) > 0;
        }
    }
    
    // If still no units, try getting most recent enrollments (fallback)
    if ($total_units == 0) {
        // Get the most recent semester/academic year for this student
        $recent_query = "SELECT cs.semester, cs.academic_year
                        FROM enrollments e
                        INNER JOIN class_sections cs ON e.section_id = cs.section_id
                        WHERE e.student_id = ?
                        AND e.status IN ('Enrolled', 'Completed')
                        ORDER BY cs.academic_year DESC, 
                                 CASE cs.semester 
                                     WHEN 'First' THEN 1
                                     WHEN '2nd' THEN 2
                                     WHEN 'Summer' THEN 3
                                     ELSE 4
                                 END DESC
                        LIMIT 1";
        $stmt = $pdo->prepare($recent_query);
        $stmt->execute([$student_id]);
        $recent = $stmt->fetch();
        
        if ($recent) {
            // Use the most recent semester/academic year found
            $enrollment_query = "SELECT 
                                SUM(CASE WHEN COALESCE(LOWER(c.course_type), '') LIKE '%lab%' THEN c.credits ELSE 0 END) as lab_credits,
                                SUM(CASE WHEN COALESCE(LOWER(c.course_type), '') NOT LIKE '%lab%' THEN c.credits ELSE 0 END) as lecture_credits,
                                COUNT(CASE WHEN c.course_type IN ('Lab', 'laboratory') OR LOWER(c.course_type) LIKE '%lab%' THEN 1 END) as lab_count
                                FROM enrollments e
                                INNER JOIN class_sections cs ON e.section_id = cs.section_id
                                INNER JOIN courses c ON cs.course_id = c.course_id
                                WHERE e.student_id = ? 
                                AND e.status IN ('Enrolled', 'Completed')
                                AND cs.semester = ?
                                AND cs.academic_year = ?";
            $stmt = $pdo->prepare($enrollment_query);
            $stmt->execute([$student_id, $recent['semester'], $recent['academic_year']]);
            $enrollment = $stmt->fetch();
            
            if ($enrollment) {
                $lecture_units = (float)($enrollment['lecture_credits'] ?? 0);
                $lab_units = (float)($enrollment['lab_credits'] ?? 0);
                $total_units = $lecture_units + $lab_units;
                $has_lab_courses = (int)($enrollment['lab_count'] ?? 0) > 0;
            }
        }
        
        // Last resort: get all enrollments regardless of semester/year
        if ($total_units == 0) {
            $enrollment_query = "SELECT 
                                SUM(CASE WHEN COALESCE(LOWER(c.course_type), '') LIKE '%lab%' THEN c.credits ELSE 0 END) as lab_credits,
                                SUM(CASE WHEN COALESCE(LOWER(c.course_type), '') NOT LIKE '%lab%' THEN c.credits ELSE 0 END) as lecture_credits,
                                COUNT(CASE WHEN c.course_type IN ('Lab', 'laboratory') OR LOWER(c.course_type) LIKE '%lab%' THEN 1 END) as lab_count
                                FROM enrollments e
                                INNER JOIN class_sections cs ON e.section_id = cs.section_id
                                INNER JOIN courses c ON cs.course_id = c.course_id
                                WHERE e.student_id = ? 
                                AND e.status IN ('Enrolled', 'Completed')";
            $stmt = $pdo->prepare($enrollment_query);
            $stmt->execute([$student_id]);
            $enrollment = $stmt->fetch();
            
            if ($enrollment) {
                $lecture_units = (float)($enrollment['lecture_credits'] ?? 0);
                $lab_units = (float)($enrollment['lab_credits'] ?? 0);
                $total_units = $lecture_units + $lab_units;
                $has_lab_courses = (int)($enrollment['lab_count'] ?? 0) > 0;
            }
        }
    }
    
    // Get tuition rate - support both lecture_rate and laboratory_rate
    $lecture_rate = null;
    $lab_rate = null;
    $rate_category = 'Program';
    
    // Check if lecture_rate and laboratory_rate columns exist
    $has_lecture_lab_rates = false;
    try {
        $check_cols = $pdo->query("SHOW COLUMNS FROM tuition_rates LIKE 'lecture_rate'");
        $has_lecture_lab_rates = $check_cols && $check_cols->rowCount() > 0;
    } catch (PDOException $e) {
        $has_lecture_lab_rates = false;
    }
    
    if ($student['program_id'] && $has_lecture_lab_rates) {
        // Try to get program-specific rate with lecture/lab breakdown
        $stmt = $pdo->prepare("SELECT lecture_rate, laboratory_rate, amount
                              FROM tuition_rates 
                              WHERE program_id = ? 
                              AND status = 'Active'
                              ORDER BY effective_date DESC 
                              LIMIT 1");
        $stmt->execute([$student['program_id']]);
        $rate = $stmt->fetch();
        
        if ($rate) {
            $lecture_rate = isset($rate['lecture_rate']) && $rate['lecture_rate'] !== null 
                ? (float)$rate['lecture_rate'] 
                : null;
            
            // If lecture_rate is null or 0, fallback to amount
            if (($lecture_rate === null || $lecture_rate <= 0) && isset($rate['amount']) && $rate['amount'] > 0) {
                $lecture_rate = (float)$rate['amount'];
            }
            
            $lab_rate = isset($rate['laboratory_rate']) && $rate['laboratory_rate'] !== null 
                ? (float)$rate['laboratory_rate'] 
                : null;
            
            // If lab_rate is null, default to lecture_rate
            if ($lab_rate === null && $lecture_rate !== null) {
                $lab_rate = $lecture_rate;
            }
            
            $rate_category = 'Program';
        }
    } elseif ($student['program_id']) {
        // Fallback to old schema (amount only)
        $stmt = $pdo->prepare("SELECT amount
                              FROM tuition_rates 
                              WHERE program_id = ? 
                              AND status = 'Active'
                              ORDER BY effective_date DESC 
                              LIMIT 1");
        $stmt->execute([$student['program_id']]);
        $rate = $stmt->fetch();
        
        if ($rate && isset($rate['amount'])) {
            $lecture_rate = (float)$rate['amount'];
            $lab_rate = $lecture_rate;
            $rate_category = 'Program';
        }
    }
    
    // If no program rate found, try general rate
    if ($lecture_rate === null && $has_lecture_lab_rates) {
        $stmt = $pdo->query("SELECT lecture_rate, laboratory_rate, amount
                            FROM tuition_rates 
                            WHERE (program_id IS NULL OR program_id = 0)
                            AND status = 'Active'
                            ORDER BY effective_date DESC 
                            LIMIT 1");
        $general_rate = $stmt ? $stmt->fetch() : null;
        
        if ($general_rate) {
            $lecture_rate = isset($general_rate['lecture_rate']) && $general_rate['lecture_rate'] !== null 
                ? (float)$general_rate['lecture_rate'] 
                : null;
            
            if (($lecture_rate === null || $lecture_rate <= 0) && isset($general_rate['amount']) && $general_rate['amount'] > 0) {
                $lecture_rate = (float)$general_rate['amount'];
            }
            
            $lab_rate = isset($general_rate['laboratory_rate']) && $general_rate['laboratory_rate'] !== null 
                ? (float)$general_rate['laboratory_rate'] 
                : null;
            
            if ($lab_rate === null && $lecture_rate !== null) {
                $lab_rate = $lecture_rate;
            }
            
            $rate_category = 'General';
        }
    } elseif ($lecture_rate === null) {
        // Fallback to old schema
        $stmt = $pdo->query("SELECT amount
                            FROM tuition_rates 
                            WHERE (program_id IS NULL OR program_id = 0)
                            AND status = 'Active'
                            ORDER BY effective_date DESC 
                            LIMIT 1");
        $general_rate = $stmt ? $stmt->fetch() : null;
        
        if ($general_rate && isset($general_rate['amount'])) {
            $lecture_rate = (float)$general_rate['amount'];
            $lab_rate = $lecture_rate;
            $rate_category = 'General';
        }
    }
    
    // Get standard fees
    $standard_fees = [];
    try {
        $stmt = $pdo->query("SELECT * FROM standard_fees WHERE is_active = 1 ORDER BY fee_category");
        $standard_fees = $stmt->fetchAll();
    } catch (PDOException $e) {
        // Table doesn't exist
    }
    
    // Calculate tuition amounts
    $lecture_tuition = $lecture_rate && $lecture_units > 0 ? $lecture_rate * $lecture_units : 0;
    $lab_tuition = $lab_rate && $lab_units > 0 ? $lab_rate * $lab_units : 0;
    $tuition_amount = $lecture_tuition + $lab_tuition;
    
    // Build response with detailed tuition information
    $tuition_info = [
        'lecture_rate' => $lecture_rate,
        'laboratory_rate' => $lab_rate,
        'rate_per_unit' => $lecture_rate, // For backwards compatibility
        'rate_category' => $rate_category,
        'lecture_amount' => $lecture_tuition,
        'lab_amount' => $lab_tuition,
        'total_amount' => $tuition_amount,
        'year_level' => $year_level,
        'program_id' => $student['program_id'],
        'semester' => $semester
    ];
    
    // Add debug info if no rate found
    if (!$lecture_rate && !$lab_rate) {
        $tuition_info['warning'] = 'No tuition rate found for this program.';
    }
    
    echo json_encode([
        'success' => true,
        'student' => [
            'student_id' => $student['student_id'],
            'student_number' => $student['student_number'],
            'name' => $student['first_name'] . ' ' . $student['last_name'],
            'program_id' => $student['program_id'],
            'program_name' => $student['program_name'],
            'year_level' => $year_level,
            'duration_years' => $student['duration_years']
        ],
        'enrollment' => [
            'total_units' => $total_units,
            'lecture_units' => $lecture_units,
            'lab_units' => $lab_units,
            'has_lab_courses' => $has_lab_courses
        ],
        'tuition' => $tuition_info,
        'standard_fees' => $standard_fees
    ]);
    
} catch (Exception $e) {
    echo json_encode(['error' => $e->getMessage()]);
}

