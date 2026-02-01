<?php
/**
 * Faculty & Staff Management - Add Faculty
 * Form to add new faculty member
 */

require_once __DIR__ . '/../../config/config.php';
requireAuth(ROLE_ADMIN);

$page_title = 'Add Faculty Member';

$pdo = getDBConnection();
$error = '';
$success = '';

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
    $status = 'Active'; // New faculty are always set to Active
    
    // User account creation fields
    $create_user_account = isset($_POST['create_user_account']) && $_POST['create_user_account'] === '1';
    
    // Handle photo upload
    $photo_path = null;
    if (isset($_FILES['photo']) && $_FILES['photo']['error'] === UPLOAD_ERR_OK) {
        $photo_result = handlePhotoUpload($_FILES['photo']);
        if ($photo_result === false) {
            $error = 'Invalid photo file. Please upload JPG, PNG, or GIF only.';
        } elseif ($photo_result !== null) {
            $photo_path = $photo_result;
        }
    }

    if (empty($error) && (empty($first_name) || empty($last_name) || empty($hire_date))) {
        $error = 'Please fill in all required fields.';
    } elseif (empty($error) && $create_user_account && empty($email)) {
        $error = 'Email is required when creating a user account. Credentials will be sent to the email address.';
    } elseif (empty($error)) {
            // Start transaction
            $pdo->beginTransaction();
            
            try {
                // Auto-generate employee ID
                $stmt = $pdo->query("SELECT COALESCE(MAX(employee_id), 0) + 1 as next_employee_id FROM faculty");
                $result = $stmt->fetch();
                $employee_id = (int)$result['next_employee_id'];
                
                $user_id = null;
                
                // Create user account if requested
                if ($create_user_account) {
                    // Check if email is already used
                    if (!empty($email)) {
                        $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE email = ?");
                        $stmt->execute([$email]);
                        if ($stmt->fetchColumn() > 0) {
                            throw new Exception('Email already exists. Please use a different email.');
                        }
                    } else {
                        throw new Exception('Email is required when creating a user account.');
                    }
                    
                    // Auto-generate username from employee ID (will be created after faculty record)
                    // We'll generate it after we have the employee_id
                    // For now, we'll use a temporary approach - generate username from name
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
                    
                    // Generate random password (8-12 characters, mix of letters, numbers)
                    $password = bin2hex(random_bytes(6)); // 12 character hex string
                    
                    // Hash password
                    $password_hash = password_hash($password, PASSWORD_DEFAULT);
                    
                    // Create user account
                    $stmt = $pdo->prepare("INSERT INTO users (username, email, password_hash, user_role, status) 
                                          VALUES (?, ?, ?, 'faculty', 'active')");
                    $stmt->execute([$username, $email, $password_hash]);
                    $user_id = $pdo->lastInsertId();
                    
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
                }
                
                // Create faculty record
                $sql = "INSERT INTO faculty (user_id, employee_id, first_name, middle_name, last_name, suffix, date_of_birth,
                        gender, phone, email, photo_path, barangay, purok_street, zipcode, province, municipality, hire_date,
                        department_id, position, qualification, specialization, status)
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

                $stmt = $pdo->prepare($sql);
                $result = $stmt->execute([
                    $user_id, $employee_id, $first_name, $middle_name ?: null, $last_name, $suffix ?: null, $date_of_birth ?: null,
                    $gender ?: null, $phone ?: null, $email ?: null, $photo_path, $barangay ?: null, $purok_street ?: null,
                    $zipcode ?: null, $province ?: null, $municipality ?: null, $hire_date, $department_id, $position ?: null,
                    $qualification ?: null, $specialization ?: null, $status
                ]);

                // Commit transaction
                $pdo->commit();
                
                $success_msg = 'Faculty member added successfully';
                if ($create_user_account) {
                    $success_msg .= '. Login credentials have been automatically generated and sent to their email address.';
                }
                
                header('Location: ' . BASE_URL . 'modules/faculty/index.php?success=' . urlencode($success_msg));
                exit;
            } catch (Exception $e) {
                // Rollback transaction on error
                $pdo->rollBack();
                $error = $e->getMessage();
            }
        }
    }

include __DIR__ . '/../../includes/header.php';
?>

