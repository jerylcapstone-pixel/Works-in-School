<?php
require_once __DIR__ . '/../../config/config.php';
requireAuth([ROLE_ADMIN]);
$page_title = 'Tuition Rate Setup';
$pdo = getDBConnection();

$error = '';
$success = '';

// Get filter parameters
$filter_program = isset($_GET['filter_program']) ? (int)$_GET['filter_program'] : 0;

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

// Get all programs for dropdown
$programs = $pdo->query("SELECT program_id, program_name FROM programs WHERE status = 'Active' ORDER BY program_name")->fetchAll();

// Build query with filters
$query = "SELECT tr.*, p.program_name, d.department_name
          FROM tuition_rates tr
          LEFT JOIN programs p ON tr.program_id = p.program_id
          LEFT JOIN departments d ON p.department_id = d.department_id
          WHERE 1=1";
$params = [];

if ($filter_program > 0) {
    $query .= " AND tr.program_id = ?";
    $params[] = $filter_program;
}

$query .= " ORDER BY p.program_name, tr.effective_date DESC";

if (!empty($params)) {
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $tuition_rates = $stmt->fetchAll();
} else {
    $tuition_rates = $pdo->query($query)->fetchAll();
}

include __DIR__ . '/../../includes/header.php';
?>

<div class="page-container">
    <div class="page-header">
        <h1><i class="fas fa-calculator"></i> Tuition Rate Setup</h1>
        <div style="display: flex; gap: 10px;">
            <a href="<?php echo BASE_URL; ?>modules/billing/index.php" class="btn btn-secondary">
                <i class="fas fa-arrow-left"></i> Back
            </a>
            <a href="<?php echo BASE_URL; ?>modules/billing/tuition_rate_add.php" class="btn btn-primary">
                <i class="fas fa-plus"></i> Add Tuition Rate
            </a>
        </div>
    </div>

    <?php if ($error): ?>
        <div class="alert alert-error"><?php echo sanitizeOutput($error); ?></div>
    <?php endif; ?>
    
    <?php if ($success || isset($_GET['success'])): ?>
        <div class="alert alert-success"><?php echo sanitizeOutput($success ?: $_GET['success']); ?></div>
    <?php endif; ?>
    
    <?php if (!$lecture_rate_column_exists || !$laboratory_rate_column_exists): ?>
        <div class="alert alert-warning">
            <strong>Note:</strong> The new tuition rate structure is not available. 
            <a href="<?php echo BASE_URL; ?>database/run_migration.php?file=migration_redesign_tuition_rates.sql" target="_blank">
                Run the migration
            </a> to enable program-based rates with separate lecture and laboratory rates.
        </div>
    <?php endif; ?>

    <div class="card" style="margin-bottom: 1.5rem;">
        <h2>Filter Tuition Rates</h2>
        <form method="GET" action="" style="display: flex; gap: 1rem; flex-wrap: wrap; align-items: end;">
            <div class="form-group" style="flex: 1; min-width: 200px;">
                <label for="filter_program">Program</label>
                <select id="filter_program" name="filter_program" class="form-control">
                    <option value="0">All Programs</option>
                    <?php foreach ($programs as $prog): ?>
                        <option value="<?php echo $prog['program_id']; ?>" 
                                <?php echo $filter_program == $prog['program_id'] ? 'selected' : ''; ?>>
                            <?php echo sanitizeOutput($prog['program_name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <button type="submit" class="btn btn-secondary">
                    <i class="fas fa-search"></i> Filter
                </button>
                <a href="<?php echo BASE_URL; ?>modules/billing/tuition_rates.php" class="btn btn-outline">
                    Clear
                </a>
            </div>
        </form>
    </div>

    <div class="card">
        <h2>Tuition Rates (<?php echo count($tuition_rates); ?>)</h2>
        
        <?php if (empty($tuition_rates)): ?>
            <div class="alert alert-info">No tuition rates found. <a href="<?php echo BASE_URL; ?>modules/billing/tuition_rate_add.php">Add a new tuition rate</a> to get started.</div>
        <?php else: ?>
            <div style="overflow-x: auto;">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Program</th>
                            <?php if ($lecture_rate_column_exists && $laboratory_rate_column_exists): ?>
                                <th>Lecture Rate per Unit</th>
                                <th>Laboratory Rate per Unit</th>
                            <?php else: ?>
                                <th>Amount per Unit</th>
                            <?php endif; ?>
                            <th>Effective Date</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($tuition_rates as $rate): ?>
                        <tr>
                            <td>
                                <?php if ($rate['program_id'] && $rate['program_name']): ?>
                                    <strong><?php echo sanitizeOutput($rate['program_name']); ?></strong>
                                    <?php if ($rate['department_name']): ?>
                                        <br><small class="text-muted"><?php echo sanitizeOutput($rate['department_name']); ?></small>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <strong class="text-primary">General Education / No Program</strong>
                                    <br><small class="text-muted">Applies to courses available to all students</small>
                                <?php endif; ?>
                            </td>
                            <?php if ($lecture_rate_column_exists && $laboratory_rate_column_exists): ?>
                                <td>
                                    <strong>₱<?php echo number_format((float)($rate['lecture_rate'] ?? $rate['amount'] ?? 0), 2); ?></strong>
                                </td>
                                <td>
                                    <?php if ($rate['laboratory_rate'] && (float)$rate['laboratory_rate'] > 0): ?>
                                        <strong>₱<?php echo number_format((float)$rate['laboratory_rate'], 2); ?></strong>
                                    <?php else: ?>
                                        <span class="text-muted">Not set</span>
                                    <?php endif; ?>
                                </td>
                            <?php else: ?>
                                <td><strong>₱<?php echo number_format($rate['amount'], 2); ?></strong></td>
                            <?php endif; ?>
                            <td><?php echo date('M d, Y', strtotime($rate['effective_date'])); ?></td>
                            <td>
                                <span class="badge badge-<?php echo ($rate['status'] ?? 'Active') === 'Active' ? 'success' : 'error'; ?>">
                                    <?php echo sanitizeOutput($rate['status'] ?? 'Active'); ?>
                                </span>
                            </td>
                            <td class="actions">
                                <a href="<?php echo BASE_URL; ?>modules/billing/tuition_rate_edit.php?id=<?php echo $rate['rate_id']; ?>" class="btn btn-sm btn-info">
                                    <i class="fas fa-edit"></i>
                                </a>
                                <form method="POST" action="tuition_rate_delete.php" style="display: inline;" 
                                      onsubmit="return confirm('Are you sure you want to delete this tuition rate?');">
                                    <input type="hidden" name="rate_id" value="<?php echo $rate['rate_id']; ?>">
                                    <button type="submit" class="btn btn-sm btn-danger">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </form>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
