<?php
/**
 * Student Information System (SIS) - Edit Student
 * Form to edit existing student record
 */

require_once __DIR__ . '/../../config/config.php';
requireAuth([ROLE_ADMIN]);

$page_title = 'Edit Student';

$pdo = getDBConnection();
$error = '';
$student_id = (int)($_GET['id'] ?? 0);

// Get student data
$student = null;
if ($student_id > 0) {
    $stmt = $pdo->prepare("SELECT s.*, 
                           u.email as user_email,
                           aa.email as application_email,
                           aa.home_street, aa.home_city, aa.home_province, aa.home_zipcode,
                           aa.current_street, aa.current_city, aa.current_province, aa.current_zipcode
                           FROM students s
                           LEFT JOIN users u ON s.user_id = u.user_id
                           LEFT JOIN admission_applications aa ON aa.student_number = s.student_number
                           WHERE s.student_id = ?");
    $stmt->execute([$student_id]);
    $student = $stmt->fetch();
    
    // Get email from users table, fallback to admission_applications
    if ($student) {
        $student['email'] = $student['user_email'] ?: $student['application_email'] ?: null;
    }
    
    // If address is not in students table but exists in admission_applications, use that
    if ($student && (empty($student['address']) || empty($student['city']) || empty($student['state']))) {
        // Use home address as primary, fallback to current address
        if (!empty($student['home_street'])) {
            $student['address'] = $student['address'] ?: $student['home_street'];
            $student['city'] = $student['city'] ?: $student['home_city'];
            $student['state'] = $student['state'] ?: $student['home_province'];
            $student['zip_code'] = $student['zip_code'] ?: $student['home_zipcode'];
        } elseif (!empty($student['current_street'])) {
            $student['address'] = $student['address'] ?: $student['current_street'];
            $student['city'] = $student['city'] ?: $student['current_city'];
            $student['state'] = $student['state'] ?: $student['current_province'];
            $student['zip_code'] = $student['zip_code'] ?: $student['current_zipcode'];
        }
    }
}

if (!$student) {
    header('Location: ' . BASE_URL . 'modules/sis/index.php?error=Student not found');
    exit;
}

// Get programs and faculty for dropdowns
$programs = [];
$advisors = [];

if ($pdo) {
    $programs = $pdo->query("SELECT program_id, program_code, program_name FROM programs WHERE status = 'Active' ORDER BY program_name")->fetchAll();
    $advisors = $pdo->query("SELECT faculty_id, first_name, last_name FROM faculty WHERE status = 'Active' ORDER BY last_name, first_name")->fetchAll();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Sanitize input
    $student_number = sanitizeInput($_POST['student_number'] ?? '');
    $first_name = sanitizeInput($_POST['first_name'] ?? '');
    $middle_name = sanitizeInput($_POST['middle_name'] ?? '');
    $last_name = sanitizeInput($_POST['last_name'] ?? '');
    $date_of_birth = $_POST['date_of_birth'] ?? '';
    $gender = $_POST['gender'] ?? '';
    $phone = sanitizeInput($_POST['phone'] ?? '');
    $address = sanitizeInput($_POST['address'] ?? '');
    $city = sanitizeInput($_POST['city'] ?? '');
    $state = sanitizeInput($_POST['state'] ?? '');
    $zip_code = sanitizeInput($_POST['zip_code'] ?? '');
    $country = sanitizeInput($_POST['country'] ?? 'USA');
    $enrollment_date = $_POST['enrollment_date'] ?? '';
    $graduation_date = !empty($_POST['graduation_date']) ? $_POST['graduation_date'] : null;
    $program_id = !empty($_POST['program_id']) ? (int)$_POST['program_id'] : null;
    $advisor_id = !empty($_POST['advisor_id']) ? (int)$_POST['advisor_id'] : null;
    $status = sanitizeInput($_POST['status'] ?? 'Active');
    $gpa = !empty($_POST['gpa']) ? (float)$_POST['gpa'] : 0.00;
    $total_credits = !empty($_POST['total_credits']) ? (int)$_POST['total_credits'] : 0;

    // Validation
    if (empty($student_number) || empty($first_name) || empty($last_name) || empty($date_of_birth) || empty($gender)) {
        $error = 'Please fill in all required fields.';
    } else {
        // Check if student number already exists (excluding current student)
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM students WHERE student_number = ? AND student_id != ?");
        $stmt->execute([$student_number, $student_id]);
        if ($stmt->fetchColumn() > 0) {
            $error = 'Student number already exists.';
        } else {
            // Update student
            $sql = "UPDATE students SET student_number = ?, first_name = ?, middle_name = ?, last_name = ?, 
                    date_of_birth = ?, gender = ?, phone = ?, address = ?, city = ?, state = ?, 
                    zip_code = ?, country = ?, enrollment_date = ?, graduation_date = ?, 
                    program_id = ?, advisor_id = ?, status = ?, gpa = ?, total_credits = ?
                    WHERE student_id = ?";
            
            $stmt = $pdo->prepare($sql);
            $result = $stmt->execute([
                $student_number, $first_name, $middle_name ?: null, $last_name, $date_of_birth,
                $gender, $phone ?: null, $address ?: null, $city ?: null, $state ?: null,
                $zip_code ?: null, $country, $enrollment_date, $graduation_date, 
                $program_id, $advisor_id, $status, $gpa, $total_credits, $student_id
            ]);

            if ($result) {
                header('Location: ' . BASE_URL . 'modules/sis/view.php?id=' . $student_id . '&success=Student updated successfully');
                exit;
            } else {
                $error = 'Failed to update student. Please try again.';
            }
        }
    }
    
    // Update $student with POST data for form re-display
    $student = array_merge($student, $_POST);
}

include __DIR__ . '/../../includes/header.php';
?>

<div class="page-container">
    <div class="page-header">
        <h1><i class="fas fa-user-edit"></i> Edit Student</h1>
        <a href="<?php echo BASE_URL; ?>modules/sis/view.php?id=<?php echo $student_id; ?>" class="btn btn-secondary">
            <i class="fas fa-arrow-left"></i> Back to View
        </a>
    </div>

    <?php if ($error): ?>
    <div class="alert alert-error">
        <i class="fas fa-exclamation-circle"></i> <?php echo sanitizeOutput($error); ?>
    </div>
    <?php endif; ?>

    <div class="form-card">
        <form method="POST" action="" class="student-form">
            <input type="hidden" name="student_id" value="<?php echo $student_id; ?>">
            
            <div class="form-section">
                <h3><i class="fas fa-id-card"></i> Personal Information</h3>
                
                <div class="form-row">
                    <div class="form-group required">
                        <label for="student_number">Student Number *</label>
                        <input type="text" id="student_number" name="student_number" required
                               value="<?php echo sanitizeOutput($student['student_number']); ?>">
                    </div>
                    <div class="form-group required">
                        <label for="enrollment_date">Enrollment Date *</label>
                        <input type="date" id="enrollment_date" name="enrollment_date" required
                               value="<?php echo $student['enrollment_date']; ?>">
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group required">
                        <label for="first_name">First Name *</label>
                        <input type="text" id="first_name" name="first_name" required
                               value="<?php echo sanitizeOutput($student['first_name']); ?>">
                    </div>
                    <div class="form-group">
                        <label for="middle_name">Middle Name</label>
                        <input type="text" id="middle_name" name="middle_name"
                               value="<?php echo sanitizeOutput($student['middle_name'] ?? ''); ?>">
                    </div>
                    <div class="form-group required">
                        <label for="last_name">Last Name *</label>
                        <input type="text" id="last_name" name="last_name" required
                               value="<?php echo sanitizeOutput($student['last_name']); ?>">
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group required">
                        <label for="date_of_birth">Date of Birth *</label>
                        <input type="date" id="date_of_birth" name="date_of_birth" required
                               value="<?php echo $student['date_of_birth']; ?>">
                    </div>
                    <div class="form-group required">
                        <label for="gender">Gender *</label>
                        <select id="gender" name="gender" required>
                            <option value="">Select Gender</option>
                            <option value="Male" <?php echo $student['gender'] === 'Male' ? 'selected' : ''; ?>>Male</option>
                            <option value="Female" <?php echo $student['gender'] === 'Female' ? 'selected' : ''; ?>>Female</option>
                            <option value="Other" <?php echo $student['gender'] === 'Other' ? 'selected' : ''; ?>>Other</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="phone">Phone</label>
                        <input type="tel" id="phone" name="phone"
                               value="<?php echo sanitizeOutput($student['phone'] ?? ''); ?>">
                    </div>
                    <div class="form-group">
                        <label for="email">Email</label>
                        <input type="email" id="email" name="email"
                               value="<?php echo sanitizeOutput($student['email'] ?? ''); ?>" readonly
                               style="background-color: #f5f5f5; cursor: not-allowed;"
                               title="Email is managed through the user account and cannot be edited here">
                        <small class="text-muted">Email is linked to the user account and cannot be edited here</small>
                    </div>
                </div>
            </div>

            <div class="form-section">
                <h3><i class="fas fa-map-marker-alt"></i> Address Information</h3>
                
                <div class="form-group">
                    <label for="address">Street Address</label>
                    <input type="text" id="address" name="address"
                           value="<?php echo sanitizeOutput($student['address'] ?? ''); ?>">
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label for="city">City</label>
                        <input type="text" id="city" name="city"
                               value="<?php echo sanitizeOutput($student['city'] ?? ''); ?>">
                    </div>
                    <div class="form-group">
                        <label for="state">State/Province</label>
                        <input type="text" id="state" name="state"
                               value="<?php echo sanitizeOutput($student['state'] ?? ''); ?>">
                    </div>
                    <div class="form-group">
                        <label for="zip_code">Zip/Postal Code</label>
                        <input type="text" id="zip_code" name="zip_code"
                               value="<?php echo sanitizeOutput($student['zip_code'] ?? ''); ?>">
                    </div>
                    <div class="form-group">
                        <label for="country">Country</label>
                        <input type="text" id="country" name="country" 
                               value="<?php echo sanitizeOutput($student['country'] ?? 'USA'); ?>">
                    </div>
                </div>
            </div>

            <div class="form-section">
                <h3><i class="fas fa-graduation-cap"></i> Academic Information</h3>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="program_id">Program</label>
                        <select id="program_id" name="program_id">
                            <option value="">Select Program</option>
                            <?php foreach ($programs as $program): ?>
                            <option value="<?php echo $program['program_id']; ?>"
                                    <?php echo ($student['program_id'] == $program['program_id']) ? 'selected' : ''; ?>>
                                <?php echo sanitizeOutput($program['program_name']); ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="advisor_id">Academic Advisor</label>
                        <select id="advisor_id" name="advisor_id">
                            <option value="">Select Advisor</option>
                            <?php foreach ($advisors as $advisor): ?>
                            <option value="<?php echo $advisor['faculty_id']; ?>"
                                    <?php echo ($student['advisor_id'] == $advisor['faculty_id']) ? 'selected' : ''; ?>>
                                <?php echo sanitizeOutput($advisor['first_name'] . ' ' . $advisor['last_name']); ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group required">
                        <label for="status">Status *</label>
                        <select id="status" name="status" required>
                            <option value="Active" <?php echo $student['status'] === 'Active' ? 'selected' : ''; ?>>Active</option>
                            <option value="Graduated" <?php echo $student['status'] === 'Graduated' ? 'selected' : ''; ?>>Graduated</option>
                            <option value="Transferred" <?php echo $student['status'] === 'Transferred' ? 'selected' : ''; ?>>Transferred</option>
                            <option value="Suspended" <?php echo $student['status'] === 'Suspended' ? 'selected' : ''; ?>>Suspended</option>
                            <option value="Withdrawn" <?php echo $student['status'] === 'Withdrawn' ? 'selected' : ''; ?>>Withdrawn</option>
                        </select>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label for="gpa">GPA</label>
                        <input type="number" id="gpa" name="gpa" step="0.01" min="0" max="4.00"
                               value="<?php echo number_format($student['gpa'], 2); ?>">
                    </div>
                    <div class="form-group">
                        <label for="total_credits">Total Credits</label>
                        <input type="number" id="total_credits" name="total_credits" min="0"
                               value="<?php echo $student['total_credits']; ?>">
                    </div>
                    <div class="form-group">
                        <label for="graduation_date">Graduation Date</label>
                        <input type="date" id="graduation_date" name="graduation_date"
                               value="<?php echo $student['graduation_date'] ?? ''; ?>">
                    </div>
                </div>
            </div>

            <div class="form-actions">
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-save"></i> Update Student
                </button>
                <a href="<?php echo BASE_URL; ?>modules/sis/view.php?id=<?php echo $student_id; ?>" class="btn btn-secondary">
                    <i class="fas fa-times"></i> Cancel
                </a>
            </div>
        </form>
    </div>
</div>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
