<?php
$search = sanitizeInput($_GET['search'] ?? '');

$query = "SELECT c.*, d.department_name, d.department_code, 
          p.program_name, p.program_code
          FROM courses c
          LEFT JOIN departments d ON c.department_id = d.department_id
          LEFT JOIN program_courses pc ON c.course_id = pc.course_id
          LEFT JOIN programs p ON pc.program_id = p.program_id
          WHERE 1=1";
$params = [];

if (!empty($search)) {
    $query .= " AND (c.course_code LIKE ? OR c.course_name LIKE ?)";
    $searchParam = "%$search%";
    $params = array_merge($params, [$searchParam, $searchParam]);
}

$query .= " ORDER BY c.course_code";
$stmt = $pdo->prepare($query);
$stmt->execute($params);
$courses = $stmt->fetchAll();

$departments = [];
$stmt = $pdo->query("SELECT department_id, department_name, department_code FROM departments ORDER BY department_name");
$departments = $stmt->fetchAll();
?>

<div style="padding: 1.5rem;">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem;">
        <h2 style="margin: 0;">Courses</h2>
        <a href="<?php echo BASE_URL; ?>modules/curriculum/course_add.php" class="btn btn-primary">
            <i class="fas fa-plus"></i> Add Course
        </a>
    </div>

    <form method="GET" action="" style="margin-bottom: 1.5rem;">
        <input type="hidden" name="tab" value="courses">
        <div style="display: flex; gap: 1rem;">
            <input type="text" name="search" placeholder="Search courses..." 
                   value="<?php echo sanitizeOutput($search); ?>" class="form-control" style="flex: 1;">
            <button type="submit" class="btn btn-secondary"><i class="fas fa-search"></i> Search</button>
            <a href="?tab=courses" class="btn btn-outline">Clear</a>
        </div>
    </form>

    <table class="data-table">
        <thead>
            <tr>
                <th>Course Name</th>
                <th>Department</th>
                <th>Program</th>
                <th>Credits</th>
                <th>Type</th>
                <th>Status</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($courses)): ?>
            <tr>
                <td colspan="7" class="text-center">
                    <p>No courses found.</p>
                    <a href="<?php echo BASE_URL; ?>modules/curriculum/course_add.php" class="btn btn-primary">
                        <i class="fas fa-plus"></i> Add First Course
                    </a>
                </td>
            </tr>
            <?php else: ?>
                <?php foreach ($courses as $course): ?>
                <tr>
                    <td><strong><?php echo sanitizeOutput($course['course_name']); ?></strong></td>
                    <td><?php echo sanitizeOutput($course['department_name'] ?? 'N/A'); ?></td>
                    <td><?php echo sanitizeOutput($course['program_name'] ?? 'N/A'); ?></td>
                    <td><?php echo $course['credits']; ?></td>
                    <td><?php echo sanitizeOutput($course['course_type']); ?></td>
                    <td>
                        <span class="badge badge-<?php echo $course['status'] === 'Active' ? 'success' : 'warning'; ?>">
                            <?php echo sanitizeOutput($course['status']); ?>
                        </span>
                    </td>
                    <td class="actions">
                        <a href="<?php echo BASE_URL; ?>modules/curriculum/course_edit.php?id=<?php echo $course['course_id']; ?>" 
                           class="btn btn-sm btn-warning" title="Edit">
                            <i class="fas fa-edit"></i>
                        </a>
                        <a href="<?php echo BASE_URL; ?>modules/curriculum/course_delete.php?id=<?php echo $course['course_id']; ?>" 
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
