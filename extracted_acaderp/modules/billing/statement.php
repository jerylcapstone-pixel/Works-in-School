<?php
require_once __DIR__ . '/../../config/config.php';
requireAuth([ROLE_ADMIN, ROLE_STUDENT]);
$page_title = 'Billing Statement';
$pdo = getDBConnection();

$invoice_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$student_id = isset($_GET['student_id']) ? (int)$_GET['student_id'] : 0;

// If student, get their own invoice
if (hasRole(ROLE_STUDENT) && !hasRole(ROLE_ADMIN)) {
    $stmt = $pdo->prepare("SELECT student_id FROM students WHERE user_id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $student = $stmt->fetch();
    if ($student) {
        $student_id = $student['student_id'];
    }
}

if (!$invoice_id && !$student_id) {
    header('Location: ' . BASE_URL . 'modules/billing/index.php?error=Invalid request');
    exit;
}

// Check if standard_fees and student_additional_fees tables exist
$standard_fees_table_exists = false;
$student_fees_table_exists = false;
try {
    $stmt = $pdo->query("SHOW TABLES LIKE 'standard_fees'");
    $standard_fees_table_exists = $stmt->rowCount() > 0;
    $stmt = $pdo->query("SHOW TABLES LIKE 'student_additional_fees'");
    $student_fees_table_exists = $stmt->rowCount() > 0;
} catch (PDOException $e) {
    // Tables don't exist
}

// Get invoice if invoice_id is provided
$invoice = null;
if ($invoice_id) {
    $stmt = $pdo->prepare("SELECT i.*, s.student_number, s.first_name, s.middle_name, s.last_name, 
                          s.address, s.city, s.state, s.zip_code,
                          p.program_name, d.department_name
                          FROM invoices i
                          INNER JOIN students s ON i.student_id = s.student_id
                          LEFT JOIN programs p ON s.program_id = p.program_id
                          LEFT JOIN departments d ON p.department_id = d.department_id
                          WHERE i.invoice_id = ?");
    $stmt->execute([$invoice_id]);
    $invoice = $stmt->fetch();
    
    if (!$invoice) {
        header('Location: ' . BASE_URL . 'modules/billing/index.php?error=Invoice not found');
        exit;
    }
    
    // Check access for students
    if (hasRole(ROLE_STUDENT) && !hasRole(ROLE_ADMIN)) {
        if ($invoice['student_id'] != $student_id) {
            header('Location: ' . BASE_URL . 'modules/billing/student_view.php?error=Access denied');
            exit;
        }
    }
    
    $student_id = $invoice['student_id'];
}

// Get student info if not from invoice
if (!$invoice && $student_id) {
    $stmt = $pdo->prepare("SELECT s.*, p.program_name, d.department_name
                          FROM students s
                          LEFT JOIN programs p ON s.program_id = p.program_id
                          LEFT JOIN departments d ON p.department_id = d.department_id
                          WHERE s.student_id = ?");
    $stmt->execute([$student_id]);
    $student_info = $stmt->fetch();
    
    // Check access for students
    if (hasRole(ROLE_STUDENT) && !hasRole(ROLE_ADMIN)) {
        if ($student_info['user_id'] != $_SESSION['user_id']) {
            header('Location: ' . BASE_URL . 'modules/billing/student_view.php?error=Access denied');
            exit;
        }
    }
}

// Get standard fees (if table exists)
$standard_fees = [];
if ($standard_fees_table_exists) {
    $stmt = $pdo->query("SELECT * FROM standard_fees WHERE is_active = 1 ORDER BY fee_category");
    $all_standard_fees = $stmt->fetchAll();
    
    // Check if student has laboratory courses (only if we have student_id and semester info)
    $has_laboratory_courses = false;
    if ($student_id && $invoice) {
        $semester = $invoice['semester'];
        $academic_year = $invoice['academic_year'];
        
        // Check if student has any enrolled laboratory courses for this semester
        $lab_check_stmt = $pdo->prepare("SELECT COUNT(*) 
                                        FROM enrollments e
                                        INNER JOIN class_sections cs ON e.section_id = cs.section_id
                                        INNER JOIN courses c ON cs.course_id = c.course_id
                                        WHERE e.student_id = ? 
                                        AND e.status = 'Enrolled'
                                        AND cs.semester = ?
                                        AND cs.academic_year = ?
                                        AND (c.course_type = 'Lab' OR c.course_type = 'laboratory' OR LOWER(c.course_type) LIKE '%lab%')");
        $lab_check_stmt->execute([$student_id, $semester, $academic_year]);
        $has_laboratory_courses = $lab_check_stmt->fetchColumn() > 0;
    } elseif ($student_id && !$invoice) {
        // If no invoice but we have student_id, check current semester enrollments
        $lab_check_stmt = $pdo->prepare("SELECT COUNT(*) 
                                        FROM enrollments e
                                        INNER JOIN class_sections cs ON e.section_id = cs.section_id
                                        INNER JOIN courses c ON cs.course_id = c.course_id
                                        WHERE e.student_id = ? 
                                        AND e.status = 'Enrolled'
                                        AND (c.course_type = 'Lab' OR c.course_type = 'laboratory' OR LOWER(c.course_type) LIKE '%lab%')");
        $lab_check_stmt->execute([$student_id]);
        $has_laboratory_courses = $lab_check_stmt->fetchColumn() > 0;
    }
    
    // Filter standard fees - exclude Laboratory Fee if student has no laboratory courses
    foreach ($all_standard_fees as $fee) {
        // Skip Laboratory Fee if student doesn't have laboratory courses
        if (strtolower($fee['fee_category']) === 'laboratory fee' && !$has_laboratory_courses) {
            continue;
        }
        $standard_fees[] = $fee;
    }
}

// Get student additional fees (if table exists)
$student_additional_fees = [];
if ($student_fees_table_exists && $student_id) {
    $semester = $invoice ? $invoice['semester'] : null;
    $academic_year = $invoice ? $invoice['academic_year'] : null;
    
    $query = "SELECT * FROM student_additional_fees WHERE student_id = ? AND status = 'Active'";
    $params = [$student_id];
    
    if ($semester && $academic_year) {
        $query .= " AND (semester = ? OR semester IS NULL OR semester = '') AND (academic_year = ? OR academic_year IS NULL OR academic_year = '')";
        $params[] = $semester;
        $params[] = $academic_year;
    }
    
    $query .= " ORDER BY created_at DESC";
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $student_additional_fees = $stmt->fetchAll();
}

// Get invoice items if invoice exists
$invoice_items = [];
if ($invoice) {
    $stmt = $pdo->prepare("SELECT * FROM invoice_items WHERE invoice_id = ? ORDER BY item_id");
    $stmt->execute([$invoice_id]);
    $invoice_items = $stmt->fetchAll();
}

// Get payments
$payments = [];
$total_payments = 0;
if ($invoice) {
    $stmt = $pdo->prepare("SELECT p.*, u.username as processed_by_name
                          FROM payments p
                          LEFT JOIN users u ON p.processed_by = u.user_id
                          WHERE p.invoice_id = ? ORDER BY p.payment_date DESC");
    $stmt->execute([$invoice_id]);
    $payments = $stmt->fetchAll();
    $total_payments = array_sum(array_column($payments, 'payment_amount'));
}

// Separate tuition fees from other invoice items
$tuition_items = [];
$other_invoice_items = [];
foreach ($invoice_items as $item) {
    if (stripos($item['item_type'], 'Tuition') !== false || stripos($item['description'], 'Tuition') !== false) {
        $tuition_items[] = $item;
    } else {
        $other_invoice_items[] = $item;
    }
}

$total_tuition = array_sum(array_column($tuition_items, 'total_price'));
$total_other_items = array_sum(array_column($other_invoice_items, 'total_price'));

// Calculate totals
$total_standard_fees = array_sum(array_column($standard_fees, 'amount'));
$total_additional_fees = array_sum(array_column($student_additional_fees, 'amount'));
$old_account = $invoice ? ($invoice['old_account'] ?? 0) : 0;

// If invoice items exist, use them instead of standard fees (since invoice items are the actual charges)
if (!empty($invoice_items)) {
    // Use invoice items total (which includes tuition) as the base
    $total_account = $total_tuition + $total_other_items + $old_account + $total_additional_fees;
} else {
    // Fallback to standard fees if no invoice items
    $total_account = $total_standard_fees + $old_account + $total_additional_fees;
}

$discount = $invoice ? ($invoice['discount'] ?? 0) : 0;
$final_balance = $total_account - $discount - $total_payments;

// Get display data
$display_student = $invoice ? $invoice : $student_info;

include __DIR__ . '/../../includes/header.php';
?>

<style>
@page {
    size: letter;
    margin: 0.5in;
}

.billing-statement {
    max-width: 8.5in;
    margin: 0 auto;
    background: white;
    padding: 0.75in;
    box-shadow: 0 4px 20px rgba(0,0,0,0.15);
    font-family: 'Times New Roman', serif;
    color: #1a1a1a;
    line-height: 1.6;
}

.billing-header {
    text-align: center;
    border-bottom: 4px double #1a1a1a;
    padding-bottom: 1.5rem;
    margin-bottom: 2rem;
    position: relative;
}

.billing-header::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    height: 3px;
    background: linear-gradient(90deg, #2563eb 0%, #1e40af 50%, #2563eb 100%);
}

.billing-header h1 {
    margin: 0.5rem 0 0.25rem 0;
    font-size: 2.2rem;
    font-weight: 700;
    color: #1a1a1a;
    letter-spacing: 2px;
    text-transform: uppercase;
}

.billing-header .school-subtitle {
    margin: 0.25rem 0;
    font-size: 0.95rem;
    color: #4a5568;
    font-style: italic;
    letter-spacing: 1px;
}

.billing-header h2 {
    margin: 1.25rem 0 0.5rem 0;
    font-size: 1.5rem;
    font-weight: 600;
    color: #1a1a1a;
    letter-spacing: 1px;
    text-transform: uppercase;
}

.billing-header .statement-subtitle {
    margin: 0;
    font-size: 0.85rem;
    color: #666;
    font-weight: normal;
}

.billing-info {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 3rem;
    margin-bottom: 2.5rem;
    padding: 1.25rem;
    background: #f8f9fa;
    border: 1px solid #dee2e6;
    border-radius: 4px;
}

.billing-info p {
    margin: 0.4rem 0;
    font-size: 0.95rem;
    line-height: 1.8;
}

.billing-info strong {
    color: #1a1a1a;
    font-weight: 600;
    min-width: 140px;
    display: inline-block;
}

.billing-section {
    margin-bottom: 2rem;
    page-break-inside: avoid;
}

.billing-section h3 {
    background: linear-gradient(135deg, #2563eb 0%, #1e40af 100%);
    color: white;
    padding: 0.75rem 1.25rem;
    margin: 0 0 1rem 0;
    font-size: 1.1rem;
    font-weight: 600;
    letter-spacing: 0.5px;
    text-transform: uppercase;
    border-radius: 4px 4px 0 0;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
}

.billing-table {
    width: 100%;
    border-collapse: collapse;
    margin-bottom: 1.5rem;
    border: 1px solid #dee2e6;
    background: white;
}

.billing-table thead {
    background: #f8f9fa;
}

.billing-table th {
    padding: 0.875rem 1rem;
    text-align: left;
    border-bottom: 2px solid #dee2e6;
    font-weight: 700;
    font-size: 0.9rem;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    color: #1a1a1a;
}

.billing-table td {
    padding: 0.75rem 1rem;
    text-align: left;
    border-bottom: 1px solid #e9ecef;
    font-size: 0.95rem;
    color: #2d3748;
}

.billing-table tbody tr:hover {
    background-color: #f8f9fa;
}

.billing-table tbody tr:last-child td {
    border-bottom: none;
}

.billing-table .text-right {
    text-align: right;
    font-weight: 500;
}

.billing-table .text-center {
    text-align: center;
}

.billing-table tfoot {
    background: #f8f9fa;
    border-top: 2px solid #dee2e6;
}

.billing-table tfoot td {
    font-weight: 700;
    padding: 1rem;
    font-size: 1rem;
    color: #1a1a1a;
}

.billing-summary {
    margin-top: 2.5rem;
    padding: 1.5rem;
    background: #f8f9fa;
    border: 2px solid #dee2e6;
    border-radius: 4px;
    page-break-inside: avoid;
}

.billing-summary-row {
    display: flex;
    justify-content: space-between;
    padding: 0.65rem 0;
    border-bottom: 1px solid #e9ecef;
    font-size: 0.95rem;
}

.billing-summary-row:last-child {
    border-bottom: none;
}

.billing-summary-row span:first-child {
    font-weight: 500;
    color: #2d3748;
}

.billing-summary-row span:last-child {
    font-weight: 600;
    color: #1a1a1a;
    min-width: 150px;
    text-align: right;
}

.billing-summary-row.total {
    font-weight: 700;
    font-size: 1.1rem;
    border-top: 2px solid #dee2e6;
    border-bottom: 2px solid #dee2e6;
    margin-top: 0.75rem;
    padding-top: 1rem;
    padding-bottom: 1rem;
    background: white;
}

.billing-summary-row.final {
    font-weight: 700;
    font-size: 1.3rem;
    color: #dc2626;
    border-top: 3px solid #dc2626;
    border-bottom: 3px solid #dc2626;
    margin-top: 1rem;
    padding: 1.25rem 0;
    background: #fff5f5;
}

.billing-footer {
    margin-top: 3rem;
    padding-top: 2rem;
    border-top: 2px solid #dee2e6;
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 3rem;
}

.billing-footer-section {
    text-align: center;
}

.billing-footer-section p {
    margin: 2.5rem 0 0.5rem 0;
    font-size: 0.9rem;
    color: #4a5568;
}

.billing-footer-section .signature-line {
    border-top: 1px solid #1a1a1a;
    width: 200px;
    margin: 0 auto;
    padding-top: 0.5rem;
}

.note-box {
    margin-top: 2rem;
    padding: 1rem 1.25rem;
    background: #fffbf0;
    border-left: 4px solid #f59e0b;
    border-radius: 4px;
    font-size: 0.9rem;
    color: #78350f;
}

.note-box strong {
    display: block;
    margin-bottom: 0.5rem;
    color: #92400e;
}

@media print {
    .no-print {
        display: none !important;
    }
    
    .billing-statement {
        box-shadow: none;
        padding: 0.5in;
        max-width: 100%;
    }
    
    .page-container {
        padding: 0;
        margin: 0;
    }
    
    body {
        background: white;
    }
    
    .billing-section {
        page-break-inside: avoid;
    }
    
    .billing-summary {
        page-break-inside: avoid;
    }
    
    @page {
        margin: 0.5in;
    }
}
</style>

<div class="page-container">
    <div class="no-print" style="margin-bottom: 1rem;">
        <a href="<?php echo $invoice ? BASE_URL . 'modules/billing/index.php' : BASE_URL . 'modules/billing/student_view.php'; ?>" class="btn btn-secondary">
            <i class="fas fa-arrow-left"></i> Back
        </a>
        <button onclick="window.print()" class="btn btn-primary" style="margin-left: 0.5rem;">
            <i class="fas fa-print"></i> Print
        </button>
    </div>

    <div class="billing-statement">
        <div class="billing-header">
            <h1>MISAMIS UNIVERSITY</h1>
            <p class="school-subtitle">Ozamiz City, Misamis Occidental</p>
            <p class="school-subtitle" style="margin-top: 0.5rem;">Republic of the Philippines</p>
            <h2>OFFICIAL BILLING STATEMENT</h2>
            <p class="statement-subtitle">Statement of Account for Tuition and Other Fees</p>
        </div>

        <div class="billing-info">
            <div>
                <h4 style="margin: 0 0 1rem 0; font-size: 1rem; font-weight: 700; color: #1a1a1a; text-transform: uppercase; letter-spacing: 0.5px; border-bottom: 2px solid #2563eb; padding-bottom: 0.5rem; display: inline-block;">Student Information</h4>
                <p><strong>Student Number:</strong> <?php echo sanitizeOutput($display_student['student_number']); ?></p>
                <p><strong>Name:</strong> <?php echo sanitizeOutput($display_student['first_name'] . ' ' . 
                    ($display_student['middle_name'] ? $display_student['middle_name'] . ' ' : '') . 
                    $display_student['last_name']); ?></p>
                <p><strong>Program:</strong> <?php echo sanitizeOutput($display_student['program_name'] ?? 'N/A'); ?></p>
                <?php if ($display_student['department_name']): ?>
                    <p><strong>Department:</strong> <?php echo sanitizeOutput($display_student['department_name']); ?></p>
                <?php endif; ?>
                <?php if ($display_student['address']): ?>
                    <p style="margin-top: 0.75rem;"><strong>Address:</strong> <?php echo sanitizeOutput($display_student['address'] . 
                        ($display_student['city'] ? ', ' . $display_student['city'] : '') . 
                        ($display_student['state'] ? ', ' . $display_student['state'] : '') . 
                        ($display_student['zip_code'] ? ' ' . $display_student['zip_code'] : '')); ?></p>
                <?php endif; ?>
            </div>
            <div>
                <h4 style="margin: 0 0 1rem 0; font-size: 1rem; font-weight: 700; color: #1a1a1a; text-transform: uppercase; letter-spacing: 0.5px; border-bottom: 2px solid #2563eb; padding-bottom: 0.5rem; display: inline-block;">Statement Details</h4>
                <?php if ($invoice): ?>
                    <p><strong>Invoice Number:</strong> <?php echo sanitizeOutput($invoice['invoice_number']); ?></p>
                <?php endif; ?>
                <?php if ($invoice): ?>
                    <p><strong>Semester:</strong> <?php echo sanitizeOutput($invoice['semester']); ?></p>
                    <p><strong>Academic Year:</strong> <?php echo sanitizeOutput($invoice['academic_year']); ?></p>
                    <p><strong>Due Date:</strong> <?php echo date('F d, Y', strtotime($invoice['due_date'])); ?></p>
                <?php endif; ?>
                <p><strong>Statement Date:</strong> <?php echo date('F d, Y'); ?></p>
                <p><strong>Status:</strong> 
                    <span style="font-weight: 600; color: <?php echo $final_balance > 0 ? '#dc2626' : '#059669'; ?>;">
                        <?php echo $final_balance > 0 ? 'Outstanding Balance' : 'Paid in Full'; ?>
                    </span>
                </p>
            </div>
        </div>

        <?php if (!empty($tuition_items)): ?>
        <div class="billing-section">
            <h3>TUITION FEE</h3>
            <table class="billing-table">
                <thead>
                    <tr>
                        <th>Description</th>
                        <th class="text-right">Total</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($tuition_items as $item): 
                        // Clean up description - remove unit details
                        $description = $item['description'];
                        if (preg_match('/^Tuition Fee/i', $description)) {
                            // Extract suffix like [General/Minor Course Rate] if present at the end
                            $suffix = '';
                            if (preg_match('/\s*\[.*?\]\s*$/', $description, $matches)) {
                                $suffix = $matches[0];
                                // Remove suffix from description temporarily
                                $description = preg_replace('/\s*\[.*?\]\s*$/', '', $description);
                            }
                            // Remove everything after "Tuition Fee" including dash and unit details
                            $description = preg_replace('/^(Tuition Fee).*$/i', '$1', $description);
                            $description = trim($description) . $suffix;
                        }
                    ?>
                    <tr>
                        <td><?php echo sanitizeOutput($description); ?></td>
                        <td class="text-right">₱<?php echo number_format($item['total_price'], 2); ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
                <tfoot>
                    <tr style="font-weight: bold; background-color: #f9f9f9;">
                        <td class="text-right">Total Tuition Fee:</td>
                        <td class="text-right">₱<?php echo number_format($total_tuition, 2); ?></td>
                    </tr>
                </tfoot>
            </table>
        </div>
        <?php endif; ?>

        <?php if (!empty($other_invoice_items)): ?>
        <div class="billing-section">
            <h3>OTHER FEES</h3>
            <table class="billing-table">
                <thead>
                    <tr>
                        <th>Description</th>
                        <th class="text-right">Unit Price</th>
                        <th class="text-right">Total</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($other_invoice_items as $item): ?>
                    <tr>
                        <td><?php echo sanitizeOutput($item['description']); ?></td>
                        <td class="text-right">₱<?php echo number_format($item['unit_price'], 2); ?></td>
                        <td class="text-right">₱<?php echo number_format($item['total_price'], 2); ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
                <tfoot>
                    <tr style="font-weight: bold; background-color: #f9f9f9;">
                        <td colspan="2" class="text-right">Total Other Fees:</td>
                        <td class="text-right">₱<?php echo number_format($total_other_items, 2); ?></td>
                    </tr>
                </tfoot>
            </table>
        </div>
        <?php endif; ?>

        <?php if (empty($invoice_items)): ?>
        <div class="billing-section">
            <h3>STANDARD FEES</h3>
            <?php if (!$has_laboratory_courses && $student_id): ?>
            <div style="padding: 0.75rem; background-color: #f0f0f0; border-left: 3px solid #666; margin-bottom: 1rem; font-size: 0.9rem;">
                <strong>Note:</strong> Laboratory Fee is not included as you have no enrolled laboratory courses for this period.
            </div>
            <?php endif; ?>
            <table class="billing-table">
                <thead>
                    <tr>
                        <th>Fee Category</th>
                        <th class="text-right">Amount</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($standard_fees)): ?>
                        <tr>
                            <td colspan="2" class="text-center">No standard fees configured</td>
                        </tr>
                    <?php else: ?>
                        <?php 
                        $has_lab_fee = false;
                        foreach ($standard_fees as $fee): 
                            if ($fee['amount'] > 0):
                                // Check if this is Laboratory Fee and student has no lab courses
                                $is_lab_fee = (strtolower($fee['fee_category']) === 'laboratory fee');
                                if ($is_lab_fee && !$has_laboratory_courses) {
                                    continue; // Skip displaying it
                                }
                                $has_lab_fee = $has_lab_fee || $is_lab_fee;
                        ?>
                            <tr>
                                <td><?php echo sanitizeOutput($fee['fee_category']); ?></td>
                                <td class="text-right">₱<?php echo number_format($fee['amount'], 2); ?></td>
                            </tr>
                        <?php 
                            endif;
                        endforeach; 
                        ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>

        <?php if (!empty($student_additional_fees)): ?>
        <div class="billing-section">
            <h3>ADDITIONAL FEES</h3>
            <table class="billing-table">
                <thead>
                    <tr>
                        <th>Description</th>
                        <th class="text-right">Amount</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($student_additional_fees as $fee): ?>
                    <tr>
                        <td><?php echo sanitizeOutput($fee['fee_name']); ?>
                            <?php if ($fee['description']): ?>
                                <br><small class="text-muted"><?php echo sanitizeOutput($fee['description']); ?></small>
                            <?php endif; ?>
                        </td>
                        <td class="text-right">₱<?php echo number_format($fee['amount'], 2); ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>

        <div class="billing-summary">
            <h3 style="margin-bottom: 1.25rem; background: transparent; color: #1a1a1a; padding: 0; border-bottom: 2px solid #dee2e6; padding-bottom: 0.75rem; font-size: 1.1rem;">ACCOUNT SUMMARY</h3>
            
            <?php if (!empty($tuition_items)): ?>
            <div class="billing-summary-row">
                <span>Tuition Fee:</span>
                <span>₱<?php echo number_format($total_tuition, 2); ?></span>
            </div>
            <?php endif; ?>
            <?php if (!empty($other_invoice_items)): ?>
            <div class="billing-summary-row">
                <span>Other Fees:</span>
                <span>₱<?php echo number_format($total_other_items, 2); ?></span>
            </div>
            <?php endif; ?>
            <?php if (empty($invoice_items)): ?>
            <div class="billing-summary-row">
                <span>Total Standard Fees:</span>
                <span>₱<?php echo number_format($total_standard_fees, 2); ?></span>
            </div>
            <?php endif; ?>
            <?php if ($old_account > 0): ?>
            <div class="billing-summary-row">
                <span>Previous Balance:</span>
                <span>₱<?php echo number_format($old_account, 2); ?></span>
            </div>
            <?php endif; ?>
            <?php if ($total_additional_fees > 0): ?>
            <div class="billing-summary-row">
                <span>Additional Fees:</span>
                <span>₱<?php echo number_format($total_additional_fees, 2); ?></span>
            </div>
            <?php endif; ?>
            <div class="billing-summary-row total">
                <span>TOTAL ACCOUNT:</span>
                <span>₱<?php echo number_format($total_account, 2); ?></span>
            </div>
            <?php if ($discount > 0): ?>
            <div class="billing-summary-row">
                <span>Less: Discount/Scholarship:</span>
                <span style="color: #059669;">-₱<?php echo number_format($discount, 2); ?></span>
            </div>
            <?php endif; ?>
            <?php if ($total_payments > 0): ?>
            <div class="billing-summary-row">
                <span>Less: Payments Made:</span>
                <span style="color: #059669;">-₱<?php echo number_format($total_payments, 2); ?></span>
            </div>
            <?php endif; ?>
            <div class="billing-summary-row final">
                <span>OUTSTANDING BALANCE:</span>
                <span>₱<?php echo number_format($final_balance, 2); ?></span>
            </div>
        </div>

        <?php if (!empty($payments)): ?>
        <div class="billing-section" style="margin-top: 2rem;">
            <h3>PAYMENT HISTORY</h3>
            <table class="billing-table">
                <thead>
                    <tr>
                        <th>Payment Date</th>
                        <th>Payment Method</th>
                        <th class="text-right">Amount Paid</th>
                        <th>Reference Number</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($payments as $payment): ?>
                    <tr>
                        <td><?php echo date('F d, Y', strtotime($payment['payment_date'])); ?></td>
                        <td><?php echo sanitizeOutput($payment['payment_method']); ?></td>
                        <td class="text-right">₱<?php echo number_format($payment['payment_amount'], 2); ?></td>
                        <td><?php echo sanitizeOutput($payment['reference_number'] ?? '-'); ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
                <tfoot>
                    <tr>
                        <td colspan="2" class="text-right" style="font-weight: 700;">Total Payments:</td>
                        <td class="text-right">₱<?php echo number_format($total_payments, 2); ?></td>
                        <td></td>
                    </tr>
                </tfoot>
            </table>
        </div>
        <?php endif; ?>

        <?php if ($final_balance > 0): ?>
        <div class="note-box">
            <strong><i class="fas fa-exclamation-triangle"></i> IMPORTANT NOTICE</strong>
            <p style="margin: 0;">Please settle your outstanding balance of <strong>₱<?php echo number_format($final_balance, 2); ?></strong> on or before the due date to avoid late payment charges and to ensure uninterrupted enrollment. For payment inquiries, please contact the Billing Office.</p>
        </div>
        <?php endif; ?>

        <div class="billing-footer">
            <div class="billing-footer-section">
                <p>Prepared by:</p>
                <div class="signature-line"></div>
                <p style="margin-top: 0.5rem; font-weight: 600;">Billing Office</p>
            </div>
            <div class="billing-footer-section">
                <p>Received by:</p>
                <div class="signature-line"></div>
                <p style="margin-top: 0.5rem; font-weight: 600;">Student/Parent/Guardian</p>
            </div>
        </div>

        <div style="margin-top: 2rem; padding-top: 1rem; border-top: 1px solid #dee2e6; text-align: center; font-size: 0.85rem; color: #6b7280;">
            <p style="margin: 0.25rem 0;"><strong>MISAMIS UNIVERSITY</strong> | Ozamiz City, Misamis Occidental</p>
            <p style="margin: 0.25rem 0;">This is a computer-generated document. No signature is required.</p>
            <p style="margin: 0.25rem 0;">For inquiries, please contact the Billing Office.</p>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../../includes/footer.php'; ?>

