<?php
$search = sanitizeInput($_GET['search'] ?? '');

$majors = [];
try {
    $query = "SELECT m.*, p.program_name, p.program_code, d.department_name
              FROM majors m
              LEFT JOIN programs p ON m.program_id = p.program_id
              LEFT JOIN departments d ON p.department_id = d.department_id
              WHERE 1=1";
    $params = [];

    if (!empty($search)) {
        $query .= " AND (m.major_name LIKE ? OR p.program_name LIKE ?)";
        $searchParam = "%$search%";
        $params = array_merge($params, [$searchParam, $searchParam]);
    }

    $query .= " ORDER BY p.program_name, m.major_name";
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $majors = $stmt->fetchAll();
} catch (PDOException $e) {
    // If majors table doesn't exist yet
    $majors = [];
}

$programs = [];
$stmt = $pdo->query("SELECT program_id, program_name, program_code FROM programs WHERE status = 'Active' ORDER BY program_name");
$programs = $stmt->fetchAll();
?>

<div style="padding: 1.5rem;">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem;">
        <h2 style="margin: 0;">Majors</h2>
        <a href="<?php echo BASE_URL; ?>modules/curriculum/major_add.php" class="btn btn-primary">
            <i class="fas fa-plus"></i> Add Major
        </a>
    </div>

    <form method="GET" action="" style="margin-bottom: 1.5rem;">
        <input type="hidden" name="tab" value="majors">
        <div style="display: flex; gap: 1rem;">
            <input type="text" name="search" placeholder="Search majors..." 
                   value="<?php echo sanitizeOutput($search); ?>" class="form-control" style="flex: 1;">
            <button type="submit" class="btn btn-secondary"><i class="fas fa-search"></i> Search</button>
            <a href="?tab=majors" class="btn btn-outline">Clear</a>
        </div>
    </form>

    <table class="data-table">
        <thead>
            <tr>
                <th>Major Name</th>
                <th>Program</th>
                <th>Department</th>
                <th>Status</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($majors)): ?>
            <tr>
                <td colspan="5" class="text-center">
                    <p>No majors found.</p>
                    <a href="<?php echo BASE_URL; ?>modules/curriculum/major_add.php" class="btn btn-primary">
                        <i class="fas fa-plus"></i> Add First Major
                    </a>
                </td>
            </tr>
            <?php else: ?>
                <?php foreach ($majors as $major): ?>
                <tr>
                    <td><strong><?php echo sanitizeOutput($major['major_name']); ?></strong></td>
                    <td><?php echo sanitizeOutput($major['program_name'] ?? 'N/A'); ?></td>
                    <td><?php echo sanitizeOutput($major['department_name'] ?? 'N/A'); ?></td>
                    <td>
                        <span class="badge badge-<?php echo $major['status'] === 'Active' ? 'success' : 'warning'; ?>">
                            <?php echo sanitizeOutput($major['status']); ?>
                        </span>
                    </td>
                    <td class="actions">
                        <a href="<?php echo BASE_URL; ?>modules/curriculum/major_edit.php?id=<?php echo $major['major_id']; ?>" 
                           class="btn btn-sm btn-warning" title="Edit">
                            <i class="fas fa-edit"></i>
                        </a>
                        <a href="<?php echo BASE_URL; ?>modules/curriculum/major_delete.php?id=<?php echo $major['major_id']; ?>" 
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

