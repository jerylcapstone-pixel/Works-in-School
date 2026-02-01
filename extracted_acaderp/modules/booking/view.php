<?php
/**
 * Resource Booking - View Booking Details
 */
require_once __DIR__ . '/../../config/config.php';
requireAuth();
$page_title = 'Booking Details';
$pdo = getDBConnection();

$booking_id = (int)($_GET['id'] ?? 0);
if (!$booking_id) {
    header('Location: ' . BASE_URL . 'modules/booking/index.php?error=Invalid booking ID');
    exit;
}

// Get booking details
try {
    $stmt = $pdo->prepare("SELECT b.*, r.room_number, r.building_name, r.room_type,
                          u.username as booked_by_name,
                          a.username as approved_by_name
                          FROM bookings b
                          LEFT JOIN rooms r ON (b.resource_type = 'Room' AND b.resource_id = r.room_id)
                          LEFT JOIN users u ON b.booked_by = u.user_id
                          LEFT JOIN users a ON b.approved_by = a.user_id
                          WHERE b.booking_id = ?");
    $stmt->execute([$booking_id]);
    $booking = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$booking) {
        $error = 'Booking not found.';
    } else {
        // Try to get full names from students or faculty tables if available
        if ($booking['booked_by']) {
            // Check if user is a student
            $stmt = $pdo->prepare("SELECT first_name, last_name FROM students WHERE user_id = ?");
            $stmt->execute([$booking['booked_by']]);
            $student = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($student) {
                $booking['booked_by_first'] = $student['first_name'];
                $booking['booked_by_last'] = $student['last_name'];
            } else {
                // Check if user is faculty
                $stmt = $pdo->prepare("SELECT first_name, last_name FROM faculty WHERE user_id = ?");
                $stmt->execute([$booking['booked_by']]);
                $faculty = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($faculty) {
                    $booking['booked_by_first'] = $faculty['first_name'];
                    $booking['booked_by_last'] = $faculty['last_name'];
                }
            }
        }
        
        // Same for approved_by
        if ($booking['approved_by']) {
            $stmt = $pdo->prepare("SELECT first_name, last_name FROM students WHERE user_id = ?");
            $stmt->execute([$booking['approved_by']]);
            $student = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($student) {
                $booking['approved_by_first'] = $student['first_name'];
                $booking['approved_by_last'] = $student['last_name'];
            } else {
                $stmt = $pdo->prepare("SELECT first_name, last_name FROM faculty WHERE user_id = ?");
                $stmt->execute([$booking['approved_by']]);
                $faculty = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($faculty) {
                    $booking['approved_by_first'] = $faculty['first_name'];
                    $booking['approved_by_last'] = $faculty['last_name'];
                }
            }
        }
    }
} catch (PDOException $e) {
    $error = 'Error loading booking: ' . $e->getMessage();
    error_log('Booking view error: ' . $e->getMessage());
    $booking = null;
}

// Check if user has permission (admin or the person who booked it)
if ($booking && !hasRole(ROLE_ADMIN) && $_SESSION['user_id'] != $booking['booked_by']) {
    $error = 'Access denied. You do not have permission to view this booking.';
    $booking = null;
}

if (!isset($error)) {
    $error = '';
}
$success = '';

// Handle status update (admin only or booking owner for cancellation)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_status'])) {
    $new_status = sanitizeInput($_POST['status'] ?? '');
    
    if (empty($new_status)) {
        $error = 'Please select a status.';
    } else {
        try {
            if (hasRole(ROLE_ADMIN)) {
                // Admin can update to any status
                $stmt = $pdo->prepare("UPDATE bookings SET status = ?, 
                                      approved_by = ?, approved_at = NOW() 
                                      WHERE booking_id = ?");
                $stmt->execute([$new_status, ($new_status === 'Confirmed' ? $_SESSION['user_id'] : null), $booking_id]);
            } elseif ($new_status === 'Cancelled' && $_SESSION['user_id'] == $booking['booked_by']) {
                // User can only cancel their own booking
                $stmt = $pdo->prepare("UPDATE bookings SET status = 'Cancelled' WHERE booking_id = ?");
                $stmt->execute([$booking_id]);
            } else {
                $error = 'You do not have permission to perform this action.';
            }
            
            if (!$error) {
                $success = 'Booking status updated successfully.';
                header('Location: ' . BASE_URL . 'modules/booking/view.php?id=' . $booking_id . '&success=' . urlencode($success));
                exit;
            }
        } catch (PDOException $e) {
            $error = 'Error updating booking: ' . $e->getMessage();
        }
    }
}

