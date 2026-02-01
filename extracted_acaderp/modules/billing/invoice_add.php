<?php
require_once __DIR__ . '/../../config/config.php';
requireAuth([ROLE_ADMIN]);
$page_title = 'Create Invoice';
$pdo = getDBConnection();

$error = '';
$success = false;

// Get all active students for dropdown
$students = $pdo->query("SELECT s.student_id, s.student_number, s.first_name, s.last_name, s.program_id, p.program_name
                          FROM students s
                          LEFT JOIN programs p ON s.program_id = p.program_id
                          WHERE s.status = 'Active'
                          ORDER BY s.student_number")->fetchAll();

// Generate invoice number
function generateInvoiceNumber($pdo) {
    $stmt = $pdo->query("SELECT COUNT(*) FROM invoices");
    $count = $stmt->fetchColumn();
    $invoice_number = 'INV-' . str_pad($count + 1, 6, '0', STR_PAD_LEFT);
    
    // Check if it exists, if so increment
    $check_stmt = $pdo->prepare("SELECT COUNT(*) FROM invoices WHERE invoice_number = ?");
    $check_stmt->execute([$invoice_number]);
    while ($check_stmt->fetchColumn() > 0) {
        $count++;
        $invoice_number = 'INV-' . str_pad($count + 1, 6, '0', STR_PAD_LEFT);
        $check_stmt->execute([$invoice_number]);
    }
    return $invoice_number;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $pdo->beginTransaction();
        
        $student_id = (int)($_POST['student_id'] ?? 0);
        $invoice_number = sanitizeInput($_POST['invoice_number'] ?? '');
        $semester = sanitizeInput($_POST['semester'] ?? '');
        $academic_year = sanitizeInput($_POST['academic_year'] ?? '');
        $due_date = sanitizeInput($_POST['due_date'] ?? '');
        $discount = (float)($_POST['discount'] ?? 0);
        $financial_aid_amount = (float)($_POST['financial_aid_amount'] ?? 0);
        
        // Validate required fields
        if (!$student_id || !$invoice_number || !$semester || !$academic_year || !$due_date) {
            throw new Exception('Please fill in all required fields.');
        }
        
        // Validate due date
        if (strtotime($due_date) < strtotime(date('Y-m-d'))) {
            throw new Exception('Due date cannot be in the past.');
        }
        
        // Get invoice items
        $items = [];
        if (isset($_POST['items']) && is_array($_POST['items'])) {
            foreach ($_POST['items'] as $item) {
                if (!empty($item['description']) && isset($item['unit_price'])) {
                    $quantity = (float)($item['quantity'] ?? 1);
                    $unit_price = (float)$item['unit_price'];
                    $total_price = $quantity * $unit_price;
                    
                    $items[] = [
                        'item_type' => sanitizeInput($item['item_type'] ?? 'Other'),
                        'description' => sanitizeInput($item['description']),
                        'quantity' => $quantity,
                        'unit_price' => $unit_price,
                        'total_price' => $total_price
                    ];
                }
            }
        }
        
        if (empty($items)) {
            throw new Exception('Please add at least one invoice item.');
        }
        
        // Calculate subtotal
        $subtotal = array_sum(array_column($items, 'total_price'));
        
        // Calculate total amount
        $total_amount = max(0, $subtotal - $discount - $financial_aid_amount);
        $balance = $total_amount;
        
        // Check if invoice number already exists
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM invoices WHERE invoice_number = ?");
        $stmt->execute([$invoice_number]);
        if ($stmt->fetchColumn() > 0) {
            throw new Exception('Invoice number already exists. Please use a different number.');
        }
        
        // Insert invoice
        $stmt = $pdo->prepare("INSERT INTO invoices (student_id, invoice_number, semester, academic_year, due_date, 
                              subtotal, discount, financial_aid_amount, total_amount, paid_amount, balance, status)
                              VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 0, ?, 'Pending')");
        $stmt->execute([
            $student_id, $invoice_number, $semester, $academic_year, $due_date,
            $subtotal, $discount, $financial_aid_amount, $total_amount, $balance
        ]);
        
        $invoice_id = $pdo->lastInsertId();
        
        // Insert invoice items
        $stmt = $pdo->prepare("INSERT INTO invoice_items (invoice_id, item_type, description, quantity, unit_price, total_price)
                              VALUES (?, ?, ?, ?, ?, ?)");
        foreach ($items as $item) {
            $stmt->execute([
                $invoice_id,
                $item['item_type'],
                $item['description'],
                $item['quantity'],
                $item['unit_price'],
                $item['total_price']
            ]);
        }
        
        $pdo->commit();
        $success = true;
        header('Location: ' . BASE_URL . 'modules/billing/index.php?success=' . urlencode('Invoice created successfully.'));
        exit;
        
    } catch (Exception $e) {
        $pdo->rollBack();
        $error = $e->getMessage();
    }
}