<div class="page-container">
    <div class="page-header">
        <h1><i class="fas fa-user-plus"></i> Add New Faculty Member</h1>
        <a href="<?php echo BASE_URL; ?>modules/faculty/index.php" class="btn btn-secondary">
            <i class="fas fa-arrow-left"></i> Back to List
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
                    <label>Hire Date <span class="required">*</span></label>
                    <input type="date" name="hire_date" required class="form-control" 
                           value="<?php echo sanitizeOutput($_POST['hire_date'] ?? ''); ?>">
                </div>
            </div>
            <div class="alert alert-info">
                <i class="fas fa-info-circle"></i> Employee ID will be automatically generated upon saving.
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label>First Name <span class="required">*</span></label>
                    <input type="text" name="first_name" required class="form-control" 
                           value="<?php echo sanitizeOutput($_POST['first_name'] ?? ''); ?>">
                </div>
                <div class="form-group">
                    <label>Middle Name</label>
                    <input type="text" name="middle_name" class="form-control" 
                           value="<?php echo sanitizeOutput($_POST['middle_name'] ?? ''); ?>">
                </div>
                <div class="form-group">
                    <label>Last Name <span class="required">*</span></label>
                    <input type="text" name="last_name" required class="form-control" 
                           value="<?php echo sanitizeOutput($_POST['last_name'] ?? ''); ?>">
                </div>
                <div class="form-group">
                    <label>Suffix</label>
                    <input type="text" name="suffix" class="form-control" 
                           placeholder="e.g., Jr., Sr., II, III, Ph.D."
                           value="<?php echo sanitizeOutput($_POST['suffix'] ?? ''); ?>">
                    <small class="text-muted">Optional: Jr., Sr., II, III, etc.</small>
                </div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label>Date of Birth</label>
                    <input type="date" name="date_of_birth" class="form-control" 
                           value="<?php echo sanitizeOutput($_POST['date_of_birth'] ?? ''); ?>">
                </div>
                <div class="form-group">
                    <label>Gender</label>
                    <select name="gender" class="form-control">
                        <option value="">Select Gender</option>
                        <option value="Male" <?php echo (($_POST['gender'] ?? '') === 'Male') ? 'selected' : ''; ?>>Male</option>
                        <option value="Female" <?php echo (($_POST['gender'] ?? '') === 'Female') ? 'selected' : ''; ?>>Female</option>
                        <option value="Other" <?php echo (($_POST['gender'] ?? '') === 'Other') ? 'selected' : ''; ?>>Other</option>
                    </select>
                </div>
            </div>

            <h2>Contact Information</h2>
            <div class="form-row">
                <div class="form-group">
                    <label>Email</label>
                    <input type="email" name="email" class="form-control" 
                           value="<?php echo sanitizeOutput($_POST['email'] ?? ''); ?>">
                </div>
                <div class="form-group">
                    <label>Phone</label>
                    <input type="text" name="phone" class="form-control" 
                           value="<?php echo sanitizeOutput($_POST['phone'] ?? ''); ?>">
                </div>
            </div>
            
            <div class="form-group">
                <label>Photo</label>
                <input type="file" name="photo" accept="image/jpeg,image/jpg,image/png,image/gif" class="form-control">
                <small class="text-muted">Upload faculty photo (JPG, PNG, or GIF - Max 5MB)</small>
            </div>

            <h2>Address Information</h2>
            <div class="form-row">
                <div class="form-group">
                    <label>Barangay</label>
                    <input type="text" name="barangay" class="form-control" 
                           value="<?php echo sanitizeOutput($_POST['barangay'] ?? ''); ?>">
                </div>
                <div class="form-group">
                    <label>Purok/Street</label>
                    <input type="text" name="purok_street" class="form-control" 
                           placeholder="e.g., Purok 5 or Main Street"
                           value="<?php echo sanitizeOutput($_POST['purok_street'] ?? ''); ?>">
                    <small class="text-muted">Enter either Purok or Street name</small>
                </div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label>Municipality</label>
                    <input type="text" name="municipality" class="form-control" 
                           value="<?php echo sanitizeOutput($_POST['municipality'] ?? ''); ?>">
                </div>
                <div class="form-group">
                    <label>Province</label>
                    <input type="text" name="province" class="form-control" 
                           value="<?php echo sanitizeOutput($_POST['province'] ?? ''); ?>">
                </div>
                <div class="form-group">
                    <label>Zip Code</label>
                    <input type="text" name="zipcode" class="form-control" 
                           value="<?php echo sanitizeOutput($_POST['zipcode'] ?? ''); ?>">
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
                                    <?php echo (($_POST['department_id'] ?? '') == $dept['department_id']) ? 'selected' : ''; ?>>
                                <?php echo sanitizeOutput($dept['department_name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>Position</label>
                    <input type="text" name="position" class="form-control" placeholder="e.g., Professor, Associate Professor"
                           value="<?php echo sanitizeOutput($_POST['position'] ?? ''); ?>">
                </div>
            </div>

            <div class="form-group">
                <label>Qualification</label>
                <textarea name="qualification" class="form-control" rows="3" 
                          placeholder="e.g., Ph.D. in Computer Science"><?php echo sanitizeOutput($_POST['qualification'] ?? ''); ?></textarea>
            </div>

            <div class="form-group">
                <label>Specialization</label>
                <textarea name="specialization" class="form-control" rows="3" 
                          placeholder="Research areas, expertise..."><?php echo sanitizeOutput($_POST['specialization'] ?? ''); ?></textarea>
            </div>

            <h2>User Account (Optional)</h2>
            <div class="form-group">
                <label>
                    <input type="checkbox" name="create_user_account" value="1" id="create_user_account" 
                           <?php echo (isset($_POST['create_user_account']) && $_POST['create_user_account'] === '1') ? 'checked' : ''; ?>>
                    Create User Account (for login access)
                </label>
                <small class="text-muted">If checked, a user account will be automatically created and login credentials will be sent to the faculty's email address. Email is required when this option is enabled.</small>
            </div>

            <div class="form-actions">
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-save"></i> Add Faculty Member
                </button>
                <a href="<?php echo BASE_URL; ?>modules/faculty/index.php" class="btn btn-secondary">Cancel</a>
            </div>
        </form>
    </div>
</div>


<?php include __DIR__ . '/../../includes/footer.php'; ?>
