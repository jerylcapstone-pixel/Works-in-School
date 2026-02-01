<?php
require_once __DIR__ . '/../../config/config.php';
requireAuth(ROLE_ADMIN);
$page_title = 'Review Application';
$app_id = (int)($_GET['id'] ?? 0);
if (!$app_id) {
    header('Location: ' . BASE_URL . 'modules/admissions/index.php?error=Invalid ID');
    exit;
}

$pdo = getDBConnection();
$stmt = $pdo->prepare("SELECT * FROM admission_applications WHERE application_id = ?");
$stmt->execute([$app_id]);
$app = $stmt->fetch();
if (!$app) {
    header('Location: ' . BASE_URL . 'modules/admissions/index.php?error=Application not found');
    exit;
}

// Prevent editing if application is already approved or rejected
if ($app['status'] === 'Approved' || $app['status'] === 'Rejected') {
    $status_text = strtolower($app['status']);
    header('Location: ' . BASE_URL . 'modules/admissions/index.php?error=Cannot edit ' . $status_text . ' applications');
    exit;
}

// Get advisors for dropdown
$advisors = [];
$stmt = $pdo->query("SELECT faculty_id, first_name, last_name FROM faculty WHERE status = 'Active' ORDER BY last_name, first_name");
$advisors = $stmt->fetchAll();

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $status = sanitizeInput($_POST['status'] ?? '');
    $notes = sanitizeInput($_POST['notes'] ?? '');
    $advisor_id = !empty($_POST['advisor_id']) ? (int)$_POST['advisor_id'] : null;
    
    if (empty($status)) {
        $error = 'Status is required.';
    } else {
        try {
            $pdo->beginTransaction();
            
            // Update application status
            $sql = "UPDATE admission_applications SET status = ?, notes = ?, reviewed_by = ?, reviewed_at = CURRENT_TIMESTAMP 
                    WHERE application_id = ?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$status, $notes ?: $app['notes'], $_SESSION['user_id'], $app_id]);
            
            // If approved, create user account and student record
            if ($status === 'Approved') {
                // Check if user account already exists
                $stmt = $pdo->prepare("SELECT user_id FROM users WHERE email = ?");
                $stmt->execute([$app['email']]);
                $existing_user = $stmt->fetch();
                
                if (!$existing_user) {
                    // Generate student number first (we'll use this as username)
                    $student_number = $app['student_number'];
                    if (empty($student_number)) {
                        $year = date('Y');
                        $stmt = $pdo->prepare("SELECT COUNT(*) FROM students WHERE student_number LIKE ?");
                        $stmt->execute(["STU-$year-%"]);
                        $count = $stmt->fetchColumn();
                        $student_number = "STU-$year-" . str_pad($count + 1, 4, '0', STR_PAD_LEFT);
                    }
                    
                    // Use student_number as username
                    $username = $student_number;
                    
                    // Ensure username is unique (in case student_number conflicts)
                    $original_username = $username;
                    $counter = 1;
                    while (true) {
                        $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE username = ?");
                        $stmt->execute([$username]);
                        if ($stmt->fetchColumn() == 0) {
                            break;
                        }
                        $username = $original_username . '_' . $counter;
                        $counter++;
                    }
                    
                    // Default password: 1234
                    $default_password = '1234';
                    $password_hash = password_hash($default_password, PASSWORD_DEFAULT);
                    
                    // Create user account
                    $stmt = $pdo->prepare("INSERT INTO users (username, email, password_hash, user_role, status) 
                                          VALUES (?, ?, ?, 'student', 'active')");
                    $stmt->execute([$username, $app['email'], $password_hash]);
                    $user_id = $pdo->lastInsertId();
                    
                    // Map address fields from admission_applications to students table
                    // Use home address as primary address, fallback to current address if home is empty
                    $address = !empty($app['home_street']) ? $app['home_street'] : ($app['current_street'] ?? null);
                    $city = !empty($app['home_city']) ? $app['home_city'] : ($app['current_city'] ?? null);
                    $state = !empty($app['home_province']) ? $app['home_province'] : ($app['current_province'] ?? null);
                    $zip_code = !empty($app['home_zipcode']) ? $app['home_zipcode'] : ($app['current_zipcode'] ?? null);
                    
                    // Create student record
                    $stmt = $pdo->prepare("INSERT INTO students (user_id, student_number, first_name, middle_name, last_name, 
                                          date_of_birth, gender, phone, address, city, state, zip_code, country, enrollment_date, program_id, advisor_id, status)
                                          VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'Philippines', CURDATE(), ?, ?, 'Active')");
                    $stmt->execute([
                        $user_id,
                        $student_number,
                        $app['first_name'],
                        $app['middle_name'] ?: null,
                        $app['last_name'],
                        $app['date_of_birth'],
                        $app['gender'] ?: 'Other',
                        $app['phone'] ?: null,
                        $address,
                        $city,
                        $state,
                        $zip_code,
                        $app['program_id'],
                        $advisor_id
                    ]);
                    
                    $student_id = $pdo->lastInsertId();
                    
                    // Create advisory assignment if advisor is selected
                    if ($advisor_id) {
                        $stmt = $pdo->prepare("INSERT INTO advisory_assignments (student_id, advisor_id, assigned_date, status) 
                                              VALUES (?, ?, CURDATE(), 'Active')");
                        $stmt->execute([$student_id, $advisor_id]);
                    }
                    
                    // Update application with student number
                    $stmt = $pdo->prepare("UPDATE admission_applications SET student_number = ? WHERE application_id = ?");
                    $stmt->execute([$student_number, $app_id]);
                    
                    // Send email with credentials
                    $email_subject = "Welcome to " . APP_NAME . " - Your Application Has Been Approved!";
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
                                    <h2>Application Approved!</h2>
                                </div>
                                <div class='content'>
                                    <p>Dear {$app['first_name']} {$app['last_name']},</p>
                                    <p>Congratulations! Your enrollment application has been approved.</p>
                                    <p>Your student account has been created. You can now access the student portal using the following credentials:</p>
                                    
                                    <div class='credentials'>
                                        <strong>Username:</strong> {$username}<br>
                                        <strong>Password:</strong> {$default_password}<br>
                                        <strong>Student Number:</strong> {$student_number}
                                    </div>
                                    
                                    <p><strong>Important:</strong> Please change your password after first login for security purposes.</p>
                                    
                                    <p>If you have any questions, please contact the admissions office.</p>
                                    
                                    <p>Best regards,<br>" . APP_NAME . " Administration</p>
                                </div>
                                <div class='footer'>
                                    <p>This is an automated email. Please do not reply.</p>
                                </div>
                            </div>
                        </body>
                        </html>
                    ";
                    
                    if (sendEmail($app['email'], $email_subject, $email_message)) {
                        $success = 'Application approved successfully. User account created and credentials sent to student email.';
                    } else {
                        $success = 'Application approved successfully. User account created, but email could not be sent. Please contact the student manually.';
                    }
                } else {
                    $success = 'Application approved, but user account already exists for this email.';
                }
            } else {
                $success = 'Application updated successfully.';
            }
            
            $pdo->commit();
            header('Location: ' . BASE_URL . 'modules/admissions/index.php?success=' . urlencode($success));
            exit;
            
        } catch (Exception $e) {
            $pdo->rollBack();
            $error = 'Error updating application: ' . $e->getMessage();
        }
    }
}

