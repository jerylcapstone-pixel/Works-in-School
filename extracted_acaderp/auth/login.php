<?php
/**
 * Login Page
 * Handles user authentication
 */

require_once __DIR__ . '/../config/config.php';

// Redirect if already logged in
if (isLoggedIn()) {
    header('Location: ' . BASE_URL . 'dashboard/index.php');
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = sanitizeInput($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    
    if (empty($username) || empty($password)) {
        $error = 'Please enter both username and password.';
    } else {
        $pdo = getDBConnection();
        if ($pdo) {
            // Get user with prepared statement
            $stmt = $pdo->prepare("SELECT user_id, username, email, password_hash, user_role, status FROM users WHERE username = ? OR email = ? LIMIT 1");
            $stmt->execute([$username, $username]);
            $user = $stmt->fetch();
            
            if ($user && password_verify($password, $user['password_hash'])) {
                if ($user['status'] !== 'active') {
                    $error = 'Your account is not active. Please contact administrator.';
                } else {
                    // Set session variables
                    $_SESSION['user_id'] = $user['user_id'];
                    $_SESSION['user_role'] = $user['user_role'];
                    $_SESSION['username'] = $user['username'];
                    
                    // Update last login
                    $updateStmt = $pdo->prepare("UPDATE users SET last_login = CURRENT_TIMESTAMP WHERE user_id = ?");
                    $updateStmt->execute([$user['user_id']]);
                    
                    // Redirect to dashboard
                    header('Location: ' . BASE_URL . 'dashboard/index.php');
                    exit;
                }
            } else {
                $error = 'Invalid username or password.';
            }
        } else {
            $error = 'Database connection error. Please try again later.';
        }
    }
}

$page_title = 'Welcome to ' . APP_NAME;
include __DIR__ . '/../includes/header.php';
?>

<style>
/* Homepage Styles */
body {
    margin: 0 !important;
    padding: 0 !important;
    overflow-x: hidden !important;
}

.homepage {
    width: 100%;
    margin: 0;
    padding: 0;
}

/* Navigation */
.home-nav {
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    background: rgba(255, 255, 255, 0.95);
    backdrop-filter: blur(10px);
    border-bottom: 1px solid rgba(226, 232, 240, 0.8);
    padding: 1rem 0;
    z-index: 100;
    box-shadow: 0 2px 20px rgba(0, 0, 0, 0.05);
}

.nav-container {
    max-width: 1400px;
    margin: 0 auto;
    padding: 0 2rem;
    display: flex;
    align-items: center;
    justify-content: space-between;
}

.nav-logo {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    text-decoration: none;
    color: #0f172a;
    font-size: 1.5rem;
    font-weight: 800;
}

.nav-logo i {
    font-size: 2rem;
    color: #1e40af;
}

.nav-actions {
    display: flex;
    gap: 1rem;
    align-items: center;
}

.btn-nav {
    padding: 0.75rem 1.5rem;
    border-radius: 12px;
    font-weight: 600;
    text-decoration: none;
    transition: all 0.3s;
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
}

.btn-nav-primary {
    background: linear-gradient(180deg, #1e40af 0%, #2563eb 100%);
    color: white;
    box-shadow: 0 4px 14px rgba(30, 64, 175, 0.4);
}

.btn-nav-primary:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 20px rgba(30, 64, 175, 0.5);
    background: linear-gradient(180deg, #1d3a9f 0%, #2152d1 100%);
}

.btn-nav-secondary {
    background: #f1f5f9;
    color: #334155;
    border: 2px solid #e2e8f0;
}

.btn-nav-secondary:hover {
    background: #e2e8f0;
}

/* Hero Section */
.hero-section {
    margin-top: 80px;
    min-height: calc(100vh - 80px);
    background: linear-gradient(180deg, #1e40af 0%, #2563eb 100%);
    position: relative;
    overflow: hidden;
    display: flex;
    align-items: center;
    padding: 4rem 2rem;
}

.hero-section::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: 
        radial-gradient(circle at 20% 50%, rgba(255, 215, 0, 0.08) 0%, transparent 50%),
        radial-gradient(circle at 80% 80%, rgba(255, 255, 255, 0.05) 0%, transparent 50%),
        url('data:image/svg+xml,<svg width="100" height="100" xmlns="http://www.w3.org/2000/svg"><defs><pattern id="grid" width="100" height="100" patternUnits="userSpaceOnUse"><path d="M 100 0 L 0 0 0 100" fill="none" stroke="rgba(255,255,255,0.03)" stroke-width="1"/></pattern></defs><rect width="100%" height="100%" fill="url(%23grid)"/></svg>');
}

.hero-container {
    max-width: 1400px;
    margin: 0 auto;
    position: relative;
    z-index: 2;
    text-align: center;
    color: white;
}

.hero-content h1 {
    font-size: 4rem;
    font-weight: 900;
    margin: 0 0 1.5rem 0;
    background: linear-gradient(135deg, #ffffff 0%, #e2e8f0 100%);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    background-clip: text;
    line-height: 1.2;
}

.hero-content p {
    font-size: 1.5rem;
    color: rgba(255, 255, 255, 0.9);
    margin: 0 0 3rem 0;
    max-width: 700px;
    margin-left: auto;
    margin-right: auto;
}

.hero-buttons {
    display: flex;
    gap: 1.5rem;
    justify-content: center;
    flex-wrap: wrap;
}

.btn-hero {
    padding: 1.25rem 2.5rem;
    border-radius: 14px;
    font-size: 1.15rem;
    font-weight: 700;
    text-decoration: none;
    transition: all 0.3s;
    display: inline-flex;
    align-items: center;
    gap: 0.75rem;
    border: none;
    cursor: pointer;
}

.btn-hero-primary {
    background: #ffd700;
    color: #1e40af;
    box-shadow: 0 8px 24px rgba(255, 215, 0, 0.3);
    font-weight: 800;
}

.btn-hero-primary:hover {
    transform: translateY(-3px);
    box-shadow: 0 12px 32px rgba(255, 215, 0, 0.4);
    background: #ffed4e;
}

.btn-hero-secondary {
    background: rgba(255, 255, 255, 0.1);
    color: white;
    border: 2px solid rgba(255, 255, 255, 0.3);
    backdrop-filter: blur(10px);
}

.btn-hero-secondary:hover {
    background: rgba(255, 255, 255, 0.15);
}

/* Features Section */
.features-section {
    padding: 6rem 2rem;
    background: white;
}

.features-container {
    max-width: 1400px;
    margin: 0 auto;
}

.section-header {
    text-align: center;
    margin-bottom: 4rem;
}

.section-header h2 {
    font-size: 3rem;
    font-weight: 800;
    color: #0f172a;
    margin: 0 0 1rem 0;
}

.section-header p {
    font-size: 1.25rem;
    color: #64748b;
    margin: 0;
}

.features-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(320px, 1fr));
    gap: 2rem;
}

.feature-card {
    padding: 2.5rem;
    background: white;
    border-radius: 24px;
    border: 2px solid #e2e8f0;
    transition: all 0.3s;
}

.feature-card:hover {
    transform: translateY(-8px);
    box-shadow: 0 20px 60px rgba(30, 64, 175, 0.2);
    border-color: #1e40af;
}

.feature-icon-box {
    width: 80px;
    height: 80px;
    background: linear-gradient(180deg, #1e40af 0%, #2563eb 100%);
    border-radius: 20px;
    display: flex;
    align-items: center;
    justify-content: center;
    margin-bottom: 1.5rem;
    box-shadow: 0 10px 30px rgba(30, 64, 175, 0.4);
}

.feature-icon-box i {
    font-size: 2.5rem;
    color: #ffd700;
}

.feature-card h3 {
    font-size: 1.5rem;
    font-weight: 700;
    color: #0f172a;
    margin: 0 0 1rem 0;
}

.feature-card p {
    font-size: 1.05rem;
    color: #64748b;
    line-height: 1.7;
    margin: 0;
}

/* Modal Styles */
.modal {
    display: none;
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: rgba(0, 0, 0, 0.2);
    backdrop-filter: blur(4px);
    z-index: 1000;
    padding: 1rem;
    overflow-y: auto;
}

.modal.active {
    display: flex;
    align-items: center;
    justify-content: center;
    animation: fadeIn 0.3s ease;
}

@keyframes fadeIn {
    from { opacity: 0; }
    to { opacity: 1; }
}

.modal-content {
    background: white;
    border-radius: 24px;
    padding: 0;
    max-width: 450px;
    width: 100%;
    max-height: 90vh;
    position: relative;
    animation: slideUp 0.4s ease;
    box-shadow: 
        0 25px 80px rgba(0, 0, 0, 0.4),
        0 0 0 1px rgba(255, 255, 255, 0.1);
    overflow: hidden;
    display: flex;
    flex-direction: column;
}

.modal-content::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    height: 6px;
    background: linear-gradient(90deg, #1e40af 0%, #2563eb 50%, #ffd700 100%);
}

@keyframes slideUp {
    from {
        opacity: 0;
        transform: translateY(30px) scale(0.95);
    }
    to {
        opacity: 1;
        transform: translateY(0) scale(1);
    }
}

.modal-close {
    position: absolute;
    top: 1rem;
    right: 1rem;
    background: rgba(30, 64, 175, 0.08);
    border: 2px solid rgba(30, 64, 175, 0.1);
    width: 40px;
    height: 40px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    transition: all 0.3s;
    color: #64748b;
    font-size: 1.125rem;
    z-index: 10;
}

.modal-close:hover {
    background: linear-gradient(180deg, #1e40af 0%, #2563eb 100%);
    border-color: transparent;
    color: white;
    transform: rotate(90deg);
}

.modal-header-section {
    background: linear-gradient(180deg, #1e40af 0%, #2563eb 100%);
    padding: 2rem 2rem 1.75rem;
    text-align: center;
    position: relative;
    overflow: hidden;
    flex-shrink: 0;
}

.modal-header-section::before {
    content: '';
    position: absolute;
    top: -50%;
    left: -50%;
    width: 200%;
    height: 200%;
    background: 
        radial-gradient(circle at 30% 50%, rgba(255, 215, 0, 0.15) 0%, transparent 50%),
        radial-gradient(circle at 70% 80%, rgba(255, 255, 255, 0.08) 0%, transparent 40%);
}

.modal-logo {
    width: 70px;
    height: 70px;
    background: rgba(255, 255, 255, 0.15);
    backdrop-filter: blur(10px);
    border-radius: 20px;
    display: flex;
    align-items: center;
    justify-content: center;
    margin: 0 auto 1.25rem;
    border: 2px solid rgba(255, 255, 255, 0.2);
    box-shadow: 0 8px 24px rgba(0, 0, 0, 0.15);
    position: relative;
    animation: float 3s ease-in-out infinite;
}

@keyframes float {
    0%, 100% { transform: translateY(0); }
    50% { transform: translateY(-10px); }
}

.modal-logo i {
    font-size: 2.25rem;
    color: #ffd700;
    filter: drop-shadow(0 2px 8px rgba(0, 0, 0, 0.2));
}

.login-header {
    position: relative;
    z-index: 2;
}

.login-header h2 {
    font-size: 1.75rem;
    font-weight: 800;
    color: white !important;
    -webkit-text-fill-color: white !important;
    background: none !important;
    -webkit-background-clip: unset !important;
    background-clip: unset !important;
    margin: 0 0 0.5rem 0;
    text-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
    position: relative;
    z-index: 2;
}

.login-header p {
    color: white !important;
    font-size: 0.95rem;
    margin: 0;
    position: relative;
    z-index: 2;
}

.modal-body {
    padding: 2rem;
    overflow-y: auto;
    flex: 1;
}

.login-alert {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    padding: 1rem 1.25rem;
    background: linear-gradient(135deg, #fef2f2 0%, #fee2e2 100%);
    border: 2px solid #fecaca;
    border-radius: 14px;
    margin-bottom: 1.5rem;
    color: #991b1b;
    box-shadow: 0 4px 12px rgba(220, 38, 38, 0.1);
}

.login-alert i {
    font-size: 1.25rem;
}

.login-form-group {
    margin-bottom: 1.5rem;
}

.login-form-group label {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    font-weight: 700;
    color: #1e293b;
    margin-bottom: 0.75rem;
    font-size: 0.95rem;
}

.login-form-group label i {
    color: #1e40af;
    font-size: 1rem;
}

.input-wrapper {
    position: relative;
}

.login-form-group input {
    width: 100%;
    padding: 1.125rem 1.25rem;
    border: 2px solid #e2e8f0;
    border-radius: 14px;
    font-size: 1rem;
    transition: all 0.3s;
    background: #f8fafc;
    color: #1e293b;
    font-weight: 500;
}

.login-form-group input::placeholder {
    color: #94a3b8;
}

.login-form-group input:hover {
    border-color: #cbd5e1;
    background: white;
}

.login-form-group input:focus {
    outline: none;
    border-color: #1e40af;
    background: white;
    box-shadow: 
        0 0 0 4px rgba(30, 64, 175, 0.1),
        0 4px 12px rgba(30, 64, 175, 0.05);
    transform: translateY(-1px);
}

.password-toggle {
    position: absolute;
    right: 0.75rem;
    top: 50%;
    transform: translateY(-50%);
    background: none;
    border: none;
    color: #64748b;
    cursor: pointer;
    padding: 0.625rem;
    border-radius: 8px;
    transition: all 0.3s;
}

.password-toggle:hover {
    color: #1e40af;
    background: rgba(30, 64, 175, 0.08);
}

.btn-login {
    width: 100%;
    padding: 1.25rem 2rem;
    background: linear-gradient(180deg, #1e40af 0%, #2563eb 100%);
    color: white;
    border: none;
    border-radius: 14px;
    font-size: 1.05rem;
    font-weight: 800;
    cursor: pointer;
    transition: all 0.3s;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 0.75rem;
    margin-top: 0.75rem;
    box-shadow: 
        0 4px 14px rgba(30, 64, 175, 0.4),
        inset 0 1px 0 rgba(255, 255, 255, 0.1);
    position: relative;
    overflow: hidden;
}

.btn-login::before {
    content: '';
    position: absolute;
    top: 0;
    left: -100%;
    width: 100%;
    height: 100%;
    background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.2), transparent);
    transition: left 0.5s;
}

.btn-login:hover::before {
    left: 100%;
}

.btn-login:hover {
    transform: translateY(-3px);
    box-shadow: 
        0 12px 28px rgba(30, 64, 175, 0.5),
        0 0 20px rgba(37, 99, 235, 0.3),
        inset 0 1px 0 rgba(255, 255, 255, 0.2);
    background: linear-gradient(180deg, #1d3a9f 0%, #2152d1 100%);
}

.btn-login:active {
    transform: translateY(-1px);
}

.login-divider {
    text-align: center;
    margin: 1.5rem 0;
    position: relative;
}

.login-divider::before {
    content: '';
    position: absolute;
    top: 50%;
    left: 0;
    right: 0;
    height: 2px;
    background: linear-gradient(90deg, transparent, #e2e8f0, transparent);
}

.login-divider span {
    background: white;
    padding: 0 1.25rem;
    color: #94a3b8;
    font-size: 0.875rem;
    font-weight: 600;
    position: relative;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.btn-register {
    width: 100%;
    padding: 1.125rem 2rem;
    background: linear-gradient(135deg, #f8fafc 0%, #ffffff 100%);
    color: #1e40af;
    border: 2px solid #e2e8f0;
    border-radius: 14px;
    font-size: 1rem;
    font-weight: 700;
    cursor: pointer;
    transition: all 0.3s;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 0.75rem;
    text-decoration: none;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.04);
}

.btn-register i {
    color: #2563eb;
}

.btn-register:hover {
    background: linear-gradient(135deg, #f1f5f9 0%, #f8fafc 100%);
    border-color: #1e40af;
    transform: translateY(-2px);
    box-shadow: 
        0 6px 16px rgba(30, 64, 175, 0.15),
        0 0 0 4px rgba(30, 64, 175, 0.05);
}

.btn-register:active {
    transform: translateY(0);
}

@media (max-width: 768px) {
    .hero-content h1 {
        font-size: 2.5rem;
    }
    .hero-content p {
        font-size: 1.25rem;
    }
    .section-header h2 {
        font-size: 2rem;
    }
    
    .modal {
        padding: 0.5rem;
    }
    
    .modal-content {
        max-width: 100%;
        max-height: 95vh;
        border-radius: 20px;
    }
    
    .modal-header-section {
        padding: 1.5rem 1.5rem 1.25rem;
    }
    
    .modal-logo {
        width: 60px;
        height: 60px;
    }
    
    .modal-logo i {
        font-size: 2rem;
    }
    
    .login-header h2 {
        font-size: 1.5rem;
    }
    
    .modal-body {
        padding: 1.5rem;
    }
}
</style>

<div class="homepage">
    <!-- Navigation -->
    <nav class="home-nav">
        <div class="nav-container">
            <a href="<?php echo BASE_URL; ?>" class="nav-logo">
                <i class="fas fa-graduation-cap"></i>
                <span><?php echo APP_NAME; ?></span>
            </a>
            <div class="nav-actions">
                <button onclick="openLoginModal()" class="btn-nav btn-nav-primary">
                    <i class="fas fa-sign-in-alt"></i>
                    <span>Sign In</span>
                </button>
                <a href="<?php echo BASE_URL; ?>modules/admissions/apply.php" class="btn-nav btn-nav-secondary">
                    <i class="fas fa-user-plus"></i>
                    <span>Apply Now</span>
                </a>
            </div>
        </div>
    </nav>

    <!-- Hero Section -->
    <section class="hero-section">
        <div class="hero-container">
            <div class="hero-content">
                <h1>Welcome to <?php echo APP_NAME; ?></h1>
                <p>Comprehensive Academic Enterprise Resource Planning System for Modern Educational Institutions</p>
                <div class="hero-buttons">
                    <button onclick="openLoginModal()" class="btn-hero btn-hero-primary">
                        <span>Get Started</span>
                        <i class="fas fa-arrow-right"></i>
                    </button>
                    <a href="<?php echo BASE_URL; ?>modules/admissions/apply.php" class="btn-hero btn-hero-secondary">
                        <i class="fas fa-user-graduate"></i>
                        <span>Apply for Admission</span>
                    </a>
                </div>
            </div>
        </div>
    </section>

    <!-- Features Section -->
    <section class="features-section">
        <div class="features-container">
            <div class="section-header">
                <h2>Comprehensive Academic Management</h2>
                <p>Everything you need to manage your educational institution efficiently</p>
            </div>
            <div class="features-grid">
                <div class="feature-card">
                    <div class="feature-icon-box">
                        <i class="fas fa-user-graduate"></i>
                    </div>
                    <h3>Student Information System</h3>
                    <p>Manage student records, enrollment, grades, and academic progress with our comprehensive SIS module.</p>
                </div>
                <div class="feature-card">
                    <div class="feature-icon-box">
                        <i class="fas fa-chalkboard-teacher"></i>
                    </div>
                    <h3>Faculty Management</h3>
                    <p>Streamline faculty administration, course assignments, and academic workload management.</p>
                </div>
                <div class="feature-card">
                    <div class="feature-icon-box">
                        <i class="fas fa-book-open"></i>
                    </div>
                    <h3>Course Management</h3>
                    <p>Create, manage, and schedule courses with integrated curriculum planning and tracking.</p>
                </div>
                <div class="feature-card">
                    <div class="feature-icon-box">
                        <i class="fas fa-clipboard-check"></i>
                    </div>
                    <h3>Attendance Tracking</h3>
                    <p>Automated attendance management with real-time tracking and comprehensive reporting.</p>
                </div>
                <div class="feature-card">
                    <div class="feature-icon-box">
                        <i class="fas fa-file-invoice-dollar"></i>
                    </div>
                    <h3>Billing & Payments</h3>
                    <p>Integrated billing system for tuition fees, payments, and financial aid management.</p>
                </div>
                <div class="feature-card">
                    <div class="feature-icon-box">
                        <i class="fas fa-book-reader"></i>
                    </div>
                    <h3>Digital Library</h3>
                    <p>Access to digital resources, books, and educational materials for students and faculty.</p>
                </div>
            </div>
        </div>
    </section>
</div>

<!-- Login Modal -->
<div id="loginModal" class="modal">
    <div class="modal-content">
        <button class="modal-close" onclick="closeLoginModal()">
            <i class="fas fa-times"></i>
        </button>
        
        <!-- Modal Header with Blue Gradient -->
        <div class="modal-header-section">
            <div class="modal-logo">
                <i class="fas fa-graduation-cap"></i>
            </div>
        <div class="login-header">
            <h2 style="color: white !important; -webkit-text-fill-color: white !important; background: none !important; -webkit-background-clip: unset !important; background-clip: unset !important;">AcadeERP</h2>
                <p style="color: white !important;">Sign in to access your account</p>
            </div>
        </div>
        
        <!-- Modal Body -->
        <div class="modal-body">
        <?php if ($error): ?>
            <div class="login-alert">
                <i class="fas fa-exclamation-circle"></i>
                <span><?php echo sanitizeOutput($error); ?></span>
        </div>
        <?php endif; ?>
        
        <form method="POST" action="" class="login-form">
                <div class="login-form-group">
                    <label for="username">
                        <i class="fas fa-user"></i>
                        <span>Username or Email</span>
                    </label>
                    <div class="input-wrapper">
                        <input type="text" id="username" name="username" required 
                               placeholder="Enter your username or email"
                       value="<?php echo isset($_POST['username']) ? sanitizeOutput($_POST['username']) : ''; ?>">
                    </div>
            </div>
            
                <div class="login-form-group">
                    <label for="password">
                        <i class="fas fa-lock"></i>
                        <span>Password</span>
                    </label>
                    <div class="input-wrapper">
                        <input type="password" id="password" name="password" required
                               placeholder="Enter your password">
                        <button type="button" class="password-toggle" id="togglePassword" aria-label="Toggle password visibility">
                            <i class="fas fa-eye"></i>
                        </button>
                    </div>
            </div>
            
                <button type="submit" class="btn-login">
                    <span>Sign In</span>
                    <i class="fas fa-arrow-right"></i>
            </button>
        </form>
        
            <div class="login-divider">
                <span>or</span>
            </div>
            
            <a href="<?php echo BASE_URL; ?>modules/admissions/apply.php" class="btn-register">
                <i class="fas fa-user-plus"></i>
                <span>New Student Registration</span>
            </a>
        </div>
    </div>
</div>

<script>
// Password visibility toggle
document.addEventListener('DOMContentLoaded', function() {
    const togglePassword = document.getElementById('togglePassword');
    const passwordInput = document.getElementById('password');
    
    if (togglePassword && passwordInput) {
        togglePassword.addEventListener('click', function() {
            const type = passwordInput.getAttribute('type') === 'password' ? 'text' : 'password';
            passwordInput.setAttribute('type', type);
            
            const icon = this.querySelector('i');
            if (type === 'password') {
                icon.classList.remove('fa-eye-slash');
                icon.classList.add('fa-eye');
            } else {
                icon.classList.remove('fa-eye');
                icon.classList.add('fa-eye-slash');
            }
        });
    }
    
    // Open login modal if there's an error
    <?php if ($error): ?>
    openLoginModal();
    <?php endif; ?>
});

// Modal functions
function openLoginModal() {
    document.getElementById('loginModal').classList.add('active');
    document.body.style.overflow = 'hidden';
}

function closeLoginModal() {
    document.getElementById('loginModal').classList.remove('active');
    document.body.style.overflow = '';
}

// Close modal when clicking outside
document.getElementById('loginModal')?.addEventListener('click', function(e) {
    if (e.target === this) {
        closeLoginModal();
    }
});

// Close modal on Escape key
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        closeLoginModal();
    }
});
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