// Generate default invoice number
$default_invoice_number = generateInvoiceNumber($pdo);
$current_year = date('Y');
$next_year = date('Y', strtotime('+1 year'));

include __DIR__ . '/../../includes/header.php';
?>

<div class="page-container">
    <div class="page-header">
        <h1><i class="fas fa-file-invoice"></i> Create Invoice</h1>
        <a href="<?php echo BASE_URL; ?>modules/billing/index.php" class="btn btn-secondary">
            <i class="fas fa-arrow-left"></i> Back
        </a>
    </div>

    <?php if ($error): ?>
        <div class="alert alert-error"><?php echo sanitizeOutput($error); ?></div>
    <?php endif; ?>

    <form method="POST" action="" id="invoiceForm" onsubmit="enableAllFields()">
        <div class="card" style="margin-bottom: 1.5rem;">
            <h2>Invoice Information</h2>
            
            <div class="form-row">
                <div class="form-group">
                    <label for="student_id">Student <span class="text-danger">*</span></label>
                    <select id="student_id" name="student_id" class="form-control" required>
                        <option value="">-- Select Student --</option>
                        <?php foreach ($students as $student): ?>
                            <option value="<?php echo $student['student_id']; ?>">
                                <?php echo sanitizeOutput($student['student_number'] . ' - ' . $student['first_name'] . ' ' . $student['last_name'] . 
                                    ($student['program_name'] ? ' (' . $student['program_name'] . ')' : '')); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="invoice_number">Invoice Number <span class="text-danger">*</span></label>
                    <input type="text" id="invoice_number" name="invoice_number" class="form-control" 
                           value="<?php echo sanitizeOutput($default_invoice_number); ?>" required>
                </div>
            </div>
            
            <div class="form-row">
                <div class="form-group">
                    <label for="semester">Semester <span class="text-danger">*</span></label>
                    <select id="semester" name="semester" class="form-control" required onchange="loadStudentData()">
                        <option value="">-- Select Semester --</option>
                        <option value="First">First</option>
                        <option value="2nd">2nd</option>
                        <option value="Summer">Summer</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="academic_year">Academic Year <span class="text-danger">*</span></label>
                    <input type="text" id="academic_year" name="academic_year" class="form-control" 
                           value="<?php echo sanitizeOutput($current_year . '-' . substr($next_year, -2)); ?>" 
                           placeholder="e.g., 2024-25" required onchange="loadStudentData()">
                </div>
                
                <div class="form-group">
                    <label for="due_date">Due Date <span class="text-danger">*</span></label>
                    <input type="date" id="due_date" name="due_date" class="form-control" 
                           min="<?php echo date('Y-m-d'); ?>" required>
                </div>
            </div>
        </div>

        <div class="card" style="margin-bottom: 1.5rem;">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem;">
                <h2 style="margin: 0;">Invoice Items</h2>
                <div style="display: flex; gap: 0.5rem;">
                    <button type="button" class="btn btn-info" onclick="regenerateDefaultItems()" id="regenerateBtn" style="display: none;">
                        <i class="fas fa-sync"></i> Regenerate Default Items
                    </button>
                    <button type="button" class="btn btn-secondary" onclick="addInvoiceItem()">
                        <i class="fas fa-plus"></i> Add Item
                    </button>
                </div>
            </div>
            <div id="loadingMessage" style="display: none; padding: 1rem; background-color: #e7f3ff; border-radius: 4px; margin-bottom: 1rem;">
                <i class="fas fa-spinner fa-spin"></i> Loading student data and calculating fees...
            </div>
            
            <div id="invoiceItems">
                <div class="alert alert-info" style="padding: 1rem; margin-bottom: 1rem;">
                    <i class="fas fa-info-circle"></i> Select a student and semester to automatically generate invoice items.
                </div>
            </div>
        </div>

        <div class="card">
            <h2>Financial Adjustments</h2>
            
            <div class="form-row">
                <div class="form-group">
                    <label for="discount">Discount</label>
                    <input type="number" id="discount" name="discount" class="form-control" step="0.01" min="0" 
                           value="0" onchange="calculateTotal()">
                </div>
                
                <div class="form-group">
                    <label for="financial_aid_amount">Financial Aid Amount</label>
                    <input type="number" id="financial_aid_amount" name="financial_aid_amount" class="form-control" 
                           step="0.01" min="0" value="0" onchange="calculateTotal()">
                </div>
            </div>
            
            <div style="margin-top: 1.5rem; padding-top: 1.5rem; border-top: 2px solid var(--border-color);">
                <div style="display: flex; justify-content: space-between; margin-bottom: 0.5rem;">
                    <strong>Subtotal:</strong>
                    <span id="subtotal">₱0.00</span>
                </div>
                <div style="display: flex; justify-content: space-between; margin-bottom: 0.5rem;">
                    <strong>Discount:</strong>
                    <span id="discount_display">₱0.00</span>
                </div>
                <div style="display: flex; justify-content: space-between; margin-bottom: 0.5rem;">
                    <strong>Financial Aid:</strong>
                    <span id="financial_aid_display">₱0.00</span>
                </div>
                <div style="display: flex; justify-content: space-between; font-size: 1.2rem; font-weight: bold; margin-top: 1rem; padding-top: 1rem; border-top: 1px solid var(--border-color);">
                    <strong>Total Amount:</strong>
                    <span id="total_amount">₱0.00</span>
                </div>
            </div>
        </div>

        <div class="form-actions" style="margin-top: 1.5rem; display: flex; gap: 0.5rem;">
            <button type="button" class="btn btn-info" onclick="previewInvoice()" style="display: none;" id="previewBtn">
                <i class="fas fa-eye"></i> Preview Invoice
            </button>
            <button type="submit" class="btn btn-primary">
                <i class="fas fa-save"></i> Create Invoice
            </button>
            <a href="<?php echo BASE_URL; ?>modules/billing/index.php" class="btn btn-secondary">
                Cancel
            </a>
        </div>
    </form>
