<?php
/**
 * Faculty & Staff Management - Edit Faculty
 * Form to edit existing faculty member
 */

require_once __DIR__ . '/../../config/config.php';
requireAuth(ROLE_ADMIN);

$page_title = 'Edit Faculty Member';

$faculty_id = (int)($_GET['id'] ?? 0);
if (!$faculty_id) {
    header('Location: ' . BASE_URL . 'modules/faculty/index.php?error=Invalid faculty ID');
    exit;
}

$pdo = getDBConnection();
$error = '';

// Handle photo upload
function handlePhotoUpload($file) {
    if (!isset($file) || $file['error'] !== UPLOAD_ERR_OK) {
        return null;
    }
    
    $allowed = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif'];
    if (!in_array($file['type'], $allowed)) {
        return false; // Error: invalid file type
    }
    
    $uploadDir = __DIR__ . '/../../uploads/faculty_photos/';
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }
    
    $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
    $filename = 'photo_' . time() . '_' . uniqid() . '.' . $extension;
    $filepath = $uploadDir . $filename;
    
    if (move_uploaded_file($file['tmp_name'], $filepath)) {
        return 'uploads/faculty_photos/' . $filename;
    }
    
    return false;
}

// Get departments for dropdown
$departments = [];
$stmt = $pdo->query("SELECT department_id, department_name, department_code FROM departments ORDER BY department_name");
$departments = $stmt->fetchAll();

