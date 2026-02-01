<?php
/**
 * Delete Question
 * Handles deletion of questions
 */

require_once __DIR__ . '/../../config/config.php';
requireAuth([ROLE_ADMIN, ROLE_FACULTY]);

$pdo = getDBConnection();
$question_id = (int)($_GET['id'] ?? 0);
$bank_id = isset($_GET['bank_id']) ? (int)$_GET['bank_id'] : null;

if (!$question_id) {
    header('Location: ' . BASE_URL . 'modules/examinations/question_banks.php?error=Invalid question ID');
    exit;
}

// Check if question exists and user has permission
$stmt = $pdo->prepare("SELECT * FROM questions WHERE question_id = ?");
$stmt->execute([$question_id]);
$question = $stmt->fetch();

if (!$question) {
    header('Location: ' . BASE_URL . 'modules/examinations/question_banks.php?error=Question not found');
    exit;
}

// Check permissions (faculty can only delete their own questions)
if (hasRole(ROLE_FACULTY) && !hasRole(ROLE_ADMIN)) {
    if ($question['created_by'] != $_SESSION['user_id']) {
        header('Location: ' . BASE_URL . 'modules/examinations/question_banks.php?error=Access denied');
        exit;
    }
}

// Delete question (cascade will handle related records)
$stmt = $pdo->prepare("DELETE FROM questions WHERE question_id = ?");
if ($stmt->execute([$question_id])) {
    if ($bank_id) {
        header('Location: ' . BASE_URL . 'modules/examinations/questions.php?bank_id=' . $bank_id . '&success=Question deleted successfully');
    } else {
        header('Location: ' . BASE_URL . 'modules/examinations/question_banks.php?success=Question deleted successfully');
    }
    exit;
} else {
    if ($bank_id) {
        header('Location: ' . BASE_URL . 'modules/examinations/questions.php?bank_id=' . $bank_id . '&error=Failed to delete question');
    } else {
        header('Location: ' . BASE_URL . 'modules/examinations/question_banks.php?error=Failed to delete question');
    }
    exit;
}

