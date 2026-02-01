<?php
require_once __DIR__ . '/../../config/config.php';
requireAuth(ROLE_ADMIN);
$page_title = 'New Application';
$pdo = getDBConnection();
$error = '';

$programs = [];
$stmt = $pdo->query("SELECT program_id, program_name, program_code FROM programs WHERE status = 'Active' ORDER BY program_name");
$programs = $stmt->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $first_name = sanitizeInput($_POST['first_name'] ?? '');
    $middle_name = sanitizeInput($_POST['middle_name'] ?? '');
    $last_name = sanitizeInput($_POST['last_name'] ?? '');
    $email = sanitizeInput($_POST['email'] ?? '');
    $phone = sanitizeInput($_POST['phone'] ?? '');
    $date_of_birth = $_POST['date_of_birth'] ?? '';
    $gender = sanitizeInput($_POST['gender'] ?? '');
    $address = sanitizeInput($_POST['address'] ?? '');
    $program_id = (int)($_POST['program_id'] ?? 0);
    $application_date = $_POST['application_date'] ?? date('Y-m-d');
    $gpa_previous = !empty($_POST['gpa_previous']) ? (float)$_POST['gpa_previous'] : null;
    $previous_school = sanitizeInput($_POST['previous_school'] ?? '');
    $notes = sanitizeInput($_POST['notes'] ?? '');
    $status = sanitizeInput($_POST['status'] ?? 'Pending');

    if (empty($first_name) || empty($last_name) || empty($email) || empty($program_id) || empty($date_of_birth)) {
        $error = 'Please fill in all required fields.';
    } else {
        $sql = "INSERT INTO admission_applications (first_name, middle_name, last_name, email, phone, date_of_birth,
                gender, address, program_id, application_date, gpa_previous, previous_school, notes, status)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        $stmt = $pdo->prepare($sql);
        if ($stmt->execute([$first_name, $middle_name ?: null, $last_name, $email, $phone ?: null, $date_of_birth,
            $gender ?: null, $address ?: null, $program_id, $application_date, $gpa_previous, $previous_school ?: null,
            $notes ?: null, $status])) {
            header('Location: ' . BASE_URL . 'modules/admissions/index.php?success=Application added successfully');
            exit;
        } else {
            $error = 'Error adding application.';
        }
    }
}

include __DIR__ . '/../../includes/header.php';
?>

<div class="page-container">
    <div class="page-header">
        <h1><i class="fas fa-user-plus"></i> New Admission Application</h1>
        <a href="<?php echo BASE_URL; ?>modules/admissions/index.php" class="btn btn-secondary">
            <i class="fas fa-arrow-left"></i> Back
        </a>
    </div>

    <?php if ($error): ?>
        <div class="alert alert-error"><?php echo sanitizeOutput($error); ?></div>
    <?php endif; ?>

    <div class="card">
        <form method="POST" action="" class="form">
            <h2>Personal Information</h2>
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

            <h2>Contact Information</h2>
            <div class="form-row">
                <div class="form-group">
                    <label>Email <span class="required">*</span></label>
                    <input type="email" name="email" required class="form-control" 
                           value="<?php echo sanitizeOutput($_POST['email'] ?? ''); ?>">
                </div>
                <div class="form-group">
                    <label>Phone</label>
                    <input type="text" name="phone" class="form-control" 
                           value="<?php echo sanitizeOutput($_POST['phone'] ?? ''); ?>">
                </div>
            </div>

            <div class="form-group">
                <label>Address</label>
                <textarea name="address" class="form-control" rows="2"><?php echo sanitizeOutput($_POST['address'] ?? ''); ?></textarea>
            </div>

            <h2>Academic Information</h2>
            <div class="form-row">
                <div class="form-group">
                    <label>Program <span class="required">*</span></label>
                    <select name="program_id" required class="form-control">
                        <option value="">Select Program</option>
                        <?php foreach ($programs as $prog): ?>
                            <option value="<?php echo $prog['program_id']; ?>"
                                    <?php echo (($_POST['program_id'] ?? '') == $prog['program_id']) ? 'selected' : ''; ?>>
                                <?php echo sanitizeOutput($prog['program_name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>Application Date</label>
                    <input type="date" name="application_date" class="form-control" 
                           value="<?php echo sanitizeOutput($_POST['application_date'] ?? date('Y-m-d')); ?>">
                </div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label>Previous GPA</label>
                    <input type="number" step="0.01" min="0" max="4" name="gpa_previous" class="form-control" 
                           value="<?php echo sanitizeOutput($_POST['gpa_previous'] ?? ''); ?>">
                </div>
                <div class="form-group">
                    <label>Previous School</label>
                    <input type="text" name="previous_school" class="form-control" 
                           value="<?php echo sanitizeOutput($_POST['previous_school'] ?? ''); ?>">
                </div>
            </div>

            <div class="form-group">
                <label>Notes</label>
                <textarea name="notes" class="form-control" rows="3"><?php echo sanitizeOutput($_POST['notes'] ?? ''); ?></textarea>
            </div>

            <div class="form-group">
                <label>Status</label>
                <select name="status" class="form-control" required>
                    <option value="Pending" <?php echo (($_POST['status'] ?? 'Pending') === 'Pending') ? 'selected' : ''; ?>>Pending</option>
                    <option value="Under Review" <?php echo (($_POST['status'] ?? '') === 'Under Review') ? 'selected' : ''; ?>>Under Review</option>
                    <option value="Approved" <?php echo (($_POST['status'] ?? '') === 'Approved') ? 'selected' : ''; ?>>Approved</option>
                    <option value="Rejected" <?php echo (($_POST['status'] ?? '') === 'Rejected') ? 'selected' : ''; ?>>Rejected</option>
                    <option value="Waitlisted" <?php echo (($_POST['status'] ?? '') === 'Waitlisted') ? 'selected' : ''; ?>>Waitlisted</option>
                </select>
            </div>

            <div class="form-actions">
                <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Submit Application</button>
                <a href="<?php echo BASE_URL; ?>modules/admissions/index.php" class="btn btn-secondary">Cancel</a>
            </div>
        </form>
    </div>
</div>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