</div>

<script>
let itemIndex = 0;
let currentStudentData = null;
let autoGeneratedItems = new Set(); // Track which items are auto-generated

// Load student data and auto-generate invoice items
function loadStudentData() {
    const studentId = document.getElementById('student_id').value;
    const semester = document.getElementById('semester').value;
    const academicYear = document.getElementById('academic_year').value;
    
    if (!studentId || !semester || !academicYear) {
        return;
    }
    
    const loadingMessage = document.getElementById('loadingMessage');
    const invoiceItems = document.getElementById('invoiceItems');
    const regenerateBtn = document.getElementById('regenerateBtn');
    
    loadingMessage.style.display = 'block';
    
    fetch(`<?php echo BASE_URL; ?>modules/billing/get_student_data.php?student_id=${studentId}&semester=${encodeURIComponent(semester)}&academic_year=${encodeURIComponent(academicYear)}`)
        .then(response => response.json())
        .then(data => {
            loadingMessage.style.display = 'none';
            
            if (data.error) {
                alert('Error: ' + data.error);
                return;
            }
            
            currentStudentData = data;
            generateDefaultItems(data);
            regenerateBtn.style.display = 'inline-block';
            document.getElementById('previewBtn').style.display = 'inline-block';
            calculateTotal();
        })
        .catch(error => {
            loadingMessage.style.display = 'none';
            console.error('Error:', error);
            alert('Error loading student data. Please try again.');
        });
}

