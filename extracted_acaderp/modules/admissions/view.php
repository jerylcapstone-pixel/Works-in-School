<?php
require_once __DIR__ . '/../../config/config.php';
requireAuth(ROLE_ADMIN);
$page_title = 'View Application';
$app_id = (int)($_GET['id'] ?? 0);
if (!$app_id) {
    header('Location: ' . BASE_URL . 'modules/admissions/index.php?error=Invalid ID');
    exit;
}

$pdo = getDBConnection();
$stmt = $pdo->prepare("SELECT a.*, p.program_name, p.program_code FROM admission_applications a
                       LEFT JOIN programs p ON a.program_id = p.program_id WHERE a.application_id = ?");
$stmt->execute([$app_id]);
$app = $stmt->fetch();
if (!$app) {
    header('Location: ' . BASE_URL . 'modules/admissions/index.php?error=Application not found');
    exit;
}

// Decode JSON fields
$organizations = !empty($app['organizations']) ? json_decode($app['organizations'], true) : [];

include __DIR__ . '/../../includes/header.php';
?>

<div class="page-container">
    <div class="page-header">
        <h1><i class="fas fa-file-alt"></i> Application Details</h1>
        <div class="header-actions">
            <a href="<?php echo BASE_URL; ?>modules/admissions/edit.php?id=<?php echo $app_id; ?>" class="btn btn-primary">
                <i class="fas fa-edit"></i> Review Application
            </a>
            <a href="<?php echo BASE_URL; ?>modules/admissions/index.php" class="btn btn-secondary">
                <i class="fas fa-arrow-left"></i> Back to List
            </a>
        </div>
    </div>

    <div class="detail-cards">
        <!-- Photo Card -->
        <?php if (!empty($app['photo_path'])): ?>
        <div class="detail-card" style="text-align: center;">
            <img src="<?php echo BASE_URL . $app['photo_path']; ?>" alt="Student Photo" 
                 style="max-width: 250px; max-height: 250px; border-radius: 12px; box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);">
        </div>
        <?php endif; ?>

        <!-- Application Status Card -->
        <div class="detail-card" style="background: linear-gradient(135deg, #2563eb 0%, #1e40af 100%); color: white;">
            <h3 style="color: white; border-bottom-color: rgba(255, 255, 255, 0.3);">
                <i class="fas fa-info-circle"></i> Application Status
            </h3>
            <div class="detail-grid">
                <div class="detail-item">
                    <label style="color: rgba(255, 255, 255, 0.9);">Status</label>
                    <span style="color: white; font-size: 1.25rem; font-weight: 700;">
                        <span class="badge badge-<?php echo $app['status'] === 'Approved' ? 'success' : ($app['status'] === 'Rejected' ? 'danger' : 'warning'); ?>">
                            <?php echo sanitizeOutput($app['status']); ?>
                        </span>
                    </span>
                </div>
                <div class="detail-item">
                    <label style="color: rgba(255, 255, 255, 0.9);">Application Date</label>
                    <span style="color: white; font-size: 1.1rem;"><?php echo date('F d, Y', strtotime($app['application_date'])); ?></span>
                </div>
                <div class="detail-item">
                    <label style="color: rgba(255, 255, 255, 0.9);">Student Type</label>
                    <span style="color: white; font-size: 1.1rem;"><?php echo sanitizeOutput($app['student_type'] ?? 'New'); ?></span>
                </div>
            </div>
        </div>

        <!-- Academic Information -->
        <div class="detail-card">
            <h3><i class="fas fa-graduation-cap"></i> Academic Information</h3>
            <div class="detail-grid">
                <div class="detail-item">
                    <label>ID Number</label>
                    <span><strong><?php echo sanitizeOutput($app['id_number'] ?? 'N/A'); ?></strong></span>
                </div>
                <div class="detail-item">
                    <label>Program</label>
                    <span><?php echo sanitizeOutput($app['program_name'] ?? 'N/A'); ?></span>
                </div>
                <div class="detail-item">
                    <label>Year Level</label>
                    <span><?php echo sanitizeOutput($app['year_level'] ?? 'N/A'); ?></span>
                </div>
                <div class="detail-item">
                    <label>Major</label>
                    <span><?php echo sanitizeOutput($app['major'] ?? 'N/A'); ?></span>
                </div>
                <div class="detail-item">
                    <label>Term</label>
                    <span><?php echo sanitizeOutput($app['term'] ?? 'N/A'); ?></span>
                </div>
                <?php if (!empty($app['gpa_previous']) || !empty($app['previous_school'])): ?>
                <div class="detail-item">
                    <label>Previous GPA</label>
                    <span><strong><?php echo $app['gpa_previous'] ? number_format($app['gpa_previous'], 2) : 'N/A'; ?></strong></span>
                </div>
                <div class="detail-item full-width">
                    <label>Previous School</label>
                    <span><?php echo sanitizeOutput($app['previous_school'] ?? 'N/A'); ?></span>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Personal Information -->
        <div class="detail-card">
            <h3><i class="fas fa-user"></i> Personal Information</h3>
            <div class="detail-grid">
                <div class="detail-item">
                    <label>Full Name</label>
                    <span><strong><?php echo sanitizeOutput($app['first_name'] . ' ' . ($app['middle_name'] ? $app['middle_name'] . ' ' : '') . $app['last_name']); ?></strong></span>
                </div>
                <div class="detail-item">
                    <label>Date of Birth</label>
                    <span><?php echo date('F d, Y', strtotime($app['date_of_birth'])); ?></span>
                </div>
                <div class="detail-item">
                    <label>Gender</label>
                    <span><?php echo sanitizeOutput($app['gender'] ?? 'N/A'); ?></span>
                </div>
                <div class="detail-item">
                    <label>Religion</label>
                    <span><?php echo sanitizeOutput($app['religion'] ?? 'N/A'); ?></span>
                </div>
                <div class="detail-item">
                    <label>Civil Status</label>
                    <span><?php echo sanitizeOutput($app['civil_status'] ?? 'N/A'); ?></span>
                </div>
                <div class="detail-item">
                    <label>Email</label>
                    <span><?php echo sanitizeOutput($app['email']); ?></span>
                </div>
                <div class="detail-item">
                    <label>Contact No</label>
                    <span><?php echo sanitizeOutput($app['phone'] ?? 'N/A'); ?></span>
                </div>
            </div>
        </div>

        <!-- Family Information -->
        <div class="detail-card">
            <h3><i class="fas fa-users"></i> Family Information</h3>
            
            <div style="margin-bottom: 2rem;">
                <h4 style="font-size: 1.1rem; font-weight: 600; color: #475569; margin-bottom: 1rem; padding-bottom: 0.5rem; border-bottom: 2px solid #e2e8f0;">
                    <i class="fas fa-male" style="color: #2563eb;"></i> Father's Name
                </h4>
                <div class="detail-grid">
                    <div class="detail-item">
                        <label>Lastname</label>
                        <span><?php echo sanitizeOutput($app['father_lastname'] ?? 'N/A'); ?></span>
                    </div>
                    <div class="detail-item">
                        <label>Firstname</label>
                        <span><?php echo sanitizeOutput($app['father_firstname'] ?? 'N/A'); ?></span>
                    </div>
                    <div class="detail-item">
                        <label>Middle Name</label>
                        <span><?php echo sanitizeOutput($app['father_middlename'] ?? 'N/A'); ?></span>
                    </div>
                </div>
            </div>
            
            <div>
                <h4 style="font-size: 1.1rem; font-weight: 600; color: #475569; margin-bottom: 1rem; padding-bottom: 0.5rem; border-bottom: 2px solid #e2e8f0;">
                    <i class="fas fa-female" style="color: #ec4899;"></i> Mother's Name
                </h4>
                <div class="detail-grid">
                    <div class="detail-item">
                        <label>Lastname</label>
                        <span><?php echo sanitizeOutput($app['mother_lastname'] ?? 'N/A'); ?></span>
                    </div>
                    <div class="detail-item">
                        <label>Firstname</label>
                        <span><?php echo sanitizeOutput($app['mother_firstname'] ?? 'N/A'); ?></span>
                    </div>
                    <div class="detail-item">
                        <label>Middle Name</label>
                        <span><?php echo sanitizeOutput($app['mother_middlename'] ?? 'N/A'); ?></span>
                    </div>
                    <div class="detail-item">
                        <label>Maiden Middle Name</label>
                        <span><?php echo sanitizeOutput($app['mother_maiden_middlename'] ?? 'N/A'); ?></span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Address Information -->
        <div class="detail-card">
            <h3><i class="fas fa-map-marker-alt"></i> Address Information</h3>
            
            <div style="margin-bottom: 2rem;">
                <h4 style="font-size: 1.1rem; font-weight: 600; color: #475569; margin-bottom: 1rem; padding-bottom: 0.5rem; border-bottom: 2px solid #e2e8f0;">
                    <i class="fas fa-home" style="color: #2563eb;"></i> Home Address
                </h4>
                <div class="detail-grid">
                    <div class="detail-item full-width">
                        <label>Street/Purok/Barangay</label>
                        <span><?php echo sanitizeOutput($app['home_street'] ?? 'N/A'); ?></span>
                    </div>
                    <div class="detail-item">
                        <label>City/Town</label>
                        <span><?php echo sanitizeOutput($app['home_city'] ?? 'N/A'); ?></span>
                    </div>
                    <div class="detail-item">
                        <label>Province</label>
                        <span><?php echo sanitizeOutput($app['home_province'] ?? 'N/A'); ?></span>
                    </div>
                    <div class="detail-item">
                        <label>Zip Code</label>
                        <span><?php echo sanitizeOutput($app['home_zipcode'] ?? 'N/A'); ?></span>
                    </div>
                </div>
            </div>
            
            <div>
                <h4 style="font-size: 1.1rem; font-weight: 600; color: #475569; margin-bottom: 1rem; padding-bottom: 0.5rem; border-bottom: 2px solid #e2e8f0;">
                    <i class="fas fa-building" style="color: #10b981;"></i> Current Address
                </h4>
                <div class="detail-grid">
                    <div class="detail-item full-width">
                        <label>Street/Purok/Barangay</label>
                        <span><?php echo sanitizeOutput($app['current_street'] ?? 'N/A'); ?></span>
                    </div>
                    <div class="detail-item">
                        <label>City/Town</label>
                        <span><?php echo sanitizeOutput($app['current_city'] ?? 'N/A'); ?></span>
                    </div>
                    <div class="detail-item">
                        <label>Province</label>
                        <span><?php echo sanitizeOutput($app['current_province'] ?? 'N/A'); ?></span>
                    </div>
                    <div class="detail-item">
                        <label>Zip Code</label>
                        <span><?php echo sanitizeOutput($app['current_zipcode'] ?? 'N/A'); ?></span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Contact & Guardian -->
        <div class="detail-card">
            <h3><i class="fas fa-phone-alt"></i> Contact & Guardian Information</h3>
            <div class="detail-grid">
                <div class="detail-item">
                    <label>Parents Contact No</label>
                    <span><?php echo sanitizeOutput($app['parents_contact'] ?? 'N/A'); ?></span>
                </div>
                <div class="detail-item">
                    <label>Guardian Name</label>
                    <span><?php echo sanitizeOutput($app['guardian_name'] ?? 'N/A'); ?></span>
                </div>
                <div class="detail-item">
                    <label>Guardian Contact No</label>
                    <span><?php echo sanitizeOutput($app['guardian_contact'] ?? 'N/A'); ?></span>
                </div>
            </div>
        </div>

        <!-- Household Information -->
        <div class="detail-card">
            <h3><i class="fas fa-home"></i> Household & Financial Information</h3>
            <div class="detail-grid">
                <div class="detail-item">
                    <label>DSWD Household No</label>
                    <span><?php echo sanitizeOutput($app['dswd_household_no'] ?? 'N/A'); ?></span>
                </div>
                <div class="detail-item">
                    <label>Monthly Income</label>
                    <span><strong><?php echo $app['monthly_income'] ? '₱' . number_format($app['monthly_income'], 2) : 'N/A'; ?></strong></span>
                </div>
                <div class="detail-item">
                    <label>Disability</label>
                    <span><?php echo sanitizeOutput($app['disability'] ?? 'N/A'); ?></span>
                </div>
            </div>
        </div>

        <!-- Organizations -->
        <?php if (!empty($organizations)): ?>
        <div class="detail-card">
            <h3><i class="fas fa-users-cog"></i> Organization Affiliations</h3>
            <div style="overflow-x: auto;">
                <table class="data-table" style="width: 100%;">
                    <thead>
                        <tr>
                            <th>Organization</th>
                            <th>Position Held</th>
                            <th>Term</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($organizations as $org): ?>
                        <tr>
                            <td><strong><?php echo sanitizeOutput($org['name'] ?? ''); ?></strong></td>
                            <td><?php echo sanitizeOutput($org['position'] ?? ''); ?></td>
                            <td><?php echo sanitizeOutput($org['term'] ?? ''); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php endif; ?>

        <!-- Citizenship & Conduct -->
        <div class="detail-card">
            <h3><i class="fas fa-passport"></i> Citizenship & Conduct</h3>
            <div class="detail-grid">
                <div class="detail-item">
                    <label>Citizenship</label>
                    <span><?php echo sanitizeOutput($app['citizenship'] ?? 'N/A'); ?></span>
                </div>
                <div class="detail-item">
                    <label>Disciplinary History</label>
                    <span>
                        <span class="badge badge-<?php echo $app['disciplinary_history'] ? 'warning' : 'success'; ?>">
                            <?php echo $app['disciplinary_history'] ? 'Yes' : 'No'; ?>
                        </span>
                    </span>
                </div>
                <?php if ($app['disciplinary_history'] && !empty($app['disciplinary_specification'])): ?>
                <div class="detail-item full-width">
                    <label>Disciplinary Specification</label>
                    <span style="color: #dc2626; font-weight: 500;"><?php echo sanitizeOutput($app['disciplinary_specification']); ?></span>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Pledges & Consents -->
        <div class="detail-card">
            <h3><i class="fas fa-handshake"></i> Pledges & Consents</h3>
            <div class="detail-grid">
                <div class="detail-item">
                    <label>Pledge: No Prohibited Drugs</label>
                    <span>
                        <span class="badge badge-<?php echo $app['pledge_no_drugs'] ? 'success' : 'danger'; ?>">
                            <?php echo $app['pledge_no_drugs'] ? 'Yes' : 'No'; ?>
                        </span>
                    </span>
                </div>
                <div class="detail-item">
                    <label>Pledge: No Unlawful Acts</label>
                    <span>
                        <span class="badge badge-<?php echo $app['pledge_no_unlawful_acts'] ? 'success' : 'danger'; ?>">
                            <?php echo $app['pledge_no_unlawful_acts'] ? 'Yes' : 'No'; ?>
                        </span>
                    </span>
                </div>
                <div class="detail-item">
                    <label>Consent: Marketing Use</label>
                    <span>
                        <span class="badge badge-<?php echo $app['consent_marketing'] ? 'success' : 'secondary'; ?>">
                            <?php echo $app['consent_marketing'] ? 'Yes' : 'No'; ?>
                        </span>
                    </span>
                </div>
                <div class="detail-item">
                    <label>Consent: Data Processing</label>
                    <span>
                        <span class="badge badge-<?php echo $app['consent_data_processing'] ? 'success' : 'secondary'; ?>">
                            <?php echo $app['consent_data_processing'] ? 'Yes' : 'No'; ?>
                        </span>
                    </span>
                </div>
                <div class="detail-item">
                    <label>Assistance Received</label>
                    <span>
                        <span class="badge badge-<?php echo $app['assistance_received'] ? 'info' : 'secondary'; ?>">
                            <?php echo $app['assistance_received'] ? 'Yes' : 'No'; ?>
                        </span>
                    </span>
                </div>
            </div>
        </div>

        <!-- Student's Pledge -->
        <div class="detail-card">
            <h3><i class="fas fa-file-signature"></i> Student's Pledge</h3>
            <div class="detail-grid">
                <div class="detail-item">
                    <label>Attested/Witnessed by</label>
                    <span><strong><?php echo sanitizeOutput($app['pledge_attested_by'] ?? 'N/A'); ?></strong></span>
                </div>
                <div class="detail-item">
                    <label>Relationship</label>
                    <span><?php echo sanitizeOutput($app['pledge_relationship'] ?? 'N/A'); ?></span>
                </div>
            </div>
        </div>

        <!-- Notes -->
        <?php if (!empty($app['notes'])): ?>
        <div class="detail-card">
            <h3><i class="fas fa-sticky-note"></i> Additional Notes</h3>
            <div style="background: #f8fafc; padding: 1.5rem; border-radius: 8px; border-left: 4px solid #2563eb;">
                <p style="margin: 0; color: #475569; line-height: 1.7;"><?php echo nl2br(sanitizeOutput($app['notes'])); ?></p>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
