<?php
require_once __DIR__ . '/../../config/config.php';
requireAuth(ROLE_ADMIN);
$page_title = 'Edit Course';
$course_id = (int)($_GET['id'] ?? 0);
if (!$course_id) {
    header('Location: ' . BASE_URL . 'modules/curriculum/index.php?tab=courses&error=Invalid course ID');
    exit;
}

$pdo = getDBConnection();
$error = '';
$stmt = $pdo->prepare("SELECT * FROM courses WHERE course_id = ?");
$stmt->execute([$course_id]);
$course = $stmt->fetch();
if (!$course) {
    header('Location: ' . BASE_URL . 'modules/curriculum/index.php?tab=courses&error=Course not found');
    exit;
}

$departments = [];
$stmt = $pdo->query("SELECT department_id, department_name, department_code FROM departments ORDER BY department_name");
$departments = $stmt->fetchAll();

$programs = [];
try {
    $stmt = $pdo->query("SELECT program_id, program_name, program_code, department_id FROM programs WHERE status = 'Active' ORDER BY program_name");
    $programs = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Ensure all programs have required fields - filter out any invalid entries
    $programs = array_filter($programs, function($prog) {
        return isset($prog['program_id']) && isset($prog['program_name']) && !empty($prog['program_id']);
    });
    // Re-index array after filtering
    $programs = array_values($programs);
    
    // If no active programs found, try to get all programs (for rendering in dropdown)
    if (empty($programs)) {
        try {
            $allPrograms = $pdo->query("SELECT program_id, program_name, program_code, department_id FROM programs ORDER BY program_name")->fetchAll(PDO::FETCH_ASSOC);
            if (count($allPrograms) > 0) {
                // Filter to ensure valid entries
                $programs = array_filter($allPrograms, function($prog) {
                    return isset($prog['program_id']) && isset($prog['program_name']) && !empty($prog['program_id']);
                });
                $programs = array_values($programs);
            }
        } catch (PDOException $e) {
            error_log("Error fetching all programs: " . $e->getMessage());
            $programs = [];
        }
    }
} catch (PDOException $e) {
    error_log("Error fetching programs: " . $e->getMessage());
    $programs = [];
}

// Check if major_id column exists in courses table
$major_column_exists = false;
try {
    $check_stmt = $pdo->query("SHOW COLUMNS FROM courses LIKE 'major_id'");
    $major_column_exists = $check_stmt->rowCount() > 0;
} catch (PDOException $e) {
    $major_column_exists = false;
}