// Generate default invoice items
function generateDefaultItems(data) {
    const container = document.getElementById('invoiceItems');
    container.innerHTML = '';
    itemIndex = 0;
    autoGeneratedItems.clear();
    
    const student = data.student;
    const enrollment = data.enrollment;
    const tuition = data.tuition;
    const standardFees = data.standard_fees || [];
    
    const lectureUnits = parseFloat(enrollment.lecture_units) || 0;
    const labUnits = parseFloat(enrollment.lab_units) || 0;
    const lectureRate = parseFloat(tuition.lecture_rate) || 0;
    const labRate = parseFloat(tuition.laboratory_rate) || 0;
    const rateCategory = tuition.rate_category || 'Program';
    
    // 1. Lecture Tuition (Unit-based fee)
    if (lectureRate > 0 && lectureUnits > 0) {
        const lectureTuition = lectureUnits * lectureRate;
        let description = `Lecture Tuition - ${lectureUnits} unit(s) @ ₱${lectureRate.toFixed(2)} per unit`;
        
        if (rateCategory === 'General') {
            description += ' [General Rate]';
        }
        
        addInvoiceItem({
            item_type: 'Tuition',
            description: description,
            quantity: lectureUnits,
            unit_price: lectureRate,
            total_price: lectureTuition,
            isAutoGenerated: true,
            isEditable: false
        });
    } else if (lectureUnits > 0) {
        // Student has enrolled lecture units but no rate configured
        addInvoiceItem({
            item_type: 'Tuition',
            description: `Lecture Tuition - ${lectureUnits} unit(s) (No rate configured)`,
            quantity: lectureUnits,
            unit_price: 0,
            total_price: 0,
            isAutoGenerated: true,
            isEditable: true
        });
    }
    
    // 2. Laboratory Tuition (Unit-based fee)
    if (labRate > 0 && labUnits > 0) {
        const labTuition = labUnits * labRate;
        let description = `Laboratory Tuition - ${labUnits} unit(s) @ ₱${labRate.toFixed(2)} per unit`;
        
        if (rateCategory === 'General') {
            description += ' [General Rate]';
        }
        
        addInvoiceItem({
            item_type: 'Tuition',
            description: description,
            quantity: labUnits,
            unit_price: labRate,
            total_price: labTuition,
            isAutoGenerated: true,
            isEditable: false
        });
    } else if (labUnits > 0) {
        // Student has enrolled lab units but no rate configured
        addInvoiceItem({
            item_type: 'Tuition',
            description: `Laboratory Tuition - ${labUnits} unit(s) (No rate configured)`,
            quantity: labUnits,
            unit_price: 0,
            total_price: 0,
            isAutoGenerated: true,
            isEditable: true
        });
    }
    
    // 3. Standard Fees (Fixed fees required for all students)
    standardFees.forEach(fee => {
        const feeCategory = fee.fee_category.toLowerCase();
        const feeAmount = parseFloat(fee.amount) || 0;
        
        // Skip if amount is 0
        if (feeAmount <= 0) {
            return;
        }
        
        // Only add Laboratory Fee if student has lab courses
        if (feeCategory === 'laboratory fee' && !enrollment.has_lab_courses) {
            return;
        }
        
        addInvoiceItem({
            item_type: 'Fee',
            description: fee.fee_category,
            quantity: 1,
            unit_price: feeAmount,
            total_price: feeAmount,
            isAutoGenerated: true,
            isEditable: false
        });
    });
    
    // Show message if no items generated
    if (container.children.length === 0) {
        container.innerHTML = '<div class="alert alert-warning" style="padding: 1rem; margin-bottom: 1rem;">No invoice items could be generated. Please check student enrollment and tuition rates.</div>';
    }
}

// Regenerate default items
function regenerateDefaultItems() {
    if (currentStudentData) {
        generateDefaultItems(currentStudentData);
        calculateTotal();
    } else {
        alert('Please select a student first.');
    }
}

