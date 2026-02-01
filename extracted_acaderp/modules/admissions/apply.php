<?php
/**
 * Public Student Enrollment Application Form
 * Comprehensive enrollment form for student enrollment
 */

require_once __DIR__ . '/../../config/config.php';

$page_title = 'Student Enrollment Application';
$pdo = getDBConnection();
$error = '';
$success = false;

// Get programs and departments
$programs = [];
$departments = [];
$all_majors = [];
if ($pdo) {
    $stmt = $pdo->query("SELECT program_id, program_name, program_code, department_id, duration_years FROM programs WHERE status = 'Active' ORDER BY program_name");
    $programs = $stmt->fetchAll();
    
    $stmt = $pdo->query("SELECT department_id, department_name, department_code FROM departments ORDER BY department_name");
    $departments = $stmt->fetchAll();
    
    // Get all majors (will be filtered by JavaScript based on program)
    try {
        $stmt = $pdo->query("SELECT major_id, program_id, major_name FROM majors WHERE status = 'Active' ORDER BY major_name");
        $all_majors = $stmt->fetchAll();
    } catch (PDOException $e) {
        // If majors table doesn't exist yet, use empty array
        $all_majors = [];
    }
}

// Handle file upload
function handlePhotoUpload($file) {
    if (!isset($file) || $file['error'] !== UPLOAD_ERR_OK) {
        return null;
    }
    
    $allowed = ['image/jpeg', 'image/jpg', 'image/png'];
    if (!in_array($file['type'], $allowed)) {
        return false; // Error: invalid file type
    }
    
    $uploadDir = __DIR__ . '/../../uploads/student_photos/';
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }
    
    $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
    $filename = 'photo_' . time() . '_' . uniqid() . '.' . $extension;
    $filepath = $uploadDir . $filename;
    
    if (move_uploaded_file($file['tmp_name'], $filepath)) {
        return 'uploads/student_photos/' . $filename;
    }
    
    return false;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Basic validation
    $first_name = sanitizeInput($_POST['first_name'] ?? '');
    $last_name = sanitizeInput($_POST['last_name'] ?? '');
    $email = sanitizeInput($_POST['email'] ?? '');
    $program_id = (int)($_POST['program_id'] ?? 0);
    $department_id = !empty($_POST['department_id']) ? (int)$_POST['department_id'] : null;
    $major_id = !empty($_POST['major_id']) ? (int)$_POST['major_id'] : null;
    $date_of_birth = $_POST['date_of_birth'] ?? '';
    $home_street = sanitizeInput($_POST['home_street'] ?? '');
    $home_city = sanitizeInput($_POST['home_city'] ?? '');
    $home_province = sanitizeInput($_POST['home_province'] ?? '');
    
    if (empty($first_name) || empty($last_name) || empty($email) || empty($program_id) || empty($date_of_birth) || 
        empty($home_street) || empty($home_city) || empty($home_province)) {
        $error = 'Please fill in all required fields marked with *';
    } else {
        // Check if email already exists
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM admission_applications WHERE email = ?");
        $stmt->execute([$email]);
        if ($stmt->fetchColumn() > 0) {
            $error = 'An application with this email already exists. Please contact the admissions office.';
        } else {
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE email = ?");
            $stmt->execute([$email]);
            if ($stmt->fetchColumn() > 0) {
                $error = 'An account with this email already exists. Please login instead.';
            } else {
                // Handle photo upload
                $photo_path = null;
                if (isset($_FILES['photo']) && $_FILES['photo']['error'] === UPLOAD_ERR_OK) {
                    $photo_result = handlePhotoUpload($_FILES['photo']);
                    if ($photo_result === false) {
                        $error = 'Invalid photo file. Please upload JPG or PNG only.';
                    } elseif ($photo_result !== null) {
                        $photo_path = $photo_result;
                    }
                }
                
                if (empty($error)) {
                    // Process organizations
                    $organizations = [];
                    if (isset($_POST['org_name']) && is_array($_POST['org_name'])) {
                        for ($i = 0; $i < count($_POST['org_name']); $i++) {
                            $org_name = sanitizeInput($_POST['org_name'][$i] ?? '');
                            if (!empty($org_name)) {
                                $organizations[] = [
                                    'name' => $org_name,
                                    'position' => sanitizeInput($_POST['org_position'][$i] ?? ''),
                                    'term' => sanitizeInput($_POST['org_term'][$i] ?? '')
                                ];
                            }
                        }
                    }
                    
                    // Prepare all form data
                    $data = [
                        'student_type' => sanitizeInput($_POST['student_type'] ?? 'New'),
                        'id_number' => sanitizeInput($_POST['id_number'] ?? ''),
                        'first_name' => $first_name,
                        'middle_name' => sanitizeInput($_POST['middle_name'] ?? ''),
                        'last_name' => $last_name,
                        'email' => $email,
                        'phone' => sanitizeInput($_POST['phone'] ?? ''),
                        'date_of_birth' => $date_of_birth,
                        'gender' => sanitizeInput($_POST['gender'] ?? ''),
                        'religion' => sanitizeInput($_POST['religion'] ?? ''),
                        'civil_status' => sanitizeInput($_POST['civil_status'] ?? ''),
                        'photo_path' => $photo_path,
                        'program_id' => $program_id,
                        'year_level' => sanitizeInput($_POST['year_level'] ?? ''),
                        'major_id' => $major_id,
                        'major' => sanitizeInput($_POST['major'] ?? ''), // Keep for backward compatibility
                        'term' => sanitizeInput($_POST['term'] ?? 'FIRST SEMESTER 2025-2026'),
                        'father_lastname' => sanitizeInput($_POST['father_lastname'] ?? ''),
                        'father_firstname' => sanitizeInput($_POST['father_firstname'] ?? ''),
                        'father_middlename' => sanitizeInput($_POST['father_middlename'] ?? ''),
                        'mother_lastname' => sanitizeInput($_POST['mother_lastname'] ?? ''),
                        'mother_firstname' => sanitizeInput($_POST['mother_firstname'] ?? ''),
                        'mother_middlename' => sanitizeInput($_POST['mother_middlename'] ?? ''),
                        'mother_maiden_middlename' => sanitizeInput($_POST['mother_maiden_middlename'] ?? ''),
                        'home_street' => sanitizeInput($_POST['home_street'] ?? ''),
                        'home_city' => sanitizeInput($_POST['home_city'] ?? ''),
                        'home_province' => sanitizeInput($_POST['home_province'] ?? ''),
                        'home_zipcode' => sanitizeInput($_POST['home_zipcode'] ?? ''),
                        'current_street' => sanitizeInput($_POST['current_street'] ?? ''),
                        'current_city' => sanitizeInput($_POST['current_city'] ?? ''),
                        'current_province' => sanitizeInput($_POST['current_province'] ?? ''),
                        'current_zipcode' => sanitizeInput($_POST['current_zipcode'] ?? ''),
                        'parents_contact' => sanitizeInput($_POST['parents_contact'] ?? ''),
                        'guardian_name' => sanitizeInput($_POST['guardian_name'] ?? ''),
                        'guardian_contact' => sanitizeInput($_POST['guardian_contact'] ?? ''),
                        'dswd_household_no' => sanitizeInput($_POST['dswd_household_no'] ?? ''),
                        'monthly_income' => !empty($_POST['monthly_income']) ? (float)$_POST['monthly_income'] : null,
                        'disability' => sanitizeInput($_POST['disability'] ?? ''),
                        'organizations' => !empty($organizations) ? json_encode($organizations) : null,
                        'citizenship' => sanitizeInput($_POST['citizenship'] ?? ''),
                        'disciplinary_history' => isset($_POST['disciplinary_history']) && $_POST['disciplinary_history'] === 'Yes' ? 1 : 0,
                        'disciplinary_specification' => sanitizeInput($_POST['disciplinary_specification'] ?? ''),
                        'pledge_no_drugs' => isset($_POST['pledge_no_drugs']) ? 1 : 0,
                        'pledge_no_unlawful_acts' => isset($_POST['pledge_no_unlawful_acts']) ? 1 : 0,
                        'consent_marketing' => isset($_POST['consent_marketing']) ? 1 : 0,
                        'consent_data_processing' => isset($_POST['consent_data_processing']) ? 1 : 0,
                        'assistance_received' => isset($_POST['assistance_received']) ? 1 : 0,
                        'pledge_attested_by' => sanitizeInput($_POST['pledge_attested_by'] ?? ''),
                        'pledge_relationship' => sanitizeInput($_POST['pledge_relationship'] ?? ''),
                        'notes' => sanitizeInput($_POST['notes'] ?? ''),
                    ];
                    
                    // Build SQL query dynamically to handle optional fields
                    $fields = [];
                    $values = [];
                    
                    // Check if department_id column exists in the table
                    $department_column_exists = false;
                    try {
                        $check_stmt = $pdo->query("SHOW COLUMNS FROM admission_applications LIKE 'department_id'");
                        $department_column_exists = $check_stmt->rowCount() > 0;
                    } catch (PDOException $e) {
                        // Column doesn't exist
                        $department_column_exists = false;
                    }
                    
                    foreach ($data as $field => $value) {
                        if ($value !== '' && $value !== null) {
                            // Skip department_id if column doesn't exist
                            if ($field === 'department_id' && !$department_column_exists) {
                                continue;
                            }
                            $fields[] = $field;
                            $values[] = $value;
                        }
                    }
                    
                    // Add department_id separately if column exists and value is not null
                    if ($department_column_exists && $department_id !== null) {
                        $fields[] = 'department_id';
                        $values[] = $department_id;
                    }
                    
                    // Add required fields
                    $fields[] = 'application_date';
                    $values[] = date('Y-m-d');
                    
                    $fields[] = 'status';
                    $values[] = 'Pending';
                    
                    $placeholders = array_fill(0, count($fields), '?');
                    $sql = "INSERT INTO admission_applications (" . implode(', ', $fields) . ") VALUES (" . implode(', ', $placeholders) . ")";
                    
                $stmt = $pdo->prepare($sql);
                if ($stmt->execute($values)) {
                    // Send confirmation email to student
                    $middle_name = sanitizeInput($_POST['middle_name'] ?? '');
                    $student_full_name = $first_name . ($middle_name ? ' ' . $middle_name . ' ' : ' ') . $last_name;
                    
                    $email_subject = "Thank You for Your Enrollment Application - " . APP_NAME;
                    $email_message = "
                        <html>
                        <head>
                            <style>
                                body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                                .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                                .header { background-color: #2196F3; color: white; padding: 20px; text-align: center; }
                                .content { padding: 20px; background-color: #f9f9f9; }
                                .info-box { background-color: #fff; padding: 15px; border-left: 4px solid #2196F3; margin: 20px 0; }
                                .footer { text-align: center; padding: 20px; color: #666; font-size: 12px; }
                                .status { background-color: #fff3cd; padding: 10px; border-radius: 5px; margin: 15px 0; }
                            </style>
                        </head>
                        <body>
                            <div class='container'>
                                <div class='header'>
                                    <h2>Application Received!</h2>
                                </div>
                                <div class='content'>
                                    <p>Dear " . sanitizeOutput($first_name) . " " . sanitizeOutput($last_name) . ",</p>
                                    <p>Thank you for submitting your enrollment application to " . APP_NAME . ".</p>
                                    
                                    <div class='info-box'>
                                        <p><strong>Application Status: Pending Review</strong></p>
                                        <p>Your application has been successfully received and is now under review by our admissions team.</p>
                                    </div>
                                    
                                    <div class='status'>
                                        <p><strong>What happens next?</strong></p>
                                        <ul>
                                            <li>Our admissions team will review your application</li>
                                            <li>You will receive an email notification once your application has been reviewed</li>
                                            <li>If approved, you will receive your student account credentials via email</li>
                                        </ul>
                                    </div>
                                    
                                    <p><strong>Application Details:</strong></p>
                                    <ul>
                                        <li><strong>Name:</strong> " . sanitizeOutput($student_full_name) . "</li>
                                        <li><strong>Email:</strong> " . sanitizeOutput($email) . "</li>
                                        <li><strong>Application Date:</strong> " . date('F d, Y') . "</li>
                                    </ul>
                                    
                                    <p>Please keep this email for your records. If you have any questions or need to update your application, please contact the admissions office.</p>
                                    
                                    <p>We appreciate your interest in joining our institution and look forward to reviewing your application.</p>
                                    
                                    <p>Best regards,<br>" . APP_NAME . " Admissions Office</p>
                                </div>
                                <div class='footer'>
                                    <p>This is an automated email. Please do not reply to this message.</p>
                                    <p>If you have questions, please contact the admissions office directly.</p>
                                </div>
                            </div>
                        </body>
                        </html>
                    ";
                    
                    // Send email (continue even if email fails)
                    try {
                        sendEmail($email, $email_subject, $email_message);
                    } catch (Exception $e) {
                        // Log error but don't prevent application submission
                        error_log("Failed to send confirmation email: " . $e->getMessage());
                    }
                    
                    $success = true;
                } else {
                    $error = 'Error submitting application. Please try again.';
                }
                }
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?> - <?php echo APP_NAME; ?></title>
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .photo-upload { 
            text-align: center; 
            padding: 2rem; 
            border: 2px dashed #cbd5e1; 
            border-radius: 12px; 
            background: #f8fafc;
            transition: all 0.3s;
        }
        .photo-upload:hover {
            border-color: #2563eb;
            background: #eff6ff;
        }
        .photo-upload-icon { 
            font-size: 3rem; 
            color: #94a3b8; 
            margin-bottom: 1rem; 
        }
        .instructions { 
            background: linear-gradient(135deg, #fef3c7 0%, #fde68a 100%); 
            padding: 1.25rem 1.5rem; 
            border-left: 4px solid #f59e0b; 
            margin: 2rem 0; 
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
        }
        .instructions strong {
            color: #92400e;
            font-size: 1.05rem;
        }
        .organization-row { 
            display: grid; 
            grid-template-columns: 2fr 2fr 1fr; 
            gap: 1rem; 
            margin-bottom: 1rem; 
        }
        .form-section h2 {
            font-size: 1.5rem;
            font-weight: 700;
            color: #1e293b;
            margin-bottom: 1.5rem;
            padding-bottom: 0.75rem;
            border-bottom: 3px solid #2563eb;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        .form-section h2 i {
            color: #2563eb;
        }
    </style>
</head>
<body>
    <div class="page-container">
        <div class="page-header">
            <div>
                <h1><i class="fas fa-graduation-cap"></i> Student Enrollment Application</h1>
                <div style="color: #ef4444; font-weight: 700; font-size: 1.1rem; margin-top: 0.5rem; padding: 0.5rem 1rem; background: #fee2e2; border-radius: 8px; display: inline-block;">FIRST SEMESTER 2025-2026</div>
            </div>
            <a href="<?php echo BASE_URL; ?>auth/login.php" class="btn btn-secondary">
                <i class="fas fa-sign-in-alt"></i> Login
            </a>
        </div>

        <?php if ($success): ?>
        <div class="alert alert-success">
            <i class="fas fa-check-circle"></i> 
            <strong>Application Submitted Successfully!</strong><br>
            Your application has been received and is pending review. You will receive an email notification once your application is reviewed by the administration.
        </div>
        <?php else: ?>
            <?php if ($error): ?>
            <div class="alert alert-error">
                <i class="fas fa-exclamation-circle"></i> <?php echo sanitizeOutput($error); ?>
            </div>
            <?php endif; ?>

            <form method="POST" action="" enctype="multipart/form-data" class="form">
                
                <!-- Student Type & Academic Information -->
                <div class="form-section">
                    <h2>Academic Information</h2>
                    <div class="form-row">
                        <div class="form-group">
                            <label>Status <span class="required">*</span></label>
                            <select name="student_type" required class="form-control">
                                <option value="New" <?php echo (($_POST['student_type'] ?? 'New') === 'New') ? 'selected' : ''; ?>>New</option>
                                <option value="Old" <?php echo (($_POST['student_type'] ?? '') === 'Old') ? 'selected' : ''; ?>>Old</option>
                                <option value="Transferee" <?php echo (($_POST['student_type'] ?? '') === 'Transferee') ? 'selected' : ''; ?>>Transferee</option>
                                <option value="Returnee" <?php echo (($_POST['student_type'] ?? '') === 'Returnee') ? 'selected' : ''; ?>>Returnee</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Department</label>
                            <select name="department_id" id="department_id" class="form-control" onchange="filterPrograms()">
                                <option value="">Select Department</option>
                                <?php foreach ($departments as $dept): ?>
                                    <option value="<?php echo $dept['department_id']; ?>"
                                            <?php echo (($_POST['department_id'] ?? '') == $dept['department_id']) ? 'selected' : ''; ?>>
                                        <?php echo sanitizeOutput($dept['department_name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label>Program/Course <span class="required">*</span></label>
                            <input type="text" id="program_search" class="form-control" placeholder="Search programs..." style="margin-bottom: 8px;">
                            <select name="program_id" id="program_id" required class="form-control" onchange="updateYearLevels()" data-search-input="program_search" data-no-results="program_search_empty">
                                <option value="">Select Program</option>
                                <?php foreach ($programs as $prog): ?>
                                    <option value="<?php echo $prog['program_id']; ?>"
                                            data-department-id="<?php echo $prog['department_id']; ?>"
                                            data-duration="<?php echo $prog['duration_years'] ?? 4; ?>"
                                            <?php echo (($_POST['program_id'] ?? '') == $prog['program_id']) ? 'selected' : ''; ?>>
                                        <?php echo sanitizeOutput($prog['program_name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <small id="program_search_empty" class="text-warning" style="display:none;">No programs match your search.</small>
                        </div>
                        <div class="form-group">
                            <label>Major</label>
                            <input type="text" id="major_search" class="form-control" placeholder="Search majors..." style="margin-bottom: 8px;">
                            <select name="major_id" id="major_id" class="form-control" data-search-input="major_search" data-no-results="major_search_empty">
                                <option value="">Select Major</option>
                                <?php foreach ($all_majors as $major): ?>
                                    <option value="<?php echo $major['major_id']; ?>"
                                            data-program-id="<?php echo $major['program_id']; ?>"
                                            <?php echo (($_POST['major_id'] ?? '') == $major['major_id']) ? 'selected' : ''; ?>>
                                        <?php echo sanitizeOutput($major['major_name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <small id="major_search_empty" class="text-warning" style="display:none;">No majors match your search.</small>
                            <small class="text-muted">Select a program first to see available majors</small>
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label>Year Level</label>
                            <select name="year_level" id="year_level" class="form-control">
                                <option value="">Select Major</option>
                                <option value="1st Year" data-year="1">1st Year</option>
                                <option value="2nd Year" data-year="2">2nd Year</option>
                                <option value="3rd Year" data-year="3">3rd Year</option>
                                <option value="4th Year" data-year="4">4th Year</option>
                                <option value="5th Year" data-year="5">5th Year</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Term</label>
                            <input type="text" name="term" class="form-control" 
                                   value="<?php echo sanitizeOutput($_POST['term'] ?? 'FIRST SEMESTER 2025-2026'); ?>" readonly>
                        </div>
                    </div>
                </div>

                <!-- Instructions -->
                <div class="instructions">
                    <strong>Instructions:</strong> Fill out all the required information needed. Leave it <span style="color: red; font-weight: bold;">blank</span> if not applicable.
                </div>

                <!-- Photo Upload -->
                <div class="form-section">
                    <h2>UPLOAD PHOTO</h2>
                    <div class="photo-upload">
                        <div class="photo-upload-icon"><i class="fas fa-user-circle"></i></div>
                        <p><strong>UPLOAD PHOTO</strong></p>
                        <p>Please select a VISA-PASSPORT size photo.</p>
                        <p style="color: #666; font-size: 12px;">[.jpg, .png files only]</p>
                        <input type="file" name="photo" accept="image/jpeg,image/jpg,image/png" style="margin-top: 10px;">
                    </div>
                </div>

                <!-- Personal Information -->
                <div class="form-section">
                    <h2>Personal Information</h2>
                    <div class="form-row">
                        <div class="form-group">
                            <label>*ID Number</label>
                            <input type="text" name="id_number" class="form-control" 
                                   value="<?php echo sanitizeOutput($_POST['id_number'] ?? ''); ?>">
                        </div>
                        <div class="form-group">
                            <label>*Religion</label>
                            <input type="text" name="religion" class="form-control" 
                                   value="<?php echo sanitizeOutput($_POST['religion'] ?? ''); ?>">
                        </div>
                        <div class="form-group">
                            <label>*Civil Status</label>
                            <select name="civil_status" class="form-control">
                                <option value="">Select</option>
                                <option value="Single" <?php echo (($_POST['civil_status'] ?? '') === 'Single') ? 'selected' : ''; ?>>Single</option>
                                <option value="Married" <?php echo (($_POST['civil_status'] ?? '') === 'Married') ? 'selected' : ''; ?>>Married</option>
                                <option value="Widowed" <?php echo (($_POST['civil_status'] ?? '') === 'Widowed') ? 'selected' : ''; ?>>Widowed</option>
                                <option value="Divorced" <?php echo (($_POST['civil_status'] ?? '') === 'Divorced') ? 'selected' : ''; ?>>Divorced</option>
                                <option value="Separated" <?php echo (($_POST['civil_status'] ?? '') === 'Separated') ? 'selected' : ''; ?>>Separated</option>
                            </select>
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label>*Contact No</label>
                            <input type="text" name="phone" class="form-control" 
                                   value="<?php echo sanitizeOutput($_POST['phone'] ?? ''); ?>">
                        </div>
                        <div class="form-group">
                            <label>*Email Address <span class="required">*</span></label>
                            <input type="email" name="email" required class="form-control" 
                                   value="<?php echo sanitizeOutput($_POST['email'] ?? ''); ?>">
                            <small class="text-muted">You will receive your login credentials at this email</small>
                        </div>
                    </div>
                </div>

                <!-- Father's Name -->
                <div class="form-section">
                    <h2>Father's Name</h2>
                    <div class="form-row">
                        <div class="form-group">
                            <label>*Lastname</label>
                            <input type="text" name="father_lastname" class="form-control" 
                                   value="<?php echo sanitizeOutput($_POST['father_lastname'] ?? ''); ?>">
                        </div>
                        <div class="form-group">
                            <label>*Firstname (eg.JOHN SR.)</label>
                            <input type="text" name="father_firstname" class="form-control" 
                                   value="<?php echo sanitizeOutput($_POST['father_firstname'] ?? ''); ?>"
                                   placeholder="e.g., JOHN SR.">
                        </div>
                        <div class="form-group">
                            <label>Middle Name</label>
                            <input type="text" name="father_middlename" class="form-control" 
                                   value="<?php echo sanitizeOutput($_POST['father_middlename'] ?? ''); ?>">
                        </div>
                    </div>
                </div>

                <!-- Mother's Name -->
                <div class="form-section">
                    <h2>Mother's Name</h2>
                    <div class="form-row">
                        <div class="form-group">
                            <label>*Lastname</label>
                            <input type="text" name="mother_lastname" class="form-control" 
                                   value="<?php echo sanitizeOutput($_POST['mother_lastname'] ?? ''); ?>">
                        </div>
                        <div class="form-group">
                            <label>*Firstname</label>
                            <input type="text" name="mother_firstname" class="form-control" 
                                   value="<?php echo sanitizeOutput($_POST['mother_firstname'] ?? ''); ?>">
                        </div>
                        <div class="form-group">
                            <label>*Middle Name</label>
                            <input type="text" name="mother_middlename" class="form-control" 
                                   value="<?php echo sanitizeOutput($_POST['mother_middlename'] ?? ''); ?>">
                        </div>
                    </div>
                    <div class="form-group">
                        <label>Maiden Middle Name</label>
                        <input type="text" name="mother_maiden_middlename" class="form-control" 
                               value="<?php echo sanitizeOutput($_POST['mother_maiden_middlename'] ?? ''); ?>">
                    </div>
                </div>

                <!-- Student Personal Details -->
                <div class="form-section">
                    <h2>Student Details</h2>
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
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label>Date of Birth <span class="required">*</span></label>
                            <input type="date" name="date_of_birth" required class="form-control" 
                                   value="<?php echo sanitizeOutput($_POST['date_of_birth'] ?? ''); ?>">
                        </div>
                        <div class="form-group">
                            <label>Gender</label>
                            <select name="gender" class="form-control">
                                <option value="">Select</option>
                                <option value="Male" <?php echo (($_POST['gender'] ?? '') === 'Male') ? 'selected' : ''; ?>>Male</option>
                                <option value="Female" <?php echo (($_POST['gender'] ?? '') === 'Female') ? 'selected' : ''; ?>>Female</option>
                                <option value="Other" <?php echo (($_POST['gender'] ?? '') === 'Other') ? 'selected' : ''; ?>>Other</option>
                            </select>
                        </div>
                    </div>
                </div>

                <!-- Home Address -->
                <div class="form-section">
                    <h2>Home Address: [AUTO]</h2>
                    <div class="form-row">
                        <div class="form-group">
                            <label>Street/Purok/Barangay <span class="required">*</span></label>
                            <input type="text" name="home_street" required class="form-control" 
                                   value="<?php echo sanitizeOutput($_POST['home_street'] ?? ''); ?>">
                        </div>
                        <div class="form-group">
                            <label>City/Town <span class="required">*</span></label>
                            <input type="text" name="home_city" required class="form-control" 
                                   value="<?php echo sanitizeOutput($_POST['home_city'] ?? ''); ?>">
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label>Province <span class="required">*</span></label>
                            <input type="text" name="home_province" required class="form-control" 
                                   value="<?php echo sanitizeOutput($_POST['home_province'] ?? ''); ?>">
                        </div>
                        <div class="form-group">
                            <label>Zip Code</label>
                            <input type="text" name="home_zipcode" class="form-control" 
                                   value="<?php echo sanitizeOutput($_POST['home_zipcode'] ?? ''); ?>">
                        </div>
                    </div>
                    <div class="form-group">
                        <label>
                            <input type="checkbox" id="copy_home_address" onchange="copyAddressToCurrent()">
                            Copy Home Address to Current Address
                        </label>
                    </div>
                </div>

                <!-- Current Address -->
                <div class="form-section">
                    <h2>Current Address: [AUTO]</h2>
                    <div class="form-row">
                        <div class="form-group">
                            <label>Street/Purok/Barangay</label>
                            <input type="text" name="current_street" class="form-control" 
                                   value="<?php echo sanitizeOutput($_POST['current_street'] ?? ''); ?>">
                        </div>
                        <div class="form-group">
                            <label>City/Town</label>
                            <input type="text" name="current_city" class="form-control" 
                                   value="<?php echo sanitizeOutput($_POST['current_city'] ?? ''); ?>">
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label>Province</label>
                            <input type="text" name="current_province" class="form-control" 
                                   value="<?php echo sanitizeOutput($_POST['current_province'] ?? ''); ?>">
                        </div>
                        <div class="form-group">
                            <label>Zip Code</label>
                            <input type="text" name="current_zipcode" class="form-control" 
                                   value="<?php echo sanitizeOutput($_POST['current_zipcode'] ?? ''); ?>">
                        </div>
                    </div>
                </div>

                <!-- Contact & Guardian Information -->
                <div class="form-section">
                    <h2>Contact & Guardian Information</h2>
                    <div class="form-row">
                        <div class="form-group">
                            <label>*Parents Contact No.</label>
                            <input type="text" name="parents_contact" class="form-control" 
                                   value="<?php echo sanitizeOutput($_POST['parents_contact'] ?? ''); ?>">
                        </div>
                        <div class="form-group">
                            <label>*Name of Guardian/Landlady/Landlord</label>
                            <input type="text" name="guardian_name" class="form-control" 
                                   value="<?php echo sanitizeOutput($_POST['guardian_name'] ?? ''); ?>">
                        </div>
                        <div class="form-group">
                            <label>*Guardian Contact No.</label>
                            <input type="text" name="guardian_contact" class="form-control" 
                                   value="<?php echo sanitizeOutput($_POST['guardian_contact'] ?? ''); ?>">
                        </div>
                    </div>
                </div>

                <!-- Household Information -->
                <div class="form-section">
                    <h2>Household & Financial Information</h2>
                    <div class="form-row">
                        <div class="form-group">
                            <label>DSWD Household No (If Available)</label>
                            <input type="text" name="dswd_household_no" class="form-control" 
                                   value="<?php echo sanitizeOutput($_POST['dswd_household_no'] ?? ''); ?>">
                        </div>
                        <div class="form-group">
                            <label>Monthly Income</label>
                            <input type="number" step="0.01" name="monthly_income" class="form-control" 
                                   value="<?php echo sanitizeOutput($_POST['monthly_income'] ?? ''); ?>">
                        </div>
                        <div class="form-group">
                            <label>Disability (if any)</label>
                            <input type="text" name="disability" class="form-control" 
                                   value="<?php echo sanitizeOutput($_POST['disability'] ?? ''); ?>">
                        </div>
                    </div>
                </div>

                <!-- Organization Affiliations -->
                <div class="form-section">
                    <h2>Organization(s) Affiliated (Leave blank if not applicable)</h2>
                    <div id="organizations-container">
                        <div class="organization-row">
                            <input type="text" name="org_name[]" class="form-control" placeholder="Organization Name">
                            <input type="text" name="org_position[]" class="form-control" placeholder="Position Held">
                            <input type="text" name="org_term[]" class="form-control" placeholder="Term">
                        </div>
                        <div class="organization-row">
                            <input type="text" name="org_name[]" class="form-control" placeholder="Organization Name">
                            <input type="text" name="org_position[]" class="form-control" placeholder="Position Held">
                            <input type="text" name="org_term[]" class="form-control" placeholder="Term">
                        </div>
                        <div class="organization-row">
                            <input type="text" name="org_name[]" class="form-control" placeholder="Organization Name">
                            <input type="text" name="org_position[]" class="form-control" placeholder="Position Held">
                            <input type="text" name="org_term[]" class="form-control" placeholder="Term">
                        </div>
                    </div>
                </div>

                <!-- Citizenship & Conduct -->
                <div class="form-section">
                    <h2>CITIZENSHIP</h2>
                    <div class="form-row">
                        <div class="form-group">
                            <label>Select your citizenship</label>
                            <select name="citizenship" class="form-control">
                                <option value="">Select Citizenship</option>
                                <option value="Filipino" <?php echo (($_POST['citizenship'] ?? '') === 'Filipino') ? 'selected' : ''; ?>>Filipino</option>
                                <option value="American" <?php echo (($_POST['citizenship'] ?? '') === 'American') ? 'selected' : ''; ?>>American</option>
                                <option value="Australian" <?php echo (($_POST['citizenship'] ?? '') === 'Australian') ? 'selected' : ''; ?>>Australian</option>
                                <option value="British" <?php echo (($_POST['citizenship'] ?? '') === 'British') ? 'selected' : ''; ?>>British</option>
                                <option value="Canadian" <?php echo (($_POST['citizenship'] ?? '') === 'Canadian') ? 'selected' : ''; ?>>Canadian</option>
                                <option value="Chinese" <?php echo (($_POST['citizenship'] ?? '') === 'Chinese') ? 'selected' : ''; ?>>Chinese</option>
                                <option value="French" <?php echo (($_POST['citizenship'] ?? '') === 'French') ? 'selected' : ''; ?>>French</option>
                                <option value="German" <?php echo (($_POST['citizenship'] ?? '') === 'German') ? 'selected' : ''; ?>>German</option>
                                <option value="Indian" <?php echo (($_POST['citizenship'] ?? '') === 'Indian') ? 'selected' : ''; ?>>Indian</option>
                                <option value="Indonesian" <?php echo (($_POST['citizenship'] ?? '') === 'Indonesian') ? 'selected' : ''; ?>>Indonesian</option>
                                <option value="Japanese" <?php echo (($_POST['citizenship'] ?? '') === 'Japanese') ? 'selected' : ''; ?>>Japanese</option>
                                <option value="Korean" <?php echo (($_POST['citizenship'] ?? '') === 'Korean') ? 'selected' : ''; ?>>Korean</option>
                                <option value="Malaysian" <?php echo (($_POST['citizenship'] ?? '') === 'Malaysian') ? 'selected' : ''; ?>>Malaysian</option>
                                <option value="New Zealander" <?php echo (($_POST['citizenship'] ?? '') === 'New Zealander') ? 'selected' : ''; ?>>New Zealander</option>
                                <option value="Singaporean" <?php echo (($_POST['citizenship'] ?? '') === 'Singaporean') ? 'selected' : ''; ?>>Singaporean</option>
                                <option value="Spanish" <?php echo (($_POST['citizenship'] ?? '') === 'Spanish') ? 'selected' : ''; ?>>Spanish</option>
                                <option value="Thai" <?php echo (($_POST['citizenship'] ?? '') === 'Thai') ? 'selected' : ''; ?>>Thai</option>
                                <option value="Vietnamese" <?php echo (($_POST['citizenship'] ?? '') === 'Vietnamese') ? 'selected' : ''; ?>>Vietnamese</option>
                                <option value="Other" <?php echo (($_POST['citizenship'] ?? '') === 'Other') ? 'selected' : ''; ?>>Other</option>
                            </select>
                        </div>
                    </div>
                    <div class="form-group">
                        <label>*Have you been disciplined for any infraction or violation of school rules and regulations?</label>
                        <div style="margin-top: 10px;">
                            <label><input type="radio" name="disciplinary_history" value="Yes" <?php echo (($_POST['disciplinary_history'] ?? '') === 'Yes') ? 'checked' : ''; ?>> Yes, specify</label>
                            <input type="text" name="disciplinary_specification" class="form-control" style="margin-top: 5px; display: inline-block; width: 300px;" 
                                   value="<?php echo sanitizeOutput($_POST['disciplinary_specification'] ?? ''); ?>" 
                                   placeholder="Specify violation">
                            <br><label style="margin-top: 10px;"><input type="radio" name="disciplinary_history" value="No" <?php echo (($_POST['disciplinary_history'] ?? 'No') === 'No') ? 'checked' : ''; ?>> No</label>
                        </div>
                    </div>
                </div>

                <!-- Pledges & Consents -->
                <div class="form-section">
                    <h2>Pledges & Consents</h2>
                    <div class="form-group">
                        <label>*Do you promise not to take any prohibited drugs as long as you are connected with this university?</label>
                        <label><input type="checkbox" name="pledge_no_drugs" value="1" required <?php echo (isset($_POST['pledge_no_drugs'])) ? 'checked' : ''; ?>> Yes</label>
                    </div>
                    <div class="form-group">
                        <label>*Do you promise not to participate in any unlawful act or any illegal assembly?</label>
                        <label><input type="checkbox" name="pledge_no_unlawful_acts" value="1" required <?php echo (isset($_POST['pledge_no_unlawful_acts'])) ? 'checked' : ''; ?>> Yes</label>
                    </div>
                    <div class="form-group">
                        <label>*I allow this university to use my personal information, photos, videos and audio recordings for marketing and promotion of the university</label>
                        <label><input type="checkbox" name="consent_marketing" value="1" required <?php echo (isset($_POST['consent_marketing'])) ? 'checked' : ''; ?>> Yes</label>
                    </div>
                    <div class="form-group">
                        <label>*I confirm my consent to this university for the processing of personal information data relating to me for university administration, student and graduate studies and management purposes by choosing this option.</label>
                        <label><input type="checkbox" name="consent_data_processing" value="1" required <?php echo (isset($_POST['consent_data_processing'])) ? 'checked' : ''; ?>> Yes</label>
                    </div>
                    <div class="form-group">
                        <label>Did someone from this university assist you during your pre-registration?</label>
                        <label><input type="checkbox" name="assistance_received" value="1" <?php echo (isset($_POST['assistance_received'])) ? 'checked' : ''; ?>> Yes</label>
                    </div>
                </div>

                <!-- Student's Pledge -->
                <div class="form-section">
                    <h2 style="text-align: center;">STUDENT'S PLEDGE</h2>
                    <p style="text-align: center; font-style: italic; margin: 20px 0;">
                        I promise to faithfully perform my duties and responsibilities as a student in this University and abide by its policies, rules and regulations and certify that all statements and information are true and correct.
                    </p>
                    <div class="form-row">
                        <div class="form-group">
                            <label>*Attested/Witness by</label>
                            <input type="text" name="pledge_attested_by" class="form-control" 
                                   value="<?php echo sanitizeOutput($_POST['pledge_attested_by'] ?? ''); ?>">
                        </div>
                        <div class="form-group">
                            <label>*Relationship</label>
                            <input type="text" name="pledge_relationship" class="form-control" 
                                   value="<?php echo sanitizeOutput($_POST['pledge_relationship'] ?? ''); ?>">
                        </div>
                    </div>
                </div>

                <div class="form-group" style="margin-top: 20px;">
                    <label>Additional Notes</label>
                    <textarea name="notes" class="form-control" rows="3" 
                              placeholder="Any additional information you'd like to provide..."><?php echo sanitizeOutput($_POST['notes'] ?? ''); ?></textarea>
                </div>

                <div class="form-actions" style="text-align: center; margin: 2rem 0;">
                    <button type="submit" class="btn btn-primary" style="font-size: 1.1rem; padding: 1rem 3rem;">
                        <i class="fas fa-paper-plane"></i> Submit Registration
                    </button>
                    <button type="reset" class="btn btn-secondary" style="margin-left: 1rem; padding: 1rem 2rem;">Clear Form</button>
                </div>
            </form>
        <?php endif; ?>
    </div>

    <script>
        function getSearchInputFor(select) {
            if (!select) {
                return null;
            }
            const inputId = select.getAttribute('data-search-input');
            return inputId ? document.getElementById(inputId) : null;
        }

        function applySelectSearch(select) {
            if (!select) {
                return;
            }

            const searchInput = getSearchInputFor(select);
            const term = searchInput ? searchInput.value.trim().toLowerCase() : '';
            let visibleCount = 0;

            Array.from(select.options).forEach(function(option) {
                if (!option.value) {
                    option.hidden = false;
                    return;
                }

                const matches = !term || option.textContent.toLowerCase().includes(term);
                const shouldHide = !matches && option.value !== select.value;
                option.hidden = shouldHide;

                if (!shouldHide) {
                    visibleCount++;
                }
            });

            const messageId = select.getAttribute('data-no-results');
            if (messageId) {
                const messageEl = document.getElementById(messageId);
                if (messageEl) {
                    messageEl.style.display = visibleCount === 0 && term.length > 0 ? 'block' : 'none';
                }
            }
        }

        function setupSelectSearch(select) {
            if (!select) {
                return;
            }
            const searchInput = getSearchInputFor(select);
            if (searchInput) {
                searchInput.addEventListener('input', function() {
                    applySelectSearch(select);
                });
            }
            applySelectSearch(select);
        }

        function copyAddressToCurrent() {
            if (document.getElementById('copy_home_address').checked) {
                document.querySelector('input[name="current_street"]').value = document.querySelector('input[name="home_street"]').value;
                document.querySelector('input[name="current_city"]').value = document.querySelector('input[name="home_city"]').value;
                document.querySelector('input[name="current_province"]').value = document.querySelector('input[name="home_province"]').value;
                document.querySelector('input[name="current_zipcode"]').value = document.querySelector('input[name="home_zipcode"]').value;
            }
        }
        
        // Store all programs initially
        let allProgramsData = [];
        
        // Filter programs based on selected department
        function filterPrograms() {
            const departmentSelect = document.getElementById('department_id');
            const programSelect = document.getElementById('program_id');
            const searchInput = document.getElementById('program_search');
            const noResultsElement = document.getElementById('program_search_empty');
            const selectedDepartmentId = departmentSelect.value;
            
            // Store all programs on first load if not already stored
            if (allProgramsData.length === 0) {
                const allOptions = programSelect.querySelectorAll('option[data-department-id]');
                allOptions.forEach(function(option) {
                    allProgramsData.push({
                        value: option.value,
                        text: option.textContent,
                        departmentId: option.getAttribute('data-department-id'),
                        duration: option.getAttribute('data-duration')
                    });
                });
            }
            
            // Clear current selection
            programSelect.innerHTML = '<option value="">Select Program</option>';
            
            if (!selectedDepartmentId) {
                // No department selected, show message
                programSelect.innerHTML = '<option value="">Select Program</option>';
                // Reset year level
                updateYearLevels();
                applySelectSearch(programSelect);
                return;
            }
            
            // Filter and show only programs that belong to the selected department
            let hasPrograms = false;
            allProgramsData.forEach(function(program) {
                const programDeptId = program.departmentId;
                if (programDeptId == selectedDepartmentId || programDeptId === null || programDeptId === '') {
                    // Include programs that match the department or have no department
                    const option = document.createElement('option');
                    option.value = program.value;
                    option.textContent = program.text;
                    option.setAttribute('data-department-id', programDeptId || '');
                    option.setAttribute('data-duration', program.duration || '4');
                    programSelect.appendChild(option);
                    hasPrograms = true;
                }
            });
            
            // If no programs found, show message
            if (!hasPrograms) {
                programSelect.innerHTML = '<option value="">No programs available for this department</option>';
            }
            
            // Reset year level when programs change
            updateYearLevels();
            applySelectSearch(programSelect);
        }
        
        // Store all majors initially
        let allMajorsData = [];
        
        // Update year levels based on selected program
        function updateYearLevels() {
            const programSelect = document.getElementById('program_id');
            const yearLevelSelect = document.getElementById('year_level');
            
            if (!programSelect || !yearLevelSelect) return;
            
            const selectedProgramId = programSelect.value;
            
            if (!selectedProgramId) {
                // No program selected, show all year levels but disabled
                yearLevelSelect.innerHTML = '<option value="">Select Program First</option>';
                updateMajors();
                return;
            }
            
            // Get the selected program option
            const selectedOption = programSelect.options[programSelect.selectedIndex];
            const duration = parseInt(selectedOption.getAttribute('data-duration')) || 4;
            
            // Clear and rebuild year level options based on program duration
            yearLevelSelect.innerHTML = '<option value="">Select Year Level</option>';
            
            for (let year = 1; year <= duration; year++) {
                const option = document.createElement('option');
                let yearText = '';
                if (year === 1) yearText = '1st Year';
                else if (year === 2) yearText = '2nd Year';
                else if (year === 3) yearText = '3rd Year';
                else if (year === 4) yearText = '4th Year';
                else if (year === 5) yearText = '5th Year';
                else yearText = year + 'th Year';
                
                option.value = yearText;
                option.textContent = yearText;
                option.setAttribute('data-year', year);
                yearLevelSelect.appendChild(option);
            }
            
            // Update majors when program changes
            updateMajors();
        }
        
        // Update majors based on selected program
        function updateMajors() {
            const programSelect = document.getElementById('program_id');
            const majorSelect = document.getElementById('major_id');
            const searchInput = document.getElementById('major_search');
            const noResultsElement = document.getElementById('major_search_empty');
            
            if (!programSelect || !majorSelect) return;
            
            const selectedProgramId = programSelect.value;
            
            // Store all majors on first load if not already stored
            if (allMajorsData.length === 0) {
                const allOptions = majorSelect.querySelectorAll('option[data-program-id]');
                allOptions.forEach(function(option) {
                    allMajorsData.push({
                        value: option.value,
                        text: option.textContent,
                        programId: option.getAttribute('data-program-id')
                    });
                });
            }
            
            // Clear current selection
            majorSelect.innerHTML = '<option value="">Select Major</option>';
            
            if (!selectedProgramId) {
                // No program selected, show message
                majorSelect.innerHTML = '<option value="">Select Major</option>';
                applySelectSearch(majorSelect);
                return;
            }
            
            // Filter and show only majors that belong to the selected program
            let hasMajors = false;
            allMajorsData.forEach(function(major) {
                const majorProgramId = major.programId;
                if (majorProgramId == selectedProgramId) {
                    // Include majors that match the program
                    const option = document.createElement('option');
                    option.value = major.value;
                    option.textContent = major.text;
                    option.setAttribute('data-program-id', majorProgramId);
                    majorSelect.appendChild(option);
                    hasMajors = true;
                }
            });
            
            // If no majors found, show message
            if (!hasMajors) {
                majorSelect.innerHTML = '<option value="">No majors available for this program</option>';
            }
            applySelectSearch(majorSelect);
        }
        
        // Show/hide disciplinary specification field
        document.addEventListener('DOMContentLoaded', function() {
            // Initialize program filtering on page load
            // Wait a bit to ensure all options are loaded
            setTimeout(function() {
                const programSelectEl = document.getElementById('program_id');
                const majorSelectEl = document.getElementById('major_id');
                setupSelectSearch(programSelectEl);
                setupSelectSearch(majorSelectEl);

                filterPrograms();
                // Restore selected program if it exists (from form error)
                const selectedProgramId = '<?php echo (int)($_POST['program_id'] ?? 0); ?>';
                if (selectedProgramId && programSelectEl) {
                    const programSelect = programSelectEl;
                    const option = programSelect.querySelector('option[value="' + selectedProgramId + '"]');
                    if (option) {
                        programSelect.value = selectedProgramId;
                        updateYearLevels();
                        // Restore selected year level if it exists
                        const selectedYearLevel = '<?php echo sanitizeOutput($_POST['year_level'] ?? ''); ?>';
                        if (selectedYearLevel && document.getElementById('year_level')) {
                            const yearLevelSelect = document.getElementById('year_level');
                            const yearOption = yearLevelSelect.querySelector('option[value="' + selectedYearLevel + '"]');
                            if (yearOption) {
                                yearLevelSelect.value = selectedYearLevel;
                            }
                        }
                        // Restore selected major if it exists
                        const selectedMajorId = '<?php echo (int)($_POST['major_id'] ?? 0); ?>';
                        if (selectedMajorId && majorSelectEl) {
                            const majorSelect = majorSelectEl;
                            const majorOption = majorSelect.querySelector('option[value="' + selectedMajorId + '"]');
                            if (majorOption) {
                                majorSelect.value = selectedMajorId;
                            }
                        }
                    }
                } else {
                    updateYearLevels();
                    updateMajors();
                }
            }, 100);
            
            const disciplinaryRadios = document.querySelectorAll('input[name="disciplinary_history"]');
            const specField = document.querySelector('input[name="disciplinary_specification"]');
            
            disciplinaryRadios.forEach(radio => {
                radio.addEventListener('change', function() {
                    if (this.value === 'Yes') {
                        specField.style.display = 'inline-block';
                        specField.required = true;
                    } else {
                        specField.style.display = 'none';
                        specField.required = false;
                        specField.value = '';
                    }
                });
            });
            
            // Initialize on page load
            if (document.querySelector('input[name="disciplinary_history"]:checked')?.value === 'Yes') {
                specField.style.display = 'inline-block';
                specField.required = true;
            } else {
                specField.style.display = 'none';
            }
        });
    </script>
</body>
</html>
