<?php
require_once __DIR__ . '/../../config/config.php';
requireAuth(ROLE_ADMIN);
$page_title = 'Edit Major';
$major_id = (int)($_GET['id'] ?? 0);
if (!$major_id) {
    header('Location: ' . BASE_URL . 'modules/curriculum/index.php?tab=majors&error=Invalid major ID');
    exit;
}

$pdo = getDBConnection();
$error = '';
$stmt = $pdo->prepare("SELECT * FROM majors WHERE major_id = ?");
$stmt->execute([$major_id]);
$major = $stmt->fetch();
if (!$major) {
    header('Location: ' . BASE_URL . 'modules/curriculum/index.php?tab=majors&error=Major not found');
    exit;
}

$departments = [];
$stmt = $pdo->query("SELECT department_id, department_name, department_code FROM departments ORDER BY department_name");
$departments = $stmt->fetchAll();

$programs = [];
$stmt = $pdo->query("SELECT program_id, program_name, program_code, department_id FROM programs WHERE status = 'Active' ORDER BY program_name");
$programs = $stmt->fetchAll();

// Get current program's department for the major
$current_program_id = $major['program_id'];
$current_department_id = null;
if ($current_program_id) {
    $stmt = $pdo->prepare("SELECT department_id FROM programs WHERE program_id = ?");
    $stmt->execute([$current_program_id]);
    $program = $stmt->fetch();
    if ($program) {
        $current_department_id = $program['department_id'];
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $program_id = !empty($_POST['program_id']) ? (int)$_POST['program_id'] : null;
    $major_name = sanitizeInput($_POST['major_name'] ?? '');
    $description = sanitizeInput($_POST['description'] ?? '');
    $status = sanitizeInput($_POST['status'] ?? 'Active');

    if (empty($program_id) || empty($major_name)) {
        $error = 'Please fill in all required fields.';
    } else {
        // Check if major already exists for this program (excluding current major)
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM majors WHERE program_id = ? AND major_name = ? AND major_id != ?");
        $stmt->execute([$program_id, $major_name, $major_id]);
        if ($stmt->fetchColumn() > 0) {
            $error = 'This major already exists for the selected program.';
        } else {
            // Major code is auto-generated, so we don't update it
            $sql = "UPDATE majors SET program_id = ?, major_name = ?, description = ?, status = ? WHERE major_id = ?";
            $stmt = $pdo->prepare($sql);
            if ($stmt->execute([$program_id, $major_name, $description ?: null, $status, $major_id])) {
                header('Location: ' . BASE_URL . 'modules/curriculum/index.php?tab=majors&success=Major updated successfully');
                exit;
            } else {
                $error = 'Error updating major.';
            }
        }
    }
    $major = array_merge($major, $_POST);
}

include __DIR__ . '/../../includes/header.php';
?>

<div class="page-container">
    <div class="page-header">
        <h1><i class="fas fa-edit"></i> Edit Major</h1>
        <a href="<?php echo BASE_URL; ?>modules/curriculum/index.php?tab=majors" class="btn btn-secondary">
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
                    <label>Department</label>
                    <select name="department_id" id="department_id" class="form-control" onchange="filterPrograms();">
                        <option value="">Select Department</option>
                        <?php foreach ($departments as $dept): ?>
                            <option value="<?php echo $dept['department_id']; ?>"
                                    <?php echo (($current_department_id ?? '') == $dept['department_id']) ? 'selected' : ''; ?>>
                                <?php echo sanitizeOutput($dept['department_name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>Program <span class="required">*</span></label>
                    <select name="program_id" id="program_id" required class="form-control">
                        <option value="">Select Program</option>
                        <?php foreach ($programs as $prog): ?>
                            <option value="<?php echo $prog['program_id']; ?>"
                                    data-department-id="<?php echo $prog['department_id']; ?>"
                                    <?php echo (($major['program_id'] ?? '') == $prog['program_id']) ? 'selected' : ''; ?>>
                                <?php echo sanitizeOutput($prog['program_name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label>Major Name <span class="required">*</span></label>
                    <input type="text" name="major_name" required class="form-control" 
                           value="<?php echo sanitizeOutput($major['major_name']); ?>">
                </div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label>Major Code</label>
                    <input type="text" class="form-control" value="<?php echo sanitizeOutput($major['major_code'] ?? ''); ?>" readonly>
                    <small class="text-muted">Major code is auto-generated and cannot be changed</small>
                </div>
                <div class="form-group">
                    <label>Status</label>
                    <select name="status" class="form-control" required>
                        <option value="Active" <?php echo ($major['status'] === 'Active') ? 'selected' : ''; ?>>Active</option>
                        <option value="Inactive" <?php echo ($major['status'] === 'Inactive') ? 'selected' : ''; ?>>Inactive</option>
                    </select>
                </div>
            </div>

            <div class="form-group">
                <label>Description</label>
                <textarea name="description" class="form-control" rows="4"><?php echo sanitizeOutput($major['description'] ?? ''); ?></textarea>
            </div>

            <div class="form-actions">
                <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Update Major</button>
                <a href="<?php echo BASE_URL; ?>modules/curriculum/index.php?tab=majors" class="btn btn-secondary">Cancel</a>
            </div>
        </form>
    </div>
</div>

<script>
    // Store all programs initially
    let allProgramsData = [];
    
    // Filter programs based on selected department
    function filterPrograms() {
        const departmentSelect = document.getElementById('department_id');
        const programSelect = document.getElementById('program_id');
        const selectedDepartmentId = departmentSelect ? departmentSelect.value : '';
        
        if (!programSelect) return;
        
        // Store all programs on first load if not already stored
        if (allProgramsData.length === 0) {
            const allOptions = programSelect.querySelectorAll('option[data-department-id]');
            allOptions.forEach(function(option) {
                if (option.value) { // Skip the "Select Program" option
                    allProgramsData.push({
                        value: option.value,
                        text: option.textContent,
                        departmentId: option.getAttribute('data-department-id')
                    });
                }
            });
        }
        
        // Clear current selection but keep the default option
        const defaultOption = programSelect.querySelector('option[value=""]');
        programSelect.innerHTML = '';
        if (defaultOption) {
            programSelect.appendChild(defaultOption.cloneNode(true));
        } else {
            programSelect.innerHTML = '<option value="">Select Program</option>';
        }
        
        if (!selectedDepartmentId) {
            // No department selected, show no programs
            programSelect.innerHTML = '<option value="">Select Program</option>';
            return;
        }
        
        // Filter and show only programs that belong to the selected department
        let hasPrograms = false;
        allProgramsData.forEach(function(program) {
            const programDeptId = program.departmentId;
            if (programDeptId == selectedDepartmentId) {
                // Include only programs that match the department
                const option = document.createElement('option');
                option.value = program.value;
                option.textContent = program.text;
                option.setAttribute('data-department-id', programDeptId || '');
                programSelect.appendChild(option);
                hasPrograms = true;
            }
        });
        
        // If no programs found, show message
        if (!hasPrograms) {
            programSelect.innerHTML = '<option value="">No programs available for this department</option>';
        }
    }
    
    // Initialize on page load
    document.addEventListener('DOMContentLoaded', function() {
        setTimeout(function() {
            // Initialize filtering
            const departmentSelect = document.getElementById('department_id');
            const programSelect = document.getElementById('program_id');
            
            if (departmentSelect && programSelect) {
                const selectedDept = departmentSelect.value;
                // Always filter programs on load
                filterPrograms();
                
                // Restore selected program if it exists
                const selectedProgramId = '<?php echo (int)($major['program_id'] ?? 0); ?>';
                if (selectedProgramId) {
                    const option = programSelect.querySelector('option[value="' + selectedProgramId + '"]');
                    if (option) {
                        programSelect.value = selectedProgramId;
                    }
                }
            }
        }, 100);
    });
</script>

<?php include __DIR__ . '/../../includes/footer.php'; ?>

