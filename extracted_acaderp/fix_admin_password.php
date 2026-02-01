<?php
/**
 * Fix Admin Password Script
 * This script updates the admin user's password hash to work with password "admin123"
 * 
 * IMPORTANT: Delete this file after running it for security!
 */

require_once __DIR__ . '/config/config.php';

$pdo = getDBConnection();

if (!$pdo) {
    die('❌ Database connection failed. Please check your database configuration in config/database.php');
}

// Correct password hash for "admin123"
$correct_hash = '$2y$10$7ev6tJ6Hev5yIqMLsXAQR.enVPpNTK4FI70CqdT2N7h8g1xLoGk8O';

try {
    // Check if admin user exists
    $stmt = $pdo->prepare("SELECT user_id, username, email FROM users WHERE username = 'admin' LIMIT 1");
    $stmt->execute();
    $admin = $stmt->fetch();
    
    if ($admin) {
        // Update the password hash
        $updateStmt = $pdo->prepare("UPDATE users SET password_hash = ? WHERE username = 'admin'");
        $updateStmt->execute([$correct_hash]);
        
        echo "✅ Admin password has been updated successfully!\n";
        echo "You can now login with:\n";
        echo "Username: admin\n";
        echo "Password: admin123\n\n";
        echo "⚠️  Please delete this file (fix_admin_password.php) for security!\n";
    } else {
        // Create admin user if it doesn't exist
        $insertStmt = $pdo->prepare("INSERT INTO users (username, email, password_hash, user_role, status) 
                                     VALUES ('admin', 'admin@university.edu', ?, 'admin', 'active')");
        $insertStmt->execute([$correct_hash]);
        
        echo "✅ Admin user has been created successfully!\n";
        echo "You can now login with:\n";
        echo "Username: admin\n";
        echo "Password: admin123\n\n";
        echo "⚠️  Please delete this file (fix_admin_password.php) for security!\n";
    }
} catch (PDOException $e) {
    die('❌ Error: ' . $e->getMessage());
}
?>
