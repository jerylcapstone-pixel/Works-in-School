<?php
require_once __DIR__ . '/../../config/config.php';
requireAuth([ROLE_ADMIN]);
$page_title = 'Tuition & Billing';
$pdo = getDBConnection();

// Get filter parameters
$search = isset($_GET['search']) ? sanitizeInput($_GET['search']) : '';
$department_id = isset($_GET['department_id']) ? (int)$_GET['department_id'] : 0;
$program_id = isset($_GET['program_id']) ? (int)$_GET['program_id'] : 0;

// Get all departments for filter dropdown
try {
    $dept_status_column = $pdo->query("SHOW COLUMNS FROM departments LIKE 'status'");
    $department_status_exists = $dept_status_column && $dept_status_column->rowCount() > 0;
} catch (PDOException $e) {
    $department_status_exists = false;
}

if ($department_status_exists) {
    $departments_stmt = $pdo->query("SELECT department_id, department_name FROM departments WHERE status = 'Active' ORDER BY department_name");
} else {
    $departments_stmt = $pdo->query("SELECT department_id, department_name FROM departments ORDER BY department_name");
}
$departments = $departments_stmt ? $departments_stmt->fetchAll() : [];

// Get all programs for filter dropdown
try {
    $status_column = $pdo->query("SHOW COLUMNS FROM programs LIKE 'status'");
    $program_status_exists = $status_column && $status_column->rowCount() > 0;
} catch (PDOException $e) {
    $program_status_exists = false;
}

$programs = [];
if ($department_id > 0) {
    // Filter programs by selected department
    $program_query = $program_status_exists
        ? "SELECT program_id, program_name FROM programs WHERE department_id = ? AND status = 'Active' ORDER BY program_name"
        : "SELECT program_id, program_name FROM programs WHERE department_id = ? ORDER BY program_name";
    $stmt = $pdo->prepare($program_query);
    $stmt->execute([$department_id]);
    $programs = $stmt->fetchAll();
} else {
    // Get all programs
    $program_query = $program_status_exists
        ? "SELECT program_id, program_name FROM programs WHERE status = 'Active' ORDER BY program_name"
        : "SELECT program_id, program_name FROM programs ORDER BY program_name";
    $stmt = $pdo->query($program_query);
    $programs = $stmt ? $stmt->fetchAll() : [];
}

// Build query with filters
$query = "SELECT i.*, s.student_number, s.first_name, s.last_name, s.program_id,
          p.program_name, p.department_id,
          d.department_name,
          (SELECT COALESCE(SUM(payment.payment_amount), 0) FROM payments payment WHERE payment.invoice_id = i.invoice_id) as total_paid
          FROM invoices i
          LEFT JOIN students s ON i.student_id = s.student_id
          LEFT JOIN programs p ON s.program_id = p.program_id
          LEFT JOIN departments d ON p.department_id = d.department_id
          WHERE 1=1";
          
$params = [];

// Apply search filter
if (!empty($search)) {
    $query .= " AND (i.invoice_number LIKE ? OR s.student_number LIKE ? OR s.first_name LIKE ? OR s.last_name LIKE ?)";
    $search_param = "%{$search}%";
    $params = array_merge($params, [$search_param, $search_param, $search_param, $search_param]);
}

// Apply department filter
if ($department_id > 0) {
    $query .= " AND p.department_id = ?";
    $params[] = $department_id;
}

// Apply program filter
if ($program_id > 0) {
    $query .= " AND s.program_id = ?";
    $params[] = $program_id;
}

$query .= " ORDER BY i.created_at DESC LIMIT 100";

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$invoices = $stmt->fetchAll();

include __DIR__ . '/../../includes/header.php';
?>

