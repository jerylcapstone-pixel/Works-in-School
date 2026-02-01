<?php
require_once __DIR__ . '/../../config/config.php';
requireAuth([ROLE_ADMIN]);
$page_title = 'Manage Student Additional Fees';
$pdo = getDBConnection();

$error = '';
$success = '';

// Check if student_additional_fees table exists
$table_exists = false;
try {
    $stmt = $pdo->query("SHOW TABLES LIKE 'student_additional_fees'");
    $table_exists = $stmt->rowCount() > 0;
} catch (PDOException $e) {
    $table_exists = false;
}

if (!$table_exists) {
    $error = 'The student_additional_fees table does not exist. Please run the migration: database/migration_add_billing_fees.sql';
}

// Get student ID from URL
$student_id = isset($_GET['student_id']) ? (int)$_GET['student_id'] : 0;

// Get student info
$student = null;
if ($student_id) {
    $stmt = $pdo->prepare("SELECT s.*, p.program_name FROM students s LEFT JOIN programs p ON s.program_id = p.program_id WHERE s.student_id = ?");
    $stmt->execute([$student_id]);
    $student = $stmt->fetch();
}

// Get all students for selection
$students = $pdo->query("SELECT s.student_id, s.student_number, s.first_name, s.last_name, p.program_name
                         FROM students s
                         LEFT JOIN programs p ON s.program_id = p.program_id
                         WHERE s.status = 'Active'
                         ORDER BY s.student_number")->fetchAll();

// Get student's additional fees
$student_fees = [];
if ($student_id && $table_exists) {
    $stmt = $pdo->prepare("SELECT * FROM student_additional_fees WHERE student_id = ? AND status = 'Active' ORDER BY created_at DESC");
    $stmt->execute([$student_id]);
    $student_fees = $stmt->fetchAll();
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['add_fee'])) {
        try {
            $student_id = (int)($_POST['student_id'] ?? 0);
            $fee_name = sanitizeInput($_POST['fee_name'] ?? '');
            $amount = (float)($_POST['amount'] ?? 0);
            $description = sanitizeInput($_POST['description'] ?? '');
            $semester = sanitizeInput($_POST['semester'] ?? '');
            $academic_year = sanitizeInput($_POST['academic_year'] ?? '');
            
            if (!$student_id || !$fee_name || $amount <= 0) {
                throw new Exception('Please fill in all required fields.');
            }
            
            $stmt = $pdo->prepare("INSERT INTO student_additional_fees (student_id, fee_name, amount, description, semester, academic_year)
                                  VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->execute([$student_id, $fee_name, $amount, $description, $semester, $academic_year]);
            
            $success = 'Student additional fee added successfully.';
            header('Location: ' . BASE_URL . 'modules/billing/student_fees_manage.php?student_id=' . $student_id . '&success=' . urlencode($success));
            exit;
        } catch (Exception $e) {
            $error = $e->getMessage();
        }
    } elseif (isset($_POST['delete_fee'])) {
        try {
            $fee_id = (int)($_POST['fee_id'] ?? 0);
            $student_id = (int)($_POST['student_id'] ?? 0);
            
            // Soft delete by setting status to Cancelled
            $stmt = $pdo->prepare("UPDATE student_additional_fees SET status = 'Cancelled' WHERE additional_fee_id = ?");
            $stmt->execute([$fee_id]);
            
            $success = 'Student additional fee removed successfully.';
            header('Location: ' . BASE_URL . 'modules/billing/student_fees_manage.php?student_id=' . $student_id . '&success=' . urlencode($success));
            exit;
        } catch (Exception $e) {
            $error = $e->getMessage();
        }
    }
}

include __DIR__ . '/../../includes/header.php';
?>

