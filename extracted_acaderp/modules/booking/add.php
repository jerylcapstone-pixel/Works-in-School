<?php
/**
 * Resource Booking - Add New Booking
 */
require_once __DIR__ . '/../../config/config.php';
requireAuth();
$page_title = 'New Booking';
$pdo = getDBConnection();
$error = '';
$success = false;

// Get available rooms
$rooms = [];
try {
    $rooms = $pdo->query("SELECT room_id, room_number, building_name, room_type, status 
                         FROM rooms 
                         WHERE status IN ('Available', 'Reserved')
                         ORDER BY building_name, room_number")->fetchAll();
} catch (PDOException $e) {
    $error = 'Error loading rooms: ' . $e->getMessage();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $resource_type = 'Room'; // Currently only supporting room bookings
    $resource_id = !empty($_POST['room_id']) ? (int)$_POST['room_id'] : null;
    $booking_date = $_POST['booking_date'] ?? '';
    $start_time = $_POST['start_time'] ?? '';
    $end_time = $_POST['end_time'] ?? '';
    $purpose = sanitizeInput($_POST['purpose'] ?? '');
    $status = sanitizeInput($_POST['status'] ?? 'Pending');
    
    // Validation
    if (empty($resource_id) || empty($booking_date) || empty($start_time) || empty($end_time)) {
        $error = 'Please fill in all required fields.';
    } elseif ($start_time >= $end_time) {
        $error = 'End time must be after start time.';
    } elseif (strtotime($booking_date) < strtotime('today')) {
        $error = 'Booking date cannot be in the past.';
    } else {
        try {
            $pdo->beginTransaction();
            
            // Check for conflicting bookings
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM bookings 
                                  WHERE resource_type = ? AND resource_id = ? 
                                  AND booking_date = ? 
                                  AND status NOT IN ('Cancelled', 'Completed')
                                  AND (
                                      (start_time <= ? AND end_time > ?) OR
                                      (start_time < ? AND end_time >= ?) OR
                                      (start_time >= ? AND end_time <= ?)
                                  )");
            $stmt->execute([
                $resource_type,
                $resource_id,
                $booking_date,
                $start_time, $start_time,
                $end_time, $end_time,
                $start_time, $end_time
            ]);
            
            if ($stmt->fetchColumn() > 0) {
                $error = 'This room is already booked for the selected date and time. Please choose a different time slot.';
                $pdo->rollBack();
            } else {
                // Check if room is available
                $stmt = $pdo->prepare("SELECT status FROM rooms WHERE room_id = ?");
                $stmt->execute([$resource_id]);
                $room = $stmt->fetch();
                
                if (!$room) {
                    $error = 'Selected room not found.';
                    $pdo->rollBack();
                } elseif ($room['status'] === 'Closed' || $room['status'] === 'Maintenance') {
                    $error = 'This room is currently not available for booking.';
                    $pdo->rollBack();
                } else {
                    // Create booking
                    $stmt = $pdo->prepare("INSERT INTO bookings (resource_type, resource_id, booked_by, booking_date, 
                                          start_time, end_time, purpose, status)
                                          VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
                    $stmt->execute([
                        $resource_type,
                        $resource_id,
                        $_SESSION['user_id'],
                        $booking_date,
                        $start_time,
                        $end_time,
                        $purpose ?: null,
                        $status
                    ]);
                    
                    // Auto-approve if admin
                    if (hasRole(ROLE_ADMIN) && $status === 'Pending') {
                        $booking_id = $pdo->lastInsertId();
                        $stmt = $pdo->prepare("UPDATE bookings SET status = 'Confirmed', approved_by = ?, approved_at = NOW() 
                                              WHERE booking_id = ?");
                        $stmt->execute([$_SESSION['user_id'], $booking_id]);
                    }
                    
                    $pdo->commit();
                    $success = true;
                    header('Location: ' . BASE_URL . 'modules/booking/index.php?success=' . urlencode('Booking created successfully.'));
                    exit;
                }
            }
        } catch (PDOException $e) {
            $pdo->rollBack();
            $error = 'Error creating booking: ' . $e->getMessage();
        }
    }
}

include __DIR__ . '/../../includes/header.php';
?>

<div class="page-container">
    <div class="page-header">
        <h1><i class="fas fa-plus"></i> New Booking</h1>
        <a href="<?php echo BASE_URL; ?>modules/booking/index.php" class="btn btn-secondary">
            <i class="fas fa-arrow-left"></i> Back
        </a>
    </div>

    <?php if ($error): ?>
        <div class="alert alert-error"><?php echo sanitizeOutput($error); ?></div>
    <?php endif; ?>

    <div class="card">
        <form method="POST" action="" class="form">
            <div class="form-section">
                <h3><i class="fas fa-door-open"></i> Room Selection</h3>
                
                <div class="form-group required">
                    <label for="room_id">Room</label>
                    <select id="room_id" name="room_id" required>
                        <option value="">Select Room</option>
                        <?php foreach ($rooms as $room): ?>
                            <option value="<?php echo $room['room_id']; ?>" 
                                    <?php echo (isset($_POST['room_id']) && $_POST['room_id'] == $room['room_id']) ? 'selected' : ''; ?>>
                                <?php 
                                $room_display = $room['room_number'];
                                if ($room['building_name']) {
                                    $room_display .= ' (' . $room['building_name'] . ')';
                                }
                                $room_display .= ' - ' . $room['room_type'];
                                if ($room['capacity']) {
                                    $room_display .= ' (Capacity: ' . $room['capacity'] . ')';
                                }
                                echo sanitizeOutput($room_display);
                                ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <?php if (empty($rooms)): ?>
                        <small class="text-muted">No rooms available. Please add rooms first.</small>
                    <?php endif; ?>
                </div>
            </div>

            <div class="form-section">
                <h3><i class="fas fa-calendar"></i> Date & Time</h3>
                
                <div class="form-row">
                    <div class="form-group required">
                        <label for="booking_date">Booking Date</label>
                        <input type="date" id="booking_date" name="booking_date" 
                               value="<?php echo sanitizeOutput($_POST['booking_date'] ?? ''); ?>" 
                               min="<?php echo date('Y-m-d'); ?>" required>
                    </div>
                    
                    <div class="form-group required">
                        <label for="start_time">Start Time</label>
                        <input type="time" id="start_time" name="start_time" 
                               value="<?php echo sanitizeOutput($_POST['start_time'] ?? ''); ?>" required>
                    </div>
                    
                    <div class="form-group required">
                        <label for="end_time">End Time</label>
                        <input type="time" id="end_time" name="end_time" 
                               value="<?php echo sanitizeOutput($_POST['end_time'] ?? ''); ?>" required>
                    </div>
                </div>
            </div>

            <div class="form-section">
                <h3><i class="fas fa-info-circle"></i> Booking Details</h3>
                
                <div class="form-group">
                    <label for="purpose">Purpose</label>
                    <textarea id="purpose" name="purpose" class="form-control" rows="4" 
                              placeholder="Describe the purpose of this booking..."><?php echo sanitizeOutput($_POST['purpose'] ?? ''); ?></textarea>
                </div>
                
                <?php if (hasRole(ROLE_ADMIN)): ?>
                    <div class="form-group">
                        <label for="status">Status</label>
                        <select id="status" name="status">
                            <option value="Pending" <?php echo (isset($_POST['status']) && $_POST['status'] === 'Pending') ? 'selected' : ''; ?>>Pending</option>
                            <option value="Confirmed" <?php echo (isset($_POST['status']) && $_POST['status'] === 'Confirmed') ? 'selected' : ''; ?>>Confirmed</option>
                        </select>
                        <small class="text-muted">Admin can set status directly. Regular users will have status set to Pending.</small>
                    </div>
                <?php else: ?>
                    <input type="hidden" name="status" value="Pending">
                <?php endif; ?>
            </div>

            <div class="info-box" style="background: #e3f2fd; padding: 15px; border-left: 4px solid #2196F3; margin: 20px 0;">
                <h4 style="margin-top: 0;"><i class="fas fa-info-circle"></i> Booking Information:</h4>
                <ul style="margin: 10px 0; padding-left: 20px;">
                    <li>Bookings are automatically checked for conflicts</li>
                    <li>Only available rooms can be booked</li>
                    <li>Booking date cannot be in the past</li>
                    <li>End time must be after start time</li>
                    <?php if (!hasRole(ROLE_ADMIN)): ?>
                        <li>Your booking will be set to "Pending" status and require approval</li>
                    <?php else: ?>
                        <li>As an admin, you can directly confirm bookings</li>
                    <?php endif; ?>
                </ul>
            </div>

            <div class="form-actions">
                <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Create Booking</button>
                <a href="<?php echo BASE_URL; ?>modules/booking/index.php" class="btn btn-secondary">Cancel</a>
            </div>
        </form>
    </div>
</div>

<script>
// Set minimum date to today
document.addEventListener('DOMContentLoaded', function() {
    const bookingDate = document.getElementById('booking_date');
    if (!bookingDate.value) {
        bookingDate.value = '<?php echo date('Y-m-d'); ?>';
    }
    
    // Validate end time is after start time
    const startTime = document.getElementById('start_time');
    const endTime = document.getElementById('end_time');
    
    function validateTimes() {
        if (startTime.value && endTime.value) {
            if (startTime.value >= endTime.value) {
                endTime.setCustomValidity('End time must be after start time');
            } else {
                endTime.setCustomValidity('');
            }
        }
    }
    
    startTime.addEventListener('change', validateTimes);
    endTime.addEventListener('change', validateTimes);
});
</script>

<?php include __DIR__ . '/../../includes/footer.php'; ?>

