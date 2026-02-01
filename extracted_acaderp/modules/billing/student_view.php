<?php
/**
 * Student Tuition & Billing View
 * Allows students to view their own invoices and payments
 */
require_once __DIR__ . '/../../config/config.php';
requireAuth(ROLE_STUDENT);
$page_title = 'My Tuition & Billing';

$pdo = getDBConnection();
$student_id = null;

// Get student_id from user_id
$stmt = $pdo->prepare("SELECT student_id FROM students WHERE user_id = ?");
$stmt->execute([$_SESSION['user_id']]);
$student = $stmt->fetch();
if (!$student) {
    header('Location: ' . BASE_URL . 'dashboard/index.php?error=Student record not found');
    exit;
}
$student_id = $student['student_id'];

// Get all invoices for this student with payment info
$stmt = $pdo->prepare("SELECT i.*, 
                      (SELECT COALESCE(SUM(p.payment_amount), 0) FROM payments p WHERE p.invoice_id = i.invoice_id) as total_paid_from_payments
                      FROM invoices i
                      WHERE i.student_id = ?
                      ORDER BY i.created_at DESC");
$stmt->execute([$student_id]);
$invoices = $stmt->fetchAll();

// Update total_paid to use the higher value (from invoice or payments)
foreach ($invoices as &$inv) {
    $paid_from_invoice = (float)($inv['paid_amount'] ?? 0);
    $paid_from_payments = (float)($inv['total_paid_from_payments'] ?? 0);
    $inv['total_paid'] = max($paid_from_invoice, $paid_from_payments);
}

// Calculate summary
$total_due = 0;
$total_paid = 0;
$total_balance = 0;

foreach ($invoices as $inv) {
    $amount = (float)($inv['total_amount'] ?? 0);
    $paid = (float)($inv['total_paid'] ?? 0);
    $total_due += $amount;
    $total_paid += $paid;
    $total_balance += ($amount - $paid);
}

include __DIR__ . '/../../includes/header.php';
?>

<div class="page-container">
    <div class="page-header">
        <h1><i class="fas fa-dollar-sign"></i> My Tuition & Billing</h1>
    </div>

    <div class="card" style="margin-bottom: 1.5rem;">
        <h2 style="margin-bottom: 1rem;">Summary</h2>
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon red">
                    <i class="fas fa-file-invoice-dollar"></i>
                </div>
                <div class="stat-info">
                    <h3>₱<?php echo number_format($total_due, 2); ?></h3>
                    <p>Total Due</p>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon green">
                    <i class="fas fa-check-circle"></i>
                </div>
                <div class="stat-info">
                    <h3>₱<?php echo number_format($total_paid, 2); ?></h3>
                    <p>Total Paid</p>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon <?php echo $total_balance > 0 ? 'orange' : 'green'; ?>">
                    <i class="fas fa-balance-scale"></i>
                </div>
                <div class="stat-info">
                    <h3>₱<?php echo number_format($total_balance, 2); ?></h3>
                    <p>Outstanding Balance</p>
                </div>
            </div>
        </div>
    </div>

    <div class="card">
        <h2 style="margin-bottom: 1rem;">Invoices</h2>
        <?php if (empty($invoices)): ?>
            <div class="alert alert-info">No invoices found.</div>
        <?php else: ?>
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Invoice #</th>
                        <th>Semester</th>
                        <th>Academic Year</th>
                        <th>Invoice Date</th>
                        <th>Due Date</th>
                        <th>Amount</th>
                        <th>Paid</th>
                        <th>Balance</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($invoices as $inv): ?>
                        <?php 
                        $amount = (float)($inv['total_amount'] ?? 0);
                        $paid = (float)($inv['total_paid'] ?? 0);
                        $balance = $amount - $paid;
                        
                        // Use invoice status from database (updated by admin)
                        $status = $inv['status'] ?? 'Pending';
                        
                        // Determine status badge color
                        $status_badge = 'warning';
                        if ($status === 'Paid') {
                            $status_badge = 'success';
                        } elseif ($status === 'Partial') {
                            $status_badge = 'info';
                        } elseif ($status === 'Overdue') {
                            $status_badge = 'danger';
                        } elseif ($status === 'Cancelled') {
                            $status_badge = 'secondary';
                        }
                        
                        // Get invoice items for breakdown
                        $stmt = $pdo->prepare("SELECT * FROM invoice_items WHERE invoice_id = ? ORDER BY item_type, description");
                        $stmt->execute([$inv['invoice_id']]);
                        $invoice_items = $stmt->fetchAll();
                        
                        // Get standard fees info if available
                        $standard_fees_info = [];
                        try {
                            $stmt = $pdo->query("SELECT fee_category, amount FROM standard_fees WHERE is_active = 1");
                            $standard_fees_info = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
                        } catch (PDOException $e) {
                            // Table might not exist
                        }
                        ?>
                        <tr>
                            <td><strong><?php echo sanitizeOutput($inv['invoice_number'] ?? 'N/A'); ?></strong></td>
                            <td><?php echo sanitizeOutput($inv['semester'] ?? 'N/A'); ?></td>
                            <td><?php echo sanitizeOutput($inv['academic_year'] ?? 'N/A'); ?></td>
                            <td><?php echo date('M d, Y', strtotime($inv['created_at'])); ?></td>
                            <td><?php echo date('M d, Y', strtotime($inv['due_date'])); ?></td>
                            <td>₱<?php echo number_format($amount, 2); ?></td>
                            <td>₱<?php echo number_format($paid, 2); ?></td>
                            <td>₱<?php echo number_format($balance, 2); ?></td>
                            <td>
                                <span class="badge badge-<?php echo $status_badge; ?>">
                                    <?php echo sanitizeOutput($status); ?>
                                </span>
                            </td>
                            <td class="actions">
                                <a href="<?php echo BASE_URL; ?>modules/billing/statement.php?id=<?php echo $inv['invoice_id']; ?>" 
                                   class="btn btn-sm btn-info" target="_blank">
                                    <i class="fas fa-file-invoice"></i> View Statement
                                </a>
                                <button type="button" class="btn btn-sm btn-secondary" onclick="openBreakdownModal(<?php echo htmlspecialchars(json_encode([
                                    'invoice_id' => $inv['invoice_id'],
                                    'invoice_number' => $inv['invoice_number'] ?? 'N/A',
                                    'semester' => $inv['semester'] ?? 'N/A',
                                    'academic_year' => $inv['academic_year'] ?? 'N/A',
                                    'invoice_date' => date('M d, Y', strtotime($inv['created_at'])),
                                    'due_date' => date('M d, Y', strtotime($inv['due_date'])),
                                    'status' => $status,
                                    'items' => array_map(function($item) {
                                        $description = $item['description'];
                                        if (stripos($item['item_type'], 'Tuition') !== false || preg_match('/^Tuition Fee/i', $description)) {
                                            $suffix = '';
                                            if (preg_match('/\s*\[.*?\]\s*$/', $description, $matches)) {
                                                $suffix = $matches[0];
                                                $description = preg_replace('/\s*\[.*?\]\s*$/', '', $description);
                                            }
                                            $description = preg_replace('/^(Tuition Fee).*$/i', '$1', $description);
                                            $description = trim($description) . $suffix;
                                        }
                                        return [
                                            'item_type' => $item['item_type'],
                                            'description' => $description,
                                            'total_price' => $item['total_price']
                                        ];
                                    }, $invoice_items),
                                    'subtotal' => $inv['subtotal'] ?? array_sum(array_column($invoice_items, 'total_price')),
                                    'discount' => $inv['discount'] ?? 0,
                                    'financial_aid_amount' => $inv['financial_aid_amount'] ?? 0,
                                    'total_amount' => $amount,
                                    'total_paid' => $paid,
                                    'balance' => $balance
                                ]), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>)">
                                    <i class="fas fa-list"></i> Breakdown
                                </button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
    