// Add invoice item (with auto-generation support)
function addInvoiceItem(itemData = null) {
    const container = document.getElementById('invoiceItems');
    
    // Remove info message if present
    const infoMsg = container.querySelector('.alert-info');
    if (infoMsg) {
        infoMsg.remove();
    }
    
    const itemDiv = document.createElement('div');
    itemDiv.className = 'invoice-item';
    itemDiv.style.cssText = 'border: 1px solid var(--border-color); padding: 1rem; margin-bottom: 1rem; border-radius: 4px;';
    
    const isAutoGenerated = itemData && itemData.isAutoGenerated;
    const isEditable = itemData ? (itemData.isEditable !== false) : true;
    const itemId = itemIndex;
    
    if (isAutoGenerated) {
        autoGeneratedItems.add(itemId);
    }
    
    itemDiv.innerHTML = `
        <div class="form-row">
            <div class="form-group" style="flex: 1;">
                <label>Item Type</label>
                <select name="items[${itemId}][item_type]" class="form-control" ${!isEditable ? 'disabled' : ''}>
                    <option value="Tuition" ${itemData && itemData.item_type === 'Tuition' ? 'selected' : ''}>Tuition</option>
                    <option value="Fees" ${itemData && itemData.item_type === 'Fees' ? 'selected' : ''}>Fees</option>
                    <option value="Books" ${itemData && itemData.item_type === 'Books' ? 'selected' : ''}>Books</option>
                    <option value="Lab Fee" ${itemData && itemData.item_type === 'Lab Fee' ? 'selected' : ''}>Lab Fee</option>
                    <option value="Other" ${itemData && itemData.item_type === 'Other' ? 'selected' : ''}>Other</option>
                </select>
                ${isAutoGenerated ? '<small class="text-muted" style="display: block; margin-top: 0.25rem;"><i class="fas fa-magic"></i> Auto-generated</small>' : ''}
            </div>
            <div class="form-group" style="flex: 2;">
                <label>Description <span class="text-danger">*</span></label>
                <input type="text" name="items[${itemId}][description]" class="form-control" 
                       value="${itemData ? (itemData.description || '') : ''}" 
                       ${!isEditable ? 'readonly style="background-color: #f5f5f5;"' : ''} required>
            </div>
            <div class="form-group" style="flex: 0.5;">
                <label>Quantity</label>
                <input type="number" name="items[${itemId}][quantity]" class="form-control" 
                       value="${itemData ? (itemData.quantity || 1) : 1}" 
                       min="0.01" step="0.01"
                       ${!isEditable ? 'readonly style="background-color: #f5f5f5;"' : ''}
                       onchange="calculateItemTotal(this)" required>
            </div>
            <div class="form-group" style="flex: 1;">
                <label>Unit Price <span class="text-danger">*</span></label>
                <input type="number" name="items[${itemId}][unit_price]" class="form-control" 
                       step="0.01" min="0" 
                       value="${itemData ? (itemData.unit_price || 0) : 0}"
                       ${!isEditable ? 'readonly style="background-color: #f5f5f5;"' : ''}
                       onchange="calculateItemTotal(this)" required>
            </div>
            <div class="form-group" style="flex: 1;">
                <label>Total Price</label>
                <input type="text" name="items[${itemId}][total_price]" class="form-control" 
                       value="${itemData ? parseFloat(itemData.total_price || 0).toFixed(2) : '0.00'}" 
                       readonly style="background-color: #f5f5f5;">
            </div>
            <div class="form-group" style="flex: 0.3;">
                <label>&nbsp;</label>
                <button type="button" class="btn btn-danger" onclick="removeInvoiceItem(this)" style="width: 100%;">
                    <i class="fas fa-trash"></i>
                </button>
            </div>
        </div>
        ${isAutoGenerated ? '<div style="margin-top: 0.5rem;"><label style="font-size: 0.85rem;"><input type="checkbox" onchange="toggleItemEdit(this)" data-item-id="${itemId}"> Allow editing</label></div>' : ''}
    `;
    
    container.appendChild(itemDiv);
    itemIndex++;
    
    if (itemData && itemData.total_price) {
        calculateTotal();
    }
}

