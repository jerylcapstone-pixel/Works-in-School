<?php
/**
 * Delete Examination
 * Handles deletion of examinations
 */

require_once __DIR__ . '/../../config/config.php';
requireAuth([ROLE_ADMIN, ROLE_FACULTY]);

$pdo = getDBConnection();
$exam_id = (int)($_GET['id'] ?? 0);

if (!$exam_id) {
    header('Location: ' . BASE_URL . 'modules/examinations/index.php?error=Invalid exam ID');
    exit;
}

// Check if exam exists and user has permission
$stmt = $pdo->prepare("SELECT e.*, cs.faculty_id 
                      FROM examinations e
                      LEFT JOIN class_sections cs ON e.section_id = cs.section_id
                      WHERE e.exam_id = ?");
$stmt->execute([$exam_id]);
$exam = $stmt->fetch();

if (!$exam) {
    header('Location: ' . BASE_URL . 'modules/examinations/index.php?error=Exam not found');
    exit;
}

// Check permissions (faculty can only delete their own exams)
if (hasRole(ROLE_FACULTY) && !hasRole(ROLE_ADMIN)) {
    $faculty_id = null;
    $stmt = $pdo->prepare("SELECT faculty_id FROM faculty WHERE user_id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $faculty = $stmt->fetch();
    if ($faculty) {
        $faculty_id = $faculty['faculty_id'];
    }
    
    if ($exam['created_by'] != $_SESSION['user_id'] && 
        ($faculty_id === null || $exam['faculty_id'] != $faculty_id)) {
        header('Location: ' . BASE_URL . 'modules/examinations/index.php?error=Access denied');
        exit;
    }
}

// Delete exam (cascade will handle related records)
$stmt = $pdo->prepare("DELETE FROM examinations WHERE exam_id = ?");
if ($stmt->execute([$exam_id])) {
    header('Location: ' . BASE_URL . 'modules/examinations/index.php?success=Examination deleted successfully');
    exit;
} else {
    header('Location: ' . BASE_URL . 'modules/examinations/index.php?error=Failed to delete examination');
    exit;
}