<style>
/* Breakdown Modal Styles */
.breakdown-modal {
    display: none;
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: rgba(0, 0, 0, 0.3);
    backdrop-filter: blur(4px);
    z-index: 1000;
    padding: 1rem;
    overflow-y: auto;
}

.breakdown-modal.active {
    display: flex;
    align-items: center;
    justify-content: center;
    animation: fadeIn 0.3s ease;
}

@keyframes fadeIn {
    from { opacity: 0; }
    to { opacity: 1; }
}

.breakdown-modal-content {
    background: white;
    border-radius: 24px;
    padding: 0;
    max-width: 900px;
    width: 100%;
    max-height: 90vh;
    position: relative;
    animation: slideUp 0.4s ease;
    box-shadow: 0 25px 80px rgba(0, 0, 0, 0.4);
    overflow: hidden;
    display: flex;
    flex-direction: column;
}

.breakdown-modal-content::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    height: 6px;
    background: linear-gradient(90deg, #1e40af 0%, #2563eb 50%, #ffd700 100%);
    z-index: 1;
}

@keyframes slideUp {
    from {
        opacity: 0;
        transform: translateY(30px) scale(0.95);
    }
    to {
        opacity: 1;
        transform: translateY(0) scale(1);
    }
}

.breakdown-modal-close {
    position: absolute;
    top: 1rem;
    right: 1rem;
    background: rgba(30, 64, 175, 0.08);
    border: 2px solid rgba(30, 64, 175, 0.1);
    width: 40px;
    height: 40px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    transition: all 0.3s;
    color: #64748b;
    font-size: 1.125rem;
    z-index: 10;
}

