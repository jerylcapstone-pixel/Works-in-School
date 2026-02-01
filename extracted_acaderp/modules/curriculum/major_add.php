<?php
require_once __DIR__ . '/../../config/config.php';
requireAuth(ROLE_ADMIN);
$page_title = 'Add Major';
$pdo = getDBConnection();
$error = '';

$departments = [];
$stmt = $pdo->query("SELECT department_id, department_name, department_code FROM departments ORDER BY department_name");
$departments = $stmt->fetchAll();

$programs = [];
$stmt = $pdo->query("SELECT program_id, program_name, program_code, department_id FROM programs WHERE status = 'Active' ORDER BY program_name");
$programs = $stmt->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $program_id = !empty($_POST['program_id']) ? (int)$_POST['program_id'] : null;
    $major_name = sanitizeInput($_POST['major_name'] ?? '');
    $description = sanitizeInput($_POST['description'] ?? '');
    $status = 'Active'; // New majors are always set to Active

    if (empty($program_id) || empty($major_name)) {
        $error = 'Please fill in all required fields.';
    } else {
        try {
            // Check if major already exists for this program
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM majors WHERE program_id = ? AND major_name = ?");
            $stmt->execute([$program_id, $major_name]);
            if ($stmt->fetchColumn() > 0) {
                $error = 'This major already exists for the selected program.';
            } else {
                $pdo->beginTransaction();
                
                // Get next major_id to generate code before insert
                $stmt = $pdo->query("SELECT COALESCE(MAX(major_id), 0) + 1 as next_id FROM majors");
                $result = $stmt->fetch();
                $next_id = $result['next_id'];
                
                // Generate auto-increment major code: MAJOR-001, MAJOR-002, etc.
                $major_code = 'MAJOR-' . str_pad($next_id, 3, '0', STR_PAD_LEFT);
                
                $sql = "INSERT INTO majors (program_id, major_name, major_code, description, status)
                        VALUES (?, ?, ?, ?, ?)";
                $stmt = $pdo->prepare($sql);
                if ($stmt->execute([$program_id, $major_name, $major_code, $description ?: null, $status])) {
                    $pdo->commit();
                    header('Location: ' . BASE_URL . 'modules/curriculum/index.php?tab=majors&success=Major added successfully with code: ' . $major_code);
                    exit;
                } else {
                    $pdo->rollBack();
                    $error = 'Error adding major.';
                }
            }
        } catch (PDOException $e) {
            if ($e->getCode() == '42S02') {
                // Table doesn't exist
                $error = 'The majors table has not been created yet. Please run the database migration: database/migration_add_majors_table.sql';
            } else {
                if (isset($pdo) && $pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                $error = 'Error adding major: ' . $e->getMessage();
            }
        } catch (Exception $e) {
            if (isset($pdo) && $pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $error = 'Error adding major: ' . $e->getMessage();
        }
    }
}

include __DIR__ . '/../../includes/header.php';
?>

<div class="page-container">
    <div class="page-header">
        <h1><i class="fas fa-tag"></i> Add Major</h1>
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
                                    <?php echo (($_POST['department_id'] ?? '') == $dept['department_id']) ? 'selected' : ''; ?>>
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
                                    <?php echo (($_POST['program_id'] ?? '') == $prog['program_id']) ? 'selected' : ''; ?>>
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
                           value="<?php echo sanitizeOutput($_POST['major_name'] ?? ''); ?>"
                           placeholder="e.g., English, Mathematics, Science">
                </div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label>Major Code</label>
                    <input type="text" class="form-control" value="Auto-generated" readonly>
                    <small class="text-muted">Major code will be automatically generated</small>
                </div>
            </div>

            <div class="form-group">
                <label>Description</label>
                <textarea name="description" class="form-control" rows="4"><?php echo sanitizeOutput($_POST['description'] ?? ''); ?></textarea>
            </div>

            <div class="form-actions">
                <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Add Major</button>
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
                
                // Restore selected program if it exists (from form error)
                const selectedProgramId = '<?php echo (int)($_POST['program_id'] ?? 0); ?>';
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

