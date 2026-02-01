<?php
/**
 * Dashboard Page
 * Main dashboard with overview statistics
 */

require_once __DIR__ . '/../config/config.php';
requireAuth();

$page_title = 'Dashboard';

// Get statistics based on user role
$pdo = getDBConnection();
$stats = [];
$student_id = null;
$faculty_id = null;

if ($pdo) {
    // Retrieve student_id or faculty_id from database using user_id
    if (hasRole(ROLE_STUDENT)) {
        $stmt = $pdo->prepare("SELECT student_id FROM students WHERE user_id = ?");
        $stmt->execute([$_SESSION['user_id']]);
        $student = $stmt->fetch();
        $student_id = $student['student_id'] ?? null;
    } elseif (hasRole(ROLE_FACULTY)) {
        $stmt = $pdo->prepare("SELECT faculty_id FROM faculty WHERE user_id = ?");
        $stmt->execute([$_SESSION['user_id']]);
        $faculty = $stmt->fetch();
        $faculty_id = $faculty['faculty_id'] ?? null;
    }
    
    if (hasRole(ROLE_ADMIN)) {
        // Admin stats
        $stats['total_students'] = $pdo->query("SELECT COUNT(*) FROM students")->fetchColumn();
        $stats['active_students'] = $pdo->query("SELECT COUNT(*) FROM students WHERE status = 'Active'")->fetchColumn();
        $stats['total_faculty'] = $pdo->query("SELECT COUNT(*) FROM faculty WHERE status = 'Active'")->fetchColumn();
        $stats['total_courses'] = $pdo->query("SELECT COUNT(*) FROM courses WHERE status = 'Active'")->fetchColumn();
        $stats['pending_applications'] = $pdo->query("SELECT COUNT(*) FROM admission_applications WHERE status = 'Pending'")->fetchColumn();
        $stats['total_programs'] = $pdo->query("SELECT COUNT(*) FROM programs WHERE status = 'Active'")->fetchColumn();
    } elseif (hasRole(ROLE_FACULTY) && $faculty_id) {
        // Faculty stats
        $stmt = $pdo->prepare("SELECT COUNT(DISTINCT e.student_id) FROM enrollments e 
                               JOIN class_sections cs ON e.section_id = cs.section_id 
                               WHERE cs.faculty_id = ? AND e.status = 'Enrolled'");
        $stmt->execute([$faculty_id]);
        $stats['my_students'] = $stmt->fetchColumn();
        
        // Count assigned courses/sections
        $stmt = $pdo->prepare("SELECT COUNT(DISTINCT cs.section_id) FROM class_sections cs 
                               WHERE cs.faculty_id = ?");
        $stmt->execute([$faculty_id]);
        $stats['my_courses'] = $stmt->fetchColumn();
        
        // Count advisory students
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM advisory_assignments 
                               WHERE advisor_id = ? AND status = 'Active'");
        $stmt->execute([$faculty_id]);
        $stats['advisory_students'] = $stmt->fetchColumn();
    } elseif (hasRole(ROLE_STUDENT) && $student_id) {
        // Student stats
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM enrollments WHERE student_id = ? AND status = 'Enrolled'");
        $stmt->execute([$student_id]);
        $stats['my_courses'] = $stmt->fetchColumn();
        
        $stmt = $pdo->prepare("SELECT gpa, total_credits, status FROM students WHERE student_id = ?");
        $stmt->execute([$student_id]);
        $student_data = $stmt->fetch();
        $stats['gpa'] = $student_data['gpa'] ?? 0.00;
        $stats['academic_status'] = $student_data['status'] ?? 'Active';
        
        // Calculate total credits from enrollments (more accurate than static field)
        $stmt = $pdo->prepare("SELECT COALESCE(SUM(c.credits), 0) as total_credits
                              FROM enrollments e
                              INNER JOIN class_sections cs ON e.section_id = cs.section_id
                              INNER JOIN courses c ON cs.course_id = c.course_id
                              WHERE e.student_id = ? 
                              AND e.status IN ('Enrolled', 'Completed')");
        $stmt->execute([$student_id]);
        $credits_result = $stmt->fetch();
        $stats['total_credits'] = (int)($credits_result['total_credits'] ?? 0);
        
        // Calculate tuition balance
        $stmt = $pdo->prepare("SELECT COALESCE(SUM(i.total_amount - COALESCE((SELECT SUM(p.payment_amount) FROM payments p WHERE p.invoice_id = i.invoice_id), i.paid_amount, 0)), 0) as balance
                               FROM invoices i
                               WHERE i.student_id = ? AND i.status != 'Paid'");
        $stmt->execute([$student_id]);
        $balance_result = $stmt->fetch();
        $stats['tuition_balance'] = $balance_result['balance'] ?? 0.00;
    }
}

// Get announcements for students and faculty (if feature is enabled)
$announcements = [];
$announcements_enabled = true; // Default to enabled

if ($pdo) {
    // Check if announcements feature is enabled
    try {
        $stmt = $pdo->query("SHOW TABLES LIKE 'system_settings'");
        if ($stmt->rowCount() > 0) {
            $stmt = $pdo->prepare("SELECT setting_value FROM system_settings WHERE setting_key = 'announcements_enabled'");
            $stmt->execute();
            $setting = $stmt->fetch();
            $announcements_enabled = $setting ? ($setting['setting_value'] == '1') : true;
        }
    } catch (PDOException $e) {
        // Table doesn't exist, default to enabled
        $announcements_enabled = true;
    }
    
    // Get active announcements for students and faculty
    if ($announcements_enabled && (hasRole(ROLE_STUDENT) || hasRole(ROLE_FACULTY))) {
        // Check if department_id column exists
        $department_column_exists = false;
        try {
            $stmt = $pdo->query("SHOW COLUMNS FROM announcements LIKE 'department_id'");
            $department_column_exists = $stmt->rowCount() > 0;
        } catch (PDOException $e) {
            $department_column_exists = false;
        }
        
        $user_role = $_SESSION['user_role'];
        $user_department_id = null;
        
        // Get user's department (only if column exists)
        if ($department_column_exists) {
            if ($user_role === ROLE_STUDENT && $student_id) {
                $stmt = $pdo->prepare("SELECT program_id FROM students WHERE student_id = ?");
                $stmt->execute([$student_id]);
                $student = $stmt->fetch();
                if ($student && $student['program_id']) {
                    $stmt = $pdo->prepare("SELECT department_id FROM programs WHERE program_id = ?");
                    $stmt->execute([$student['program_id']]);
                    $program = $stmt->fetch();
                    $user_department_id = $program['department_id'] ?? null;
                }
            } elseif ($user_role === ROLE_FACULTY && $faculty_id) {
                $stmt = $pdo->prepare("SELECT department_id FROM faculty WHERE faculty_id = ?");
                $stmt->execute([$faculty_id]);
                $faculty = $stmt->fetch();
                $user_department_id = $faculty['department_id'] ?? null;
            }
        }
        
        // Build visibility filter using prepared statement (handle both old and new values)
        $visibility_params = ['All'];
        if ($user_role === ROLE_STUDENT) {
            $visibility_params[] = 'All_Students';
            // Support old 'Students' value for backward compatibility
            $visibility_params[] = 'Students';
            if ($department_column_exists && $user_department_id) {
                $visibility_params[] = 'Students_Department';
            }
        } elseif ($user_role === ROLE_FACULTY) {
            $visibility_params[] = 'All_Faculty';
            // Support old 'Faculty' value for backward compatibility
            $visibility_params[] = 'Faculty';
            if ($department_column_exists && $user_department_id) {
                $visibility_params[] = 'Faculty_Department';
            }
        }
        
        $placeholders = implode(',', array_fill(0, count($visibility_params), '?'));
        
        // Build query based on whether department column exists and user has a department
        if ($department_column_exists && $user_department_id) {
            // User has a department - show all matching announcements including department-specific ones
            $query = "SELECT a.*, u.username as created_by_username, d.department_name
                      FROM announcements a
                      LEFT JOIN users u ON a.created_by = u.user_id
                      LEFT JOIN departments d ON a.department_id = d.department_id
                      WHERE a.is_active = 1
                      AND a.visibility IN ($placeholders)
                      AND (a.expiration_date IS NULL OR a.expiration_date >= CURDATE())
                      AND (
                          a.visibility NOT IN ('Students_Department', 'Faculty_Department')
                          OR a.department_id = ?
                      )
                      ORDER BY a.created_at DESC
                      LIMIT 10";
            $query_params = array_merge($visibility_params, [$user_department_id]);
        } elseif ($department_column_exists) {
            // Department column exists but user has no department - only show non-department-specific announcements
            $query = "SELECT a.*, u.username as created_by_username, d.department_name
                      FROM announcements a
                      LEFT JOIN users u ON a.created_by = u.user_id
                      LEFT JOIN departments d ON a.department_id = d.department_id
                      WHERE a.is_active = 1
                      AND a.visibility IN ($placeholders)
                      AND (a.expiration_date IS NULL OR a.expiration_date >= CURDATE())
                      AND a.visibility NOT IN ('Students_Department', 'Faculty_Department')
                      ORDER BY a.created_at DESC
                      LIMIT 10";
            $query_params = $visibility_params;
        } else {
            // Department column doesn't exist - use old query format
            $query = "SELECT a.*, u.username as created_by_username
                      FROM announcements a
                      LEFT JOIN users u ON a.created_by = u.user_id
                      WHERE a.is_active = 1
                      AND a.visibility IN ($placeholders)
                      AND (a.expiration_date IS NULL OR a.expiration_date >= CURDATE())
                      ORDER BY a.created_at DESC
                      LIMIT 10";
            $query_params = $visibility_params;
        }
        
        try {
            $stmt = $pdo->prepare($query);
            $stmt->execute($query_params);
            $announcements = $stmt->fetchAll();
        } catch (PDOException $e) {
            // Table doesn't exist yet or error, announcements will be empty
            $announcements = [];
        }
    }
}

include __DIR__ . '/../includes/header.php';
?>

<div class="dashboard">
    <div class="page-header">
        <h1><i class="fas fa-home"></i> Dashboard</h1>
        <p>Welcome back, <?php echo sanitizeOutput($_SESSION['username']); ?>!</p>
    </div>

    <?php if (hasRole(ROLE_ADMIN)): ?>
    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-icon blue">
                <i class="fas fa-user-graduate"></i>
            </div>
            <div class="stat-info">
                <h3><?php echo number_format($stats['total_students'] ?? 0); ?></h3>
                <p>Total Students</p>
            </div>
        </div>

        <div class="stat-card">
            <div class="stat-icon green">
                <i class="fas fa-check-circle"></i>
            </div>
            <div class="stat-info">
                <h3><?php echo number_format($stats['active_students'] ?? 0); ?></h3>
                <p>Active Students</p>
            </div>
        </div>

        <div class="stat-card">
            <div class="stat-icon orange">
                <i class="fas fa-chalkboard-teacher"></i>
            </div>
            <div class="stat-info">
                <h3><?php echo number_format($stats['total_faculty'] ?? 0); ?></h3>
                <p>Faculty Members</p>
            </div>
        </div>

        <div class="stat-card">
            <div class="stat-icon purple">
                <i class="fas fa-book"></i>
            </div>
            <div class="stat-info">
                <h3><?php echo number_format($stats['total_courses'] ?? 0); ?></h3>
                <p>Active Courses</p>
            </div>
        </div>

        <div class="stat-card">
            <div class="stat-icon red">
                <i class="fas fa-file-alt"></i>
            </div>
            <div class="stat-info">
                <h3><?php echo number_format($stats['pending_applications'] ?? 0); ?></h3>
                <p>Pending Applications</p>
            </div>
        </div>

        <div class="stat-card">
            <div class="stat-icon teal">
                <i class="fas fa-certificate"></i>
            </div>
            <div class="stat-info">
                <h3><?php echo number_format($stats['total_programs'] ?? 0); ?></h3>
                <p>Academic Programs</p>
            </div>
        </div>
    </div>

    <div class="quick-actions">
        <h2>Quick Actions</h2>
        <div class="actions-grid">
            <a href="<?php echo BASE_URL; ?>modules/sis/add.php" class="action-card">
                <i class="fas fa-plus-circle"></i>
                <span>Add New Student</span>
            </a>
            <a href="<?php echo BASE_URL; ?>modules/admissions/index.php" class="action-card">
                <i class="fas fa-file-alt"></i>
                <span>Review Applications</span>
            </a>
            <a href="<?php echo BASE_URL; ?>modules/curriculum/index.php" class="action-card">
                <i class="fas fa-book"></i>
                <span>Manage Courses</span>
            </a>
            <a href="<?php echo BASE_URL; ?>modules/registration/index.php" class="action-card">
                <i class="fas fa-calendar-alt"></i>
                <span>Course Registration</span>
            </a>
        </div>
    </div>
    <?php endif; ?>

    <?php if (hasRole(ROLE_FACULTY)): ?>
    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-icon blue">
                <i class="fas fa-user-graduate"></i>
            </div>
            <div class="stat-info">
                <h3><?php echo number_format($stats['my_students'] ?? 0); ?></h3>
                <p>My Students</p>
            </div>
        </div>

        <div class="stat-card">
            <div class="stat-icon green">
                <i class="fas fa-book"></i>
            </div>
            <div class="stat-info">
                <h3><?php echo number_format($stats['my_courses'] ?? 0); ?></h3>
                <p>My Courses</p>
            </div>
        </div>

        <div class="stat-card">
            <div class="stat-icon orange">
                <i class="fas fa-user-friends"></i>
            </div>
            <div class="stat-info">
                <h3><?php echo number_format($stats['advisory_students'] ?? 0); ?></h3>
                <p>Advisory Students</p>
            </div>
        </div>
    </div>

    <div class="quick-actions">
        <h2>Quick Actions</h2>
        <div class="actions-grid">
            <a href="<?php echo BASE_URL; ?>modules/grades/index.php" class="action-card">
                <i class="fas fa-clipboard-list"></i>
                <span>Enter Grades</span>
            </a>
            <a href="<?php echo BASE_URL; ?>modules/attendance/index.php" class="action-card">
                <i class="fas fa-calendar-check"></i>
                <span>Take Attendance</span>
            </a>
            <a href="<?php echo BASE_URL; ?>modules/advisory/index.php" class="action-card">
                <i class="fas fa-user-friends"></i>
                <span>View Advisory Students</span>
            </a>
            <a href="<?php echo BASE_URL; ?>auth/profile.php" class="action-card">
                <i class="fas fa-user-circle"></i>
                <span>My Profile</span>
            </a>
        </div>
    </div>
    <?php endif; ?>

    <?php if (hasRole(ROLE_STUDENT)): ?>
    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-icon blue">
                <i class="fas fa-book"></i>
            </div>
            <div class="stat-info">
                <h3><?php echo number_format($stats['my_courses'] ?? 0); ?></h3>
                <p>My Courses</p>
            </div>
        </div>

        <div class="stat-card">
            <div class="stat-icon green">
                <i class="fas fa-chart-line"></i>
            </div>
            <div class="stat-info">
                <h3><?php echo number_format($stats['gpa'] ?? 0, 2); ?></h3>
                <p>Current GPA</p>
            </div>
        </div>

        <div class="stat-card">
            <div class="stat-icon purple">
                <i class="fas fa-certificate"></i>
            </div>
            <div class="stat-info">
                <h3><?php echo number_format($stats['total_credits'] ?? 0); ?></h3>
                <p>Total Credits</p>
            </div>
        </div>

        <div class="stat-card">
            <div class="stat-icon <?php echo ($stats['tuition_balance'] ?? 0) > 0 ? 'red' : 'teal'; ?>">
                <i class="fas fa-dollar-sign"></i>
            </div>
            <div class="stat-info">
                <h3>₱<?php echo number_format($stats['tuition_balance'] ?? 0, 2); ?></h3>
                <p>Tuition Balance</p>
            </div>
        </div>

        <div class="stat-card">
            <div class="stat-icon orange">
                <i class="fas fa-info-circle"></i>
            </div>
            <div class="stat-info">
                <h3><?php echo sanitizeOutput($stats['academic_status'] ?? 'Active'); ?></h3>
                <p>Academic Status</p>
            </div>
        </div>
    </div>

    <div class="quick-actions">
        <h2>Quick Actions</h2>
        <div class="actions-grid">
            <a href="<?php echo BASE_URL; ?>modules/registration/student_courses.php" class="action-card">
                <i class="fas fa-book"></i>
                <span>View My Courses</span>
            </a>
            <a href="<?php echo BASE_URL; ?>modules/grades/student_view.php" class="action-card">
                <i class="fas fa-clipboard-list"></i>
                <span>View My Grades</span>
            </a>
            <a href="<?php echo BASE_URL; ?>modules/billing/student_view.php" class="action-card">
                <i class="fas fa-dollar-sign"></i>
                <span>View My Tuition</span>
            </a>
            <a href="<?php echo BASE_URL; ?>auth/profile.php" class="action-card">
                <i class="fas fa-user-circle"></i>
                <span>My Profile</span>
            </a>
        </div>
    </div>
    <?php endif; ?>
    
    <?php if ((hasRole(ROLE_STUDENT) || hasRole(ROLE_FACULTY)) && $announcements_enabled && !empty($announcements)): ?>
    <div class="card" style="margin-top: 1.5rem;">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem;">
            <h2 style="margin: 0;"><i class="fas fa-bullhorn"></i> School News & Announcements</h2>
        </div>
        <div style="display: flex; flex-direction: column; gap: 1rem;">
            <?php foreach ($announcements as $ann): 
                $category_colors = [
                    'General' => 'info',
                    'Academic' => 'primary',
                    'Events' => 'success',
                    'Financial' => 'warning',
                    'Faculty' => 'secondary'
                ];
                $category_color = $category_colors[$ann['category']] ?? 'info';
            ?>
            <div style="border: 1px solid #ddd; border-radius: 5px; padding: 1rem; background: #fff;">
                <div style="display: flex; justify-content: space-between; align-items: start; margin-bottom: 0.5rem;">
                    <h3 style="margin: 0; font-size: 1.1rem;"><?php echo sanitizeOutput($ann['title']); ?></h3>
                    <span class="badge badge-<?php echo $category_color; ?>"><?php echo sanitizeOutput($ann['category']); ?></span>
                </div>
                <div style="color: #666; margin-bottom: 0.5rem; white-space: pre-wrap;">
                    <?php echo nl2br(sanitizeOutput(substr($ann['content'], 0, 200))); ?>
                    <?php if (strlen($ann['content']) > 200): ?>...<?php endif; ?>
                </div>
                <div style="display: flex; justify-content: space-between; align-items: center; font-size: 0.9rem; color: #999;">
                    <span>
                        <i class="fas fa-calendar"></i> <?php echo date('M d, Y', strtotime($ann['created_at'])); ?>
                        <?php if ($ann['expiration_date']): ?>
                            | <i class="fas fa-clock"></i> Expires: <?php echo date('M d, Y', strtotime($ann['expiration_date'])); ?>
                        <?php endif; ?>
                    </span>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
