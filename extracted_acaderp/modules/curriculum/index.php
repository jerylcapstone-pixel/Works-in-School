<?php
/**
 * Curriculum Management - Main Page
 * Main hub for managing departments, programs, and courses
 */

require_once __DIR__ . '/../../config/config.php';
requireAuth(ROLE_ADMIN);

$page_title = 'Curriculum Management';

$pdo = getDBConnection();
$tab = sanitizeInput($_GET['tab'] ?? 'departments');

// Get statistics
try {
    $majors_count = $pdo->query("SELECT COUNT(*) FROM majors WHERE status = 'Active'")->fetchColumn();
} catch (PDOException $e) {
    $majors_count = 0; // If majors table doesn't exist yet
}

$stats = [
    'departments' => $pdo->query("SELECT COUNT(*) FROM departments")->fetchColumn(),
    'programs' => $pdo->query("SELECT COUNT(*) FROM programs WHERE status = 'Active'")->fetchColumn(),
    'courses' => $pdo->query("SELECT COUNT(*) FROM courses WHERE status = 'Active'")->fetchColumn(),
    'majors' => $majors_count
];

include __DIR__ . '/../../includes/header.php';
?>

<div class="page-container">
    <div class="page-header">
        <h1><i class="fas fa-book"></i> Curriculum Management</h1>
    </div>

    <div class="stats-grid" style="margin-bottom: 2rem;">
        <div class="stat-card">
            <div class="stat-icon blue">
                <i class="fas fa-building"></i>
            </div>
            <div class="stat-info">
                <h3><?php echo number_format($stats['departments']); ?></h3>
                <p>Departments</p>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon green">
                <i class="fas fa-graduation-cap"></i>
            </div>
            <div class="stat-info">
                <h3><?php echo number_format($stats['programs']); ?></h3>
                <p>Active Programs</p>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon purple">
                <i class="fas fa-tag"></i>
            </div>
            <div class="stat-info">
                <h3><?php echo number_format($stats['majors']); ?></h3>
                <p>Active Majors</p>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon orange">
                <i class="fas fa-book-open"></i>
            </div>
            <div class="stat-info">
                <h3><?php echo number_format($stats['courses']); ?></h3>
                <p>Active Courses</p>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="tabs">
            <a href="?tab=departments" class="tab <?php echo $tab === 'departments' ? 'active' : ''; ?>">
                <i class="fas fa-building"></i> Departments
            </a>
            <a href="?tab=programs" class="tab <?php echo $tab === 'programs' ? 'active' : ''; ?>">
                <i class="fas fa-graduation-cap"></i> Programs
            </a>
            <a href="?tab=majors" class="tab <?php echo $tab === 'majors' ? 'active' : ''; ?>">
                <i class="fas fa-tag"></i> Majors
            </a>
            <a href="?tab=courses" class="tab <?php echo $tab === 'courses' ? 'active' : ''; ?>">
                <i class="fas fa-book-open"></i> Courses
            </a>
        </div>

        <div class="tab-content">
            <?php if ($tab === 'departments'): ?>
                <?php include __DIR__ . '/departments.php'; ?>
            <?php elseif ($tab === 'programs'): ?>
                <?php include __DIR__ . '/programs.php'; ?>
            <?php elseif ($tab === 'courses'): ?>
                <?php include __DIR__ . '/courses.php'; ?>
            <?php elseif ($tab === 'majors'): ?>
                <?php include __DIR__ . '/majors.php'; ?>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
