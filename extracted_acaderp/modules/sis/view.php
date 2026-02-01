<?php
/**
 * Student Information System (SIS) - View Student
 * Displays detailed student information
 */

require_once __DIR__ . '/../../config/config.php';
requireAuth([ROLE_ADMIN, ROLE_FACULTY]);

$page_title = 'View Student';

$pdo = getDBConnection();
$student = null;
$student_id = (int)($_GET['id'] ?? 0);

if ($student_id > 0) {
    $stmt = $pdo->prepare("SELECT s.*, p.program_name, p.program_code, 
                           f.first_name as advisor_first, f.last_name as advisor_last,
                           f.email as advisor_email,
                           u.email as user_email,
                           aa.email as application_email,
                           aa.home_street, aa.home_city, aa.home_province, aa.home_zipcode,
                           aa.current_street, aa.current_city, aa.current_province, aa.current_zipcode
                           FROM students s 
                           LEFT JOIN programs p ON s.program_id = p.program_id 
                           LEFT JOIN faculty f ON s.advisor_id = f.faculty_id 
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

include __DIR__ . '/../../includes/header.php';
?>

<div class="page-container">
    <div class="page-header">
        <h1><i class="fas fa-user-graduate"></i> Student Details</h1>
        <div class="header-actions">
            <a href="<?php echo BASE_URL; ?>modules/sis/edit.php?id=<?php echo $student_id; ?>" class="btn btn-warning">
                <i class="fas fa-edit"></i> Edit
            </a>
            <a href="<?php echo BASE_URL; ?>modules/sis/index.php" class="btn btn-secondary">
                <i class="fas fa-arrow-left"></i> Back to List
            </a>
        </div>
    </div>

    <div class="detail-cards">
        <div class="detail-card">
            <h3><i class="fas fa-id-card"></i> Personal Information</h3>
            <div class="detail-grid">
                <div class="detail-item">
                    <label>Student Number:</label>
                    <span><strong><?php echo sanitizeOutput($student['student_number']); ?></strong></span>
                </div>
                <div class="detail-item">
                    <label>Full Name:</label>
                    <span><?php echo sanitizeOutput($student['first_name'] . ' ' . ($student['middle_name'] ? $student['middle_name'] . ' ' : '') . $student['last_name']); ?></span>
                </div>
                <div class="detail-item">
                    <label>Date of Birth:</label>
                    <span><?php echo date('F d, Y', strtotime($student['date_of_birth'])); ?></span>
                </div>
                <div class="detail-item">
                    <label>Gender:</label>
                    <span><?php echo sanitizeOutput($student['gender']); ?></span>
                </div>
                <div class="detail-item">
                    <label>Phone:</label>
                    <span><?php echo sanitizeOutput($student['phone'] ?? 'N/A'); ?></span>
                </div>
                <div class="detail-item">
                    <label>Email:</label>
                    <span><?php echo sanitizeOutput($student['email'] ?? 'N/A'); ?></span>
                </div>
            </div>
        </div>

        <div class="detail-card">
            <h3><i class="fas fa-map-marker-alt"></i> Address Information</h3>
            
            <?php if (!empty($student['home_street']) || !empty($student['address'])): ?>
            <div style="margin-bottom: 20px;">
                <h4 style="margin-bottom: 10px; color: #555;">Home Address</h4>
                <div class="detail-grid">
                    <div class="detail-item full-width">
                        <label>Street/Purok/Barangay:</label>
                        <span><?php echo sanitizeOutput($student['home_street'] ?? $student['address'] ?? 'N/A'); ?></span>
                    </div>
                    <div class="detail-item">
                        <label>City/Town:</label>
                        <span><?php echo sanitizeOutput($student['home_city'] ?? $student['city'] ?? 'N/A'); ?></span>
                    </div>
                    <div class="detail-item">
                        <label>Province:</label>
                        <span><?php echo sanitizeOutput($student['home_province'] ?? $student['state'] ?? 'N/A'); ?></span>
                    </div>
                    <div class="detail-item">
                        <label>Zip Code:</label>
                        <span><?php echo sanitizeOutput($student['home_zipcode'] ?? $student['zip_code'] ?? 'N/A'); ?></span>
                    </div>
                </div>
            </div>
            <?php endif; ?>
            
            <?php if (!empty($student['current_street'])): ?>
            <div>
                <h4 style="margin-bottom: 10px; color: #555;">Current Address</h4>
                <div class="detail-grid">
                    <div class="detail-item full-width">
                        <label>Street/Purok/Barangay:</label>
                        <span><?php echo sanitizeOutput($student['current_street'] ?? 'N/A'); ?></span>
                    </div>
                    <div class="detail-item">
                        <label>City/Town:</label>
                        <span><?php echo sanitizeOutput($student['current_city'] ?? 'N/A'); ?></span>
                    </div>
                    <div class="detail-item">
                        <label>Province:</label>
                        <span><?php echo sanitizeOutput($student['current_province'] ?? 'N/A'); ?></span>
                    </div>
                    <div class="detail-item">
                        <label>Zip Code:</label>
                        <span><?php echo sanitizeOutput($student['current_zipcode'] ?? 'N/A'); ?></span>
                    </div>
                </div>
            </div>
            <?php endif; ?>
            
            <?php if (empty($student['home_street']) && empty($student['current_street']) && empty($student['address'])): ?>
            <div class="detail-grid">
                <div class="detail-item full-width">
                    <label>Street Address:</label>
                    <span>N/A</span>
                </div>
                <div class="detail-item">
                    <label>City:</label>
                    <span>N/A</span>
                </div>
                <div class="detail-item">
                    <label>State/Province:</label>
                    <span>N/A</span>
                </div>
                <div class="detail-item">
                    <label>Zip Code:</label>
                    <span>N/A</span>
                </div>
                <div class="detail-item">
                    <label>Country:</label>
                    <span><?php echo sanitizeOutput($student['country'] ?? 'N/A'); ?></span>
                </div>
            </div>
            <?php endif; ?>
        </div>

        <div class="detail-card">
            <h3><i class="fas fa-graduation-cap"></i> Academic Information</h3>
            <div class="detail-grid">
                <div class="detail-item">
                    <label>Program:</label>
                    <span><?php echo sanitizeOutput($student['program_name'] ?? 'N/A'); ?></span>
                </div>
                <div class="detail-item">
                    <label>Enrollment Date:</label>
                    <span><?php echo date('F d, Y', strtotime($student['enrollment_date'])); ?></span>
                </div>
                <div class="detail-item">
                    <label>Status:</label>
                    <span class="badge badge-<?php 
                        echo $student['status'] === 'Active' ? 'success' : 
                            ($student['status'] === 'Graduated' ? 'info' : 'warning'); 
                    ?>"><?php echo sanitizeOutput($student['status']); ?></span>
                </div>
                <div class="detail-item">
                    <label>GPA:</label>
                    <span><strong><?php echo number_format($student['gpa'], 2); ?></strong></span>
                </div>
                <div class="detail-item">
                    <label>Total Credits:</label>
                    <span><strong><?php echo $student['total_credits']; ?></strong></span>
                </div>
                <div class="detail-item">
                    <label>Academic Advisor:</label>
                    <span><?php 
                        if ($student['advisor_first']) {
                            echo sanitizeOutput($student['advisor_first'] . ' ' . $student['advisor_last']);
                        } else {
                            echo 'N/A';
                        }
                    ?></span>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
