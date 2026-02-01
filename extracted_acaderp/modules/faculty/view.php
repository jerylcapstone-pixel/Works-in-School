<?php
/**
 * Faculty & Staff Management - View Faculty
 * Displays detailed information for a specific faculty member
 */

require_once __DIR__ . '/../../config/config.php';
requireAuth(ROLE_ADMIN);

$page_title = 'View Faculty Member';

$faculty_id = (int)($_GET['id'] ?? 0);
if (!$faculty_id) {
    header('Location: ' . BASE_URL . 'modules/faculty/index.php?error=Invalid faculty ID');
    exit;
}

$pdo = getDBConnection();
$stmt = $pdo->prepare("SELECT f.*, d.department_name, d.department_code,
                       u.user_id, u.username, u.email as user_email, u.status as user_status, u.last_login
                       FROM faculty f
                       LEFT JOIN departments d ON f.department_id = d.department_id
                       LEFT JOIN users u ON f.user_id = u.user_id
                       WHERE f.faculty_id = ?");
$stmt->execute([$faculty_id]);
$faculty = $stmt->fetch();

if (!$faculty) {
    header('Location: ' . BASE_URL . 'modules/faculty/index.php?error=Faculty member not found');
    exit;
}

include __DIR__ . '/../../includes/header.php';
?>

<div class="page-container">
    <div class="page-header">
        <h1><i class="fas fa-chalkboard-teacher"></i> Faculty Member Details</h1>
        <div class="header-actions">
            <a href="<?php echo BASE_URL; ?>modules/faculty/edit.php?id=<?php echo $faculty_id; ?>" class="btn btn-warning">
                <i class="fas fa-edit"></i> Edit
            </a>
            <a href="<?php echo BASE_URL; ?>modules/faculty/index.php" class="btn btn-secondary">
                <i class="fas fa-arrow-left"></i> Back to List
            </a>
        </div>
    </div>

    <div class="detail-cards">
        <!-- Photo Card -->
        <?php if (!empty($faculty['photo_path'])): ?>
        <div class="detail-card" style="text-align: center;">
            <img src="<?php echo BASE_URL . $faculty['photo_path']; ?>" alt="Faculty Photo" 
                 style="max-width: 250px; max-height: 250px; border-radius: 12px; box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);">
        </div>
        <?php endif; ?>

        <!-- Personal Information -->
        <div class="detail-card">
            <h3><i class="fas fa-id-card"></i> Personal Information</h3>
            <div class="detail-grid">
                <div class="detail-item">
                    <label>Employee ID:</label>
                    <span><strong><?php echo sanitizeOutput($faculty['employee_id']); ?></strong></span>
                </div>
                <div class="detail-item">
                    <label>Full Name:</label>
                    <span><?php 
                        $full_name = $faculty['first_name'];
                        if (!empty($faculty['middle_name'])) {
                            $full_name .= ' ' . $faculty['middle_name'];
                        }
                        $full_name .= ' ' . $faculty['last_name'];
                        if (!empty($faculty['suffix'])) {
                            $full_name .= ', ' . $faculty['suffix'];
                        }
                        echo sanitizeOutput($full_name);
                    ?></span>
                </div>
                <div class="detail-item">
                    <label>Date of Birth:</label>
                    <span><?php echo $faculty['date_of_birth'] ? date('F d, Y', strtotime($faculty['date_of_birth'])) : 'N/A'; ?></span>
                </div>
                <div class="detail-item">
                    <label>Gender:</label>
                    <span><?php echo sanitizeOutput($faculty['gender'] ?? 'N/A'); ?></span>
                </div>
                <div class="detail-item">
                    <label>Status:</label>
                    <span class="badge badge-<?php
                        echo $faculty['status'] === 'Active' ? 'success' :
                            ($faculty['status'] === 'Retired' ? 'info' : 'warning');
                    ?>"><?php echo sanitizeOutput($faculty['status']); ?></span>
                </div>
            </div>
        </div>

        <!-- Contact Information -->
        <div class="detail-card">
            <h3><i class="fas fa-phone"></i> Contact Information</h3>
            <div class="detail-grid">
                <div class="detail-item">
                    <label>Email:</label>
                    <span><?php echo sanitizeOutput($faculty['email'] ?? 'N/A'); ?></span>
                </div>
                <div class="detail-item">
                    <label>Phone:</label>
                    <span><?php echo sanitizeOutput($faculty['phone'] ?? 'N/A'); ?></span>
                </div>
            </div>
        </div>

        <!-- Address Information -->
        <div class="detail-card">
            <h3><i class="fas fa-map-marker-alt"></i> Address Information</h3>
            <div class="detail-grid">
                <div class="detail-item">
                    <label>Barangay:</label>
                    <span><?php echo sanitizeOutput($faculty['barangay'] ?? 'N/A'); ?></span>
                </div>
                <div class="detail-item">
                    <label>Purok/Street:</label>
                    <span><?php echo sanitizeOutput($faculty['purok_street'] ?? 'N/A'); ?></span>
                </div>
                <div class="detail-item">
                    <label>Municipality:</label>
                    <span><?php echo sanitizeOutput($faculty['municipality'] ?? 'N/A'); ?></span>
                </div>
                <div class="detail-item">
                    <label>Province:</label>
                    <span><?php echo sanitizeOutput($faculty['province'] ?? 'N/A'); ?></span>
                </div>
                <div class="detail-item">
                    <label>Zip Code:</label>
                    <span><?php echo sanitizeOutput($faculty['zipcode'] ?? 'N/A'); ?></span>
                </div>
            </div>
        </div>

        <!-- Professional Information -->
        <div class="detail-card">
            <h3><i class="fas fa-briefcase"></i> Professional Information</h3>
            <div class="detail-grid">
                <div class="detail-item">
                    <label>Department:</label>
                    <span><?php echo sanitizeOutput($faculty['department_name'] ?? 'N/A'); ?></span>
                </div>
                <div class="detail-item">
                    <label>Position:</label>
                    <span><?php echo sanitizeOutput($faculty['position'] ?? 'N/A'); ?></span>
                </div>
                <div class="detail-item">
                    <label>Hire Date:</label>
                    <span><?php echo date('F d, Y', strtotime($faculty['hire_date'])); ?></span>
                </div>
                <div class="detail-item full-width">
                    <label>Qualification:</label>
                    <span><?php echo sanitizeOutput($faculty['qualification'] ?? 'N/A'); ?></span>
                </div>
                <div class="detail-item full-width">
                    <label>Specialization:</label>
                    <span><?php echo sanitizeOutput($faculty['specialization'] ?? 'N/A'); ?></span>
                </div>
            </div>
        </div>

        <!-- User Account Information -->
        <div class="detail-card">
            <h3><i class="fas fa-user-circle"></i> User Account Information</h3>
            <?php if ($faculty['user_id']): ?>
                <div class="detail-grid">
                    <div class="detail-item">
                        <label>Account Status:</label>
                        <span class="badge badge-success">Active</span>
                    </div>
                    <div class="detail-item">
                        <label>Username:</label>
                        <span><strong><?php echo sanitizeOutput($faculty['username']); ?></strong></span>
                    </div>
                    <div class="detail-item">
                        <label>Login Email:</label>
                        <span><?php echo sanitizeOutput($faculty['user_email']); ?></span>
                    </div>
                    <div class="detail-item">
                        <label>User Status:</label>
                        <span class="badge badge-<?php echo $faculty['user_status'] === 'active' ? 'success' : 'warning'; ?>">
                            <?php echo sanitizeOutput(ucfirst($faculty['user_status'])); ?>
                        </span>
                    </div>
                    <div class="detail-item">
                        <label>Last Login:</label>
                        <span><?php echo $faculty['last_login'] ? date('F d, Y g:i A', strtotime($faculty['last_login'])) : 'Never'; ?></span>
                    </div>
                </div>
            <?php else: ?>
                <div class="alert alert-warning">
                    <i class="fas fa-exclamation-triangle"></i> This faculty member does not have a user account.
                    <a href="<?php echo BASE_URL; ?>modules/faculty/edit.php?id=<?php echo $faculty_id; ?>" style="margin-left: 10px; color: #f59e0b; font-weight: 600;">Create account now</a>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
