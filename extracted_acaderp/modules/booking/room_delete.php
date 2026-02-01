<?php
/**
 * Room Management - Delete Room
 */
require_once __DIR__ . '/../../config/config.php';
requireAuth(ROLE_ADMIN);
$pdo = getDBConnection();

$room_id = (int)($_GET['id'] ?? 0);
if (!$room_id) {
    header('Location: ' . BASE_URL . 'modules/booking/rooms.php?error=Invalid room ID');
    exit;
}

// Check if room is being used in any bookings or sections
try {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM bookings WHERE resource_type = 'Room' AND resource_id = ? AND status NOT IN ('Cancelled', 'Completed')");
    $stmt->execute([$room_id]);
    $bookings_count = $stmt->fetchColumn();
    
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM class_sections WHERE room_id = ?");
    $stmt->execute([$room_id]);
    $sections_count = $stmt->fetchColumn();
    
    if ($bookings_count > 0 || $sections_count > 0) {
        $error_msg = 'Cannot delete room. ';
        if ($bookings_count > 0) {
            $error_msg .= "It has $bookings_count active booking(s). ";
        }
        if ($sections_count > 0) {
            $error_msg .= "It is assigned to $sections_count class section(s).";
        }
        header('Location: ' . BASE_URL . 'modules/booking/rooms.php?error=' . urlencode($error_msg));
        exit;
    }
    
    // Delete the room
    $stmt = $pdo->prepare("DELETE FROM rooms WHERE room_id = ?");
    if ($stmt->execute([$room_id])) {
        header('Location: ' . BASE_URL . 'modules/booking/rooms.php?success=Room deleted successfully');
    } else {
        header('Location: ' . BASE_URL . 'modules/booking/rooms.php?error=Error deleting room');
    }
} catch (PDOException $e) {
    header('Location: ' . BASE_URL . 'modules/booking/rooms.php?error=Error: ' . urlencode($e->getMessage()));
}
exit;

