<?php
require_once __DIR__ . '/../../config/config.php';
requireAuth();
$page_title = 'Resource Booking';
$pdo = getDBConnection();

$bookings = [];
// Filter bookings based on role
if (hasRole(ROLE_FACULTY) && !hasRole(ROLE_ADMIN)) {
    // Faculty can only see their own bookings
    $query = "SELECT b.*, r.room_number, r.building_name,
              u.username as booked_by_name
              FROM bookings b
              LEFT JOIN rooms r ON (b.resource_type = 'Room' AND b.resource_id = r.room_id)
              LEFT JOIN users u ON b.booked_by = u.user_id
              WHERE b.resource_type = 'Room' AND b.booked_by = ?
              ORDER BY b.booking_date DESC, b.start_time LIMIT 50";
    $stmt = $pdo->prepare($query);
    $stmt->execute([$_SESSION['user_id']]);
    $bookings = $stmt->fetchAll();
} else {
    // Admin can see all bookings
    $query = "SELECT b.*, r.room_number, r.building_name,
              u.username as booked_by_name
              FROM bookings b
              LEFT JOIN rooms r ON (b.resource_type = 'Room' AND b.resource_id = r.room_id)
              LEFT JOIN users u ON b.booked_by = u.user_id
              WHERE b.resource_type = 'Room'
              ORDER BY b.booking_date DESC, b.start_time LIMIT 50";
    $bookings = $pdo->query($query)->fetchAll();
}

include __DIR__ . '/../../includes/header.php';
?>

<div class="page-container">
    <div class="page-header">
        <h1><i class="fas fa-door-open"></i> Classroom Resource Booking</h1>
        <div style="display: flex; gap: 10px;">
            <?php if (hasRole(ROLE_ADMIN)): ?>
                <a href="<?php echo BASE_URL; ?>modules/booking/rooms.php" class="btn btn-secondary">
                    <i class="fas fa-building"></i> Manage Rooms
                </a>
            <?php endif; ?>
            <a href="<?php echo BASE_URL; ?>modules/booking/add.php" class="btn btn-primary">
                <i class="fas fa-plus"></i> New Booking
            </a>
        </div>
    </div>

    <div class="card">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Resource</th>
                    <th>Date</th>
                    <th>Time</th>
                    <?php if (hasRole(ROLE_ADMIN)): ?>
                        <th>Booked By</th>
                    <?php endif; ?>
                    <th>Purpose</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($bookings)): ?>
                    <tr><td colspan="<?php echo hasRole(ROLE_ADMIN) ? '7' : '6'; ?>" class="text-center">No bookings found.</td></tr>
                <?php else: ?>
                    <?php foreach ($bookings as $book): ?>
                    <tr>
                        <td><strong><?php echo sanitizeOutput($book['room_number'] ?? 'N/A'); ?></strong><br>
                            <small><?php echo sanitizeOutput($book['building_name'] ?? ''); ?></small></td>
                        <td><?php echo date('M d, Y', strtotime($book['booking_date'])); ?></td>
                        <td><?php echo date('g:i A', strtotime($book['start_time'])) . ' - ' . date('g:i A', strtotime($book['end_time'])); ?></td>
                        <?php if (hasRole(ROLE_ADMIN)): ?>
                            <td><?php echo sanitizeOutput($book['booked_by_name'] ?? 'N/A'); ?></td>
                        <?php endif; ?>
                        <td><?php echo sanitizeOutput(substr($book['purpose'] ?? 'N/A', 0, 50)); ?></td>
                        <td><span class="badge badge-<?php echo ($book['status'] ?? '') === 'Confirmed' ? 'success' : 'warning'; ?>">
                            <?php echo sanitizeOutput($book['status'] ?? 'Pending'); ?>
                        </span></td>
                        <td class="actions">
                            <a href="<?php echo BASE_URL; ?>modules/booking/view.php?id=<?php echo $book['booking_id']; ?>" class="btn btn-sm btn-info">
                                <i class="fas fa-eye"></i>
                            </a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
