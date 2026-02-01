<?php
require_once __DIR__ . '/../../config/config.php';
requireAuth([ROLE_ADMIN]);
$page_title = 'Manage Standard Fees';
$pdo = getDBConnection();

$error = '';
$success = '';

// Default fee categories
// Note: Tuition Fee is calculated per credit based on Tuition Rate Setup, not as a fixed standard fee
$default_categories = [
    'Laboratory Fee',
    'Matriculation Fee',
    'Other Fees',
    'Test Paper',
    'Charges'
];

// Check if standard_fees table exists
$table_exists = false;
try {
    $stmt = $pdo->query("SHOW TABLES LIKE 'standard_fees'");
    $table_exists = $stmt->rowCount() > 0;
} catch (PDOException $e) {
    $table_exists = false;
}

if (!$table_exists) {
    $error = 'The standard_fees table does not exist. Please run the migration: database/migration_add_billing_fees.sql';
}

// Initialize default categories if table is empty
if ($table_exists) {
    $existing_categories = $pdo->query("SELECT fee_category FROM standard_fees")->fetchAll(PDO::FETCH_COLUMN);
    foreach ($default_categories as $category) {
        if (!in_array($category, $existing_categories)) {
            $stmt = $pdo->prepare("INSERT INTO standard_fees (fee_category, amount, is_active) VALUES (?, 0, 1)");
            $stmt->execute([$category]);
        }
    }
    
    // Remove "Tuition Fee" from standard fees if it exists (tuition is calculated per credit, not as a fixed fee)
    if (in_array('Tuition Fee', $existing_categories)) {
        $stmt = $pdo->prepare("DELETE FROM standard_fees WHERE fee_category = 'Tuition Fee'");
        $stmt->execute();
    }
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['update_fees'])) {
        try {
            $pdo->beginTransaction();
            
            // Update existing fees
            if (isset($_POST['fees']) && is_array($_POST['fees'])) {
                foreach ($_POST['fees'] as $fee_id => $data) {
                    $fee_id = (int)$fee_id;
                    $amount = isset($data['amount']) && $data['amount'] !== '' ? (float)$data['amount'] : 0;
                    $is_active = isset($data['is_active']) ? 1 : 0;
                    
                    $stmt = $pdo->prepare("UPDATE standard_fees SET amount = ?, is_active = ? WHERE fee_id = ?");
                    $stmt->execute([$amount, $is_active, $fee_id]);
                }
            }
            
            // Add new category if provided
            if (!empty($_POST['new_category'])) {
                $new_category = sanitizeInput(trim($_POST['new_category']));
                if ($new_category) {
                    // Check if category already exists
                    $check_stmt = $pdo->prepare("SELECT COUNT(*) FROM standard_fees WHERE fee_category = ?");
                    $check_stmt->execute([$new_category]);
                    if ($check_stmt->fetchColumn() == 0) {
                        $amount = isset($_POST['new_amount']) && $_POST['new_amount'] !== '' ? (float)$_POST['new_amount'] : 0;
                        $stmt = $pdo->prepare("INSERT INTO standard_fees (fee_category, amount, is_active) VALUES (?, ?, 1)");
                        $stmt->execute([$new_category, $amount]);
                    } else {
                        throw new Exception('Fee category already exists.');
                    }
                }
            }
            
            $pdo->commit();
            $success = 'Standard fees updated successfully.';
            header('Location: ' . BASE_URL . 'modules/billing/fees_manage.php?success=' . urlencode($success));
            exit;
        } catch (Exception $e) {
            $pdo->rollBack();
            $error = $e->getMessage();
        }
    } elseif (isset($_POST['delete_category'])) {
        try {
            $fee_id = (int)($_POST['fee_id'] ?? 0);
            if ($fee_id) {
                $stmt = $pdo->prepare("DELETE FROM standard_fees WHERE fee_id = ?");
                $stmt->execute([$fee_id]);
                $success = 'Fee category removed successfully.';
                header('Location: ' . BASE_URL . 'modules/billing/fees_manage.php?success=' . urlencode($success));
                exit;
            }
        } catch (Exception $e) {
            $error = $e->getMessage();
        }
    }
}