// Fetch all majors with their program and department info (only if column exists)
$majors = [];
if ($major_column_exists) {
    try {
        $stmt = $pdo->query("SELECT m.major_id, m.major_name, m.program_id, p.department_id 
                             FROM majors m
                             LEFT JOIN programs p ON m.program_id = p.program_id
                             WHERE m.status = 'Active' 
                             ORDER BY p.department_id, p.program_name, m.major_name");
        $majors = $stmt->fetchAll();
    } catch (PDOException $e) {
        // If majors table doesn't exist yet, just continue with empty array
        $majors = [];
    }
}

// Get current program association for this course
$current_program_id = null;
$stmt = $pdo->prepare("SELECT program_id FROM program_courses WHERE course_id = ? LIMIT 1");
$stmt->execute([$course_id]);
$program_course = $stmt->fetch();
if ($program_course) {
    $current_program_id = $program_course['program_id'];
}

// Get current major_id from course (if column exists)
$current_major_id = null;
if ($major_column_exists) {
    $current_major_id = $course['major_id'] ?? null;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $course_name = sanitizeInput($_POST['course_name'] ?? '');
    $department_id = !empty($_POST['department_id']) ? (int)$_POST['department_id'] : null;
    $program_id = !empty($_POST['program_id']) ? (int)$_POST['program_id'] : null;
    $major_id = !empty($_POST['major_id']) ? (int)$_POST['major_id'] : null;
    $credits = (int)($_POST['credits'] ?? 0);
    $course_type = sanitizeInput($_POST['course_type'] ?? 'Lecture');
    $description = sanitizeInput($_POST['description'] ?? '');
    $status = sanitizeInput($_POST['status'] ?? 'Active');

    if (empty($course_name) || $credits <= 0 || !$department_id) {
        $error = 'Please fill in all required fields.';
    } else {
        try {
            $pdo->beginTransaction();
            
            // Update course - build SQL dynamically based on whether major_id column exists
            if ($major_column_exists) {
                $sql = "UPDATE courses SET course_name = ?, department_id = ?, credits = ?, 
                        course_type = ?, description = ?, status = ?, major_id = ? WHERE course_id = ?";
                $params = [$course_name, $department_id, $credits, $course_type, $description ?: null, $status, $major_id, $course_id];
        } else {
                $sql = "UPDATE courses SET course_name = ?, department_id = ?, credits = ?, 
                        course_type = ?, description = ?, status = ? WHERE course_id = ?";
                $params = [$course_name, $department_id, $credits, $course_type, $description ?: null, $status, $course_id];
            }
            $stmt = $pdo->prepare($sql);
            if ($stmt->execute($params)) {
                // Handle program association
                // Check if program association already exists
                $stmt = $pdo->prepare("SELECT program_id FROM program_courses WHERE course_id = ? LIMIT 1");
                $stmt->execute([$course_id]);
                $existing_program = $stmt->fetch();
                
                if ($program_id) {
                    // Program is selected
                    if ($existing_program) {
                        // Update existing association
                        if ($existing_program['program_id'] != $program_id) {
                            $stmt = $pdo->prepare("UPDATE program_courses SET program_id = ? WHERE course_id = ?");
                            $stmt->execute([$program_id, $course_id]);
                        }
                    } else {
                        // Create new association
                        $stmt = $pdo->prepare("INSERT INTO program_courses (program_id, course_id) VALUES (?, ?)");
                        $stmt->execute([$program_id, $course_id]);
                    }
        } else {
                    // Program is not selected - remove association if exists
                    if ($existing_program) {
                        $stmt = $pdo->prepare("DELETE FROM program_courses WHERE course_id = ?");
                        $stmt->execute([$course_id]);
                    }
                }
                
                $pdo->commit();
                header('Location: ' . BASE_URL . 'modules/curriculum/index.php?tab=courses&success=Course updated successfully');
                exit;
            } else {
                $pdo->rollBack();
                $error = 'Error updating course.';
            }
        } catch (Exception $e) {
            $pdo->rollBack();
            $error = 'Error updating course: ' . $e->getMessage();
        }
    }
    $course = array_merge($course, $_POST);
    $current_program_id = $program_id; // Update for display
}

include __DIR__ . '/../../includes/header.php';
?>

<div class="page-container">
    <div class="page-header">
        <h1><i class="fas fa-edit"></i> Edit Course</h1>
        <a href="<?php echo BASE_URL; ?>modules/curriculum/index.php?tab=courses" class="btn btn-secondary">
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
                    <label>Course Code</label>
                    <input type="text" class="form-control" 
                           value="<?php echo sanitizeOutput($course['course_code']); ?>" 
                           readonly style="background-color: #f5f5f5; cursor: not-allowed;">
                    <small class="text-muted">Course code is auto-generated and cannot be changed</small>
                </div>
                <div class="form-group">
                    <label>Course Name <span class="required">*</span></label>
                    <input type="text" name="course_name" required class="form-control" 
                           value="<?php echo sanitizeOutput($course['course_name']); ?>">
                </div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label>Department <span class="required">*</span></label>
                    <select name="department_id" id="department_id" class="form-control" required onchange="filterPrograms(); updateMajors();">
                        <option value="">Select Department</option>
                        <?php foreach ($departments as $dept): ?>
                            <option value="<?php echo $dept['department_id']; ?>"
                                    <?php echo (($course['department_id'] ?? '') == $dept['department_id']) ? 'selected' : ''; ?>>
                                <?php echo sanitizeOutput($dept['department_name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>Program (Optional)</label>
                    <input type="text" id="program_search" class="form-control" placeholder="Search programs..." style="margin-bottom: 8px;">
                    <select name="program_id" id="program_id" class="form-control" onchange="updateMajors();" data-search-input="program_search" data-no-results="program_search_empty">
                        <option value="">Select Program</option>
                        <?php if (!empty($programs)): ?>
                            <?php foreach ($programs as $prog): ?>
                                <?php 
                                // Ensure we have valid data
                                if (!isset($prog['program_id']) || !isset($prog['program_name'])) {
                                    continue;
                                }
                                $progId = (int)$prog['program_id'];
                                $deptId = isset($prog['department_id']) && $prog['department_id'] ? (int)$prog['department_id'] : '';
                                $progName = isset($prog['program_name']) ? $prog['program_name'] : 'Unknown Program';
                                ?>
                                <option value="<?php echo $progId; ?>"
                                        data-department-id="<?php echo $deptId; ?>"
                                        <?php echo (($current_program_id ?? '') == $progId) ? 'selected' : ''; ?>>
                                    <?php echo sanitizeOutput($progName); ?>
                                </option>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <option value="" disabled>No programs available</option>
                        <?php endif; ?>
                    </select>
                    <small id="program_search_empty" class="text-warning" style="display:none;">No programs match your search.</small>
                    <small class="text-muted">Leave blank for minor/general courses</small>
                </div>
                <div class="form-group">
                    <label>Major (Optional)</label>
                    <input type="text" id="major_search" class="form-control" placeholder="Search majors..." style="margin-bottom: 8px;">
                    <select name="major_id" id="major_id" class="form-control" data-search-input="major_search" data-no-results="major_search_empty">
                        <option value="">Select Major</option>
                        <?php if ($major_column_exists && !empty($majors)): ?>
                            <?php foreach ($majors as $major): ?>
                                <option value="<?php echo $major['major_id']; ?>"
                                        data-program-id="<?php echo $major['program_id']; ?>"
                                        data-department-id="<?php echo $major['department_id']; ?>"
                                        <?php echo (($current_major_id ?? '') == $major['major_id']) ? 'selected' : ''; ?>>
                                    <?php echo sanitizeOutput($major['major_name']); ?>
                                </option>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <option value="" disabled>No majors available</option>
                        <?php endif; ?>
                    </select>
                    <small id="major_search_empty" class="text-warning" style="display:none;">No majors match your search.</small>
                    <small class="text-muted">Leave blank if not applicable</small>
                    <?php if (!$major_column_exists): ?>
                        <small class="text-warning" style="display: block; margin-top: 0.25rem;">
                            <i class="fas fa-info-circle"></i> Major support requires running the migration: migration_add_major_to_courses.sql
                        </small>
                    <?php endif; ?>
                </div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label>Credits <span class="required">*</span></label>
                    <input type="number" name="credits" required class="form-control" min="1" max="6"
                           value="<?php echo $course['credits']; ?>">
                </div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label>Course Type</label>
                    <select name="course_type" class="form-control" required>
                        <?php
                        $currentType = $course['course_type'] ?? 'Lecture';
                        $course_types = [
                            'Lecture' => 'Lecture',
                            'Laboratory' => 'Laboratory',
                            'Seminar' => 'Seminar',
                            'Workshop' => 'Workshop',
                            'Online' => 'Online',
                            'Hybrid' => 'Hybrid'
                        ];
                        foreach ($course_types as $value => $label):
                            $isSelected = strcasecmp($currentType, $value) === 0;
                        ?>
                            <option value="<?php echo $value; ?>" <?php echo $isSelected ? 'selected' : ''; ?>>
                                <?php echo sanitizeOutput($label); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>Status</label>
                    <select name="status" class="form-control" required>
                        <option value="Active" <?php echo ($course['status'] === 'Active') ? 'selected' : ''; ?>>Active</option>
                        <option value="Inactive" <?php echo ($course['status'] === 'Inactive') ? 'selected' : ''; ?>>Inactive</option>
                        <option value="Archived" <?php echo ($course['status'] === 'Archived') ? 'selected' : ''; ?>>Archived</option>
                    </select>
                </div>
            </div>

            <div class="form-group">
                <label>Description</label>
                <textarea name="description" class="form-control" rows="4"><?php echo sanitizeOutput($course['description'] ?? ''); ?></textarea>
            </div>

            <div class="form-actions">
                <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Update Course</button>
                <a href="<?php echo BASE_URL; ?>modules/curriculum/index.php?tab=courses" class="btn btn-secondary">Cancel</a>
            </div>
        </form>
    </div>
</div>

<script>
    function getSearchInputFor(select) {
        if (!select) {
            return null;
        }
        const inputId = select.getAttribute('data-search-input');
        return inputId ? document.getElementById(inputId) : null;
    }

    function applySearchFilter(select) {
        if (!select) {
            return;
        }

        const searchInput = getSearchInputFor(select);
        const term = searchInput ? searchInput.value.trim().toLowerCase() : '';
        let visibleCount = 0;

        Array.from(select.options).forEach(function(option) {
            if (!option.value) {
                option.hidden = false;
                return;
            }

            const matches = !term || option.textContent.toLowerCase().includes(term);
            const shouldHide = !matches && option.value !== select.value;
            option.hidden = shouldHide;

            if (!shouldHide) {
                visibleCount++;
            }
        });

        const messageId = select.getAttribute('data-no-results');
        if (messageId) {
            const messageEl = document.getElementById(messageId);
            if (messageEl) {
                messageEl.style.display = visibleCount === 0 && term.length > 0 ? 'block' : 'none';
            }
        }
    }

    function setupSelectSearch(select) {
        if (!select) {
            return;
        }
        const searchInput = getSearchInputFor(select);
        if (searchInput) {
            searchInput.addEventListener('input', function() {
                applySearchFilter(select);
            });
        }
        applySearchFilter(select);
    }

    // Store all programs initially
    let allProgramsData = [];
    
    // Filter programs based on selected department
    function filterPrograms() {
        const departmentSelect = document.getElementById('department_id');
        const programSelect = document.getElementById('program_id');
        const selectedDepartmentId = departmentSelect ? departmentSelect.value : '';
        
        if (!programSelect) {
            console.log('Program select not found');
            return;
        }
        
        // Store all programs on first load if not already stored
        if (allProgramsData.length === 0) {
            const allOptions = programSelect.querySelectorAll('option');
            console.log('Found program options:', allOptions.length);
            
            allOptions.forEach(function(option) {
                // Only store options that have a value (skip placeholder and disabled options)
                if (option.value && option.value !== '' && option.value !== '0' && !option.disabled) {
                    // Get department ID from data attribute, default to empty string if not set
                    let deptId = option.getAttribute('data-department-id');
                    if (deptId === null || deptId === '') {
                        deptId = '';
                    }
                    allProgramsData.push({
                        value: option.value,
                        text: option.textContent.trim(),
                        departmentId: deptId
                    });
                    console.log(`Stored program: value="${option.value}", text="${option.textContent.trim()}", deptId="${deptId}"`);
                }
            });
            
            console.log('Total programs stored:', allProgramsData.length);
            
            // If no programs were stored, there might be an issue with PHP rendering
            if (allProgramsData.length === 0) {
                console.warn('WARNING: No programs found in dropdown! Check if programs exist in database and PHP query is correct.');
                console.warn('Program select HTML:', programSelect.innerHTML);
            }
        }
        
        // Save current selection if it exists
        const currentSelection = programSelect.value;
        
        // Clear current selection but keep the default option
        programSelect.innerHTML = '<option value="">Select Program</option>';
        
        if (!selectedDepartmentId) {
            applySearchFilter(programSelect);
            return;
        }
        
        // Filter and show only programs that belong to the selected department
        const selectedDeptIdStr = String(selectedDepartmentId);
        let hasPrograms = false;
        
        allProgramsData.forEach(function(program) {
            // Normalize department IDs for comparison
            const programDeptId = program.departmentId || '';
            const programDeptIdStr = String(programDeptId);
            
            console.log('Checking program:', program.text, 'dept:', programDeptIdStr, 'selected:', selectedDeptIdStr, 'match:', programDeptIdStr === selectedDeptIdStr);
            
            // Match only if department IDs match exactly (both as strings)
            if (programDeptIdStr === selectedDeptIdStr) {
                const option = document.createElement('option');
                option.value = program.value;
                option.textContent = program.text;
                option.setAttribute('data-department-id', programDeptIdStr);
                // Restore selection if it matches
                if (program.value == currentSelection) {
                    option.selected = true;
                }
                programSelect.appendChild(option);
                hasPrograms = true;
            }
        });
        
        console.log('Found programs:', hasPrograms, 'Total options:', programSelect.options.length);
        
        // If no programs found, show message
        if (!hasPrograms && programSelect.options.length === 1) {
            const option = document.createElement('option');
            option.value = '';
            option.textContent = 'No programs available for this department';
            option.disabled = true;
            programSelect.appendChild(option);
        }
        
        applySearchFilter(programSelect);

        // Update majors when programs change
        updateMajors();
    }
    
    // Store all majors initially
    let allMajorsData = [];
    
    // Filter majors based on selected department and program
    function updateMajors() {
        const departmentSelect = document.getElementById('department_id');
        const programSelect = document.getElementById('program_id');
        const majorSelect = document.getElementById('major_id');
        
        if (!majorSelect) return;
        
        const selectedDepartmentId = departmentSelect ? departmentSelect.value : '';
        const selectedProgramId = programSelect ? programSelect.value : '';
        
        // Store all majors on first load if not already stored
        if (allMajorsData.length === 0) {
            const allOptions = majorSelect.querySelectorAll('option[data-department-id]');
            allOptions.forEach(function(option) {
                if (option.value) { // Skip the "Select Major" option
                    allMajorsData.push({
                        value: option.value,
                        text: option.textContent,
                        programId: option.getAttribute('data-program-id'),
                        departmentId: option.getAttribute('data-department-id')
                    });
                }
            });
        }
        
        // Clear current selection but keep the default option
        const defaultOption = majorSelect.querySelector('option[value=""]');
        majorSelect.innerHTML = '';
        if (defaultOption) {
            majorSelect.appendChild(defaultOption.cloneNode(true));
        } else {
            majorSelect.innerHTML = '<option value="">Select Major</option>';
        }
        
        if (!selectedDepartmentId) {
            // No department selected, show all majors
            allMajorsData.forEach(function(major) {
                const option = document.createElement('option');
                option.value = major.value;
                option.textContent = major.text;
                option.setAttribute('data-program-id', major.programId || '');
                option.setAttribute('data-department-id', major.departmentId || '');
                majorSelect.appendChild(option);
            });
            applySearchFilter(majorSelect);
            return;
        }
        
        // Filter majors based on department and program
        let hasMajors = false;
        allMajorsData.forEach(function(major) {
            const majorDeptId = major.departmentId;
            const majorProgramId = major.programId;
            
            // Must match department
            if (majorDeptId == selectedDepartmentId) {
                // If program is selected, show only majors for that program
                // If program is not selected, show all majors from the department
                if (!selectedProgramId || majorProgramId == selectedProgramId) {
                    const option = document.createElement('option');
                    option.value = major.value;
                    option.textContent = major.text;
                    option.setAttribute('data-program-id', majorProgramId || '');
                    option.setAttribute('data-department-id', majorDeptId || '');
                    majorSelect.appendChild(option);
                    hasMajors = true;
                }
            }
        });
        
        // If no majors found, show message
        if (!hasMajors) {
            if (selectedProgramId) {
                majorSelect.innerHTML = '<option value="">No majors available for this program</option>';
            } else {
                majorSelect.innerHTML = '<option value="">No majors available for this department</option>';
            }
        }
        applySearchFilter(majorSelect);
    }
    
    // Initialize on page load
    document.addEventListener('DOMContentLoaded', function() {
        // Initialize filtering
        const departmentSelect = document.getElementById('department_id');
        const programSelect = document.getElementById('program_id');
        const majorSelect = document.getElementById('major_id');
        setupSelectSearch(programSelect);
        setupSelectSearch(majorSelect);
        
        if (departmentSelect && programSelect) {
            // Store all programs first (before any filtering)
            if (allProgramsData.length === 0) {
                const allOptions = programSelect.querySelectorAll('option');
                console.log('Found program options:', allOptions.length);
                
                allOptions.forEach(function(option) {
                    // Only store options that have a value (skip placeholder and disabled options)
                    if (option.value && option.value !== '' && option.value !== '0' && !option.disabled) {
                        // Get department ID from data attribute, default to empty string if not set
                        let deptId = option.getAttribute('data-department-id');
                        if (deptId === null || deptId === '') {
                            deptId = '';
                        }
                        allProgramsData.push({
                            value: option.value,
                            text: option.textContent.trim(),
                            departmentId: deptId
                        });
                        console.log(`Stored program: value="${option.value}", text="${option.textContent.trim()}", deptId="${deptId}"`);
                    }
                });
                
                console.log('Total programs stored:', allProgramsData.length);
            }
            
            const selectedDept = departmentSelect.value;
            
            // If department is selected, filter programs
            if (selectedDept) {
                filterPrograms();
            } else {
                // No department selected, clear programs dropdown but keep programs stored
                programSelect.innerHTML = '<option value="">Select Program</option>';
                updateMajors();
            }
            
            // Restore selected program if it exists
            const selectedProgramId = '<?php echo (int)($current_program_id ?? 0); ?>';
            if (selectedProgramId && selectedDept) {
                // Wait a bit for filterPrograms to complete
                setTimeout(function() {
                    const option = programSelect.querySelector('option[value="' + selectedProgramId + '"]');
                    if (option) {
                        programSelect.value = selectedProgramId;
                        // Update majors after program is restored
                        updateMajors();
                    }
                }, 50);
            }
            
            // Restore selected major if it exists
            const selectedMajorId = '<?php echo (int)($current_major_id ?? 0); ?>';
            if (selectedMajorId) {
                setTimeout(function() {
                    const majorSelect = document.getElementById('major_id');
                    if (majorSelect) {
                        const option = majorSelect.querySelector('option[value="' + selectedMajorId + '"]');
                        if (option) {
                            majorSelect.value = selectedMajorId;
                        }
                    }
                }, 100);
            }
        }
    });
</script>

<?php include __DIR__ . '/../../includes/footer.php'; ?>

