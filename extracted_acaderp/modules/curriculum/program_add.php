<?php
require_once __DIR__ . '/../../config/config.php';
requireAuth(ROLE_ADMIN);
$page_title = 'Add Program';
$pdo = getDBConnection();
$error = '';

$departments = [];
$stmt = $pdo->query("SELECT department_id, department_name, department_code FROM departments ORDER BY department_name");
$departments = $stmt->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $program_name = sanitizeInput($_POST['program_name'] ?? '');
    $department_id = !empty($_POST['department_id']) ? (int)$_POST['department_id'] : null;
    $degree_type = sanitizeInput($_POST['degree_type'] ?? '');
    $total_credits = (int)($_POST['total_credits'] ?? 0);
    $duration_years = !empty($_POST['duration_years']) ? (int)$_POST['duration_years'] : null;
    $description = sanitizeInput($_POST['description'] ?? '');
    $status = 'Active'; // New programs are always set to Active

    if (empty($program_name) || empty($degree_type) || $total_credits <= 0 || $duration_years <= 0) {
        $error = 'Please fill in all required fields.';
    } else {
        // Get next program_id to generate code before insert
        $stmt = $pdo->query("SELECT COALESCE(MAX(program_id), 0) + 1 as next_id FROM programs");
        $result = $stmt->fetch();
        $next_id = $result['next_id'];
        
        // Generate auto-increment program code: PROG-001, PROG-002, etc.
        $program_code = 'PROG-' . str_pad($next_id, 3, '0', STR_PAD_LEFT);
        
        // Insert program with the generated code
            $sql = "INSERT INTO programs (program_code, program_name, department_id, degree_type, total_credits, duration_years, description, status)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
            $stmt = $pdo->prepare($sql);
            if ($stmt->execute([$program_code, $program_name, $department_id, $degree_type, $total_credits, $duration_years, $description ?: null, $status])) {
            header('Location: ' . BASE_URL . 'modules/curriculum/index.php?tab=programs&success=Program added successfully with code: ' . $program_code);
                exit;
            } else {
                $error = 'Error adding program.';
        }
    }
}

include __DIR__ . '/../../includes/header.php';
?>

<div class="page-container">
    <div class="page-header">
        <h1><i class="fas fa-graduation-cap"></i> Add Program</h1>
        <a href="<?php echo BASE_URL; ?>modules/curriculum/index.php?tab=programs" class="btn btn-secondary">
            <i class="fas fa-arrow-left"></i> Back
        </a>
    </div>

    <?php if ($error): ?>
        <div class="alert alert-error"><?php echo sanitizeOutput($error); ?></div>
    <?php endif; ?>

    <div class="card">
        <form method="POST" action="" class="form">
            <div class="form-row">
                <div class="form-group">
                    <label>Program Code</label>
                    <input type="text" class="form-control" value="Auto-generated" readonly style="background-color: #f5f5f5; cursor: not-allowed;">
                    <small class="text-muted">Program code will be auto-generated (e.g., PROG-001, PROG-002)</small>
                </div>
                <div class="form-group">
                    <label>Program Name <span class="required">*</span></label>
                    <input type="text" name="program_name" required class="form-control" 
                           value="<?php echo sanitizeOutput($_POST['program_name'] ?? ''); ?>">
                </div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label>Department</label>
                    <select name="department_id" class="form-control">
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
                    <label>Degree Type <span class="required">*</span></label>
                    <select name="degree_type" required class="form-control">
                        <option value="">Select</option>
                        <option value="Associate" <?php echo (($_POST['degree_type'] ?? '') === 'Associate') ? 'selected' : ''; ?>>Associate</option>
                        <option value="Bachelor" <?php echo (($_POST['degree_type'] ?? '') === 'Bachelor') ? 'selected' : ''; ?>>Bachelor</option>
                        <option value="Master" <?php echo (($_POST['degree_type'] ?? '') === 'Master') ? 'selected' : ''; ?>>Master</option>
                        <option value="Doctorate" <?php echo (($_POST['degree_type'] ?? '') === 'Doctorate') ? 'selected' : ''; ?>>Doctorate</option>
                        <option value="Certificate" <?php echo (($_POST['degree_type'] ?? '') === 'Certificate') ? 'selected' : ''; ?>>Certificate</option>
                    </select>
                </div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label>Total Credits <span class="required">*</span></label>
                    <input type="number" name="total_credits" required class="form-control" min="1"
                           value="<?php echo sanitizeOutput($_POST['total_credits'] ?? ''); ?>">
                </div>
                <div class="form-group">
                    <label>Duration (Years) <span class="required">*</span></label>
                    <input type="number" name="duration_years" required class="form-control" min="1" max="10"
                           value="<?php echo sanitizeOutput($_POST['duration_years'] ?? ''); ?>">
                </div>
            </div>

            <div class="form-group">
                <label>Description</label>
                <textarea name="description" class="form-control" rows="4"><?php echo sanitizeOutput($_POST['description'] ?? ''); ?></textarea>
            </div>

            <div class="form-actions">
                <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Add Program</button>
                <a href="<?php echo BASE_URL; ?>modules/curriculum/index.php?tab=programs" class="btn btn-secondary">Cancel</a>
            </div>
        </form>
    </div>
</div>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