<div class="page-container">
    <div class="page-header">
        <h1><i class="fas fa-user-dollar"></i> Manage Student Additional Fees</h1>
        <a href="<?php echo BASE_URL; ?>modules/billing/index.php" class="btn btn-secondary">
            <i class="fas fa-arrow-left"></i> Back to Billing
        </a>
    </div>

    <?php if ($error): ?>
        <div class="alert alert-error"><?php echo sanitizeOutput($error); ?></div>
    <?php endif; ?>
    
    <?php if ($success || isset($_GET['success'])): ?>
        <div class="alert alert-success"><?php echo sanitizeOutput($success ?: $_GET['success']); ?></div>
    <?php endif; ?>

    <div class="card" style="margin-bottom: 1.5rem;">
        <h2>Select Student</h2>
        <form method="GET" action="" style="display: flex; gap: 1rem; align-items: end;">
            <div class="form-group" style="flex: 1;">
                <label for="student_id">Student</label>
                <select id="student_id" name="student_id" class="form-control" required onchange="this.form.submit()">
                    <option value="">-- Select Student --</option>
                    <?php foreach ($students as $s): ?>
                        <option value="<?php echo $s['student_id']; ?>" <?php echo ($student_id == $s['student_id']) ? 'selected' : ''; ?>>
                            <?php echo sanitizeOutput($s['student_number'] . ' - ' . $s['first_name'] . ' ' . $s['last_name'] . 
                                ($s['program_name'] ? ' (' . $s['program_name'] . ')' : '')); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
        </form>
    </div>

    <?php if ($student): ?>
        <div class="card" style="margin-bottom: 1.5rem;">
            <h2>Student Information</h2>
            <div class="info-grid">
                <div><strong>Student Number:</strong> <?php echo sanitizeOutput($student['student_number']); ?></div>
                <div><strong>Name:</strong> <?php echo sanitizeOutput($student['first_name'] . ' ' . $student['last_name']); ?></div>
                <div><strong>Program:</strong> <?php echo sanitizeOutput($student['program_name'] ?? 'N/A'); ?></div>
            </div>
        </div>

        <?php if ($table_exists): ?>
        <div class="card" style="margin-bottom: 1.5rem;">
            <h2>Add Additional Fee</h2>
            <form method="POST" action="">
                <input type="hidden" name="student_id" value="<?php echo $student_id; ?>">
                
                <div class="form-row">
                    <div class="form-group" style="flex: 2;">
                        <label for="fee_name">Fee Name <span class="text-danger">*</span></label>
                        <input type="text" id="fee_name" name="fee_name" class="form-control" 
                               placeholder="e.g., Field Trip, ID Replacement" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="amount">Amount <span class="text-danger">*</span></label>
                        <input type="number" id="amount" name="amount" class="form-control" 
                               step="0.01" min="0" required>
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="semester">Semester</label>
                        <select id="semester" name="semester" class="form-control">
                            <option value="">-- Optional --</option>
                            <option value="First">First</option>
                            <option value="2nd">2nd</option>
                            <option value="Summer">Summer</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="academic_year">Academic Year</label>
                        <input type="text" id="academic_year" name="academic_year" class="form-control" 
                               placeholder="e.g., 2024-25">
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="description">Description</label>
                    <textarea id="description" name="description" class="form-control" rows="2"></textarea>
                </div>
                
                <div class="form-actions">
                    <button type="submit" name="add_fee" class="btn btn-primary">
                        <i class="fas fa-plus"></i> Add Fee
                    </button>
                </div>
            </form>
        </div>

        <div class="card">
            <h2>Student Additional Fees</h2>
            
            <?php if (empty($student_fees)): ?>
                <div class="alert alert-info">No additional fees for this student.</div>
            <?php else: ?>
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Fee Name</th>
                            <th>Amount</th>
                            <th>Description</th>
                            <th>Semester</th>
                            <th>Academic Year</th>
                            <th>Date Added</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($student_fees as $fee): ?>
                        <tr>
                            <td><strong><?php echo sanitizeOutput($fee['fee_name']); ?></strong></td>
                            <td>₱<?php echo number_format($fee['amount'], 2); ?></td>
                            <td><?php echo sanitizeOutput($fee['description'] ?? '-'); ?></td>
                            <td><?php echo sanitizeOutput($fee['semester'] ?? '-'); ?></td>
                            <td><?php echo sanitizeOutput($fee['academic_year'] ?? '-'); ?></td>
                            <td><?php echo date('M d, Y', strtotime($fee['created_at'])); ?></td>
                            <td class="actions">
                                <form method="POST" action="" style="display: inline;" onsubmit="return confirm('Are you sure you want to remove this fee?');">
                                    <input type="hidden" name="fee_id" value="<?php echo $fee['additional_fee_id']; ?>">
                                    <input type="hidden" name="student_id" value="<?php echo $student_id; ?>">
                                    <button type="submit" name="delete_fee" class="btn btn-sm btn-danger">
                                        <i class="fas fa-trash"></i> Remove
                                    </button>
                                </form>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        <tr style="background-color: #f5f5f5; font-weight: bold;">
                            <td colspan="1">Total:</td>
                            <td>₱<?php echo number_format(array_sum(array_column($student_fees, 'amount')), 2); ?></td>
                            <td colspan="5"></td>
                        </tr>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
        <?php endif; ?>
    <?php endif; ?>
</div>

<?php include __DIR__ . '/../../includes/footer.php'; ?>

