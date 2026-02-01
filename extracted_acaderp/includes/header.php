<?php
/**
 * Header Include File
 * Common header with navigation for all pages
 */
require_once __DIR__ . '/../config/config.php';

// Get current user info if logged in
$current_user = null;
if (isLoggedIn()) {
    $pdo = getDBConnection();
    if ($pdo) {
        $stmt = $pdo->prepare("SELECT user_id, username, email, user_role FROM users WHERE user_id = ?");
        $stmt->execute([$_SESSION['user_id']]);
        $current_user = $stmt->fetch();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo isset($page_title) ? sanitizeOutput($page_title) . ' - ' : ''; ?><?php echo APP_NAME; ?></title>
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>assets/css/style.css?v=<?php echo time(); ?>">
    <?php 
    // Load dark theme CSS if theme customization is enabled
    if (isLoggedIn() && hasRole(ROLE_STUDENT)) {
        require_once __DIR__ . '/../config/feature_toggles.php';
        $pdo = getDBConnection();
        if (isThemeCustomizationEnabled($pdo)) {
            echo '<link rel="stylesheet" href="' . BASE_URL . 'assets/css/theme-dark.css?v=' . time() . '">';
        }
    }
    ?>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script>
        // Make BASE_URL available to JavaScript
        var BASE_URL = '<?php echo BASE_URL; ?>';
    </script>
</head>
<body <?php 
    $body_classes = [];
    
    // Add sidebar class
    if (isLoggedIn()) {
        $body_classes[] = 'has-sidebar';
    } else {
        $body_classes[] = 'no-sidebar';
    }
    
    // Apply theme class if theme customization is enabled
    if (isLoggedIn() && hasRole(ROLE_STUDENT)) {
        require_once __DIR__ . '/../config/feature_toggles.php';
        $pdo = getDBConnection();
        if (isThemeCustomizationEnabled($pdo)) {
            // Get user theme preference
            try {
                $stmt = $pdo->prepare("SELECT theme_mode FROM user_preferences WHERE user_id = ?");
                $stmt->execute([$_SESSION['user_id']]);
                $pref = $stmt->fetch();
                $theme_mode = $pref ? $pref['theme_mode'] : 'light';
                $body_classes[] = 'theme-' . $theme_mode;
                echo 'data-theme-enabled="1" ';
            } catch (PDOException $e) {
                echo 'data-theme-enabled="1" ';
            }
        }
    }
    
    if (!empty($body_classes)) {
        echo 'class="' . implode(' ', $body_classes) . '"';
    }
?>>
    <?php if (isLoggedIn()): ?>
    <?php
    // Get current page path for active navigation
    $current_page = $_SERVER['SCRIPT_NAME'];
    $current_page = str_replace('\\', '/', $current_page); // Normalize path separators for Windows
    
    // Helper function to check if a link should be active
    function isActiveLink($link_path, $current_page) {
        $link_path = str_replace('\\', '/', $link_path);
        // Remove BASE_URL from link path for comparison
        $link_path = str_replace(BASE_URL, '', $link_path);
        
        // Exact match first
        if (strpos($current_page, $link_path) !== false) {
            // For module index pages (e.g., modules/grades/index.php), 
            // match any page in that module directory, but exclude specific sub-pages
            if (strpos($link_path, '/index.php') !== false) {
                $module_dir = dirname($link_path) . '/';
                // Check if current page is in the module directory
                if (strpos($current_page, $module_dir) !== false) {
                    // Exclude specific student/faculty views that have their own links
                    $exclude_patterns = ['student_view.php', 'student_portal.php', 'student_courses.php', 'student_exams.php', 'my_requests.php'];
                    foreach ($exclude_patterns as $pattern) {
                        if (strpos($current_page, $pattern) !== false) {
                            return false;
                        }
                    }
                    return true;
                }
            }
            // For specific pages, check if current page contains the link path
            return true;
        }
        return false;
    }
    ?>
    <!-- Sidebar Navigation -->
    <aside class="sidebar" id="sidebar">
        <div class="sidebar-header">
            <a href="<?php echo BASE_URL; ?>dashboard/index.php" class="sidebar-brand">
                <i class="fas fa-graduation-cap"></i>
                <span class="brand-text"><?php echo APP_NAME; ?></span>
            </a>
            <button class="sidebar-toggle" id="sidebarToggle" aria-label="Toggle sidebar">
                <i class="fas fa-bars"></i>
            </button>
        </div>
        
        <nav class="sidebar-nav">
            <ul class="sidebar-menu" id="sidebarMenu">
                <li>
                    <a href="<?php echo BASE_URL; ?>dashboard/index.php" class="sidebar-link<?php echo isActiveLink('dashboard/index.php', $current_page) ? ' active' : ''; ?>">
                        <i class="fas fa-home"></i>
                        <span>Dashboard</span>
                    </a>
                </li>
                
                <?php if (hasRole(ROLE_ADMIN)): ?>
                <li class="sidebar-divider">
                    <span>Management</span>
                </li>
                <li>
                    <a href="<?php echo BASE_URL; ?>modules/sis/index.php" class="sidebar-link<?php echo isActiveLink('modules/sis/index.php', $current_page) ? ' active' : ''; ?>">
                        <i class="fas fa-user-graduate"></i>
                        <span>Students</span>
                    </a>
                </li>
                <li>
                    <a href="<?php echo BASE_URL; ?>modules/faculty/index.php" class="sidebar-link<?php echo isActiveLink('modules/faculty/index.php', $current_page) ? ' active' : ''; ?>">
                        <i class="fas fa-chalkboard-teacher"></i>
                        <span>Faculty</span>
                    </a>
                </li>
                <li>
                    <a href="<?php echo BASE_URL; ?>modules/curriculum/index.php" class="sidebar-link<?php echo isActiveLink('modules/curriculum/index.php', $current_page) ? ' active' : ''; ?>">
                        <i class="fas fa-book"></i>
                        <span>Curriculum</span>
                    </a>
                </li>
                <li>
                    <a href="<?php echo BASE_URL; ?>modules/admissions/index.php" class="sidebar-link<?php echo isActiveLink('modules/admissions/index.php', $current_page) ? ' active' : ''; ?>">
                        <i class="fas fa-file-alt"></i>
                        <span>Admissions</span>
                    </a>
                </li>
                <li>
                    <a href="<?php echo BASE_URL; ?>modules/registration/index.php" class="sidebar-link<?php echo isActiveLink('modules/registration/index.php', $current_page) ? ' active' : ''; ?>">
                        <i class="fas fa-calendar-alt"></i>
                        <span>Registration</span>
                    </a>
                </li>
                <li>
                    <a href="<?php echo BASE_URL; ?>modules/billing/index.php" class="sidebar-link<?php echo isActiveLink('modules/billing/index.php', $current_page) ? ' active' : ''; ?>">
                        <i class="fas fa-dollar-sign"></i>
                        <span>Billing</span>
                    </a>
                </li>
                <li>
                    <a href="<?php echo BASE_URL; ?>modules/booking/index.php" class="sidebar-link<?php echo isActiveLink('modules/booking/index.php', $current_page) ? ' active' : ''; ?>">
                        <i class="fas fa-chalkboard-teacher"></i>
                        <span>Booking</span>
                    </a>
                </li>
                <?php 
                require_once __DIR__ . '/../config/feature_toggles.php';
                $pdo = getDBConnection();
                if (isExaminationsEnabled($pdo)): ?>
                <li>
                    <a href="<?php echo BASE_URL; ?>modules/examinations/index.php" class="sidebar-link<?php echo isActiveLink('modules/examinations/index.php', $current_page) ? ' active' : ''; ?>">
                        <i class="fas fa-file-alt"></i>
                        <span>Examinations</span>
                    </a>
                </li>
                <?php endif; ?>
                <?php if (isLibraryEnabled($pdo)): ?>
                <li>
                    <a href="<?php echo BASE_URL; ?>modules/library/index.php" class="sidebar-link<?php echo isActiveLink('modules/library/index.php', $current_page) ? ' active' : ''; ?>">
                        <i class="fas fa-book-open"></i>
                        <span>Library</span>
                    </a>
                </li>
                <?php endif; ?>
                <?php if (isDocumentRequestsEnabled($pdo)): ?>
                <li>
                    <a href="<?php echo BASE_URL; ?>modules/documents/index.php" class="sidebar-link<?php echo isActiveLink('modules/documents/index.php', $current_page) ? ' active' : ''; ?>">
                        <i class="fas fa-file-invoice"></i>
                        <span>Document Requests</span>
                    </a>
                </li>
                <?php endif; ?>
                <?php if (isAnnouncementsEnabled($pdo)): ?>
                <li>
                    <a href="<?php echo BASE_URL; ?>modules/announcements/index.php" class="sidebar-link<?php echo isActiveLink('modules/announcements/index.php', $current_page) ? ' active' : ''; ?>">
                        <i class="fas fa-bullhorn"></i>
                        <span>Announcements</span>
                    </a>
                </li>
                <?php endif; ?>
                <li>
                    <a href="<?php echo BASE_URL; ?>modules/settings/index.php" class="sidebar-link<?php echo isActiveLink('modules/settings/index.php', $current_page) ? ' active' : ''; ?>">
                        <i class="fas fa-cog"></i>
                        <span>Settings</span>
                    </a>
                </li>
                <?php endif; ?>
                
                <?php if (hasRole(ROLE_FACULTY)): ?>
                <li class="sidebar-divider">
                    <span>Teaching</span>
                </li>
                <li>
                    <a href="<?php echo BASE_URL; ?>modules/grades/index.php" class="sidebar-link<?php echo isActiveLink('modules/grades/index.php', $current_page) ? ' active' : ''; ?>">
                        <i class="fas fa-clipboard-list"></i>
                        <span>Grades</span>
                    </a>
                </li>
                <?php 
                require_once __DIR__ . '/../config/feature_toggles.php';
                $pdo = getDBConnection();
                if (isExaminationsEnabled($pdo)): ?>
                <li>
                    <a href="<?php echo BASE_URL; ?>modules/examinations/index.php" class="sidebar-link<?php echo isActiveLink('modules/examinations/index.php', $current_page) ? ' active' : ''; ?>">
                        <i class="fas fa-file-alt"></i>
                        <span>Examinations</span>
                    </a>
                </li>
                <?php endif; ?>
                <?php if (isLibraryEnabled($pdo)): ?>
                <li>
                    <a href="<?php echo BASE_URL; ?>modules/library/student_portal.php" class="sidebar-link<?php echo isActiveLink('modules/library/student_portal.php', $current_page) ? ' active' : ''; ?>">
                        <i class="fas fa-book-open"></i>
                        <span>Library</span>
                    </a>
                </li>
                <?php endif; ?>
                <li>
                    <a href="<?php echo BASE_URL; ?>modules/attendance/index.php" class="sidebar-link<?php echo isActiveLink('modules/attendance/index.php', $current_page) ? ' active' : ''; ?>">
                        <i class="fas fa-calendar-check"></i>
                        <span>Attendance</span>
                    </a>
                </li>
                <li>
                    <a href="<?php echo BASE_URL; ?>modules/advisory/index.php" class="sidebar-link<?php echo isActiveLink('modules/advisory/index.php', $current_page) ? ' active' : ''; ?>">
                        <i class="fas fa-user-friends"></i>
                        <span>Advisory</span>
                    </a>
                </li>
                <?php if (hasRole(ROLE_ADMIN) || hasRole(ROLE_FACULTY)): ?>
                <li>
                    <a href="<?php echo BASE_URL; ?>modules/booking/index.php" class="sidebar-link<?php echo isActiveLink('modules/booking/index.php', $current_page) ? ' active' : ''; ?>">
                        <i class="fas fa-door-open"></i>
                        <span>Booking</span>
                    </a>
                </li>
                <?php endif; ?>
                <?php endif; ?>
                
                <?php if (hasRole(ROLE_STUDENT)): ?>
                <li class="sidebar-divider">
                    <span>My Portal</span>
                </li>
                <li>
                    <a href="<?php echo BASE_URL; ?>modules/registration/student_courses.php" class="sidebar-link<?php echo isActiveLink('modules/registration/student_courses.php', $current_page) ? ' active' : ''; ?>">
                        <i class="fas fa-book"></i>
                        <span>My Courses</span>
                    </a>
                </li>
                <li>
                    <a href="<?php echo BASE_URL; ?>modules/grades/student_view.php" class="sidebar-link<?php echo isActiveLink('modules/grades/student_view.php', $current_page) ? ' active' : ''; ?>">
                        <i class="fas fa-clipboard-list"></i>
                        <span>My Grades</span>
                    </a>
                </li>
                <?php 
                require_once __DIR__ . '/../config/feature_toggles.php';
                $pdo = getDBConnection();
                if (isExaminationsEnabled($pdo)): ?>
                <li>
                    <a href="<?php echo BASE_URL; ?>modules/examinations/student_exams.php" class="sidebar-link<?php echo isActiveLink('modules/examinations/student_exams.php', $current_page) ? ' active' : ''; ?>">
                        <i class="fas fa-file-alt"></i>
                        <span>Examinations</span>
                    </a>
                </li>
                <?php endif; ?>
                <?php if (isLibraryEnabled($pdo)): ?>
                <li>
                    <a href="<?php echo BASE_URL; ?>modules/library/student_portal.php" class="sidebar-link<?php echo isActiveLink('modules/library/student_portal.php', $current_page) ? ' active' : ''; ?>">
                        <i class="fas fa-book-open"></i>
                        <span>Library</span>
                    </a>
                </li>
                <?php endif; ?>
                <?php if (isDocumentRequestsEnabled($pdo)): ?>
                <li>
                    <a href="<?php echo BASE_URL; ?>modules/documents/my_requests.php" class="sidebar-link<?php echo isActiveLink('modules/documents/my_requests.php', $current_page) ? ' active' : ''; ?>">
                        <i class="fas fa-file-alt"></i>
                        <span>Document Requests</span>
                    </a>
                </li>
                <?php endif; ?>
                <li>
                    <a href="<?php echo BASE_URL; ?>modules/billing/student_view.php" class="sidebar-link<?php echo isActiveLink('modules/billing/student_view.php', $current_page) ? ' active' : ''; ?>">
                        <i class="fas fa-dollar-sign"></i>
                        <span>My Tuition</span>
                    </a>
                </li>
                <?php 
                // Add theme toggle for students if enabled
                if (hasRole(ROLE_STUDENT)) {
                    require_once __DIR__ . '/../config/feature_toggles.php';
                    $pdo = getDBConnection();
                    if (isThemeCustomizationEnabled($pdo)) {
                        // Get current theme
                        $current_theme = 'light';
                        try {
                            $stmt = $pdo->prepare("SELECT theme_mode FROM user_preferences WHERE user_id = ?");
                            $stmt->execute([$_SESSION['user_id']]);
                            $pref = $stmt->fetch();
                            if ($pref) {
                                $current_theme = $pref['theme_mode'];
                            }
                        } catch (PDOException $e) {
                            // Use default
                        }
                        $theme_icon = $current_theme === 'dark' ? 'fa-sun' : 'fa-moon';
                        $theme_title = $current_theme === 'dark' ? 'Switch to Light Mode' : 'Switch to Dark Mode';
                ?>
                <li>
                    <a href="#" id="theme-toggle-btn" class="sidebar-link" title="<?php echo $theme_title; ?>" style="cursor: pointer;">
                        <i class="fas <?php echo $theme_icon; ?>"></i>
                        <span><?php echo $current_theme === 'dark' ? 'Light Mode' : 'Dark Mode'; ?></span>
                    </a>
                </li>
                <?php 
                    }
                }
                ?>
                <?php endif; ?>
            </ul>
        </nav>
        
        <div class="sidebar-footer">
            <div class="sidebar-user">
                <div class="user-info">
                    <i class="fas fa-user-circle"></i>
                    <div class="user-details">
                        <span class="user-name"><?php echo sanitizeOutput($current_user['username'] ?? 'User'); ?></span>
                        <span class="user-role"><?php echo ucfirst(sanitizeOutput($current_user['user_role'] ?? 'user')); ?></span>
                    </div>
                </div>
                <div class="user-actions">
                    <a href="<?php echo BASE_URL; ?>auth/profile.php" class="user-action-btn" title="Profile">
                        <i class="fas fa-user-circle"></i>
                    </a>
                    <a href="<?php echo BASE_URL; ?>auth/logout.php" class="user-action-btn" title="Logout">
                        <i class="fas fa-sign-out-alt"></i>
                    </a>
                </div>
            </div>
        </div>
    </aside>
    
    <!-- Sidebar Overlay for Mobile -->
    <div class="sidebar-overlay" id="sidebarOverlay"></div>
    <?php endif; ?>
    
    <?php if (isLoggedIn()): ?>
    <div class="content-wrapper">
        <main class="main-content">
    <?php else: ?>
    <main class="main-content">
    <?php endif; ?>
        <?php if (isset($_GET['success'])): ?>
        <div class="alert alert-success">
            <i class="fas fa-check-circle"></i> <?php echo sanitizeOutput($_GET['success']); ?>
        </div>
        <?php endif; ?>
        
        <?php if (isset($_GET['error'])): ?>
        <div class="alert alert-error">
            <i class="fas fa-exclamation-circle"></i> <?php echo sanitizeOutput($_GET['error']); ?>
        </div>
        <?php endif; ?>
