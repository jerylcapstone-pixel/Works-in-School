<?php
/**
 * User Profile & Password Change Page
 */

require_once __DIR__ . '/../config/config.php';
requireAuth(); // Any authenticated user can access

$page_title = 'My Profile';
$pdo = getDBConnection();
$error = '';
$success = '';

// Get current user info
$stmt = $pdo->prepare("SELECT user_id, username, email, user_role FROM users WHERE user_id = ?");
$stmt->execute([$_SESSION['user_id']]);
$user = $stmt->fetch();

// Get role-specific information
$profile_data = null;
if ($user) {
    if ($user['user_role'] === ROLE_STUDENT) {
        // Get student information
        $stmt = $pdo->prepare("SELECT s.*, p.program_name, p.program_code
                              FROM students s
                              LEFT JOIN programs p ON s.program_id = p.program_id
                              WHERE s.user_id = ?");
        $stmt->execute([$_SESSION['user_id']]);
        $profile_data = $stmt->fetch();
    } elseif ($user['user_role'] === ROLE_FACULTY) {
        // Get faculty information
        $stmt = $pdo->prepare("SELECT f.*, d.department_name, d.department_code
                              FROM faculty f
                              LEFT JOIN departments d ON f.department_id = d.department_id
                              WHERE f.user_id = ?");
        $stmt->execute([$_SESSION['user_id']]);
        $profile_data = $stmt->fetch();
    }
    // Admin doesn't need additional data, just use $user
}

// Handle username update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_username'])) {
    $new_username = sanitizeInput($_POST['new_username'] ?? '');
    
    if (empty($new_username)) {
        $error = 'Username is required.';
    } else {
        // Check if username already exists (excluding current user)
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE username = ? AND user_id != ?");
        $stmt->execute([$new_username, $_SESSION['user_id']]);
        if ($stmt->fetchColumn() > 0) {
            $error = 'Username already exists. Please choose a different username.';
        } else {
            // Update username
            $stmt = $pdo->prepare("UPDATE users SET username = ? WHERE user_id = ?");
            if ($stmt->execute([$new_username, $_SESSION['user_id']])) {
                $success = 'Username updated successfully!';
                // Refresh user data
                $stmt = $pdo->prepare("SELECT user_id, username, email, user_role FROM users WHERE user_id = ?");
                $stmt->execute([$_SESSION['user_id']]);
                $user = $stmt->fetch();
                $_SESSION['username'] = $new_username; // Update session
            } else {
                $error = 'Failed to update username. Please try again.';
            }
        }
    }
}

// Handle password change
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_password'])) {
    $current_password = $_POST['current_password'] ?? '';
    $new_password = $_POST['new_password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    
    if (empty($current_password) || empty($new_password) || empty($confirm_password)) {
        $error = 'All password fields are required.';
    } elseif ($new_password !== $confirm_password) {
        $error = 'New password and confirmation do not match.';
    } elseif (strlen($new_password) < PASSWORD_MIN_LENGTH) {
        $error = 'New password must be at least ' . PASSWORD_MIN_LENGTH . ' characters long.';
    } else {
        // Verify current password
        $stmt = $pdo->prepare("SELECT password_hash FROM users WHERE user_id = ?");
        $stmt->execute([$_SESSION['user_id']]);
        $user_data = $stmt->fetch();
        
        if ($user_data && password_verify($current_password, $user_data['password_hash'])) {
            // Update password
            $new_password_hash = password_hash($new_password, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("UPDATE users SET password_hash = ? WHERE user_id = ?");
            if ($stmt->execute([$new_password_hash, $_SESSION['user_id']])) {
                $success = 'Password changed successfully!';
            } else {
                $error = 'Failed to update password. Please try again.';
            }
        } else {
            $error = 'Current password is incorrect.';
        }
    }
}

include __DIR__ . '/../includes/header.php';
?>

<div class="page-container">
    <div class="page-header">
        <h1><i class="fas fa-user-circle"></i> My Profile</h1>
    </div>

    <?php if ($error): ?>
        <div class="alert alert-error">
            <i class="fas fa-exclamation-circle"></i> <?php echo sanitizeOutput($error); ?>
        </div>
    <?php endif; ?>

    <?php if ($success): ?>
        <div class="alert alert-success">
            <i class="fas fa-check-circle"></i> <?php echo sanitizeOutput($success); ?>
        </div>
    <?php endif; ?>

    <div class="detail-cards">
        <!-- Profile Header Card -->
        <div class="detail-card" style="background: linear-gradient(135deg, #2563eb 0%, #1e40af 100%); color: white;">
            <div style="display: flex; align-items: center; gap: 1.5rem; margin-bottom: 1.5rem;">
                <div style="width: 100px; height: 100px; border-radius: 50%; background: rgba(255, 255, 255, 0.2); display: flex; align-items: center; justify-content: center; border: 3px solid rgba(255, 255, 255, 0.3);">
                    <i class="fas fa-user" style="font-size: 3rem; color: white;"></i>
                </div>
                <div style="flex: 1;">
                    <h3 style="color: white; border-bottom: none; margin: 0; font-size: 1.75rem; padding: 0;">
                        <?php 
                        if ($user['user_role'] === ROLE_STUDENT && $profile_data) {
                            $full_name = ($profile_data['first_name'] ?? '') . ' ' . 
                                        ($profile_data['middle_name'] ? $profile_data['middle_name'] . ' ' : '') . 
                                        ($profile_data['last_name'] ?? '');
                            echo sanitizeOutput(trim($full_name));
                        } elseif ($user['user_role'] === ROLE_FACULTY && $profile_data) {
                            $full_name = ($profile_data['first_name'] ?? '') . ' ' . 
                                        ($profile_data['middle_name'] ? $profile_data['middle_name'] . ' ' : '') . 
                                        ($profile_data['last_name'] ?? '');
                            if (!empty($profile_data['suffix'])) {
                                $full_name .= ', ' . $profile_data['suffix'];
                            }
                            echo sanitizeOutput(trim($full_name));
                        } else {
                            echo sanitizeOutput($user['username']);
                        }
                        ?>
                    </h3>
                    <p style="margin: 0.5rem 0 0 0; color: rgba(255, 255, 255, 0.9); font-size: 1rem;">
                        <span class="badge badge-primary" style="background: rgba(255, 255, 255, 0.2); color: white; border: 1px solid rgba(255, 255, 255, 0.3);">
                            <?php echo ucfirst($user['user_role']); ?>
                        </span>
                    </p>
                </div>
            </div>
            <div class="detail-grid">
                <div class="detail-item">
                    <label style="color: rgba(255, 255, 255, 0.9);">Email</label>
                    <span style="color: white; font-size: 1rem;">
                        <i class="fas fa-envelope" style="margin-right: 0.5rem;"></i>
                        <?php echo sanitizeOutput($user['email']); ?>
                    </span>
                </div>
                <div class="detail-item">
                    <label style="color: rgba(255, 255, 255, 0.9);">Username</label>
                    <span style="color: white; font-size: 1rem;">
                        <i class="fas fa-user" style="margin-right: 0.5rem;"></i>
                        <?php echo sanitizeOutput($user['username']); ?>
                    </span>
                </div>
            </div>
        </div>

        <!-- Profile Information -->
        <div class="detail-card">
            <h3><i class="fas fa-id-card"></i> Profile Information</h3>
            <div class="detail-grid">
                <?php if ($user['user_role'] === ROLE_STUDENT && $profile_data): ?>
                    <div class="detail-item">
                        <label>Student Number</label>
                        <span><strong><?php echo sanitizeOutput($profile_data['student_number'] ?? 'N/A'); ?></strong></span>
                    </div>
                    <div class="detail-item">
                        <label>Full Name</label>
                        <span><?php 
                            $full_name = ($profile_data['first_name'] ?? '') . ' ' . 
                                        ($profile_data['middle_name'] ? $profile_data['middle_name'] . ' ' : '') . 
                                        ($profile_data['last_name'] ?? '');
                            echo sanitizeOutput(trim($full_name)); 
                        ?></span>
                    </div>
                    <div class="detail-item">
                        <label>Program</label>
                        <span><?php echo sanitizeOutput($profile_data['program_name'] ?? 'N/A'); ?></span>
                    </div>
                    <div class="detail-item">
                        <label>Email</label>
                        <span><?php echo sanitizeOutput($user['email']); ?></span>
                    </div>
                <?php elseif ($user['user_role'] === ROLE_FACULTY && $profile_data): ?>
                    <div class="detail-item">
                        <label>Employee ID</label>
                        <span><strong><?php echo sanitizeOutput($profile_data['employee_id'] ?? 'N/A'); ?></strong></span>
                    </div>
                    <div class="detail-item">
                        <label>Full Name</label>
                        <span><?php 
                            $full_name = ($profile_data['first_name'] ?? '') . ' ' . 
                                        ($profile_data['middle_name'] ? $profile_data['middle_name'] . ' ' : '') . 
                                        ($profile_data['last_name'] ?? '');
                            if (!empty($profile_data['suffix'])) {
                                $full_name .= ', ' . $profile_data['suffix'];
                            }
                            echo sanitizeOutput(trim($full_name)); 
                        ?></span>
                    </div>
                    <div class="detail-item">
                        <label>Department</label>
                        <span><?php echo sanitizeOutput($profile_data['department_name'] ?? 'N/A'); ?></span>
                    </div>
                    <div class="detail-item">
                        <label>Position</label>
                        <span><?php echo sanitizeOutput($profile_data['position'] ?? 'N/A'); ?></span>
                    </div>
                    <div class="detail-item">
                        <label>Email</label>
                        <span><?php echo sanitizeOutput($user['email']); ?></span>
                    </div>
                <?php else: ?>
                    <div class="detail-item">
                        <label>Username</label>
                        <span><strong><?php echo sanitizeOutput($user['username']); ?></strong></span>
                    </div>
                    <div class="detail-item">
                        <label>Email</label>
                        <span><?php echo sanitizeOutput($user['email']); ?></span>
                    </div>
                    <div class="detail-item">
                        <label>Role</label>
                        <span>
                            <span class="badge badge-info"><?php echo ucfirst($user['user_role']); ?></span>
                        </span>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Edit Username -->
        <div class="form-card">
            <form method="POST" action="" class="form">
                <input type="hidden" name="update_username" value="1">
                
                <div class="form-section">
                    <h3><i class="fas fa-user-edit"></i> Update Username</h3>
                    
                    <div class="form-group">
                        <label>
                            <i class="fas fa-user" style="color: #2563eb;"></i> Current Username
                        </label>
                        <input type="text" value="<?php echo sanitizeOutput($user['username']); ?>" 
                               class="form-control" readonly 
                               style="background-color: #f8fafc; cursor: not-allowed; border: 1px solid #e2e8f0;">
                        <small class="text-muted" style="display: block; margin-top: 0.5rem; color: #64748b;">
                            <i class="fas fa-info-circle"></i> This is your current username
                        </small>
                    </div>

                    <div class="form-group">
                        <label>
                            <i class="fas fa-user-plus" style="color: #2563eb;"></i> New Username <span class="required">*</span>
                        </label>
                        <input type="text" name="new_username" required class="form-control" 
                               placeholder="Enter new username" 
                               value="<?php echo sanitizeOutput($_POST['new_username'] ?? ''); ?>"
                               style="font-size: 1rem; padding: 0.75rem;">
                        <small class="text-muted" style="display: block; margin-top: 0.5rem; color: #64748b;">
                            <i class="fas fa-lightbulb"></i> Username must be unique and will be used for login
                        </small>
                    </div>

                    <div class="form-actions" style="margin-top: 1.5rem; padding-top: 1.5rem; border-top: 2px solid #e2e8f0;">
                        <button type="submit" class="btn btn-primary" style="min-width: 180px;">
                            <i class="fas fa-save"></i> Update Username
                        </button>
                    </div>
                </div>
            </form>
        </div>

        <!-- Change Password -->
        <div class="form-card">
            <form method="POST" action="" class="form">
                <input type="hidden" name="change_password" value="1">
                
                <div class="form-section">
                    <h3><i class="fas fa-key"></i> Change Password</h3>
                    
                    <div class="form-group">
                        <label>
                            <i class="fas fa-lock" style="color: #2563eb;"></i> Current Password <span class="required">*</span>
                        </label>
                        <input type="password" name="current_password" required class="form-control" 
                               placeholder="Enter your current password" 
                               autocomplete="current-password"
                               style="font-size: 1rem; padding: 0.75rem;">
                        <small class="text-muted" style="display: block; margin-top: 0.5rem; color: #64748b;">
                            <i class="fas fa-shield-alt"></i> Required to verify your identity
                        </small>
                    </div>

                    <div class="form-group">
                        <label>
                            <i class="fas fa-key" style="color: #10b981;"></i> New Password <span class="required">*</span>
                        </label>
                        <input type="password" name="new_password" required class="form-control" 
                               placeholder="Minimum <?php echo PASSWORD_MIN_LENGTH; ?> characters" 
                               minlength="<?php echo PASSWORD_MIN_LENGTH; ?>"
                               autocomplete="new-password"
                               style="font-size: 1rem; padding: 0.75rem;">
                        <small class="text-muted" style="display: block; margin-top: 0.5rem; color: #64748b;">
                            <i class="fas fa-info-circle"></i> Password must be at least <?php echo PASSWORD_MIN_LENGTH; ?> characters long
                        </small>
                    </div>

                    <div class="form-group">
                        <label>
                            <i class="fas fa-check-double" style="color: #f59e0b;"></i> Confirm New Password <span class="required">*</span>
                        </label>
                        <input type="password" name="confirm_password" required class="form-control" 
                               placeholder="Re-enter your new password"
                               autocomplete="new-password"
                               style="font-size: 1rem; padding: 0.75rem;">
                        <small class="text-muted" style="display: block; margin-top: 0.5rem; color: #64748b;">
                            <i class="fas fa-check-circle"></i> Must match your new password
                        </small>
                    </div>

                    <div style="background: #eff6ff; padding: 1rem; border-radius: 8px; border-left: 4px solid #2563eb; margin-top: 1rem;">
                        <p style="margin: 0; color: #1e40af; font-size: 0.9rem; line-height: 1.6;">
                            <i class="fas fa-shield-alt"></i> 
                            <strong>Security Tip:</strong> Use a strong, unique password that you don't use elsewhere. 
                            Consider using a combination of letters, numbers, and special characters.
                        </p>
                    </div>

                    <div class="form-actions" style="margin-top: 1.5rem; padding-top: 1.5rem; border-top: 2px solid #e2e8f0;">
                        <button type="submit" class="btn btn-primary" style="min-width: 180px;">
                            <i class="fas fa-key"></i> Change Password
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>