// Get faculty data with user account info
$stmt = $pdo->prepare("SELECT f.*, u.user_id as has_user_id, u.username, u.email as user_email, u.status as user_status
                       FROM faculty f
                       LEFT JOIN users u ON f.user_id = u.user_id
                       WHERE f.faculty_id = ?");
$stmt->execute([$faculty_id]);
$faculty = $stmt->fetch();

if (!$faculty) {
    header('Location: ' . BASE_URL . 'modules/faculty/index.php?error=Faculty member not found');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $first_name = sanitizeInput($_POST['first_name'] ?? '');
    $middle_name = sanitizeInput($_POST['middle_name'] ?? '');
    $last_name = sanitizeInput($_POST['last_name'] ?? '');
    $suffix = sanitizeInput($_POST['suffix'] ?? '');
    $date_of_birth = $_POST['date_of_birth'] ?? '';
    $gender = sanitizeInput($_POST['gender'] ?? '');
    $phone = sanitizeInput($_POST['phone'] ?? '');
    $email = sanitizeInput($_POST['email'] ?? '');
    $barangay = sanitizeInput($_POST['barangay'] ?? '');
    $purok_street = sanitizeInput($_POST['purok_street'] ?? '');
    $zipcode = sanitizeInput($_POST['zipcode'] ?? '');
    $province = sanitizeInput($_POST['province'] ?? '');
    $municipality = sanitizeInput($_POST['municipality'] ?? '');
    $hire_date = $_POST['hire_date'] ?? '';
    $department_id = !empty($_POST['department_id']) ? (int)$_POST['department_id'] : null;
    $position = sanitizeInput($_POST['position'] ?? '');
    $qualification = sanitizeInput($_POST['qualification'] ?? '');
    $specialization = sanitizeInput($_POST['specialization'] ?? '');
    $status = sanitizeInput($_POST['status'] ?? 'Active');
    
    // Handle photo upload
    $photo_path = $faculty['photo_path']; // Keep existing photo if no new one uploaded
    if (isset($_FILES['photo']) && $_FILES['photo']['error'] === UPLOAD_ERR_OK) {
        $photo_result = handlePhotoUpload($_FILES['photo']);
        if ($photo_result === false) {
            $error = 'Invalid photo file. Please upload JPG, PNG, or GIF only.';
        } elseif ($photo_result !== null) {
            // Delete old photo if exists
            if (!empty($faculty['photo_path']) && file_exists(__DIR__ . '/../../' . $faculty['photo_path'])) {
                @unlink(__DIR__ . '/../../' . $faculty['photo_path']);
            }
            $photo_path = $photo_result;
        }
    }
    
    // User account management fields
    $manage_user_account = isset($_POST['manage_user_account']) && $_POST['manage_user_account'] === '1';
    $create_new_account = isset($_POST['create_new_account']) && $_POST['create_new_account'] === '1';
    $password = $_POST['password'] ?? '';
    $reset_password = isset($_POST['reset_password']) && $_POST['reset_password'] === '1';

    if (empty($first_name) || empty($last_name) || empty($hire_date)) {
        $error = 'Please fill in all required fields.';
    } elseif ($manage_user_account && $create_new_account && empty($email)) {
        $error = 'Email is required when creating a new user account. Credentials will be sent to the email address.';
    } elseif ($manage_user_account && $reset_password && empty($password)) {
        $error = 'Password is required when resetting password.';
    } else {
            // Start transaction
            $pdo->beginTransaction();
            
            try {
                $current_user_id = $faculty['user_id'];
                
                // Handle user account creation/update
                if ($manage_user_account) {
                    if ($create_new_account && !$current_user_id) {
                        // Create new user account
                        if (empty($email)) {
                            throw new Exception('Email is required when creating a new user account.');
                        }
                        
                        // Check if email is already used
                        $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE email = ?");
                        $stmt->execute([$email]);
                        if ($stmt->fetchColumn() > 0) {
                            throw new Exception('Email already exists. Please use a different email.');
                        }
                        
                        // Auto-generate username from name
                        $base_username = strtolower(preg_replace('/[^a-z0-9]/', '', $first_name . '.' . $last_name));
                        $username = $base_username;
                        $counter = 1;
                        while (true) {
                            $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE username = ?");
                            $stmt->execute([$username]);
                            if ($stmt->fetchColumn() == 0) {
                                break;
                            }
                            $username = $base_username . $counter;
                            $counter++;
                        }
                        
                        // Generate random password
                        $password = bin2hex(random_bytes(6)); // 12 character hex string
                        
                        // Hash password
                        $password_hash = password_hash($password, PASSWORD_DEFAULT);
                        
                        // Create user account
                        $stmt = $pdo->prepare("INSERT INTO users (username, email, password_hash, user_role, status) 
                                              VALUES (?, ?, ?, 'faculty', 'active')");
                        $stmt->execute([$username, $email, $password_hash]);
                        $current_user_id = $pdo->lastInsertId();
                        
                        // Send email with credentials if email is provided
                        if (!empty($email)) {
                            $faculty_name = trim(($first_name ?? '') . ' ' . ($last_name ?? ''));
                            $email_subject = "Welcome to " . APP_NAME . " - Your Faculty Account Has Been Created";
                            $email_message = "
                                <html>
                                <head>
                                    <style>
                                        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                                        .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                                        .header { background-color: #4CAF50; color: white; padding: 20px; text-align: center; }
                                        .content { padding: 20px; background-color: #f9f9f9; }
                                        .credentials { background-color: #fff; padding: 15px; border-left: 4px solid #4CAF50; margin: 20px 0; }
                                        .footer { text-align: center; padding: 20px; color: #666; font-size: 12px; }
                                    </style>
                                </head>
                                <body>
                                    <div class='container'>
                                        <div class='header'>
                                            <h2>Faculty Account Created!</h2>
                                        </div>
                                        <div class='content'>
                                            <p>Dear {$faculty_name},</p>
                                            <p>Your faculty account has been created successfully. You can now access the faculty portal using the following credentials:</p>
                                            
                                            <div class='credentials'>
                                                <strong>Username:</strong> {$username}<br>
                                                <strong>Password:</strong> {$password}
                                            </div>
                                            
                                            <p><strong>Important:</strong> Please change your password after first login for security purposes.</p>
                                            
                                            <p>Login URL: <a href='" . BASE_URL . "auth/login.php'>" . BASE_URL . "auth/login.php</a></p>
                                            
                                            <p>If you have any questions, please contact the administration.</p>
                                            
                                            <p>Best regards,<br>" . APP_NAME . " Administration</p>
                                        </div>
                                        <div class='footer'>
                                            <p>This is an automated email. Please do not reply.</p>
                                        </div>
                                    </div>
                                </body>
                                </html>
                            ";
                            
                            if (sendEmail($email, $email_subject, $email_message)) {
                                // Email sent successfully
                            } else {
                                // Email failed, but don't stop the process
                                error_log("Failed to send email to faculty: " . $email);
                            }
                        }
                    } elseif ($reset_password && $current_user_id) {
                        // Reset password for existing account
                        if (empty($password)) {
                            throw new Exception('Password is required.');
                        }
                        
                        $password_hash = password_hash($password, PASSWORD_DEFAULT);
                        $stmt = $pdo->prepare("UPDATE users SET password_hash = ? WHERE user_id = ?");
                        $stmt->execute([$password_hash, $current_user_id]);
                        
                        // Send email with new password if email is provided
                        if (!empty($email)) {
                            $faculty_name = trim(($first_name ?? '') . ' ' . ($last_name ?? ''));
                            $email_subject = APP_NAME . " - Your Password Has Been Reset";
                            $email_message = "
                                <html>
                                <head>
                                    <style>
                                        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                                        .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                                        .header { background-color: #4CAF50; color: white; padding: 20px; text-align: center; }
                                        .content { padding: 20px; background-color: #f9f9f9; }
                                        .credentials { background-color: #fff; padding: 15px; border-left: 4px solid #4CAF50; margin: 20px 0; }
                                        .footer { text-align: center; padding: 20px; color: #666; font-size: 12px; }
                                    </style>
                                </head>
                                <body>
                                    <div class='container'>
                                        <div class='header'>
                                            <h2>Password Reset</h2>
                                        </div>
                                        <div class='content'>
                                            <p>Dear {$faculty_name},</p>
                                            <p>Your password has been reset. Please use the following new password to log in:</p>
                                            
                                            <div class='credentials'>
                                                <strong>Username:</strong> {$faculty['username']}<br>
                                                <strong>New Password:</strong> {$password}
                                            </div>
                                            
                                            <p><strong>Important:</strong> Please change your password after first login for security purposes.</p>
                                            
                                            <p>Login URL: <a href='" . BASE_URL . "auth/login.php'>" . BASE_URL . "auth/login.php</a></p>
                                            
                                            <p>If you did not request this password reset, please contact the administration immediately.</p>
                                            
                                            <p>Best regards,<br>" . APP_NAME . " Administration</p>
                                        </div>
                                        <div class='footer'>
                                            <p>This is an automated email. Please do not reply.</p>
                                        </div>
                                    </div>
                                </body>
                                </html>
                            ";
                            
                            if (sendEmail($email, $email_subject, $email_message)) {
                                // Email sent successfully
                            } else {
                                // Email failed, but don't stop the process
                                error_log("Failed to send email to faculty: " . $email);
                            }
                        }
                    }
                }
                
                // Update faculty record
                $sql = "UPDATE faculty SET first_name = ?, middle_name = ?, last_name = ?, suffix = ?,
                        date_of_birth = ?, gender = ?, phone = ?, email = ?, photo_path = ?, barangay = ?, purok_street = ?,
                        zipcode = ?, province = ?, municipality = ?, hire_date = ?, department_id = ?, position = ?,
                        qualification = ?, specialization = ?, status = ?, user_id = ?
                        WHERE faculty_id = ?";

                $stmt = $pdo->prepare($sql);
                $result = $stmt->execute([
                    $first_name, $middle_name ?: null, $last_name, $suffix ?: null, $date_of_birth ?: null,
                    $gender ?: null, $phone ?: null, $email ?: null, $photo_path, $barangay ?: null, $purok_street ?: null,
                    $zipcode ?: null, $province ?: null, $municipality ?: null, $hire_date, $department_id, $position ?: null,
                    $qualification ?: null, $specialization ?: null, $status, $current_user_id, $faculty_id
                ]);

                // Commit transaction
                $pdo->commit();
                
                $success_msg = 'Faculty member updated successfully';
                if ($create_new_account) {
                    $success_msg .= '. Login credentials have been automatically generated and sent to their email address.';
                } elseif ($reset_password) {
                    if (!empty($email)) {
                        $success_msg .= '. Password has been reset and sent to their email address.';
                    } else {
                        $success_msg .= '. Password has been reset. New password: ' . htmlspecialchars($password);
                    }
                }
                
                header('Location: ' . BASE_URL . 'modules/faculty/view.php?id=' . $faculty_id . '&success=' . urlencode($success_msg));
                exit;
            } catch (Exception $e) {
                // Rollback transaction on error
                $pdo->rollBack();
                $error = $e->getMessage();
            }
        }
    // Update $faculty with POST data for form display
    $faculty = array_merge($faculty, $_POST);
}

include __DIR__ . '/../../includes/header.php';
?>

<div class="page-container">
    <div class="page-header">
        <h1><i class="fas fa-edit"></i> Edit Faculty Member</h1>
        <a href="<?php echo BASE_URL; ?>modules/faculty/view.php?id=<?php echo $faculty_id; ?>" class="btn btn-secondary">
            <i class="fas fa-arrow-left"></i> Back to View
        </a>
    </div>

    <?php if ($error): ?>
        <div class="alert alert-error"><?php echo sanitizeOutput($error); ?></div>
    <?php endif; ?>

    <div class="card">
        <form method="POST" action="" enctype="multipart/form-data" class="form">
            <h2>Personal Information</h2>
            <div class="form-row">
                <div class="form-group">
                    <label>Employee ID</label>
                    <input type="text" class="form-control" 
                           value="<?php echo sanitizeOutput($faculty['employee_id']); ?>" 
                           readonly style="background-color: #f5f5f5; cursor: not-allowed;">
                    <small class="text-muted">Employee ID is auto-generated and cannot be changed</small>
                </div>
                <div class="form-group">
                    <label>Hire Date <span class="required">*</span></label>
                    <input type="date" name="hire_date" required class="form-control" 
                           value="<?php echo sanitizeOutput($faculty['hire_date']); ?>">
                </div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label>First Name <span class="required">*</span></label>
                    <input type="text" name="first_name" required class="form-control" 
                           value="<?php echo sanitizeOutput($faculty['first_name']); ?>">
                </div>
                <div class="form-group">
                    <label>Middle Name</label>
                    <input type="text" name="middle_name" class="form-control" 
                           value="<?php echo sanitizeOutput($faculty['middle_name'] ?? ''); ?>">
                </div>
                <div class="form-group">
                    <label>Last Name <span class="required">*</span></label>
                    <input type="text" name="last_name" required class="form-control" 
                           value="<?php echo sanitizeOutput($faculty['last_name']); ?>">
                </div>
                <div class="form-group">
                    <label>Suffix</label>
                    <input type="text" name="suffix" class="form-control" 
                           placeholder="e.g., Jr., Sr., II, III, Ph.D."
                           value="<?php echo sanitizeOutput($faculty['suffix'] ?? ''); ?>">
                    <small class="text-muted">Optional: Jr., Sr., II, III, etc.</small>
                </div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label>Date of Birth</label>
                    <input type="date" name="date_of_birth" class="form-control" 
                           value="<?php echo sanitizeOutput($faculty['date_of_birth'] ?? ''); ?>">
                </div>
                <div class="form-group">
                    <label>Gender</label>
                    <select name="gender" class="form-control">
                        <option value="">Select Gender</option>
                        <option value="Male" <?php echo (($faculty['gender'] ?? '') === 'Male') ? 'selected' : ''; ?>>Male</option>
                        <option value="Female" <?php echo (($faculty['gender'] ?? '') === 'Female') ? 'selected' : ''; ?>>Female</option>
                        <option value="Other" <?php echo (($faculty['gender'] ?? '') === 'Other') ? 'selected' : ''; ?>>Other</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Status</label>
                    <select name="status" class="form-control" required>
                        <option value="Active" <?php echo ($faculty['status'] === 'Active') ? 'selected' : ''; ?>>Active</option>
                        <option value="On Leave" <?php echo ($faculty['status'] === 'On Leave') ? 'selected' : ''; ?>>On Leave</option>
                        <option value="Retired" <?php echo ($faculty['status'] === 'Retired') ? 'selected' : ''; ?>>Retired</option>
                        <option value="Terminated" <?php echo ($faculty['status'] === 'Terminated') ? 'selected' : ''; ?>>Terminated</option>
                    </select>
                </div>
            </div>

            <h2>Contact Information</h2>
            <div class="form-row">
                <div class="form-group">
                    <label>Email</label>
                    <input type="email" name="email" class="form-control" 
                           value="<?php echo sanitizeOutput($faculty['email'] ?? ''); ?>">
                </div>
                <div class="form-group">
                    <label>Phone</label>
                    <input type="text" name="phone" class="form-control" 
                           value="<?php echo sanitizeOutput($faculty['phone'] ?? ''); ?>">
                </div>
            </div>
            
            <div class="form-group">
                <label>Photo</label>
                <?php if (!empty($faculty['photo_path'])): ?>
                <div style="margin-bottom: 10px;">
                    <img src="<?php echo BASE_URL . $faculty['photo_path']; ?>" alt="Faculty Photo" 
                         style="max-width: 150px; max-height: 150px; border: 2px solid #ddd; border-radius: 5px; padding: 5px;">
                    <br><small class="text-muted">Current photo</small>
                </div>
                <?php endif; ?>
                <input type="file" name="photo" accept="image/jpeg,image/jpg,image/png,image/gif" class="form-control">
                <small class="text-muted"><?php echo !empty($faculty['photo_path']) ? 'Upload a new photo to replace the current one. ' : ''; ?>Accepted formats: JPG, PNG, or GIF - Max 5MB</small>
            </div>

            <h2>Address Information</h2>
            <div class="form-row">
                <div class="form-group">
                    <label>Barangay</label>
                    <input type="text" name="barangay" class="form-control" 
                           value="<?php echo sanitizeOutput($faculty['barangay'] ?? ''); ?>">
                </div>
                <div class="form-group">
                    <label>Purok/Street</label>
                    <input type="text" name="purok_street" class="form-control" 
                           placeholder="e.g., Purok 5 or Main Street"
                           value="<?php echo sanitizeOutput($faculty['purok_street'] ?? ''); ?>">
                    <small class="text-muted">Enter either Purok or Street name</small>
                </div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label>Municipality</label>
                    <input type="text" name="municipality" class="form-control" 
                           value="<?php echo sanitizeOutput($faculty['municipality'] ?? ''); ?>">
                </div>
                <div class="form-group">
                    <label>Province</label>
                    <input type="text" name="province" class="form-control" 
                           value="<?php echo sanitizeOutput($faculty['province'] ?? ''); ?>">
                </div>
                <div class="form-group">
                    <label>Zip Code</label>
                    <input type="text" name="zipcode" class="form-control" 
                           value="<?php echo sanitizeOutput($faculty['zipcode'] ?? ''); ?>">
                </div>
            </div>

            <h2>Professional Information</h2>
            <div class="form-row">
                <div class="form-group">
                    <label>Department</label>
                    <select name="department_id" class="form-control">
                        <option value="">Select Department</option>
                        <?php foreach ($departments as $dept): ?>
                            <option value="<?php echo $dept['department_id']; ?>" 
                                    <?php echo (($faculty['department_id'] ?? '') == $dept['department_id']) ? 'selected' : ''; ?>>
                                <?php echo sanitizeOutput($dept['department_name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>Position</label>
                    <input type="text" name="position" class="form-control" placeholder="e.g., Professor, Associate Professor"
                           value="<?php echo sanitizeOutput($faculty['position'] ?? ''); ?>">
                </div>
            </div>

            <div class="form-group">
                <label>Qualification</label>
                <textarea name="qualification" class="form-control" rows="3" 
                          placeholder="e.g., Ph.D. in Computer Science"><?php echo sanitizeOutput($faculty['qualification'] ?? ''); ?></textarea>
            </div>

            <div class="form-group">
                <label>Specialization</label>
                <textarea name="specialization" class="form-control" rows="3" 
                          placeholder="Research areas, expertise..."><?php echo sanitizeOutput($faculty['specialization'] ?? ''); ?></textarea>
            </div>

            <h2>User Account Management</h2>
            <?php if ($faculty['has_user_id']): ?>
                <div class="alert alert-info">
                    <i class="fas fa-info-circle"></i> This faculty member has a user account.
                    <strong>Username:</strong> <?php echo sanitizeOutput($faculty['username']); ?><br>
                    <strong>Email:</strong> <?php echo sanitizeOutput($faculty['user_email']); ?><br>
                    <strong>Status:</strong> <?php echo sanitizeOutput($faculty['user_status']); ?>
                </div>
                <div class="form-group">
                    <label>
                        <input type="checkbox" name="manage_user_account" value="1" id="manage_user_account"
                               onchange="toggleUserAccountFields()">
                        Reset Password
                    </label>
                </div>
                <div id="user_account_fields" style="display: none;">
                    <input type="hidden" name="reset_password" value="1">
                    <div class="form-group">
                        <label>New Password <span class="required">*</span></label>
                        <input type="password" name="password" id="password" class="form-control" 
                               placeholder="Minimum 8 characters">
                        <small class="text-muted">Enter a new password for this faculty member</small>
                    </div>
                    <div class="form-group">
                        <button type="button" class="btn btn-outline" onclick="generatePassword()">
                            <i class="fas fa-key"></i> Generate Random Password
                        </button>
                    </div>
                </div>
            <?php else: ?>
                <div class="alert alert-warning">
                    <i class="fas fa-exclamation-triangle"></i> This faculty member does not have a user account yet.
                </div>
                <div class="form-group">
                    <label>
                        <input type="checkbox" name="manage_user_account" value="1" id="manage_user_account"
                               onchange="toggleUserAccountFields()">
                        Create User Account (for login access)
                    </label>
                    <small class="text-muted">If checked, a user account will be automatically created and login credentials will be sent to the faculty's email address. Email is required when this option is enabled.</small>
                </div>
                <div id="user_account_fields" style="display: none;">
                    <input type="hidden" name="create_new_account" value="1">
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle"></i> Username and password will be automatically generated and sent to the faculty's email address.
                    </div>
                </div>
            <?php endif; ?>

            <div class="form-actions">
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-save"></i> Update Faculty Member
                </button>
                <a href="<?php echo BASE_URL; ?>modules/faculty/view.php?id=<?php echo $faculty_id; ?>" class="btn btn-secondary">Cancel</a>
            </div>
        </form>
    </div>
</div>

<script>
function toggleUserAccountFields() {
    const checkbox = document.getElementById('manage_user_account');
    const fields = document.getElementById('user_account_fields');
    const passwordField = document.getElementById('password');
    
    if (checkbox.checked) {
        fields.style.display = 'block';
        if (passwordField) {
            passwordField.required = true;
        }
        // Auto-generate username if creating new account
        const usernameField = document.getElementById('username');
        if (usernameField && !usernameField.value) {
            generateUsername();
        }
    } else {
        fields.style.display = 'none';
        if (passwordField) {
            passwordField.required = false;
        }
    }
}

function generateUsername() {
    const usernameField = document.getElementById('username');
    if (usernameField && !usernameField.value) {
        const firstName = document.querySelector('input[name="first_name"]').value.toLowerCase();
        const lastName = document.querySelector('input[name="last_name"]').value.toLowerCase();
        const email = document.querySelector('input[name="email"]').value.toLowerCase();
        
        let generated = '';
        if (firstName && lastName) {
            generated = (firstName.charAt(0) + lastName).substring(0, 20);
        } else if (email) {
            generated = email.split('@')[0].substring(0, 20);
        }
        
        if (generated) {
            usernameField.value = generated.replace(/[^a-z0-9._]/g, '');
        }
    }
}

function generatePassword() {
    const chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789!@#$%&*';
    let password = '';
    for (let i = 0; i < 12; i++) {
        password += chars.charAt(Math.floor(Math.random() * chars.length));
    }
    document.getElementById('password').value = password;
}
</script>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
