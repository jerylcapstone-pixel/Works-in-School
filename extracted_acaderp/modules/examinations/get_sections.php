<?php
/**
 * AJAX endpoint to get sections by course_id
 */
// Suppress any output before JSON
ob_start();

require_once __DIR__ . '/../../config/config.php';
requireAuth([ROLE_ADMIN, ROLE_FACULTY]);

// Clear any output that might have been generated (warnings, notices, etc.)
ob_clean();

// Set JSON header
header('Content-Type: application/json; charset=utf-8');

$course_id = isset($_GET['course_id']) ? (int)$_GET['course_id'] : 0;

if ($course_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid course ID', 'sections' => []]);
    exit;
}

try {
    $pdo = getDBConnection();
    
    // Get sections for the selected course
    if (hasRole(ROLE_FACULTY) && !hasRole(ROLE_ADMIN)) {
        // Faculty can only see sections they are assigned to teach
        $faculty_id = null;
        $stmt = $pdo->prepare("SELECT faculty_id FROM faculty WHERE user_id = ?");
        $stmt->execute([$_SESSION['user_id']]);
        $faculty = $stmt->fetch();
        if ($faculty) {
            $faculty_id = $faculty['faculty_id'];
            
            // Only show sections where:
            // 1. Course matches the selected course_id
            // 2. Faculty is assigned to teach the section
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
                                  WHERE cs.course_id = ? 
                                  AND cs.status = 'Open'
                                  AND c.status = 'Active'
                                  AND cs.faculty_id = ?
                                  GROUP BY cs.section_id, cs.section_number, cs.course_id, c.course_name, 
                                           c.course_code, c.department_id, cs.semester, cs.academic_year
                                  ORDER BY cs.academic_year DESC, cs.semester, cs.section_number");
            $stmt->execute([$course_id, $faculty_id]);
            $sections = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } else {
            $sections = [];
        }
    } else {
        // Admin can see all sections
        $stmt = $pdo->prepare("SELECT cs.section_id, cs.section_number, cs.course_id, c.course_name, c.course_code, cs.semester, cs.academic_year
                              FROM class_sections cs
                              LEFT JOIN courses c ON cs.course_id = c.course_id
                              WHERE cs.course_id = ? AND cs.status = 'Open'
                              ORDER BY cs.academic_year DESC, cs.semester, cs.section_number");
        $stmt->execute([$course_id]);
        $sections = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    // Format sections for display
    $formatted_sections = [];
    $is_faculty = hasRole(ROLE_FACULTY) && !hasRole(ROLE_ADMIN);
    foreach ($sections as $section) {
        $sectionText = '';
        if (!empty($section['course_code'])) {
            $sectionText .= $section['course_code'];
        }
        if (!empty($section['course_name'])) {
            $sectionText .= ' - ' . $section['course_name'];
        }
        $sectionText .= ' - Section ' . $section['section_number'];
        if (!empty($section['semester']) && !empty($section['academic_year'])) {
            $sectionText .= ' (' . $section['semester'] . ' ' . $section['academic_year'] . ')';
        }
        // Show student count for faculty
        if ($is_faculty && isset($section['student_count'])) {
            $sectionText .= ' - ' . $section['student_count'] . ' student(s) enrolled';
        }
        
        $formatted_sections[] = [
            'section_id' => (int)$section['section_id'],
            'text' => $sectionText,
            'section_number' => $section['section_number'],
            'semester' => $section['semester'] ?? '',
            'academic_year' => $section['academic_year'] ?? '',
            'student_count' => isset($section['student_count']) ? (int)$section['student_count'] : 0
        ];
    }
    
    // Always return success, even if no sections found
    echo json_encode([
        'success' => true,
        'sections' => $formatted_sections,
        'message' => count($formatted_sections) > 0 ? '' : 'No sections available for this course'
    ], JSON_UNESCAPED_UNICODE);
} catch (PDOException $e) {
    error_log('Get sections error: ' . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Database error: ' . $e->getMessage(),
        'sections' => []
    ]);
    exit;
} catch (Exception $e) {
    error_log('Get sections error: ' . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Error fetching sections: ' . $e->getMessage(),
        'sections' => []
    ]);
    exit;
}
?>

