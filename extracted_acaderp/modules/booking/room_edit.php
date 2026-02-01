<?php
/**
 * Room Management - Edit Room
 */
require_once __DIR__ . '/../../config/config.php';
requireAuth(ROLE_ADMIN);
$page_title = 'Edit Room';
$pdo = getDBConnection();
$error = '';
$success = false;

$room_id = (int)($_GET['id'] ?? 0);
if (!$room_id) {
    header('Location: ' . BASE_URL . 'modules/booking/rooms.php?error=Invalid room ID');
    exit;
}

// Get room details
$stmt = $pdo->prepare("SELECT * FROM rooms WHERE room_id = ?");
$stmt->execute([$room_id]);
$room = $stmt->fetch();

if (!$room) {
    header('Location: ' . BASE_URL . 'modules/booking/rooms.php?error=Room not found');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $room_number = sanitizeInput($_POST['room_number'] ?? '');
    $building_name = sanitizeInput($_POST['building_name'] ?? '');
    $room_type = sanitizeInput($_POST['room_type'] ?? 'Classroom');
    $status = sanitizeInput($_POST['status'] ?? 'Available');

    if (empty($room_number)) {
        $error = 'Room number is required.';
    } else {
        try {
            // Check if room number already exists (excluding current room)
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM rooms WHERE room_number = ? AND room_id != ?");
            $stmt->execute([$room_number, $room_id]);
            if ($stmt->fetchColumn() > 0) {
                $error = 'Room number already exists.';
            } else {
                $stmt = $pdo->prepare("UPDATE rooms SET room_number = ?, building_name = ?, room_type = ?, status = ? WHERE room_id = ?");
                if ($stmt->execute([$room_number, $building_name ?: null, $room_type, $status, $room_id])) {
                    header('Location: ' . BASE_URL . 'modules/booking/rooms.php?success=Room updated successfully');
                    exit;
                } else {
                    $error = 'Error updating room.';
                }
            }
        } catch (PDOException $e) {
            $error = 'Error updating room: ' . $e->getMessage();
        }
    }
}

include __DIR__ . '/../../includes/header.php';
?>

<div class="page-container">
    <div class="page-header">
        <h1><i class="fas fa-door-open"></i> Edit Room</h1>
        <a href="<?php echo BASE_URL; ?>modules/booking/rooms.php" class="btn btn-secondary">
            <i class="fas fa-arrow-left"></i> Back
        </a>
    </div>

    <?php if ($error): ?>
        <div class="alert alert-error"><?php echo sanitizeOutput($error); ?></div>
    <?php endif; ?>

    <div class="card">
        <form method="POST" action="" class="form">
            <div class="form-section">
                <h3><i class="fas fa-info-circle"></i> Room Information</h3>
                
                <div class="form-row">
                    <div class="form-group required">
                        <label for="room_number">Room Number</label>
                        <input type="text" id="room_number" name="room_number" 
                               value="<?php echo sanitizeOutput($room['room_number']); ?>" 
                               placeholder="e.g., 101, A-201" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="building_name">Building Name</label>
                        <input type="text" id="building_name" name="building_name" 
                               value="<?php echo sanitizeOutput($room['building_name'] ?? ''); ?>" 
                               placeholder="e.g., Main Building, Science Hall">
                    </div>
                </div>
                
                <div class="form-group required">
                    <label for="room_type">Room Type</label>
                    <select id="room_type" name="room_type" required>
                        <option value="Classroom" <?php echo $room['room_type'] === 'Classroom' ? 'selected' : ''; ?>>Classroom</option>
                        <option value="Laboratory" <?php echo $room['room_type'] === 'Laboratory' ? 'selected' : ''; ?>>Laboratory</option>
                        <option value="Lecture Hall" <?php echo $room['room_type'] === 'Lecture Hall' ? 'selected' : ''; ?>>Lecture Hall</option>
                        <option value="Computer Lab" <?php echo $room['room_type'] === 'Computer Lab' ? 'selected' : ''; ?>>Computer Lab</option>
                        <option value="Library" <?php echo $room['room_type'] === 'Library' ? 'selected' : ''; ?>>Library</option>
                        <option value="Meeting Room" <?php echo $room['room_type'] === 'Meeting Room' ? 'selected' : ''; ?>>Meeting Room</option>
                        <option value="Other" <?php echo $room['room_type'] === 'Other' ? 'selected' : ''; ?>>Other</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="status">Status</label>
                    <select id="status" name="status">
                        <option value="Available" <?php echo $room['status'] === 'Available' ? 'selected' : ''; ?>>Available</option>
                        <option value="Maintenance" <?php echo $room['status'] === 'Maintenance' ? 'selected' : ''; ?>>Maintenance</option>
                        <option value="Reserved" <?php echo $room['status'] === 'Reserved' ? 'selected' : ''; ?>>Reserved</option>
                        <option value="Closed" <?php echo $room['status'] === 'Closed' ? 'selected' : ''; ?>>Closed</option>
                    </select>
                </div>
            </div>

            <div class="form-actions">
                <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Update Room</button>
                <a href="<?php echo BASE_URL; ?>modules/booking/rooms.php" class="btn btn-secondary">Cancel</a>
            </div>
        </form>
    </div>
</div>

<?php include __DIR__ . '/../../includes/footer.php'; ?>

