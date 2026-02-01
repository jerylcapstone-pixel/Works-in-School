<?php
/**
 * Room Management - List Rooms
 */
require_once __DIR__ . '/../../config/config.php';
requireAuth(ROLE_ADMIN);
$page_title = 'Room Management';
$pdo = getDBConnection();

$search = sanitizeInput($_GET['search'] ?? '');

$query = "SELECT room_id, room_number, building_name, room_type, status 
          FROM rooms WHERE 1=1";
$params = [];

if (!empty($search)) {
    $query .= " AND (room_number LIKE ? OR building_name LIKE ? OR room_type LIKE ?)";
    $searchParam = "%$search%";
    $params = array_merge($params, [$searchParam, $searchParam, $searchParam]);
}

$query .= " ORDER BY building_name, room_number";
$stmt = $pdo->prepare($query);
$stmt->execute($params);
$rooms = $stmt->fetchAll();

include __DIR__ . '/../../includes/header.php';
?>

<div class="page-container">
    <div class="page-header">
        <h1><i class="fas fa-door-open"></i> Room Management</h1>
        <div style="display: flex; gap: 10px;">
            <a href="<?php echo BASE_URL; ?>modules/booking/index.php" class="btn btn-secondary">
                <i class="fas fa-arrow-left"></i> Back to Booking
            </a>
            <a href="<?php echo BASE_URL; ?>modules/booking/room_add.php" class="btn btn-primary">
                <i class="fas fa-plus"></i> Add Room
            </a>
        </div>
    </div>

    <div class="card">
        <form method="GET" action="" style="margin-bottom: 1.5rem;">
            <div style="display: flex; gap: 1rem;">
                <input type="text" name="search" placeholder="Search rooms by number, building, or type..." 
                       value="<?php echo sanitizeOutput($search); ?>" class="form-control" style="flex: 1;">
                <button type="submit" class="btn btn-secondary"><i class="fas fa-search"></i> Search</button>
                <a href="?" class="btn btn-outline">Clear</a>
            </div>
        </form>

        <table class="data-table">
            <thead>
                <tr>
                    <th>Room Number</th>
                    <th>Building</th>
                    <th>Type</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($rooms)): ?>
                    <tr><td colspan="5" class="text-center">No rooms found. <a href="<?php echo BASE_URL; ?>modules/booking/room_add.php">Add a room</a> to get started.</td></tr>
                <?php else: ?>
                    <?php foreach ($rooms as $room): ?>
                    <tr>
                        <td><strong><?php echo sanitizeOutput($room['room_number']); ?></strong></td>
                        <td><?php echo sanitizeOutput($room['building_name'] ?? 'N/A'); ?></td>
                        <td><?php echo sanitizeOutput($room['room_type']); ?></td>
                        <td>
                            <span class="badge badge-<?php 
                                echo $room['status'] === 'Available' ? 'success' : 
                                    ($room['status'] === 'Maintenance' ? 'warning' : 'error'); 
                            ?>">
                                <?php echo sanitizeOutput($room['status']); ?>
                            </span>
                        </td>
                        <td class="actions">
                            <a href="<?php echo BASE_URL; ?>modules/booking/room_edit.php?id=<?php echo $room['room_id']; ?>" class="btn btn-sm btn-info">
                                <i class="fas fa-edit"></i> Edit
                            </a>
                            <a href="<?php echo BASE_URL; ?>modules/booking/room_delete.php?id=<?php echo $room['room_id']; ?>" 
                               class="btn btn-sm btn-danger" 
                               onclick="return confirm('Are you sure you want to delete this room?');">
                                <i class="fas fa-trash"></i> Delete
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

