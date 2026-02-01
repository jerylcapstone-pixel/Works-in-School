<?php
/**
 * Student Advisory - Assign Advisor
 */
require_once __DIR__ . '/../../config/config.php';
requireAuth(ROLE_ADMIN);
$page_title = 'Assign Advisor';
$pdo = getDBConnection();
$error = '';
$success = false;

// Get students and advisors
$students = $pdo->query("SELECT s.student_id, s.student_number, s.first_name, s.last_name, p.program_name
                        FROM students s
                        LEFT JOIN programs p ON s.program_id = p.program_id
                        WHERE s.status = 'Active'
                        ORDER BY s.last_name, s.first_name")->fetchAll();

$advisors = $pdo->query("SELECT faculty_id, first_name, last_name FROM faculty WHERE status = 'Active' ORDER BY last_name, first_name")->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $student_id = !empty($_POST['student_id']) ? (int)$_POST['student_id'] : null;
    $advisor_id = !empty($_POST['advisor_id']) ? (int)$_POST['advisor_id'] : null;
    
    if (empty($student_id) || empty($advisor_id)) {
        $error = 'Please select both student and advisor.';
    } else {
        try {
            // Check if there's already an active assignment
            $stmt = $pdo->prepare("SELECT advisory_id FROM advisory_assignments WHERE student_id = ? AND status = 'Active'");
            $stmt->execute([$student_id]);
            $existing = $stmt->fetch();
            
            if ($existing) {
                // Update existing assignment
                $stmt = $pdo->prepare("UPDATE advisory_assignments SET advisor_id = ?, assigned_date = CURDATE() WHERE advisory_id = ?");
                $stmt->execute([$advisor_id, $existing['advisory_id']]);
                $success_message = 'Advisor assignment updated successfully.';
            } else {
                // Create new assignment
                $stmt = $pdo->prepare("INSERT INTO advisory_assignments (student_id, advisor_id, assigned_date, status) 
                                      VALUES (?, ?, CURDATE(), 'Active')");
                $stmt->execute([$student_id, $advisor_id]);
                $success_message = 'Advisor assigned successfully.';
            }
            
            // Also update student's advisor_id
            $stmt = $pdo->prepare("UPDATE students SET advisor_id = ? WHERE student_id = ?");
            $stmt->execute([$advisor_id, $student_id]);
            
            $success = true;
            header('Location: ' . BASE_URL . 'modules/advisory/index.php?success=' . urlencode($success_message));
            exit;
        } catch (PDOException $e) {
            $error = 'Error assigning advisor: ' . $e->getMessage();
        }
    }
}

include __DIR__ . '/../../includes/header.php';
?>

<div class="page-container">
    <div class="page-header">
        <h1><i class="fas fa-user-friends"></i> Assign Advisor</h1>
        <a href="<?php echo BASE_URL; ?>modules/advisory/index.php" class="btn btn-secondary">
            <i class="fas fa-arrow-left"></i> Back
        </a>
    </div>

    <?php if ($error): ?>
        <div class="alert alert-error"><?php echo sanitizeOutput($error); ?></div>
    <?php endif; ?>

    <div class="card">
        <form method="POST" action="" class="form">
            <div class="form-section">
                <h3><i class="fas fa-user-graduate"></i> Student Information</h3>
                
                <div class="form-group required">
                    <label for="student_id">Student</label>
                    <select id="student_id" name="student_id" required>
                        <option value="">Select Student</option>
                        <?php foreach ($students as $student): ?>
                            <option value="<?php echo $student['student_id']; ?>" <?php echo (isset($_POST['student_id']) && $_POST['student_id'] == $student['student_id']) ? 'selected' : ''; ?>>
                                <?php echo sanitizeOutput($student['student_number'] . ' - ' . $student['first_name'] . ' ' . $student['last_name'] . ($student['program_name'] ? ' (' . $student['program_name'] . ')' : '')); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <div class="form-section">
                <h3><i class="fas fa-user-tie"></i> Advisor Information</h3>
                
                <div class="form-group required">
                    <label for="advisor_id">Advisor</label>
                    <select id="advisor_id" name="advisor_id" required>
                        <option value="">Select Advisor</option>
                        <?php foreach ($advisors as $advisor): ?>
                            <option value="<?php echo $advisor['faculty_id']; ?>" <?php echo (isset($_POST['advisor_id']) && $_POST['advisor_id'] == $advisor['faculty_id']) ? 'selected' : ''; ?>>
                                <?php echo sanitizeOutput($advisor['first_name'] . ' ' . $advisor['last_name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <div class="info-box" style="background: #e3f2fd; padding: 15px; border-left: 4px solid #2196F3; margin: 20px 0;">
                <p><strong>Note:</strong> If the student already has an active advisor assignment, it will be updated to the newly selected advisor.</p>
            </div>

            <div class="form-actions">
                <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Assign Advisor</button>
                <a href="<?php echo BASE_URL; ?>modules/advisory/index.php" class="btn btn-secondary">Cancel</a>
            </div>
        </form>
    </div>
</div>

<?php include __DIR__ . '/../../includes/footer.php'; ?>

