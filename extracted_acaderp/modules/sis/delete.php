<?php
/**
 * Student Information System (SIS) - Delete Student
 * Handles student deletion with confirmation
 */

require_once __DIR__ . '/../../config/config.php';
requireAuth([ROLE_ADMIN]);

$pdo = getDBConnection();
$student_id = (int)($_GET['id'] ?? 0);

if ($student_id > 0) {
    // Check if student exists
    $stmt = $pdo->prepare("SELECT student_id, student_number, first_name, last_name FROM students WHERE student_id = ?");
    $stmt->execute([$student_id]);
    $student = $stmt->fetch();
    
    if ($student) {
        // Check if student has enrollments (prevent deletion if enrolled in courses)
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM enrollments WHERE student_id = ?");
        $stmt->execute([$student_id]);
        $enrollment_count = $stmt->fetchColumn();
        
        if ($enrollment_count > 0) {
            header('Location: ' . BASE_URL . 'modules/sis/index.php?error=Cannot delete student with existing enrollments. Please withdraw or delete enrollments first.');
            exit;
        }
        
        // Delete student
        $stmt = $pdo->prepare("DELETE FROM students WHERE student_id = ?");
        if ($stmt->execute([$student_id])) {
            header('Location: ' . BASE_URL . 'modules/sis/index.php?success=Student deleted successfully');
            exit;
        } else {
            header('Location: ' . BASE_URL . 'modules/sis/index.php?error=Failed to delete student');
            exit;
        }
    } else {
        header('Location: ' . BASE_URL . 'modules/sis/index.php?error=Student not found');
        exit;
    }
} else {
    header('Location: ' . BASE_URL . 'modules/sis/index.php?error=Invalid student ID');
    exit;
}
?>