include __DIR__ . '/../../includes/header.php';
?>

<style>
.booking-header-card {
    background: linear-gradient(135deg, #1e40af 0%, #2563eb 100%);
    border-radius: 20px;
    padding: 2rem;
    color: white;
    margin-bottom: 2rem;
    box-shadow: 0 8px 24px rgba(30, 64, 175, 0.3);
}

.booking-header-title {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 1.5rem;
}

.booking-header-title-left {
    display: flex;
    align-items: center;
    gap: 1rem;
}

.booking-header-title i {
    font-size: 2.5rem;
    color: #ffd700;
}

.booking-header-title h1 {
    margin: 0;
    font-size: 2rem;
    font-weight: 700;
}

.booking-status-badge {
    background: rgba(255, 255, 255, 0.2);
    backdrop-filter: blur(10px);
    border-radius: 12px;
    padding: 0.75rem 1.5rem;
    border: 2px solid rgba(255, 255, 255, 0.3);
    font-weight: 600;
    font-size: 1rem;
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.booking-info-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 1.5rem;
    margin-top: 1.5rem;
}

.booking-info-item {
    background: rgba(255, 255, 255, 0.15);
    backdrop-filter: blur(10px);
    border-radius: 12px;
    padding: 1rem 1.25rem;
    border: 2px solid rgba(255, 255, 255, 0.3);
}

.booking-info-item label {
    display: block;
    font-size: 0.75rem;
    opacity: 0.9;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    margin-bottom: 0.5rem;
}

.booking-info-item .value {
    font-size: 1.1rem;
    font-weight: 600;
}

.time-display {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    font-size: 1.25rem;
    font-weight: 700;
}

.time-display i {
    color: #ffd700;
}

.purpose-card {
    background: white;
    border: 2px solid #e2e8f0;
    border-radius: 16px;
    padding: 2rem;
    margin-bottom: 2rem;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
}

.purpose-card h3 {
    margin: 0 0 1rem 0;
    color: #1e40af;
    font-size: 1.25rem;
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.purpose-content {
    padding: 1.5rem;
    background: #f8fafc;
    border-radius: 12px;
    border: 2px solid #e2e8f0;
    color: #1e293b;
    line-height: 1.7;
}

.status-update-card {
    background: white;
    border: 2px solid #e2e8f0;
    border-radius: 16px;
    padding: 2rem;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
}

.status-update-card h3 {
    margin: 0 0 1.5rem 0;
    color: #1e40af;
    font-size: 1.25rem;
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.status-select {
    padding: 0.875rem 1rem;
    border: 2px solid #e2e8f0;
    border-radius: 8px;
    font-size: 1rem;
    font-weight: 500;
    background: white;
    color: #1e293b;
    cursor: pointer;
    transition: all 0.3s;
    width: 100%;
    max-width: 300px;
}

.status-select:focus {
    outline: none;
    border-color: #2563eb;
    box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1);
}
</style>

<div class="page-container">
    <div class="page-header">
        <h1><i class="fas fa-calendar-check"></i> Booking Details</h1>
        <a href="<?php echo BASE_URL; ?>modules/booking/index.php" class="btn btn-secondary">
            <i class="fas fa-arrow-left"></i> Back
        </a>
    </div>

    <?php if ($error): ?>
        <div class="alert alert-error">
            <i class="fas fa-exclamation-circle"></i> <?php echo sanitizeOutput($error); ?>
        </div>
        <div style="text-align: center; padding: 3rem;">
            <a href="<?php echo BASE_URL; ?>modules/booking/index.php" class="btn btn-primary">
                <i class="fas fa-arrow-left"></i> Back to Bookings
            </a>
        </div>
    <?php elseif (!$booking): ?>
        <div class="alert alert-error">
            <i class="fas fa-exclamation-circle"></i> Booking not found or you do not have access to view it.
        </div>
        <div style="text-align: center; padding: 3rem;">
            <a href="<?php echo BASE_URL; ?>modules/booking/index.php" class="btn btn-primary">
                <i class="fas fa-arrow-left"></i> Back to Bookings
            </a>
        </div>
    <?php else: ?>
    
    <?php if ($success || isset($_GET['success'])): ?>
        <div class="alert alert-success">
            <i class="fas fa-check-circle"></i> <?php echo sanitizeOutput($success ?: $_GET['success']); ?>
        </div>
    <?php endif; ?>

    <div class="booking-header-card">
        <div class="booking-header-title">
            <div class="booking-header-title-left">
                <i class="fas fa-door-open"></i>
                <h1><?php echo sanitizeOutput($booking['room_number'] ?? 'Room Booking'); ?></h1>
            </div>
            <div class="booking-status-badge">
                <i class="fas fa-<?php 
                    echo $booking['status'] === 'Confirmed' ? 'check-circle' : 
                        ($booking['status'] === 'Cancelled' ? 'times-circle' : 
                        ($booking['status'] === 'Completed' ? 'check-double' : 'clock')); 
                ?>"></i>
                <span><?php echo sanitizeOutput($booking['status']); ?></span>
            </div>
        </div>
        
        <div class="booking-info-grid">
            <div class="booking-info-item">
                <label><i class="fas fa-building"></i> Building</label>
                <div class="value"><?php echo sanitizeOutput($booking['building_name'] ?? 'N/A'); ?></div>
            </div>
            <div class="booking-info-item">
                <label><i class="fas fa-door-open"></i> Room Number</label>
                <div class="value"><?php echo sanitizeOutput($booking['room_number'] ?? 'N/A'); ?></div>
            </div>
            <div class="booking-info-item">
                <label><i class="fas fa-tag"></i> Room Type</label>
                <div class="value"><?php echo sanitizeOutput($booking['room_type'] ?? 'N/A'); ?></div>
            </div>
            <div class="booking-info-item">
                <label><i class="fas fa-calendar-alt"></i> Booking Date</label>
                <div class="value"><?php echo date('F d, Y', strtotime($booking['booking_date'])); ?></div>
            </div>
            <div class="booking-info-item">
                <label><i class="fas fa-clock"></i> Time Slot</label>
                <div class="time-display">
                    <i class="fas fa-play-circle"></i>
                    <span><?php echo date('g:i A', strtotime($booking['start_time'])); ?></span>
                    <i class="fas fa-arrow-right" style="font-size: 0.875rem; opacity: 0.8;"></i>
                    <i class="fas fa-stop-circle"></i>
                    <span><?php echo date('g:i A', strtotime($booking['end_time'])); ?></span>
                </div>
            </div>
            <div class="booking-info-item">
                <label><i class="fas fa-hourglass-half"></i> Duration</label>
                <div class="value">
                    <?php 
                    $start = strtotime($booking['start_time']);
                    $end = strtotime($booking['end_time']);
                    $hours = floor(($end - $start) / 3600);
                    $minutes = (($end - $start) % 3600) / 60;
                    echo $hours . 'h ' . ($minutes > 0 ? $minutes . 'm' : '');
                    ?>
                </div>
            </div>
            <?php if (hasRole(ROLE_ADMIN)): ?>
            <div class="booking-info-item">
                <label><i class="fas fa-user"></i> Booked By</label>
                <div class="value">
                    <?php 
                    echo sanitizeOutput(trim(($booking['booked_by_first'] ?? '') . ' ' . ($booking['booked_by_last'] ?? '')));
                    if ($booking['booked_by_name']): ?>
                        <span style="opacity: 0.8; font-size: 0.875rem; display: block; margin-top: 0.25rem;">
                            (@<?php echo sanitizeOutput($booking['booked_by_name']); ?>)
                        </span>
                    <?php endif; ?>
                </div>
            </div>
            <?php if ($booking['approved_by']): ?>
            <div class="booking-info-item">
                <label><i class="fas fa-check-circle"></i> Approved By</label>
                <div class="value">
                    <?php 
                    echo sanitizeOutput(trim(($booking['approved_by_first'] ?? '') . ' ' . ($booking['approved_by_last'] ?? '')));
                    if ($booking['approved_by_name']): ?>
                        <span style="opacity: 0.8; font-size: 0.875rem; display: block; margin-top: 0.25rem;">
                            (@<?php echo sanitizeOutput($booking['approved_by_name']); ?>)
                        </span>
                    <?php endif; ?>
                </div>
            </div>
            <div class="booking-info-item">
                <label><i class="fas fa-calendar-check"></i> Approved At</label>
                <div class="value"><?php echo date('M d, Y g:i A', strtotime($booking['approved_at'])); ?></div>
            </div>
            <?php endif; ?>
            <?php endif; ?>
            <div class="booking-info-item">
                <label><i class="fas fa-calendar-plus"></i> Created</label>
                <div class="value"><?php echo date('M d, Y g:i A', strtotime($booking['created_at'])); ?></div>
            </div>
        </div>
    </div>

    <?php if ($booking['purpose']): ?>
    <div class="purpose-card">
        <h3>
            <i class="fas fa-file-alt"></i> Booking Purpose
        </h3>
        <div class="purpose-content">
            <?php echo nl2br(sanitizeOutput($booking['purpose'])); ?>
        </div>
    </div>
    <?php endif; ?>

    <?php if (hasRole(ROLE_ADMIN) || ($_SESSION['user_id'] == $booking['booked_by'] && $booking['status'] === 'Pending')): ?>
        <div class="status-update-card">
            <h3>
                <i class="fas fa-edit"></i> 
                <?php echo hasRole(ROLE_ADMIN) ? 'Update Booking Status' : 'Cancel Booking'; ?>
            </h3>
            <form method="POST" action="">
                <div class="form-group">
                    <label for="status" style="display: block; margin-bottom: 0.5rem; font-weight: 600; color: #1e293b;">
                        Status
                    </label>
                    <?php if (hasRole(ROLE_ADMIN)): ?>
                        <select id="status" name="status" class="status-select">
                            <option value="Pending" <?php echo ($booking['status'] === 'Pending') ? 'selected' : ''; ?>>⏳ Pending</option>
                            <option value="Confirmed" <?php echo ($booking['status'] === 'Confirmed') ? 'selected' : ''; ?>>✓ Confirmed</option>
                            <option value="Cancelled" <?php echo ($booking['status'] === 'Cancelled') ? 'selected' : ''; ?>>✗ Cancelled</option>
                            <option value="Completed" <?php echo ($booking['status'] === 'Completed') ? 'selected' : ''; ?>>✓ Completed</option>
                        </select>
                    <?php else: ?>
                        <select id="status" name="status" class="status-select">
                            <option value="Cancelled">✗ Cancel Booking</option>
                        </select>
                    <?php endif; ?>
                </div>
                <div class="form-actions" style="margin-top: 1.5rem;">
                    <button type="submit" name="update_status" class="btn btn-primary btn-lg">
                        <i class="fas fa-save"></i> 
                        <?php echo hasRole(ROLE_ADMIN) ? 'Update Status' : 'Cancel Booking'; ?>
                    </button>
                </div>
            </form>
        </div>
    <?php endif; ?>
    
    <?php endif; // End of booking check ?>
</div>

<?php include __DIR__ . '/../../includes/footer.php'; ?>