include __DIR__ . '/../../includes/header.php';
?>

<div class="page-container">
    <div class="page-header">
        <h1><i class="fas fa-clipboard-check"></i> Review Application</h1>
        <div class="header-actions">
            <a href="<?php echo BASE_URL; ?>modules/admissions/view.php?id=<?php echo $app_id; ?>" class="btn btn-secondary">
                <i class="fas fa-eye"></i> View Details
            </a>
            <a href="<?php echo BASE_URL; ?>modules/admissions/index.php" class="btn btn-secondary">
                <i class="fas fa-arrow-left"></i> Back to List
            </a>
        </div>
    </div>

    <?php if ($error): ?>
        <div class="alert alert-error">
            <i class="fas fa-exclamation-circle"></i> <?php echo sanitizeOutput($error); ?>
        </div>
    <?php endif; ?>

    <div class="detail-cards">
        <!-- Application Summary Card -->
        <div class="detail-card" style="background: linear-gradient(135deg, #2563eb 0%, #1e40af 100%); color: white;">
            <h3 style="color: white; border-bottom-color: rgba(255, 255, 255, 0.3);">
                <i class="fas fa-user-graduate"></i> Applicant Information
            </h3>
            <div class="detail-grid">
                <div class="detail-item">
                    <label style="color: rgba(255, 255, 255, 0.9);">Full Name</label>
                    <span style="color: white; font-size: 1.1rem; font-weight: 600;">
                        <?php echo sanitizeOutput($app['first_name'] . ' ' . ($app['middle_name'] ? $app['middle_name'] . ' ' : '') . $app['last_name']); ?>
                    </span>
                </div>
                <div class="detail-item">
                    <label style="color: rgba(255, 255, 255, 0.9);">Email</label>
                    <span style="color: white; font-size: 1rem;"><?php echo sanitizeOutput($app['email']); ?></span>
                </div>
                <div class="detail-item">
                    <label style="color: rgba(255, 255, 255, 0.9);">Phone</label>
                    <span style="color: white; font-size: 1rem;"><?php echo sanitizeOutput($app['phone'] ?? 'N/A'); ?></span>
                </div>
                <div class="detail-item">
                    <label style="color: rgba(255, 255, 255, 0.9);">Date of Birth</label>
                    <span style="color: white; font-size: 1rem;"><?php echo date('F d, Y', strtotime($app['date_of_birth'])); ?></span>
                </div>
                <div class="detail-item">
                    <label style="color: rgba(255, 255, 255, 0.9);">Gender</label>
                    <span style="color: white; font-size: 1rem;"><?php echo sanitizeOutput($app['gender'] ?? 'N/A'); ?></span>
                </div>
                <div class="detail-item">
                    <label style="color: rgba(255, 255, 255, 0.9);">Application Date</label>
                    <span style="color: white; font-size: 1rem;"><?php echo date('F d, Y', strtotime($app['application_date'])); ?></span>
                </div>
            </div>
        </div>

        <!-- Review Form Card -->
        <div class="form-card">
            <form method="POST" action="" class="form">
                <div class="form-section">
                    <h3><i class="fas fa-tasks"></i> Review & Decision</h3>
                    
                    <div class="form-group">
                        <label>Application Status <span class="required">*</span></label>
                        <select name="status" id="status" required class="form-control" onchange="toggleAdvisorField()" style="font-size: 1rem; padding: 0.75rem;">
                            <option value="Pending" <?php echo ($app['status'] === 'Pending') ? 'selected' : ''; ?>>Pending</option>
                            <option value="Approved" <?php echo ($app['status'] === 'Approved') ? 'selected' : ''; ?>>Approved</option>
                            <option value="Rejected" <?php echo ($app['status'] === 'Rejected') ? 'selected' : ''; ?>>Rejected</option>
                        </select>
                        <small class="text-muted" style="display: block; margin-top: 0.5rem; color: #64748b;">
                            <i class="fas fa-info-circle"></i> When approved, a user account will be automatically created with default password "1234"
                        </small>
                    </div>

                    <div class="form-group" id="advisor-group" style="display: none; padding: 1.5rem; background: #f0f9ff; border-radius: 8px; border-left: 4px solid #2563eb; margin-top: 1rem;">
                        <label style="font-weight: 600; color: #1e293b;">
                            <i class="fas fa-user-tie" style="color: #2563eb;"></i> Assign Academic Advisor (Optional)
                        </label>
                        <select name="advisor_id" class="form-control" style="margin-top: 0.5rem;">
                            <option value="">Select Advisor</option>
                            <?php foreach ($advisors as $advisor): ?>
                                <option value="<?php echo $advisor['faculty_id']; ?>">
                                    <?php echo sanitizeOutput($advisor['first_name'] . ' ' . $advisor['last_name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <small class="text-muted" style="display: block; margin-top: 0.5rem; color: #64748b;">
                            <i class="fas fa-lightbulb"></i> You can assign an advisor now or later through Student Advisory Management
                        </small>
                    </div>

                    <div class="form-group">
                        <label>Review Notes</label>
                        <textarea name="notes" class="form-control" rows="6" placeholder="Add any notes or comments about this application..."><?php echo sanitizeOutput($app['notes'] ?? ''); ?></textarea>
                        <small class="text-muted" style="display: block; margin-top: 0.5rem; color: #64748b;">
                            <i class="fas fa-sticky-note"></i> These notes will be saved with the application record
                        </small>
                    </div>
                </div>

                <!-- Information Box -->
                <div class="form-section" style="background: linear-gradient(135deg, #dbeafe 0%, #bfdbfe 100%); border: none; border-left: 4px solid #2563eb; padding: 1.5rem;">
                    <h4 style="margin-top: 0; margin-bottom: 1rem; color: #1e40af; display: flex; align-items: center; gap: 0.5rem;">
                        <i class="fas fa-info-circle" style="color: #2563eb;"></i> After Approval Process
                    </h4>
                    <ul style="margin: 0; padding-left: 1.5rem; color: #1e40af; line-height: 1.8;">
                        <li style="margin-bottom: 0.75rem;">
                            <strong>Advisor Assignment:</strong> Can be assigned during approval or later in Student Advisory Management
                        </li>
                        <li style="margin-bottom: 0.75rem;">
                            <strong>Course Registration:</strong> Admin will enroll student in courses through Course Registration module
                        </li>
                        <li>
                            <strong>Class Sections:</strong> Admin creates sections with assigned teachers, then enrolls students
                        </li>
                    </ul>
                </div>

                <div class="form-actions" style="margin-top: 2rem; padding-top: 1.5rem; border-top: 2px solid #e2e8f0; display: flex; gap: 1rem; justify-content: flex-end;">
                    <button type="submit" class="btn btn-primary" style="min-width: 150px;">
                        <i class="fas fa-save"></i> Update Application
                    </button>
                    <a href="<?php echo BASE_URL; ?>modules/admissions/index.php" class="btn btn-secondary" style="min-width: 120px;">
                        <i class="fas fa-times"></i> Cancel
                    </a>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
    function toggleAdvisorField() {
        const statusSelect = document.getElementById('status');
        const advisorGroup = document.getElementById('advisor-group');
        
        if (statusSelect.value === 'Approved') {
            advisorGroup.style.display = 'block';
            // Smooth fade-in animation
            advisorGroup.style.opacity = '0';
            advisorGroup.style.transition = 'opacity 0.3s ease-in';
            setTimeout(() => {
                advisorGroup.style.opacity = '1';
            }, 10);
        } else {
            advisorGroup.style.opacity = '0';
            advisorGroup.style.transition = 'opacity 0.3s ease-out';
            setTimeout(() => {
                advisorGroup.style.display = 'none';
            }, 300);
        }
    }
    
    // Initialize on page load
    document.addEventListener('DOMContentLoaded', function() {
        toggleAdvisorField();
        // Set initial opacity
        const advisorGroup = document.getElementById('advisor-group');
        if (advisorGroup.style.display !== 'none') {
            advisorGroup.style.opacity = '1';
        }
    });
</script>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
