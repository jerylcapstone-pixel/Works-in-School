<?php
/**
 * Invoice Edit - Update Status and Payment
 * Allows admin to update invoice status and paid amount
 */
require_once __DIR__ . '/../../config/config.php';
requireAuth([ROLE_ADMIN]);
$page_title = 'Update Invoice Status';
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

// Get total payments
$stmt = $pdo->prepare("SELECT COALESCE(SUM(payment_amount), 0) as total_paid FROM payments WHERE invoice_id = ?");
$stmt->execute([$invoice_id]);
$payment_data = $stmt->fetch();
$total_paid_from_payments = (float)($payment_data['total_paid'] ?? 0);

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $status = sanitizeInput($_POST['status'] ?? 'Pending');
        $paid_amount = (float)($_POST['paid_amount'] ?? 0);
        $payment_method = sanitizeInput($_POST['payment_method'] ?? '');
        $payment_date = sanitizeInput($_POST['payment_date'] ?? date('Y-m-d'));
        $reference_number = sanitizeInput($_POST['reference_number'] ?? '');
        $notes = sanitizeInput($_POST['notes'] ?? '');
        
        // Validate status
        $valid_statuses = ['Pending', 'Partial', 'Paid', 'Overdue', 'Cancelled'];
        if (!in_array($status, $valid_statuses)) {
            throw new Exception('Invalid status selected.');
        }
        
        // Validate paid amount
        if ($paid_amount < 0) {
            throw new Exception('Paid amount cannot be negative.');
        }
        
        if ($paid_amount > $invoice['total_amount']) {
            throw new Exception('Paid amount cannot exceed total amount.');
        }
        
        $pdo->beginTransaction();
        
        // Calculate new balance
        $new_balance = $invoice['total_amount'] - $paid_amount;
        if ($new_balance < 0) {
            $new_balance = 0;
        }
        
        // Auto-update status based on paid amount if status is being set to Paid/Partial
        if ($status === 'Paid' && $paid_amount < $invoice['total_amount']) {
            $status = 'Partial';
        } elseif ($status === 'Paid' && $paid_amount >= $invoice['total_amount']) {
            $paid_amount = $invoice['total_amount']; // Set to full amount
            $new_balance = 0;
        } elseif ($status === 'Partial' && $paid_amount >= $invoice['total_amount']) {
            $status = 'Paid';
            $paid_amount = $invoice['total_amount'];
            $new_balance = 0;
        } elseif ($status === 'Partial' && $paid_amount == 0) {
            $status = 'Pending';
        }
        
        // Update invoice
        $stmt = $pdo->prepare("UPDATE invoices SET 
                              status = ?, 
                              paid_amount = ?, 
                              balance = ?,
                              updated_at = NOW()
                              WHERE invoice_id = ?");
        $stmt->execute([$status, $paid_amount, $new_balance, $invoice_id]);
        
        // If paid_amount increased, create a payment record
        $amount_difference = $paid_amount - $total_paid_from_payments;
        if ($amount_difference > 0 && !empty($payment_method)) {
            $stmt = $pdo->prepare("INSERT INTO payments 
                                  (invoice_id, payment_method, payment_amount, payment_date, reference_number, notes, processed_by)
                                  VALUES (?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([
                $invoice_id,
                $payment_method,
                $amount_difference,
                $payment_date,
                $reference_number,
                $notes,
                $_SESSION['user_id']
            ]);
        }
        
        $pdo->commit();
        header('Location: ' . BASE_URL . 'modules/billing/index.php?success=Invoice status updated successfully');
        exit;
        
    } catch (Exception $e) {
        $pdo->rollBack();
        $error = $e->getMessage();
    }
}

include __DIR__ . '/../../includes/header.php';
?>

<div class="page-container">
    <div class="page-header">
        <h1><i class="fas fa-edit"></i> Update Invoice Status</h1>
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
        </div>
        <div class="form-row">
            <div class="form-group">
                <label>Total Amount</label>
                <input type="text" class="form-control" value="₱<?php echo number_format($invoice['total_amount'], 2); ?>" readonly>
            </div>
            <div class="form-group">
                <label>Current Paid Amount</label>
                <input type="text" class="form-control" value="₱<?php echo number_format($invoice['paid_amount'], 2); ?>" readonly>
            </div>
            <div class="form-group">
                <label>Current Balance</label>
                <input type="text" class="form-control" value="₱<?php echo number_format($invoice['balance'], 2); ?>" readonly>
            </div>
        </div>
    </div>

    <div class="card">
        <form method="POST" action="" id="updateForm">
            <h2>Update Status & Payment</h2>
            
            <div class="form-row">
                <div class="form-group">
                    <label for="status">Status <span class="text-danger">*</span></label>
                    <select id="status" name="status" class="form-control" required onchange="updateStatusLogic()">
                        <option value="Pending" <?php echo $invoice['status'] === 'Pending' ? 'selected' : ''; ?>>Pending</option>
                        <option value="Partial" <?php echo $invoice['status'] === 'Partial' ? 'selected' : ''; ?>>Partial</option>
                        <option value="Paid" <?php echo $invoice['status'] === 'Paid' ? 'selected' : ''; ?>>Paid</option>
                        <option value="Overdue" <?php echo $invoice['status'] === 'Overdue' ? 'selected' : ''; ?>>Overdue</option>
                        <option value="Cancelled" <?php echo $invoice['status'] === 'Cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="paid_amount">Paid Amount <span class="text-danger">*</span></label>
                    <input type="number" id="paid_amount" name="paid_amount" class="form-control" 
                           step="0.01" min="0" max="<?php echo $invoice['total_amount']; ?>"
                           value="<?php echo $invoice['paid_amount']; ?>" required onchange="calculateBalance()">
                    <small class="text-muted">Maximum: ₱<?php echo number_format($invoice['total_amount'], 2); ?></small>
                </div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label for="payment_method">Payment Method</label>
                    <select id="payment_method" name="payment_method" class="form-control">
                        <option value="">-- Select Method --</option>
                        <option value="Cash">Cash</option>
                        <option value="Check">Check</option>
                        <option value="Credit Card">Credit Card</option>
                        <option value="Bank Transfer">Bank Transfer</option>
                        <option value="Online">Online</option>
                        <option value="Other">Other</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="payment_date">Payment Date</label>
                    <input type="date" id="payment_date" name="payment_date" class="form-control" 
                           value="<?php echo date('Y-m-d'); ?>" max="<?php echo date('Y-m-d'); ?>">
                </div>
                
                <div class="form-group">
                    <label for="reference_number">Reference Number</label>
                    <input type="text" id="reference_number" name="reference_number" class="form-control" 
                           placeholder="e.g., Check #, Transaction ID">
                </div>
            </div>

            <div class="form-group">
                <label for="notes">Notes</label>
                <textarea id="notes" name="notes" class="form-control" rows="3" placeholder="Optional notes about this payment"></textarea>
            </div>

            <div style="margin-top: 1.5rem; padding: 1rem; background-color: #f5f5f5; border-radius: 4px;">
                <div style="display: flex; justify-content: space-between; margin-bottom: 0.5rem;">
                    <strong>Total Amount:</strong>
                    <span>₱<?php echo number_format($invoice['total_amount'], 2); ?></span>
                </div>
                <div style="display: flex; justify-content: space-between; margin-bottom: 0.5rem;">
                    <strong>Paid Amount:</strong>
                    <span id="paid_display">₱<?php echo number_format($invoice['paid_amount'], 2); ?></span>
                </div>
                <div style="display: flex; justify-content: space-between; font-weight: bold; font-size: 1.1rem; margin-top: 1rem; padding-top: 1rem; border-top: 1px solid #ddd;">
                    <strong>New Balance:</strong>
                    <span id="balance_display">₱<?php echo number_format($invoice['balance'], 2); ?></span>
                </div>
            </div>

            <div class="form-actions" style="margin-top: 1.5rem;">
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-save"></i> Update Invoice Status
                </button>
                <a href="<?php echo BASE_URL; ?>modules/billing/index.php" class="btn btn-secondary">
                    Cancel
                </a>
            </div>
        </form>
    </div>
</div>

<script>
function calculateBalance() {
    const totalAmount = <?php echo $invoice['total_amount']; ?>;
    const paidAmount = parseFloat(document.getElementById('paid_amount').value) || 0;
    const balance = Math.max(0, totalAmount - paidAmount);
    
    document.getElementById('paid_display').textContent = '₱' + paidAmount.toFixed(2);
    document.getElementById('balance_display').textContent = '₱' + balance.toFixed(2);
    
    // Auto-update status based on payment
    const statusSelect = document.getElementById('status');
    if (paidAmount >= totalAmount) {
        if (statusSelect.value !== 'Cancelled') {
            statusSelect.value = 'Paid';
        }
    } else if (paidAmount > 0) {
        if (statusSelect.value !== 'Cancelled' && statusSelect.value !== 'Overdue') {
            statusSelect.value = 'Partial';
        }
    } else {
        if (statusSelect.value !== 'Cancelled' && statusSelect.value !== 'Overdue') {
            statusSelect.value = 'Pending';
        }
    }
}

function updateStatusLogic() {
    const status = document.getElementById('status').value;
    const paidAmountInput = document.getElementById('paid_amount');
    const totalAmount = <?php echo $invoice['total_amount']; ?>;
    
    if (status === 'Paid') {
        paidAmountInput.value = totalAmount.toFixed(2);
        calculateBalance();
    } else if (status === 'Pending') {
        paidAmountInput.value = '0.00';
        calculateBalance();
    }
}

// Initialize on page load
document.addEventListener('DOMContentLoaded', function() {
    calculateBalance();
});
</script>

<?php include __DIR__ . '/../../includes/footer.php'; ?>