// Get all standard fees
$fees = [];
if ($table_exists) {
    $fees = $pdo->query("SELECT * FROM standard_fees ORDER BY fee_category")->fetchAll();
}

include __DIR__ . '/../../includes/header.php';
?>

<div class="page-container">
    <div class="page-header">
        <h1><i class="fas fa-money-bill-wave"></i> Manage Standard Fees</h1>
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

    <?php if ($table_exists): ?>
    <form method="POST" action="" id="feesForm">
        <div class="card" style="margin-bottom: 1.5rem;">
            <h2>Standard Fees</h2>
            <p style="color: var(--text-color-secondary); margin-bottom: 1.5rem;">
                Set the amount for each fee category. Leave blank to set amount as 0.00.
            </p>
            
            <div style="overflow-x: auto;">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Fee Category</th>
                            <th>Amount</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($fees)): ?>
                            <tr>
                                <td colspan="4" class="text-center">No fee categories found. Add a new category below.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($fees as $fee): ?>
                            <tr>
                                <td><strong><?php echo sanitizeOutput($fee['fee_category']); ?></strong></td>
                                <td>
                                    <div style="display: flex; align-items: center; gap: 0.5rem;">
                                        <span>₱</span>
                                        <input type="number" 
                                               name="fees[<?php echo $fee['fee_id']; ?>][amount]" 
                                               class="form-control" 
                                               step="0.01" 
                                               min="0" 
                                               value="<?php echo $fee['amount'] > 0 ? number_format($fee['amount'], 2, '.', '') : ''; ?>"
                                               placeholder="0.00"
                                               style="width: 150px;">
                                    </div>
                                </td>
                                <td>
                                    <label style="display: flex; align-items: center; gap: 0.5rem; cursor: pointer;">
                                        <input type="checkbox" 
                                               name="fees[<?php echo $fee['fee_id']; ?>][is_active]" 
                                               <?php echo $fee['is_active'] ? 'checked' : ''; ?>>
                                        <span class="badge badge-<?php echo $fee['is_active'] ? 'success' : 'error'; ?>">
                                            <?php echo $fee['is_active'] ? 'Active' : 'Inactive'; ?>
                                        </span>
                                    </label>
                                </td>
                                <td class="actions">
                                    <form method="POST" action="" style="display: inline;" 
                                          onsubmit="return confirm('Are you sure you want to remove this fee category?');">
                                        <input type="hidden" name="fee_id" value="<?php echo $fee['fee_id']; ?>">
                                        <button type="submit" name="delete_category" class="btn btn-sm btn-danger">
                                            <i class="fas fa-trash"></i> Remove
                                        </button>
                                    </form>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="card" style="margin-bottom: 1.5rem;">
            <h2>Add New Fee Category</h2>
            <div class="form-row">
                <div class="form-group" style="flex: 2;">
                    <label for="new_category">Category Name <span class="text-danger">*</span></label>
                    <input type="text" id="new_category" name="new_category" class="form-control" 
                           placeholder="e.g., Library Fee, Sports Fee" maxlength="100">
                </div>
                <div class="form-group" style="flex: 1;">
                    <label for="new_amount">Amount (Optional)</label>
                    <div style="display: flex; align-items: center; gap: 0.5rem;">
                        <span>$</span>
                        <input type="number" id="new_amount" name="new_amount" class="form-control" 
                               step="0.01" min="0" placeholder="0.00">
                    </div>
                </div>
            </div>
        </div>

        <div class="form-actions">
            <button type="submit" name="update_fees" class="btn btn-primary">
                <i class="fas fa-save"></i> Save Changes
            </button>
            <a href="<?php echo BASE_URL; ?>modules/billing/index.php" class="btn btn-secondary">
                Cancel
            </a>
        </div>
    </form>
    <?php endif; ?>
</div>

<script>
// Auto-save calculation preview
document.addEventListener('DOMContentLoaded', function() {
    const form = document.getElementById('feesForm');
    const inputs = form.querySelectorAll('input[type="number"]');
    
    inputs.forEach(input => {
        input.addEventListener('input', function() {
            // Format the display value
            if (this.value && parseFloat(this.value) >= 0) {
                // Keep the value for form submission
            }
        });
    });
});
</script>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
