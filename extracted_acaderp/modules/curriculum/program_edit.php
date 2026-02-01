<?php
require_once __DIR__ . '/../../config/config.php';
requireAuth(ROLE_ADMIN);
$page_title = 'Edit Program';
$prog_id = (int)($_GET['id'] ?? 0);
if (!$prog_id) {
    header('Location: ' . BASE_URL . 'modules/curriculum/index.php?tab=programs&error=Invalid program ID');
    exit;
}

$pdo = getDBConnection();
$error = '';
$stmt = $pdo->prepare("SELECT * FROM programs WHERE program_id = ?");
$stmt->execute([$prog_id]);
$program = $stmt->fetch();
if (!$program) {
    header('Location: ' . BASE_URL . 'modules/curriculum/index.php?tab=programs&error=Program not found');
    exit;
}

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
    $status = sanitizeInput($_POST['status'] ?? 'Active');

    if (empty($program_name) || empty($degree_type) || $total_credits <= 0) {
        $error = 'Please fill in all required fields.';
    } else {
        // Update program (code is auto-generated, so we don't update it)
        $sql = "UPDATE programs SET program_name = ?, department_id = ?, degree_type = ?, 
                    total_credits = ?, duration_years = ?, description = ?, status = ? WHERE program_id = ?";
            $stmt = $pdo->prepare($sql);
        if ($stmt->execute([$program_name, $department_id, $degree_type, $total_credits, $duration_years, $description ?: null, $status, $prog_id])) {
                header('Location: ' . BASE_URL . 'modules/curriculum/index.php?tab=programs&success=Program updated successfully');
                exit;
            } else {
                $error = 'Error updating program.';
        }
    }
    $program = array_merge($program, $_POST);
}

include __DIR__ . '/../../includes/header.php';
?>

<div class="page-container">
    <div class="page-header">
        <h1><i class="fas fa-edit"></i> Edit Program</h1>
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
                    <input type="text" class="form-control" 
                           value="<?php echo sanitizeOutput($program['program_code']); ?>" 
                           readonly style="background-color: #f5f5f5; cursor: not-allowed;">
                    <small class="text-muted">Program code is auto-generated and cannot be changed</small>
                </div>
                <div class="form-group">
                    <label>Program Name <span class="required">*</span></label>
                    <input type="text" name="program_name" required class="form-control" 
                           value="<?php echo sanitizeOutput($program['program_name']); ?>">
                </div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label>Department</label>
                    <select name="department_id" class="form-control">
                        <option value="">Select Department</option>
                        <?php foreach ($departments as $dept): ?>
                            <option value="<?php echo $dept['department_id']; ?>"
                                    <?php echo (($program['department_id'] ?? '') == $dept['department_id']) ? 'selected' : ''; ?>>
                                <?php echo sanitizeOutput($dept['department_name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>Degree Type <span class="required">*</span></label>
                    <select name="degree_type" required class="form-control">
                        <option value="">Select</option>
                        <option value="Associate" <?php echo ($program['degree_type'] === 'Associate') ? 'selected' : ''; ?>>Associate</option>
                        <option value="Bachelor" <?php echo ($program['degree_type'] === 'Bachelor') ? 'selected' : ''; ?>>Bachelor</option>
                        <option value="Master" <?php echo ($program['degree_type'] === 'Master') ? 'selected' : ''; ?>>Master</option>
                        <option value="Doctorate" <?php echo ($program['degree_type'] === 'Doctorate') ? 'selected' : ''; ?>>Doctorate</option>
                        <option value="Certificate" <?php echo ($program['degree_type'] === 'Certificate') ? 'selected' : ''; ?>>Certificate</option>
                    </select>
                </div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label>Total Credits <span class="required">*</span></label>
                    <input type="number" name="total_credits" required class="form-control" min="1"
                           value="<?php echo $program['total_credits']; ?>">
                </div>
                <div class="form-group">
                    <label>Duration (Years)</label>
                    <input type="number" name="duration_years" class="form-control" min="1" max="10"
                           value="<?php echo $program['duration_years'] ?? ''; ?>">
                </div>
                <div class="form-group">
                    <label>Status</label>
                    <select name="status" class="form-control" required>
                        <option value="Active" <?php echo ($program['status'] === 'Active') ? 'selected' : ''; ?>>Active</option>
                        <option value="Inactive" <?php echo ($program['status'] === 'Inactive') ? 'selected' : ''; ?>>Inactive</option>
                        <option value="Archived" <?php echo ($program['status'] === 'Archived') ? 'selected' : ''; ?>>Archived</option>
                    </select>
                </div>
            </div>

            <div class="form-group">
                <label>Description</label>
                <textarea name="description" class="form-control" rows="4"><?php echo sanitizeOutput($program['description'] ?? ''); ?></textarea>
            </div>

            <div class="form-actions">
                <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Update Program</button>
                <a href="<?php echo BASE_URL; ?>modules/curriculum/index.php?tab=programs" class="btn btn-secondary">Cancel</a>
            </div>
        </form>
    </div>
</div>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
