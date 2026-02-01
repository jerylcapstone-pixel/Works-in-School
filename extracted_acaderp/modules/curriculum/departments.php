<?php
/**
 * Curriculum Management - Departments Section
 * Lists and manages departments
 */

$search = sanitizeInput($_GET['search'] ?? '');

$query = "SELECT d.*, f.first_name as head_first, f.last_name as head_last
          FROM departments d
          LEFT JOIN faculty f ON d.head_faculty_id = f.faculty_id
          WHERE 1=1";
$params = [];

if (!empty($search)) {
    $query .= " AND (d.department_code LIKE ? OR d.department_name LIKE ?)";
    $searchParam = "%$search%";
    $params = array_merge($params, [$searchParam, $searchParam]);
}

$query .= " ORDER BY d.department_name";
$stmt = $pdo->prepare($query);
$stmt->execute($params);
$departments = $stmt->fetchAll();

// Get faculty for dropdown
$faculty_list = [];
$stmt = $pdo->query("SELECT faculty_id, first_name, last_name, employee_id FROM faculty WHERE status = 'Active' ORDER BY last_name");
$faculty_list = $stmt->fetchAll();
?>

<div style="padding: 1.5rem;">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem;">
        <h2 style="margin: 0;">Departments</h2>
        <a href="<?php echo BASE_URL; ?>modules/curriculum/department_add.php" class="btn btn-primary">
            <i class="fas fa-plus"></i> Add Department
        </a>
    </div>

    <form method="GET" action="" style="margin-bottom: 1.5rem;">
        <input type="hidden" name="tab" value="departments">
        <div style="display: flex; gap: 1rem;">
            <input type="text" name="search" placeholder="Search departments..." 
                   value="<?php echo sanitizeOutput($search); ?>" class="form-control" style="flex: 1;">
            <button type="submit" class="btn btn-secondary"><i class="fas fa-search"></i> Search</button>
            <a href="?tab=departments" class="btn btn-outline">Clear</a>
        </div>
    </form>

    <table class="data-table">
        <thead>
            <tr>
                <th>Logo</th>
                <th>Department Name</th>
                <th>Head of Department</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($departments)): ?>
            <tr>
                <td colspan="4" class="text-center">
                    <p>No departments found.</p>
                    <a href="<?php echo BASE_URL; ?>modules/curriculum/department_add.php" class="btn btn-primary">
                        <i class="fas fa-plus"></i> Add First Department
                    </a>
                </td>
            </tr>
            <?php else: ?>
                <?php foreach ($departments as $dept): ?>
                <tr>
                    <td style="text-align: center;">
                        <?php if (!empty($dept['logo_path'])): ?>
                            <img src="<?php echo BASE_URL . $dept['logo_path']; ?>" alt="Logo" 
                                 style="max-width: 60px; max-height: 60px; border: 1px solid #ddd; border-radius: 4px; padding: 5px;">
                        <?php else: ?>
                            <span style="color: #999; font-size: 12px;">No logo</span>
                        <?php endif; ?>
                    </td>
                    <td><strong><?php echo sanitizeOutput($dept['department_name']); ?></strong></td>
                    <td>
                        <?php if ($dept['head_first']): ?>
                            <?php echo sanitizeOutput($dept['head_first'] . ' ' . $dept['head_last']); ?>
                        <?php else: ?>
                            <span style="color: #999;">Not assigned</span>
                        <?php endif; ?>
                    </td>
                    <td class="actions">
                        <a href="<?php echo BASE_URL; ?>modules/curriculum/department_edit.php?id=<?php echo $dept['department_id']; ?>" 
                           class="btn btn-sm btn-warning" title="Edit">
                            <i class="fas fa-edit"></i>
                        </a>
                        <a href="<?php echo BASE_URL; ?>modules/curriculum/department_delete.php?id=<?php echo $dept['department_id']; ?>" 
                           class="btn btn-sm btn-danger"
                           onclick="return confirm('Are you sure? This may affect related programs and courses.');"
                           title="Delete">
                            <i class="fas fa-trash"></i>
                        </a>
                    </td>
                </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
</div>
