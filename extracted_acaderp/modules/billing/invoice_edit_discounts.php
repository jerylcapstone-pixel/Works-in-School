<?php
/**
 * Edit Invoice - Update Discounts and Financial Aid
 * Allows admin to edit discounts and financial aid amounts for an invoice
 */
require_once __DIR__ . '/../../config/config.php';
requireAuth([ROLE_ADMIN]);
$page_title = 'Edit Invoice - Discounts';
$pdo = getDBConnection();

$invoice_id = (int)($_GET['id'] ?? 0);
$error = '';
$success = '';

if (!$invoice_id) {
    header('Location: ' . BASE_URL . 'modules/billing/index.php?error=Invalid invoice ID');
    exit;
}

// Get invoice details
$stmt = $pdo->prepare("SELECT i.*, s.student_number, s.first_name, s.last_name, s.program_id, p.program_name
                      FROM invoices i
                      LEFT JOIN students s ON i.student_id = s.student_id
                      LEFT JOIN programs p ON s.program_id = p.program_id
                      WHERE i.invoice_id = ?");
$stmt->execute([$invoice_id]);
$invoice = $stmt->fetch();

if (!$invoice) {
    header('Location: ' . BASE_URL . 'modules/billing/index.php?error=Invoice not found');
    exit;
}

// Get invoice items
$stmt = $pdo->prepare("SELECT * FROM invoice_items WHERE invoice_id = ? ORDER BY item_id");
$stmt->execute([$invoice_id]);
$invoice_items = $stmt->fetchAll();

// Get total payments
$stmt = $pdo->prepare("SELECT COALESCE(SUM(payment_amount), 0) as total_paid FROM payments WHERE invoice_id = ?");
$stmt->execute([$invoice_id]);
$payment_data = $stmt->fetch();
$total_paid_from_payments = (float)($payment_data['total_paid'] ?? 0);

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $discount = (float)($_POST['discount'] ?? 0);
        $financial_aid_amount = (float)($_POST['financial_aid_amount'] ?? 0);
        
        // Validate
        if ($discount < 0) {
            throw new Exception('Discount cannot be negative.');
        }
        
        if ($financial_aid_amount < 0) {
            throw new Exception('Financial aid amount cannot be negative.');
        }
        
        // Calculate new totals
        $subtotal = (float)$invoice['subtotal'];
        $total_amount = max(0, $subtotal - $discount - $financial_aid_amount);
        
        // Calculate new balance (considering existing payments)
        $current_paid = (float)($invoice['paid_amount'] ?? $total_paid_from_payments);
        $new_balance = max(0, $total_amount - $current_paid);
        
        // Auto-update status based on balance
        $status = $invoice['status'];
        if ($new_balance <= 0 && $current_paid >= $total_amount) {
            $status = 'Paid';
        } elseif ($current_paid > 0 && $current_paid < $total_amount) {
            $status = 'Partial';
        } elseif ($current_paid == 0) {
            $status = 'Pending';
        }
        
        // Update invoice
        $stmt = $pdo->prepare("UPDATE invoices SET 
                              discount = ?, 
                              financial_aid_amount = ?, 
                              total_amount = ?,
                              balance = ?,
                              status = ?,
                              updated_at = NOW()
                              WHERE invoice_id = ?");
        $stmt->execute([$discount, $financial_aid_amount, $total_amount, $new_balance, $status, $invoice_id]);
        
        header('Location: ' . BASE_URL . 'modules/billing/index.php?success=Invoice discounts updated successfully');
        exit;
        
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

include __DIR__ . '/../../includes/header.php';
?>

<div class="page-container">
    <div class="page-header">
        <h1><i class="fas fa-edit"></i> Edit Invoice - Discounts</h1>
        <a href="<?php echo BASE_URL; ?>modules/billing/index.php" class="btn btn-secondary">
            <i class="fas fa-arrow-left"></i> Back
        </a>
    </div>

    <?php if ($error): ?>
        <div class="alert alert-error"><?php echo sanitizeOutput($error); ?></div>
    <?php endif; ?>

    <div class="card" style="margin-bottom: 1.5rem;">
        <h2>Invoice Information</h2>
        <div class="form-row">
            <div class="form-group">
                <label>Invoice Number</label>
                <input type="text" class="form-control" value="<?php echo sanitizeOutput($invoice['invoice_number']); ?>" readonly>
            </div>
            <div class="form-group">
                <label>Student</label>
                <input type="text" class="form-control" 
                       value="<?php echo sanitizeOutput($invoice['student_number'] . ' - ' . $invoice['first_name'] . ' ' . $invoice['last_name']); ?>" readonly>
            </div>
        </div>
        <div class="form-row">
            <div class="form-group">
                <label>Semester</label>
                <input type="text" class="form-control" value="<?php echo sanitizeOutput($invoice['semester']); ?>" readonly>
            </div>
            <div class="form-group">
                <label>Academic Year</label>
                <input type="text" class="form-control" value="<?php echo sanitizeOutput($invoice['academic_year']); ?>" readonly>
            </div>
            <div class="form-group">
                <label>Due Date</label>
                <input type="text" class="form-control" value="<?php echo date('M d, Y', strtotime($invoice['due_date'])); ?>" readonly>
            </div>
        </div>
    </div>

    <div class="card" style="margin-bottom: 1.5rem;">
        <h2>Invoice Items</h2>
        <div style="overflow-x: auto;">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Type</th>
                        <th>Description</th>
                        <th>Unit Price</th>
                        <th>Total Price</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($invoice_items as $item): ?>
                    <tr>
                        <td><?php echo sanitizeOutput($item['item_type']); ?></td>
                        <td><?php echo sanitizeOutput($item['description']); ?></td>
                        <td>₱<?php echo number_format($item['unit_price'], 2); ?></td>
                        <td>₱<?php echo number_format($item['total_price'], 2); ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <div class="card">
        <form method="POST" action="" id="editForm">
            <h2>Financial Adjustments</h2>
            
            <div class="form-row">
                <div class="form-group">
                    <label for="discount">Discount</label>
                    <input type="number" id="discount" name="discount" class="form-control" 
                           step="0.01" min="0" 
                           value="<?php echo number_format($invoice['discount'], 2, '.', ''); ?>" 
                           onchange="calculateTotal()">
                    <small class="text-muted">Enter discount amount</small>
                </div>
                
                <div class="form-group">
                    <label for="financial_aid_amount">Financial Aid Amount</label>
                    <input type="number" id="financial_aid_amount" name="financial_aid_amount" class="form-control" 
                           step="0.01" min="0" 
                           value="<?php echo number_format($invoice['financial_aid_amount'], 2, '.', ''); ?>" 
                           onchange="calculateTotal()">
                    <small class="text-muted">Enter financial aid amount</small>
                </div>
            </div>
            
            <div style="margin-top: 1.5rem; padding-top: 1.5rem; border-top: 2px solid var(--border-color);">
                <div style="display: flex; justify-content: space-between; margin-bottom: 0.5rem;">
                    <strong>Subtotal:</strong>
                    <span id="subtotal">₱<?php echo number_format($invoice['subtotal'], 2); ?></span>
                </div>
                <div style="display: flex; justify-content: space-between; margin-bottom: 0.5rem;">
                    <strong>Discount:</strong>
                    <span id="discount_display">₱<?php echo number_format($invoice['discount'], 2); ?></span>
                </div>
                <div style="display: flex; justify-content: space-between; margin-bottom: 0.5rem;">
                    <strong>Financial Aid:</strong>
                    <span id="financial_aid_display">₱<?php echo number_format($invoice['financial_aid_amount'], 2); ?></span>
                </div>
                <div style="display: flex; justify-content: space-between; font-size: 1.2rem; font-weight: bold; margin-top: 1rem; padding-top: 1rem; border-top: 1px solid var(--border-color);">
                    <strong>Total Amount:</strong>
                    <span id="total_amount">₱<?php echo number_format($invoice['total_amount'], 2); ?></span>
                </div>
                <div style="display: flex; justify-content: space-between; margin-top: 0.5rem; color: #666;">
                    <strong>Current Paid:</strong>
                    <span>₱<?php echo number_format($invoice['paid_amount'] ?? $total_paid_from_payments, 2); ?></span>
                </div>
                <div style="display: flex; justify-content: space-between; font-weight: bold; margin-top: 0.5rem; padding-top: 0.5rem; border-top: 1px solid var(--border-color);">
                    <strong>New Balance:</strong>
                    <span id="balance_display">₱<?php echo number_format($invoice['balance'], 2); ?></span>
                </div>
            </div>

            <div class="form-actions" style="margin-top: 1.5rem;">
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-save"></i> Update Discounts
                </button>
                <a href="<?php echo BASE_URL; ?>modules/billing/index.php" class="btn btn-secondary">
                    Cancel
                </a>
            </div>
        </form>
    </div>
</div>

<script>
function calculateTotal() {
    const subtotal = <?php echo $invoice['subtotal']; ?>;
    const discount = parseFloat(document.getElementById('discount').value) || 0;
    const financialAid = parseFloat(document.getElementById('financial_aid_amount').value) || 0;
    const currentPaid = <?php echo $invoice['paid_amount'] ?? $total_paid_from_payments; ?>;
    
    const totalAmount = Math.max(0, subtotal - discount - financialAid);
    const newBalance = Math.max(0, totalAmount - currentPaid);
    
    document.getElementById('subtotal').textContent = '₱' + subtotal.toFixed(2);
    document.getElementById('discount_display').textContent = '₱' + discount.toFixed(2);
    document.getElementById('financial_aid_display').textContent = '₱' + financialAid.toFixed(2);
    document.getElementById('total_amount').textContent = '₱' + totalAmount.toFixed(2);
    document.getElementById('balance_display').textContent = '₱' + newBalance.toFixed(2);
}

// Initialize on page load
document.addEventListener('DOMContentLoaded', function() {
    calculateTotal();
});
</script>

<?php include __DIR__ . '/../../includes/footer.php'; ?>

