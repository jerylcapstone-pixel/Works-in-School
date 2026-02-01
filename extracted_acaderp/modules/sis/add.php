<?php
/**
 * Student Information System (SIS) - Add Student
 * Form to add a new student record
 */

require_once __DIR__ . '/../../config/config.php';
requireAuth([ROLE_ADMIN]);

$page_title = 'Add New Student';

$pdo = getDBConnection();
$error = '';
$success = false;

// Get programs, departments, majors, and faculty for dropdowns
$programs = [];
$departments = [];
$all_majors = [];
$advisors = [];

if ($pdo) {
    $programs = $pdo->query("SELECT program_id, program_name, program_code, department_id, duration_years FROM programs WHERE status = 'Active' ORDER BY program_name")->fetchAll();
    $departments = $pdo->query("SELECT department_id, department_name, department_code FROM departments ORDER BY department_name")->fetchAll();
    $advisors = $pdo->query("SELECT faculty_id, first_name, last_name FROM faculty WHERE status = 'Active' ORDER BY last_name, first_name")->fetchAll();
    
    // Get all majors
    try {
        $stmt = $pdo->query("SELECT major_id, program_id, major_name FROM majors WHERE status = 'Active' ORDER BY major_name");
        $all_majors = $stmt->fetchAll();
    } catch (PDOException $e) {
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
        return false;
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
    // Sanitize all input fields from admissions form
    $student_number = sanitizeInput($_POST['student_number'] ?? '');
    $first_name = sanitizeInput($_POST['first_name'] ?? '');
    $middle_name = sanitizeInput($_POST['middle_name'] ?? '');
    $last_name = sanitizeInput($_POST['last_name'] ?? '');
    $email = sanitizeInput($_POST['email'] ?? '');
    $date_of_birth = $_POST['date_of_birth'] ?? '';
    $gender = $_POST['gender'] ?? '';
    $phone = sanitizeInput($_POST['phone'] ?? '');
    $address = sanitizeInput($_POST['address'] ?? '');
    $city = sanitizeInput($_POST['city'] ?? '');
    $state = sanitizeInput($_POST['state'] ?? '');
    $zip_code = sanitizeInput($_POST['zip_code'] ?? '');
    $country = sanitizeInput($_POST['country'] ?? 'USA');
    $enrollment_date = $_POST['enrollment_date'] ?? date('Y-m-d');
    $program_id = !empty($_POST['program_id']) ? (int)$_POST['program_id'] : null;
    $major_id = !empty($_POST['major_id']) ? (int)$_POST['major_id'] : null;
    $advisor_id = !empty($_POST['advisor_id']) ? (int)$_POST['advisor_id'] : null;
    $status = sanitizeInput($_POST['status'] ?? 'Active');
    
    // Additional fields from admissions form
    $student_type = sanitizeInput($_POST['student_type'] ?? 'New');
    $id_number = sanitizeInput($_POST['id_number'] ?? '');
    $religion = sanitizeInput($_POST['religion'] ?? '');
    $civil_status = sanitizeInput($_POST['civil_status'] ?? '');
    $year_level = sanitizeInput($_POST['year_level'] ?? '');
    $term = sanitizeInput($_POST['term'] ?? '');
    
    // Family information
    $father_lastname = sanitizeInput($_POST['father_lastname'] ?? '');
    $father_firstname = sanitizeInput($_POST['father_firstname'] ?? '');
    $father_middlename = sanitizeInput($_POST['father_middlename'] ?? '');
    $mother_lastname = sanitizeInput($_POST['mother_lastname'] ?? '');
    $mother_firstname = sanitizeInput($_POST['mother_firstname'] ?? '');
    $mother_middlename = sanitizeInput($_POST['mother_middlename'] ?? '');
    $mother_maiden_middlename = sanitizeInput($_POST['mother_maiden_middlename'] ?? '');
    
    // Address information
    $home_street = sanitizeInput($_POST['home_street'] ?? '');
    $home_city = sanitizeInput($_POST['home_city'] ?? '');
    $home_province = sanitizeInput($_POST['home_province'] ?? '');
    $home_zipcode = sanitizeInput($_POST['home_zipcode'] ?? '');
    $current_street = sanitizeInput($_POST['current_street'] ?? '');
    $current_city = sanitizeInput($_POST['current_city'] ?? '');
    $current_province = sanitizeInput($_POST['current_province'] ?? '');
    $current_zipcode = sanitizeInput($_POST['current_zipcode'] ?? '');
    
    // Contact & Guardian
    $parents_contact = sanitizeInput($_POST['parents_contact'] ?? '');
    $guardian_name = sanitizeInput($_POST['guardian_name'] ?? '');
    $guardian_contact = sanitizeInput($_POST['guardian_contact'] ?? '');
    
    // Household information
    $dswd_household_no = sanitizeInput($_POST['dswd_household_no'] ?? '');
    $monthly_income = !empty($_POST['monthly_income']) ? (float)$_POST['monthly_income'] : null;
    $disability = sanitizeInput($_POST['disability'] ?? '');
    
    // Organizations
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
    
    // Citizenship & Conduct
    $citizenship = sanitizeInput($_POST['citizenship'] ?? '');
    $disciplinary_history = isset($_POST['disciplinary_history']) && $_POST['disciplinary_history'] === 'Yes' ? 1 : 0;
    $disciplinary_specification = sanitizeInput($_POST['disciplinary_specification'] ?? '');
    
    // Pledges & Consents
    $pledge_no_drugs = isset($_POST['pledge_no_drugs']) ? 1 : 0;
    $pledge_no_unlawful_acts = isset($_POST['pledge_no_unlawful_acts']) ? 1 : 0;
    $consent_marketing = isset($_POST['consent_marketing']) ? 1 : 0;
    $consent_data_processing = isset($_POST['consent_data_processing']) ? 1 : 0;
    $assistance_received = isset($_POST['assistance_received']) ? 1 : 0;
    $pledge_attested_by = sanitizeInput($_POST['pledge_attested_by'] ?? '');
    $pledge_relationship = sanitizeInput($_POST['pledge_relationship'] ?? '');
    $notes = sanitizeInput($_POST['notes'] ?? '');
    
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

    // Validation
    if (empty($student_number) || empty($first_name) || empty($last_name) || empty($date_of_birth) || empty($gender)) {
        $error = 'Please fill in all required fields.';
    } else {
        // Check if student number already exists
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM students WHERE student_number = ?");
        $stmt->execute([$student_number]);
        if ($stmt->fetchColumn() > 0) {
            $error = 'Student number already exists.';
        } else {
            // Check if email already exists in users table
            if (!empty($email)) {
                $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE email = ?");
                $stmt->execute([$email]);
                if ($stmt->fetchColumn() > 0) {
                    $error = 'An account with this email already exists.';
                }
            }
            
            if (empty($error)) {
                // Build dynamic INSERT query for students table
                // First, check which columns exist in students table
                $stmt = $pdo->query("SHOW COLUMNS FROM students");
                $columns = $stmt->fetchAll(PDO::FETCH_COLUMN);
                
                $fields = [];
                $values = [];
                $placeholders = [];
                
                // Core required fields
                $fields[] = 'student_number';
                $values[] = $student_number;
                $placeholders[] = '?';
                
                $fields[] = 'first_name';
                $values[] = $first_name;
                $placeholders[] = '?';
                
                $fields[] = 'last_name';
                $values[] = $last_name;
                $placeholders[] = '?';
                
                if (in_array('middle_name', $columns)) {
                    $fields[] = 'middle_name';
                    $values[] = $middle_name ?: null;
                    $placeholders[] = '?';
                }
                
                $fields[] = 'date_of_birth';
                $values[] = $date_of_birth;
                $placeholders[] = '?';
                
                $fields[] = 'gender';
                $values[] = $gender;
                $placeholders[] = '?';
                
                if (in_array('phone', $columns)) {
                    $fields[] = 'phone';
                    $values[] = $phone ?: null;
                    $placeholders[] = '?';
                }
                
                // Use home address if provided, otherwise use address field
                $street_address = !empty($home_street) ? $home_street : $address;
                $address_city = !empty($home_city) ? $home_city : $city;
                $address_state = !empty($home_province) ? $home_province : $state;
                $address_zip = !empty($home_zipcode) ? $home_zipcode : $zip_code;
                
                if (in_array('address', $columns)) {
                    $fields[] = 'address';
                    $values[] = $street_address ?: null;
                    $placeholders[] = '?';
                }
                
                if (in_array('city', $columns)) {
                    $fields[] = 'city';
                    $values[] = $address_city ?: null;
                    $placeholders[] = '?';
                }
                
                if (in_array('state', $columns)) {
                    $fields[] = 'state';
                    $values[] = $address_state ?: null;
                    $placeholders[] = '?';
                }
                
                if (in_array('zip_code', $columns)) {
                    $fields[] = 'zip_code';
                    $values[] = $address_zip ?: null;
                    $placeholders[] = '?';
                }
                
                if (in_array('country', $columns)) {
                    $fields[] = 'country';
                    $values[] = $country;
                    $placeholders[] = '?';
                }
                
                $fields[] = 'enrollment_date';
                $values[] = $enrollment_date;
                $placeholders[] = '?';
                
                if (in_array('program_id', $columns)) {
                    $fields[] = 'program_id';
                    $values[] = $program_id;
                    $placeholders[] = '?';
                }
                
                if (in_array('advisor_id', $columns)) {
                    $fields[] = 'advisor_id';
                    $values[] = $advisor_id;
                    $placeholders[] = '?';
                }
                
                $fields[] = 'status';
                $values[] = $status;
                $placeholders[] = '?';
                
                // Insert into students table
                $sql = "INSERT INTO students (" . implode(', ', $fields) . ") VALUES (" . implode(', ', $placeholders) . ")";
                $stmt = $pdo->prepare($sql);
                $result = $stmt->execute($values);
                
                if ($result) {
                    $student_id = $pdo->lastInsertId();
                    
                    // Also create admission application record with all additional details
                    try {
                        $app_fields = [];
                        $app_values = [];
                        $app_placeholders = [];
                        
                        $app_fields[] = 'student_number';
                        $app_values[] = $student_number;
                        $app_placeholders[] = '?';
                        
                        $app_fields[] = 'first_name';
                        $app_values[] = $first_name;
                        $app_placeholders[] = '?';
                        
                        $app_fields[] = 'last_name';
                        $app_values[] = $last_name;
                        $app_placeholders[] = '?';
                        
                        if (!empty($email)) {
                            $app_fields[] = 'email';
                            $app_values[] = $email;
                            $app_placeholders[] = '?';
                        }
                        
                        if (!empty($middle_name)) {
                            $app_fields[] = 'middle_name';
                            $app_values[] = $middle_name;
                            $app_placeholders[] = '?';
                        }
                        
                        if (!empty($phone)) {
                            $app_fields[] = 'phone';
                            $app_values[] = $phone;
                            $app_placeholders[] = '?';
                        }
                        
                        if (!empty($date_of_birth)) {
                            $app_fields[] = 'date_of_birth';
                            $app_values[] = $date_of_birth;
                            $app_placeholders[] = '?';
                        }
                        
                        if (!empty($gender)) {
                            $app_fields[] = 'gender';
                            $app_values[] = $gender;
                            $app_placeholders[] = '?';
                        }
                        
                        if (!empty($program_id)) {
                            $app_fields[] = 'program_id';
                            $app_values[] = $program_id;
                            $app_placeholders[] = '?';
                        }
                        
                        // Add all other fields that exist in admission_applications
                        $additional_fields = [
                            'student_type', 'id_number', 'religion', 'civil_status', 'photo_path',
                            'year_level', 'major', 'term',
                            'father_lastname', 'father_firstname', 'father_middlename',
                            'mother_lastname', 'mother_firstname', 'mother_middlename', 'mother_maiden_middlename',
                            'home_street', 'home_city', 'home_province', 'home_zipcode',
                            'current_street', 'current_city', 'current_province', 'current_zipcode',
                            'parents_contact', 'guardian_name', 'guardian_contact',
                            'dswd_household_no', 'monthly_income', 'disability',
                            'citizenship', 'disciplinary_history', 'disciplinary_specification',
                            'pledge_no_drugs', 'pledge_no_unlawful_acts', 'consent_marketing',
                            'consent_data_processing', 'assistance_received',
                            'pledge_attested_by', 'pledge_relationship', 'notes'
                        ];
                        
                        $app_stmt = $pdo->query("SHOW COLUMNS FROM admission_applications");
                        $app_columns = $app_stmt->fetchAll(PDO::FETCH_COLUMN);
                        
                        foreach ($additional_fields as $field) {
                            if (in_array($field, $app_columns)) {
                                $value = null;
                                switch($field) {
                                    case 'photo_path':
                                        $value = $photo_path;
                                        break;
                                    case 'organizations':
                                        $value = !empty($organizations) ? json_encode($organizations) : null;
                                        break;
                                    case 'monthly_income':
                                        $value = $monthly_income;
                                        break;
                                    case 'disciplinary_history':
                                    case 'pledge_no_drugs':
                                    case 'pledge_no_unlawful_acts':
                                    case 'consent_marketing':
                                    case 'consent_data_processing':
                                    case 'assistance_received':
                                        $value = $$field;
                                        break;
                                    default:
                                        $value = $$field ?: null;
                                }
                                
                                if ($value !== null && $value !== '') {
                                    $app_fields[] = $field;
                                    $app_values[] = $value;
                                    $app_placeholders[] = '?';
                                }
                            }
                        }
                        
                        // Add organizations if column exists
                        if (in_array('organizations', $app_columns) && !empty($organizations)) {
                            $app_fields[] = 'organizations';
                            $app_values[] = json_encode($organizations);
                            $app_placeholders[] = '?';
                        }
                        
                        // Add major_id if column exists
                        if (in_array('major_id', $app_columns) && !empty($major_id)) {
                            $app_fields[] = 'major_id';
                            $app_values[] = $major_id;
                            $app_placeholders[] = '?';
                        }
                        
                        $app_fields[] = 'application_date';
                        $app_values[] = date('Y-m-d');
                        $app_placeholders[] = '?';
                        
                        $app_fields[] = 'status';
                        $app_values[] = 'Approved'; // Since admin is adding directly
                        $app_placeholders[] = '?';
                        
                        if (count($app_fields) > 3) { // More than just student_number, first_name, last_name
                            $app_sql = "INSERT INTO admission_applications (" . implode(', ', $app_fields) . ") VALUES (" . implode(', ', $app_placeholders) . ")";
                            $app_stmt = $pdo->prepare($app_sql);
                            $app_stmt->execute($app_values);
                        }
                    } catch (PDOException $e) {
                        // Log error but don't fail student creation
                        error_log("Failed to create admission application record: " . $e->getMessage());
                    }
                    
                    header('Location: ' . BASE_URL . 'modules/sis/index.php?success=Student added successfully');
                    exit;
                } else {
                    $error = 'Failed to add student. Please try again.';
                }
            }
        }
    }
}

include __DIR__ . '/../../includes/header.php';
?>

<div class="page-container">
    <div class="page-header">
        <h1><i class="fas fa-user-plus"></i> Add New Student</h1>
        <a href="<?php echo BASE_URL; ?>modules/sis/index.php" class="btn btn-secondary">
            <i class="fas fa-arrow-left"></i> Back to List
        </a>
    </div>

    <?php if ($error): ?>
    <div class="alert alert-error">
        <i class="fas fa-exclamation-circle"></i> <?php echo sanitizeOutput($error); ?>
    </div>
    <?php endif; ?>

    <div class="form-card">
        <form method="POST" action="" class="student-form" enctype="multipart/form-data">
            <!-- Academic Information -->
            <div class="form-section">
                <h3><i class="fas fa-graduation-cap"></i> Academic Information</h3>
                <div class="form-row">
                    <div class="form-group required">
                        <label for="student_number">Student Number *</label>
                        <input type="text" id="student_number" name="student_number" required
                               value="<?php echo isset($_POST['student_number']) ? sanitizeOutput($_POST['student_number']) : ''; ?>"
                               placeholder="e.g., STU-2024-001">
                    </div>
                    <div class="form-group required">
                        <label for="enrollment_date">Enrollment Date *</label>
                        <input type="date" id="enrollment_date" name="enrollment_date" required
                               value="<?php echo isset($_POST['enrollment_date']) ? sanitizeOutput($_POST['enrollment_date']) : date('Y-m-d'); ?>">
                    </div>
                    <div class="form-group">
                        <label for="student_type">Student Type</label>
                        <select id="student_type" name="student_type">
                            <option value="New" <?php echo (($_POST['student_type'] ?? 'New') === 'New') ? 'selected' : ''; ?>>New</option>
                            <option value="Old" <?php echo (($_POST['student_type'] ?? '') === 'Old') ? 'selected' : ''; ?>>Old</option>
                            <option value="Transferee" <?php echo (($_POST['student_type'] ?? '') === 'Transferee') ? 'selected' : ''; ?>>Transferee</option>
                            <option value="Returnee" <?php echo (($_POST['student_type'] ?? '') === 'Returnee') ? 'selected' : ''; ?>>Returnee</option>
                        </select>
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label for="department_id">Department</label>
                        <select id="department_id" name="department_id" onchange="filterPrograms()">
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
                        <label for="program_id">Program/Course *</label>
                        <input type="text" id="program_search" placeholder="Search programs..." style="margin-bottom: 8px; width: 100%; padding: 0.625rem 0.875rem; border: 1px solid #e2e8f0; border-radius: 0.5rem;">
                        <select id="program_id" name="program_id" required onchange="updateYearLevels()" data-search-input="program_search">
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
                    </div>
                    <div class="form-group">
                        <label for="major_id">Major</label>
                        <input type="text" id="major_search" placeholder="Search majors..." style="margin-bottom: 8px; width: 100%; padding: 0.625rem 0.875rem; border: 1px solid #e2e8f0; border-radius: 0.5rem;">
                        <select id="major_id" name="major_id" data-search-input="major_search">
                            <option value="">Select Major</option>
                            <?php foreach ($all_majors as $major): ?>
                                <option value="<?php echo $major['major_id']; ?>"
                                        data-program-id="<?php echo $major['program_id']; ?>"
                                        <?php echo (($_POST['major_id'] ?? '') == $major['major_id']) ? 'selected' : ''; ?>>
                                    <?php echo sanitizeOutput($major['major_name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label for="year_level">Year Level</label>
                        <select id="year_level" name="year_level">
                            <option value="">Select Year Level</option>
                            <option value="1st Year" data-year="1">1st Year</option>
                            <option value="2nd Year" data-year="2">2nd Year</option>
                            <option value="3rd Year" data-year="3">3rd Year</option>
                            <option value="4th Year" data-year="4">4th Year</option>
                            <option value="5th Year" data-year="5">5th Year</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="term">Term</label>
                        <input type="text" id="term" name="term" 
                               value="<?php echo sanitizeOutput($_POST['term'] ?? 'FIRST SEMESTER 2025-2026'); ?>">
                    </div>
                    <div class="form-group">
                        <label for="advisor_id">Academic Advisor</label>
                        <select id="advisor_id" name="advisor_id">
                            <option value="">Select Advisor</option>
                            <?php foreach ($advisors as $advisor): ?>
                            <option value="<?php echo $advisor['faculty_id']; ?>"
                                    <?php echo (isset($_POST['advisor_id']) && $_POST['advisor_id'] == $advisor['faculty_id']) ? 'selected' : ''; ?>>
                                <?php echo sanitizeOutput($advisor['first_name'] . ' ' . $advisor['last_name']); ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group required">
                        <label for="status">Status *</label>
                        <select id="status" name="status" required>
                            <option value="Active" <?php echo (!isset($_POST['status']) || $_POST['status'] === 'Active') ? 'selected' : ''; ?>>Active</option>
                            <option value="Graduated" <?php echo (isset($_POST['status']) && $_POST['status'] === 'Graduated') ? 'selected' : ''; ?>>Graduated</option>
                            <option value="Transferred" <?php echo (isset($_POST['status']) && $_POST['status'] === 'Transferred') ? 'selected' : ''; ?>>Transferred</option>
                            <option value="Suspended" <?php echo (isset($_POST['status']) && $_POST['status'] === 'Suspended') ? 'selected' : ''; ?>>Suspended</option>
                            <option value="Withdrawn" <?php echo (isset($_POST['status']) && $_POST['status'] === 'Withdrawn') ? 'selected' : ''; ?>>Withdrawn</option>
                        </select>
                    </div>
                </div>
            </div>

            <!-- Photo Upload -->
            <div class="form-section">
                <h3><i class="fas fa-camera"></i> Upload Photo</h3>
                <div style="text-align: center; padding: 2rem; border: 2px dashed #cbd5e1; border-radius: 12px; background: #f8fafc;">
                    <div style="font-size: 3rem; color: #94a3b8; margin-bottom: 1rem;"><i class="fas fa-user-circle"></i></div>
                    <p><strong>UPLOAD PHOTO</strong></p>
                    <p>Please select a VISA-PASSPORT size photo.</p>
                    <p style="color: #666; font-size: 12px;">[.jpg, .png files only]</p>
                    <input type="file" name="photo" accept="image/jpeg,image/jpg,image/png" style="margin-top: 10px;">
                </div>
            </div>

            <!-- Personal Information -->
            <div class="form-section">
                <h3><i class="fas fa-id-card"></i> Personal Information</h3>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="id_number">ID Number</label>
                        <input type="text" id="id_number" name="id_number"
                               value="<?php echo isset($_POST['id_number']) ? sanitizeOutput($_POST['id_number']) : ''; ?>">
                    </div>
                    <div class="form-group">
                        <label for="religion">Religion</label>
                        <input type="text" id="religion" name="religion"
                               value="<?php echo isset($_POST['religion']) ? sanitizeOutput($_POST['religion']) : ''; ?>">
                    </div>
                    <div class="form-group">
                        <label for="civil_status">Civil Status</label>
                        <select id="civil_status" name="civil_status">
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
                        <label for="phone">Contact No</label>
                        <input type="text" id="phone" name="phone"
                               value="<?php echo isset($_POST['phone']) ? sanitizeOutput($_POST['phone']) : ''; ?>"
                               placeholder="+1 (555) 123-4567">
                    </div>
                    <div class="form-group">
                        <label for="email">Email Address</label>
                        <input type="email" id="email" name="email"
                               value="<?php echo isset($_POST['email']) ? sanitizeOutput($_POST['email']) : ''; ?>">
                        <small style="color: #64748b; font-size: 0.875rem;">You can create a user account with this email later</small>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group required">
                        <label for="first_name">First Name *</label>
                        <input type="text" id="first_name" name="first_name" required
                               value="<?php echo isset($_POST['first_name']) ? sanitizeOutput($_POST['first_name']) : ''; ?>">
                    </div>
                    <div class="form-group">
                        <label for="middle_name">Middle Name</label>
                        <input type="text" id="middle_name" name="middle_name"
                               value="<?php echo isset($_POST['middle_name']) ? sanitizeOutput($_POST['middle_name']) : ''; ?>">
                    </div>
                    <div class="form-group required">
                        <label for="last_name">Last Name *</label>
                        <input type="text" id="last_name" name="last_name" required
                               value="<?php echo isset($_POST['last_name']) ? sanitizeOutput($_POST['last_name']) : ''; ?>">
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group required">
                        <label for="date_of_birth">Date of Birth *</label>
                        <input type="date" id="date_of_birth" name="date_of_birth" required
                               value="<?php echo isset($_POST['date_of_birth']) ? sanitizeOutput($_POST['date_of_birth']) : ''; ?>">
                    </div>
                    <div class="form-group required">
                        <label for="gender">Gender *</label>
                        <select id="gender" name="gender" required>
                            <option value="">Select Gender</option>
                            <option value="Male" <?php echo (isset($_POST['gender']) && $_POST['gender'] === 'Male') ? 'selected' : ''; ?>>Male</option>
                            <option value="Female" <?php echo (isset($_POST['gender']) && $_POST['gender'] === 'Female') ? 'selected' : ''; ?>>Female</option>
                            <option value="Other" <?php echo (isset($_POST['gender']) && $_POST['gender'] === 'Other') ? 'selected' : ''; ?>>Other</option>
                        </select>
                    </div>
                </div>
            </div>

            <!-- Father's Name -->
            <div class="form-section">
                <h3><i class="fas fa-user-tie"></i> Father's Name</h3>
                <div class="form-row">
                    <div class="form-group">
                        <label for="father_lastname">Lastname</label>
                        <input type="text" id="father_lastname" name="father_lastname"
                               value="<?php echo isset($_POST['father_lastname']) ? sanitizeOutput($_POST['father_lastname']) : ''; ?>">
                    </div>
                    <div class="form-group">
                        <label for="father_firstname">Firstname (e.g., JOHN SR.)</label>
                        <input type="text" id="father_firstname" name="father_firstname"
                               value="<?php echo isset($_POST['father_firstname']) ? sanitizeOutput($_POST['father_firstname']) : ''; ?>"
                               placeholder="e.g., JOHN SR.">
                    </div>
                    <div class="form-group">
                        <label for="father_middlename">Middle Name</label>
                        <input type="text" id="father_middlename" name="father_middlename"
                               value="<?php echo isset($_POST['father_middlename']) ? sanitizeOutput($_POST['father_middlename']) : ''; ?>">
                    </div>
                </div>
            </div>

            <!-- Mother's Name -->
            <div class="form-section">
                <h3><i class="fas fa-user"></i> Mother's Name</h3>
                <div class="form-row">
                    <div class="form-group">
                        <label for="mother_lastname">Lastname</label>
                        <input type="text" id="mother_lastname" name="mother_lastname"
                               value="<?php echo isset($_POST['mother_lastname']) ? sanitizeOutput($_POST['mother_lastname']) : ''; ?>">
                    </div>
                    <div class="form-group">
                        <label for="mother_firstname">Firstname</label>
                        <input type="text" id="mother_firstname" name="mother_firstname"
                               value="<?php echo isset($_POST['mother_firstname']) ? sanitizeOutput($_POST['mother_firstname']) : ''; ?>">
                    </div>
                    <div class="form-group">
                        <label for="mother_middlename">Middle Name</label>
                        <input type="text" id="mother_middlename" name="mother_middlename"
                               value="<?php echo isset($_POST['mother_middlename']) ? sanitizeOutput($_POST['mother_middlename']) : ''; ?>">
                    </div>
                </div>
                <div class="form-group">
                    <label for="mother_maiden_middlename">Maiden Middle Name</label>
                    <input type="text" id="mother_maiden_middlename" name="mother_maiden_middlename"
                           value="<?php echo isset($_POST['mother_maiden_middlename']) ? sanitizeOutput($_POST['mother_maiden_middlename']) : ''; ?>">
                </div>
            </div>

            <!-- Home Address -->
            <div class="form-section">
                <h3><i class="fas fa-home"></i> Home Address</h3>
                <div class="form-row">
                    <div class="form-group">
                        <label for="home_street">Street/Purok/Barangay</label>
                        <input type="text" id="home_street" name="home_street"
                               value="<?php echo isset($_POST['home_street']) ? sanitizeOutput($_POST['home_street']) : ''; ?>">
                    </div>
                    <div class="form-group">
                        <label for="home_city">City/Town</label>
                        <input type="text" id="home_city" name="home_city"
                               value="<?php echo isset($_POST['home_city']) ? sanitizeOutput($_POST['home_city']) : ''; ?>">
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label for="home_province">Province</label>
                        <input type="text" id="home_province" name="home_province"
                               value="<?php echo isset($_POST['home_province']) ? sanitizeOutput($_POST['home_province']) : ''; ?>">
                    </div>
                    <div class="form-group">
                        <label for="home_zipcode">Zip Code</label>
                        <input type="text" id="home_zipcode" name="home_zipcode"
                               value="<?php echo isset($_POST['home_zipcode']) ? sanitizeOutput($_POST['home_zipcode']) : ''; ?>">
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
                <h3><i class="fas fa-map-marker-alt"></i> Current Address</h3>
                <div class="form-row">
                    <div class="form-group">
                        <label for="current_street">Street/Purok/Barangay</label>
                        <input type="text" id="current_street" name="current_street"
                               value="<?php echo isset($_POST['current_street']) ? sanitizeOutput($_POST['current_street']) : ''; ?>">
                    </div>
                    <div class="form-group">
                        <label for="current_city">City/Town</label>
                        <input type="text" id="current_city" name="current_city"
                               value="<?php echo isset($_POST['current_city']) ? sanitizeOutput($_POST['current_city']) : ''; ?>">
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label for="current_province">Province</label>
                        <input type="text" id="current_province" name="current_province"
                               value="<?php echo isset($_POST['current_province']) ? sanitizeOutput($_POST['current_province']) : ''; ?>">
                    </div>
                    <div class="form-group">
                        <label for="current_zipcode">Zip Code</label>
                        <input type="text" id="current_zipcode" name="current_zipcode"
                               value="<?php echo isset($_POST['current_zipcode']) ? sanitizeOutput($_POST['current_zipcode']) : ''; ?>">
                    </div>
                </div>
            </div>

            <!-- Contact & Guardian Information -->
            <div class="form-section">
                <h3><i class="fas fa-phone"></i> Contact & Guardian Information</h3>
                <div class="form-row">
                    <div class="form-group">
                        <label for="parents_contact">Parents Contact No.</label>
                        <input type="text" id="parents_contact" name="parents_contact"
                               value="<?php echo isset($_POST['parents_contact']) ? sanitizeOutput($_POST['parents_contact']) : ''; ?>">
                    </div>
                    <div class="form-group">
                        <label for="guardian_name">Name of Guardian/Landlady/Landlord</label>
                        <input type="text" id="guardian_name" name="guardian_name"
                               value="<?php echo isset($_POST['guardian_name']) ? sanitizeOutput($_POST['guardian_name']) : ''; ?>">
                    </div>
                    <div class="form-group">
                        <label for="guardian_contact">Guardian Contact No.</label>
                        <input type="text" id="guardian_contact" name="guardian_contact"
                               value="<?php echo isset($_POST['guardian_contact']) ? sanitizeOutput($_POST['guardian_contact']) : ''; ?>">
                    </div>
                </div>
            </div>

            <!-- Household & Financial Information -->
            <div class="form-section">
                <h3><i class="fas fa-dollar-sign"></i> Household & Financial Information</h3>
                <div class="form-row">
                    <div class="form-group">
                        <label for="dswd_household_no">DSWD Household No (If Available)</label>
                        <input type="text" id="dswd_household_no" name="dswd_household_no"
                               value="<?php echo isset($_POST['dswd_household_no']) ? sanitizeOutput($_POST['dswd_household_no']) : ''; ?>">
                    </div>
                    <div class="form-group">
                        <label for="monthly_income">Monthly Income</label>
                        <input type="number" step="0.01" id="monthly_income" name="monthly_income"
                               value="<?php echo isset($_POST['monthly_income']) ? sanitizeOutput($_POST['monthly_income']) : ''; ?>">
                    </div>
                    <div class="form-group">
                        <label for="disability">Disability (if any)</label>
                        <input type="text" id="disability" name="disability"
                               value="<?php echo isset($_POST['disability']) ? sanitizeOutput($_POST['disability']) : ''; ?>">
                    </div>
                </div>
            </div>

            <!-- Organizations -->
            <div class="form-section">
                <h3><i class="fas fa-users"></i> Organization(s) Affiliated (Leave blank if not applicable)</h3>
                <div id="organizations-container">
                    <div style="display: grid; grid-template-columns: 2fr 2fr 1fr; gap: 1rem; margin-bottom: 1rem;">
                        <input type="text" name="org_name[]" placeholder="Organization Name">
                        <input type="text" name="org_position[]" placeholder="Position Held">
                        <input type="text" name="org_term[]" placeholder="Term">
                    </div>
                    <div style="display: grid; grid-template-columns: 2fr 2fr 1fr; gap: 1rem; margin-bottom: 1rem;">
                        <input type="text" name="org_name[]" placeholder="Organization Name">
                        <input type="text" name="org_position[]" placeholder="Position Held">
                        <input type="text" name="org_term[]" placeholder="Term">
                    </div>
                    <div style="display: grid; grid-template-columns: 2fr 2fr 1fr; gap: 1rem; margin-bottom: 1rem;">
                        <input type="text" name="org_name[]" placeholder="Organization Name">
                        <input type="text" name="org_position[]" placeholder="Position Held">
                        <input type="text" name="org_term[]" placeholder="Term">
                    </div>
                </div>
            </div>

            <!-- Citizenship & Conduct -->
            <div class="form-section">
                <h3><i class="fas fa-globe"></i> Citizenship & Conduct</h3>
                <div class="form-row">
                    <div class="form-group">
                        <label for="citizenship">Citizenship</label>
                        <select id="citizenship" name="citizenship">
                            <option value="">Select Citizenship</option>
                            <option value="Filipino" <?php echo (($_POST['citizenship'] ?? '') === 'Filipino') ? 'selected' : ''; ?>>Filipino</option>
                            <option value="American" <?php echo (($_POST['citizenship'] ?? '') === 'American') ? 'selected' : ''; ?>>American</option>
                            <option value="Australian" <?php echo (($_POST['citizenship'] ?? '') === 'Australian') ? 'selected' : ''; ?>>Australian</option>
                            <option value="British" <?php echo (($_POST['citizenship'] ?? '') === 'British') ? 'selected' : ''; ?>>British</option>
                            <option value="Canadian" <?php echo (($_POST['citizenship'] ?? '') === 'Canadian') ? 'selected' : ''; ?>>Canadian</option>
                            <option value="Chinese" <?php echo (($_POST['citizenship'] ?? '') === 'Chinese') ? 'selected' : ''; ?>>Chinese</option>
                            <option value="Other" <?php echo (($_POST['citizenship'] ?? '') === 'Other') ? 'selected' : ''; ?>>Other</option>
                        </select>
                    </div>
                </div>
                <div class="form-group">
                    <label>Have you been disciplined for any infraction or violation of school rules and regulations?</label>
                    <div style="margin-top: 10px;">
                        <label><input type="radio" name="disciplinary_history" value="Yes" <?php echo (($_POST['disciplinary_history'] ?? '') === 'Yes') ? 'checked' : ''; ?>> Yes, specify</label>
                        <input type="text" name="disciplinary_specification" style="margin-top: 5px; display: inline-block; width: 300px; padding: 0.5rem; border: 1px solid #e2e8f0; border-radius: 0.5rem;" 
                               value="<?php echo isset($_POST['disciplinary_specification']) ? sanitizeOutput($_POST['disciplinary_specification']) : ''; ?>" 
                               placeholder="Specify violation">
                        <br><label style="margin-top: 10px;"><input type="radio" name="disciplinary_history" value="No" <?php echo (($_POST['disciplinary_history'] ?? 'No') === 'No') ? 'checked' : ''; ?>> No</label>
                    </div>
                </div>
            </div>

            <!-- Pledges & Consents -->
            <div class="form-section">
                <h3><i class="fas fa-handshake"></i> Pledges & Consents</h3>
                <div class="form-group">
                    <label>
                        <input type="checkbox" name="pledge_no_drugs" value="1" <?php echo (isset($_POST['pledge_no_drugs'])) ? 'checked' : ''; ?>>
                        Do you promise not to take any prohibited drugs as long as you are connected with this university?
                    </label>
                </div>
                <div class="form-group">
                    <label>
                        <input type="checkbox" name="pledge_no_unlawful_acts" value="1" <?php echo (isset($_POST['pledge_no_unlawful_acts'])) ? 'checked' : ''; ?>>
                        Do you promise not to participate in any unlawful act or any illegal assembly?
                    </label>
                </div>
                <div class="form-group">
                    <label>
                        <input type="checkbox" name="consent_marketing" value="1" <?php echo (isset($_POST['consent_marketing'])) ? 'checked' : ''; ?>>
                        I allow this university to use my personal information, photos, videos and audio recordings for marketing and promotion of the university
                    </label>
                </div>
                <div class="form-group">
                    <label>
                        <input type="checkbox" name="consent_data_processing" value="1" <?php echo (isset($_POST['consent_data_processing'])) ? 'checked' : ''; ?>>
                        I confirm my consent to this university for the processing of personal information data relating to me for university administration, student and graduate studies and management purposes
                    </label>
                </div>
                <div class="form-group">
                    <label>
                        <input type="checkbox" name="assistance_received" value="1" <?php echo (isset($_POST['assistance_received'])) ? 'checked' : ''; ?>>
                        Did someone from this university assist you during your pre-registration?
                    </label>
                </div>
            </div>

            <!-- Student's Pledge -->
            <div class="form-section">
                <h3 style="text-align: center;"><i class="fas fa-file-signature"></i> STUDENT'S PLEDGE</h3>
                <p style="text-align: center; font-style: italic; margin: 20px 0; padding: 1rem; background: #f8fafc; border-radius: 8px;">
                    I promise to faithfully perform my duties and responsibilities as a student in this University and abide by its policies, rules and regulations and certify that all statements and information are true and correct.
                </p>
                <div class="form-row">
                    <div class="form-group">
                        <label for="pledge_attested_by">Attested/Witness by</label>
                        <input type="text" id="pledge_attested_by" name="pledge_attested_by"
                               value="<?php echo isset($_POST['pledge_attested_by']) ? sanitizeOutput($_POST['pledge_attested_by']) : ''; ?>">
                    </div>
                    <div class="form-group">
                        <label for="pledge_relationship">Relationship</label>
                        <input type="text" id="pledge_relationship" name="pledge_relationship"
                               value="<?php echo isset($_POST['pledge_relationship']) ? sanitizeOutput($_POST['pledge_relationship']) : ''; ?>">
                    </div>
                </div>
            </div>

            <!-- Additional Notes -->
            <div class="form-section">
                <h3><i class="fas fa-sticky-note"></i> Additional Notes</h3>
                <div class="form-group">
                    <label for="notes">Notes</label>
                    <textarea id="notes" name="notes" rows="3" 
                              placeholder="Any additional information you'd like to provide..."><?php echo isset($_POST['notes']) ? sanitizeOutput($_POST['notes']) : ''; ?></textarea>
                </div>
            </div>

            <div class="form-actions">
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-save"></i> Save Student
                </button>
                <a href="<?php echo BASE_URL; ?>modules/sis/index.php" class="btn btn-secondary">
                    <i class="fas fa-times"></i> Cancel
                </a>
            </div>
        </form>
    </div>
</div>

<script>
function getSearchInputFor(select) {
    if (!select) return null;
    const inputId = select.getAttribute('data-search-input');
    return inputId ? document.getElementById(inputId) : null;
}

function applySelectSearch(select) {
    if (!select) return;
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
        if (!shouldHide) visibleCount++;
    });
}

function setupSelectSearch(select) {
    if (!select) return;
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

let allProgramsData = [];
let allMajorsData = [];

function filterPrograms() {
    const departmentSelect = document.getElementById('department_id');
    const programSelect = document.getElementById('program_id');
    const selectedDepartmentId = departmentSelect.value;
    
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
    
    programSelect.innerHTML = '<option value="">Select Program</option>';
    
    if (!selectedDepartmentId) {
        updateYearLevels();
        applySelectSearch(programSelect);
        return;
    }
    
    let hasPrograms = false;
    allProgramsData.forEach(function(program) {
        const programDeptId = program.departmentId;
        if (programDeptId == selectedDepartmentId || programDeptId === null || programDeptId === '') {
            const option = document.createElement('option');
            option.value = program.value;
            option.textContent = program.text;
            option.setAttribute('data-department-id', programDeptId || '');
            option.setAttribute('data-duration', program.duration || '4');
            programSelect.appendChild(option);
            hasPrograms = true;
        }
    });
    
    if (!hasPrograms) {
        programSelect.innerHTML = '<option value="">No programs available for this department</option>';
    }
    
    updateYearLevels();
    applySelectSearch(programSelect);
}

function updateYearLevels() {
    const programSelect = document.getElementById('program_id');
    const yearLevelSelect = document.getElementById('year_level');
    
    if (!programSelect || !yearLevelSelect) return;
    
    const selectedProgramId = programSelect.value;
    
    if (!selectedProgramId) {
        yearLevelSelect.innerHTML = '<option value="">Select Program First</option>';
        updateMajors();
        return;
    }
    
    const selectedOption = programSelect.options[programSelect.selectedIndex];
    const duration = parseInt(selectedOption.getAttribute('data-duration')) || 4;
    
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
    
    updateMajors();
}

function updateMajors() {
    const programSelect = document.getElementById('program_id');
    const majorSelect = document.getElementById('major_id');
    
    if (!programSelect || !majorSelect) return;
    
    const selectedProgramId = programSelect.value;
    
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
    
    majorSelect.innerHTML = '<option value="">Select Major</option>';
    
    if (!selectedProgramId) {
        applySelectSearch(majorSelect);
        return;
    }
    
    let hasMajors = false;
    allMajorsData.forEach(function(major) {
        const majorProgramId = major.programId;
        if (majorProgramId == selectedProgramId) {
            const option = document.createElement('option');
            option.value = major.value;
            option.textContent = major.text;
            option.setAttribute('data-program-id', majorProgramId);
            majorSelect.appendChild(option);
            hasMajors = true;
        }
    });
    
    if (!hasMajors) {
        majorSelect.innerHTML = '<option value="">No majors available for this program</option>';
    }
    applySelectSearch(majorSelect);
}

document.addEventListener('DOMContentLoaded', function() {
    setTimeout(function() {
        const programSelectEl = document.getElementById('program_id');
        const majorSelectEl = document.getElementById('major_id');
        setupSelectSearch(programSelectEl);
        setupSelectSearch(majorSelectEl);
        
        filterPrograms();
        const selectedProgramId = '<?php echo (int)($_POST['program_id'] ?? 0); ?>';
        if (selectedProgramId && programSelectEl) {
            const option = programSelectEl.querySelector('option[value="' + selectedProgramId + '"]');
            if (option) {
                programSelectEl.value = selectedProgramId;
                updateYearLevels();
                const selectedYearLevel = '<?php echo sanitizeOutput($_POST['year_level'] ?? ''); ?>';
                if (selectedYearLevel && document.getElementById('year_level')) {
                    const yearLevelSelect = document.getElementById('year_level');
                    const yearOption = yearLevelSelect.querySelector('option[value="' + selectedYearLevel + '"]');
                    if (yearOption) yearLevelSelect.value = selectedYearLevel;
                }
                const selectedMajorId = '<?php echo (int)($_POST['major_id'] ?? 0); ?>';
                if (selectedMajorId && majorSelectEl) {
                    const majorOption = majorSelectEl.querySelector('option[value="' + selectedMajorId + '"]');
                    if (majorOption) majorSelectEl.value = selectedMajorId;
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
    
    if (document.querySelector('input[name="disciplinary_history"]:checked')?.value === 'Yes') {
        specField.style.display = 'inline-block';
        specField.required = true;
    } else {
        specField.style.display = 'none';
    }
});
</script>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
