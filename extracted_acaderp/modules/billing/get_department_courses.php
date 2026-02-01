<?php
/**
 * AJAX endpoint to get courses by department
 */
require_once __DIR__ . '/../../config/config.php';
requireAuth([ROLE_ADMIN]);

header('Content-Type: application/json');

$department_id = isset($_GET['department_id']) ? (int)$_GET['department_id'] : 0;

if ($department_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid department ID']);
    exit;
}

try {
    $pdo = getDBConnection();
    
    $stmt = $pdo->prepare("SELECT course_id, course_code, course_name, credits
                          FROM courses
                          WHERE department_id = ? AND status = 'Active'
                          ORDER BY course_code");
    $stmt->execute([$department_id]);
    $courses = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'success' => true,
        'courses' => $courses
    ]);
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Error fetching courses: ' . $e->getMessage()
    ]);
}
?>