.breakdown-modal-close:hover {
    background: linear-gradient(180deg, #1e40af 0%, #2563eb 100%);
    border-color: transparent;
    color: white;
    transform: rotate(90deg);
}

.breakdown-modal-header {
    background: linear-gradient(180deg, #1e40af 0%, #2563eb 100%);
    padding: 2rem;
    color: white;
    position: relative;
    overflow: hidden;
}

.breakdown-modal-header::before {
    content: '';
    position: absolute;
    top: -50%;
    left: -50%;
    width: 200%;
    height: 200%;
    background: 
        radial-gradient(circle at 30% 50%, rgba(255, 215, 0, 0.15) 0%, transparent 50%),
        radial-gradient(circle at 70% 80%, rgba(255, 255, 255, 0.08) 0%, transparent 40%);
}

.breakdown-modal-header h2 {
    font-size: 1.75rem;
    font-weight: 800;
    margin: 0 0 0.5rem 0;
    text-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
    position: relative;
}

.breakdown-modal-header .invoice-info {
    display: flex;
    flex-wrap: wrap;
    gap: 1.5rem;
    margin-top: 1rem;
    position: relative;
}

.breakdown-modal-header .info-item {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    font-size: 0.95rem;
    color: rgba(255, 255, 255, 0.95);
}

.breakdown-modal-header .info-item i {
    color: #ffd700;
    font-size: 1rem;
}

.breakdown-modal-body {
    padding: 2rem;
    overflow-y: auto;
    flex: 1;
}

.breakdown-table {
    width: 100%;
    border-collapse: collapse;
    margin-top: 1rem;
}

.breakdown-table thead {
    background: linear-gradient(180deg, #1e40af 0%, #2563eb 100%);
    color: white;
}

.breakdown-table th {
    padding: 1rem;
    text-align: left;
    font-weight: 700;
    font-size: 0.9rem;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.breakdown-table td {
    padding: 1rem;
    border-bottom: 1px solid #e2e8f0;
}

.breakdown-table tbody tr:hover {
    background: #f8fafc;
}

.breakdown-table tfoot {
    background: linear-gradient(135deg, #f1f5f9 0%, #e2e8f0 100%);
    font-weight: 800;
}

.breakdown-table tfoot td {
    border-top: 2px solid #1e40af;
    color: #1e40af;
}

.breakdown-table tfoot .total-row {
    background: linear-gradient(135deg, #dcfce7 0%, #bbf7d0 100%);
    color: #166534;
}

.breakdown-table tfoot .balance-row {
    background: linear-gradient(135deg, #fef2f2 0%, #fee2e2 100%);
    color: #991b1b;
}

.breakdown-table tfoot .balance-paid {
    background: linear-gradient(135deg, #dcfce7 0%, #bbf7d0 100%);
    color: #166534;
}

@media (max-width: 768px) {
    .breakdown-modal-content {
        max-width: 100%;
        max-height: 95vh;
        border-radius: 20px;
    }
    
    .breakdown-modal-header {
        padding: 1.5rem;
    }
    
    .breakdown-modal-header h2 {
        font-size: 1.5rem;
    }
    
    .breakdown-modal-body {
        padding: 1.5rem;
    }
    
    .breakdown-table {
        font-size: 0.875rem;
    }
    
    .breakdown-table th,
    .breakdown-table td {
        padding: 0.75rem 0.5rem;
    }
}
</style>

<!-- Breakdown Modal -->
<div id="breakdownModal" class="breakdown-modal">
    <div class="breakdown-modal-content">
        <button class="breakdown-modal-close" onclick="closeBreakdownModal()">
            <i class="fas fa-times"></i>
        </button>
        
        <div class="breakdown-modal-header">
            <h2 id="modalInvoiceNumber">Invoice Breakdown</h2>
            <div class="invoice-info" id="modalInvoiceInfo"></div>
        </div>
        
        <div class="breakdown-modal-body">
            <h3 style="margin-bottom: 1rem; color: #1e293b; font-size: 1.25rem;">Fee Breakdown</h3>
            <div id="modalBreakdownTable"></div>
        </div>
    </div>
</div>

<script>
function openBreakdownModal(data) {
    const modal = document.getElementById('breakdownModal');
    const invoiceNumber = document.getElementById('modalInvoiceNumber');
    const invoiceInfo = document.getElementById('modalInvoiceInfo');
    const breakdownTable = document.getElementById('modalBreakdownTable');
    
    // Set invoice number
    invoiceNumber.textContent = 'Invoice #' + data.invoice_number;
    
    // Set invoice info
    invoiceInfo.innerHTML = `
        <div class="info-item">
            <i class="fas fa-calendar-alt"></i>
            <span>${data.semester} ${data.academic_year}</span>
        </div>
        <div class="info-item">
            <i class="fas fa-calendar"></i>
            <span>Invoice Date: ${data.invoice_date}</span>
        </div>
        <div class="info-item">
            <i class="fas fa-clock"></i>
            <span>Due Date: ${data.due_date}</span>
        </div>
        <div class="info-item">
            <i class="fas fa-info-circle"></i>
            <span>Status: ${data.status}</span>
        </div>
    `;
    
    // Build breakdown table
    let tableHTML = '<table class="breakdown-table">';
    tableHTML += '<thead><tr>';
    tableHTML += '<th>Item Type</th>';
    tableHTML += '<th>Description</th>';
    tableHTML += '<th style="text-align: right;">Total</th>';
    tableHTML += '</tr></thead><tbody>';
    
    data.items.forEach(item => {
        tableHTML += '<tr>';
        tableHTML += `<td>${escapeHtml(item.item_type || 'N/A')}</td>`;
        tableHTML += `<td>${escapeHtml(item.description || 'N/A')}</td>`;
        tableHTML += `<td style="text-align: right;">₱${parseFloat(item.total_price || 0).toFixed(2)}</td>`;
        tableHTML += '</tr>';
    });
    
    tableHTML += '</tbody><tfoot>';
    
    // Subtotal
    tableHTML += '<tr>';
    tableHTML += '<td colspan="2"><strong>Subtotal</strong></td>';
    tableHTML += `<td style="text-align: right;"><strong>₱${parseFloat(data.subtotal || 0).toFixed(2)}</strong></td>`;
    tableHTML += '</tr>';
    
    // Discount
    if (data.discount && parseFloat(data.discount) > 0) {
        tableHTML += '<tr>';
        tableHTML += '<td colspan="2">Discount</td>';
        tableHTML += `<td style="text-align: right;">-₱${parseFloat(data.discount).toFixed(2)}</td>`;
        tableHTML += '</tr>';
    }
    
    // Financial Aid
    if (data.financial_aid_amount && parseFloat(data.financial_aid_amount) > 0) {
        tableHTML += '<tr>';
        tableHTML += '<td colspan="2">Financial Aid</td>';
        tableHTML += `<td style="text-align: right;">-₱${parseFloat(data.financial_aid_amount).toFixed(2)}</td>`;
        tableHTML += '</tr>';
    }
    
    // Total Amount
    tableHTML += '<tr class="total-row">';
    tableHTML += '<td colspan="2"><strong>Total Amount</strong></td>';
    tableHTML += `<td style="text-align: right;"><strong>₱${parseFloat(data.total_amount || 0).toFixed(2)}</strong></td>`;
    tableHTML += '</tr>';
    
    // Total Paid
    tableHTML += '<tr>';
    tableHTML += '<td colspan="2">Total Paid</td>';
    tableHTML += `<td style="text-align: right;">₱${parseFloat(data.total_paid || 0).toFixed(2)}</td>`;
    tableHTML += '</tr>';
    
    // Outstanding Balance
    const balanceClass = parseFloat(data.balance) > 0 ? 'balance-row' : 'balance-paid';
    tableHTML += `<tr class="${balanceClass}">`;
    tableHTML += '<td colspan="2"><strong>Outstanding Balance</strong></td>';
    tableHTML += `<td style="text-align: right;"><strong>₱${parseFloat(data.balance || 0).toFixed(2)}</strong></td>`;
    tableHTML += '</tr>';
    
    tableHTML += '</tfoot></table>';
    
    breakdownTable.innerHTML = tableHTML;
    
    // Show modal
    modal.classList.add('active');
    document.body.style.overflow = 'hidden';
}

function closeBreakdownModal() {
    const modal = document.getElementById('breakdownModal');
    modal.classList.remove('active');
    document.body.style.overflow = '';
}

function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

// Close modal when clicking outside
document.getElementById('breakdownModal')?.addEventListener('click', function(e) {
    if (e.target === this) {
        closeBreakdownModal();
    }
});

// Close modal on Escape key
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        closeBreakdownModal();
    }
});
</script>
</div>

<?php include __DIR__ . '/../../includes/footer.php'; ?>