// Toggle edit mode for auto-generated items
function toggleItemEdit(checkbox) {
    const itemId = checkbox.getAttribute('data-item-id');
    const itemDiv = checkbox.closest('.invoice-item');
    const description = itemDiv.querySelector('input[name*="[description]"]');
    const quantity = itemDiv.querySelector('input[name*="[quantity]"]');
    const unitPrice = itemDiv.querySelector('input[name*="[unit_price]"]');
    const itemType = itemDiv.querySelector('select[name*="[item_type]"]');
    
    if (checkbox.checked) {
        description.removeAttribute('readonly');
        description.style.backgroundColor = '';
        quantity.removeAttribute('readonly');
        quantity.style.backgroundColor = '';
        unitPrice.removeAttribute('readonly');
        unitPrice.style.backgroundColor = '';
        itemType.removeAttribute('disabled');
        autoGeneratedItems.delete(parseInt(itemId));
    } else {
        description.setAttribute('readonly', 'readonly');
        description.style.backgroundColor = '#f5f5f5';
        quantity.setAttribute('readonly', 'readonly');
        quantity.style.backgroundColor = '#f5f5f5';
        unitPrice.setAttribute('readonly', 'readonly');
        unitPrice.style.backgroundColor = '#f5f5f5';
        itemType.setAttribute('disabled', 'disabled');
        autoGeneratedItems.add(parseInt(itemId));
    }
}

function removeInvoiceItem(button) {
    const items = document.querySelectorAll('.invoice-item');
    if (items.length > 1) {
        const itemDiv = button.closest('.invoice-item');
        const itemId = parseInt(itemDiv.querySelector('input[name*="[quantity]"]').getAttribute('name').match(/\[(\d+)\]/)[1]);
        autoGeneratedItems.delete(itemId);
        itemDiv.remove();
        calculateTotal();
    } else {
        alert('At least one invoice item is required.');
    }
}

function calculateItemTotal(input) {
    const item = input.closest('.invoice-item');
    const quantity = parseFloat(item.querySelector('input[name*="[quantity]"]').value) || 0;
    const unitPrice = parseFloat(item.querySelector('input[name*="[unit_price]"]').value) || 0;
    const totalPrice = quantity * unitPrice;
    item.querySelector('input[name*="[total_price]"]').value = totalPrice.toFixed(2);
    calculateTotal();
}

function calculateTotal() {
    let subtotal = 0;
    document.querySelectorAll('input[name*="[total_price]"]').forEach(input => {
        subtotal += parseFloat(input.value) || 0;
    });
    
    const discount = parseFloat(document.getElementById('discount').value) || 0;
    const financialAid = parseFloat(document.getElementById('financial_aid_amount').value) || 0;
    
    const totalAmount = Math.max(0, subtotal - discount - financialAid);
    
    document.getElementById('subtotal').textContent = '₱' + subtotal.toFixed(2);
    document.getElementById('discount_display').textContent = '₱' + discount.toFixed(2);
    document.getElementById('financial_aid_display').textContent = '₱' + financialAid.toFixed(2);
    document.getElementById('total_amount').textContent = '₱' + totalAmount.toFixed(2);
}

