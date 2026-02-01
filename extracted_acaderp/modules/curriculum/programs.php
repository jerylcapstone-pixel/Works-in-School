<?php
$search = sanitizeInput($_GET['search'] ?? '');
$department_filter = !empty($_GET['department_id']) ? (int)$_GET['department_id'] : null;

$query = "SELECT p.*, d.department_name, d.department_code
          FROM programs p
          LEFT JOIN departments d ON p.department_id = d.department_id
          WHERE 1=1";
$params = [];

if (!empty($search)) {
    $query .= " AND (p.program_code LIKE ? OR p.program_name LIKE ?)";
    $searchParam = "%$search%";
    $params = array_merge($params, [$searchParam, $searchParam]);
}

if (!empty($department_filter)) {
    $query .= " AND p.department_id = ?";
    $params[] = $department_filter;
}

$query .= " ORDER BY p.program_name";
$stmt = $pdo->prepare($query);
$stmt->execute($params);
$programs = $stmt->fetchAll();

$departments = [];
$stmt = $pdo->query("SELECT department_id, department_name, department_code FROM departments ORDER BY department_name");
$departments = $stmt->fetchAll();
?>

<div style="padding: 1.5rem;">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem;">
        <h2 style="margin: 0;">Programs</h2>
        <a href="<?php echo BASE_URL; ?>modules/curriculum/program_add.php" class="btn btn-primary">
            <i class="fas fa-plus"></i> Add Program
        </a>
    </div>

    <form method="GET" action="" style="margin-bottom: 1.5rem;">
        <input type="hidden" name="tab" value="programs">
        <div style="display: flex; gap: 1rem; flex-wrap: wrap;">
            <div style="flex: 1; min-width: 220px;">
                <label style="display:block; font-weight:600; margin-bottom:4px;">Department</label>
                <select name="department_id" class="form-control" onchange="this.form.submit()">
                    <option value="">All Departments</option>
                    <?php foreach ($departments as $dept): ?>
                        <option value="<?php echo $dept['department_id']; ?>" <?php echo ($department_filter == $dept['department_id']) ? 'selected' : ''; ?>>
                            <?php echo sanitizeOutput($dept['department_name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div style="flex: 2; min-width: 220px; display: flex; gap: 1rem; align-items: flex-end;">
                <div style="flex:1;">
                    <label style="display:block; font-weight:600; margin-bottom:4px;">Search</label>
                    <input type="text" name="search" placeholder="Search programs..." 
                           value="<?php echo sanitizeOutput($search); ?>" class="form-control">
                </div>
                <div style="display:flex; gap:0.5rem;">
                    <button type="submit" class="btn btn-secondary" style="height:38px;"><i class="fas fa-search"></i> Search</button>
                    <a href="?tab=programs" class="btn btn-outline" style="height:38px;line-height:24px;">Clear</a>
                </div>
            </div>
        </div>
    </form>

    <table class="data-table">
        <thead>
            <tr>
                <th>Program Name</th>
                <th>Department</th>
                <th>Degree Type</th>
                <th>Total Credits</th>
                <th>Duration</th>
                <th>Status</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($programs)): ?>
            <tr>
                <td colspan="7" class="text-center">
                    <p>No programs found.</p>
                    <a href="<?php echo BASE_URL; ?>modules/curriculum/program_add.php" class="btn btn-primary">
                        <i class="fas fa-plus"></i> Add First Program
                    </a>
                </td>
            </tr>
            <?php else: ?>
                <?php foreach ($programs as $prog): ?>
                <tr>
                    <td><strong><?php echo sanitizeOutput($prog['program_name']); ?></strong></td>
                    <td><?php echo sanitizeOutput($prog['department_name'] ?? 'N/A'); ?></td>
                    <td><?php echo sanitizeOutput($prog['degree_type']); ?></td>
                    <td><?php echo $prog['total_credits']; ?></td>
                    <td><?php echo $prog['duration_years'] ? $prog['duration_years'] . ' years' : 'N/A'; ?></td>
                    <td>
                        <span class="badge badge-<?php echo $prog['status'] === 'Active' ? 'success' : 'warning'; ?>">
                            <?php echo sanitizeOutput($prog['status']); ?>
                        </span>
                    </td>
                    <td class="actions">
                        <a href="<?php echo BASE_URL; ?>modules/curriculum/program_edit.php?id=<?php echo $prog['program_id']; ?>" 
                           class="btn btn-sm btn-warning" title="Edit">
                            <i class="fas fa-edit"></i>
                        </a>
                        <a href="<?php echo BASE_URL; ?>modules/curriculum/program_delete.php?id=<?php echo $prog['program_id']; ?>" 
                           class="btn btn-sm btn-danger"
                           onclick="return confirm('Are you sure?');"
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
