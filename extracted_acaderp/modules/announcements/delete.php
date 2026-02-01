<?php
/**
 * Delete Announcement
 * Handles deletion of announcements
 */

require_once __DIR__ . '/../../config/config.php';
requireAuth([ROLE_ADMIN]);

$pdo = getDBConnection();
$announcement_id = (int)($_GET['id'] ?? 0);

if (!$announcement_id) {
    header('Location: ' . BASE_URL . 'modules/announcements/index.php?error=Invalid announcement ID');
    exit;
}

// Check if announcement exists
$stmt = $pdo->prepare("SELECT * FROM announcements WHERE announcement_id = ?");
$stmt->execute([$announcement_id]);
$announcement = $stmt->fetch();

if (!$announcement) {
    header('Location: ' . BASE_URL . 'modules/announcements/index.php?error=Announcement not found');
    exit;
}

// Delete announcement (hard delete)
$stmt = $pdo->prepare("DELETE FROM announcements WHERE announcement_id = ?");
if ($stmt->execute([$announcement_id])) {
    header('Location: ' . BASE_URL . 'modules/announcements/index.php?success=Announcement deleted successfully');
    exit;
} else {
    header('Location: ' . BASE_URL . 'modules/announcements/index.php?error=Failed to delete announcement');
    exit;
}