<div class="page-container">
    <?php if (isset($_GET['success'])): ?>
        <div class="alert alert-success"><?php echo sanitizeOutput($_GET['success']); ?></div>
    <?php endif; ?>
    
    <?php if (isset($_GET['error'])): ?>
        <div class="alert alert-error"><?php echo sanitizeOutput($_GET['error']); ?></div>
    <?php endif; ?>
    <div class="page-header">
        <h1><i class="fas fa-dollar-sign"></i> Tuition & Academic Billing</h1>
        <div style="display: flex; gap: 10px;">
            <a href="<?php echo BASE_URL; ?>modules/billing/generate.php" class="btn btn-primary">
                <i class="fas fa-file-invoice-dollar"></i> Generate Invoices
            </a>
            <a href="<?php echo BASE_URL; ?>modules/billing/invoice_add.php" class="btn btn-secondary">
                <i class="fas fa-plus"></i> Create Invoice
            </a>
            <a href="<?php echo BASE_URL; ?>modules/billing/fees_manage.php" class="btn btn-secondary">
                <i class="fas fa-money-bill-wave"></i> Manage Standard Fees
            </a>
            <a href="<?php echo BASE_URL; ?>modules/billing/tuition_rates.php" class="btn btn-secondary">
                <i class="fas fa-calculator"></i> Tuition Rate Setup
            </a>
        </div>
    </div>

    <div class="filters-card">
        <h2>Filters</h2>
        <form method="GET" action="" id="filterForm">
            <div class="form-group">
                <label for="search">Search</label>
                <input type="text" id="search" name="search" class="form-control" 
                       placeholder="Invoice #, Student Number, or Name" 
                       value="<?php echo sanitizeOutput($search); ?>"
                       autocomplete="off">
            </div>
            <div class="form-group">
                <label for="department_id">Department</label>
                <select id="department_id" name="department_id" class="form-control">
                    <option value="">All Departments</option>
                    <?php foreach ($departments as $dept): ?>
                        <option value="<?php echo $dept['department_id']; ?>" 
                                <?php echo ($department_id == $dept['department_id']) ? 'selected' : ''; ?>>
                            <?php echo sanitizeOutput($dept['department_name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label for="program_id">Program</label>
                <select id="program_id" name="program_id" class="form-control">
                    <option value="">All Programs</option>
                    <?php foreach ($programs as $prog): ?>
                        <option value="<?php echo $prog['program_id']; ?>" 
                                <?php echo ($program_id == $prog['program_id']) ? 'selected' : ''; ?>>
                            <?php echo sanitizeOutput($prog['program_name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <a href="<?php echo BASE_URL; ?>modules/billing/index.php" class="btn btn-secondary">
                    <i class="fas fa-times"></i> Clear
                </a>
            </div>
        </form>
    </div>

    <div class="table-card">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem; padding: 1.5rem 1.5rem 0;">
            <h2 style="margin: 0; font-size: 1.25rem;">Invoices (<?php echo count($invoices); ?>)</h2>
        </div>
        <div class="table-responsive">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Invoice #</th>
                        <th>Student</th>
                        <th>Department</th>
                        <th>Program</th>
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
                    <?php if (empty($invoices)): ?>
                        <tr><td colspan="11" class="text-center">No invoices found.</td></tr>
                    <?php else: ?>
                        <?php foreach ($invoices as $inv): 
                            $total_amount = (float)($inv['total_amount'] ?? 0);
                            $total_paid = (float)($inv['total_paid'] ?? $inv['paid_amount'] ?? 0);
                            $balance = $total_amount - $total_paid;
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
                        ?>
                        <tr data-invoice-number="<?php echo strtolower(sanitizeOutput($inv['invoice_number'] ?? '')); ?>"
                            data-student-number="<?php echo strtolower(sanitizeOutput($inv['student_number'] ?? '')); ?>"
                            data-student-name="<?php echo strtolower(sanitizeOutput($inv['first_name'] . ' ' . $inv['last_name'])); ?>"
                            data-department-id="<?php echo $inv['department_id'] ?? ''; ?>"
                            data-program-id="<?php echo $inv['program_id'] ?? ''; ?>"
                            data-department-name="<?php echo strtolower(sanitizeOutput($inv['department_name'] ?? '')); ?>"
                            data-program-name="<?php echo strtolower(sanitizeOutput($inv['program_name'] ?? '')); ?>">
                            <td><strong><?php echo sanitizeOutput($inv['invoice_number'] ?? 'N/A'); ?></strong></td>
                            <td>
                                <div style="max-width: 250px;">
                                    <strong><?php echo sanitizeOutput($inv['student_number']); ?></strong><br>
                                    <small style="color: #64748b;"><?php echo sanitizeOutput($inv['first_name'] . ' ' . $inv['last_name']); ?></small>
                                </div>
                            </td>
                            <td>
                                <div style="max-width: 200px;"><?php echo sanitizeOutput($inv['department_name'] ?? 'N/A'); ?></div>
                            </td>
                            <td>
                                <div style="max-width: 250px;"><?php echo sanitizeOutput($inv['program_name'] ?? 'N/A'); ?></div>
                            </td>
                            <td><?php echo date('M d, Y', strtotime($inv['created_at'])); ?></td>
                            <td><?php echo date('M d, Y', strtotime($inv['due_date'])); ?></td>
                            <td>₱<?php echo number_format($total_amount, 2); ?></td>
                            <td>₱<?php echo number_format($total_paid, 2); ?></td>
                            <td>₱<?php echo number_format($balance, 2); ?></td>
                            <td><span class="badge badge-<?php echo $status_badge; ?>">
                                <?php echo sanitizeOutput($status); ?>
                            </span></td>
                            <td class="actions">
                                <a href="<?php echo BASE_URL; ?>modules/billing/invoice_edit_discounts.php?id=<?php echo $inv['invoice_id']; ?>" class="btn btn-sm btn-warning" title="Edit Invoice">
                                    <i class="fas fa-edit"></i><span class="btn-text">Edit</span>
                                </a>
                                <a href="<?php echo BASE_URL; ?>modules/billing/invoice_edit.php?id=<?php echo $inv['invoice_id']; ?>" class="btn btn-sm btn-primary" title="Update Status">
                                    <i class="fas fa-dollar-sign"></i><span class="btn-text">Status</span>
                                </a>
                                <a href="<?php echo BASE_URL; ?>modules/billing/statement.php?id=<?php echo $inv['invoice_id']; ?>" class="btn btn-sm btn-info" target="_blank" title="View Statement">
                                    <i class="fas fa-file-invoice"></i><span class="btn-text">View</span>
                                </a>
                                <?php 
                                // Only show delete button if invoice has no payments and is not paid
                                $can_delete = ($total_paid == 0 && $status !== 'Paid');
                                if ($can_delete): 
                                ?>
                                <a href="<?php echo BASE_URL; ?>modules/billing/invoice_delete.php?id=<?php echo $inv['invoice_id']; ?>" 
                                   class="btn btn-sm btn-danger" 
                                   onclick="return confirm('Are you sure you want to delete invoice <?php echo htmlspecialchars($inv['invoice_number']); ?>? This action cannot be undone.');"
                                   title="Delete Invoice">
                                    <i class="fas fa-trash"></i><span class="btn-text">Delete</span>
                                </a>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const departmentSelect = document.getElementById('department_id');
    const programSelect = document.getElementById('program_id');
    const searchInput = document.getElementById('search');
    const filterForm = document.getElementById('filterForm');
    const invoiceTable = document.querySelector('.data-table tbody');
    const invoiceCountDisplay = document.querySelector('.card > div > h2');
    
    // Store all programs with their department IDs
    <?php 
    // Get all programs for JavaScript filtering
    $all_programs_query = $program_status_exists
        ? "SELECT program_id, program_name, department_id FROM programs WHERE status = 'Active' ORDER BY program_name"
        : "SELECT program_id, program_name, department_id FROM programs ORDER BY program_name";
    $all_programs_stmt = $pdo->query($all_programs_query);
    $all_programs_js = $all_programs_stmt ? $all_programs_stmt->fetchAll() : [];
    ?>
    const allPrograms = <?php echo json_encode($all_programs_js, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>;
    
    // Function to filter programs based on selected department
    function filterProgramsByDepartment() {
        if (!departmentSelect || !programSelect) {
            return;
        }
        
        const selectedDepartmentId = departmentSelect.value;
        const currentProgramValue = programSelect.value;
        
        // Clear and rebuild program options
        programSelect.innerHTML = '<option value="">All Programs</option>';
        
        // Add filtered programs
        allPrograms.forEach(function(program) {
            if (!selectedDepartmentId || selectedDepartmentId === '' || program.department_id == selectedDepartmentId) {
                const option = document.createElement('option');
                option.value = program.program_id;
                option.textContent = program.program_name;
                
                // Restore selection if it matches
                if (program.program_id == currentProgramValue) {
                    option.selected = true;
                }
                
                programSelect.appendChild(option);
            }
        });
        
        // If current selected program was removed, clear selection
        if (currentProgramValue && !programSelect.querySelector(`option[value="${currentProgramValue}"]`)) {
            programSelect.value = '';
        }
        
        // Trigger live filter after program dropdown updates
        applyLiveFilter();
    }
    
    // Live search and filter function
    function applyLiveFilter() {
        if (!invoiceTable) {
            return;
        }
        
        const searchTerm = searchInput ? searchInput.value.toLowerCase().trim() : '';
        const selectedDepartmentId = departmentSelect ? departmentSelect.value : '';
        const selectedProgramId = programSelect ? programSelect.value : '';
        
        const rows = invoiceTable.querySelectorAll('tr');
        let visibleCount = 0;
        
        rows.forEach(function(row) {
            // Skip the "No invoices found" row
            if (row.querySelector('td[colspan]')) {
                return;
            }
            
            const invoiceNumber = row.getAttribute('data-invoice-number') || '';
            const studentNumber = row.getAttribute('data-student-number') || '';
            const studentName = row.getAttribute('data-student-name') || '';
            const departmentId = row.getAttribute('data-department-id') || '';
            const programId = row.getAttribute('data-program-id') || '';
            
            // Check search filter
            let matchesSearch = true;
            if (searchTerm) {
                matchesSearch = invoiceNumber.includes(searchTerm) || 
                               studentNumber.includes(searchTerm) || 
                               studentName.includes(searchTerm);
            }
            
            // Check department filter
            let matchesDepartment = true;
            if (selectedDepartmentId && selectedDepartmentId !== '') {
                matchesDepartment = departmentId === selectedDepartmentId;
            }
            
            // Check program filter
            let matchesProgram = true;
            if (selectedProgramId && selectedProgramId !== '') {
                matchesProgram = programId === selectedProgramId;
            }
            
            // Show/hide row based on all filters
            if (matchesSearch && matchesDepartment && matchesProgram) {
                row.style.display = '';
                visibleCount++;
            } else {
                row.style.display = 'none';
            }
        });
        
        // Update invoice count
        if (invoiceCountDisplay) {
            invoiceCountDisplay.textContent = `Invoices (${visibleCount})`;
        }
        
        // Show "No invoices found" message if no rows are visible
        const noResultsRow = invoiceTable.querySelector('tr td[colspan]');
        if (visibleCount === 0 && rows.length > 0) {
            if (!noResultsRow) {
                const tr = document.createElement('tr');
                tr.innerHTML = '<td colspan="11" class="text-center">No invoices found matching your filters.</td>';
                invoiceTable.appendChild(tr);
            } else {
                noResultsRow.parentElement.style.display = '';
            }
        } else if (noResultsRow) {
            noResultsRow.parentElement.style.display = 'none';
        }
    }
    
    // Debounce function for search input
    let searchTimeout;
    function debounceSearch(func, delay) {
        clearTimeout(searchTimeout);
        searchTimeout = setTimeout(func, delay);
    }
    
    // Add event listeners for live filtering
    if (searchInput) {
        searchInput.addEventListener('input', function() {
            debounceSearch(applyLiveFilter, 300); // Wait 300ms after user stops typing
        });
    }
    
    if (departmentSelect) {
        departmentSelect.addEventListener('change', function() {
            filterProgramsByDepartment();
        });
    }
    
    if (programSelect) {
        programSelect.addEventListener('change', function() {
            applyLiveFilter();
        });
    }
    
    // Prevent form submission on Enter key (for live search)
    if (filterForm) {
        filterForm.addEventListener('submit', function(e) {
            e.preventDefault();
            applyLiveFilter();
        });
    }
    
    // Initialize on page load
    filterProgramsByDepartment();
    applyLiveFilter();
});
</script>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
