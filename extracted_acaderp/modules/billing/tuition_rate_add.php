<?php
require_once __DIR__ . '/../../config/config.php';
requireAuth([ROLE_ADMIN]);
$page_title = 'Add Tuition Rate';
$pdo = getDBConnection();

$error = '';
$success = false;

// Check if new columns exist
$lecture_rate_column_exists = false;
$laboratory_rate_column_exists = false;
try {
    $check_stmt = $pdo->query("SHOW COLUMNS FROM tuition_rates LIKE 'lecture_rate'");
    $lecture_rate_column_exists = $check_stmt->rowCount() > 0;
    $check_stmt = $pdo->query("SHOW COLUMNS FROM tuition_rates LIKE 'laboratory_rate'");
    $laboratory_rate_column_exists = $check_stmt->rowCount() > 0;
} catch (PDOException $e) {
    $lecture_rate_column_exists = false;
    $laboratory_rate_column_exists = false;
}

// Get all active programs
$programs = $pdo->query("SELECT p.program_id, p.program_name, d.department_name
                         FROM programs p
                         LEFT JOIN departments d ON p.department_id = d.department_id
                         WHERE p.status = 'Active'
                         ORDER BY d.department_name, p.program_name")->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $program_id = !empty($_POST['program_id']) ? (int)$_POST['program_id'] : null;
        $lecture_rate = !empty($_POST['lecture_rate']) ? (float)$_POST['lecture_rate'] : 0;
        $laboratory_rate = !empty($_POST['laboratory_rate']) ? (float)$_POST['laboratory_rate'] : 0;
        $effective_date = sanitizeInput($_POST['effective_date'] ?? date('Y-m-d'));
        $status = sanitizeInput($_POST['status'] ?? 'Active');
        $description = sanitizeInput($_POST['description'] ?? '');
        
        // Validation - program_id can be NULL for General Education
        // If program_id is empty string, set to NULL
        if ($program_id === '') {
            $program_id = null;
        }
        
        if ($lecture_rate <= 0) {
            throw new Exception('Lecture rate per unit is required and must be greater than 0.');
        }
        
        // Validate effective date cannot be in the past
        if (strtotime($effective_date) === false) {
            throw new Exception('Invalid effective date.');
        }
        
        $today = date('Y-m-d');
        if (strtotime($effective_date) < strtotime($today)) {
            throw new Exception('Effective date cannot be in the past.');
        }
        
        // Check if new columns exist, otherwise use old structure
        if ($lecture_rate_column_exists && $laboratory_rate_column_exists) {
            // New structure: use lecture_rate and laboratory_rate columns
            
            // If status is Active, deactivate any existing active rates for this program (or NULL for general education)
            if ($status === 'Active') {
                if ($program_id) {
                    $stmt = $pdo->prepare("UPDATE tuition_rates SET status = 'Inactive' 
                                          WHERE program_id = ? AND status = 'Active'");
                    $stmt->execute([$program_id]);
                } else {
                    // For general education (NULL program_id)
                    $stmt = $pdo->prepare("UPDATE tuition_rates SET status = 'Inactive' 
                                          WHERE program_id IS NULL AND status = 'Active'");
                    $stmt->execute();
                }
            }
            
            // Check for duplicate active rates
            if ($program_id) {
                $check_stmt = $pdo->prepare("SELECT COUNT(*) FROM tuition_rates 
                                            WHERE program_id = ? AND status = 'Active'");
                $check_stmt->execute([$program_id]);
            } else {
                $check_stmt = $pdo->prepare("SELECT COUNT(*) FROM tuition_rates 
                                            WHERE program_id IS NULL AND status = 'Active'");
                $check_stmt->execute();
            }
            if ($check_stmt->fetchColumn() > 0 && $status === 'Active') {
                $rate_type = $program_id ? 'this program' : 'General Education';
                throw new Exception("An active tuition rate already exists for $rate_type. The old rate has been deactivated, but please verify.");
            }
            
            // Insert new rate
            $stmt = $pdo->prepare("INSERT INTO tuition_rates 
                                  (program_id, lecture_rate, laboratory_rate, effective_date, status, description, rate_type, amount)
                                  VALUES (?, ?, ?, ?, ?, ?, 'Per Credit', ?)");
            $stmt->execute([
                $program_id, 
                $lecture_rate, 
                $laboratory_rate > 0 ? $laboratory_rate : null, 
                $effective_date, 
                $status, 
                $description,
                $lecture_rate // Store in amount column for backward compatibility
            ]);
        } else {
            // Old structure: use amount column (for backward compatibility)
            // This is a fallback if migration hasn't been run yet
            throw new Exception('Please run the migration first: database/migration_redesign_tuition_rates.sql');
        }
        
        $success = true;
        header('Location: ' . BASE_URL . 'modules/billing/tuition_rates.php?success=' . urlencode('Tuition rate added successfully.'));
        exit;
        
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

include __DIR__ . '/../../includes/header.php';
?>

<div class="page-container">
    <div class="page-header">
        <h1><i class="fas fa-plus"></i> Add Tuition Rate</h1>
        <a href="<?php echo BASE_URL; ?>modules/billing/tuition_rates.php" class="btn btn-secondary">
            <i class="fas fa-arrow-left"></i> Back
        </a>
    </div>

    <?php if ($error): ?>
        <div class="alert alert-error"><?php echo sanitizeOutput($error); ?></div>
    <?php endif; ?>
    
    <?php if (!$lecture_rate_column_exists || !$laboratory_rate_column_exists): ?>
        <div class="alert alert-warning">
            <strong>Note:</strong> The new tuition rate structure is not available. 
            <a href="<?php echo BASE_URL; ?>database/run_migration.php?file=migration_redesign_tuition_rates.sql" target="_blank">
                Run the migration
            </a> to enable program-based rates with separate lecture and laboratory rates.
        </div>
    <?php endif; ?>

    <form method="POST" action="" id="tuitionRateForm">
        <div class="card">
            <h2>Tuition Rate Information</h2>
            
            <div class="form-row">
                <div class="form-group" style="flex: 2;">
                    <label for="program_id">Program</label>
                    <select id="program_id" name="program_id" class="form-control">
                        <option value="">-- General Education / No Program --</option>
                        <?php 
                        $current_dept = '';
                        foreach ($programs as $prog): 
                            $dept_name = $prog['department_name'] ?? 'General';
                            if ($dept_name !== $current_dept) {
                                if ($current_dept !== '') {
                                    echo '</optgroup>';
                                }
                                echo '<optgroup label="' . sanitizeOutput($dept_name) . '">';
                                $current_dept = $dept_name;
                            }
                        ?>
                            <option value="<?php echo $prog['program_id']; ?>">
                                <?php echo sanitizeOutput($prog['program_name']); ?>
                            </option>
                        <?php endforeach; ?>
                        <?php if ($current_dept !== '') echo '</optgroup>'; ?>
                    </select>
                    <small class="text-muted">Select a program or leave as "General Education" for courses available to all students</small>
                </div>
            </div>
            
            <div class="form-row">
                <div class="form-group">
                    <label for="lecture_rate">Lecture Rate per Unit <span class="text-danger">*</span></label>
                    <div style="display: flex; align-items: center; gap: 0.5rem;">
                        <span>₱</span>
                        <input type="number" id="lecture_rate" name="lecture_rate" class="form-control" 
                               step="0.01" min="0.01" required>
                    </div>
                    <small class="text-muted">Rate per credit unit for lecture subjects</small>
                </div>
                
                <div class="form-group">
                    <label for="laboratory_rate">Laboratory Rate per Unit (Optional)</label>
                    <div style="display: flex; align-items: center; gap: 0.5rem;">
                        <span>₱</span>
                        <input type="number" id="laboratory_rate" name="laboratory_rate" class="form-control" 
                               step="0.01" min="0" value="0">
                    </div>
                    <small class="text-muted">Rate per credit unit for laboratory subjects (leave 0 if no lab subjects)</small>
                </div>
            </div>
            
            <div class="form-row">
                <div class="form-group">
                    <label for="effective_date">Effective Date <span class="text-danger">*</span></label>
                    <input type="date" id="effective_date" name="effective_date" class="form-control" 
                           value="<?php echo date('Y-m-d'); ?>" required min="<?php echo date('Y-m-d'); ?>">
                    <small class="text-muted">Cannot be in the past</small>
                </div>
                
                <div class="form-group">
                    <label for="status">Status</label>
                    <select id="status" name="status" class="form-control">
                        <option value="Active" selected>Active</option>
                        <option value="Inactive">Inactive</option>
                    </select>
                    <small class="text-muted">Active rates will be used for billing. Adding a new active rate will deactivate existing ones for this program.</small>
                </div>
            </div>
            
            <div class="form-row">
                <div class="form-group" style="flex: 2;">
                    <label for="description">Description (Optional)</label>
                    <textarea id="description" name="description" class="form-control" rows="2" placeholder="Additional notes about this rate..."><?php echo sanitizeOutput($_POST['description'] ?? ''); ?></textarea>
                </div>
            </div>
            
            <div class="form-actions">
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-save"></i> Save Tuition Rate
                </button>
                <a href="<?php echo BASE_URL; ?>modules/billing/tuition_rates.php" class="btn btn-secondary">
                    Cancel
                </a>
            </div>
        </div>
    </form>
</div>

<script>
// Validate form before submission
document.getElementById('tuitionRateForm').addEventListener('submit', function(e) {
    const lectureRate = parseFloat(document.getElementById('lecture_rate').value);
    const effectiveDate = document.getElementById('effective_date').value;
    const today = new Date().toISOString().split('T')[0];
    
    // Program is optional - can be General Education (empty value)
    
    if (!lectureRate || lectureRate <= 0) {
        e.preventDefault();
        alert('Lecture rate per unit is required and must be greater than 0.');
        return false;
    }
    
    if (effectiveDate < today) {
        e.preventDefault();
        alert('Effective date cannot be in the past.');
        return false;
    }
});
</script>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