// Preview invoice function
function previewInvoice() {
    // Get form data
    const studentId = document.getElementById('student_id').value;
    const invoiceNumber = document.getElementById('invoice_number').value;
    const semester = document.getElementById('semester').value;
    const academicYear = document.getElementById('academic_year').value;
    const dueDate = document.getElementById('due_date').value;
    
    if (!studentId || !invoiceNumber || !semester || !academicYear || !dueDate) {
        alert('Please fill in all required fields before previewing.');
        return;
    }
    
    // Calculate totals
    let subtotal = 0;
    const items = [];
    document.querySelectorAll('.invoice-item').forEach((itemDiv, index) => {
        const description = itemDiv.querySelector('input[name*="[description]"]').value;
        const quantity = parseFloat(itemDiv.querySelector('input[name*="[quantity]"]').value) || 0;
        const unitPrice = parseFloat(itemDiv.querySelector('input[name*="[unit_price]"]').value) || 0;
        const totalPrice = quantity * unitPrice;
        if (description && totalPrice > 0) {
            items.push({ description, quantity, unitPrice, totalPrice });
            subtotal += totalPrice;
        }
    });
    
    const discount = parseFloat(document.getElementById('discount').value) || 0;
    const financialAid = parseFloat(document.getElementById('financial_aid_amount').value) || 0;
    const totalAmount = Math.max(0, subtotal - discount - financialAid);
    
    // Show preview in a modal or new window
    const previewContent = `
        <div style="padding: 2rem; max-width: 800px; margin: 0 auto;">
            <h2 style="text-align: center; margin-bottom: 2rem;">Invoice Preview</h2>
            <div style="margin-bottom: 1rem;">
                <strong>Invoice Number:</strong> ${invoiceNumber}<br>
                <strong>Semester:</strong> ${semester}<br>
                <strong>Academic Year:</strong> ${academicYear}<br>
                <strong>Due Date:</strong> ${dueDate}
            </div>
            <table style="width: 100%; border-collapse: collapse; margin-bottom: 1rem;">
                <thead>
                    <tr style="background-color: #f5f5f5;">
                        <th style="padding: 0.5rem; text-align: left; border: 1px solid #ddd;">Description</th>
                        <th style="padding: 0.5rem; text-align: center; border: 1px solid #ddd;">Quantity</th>
                        <th style="padding: 0.5rem; text-align: right; border: 1px solid #ddd;">Unit Price</th>
                        <th style="padding: 0.5rem; text-align: right; border: 1px solid #ddd;">Total</th>
                    </tr>
                </thead>
                <tbody>
                    ${items.map(item => `
                        <tr>
                            <td style="padding: 0.5rem; border: 1px solid #ddd;">${item.description}</td>
                            <td style="padding: 0.5rem; text-align: center; border: 1px solid #ddd;">${item.quantity}</td>
                            <td style="padding: 0.5rem; text-align: right; border: 1px solid #ddd;">₱${item.unitPrice.toFixed(2)}</td>
                            <td style="padding: 0.5rem; text-align: right; border: 1px solid #ddd;">₱${item.totalPrice.toFixed(2)}</td>
                        </tr>
                    `).join('')}
                </tbody>
            </table>
            <div style="margin-left: auto; width: 300px;">
                <div style="display: flex; justify-content: space-between; margin-bottom: 0.5rem;">
                    <strong>Subtotal:</strong> <span>₱${subtotal.toFixed(2)}</span>
                </div>
                ${discount > 0 ? `<div style="display: flex; justify-content: space-between; margin-bottom: 0.5rem;"><strong>Discount:</strong> <span>-₱${discount.toFixed(2)}</span></div>` : ''}
                ${financialAid > 0 ? `<div style="display: flex; justify-content: space-between; margin-bottom: 0.5rem;"><strong>Financial Aid:</strong> <span>-₱${financialAid.toFixed(2)}</span></div>` : ''}
                <div style="display: flex; justify-content: space-between; font-size: 1.2rem; font-weight: bold; margin-top: 1rem; padding-top: 1rem; border-top: 2px solid #333;">
                    <strong>Total Amount:</strong> <span>₱${totalAmount.toFixed(2)}</span>
                </div>
            </div>
        </div>
    `;
    
    const previewWindow = window.open('', 'InvoicePreview', 'width=900,height=700,scrollbars=yes');
    previewWindow.document.write(`
        <html>
            <head>
                <title>Invoice Preview - ${invoiceNumber}</title>
                <style>
                    body { font-family: Arial, sans-serif; margin: 0; padding: 1rem; }
                    table { border-collapse: collapse; }
                    th, td { padding: 0.5rem; }
                </style>
            </head>
            <body>
                ${previewContent}
                <div style="text-align: center; margin-top: 2rem;">
                    <button onclick="window.close()" style="padding: 0.5rem 1rem; background-color: #666; color: white; border: none; cursor: pointer;">Close</button>
                </div>
            </body>
        </html>
    `);
    previewWindow.document.close();
}

// Enable all disabled fields before form submission
function enableAllFields() {
    document.querySelectorAll('.invoice-item select[disabled]').forEach(select => {
        select.disabled = false;
    });
    document.querySelectorAll('.invoice-item input[readonly]').forEach(input => {
        // Keep readonly inputs as they are (they will be submitted)
        // Only re-enable if they were previously disabled
        if (input.style.backgroundColor === 'rgb(245, 245, 245)' && input.hasAttribute('readonly')) {
            // They're readonly but will be submitted, so we're good
        }
    });
}

// Initialize calculation on page load
document.addEventListener('DOMContentLoaded', function() {
    calculateTotal();
});
</script>

<?php include __DIR__ . '/../../includes/footer.php'; ?>

