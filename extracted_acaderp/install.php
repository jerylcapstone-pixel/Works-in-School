<?php
/**
 * Database Installation Script
 * Run this file once to set up the database and create default admin user
 * 
 * IMPORTANT: Delete this file after installation for security!
 */

// Database configuration
$db_host = 'localhost';
$db_user = 'root';
$db_pass = '';
$db_name = 'academic_management_system';

// Check if already installed
if (file_exists(__DIR__ . '/config/database.php')) {
    require_once __DIR__ . '/config/database.php';
    $pdo = getDBConnection();
    if ($pdo) {
        try {
            $stmt = $pdo->query("SELECT COUNT(*) FROM users");
            if ($stmt->fetchColumn() > 0) {
                die('Database already installed! Please delete install.php for security.');
            }
        } catch (PDOException $e) {
            // Database or tables don't exist yet, continue with installation
        }
    }
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Database Installation - Academic Management System</title>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            max-width: 800px;
            margin: 50px auto;
            padding: 20px;
            background: #f5f5f5;
        }
        .container {
            background: white;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        h1 { color: #2563eb; }
        .success { color: #10b981; padding: 10px; background: #d1fae5; border-radius: 5px; margin: 10px 0; }
        .error { color: #ef4444; padding: 10px; background: #fee2e2; border-radius: 5px; margin: 10px 0; }
        .info { color: #3b82f6; padding: 10px; background: #dbeafe; border-radius: 5px; margin: 10px 0; }
        code { background: #f3f4f6; padding: 2px 6px; border-radius: 3px; }
    </style>
</head>
<body>
    <div class="container">
        <h1>🚀 Academic Management System - Database Installation</h1>
        
        <?php
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $db_host = $_POST['db_host'] ?? 'localhost';
            $db_user = $_POST['db_user'] ?? 'root';
            $db_pass = $_POST['db_pass'] ?? '';
            $db_name = $_POST['db_name'] ?? 'academic_management_system';
            
            try {
                // Connect to MySQL server (without database)
                $pdo = new PDO("mysql:host=$db_host", $db_user, $db_pass);
                $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                
                // Create database if not exists
                $pdo->exec("CREATE DATABASE IF NOT EXISTS `$db_name` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
                echo '<div class="success">✅ Database created successfully!</div>';
                
                // Connect to the database
                $pdo->exec("USE `$db_name`");
                
                // Read and execute schema
                $schemaFile = __DIR__ . '/database/schema.sql';
                if (file_exists($schemaFile)) {
                    $schema = file_get_contents($schemaFile);
                    // Remove CREATE DATABASE statements if any
                    $schema = preg_replace('/CREATE DATABASE.*?;/i', '', $schema);
                    $schema = preg_replace('/USE.*?;/i', '', $schema);
                    
                    // Split by semicolons and execute each statement
                    $statements = array_filter(array_map('trim', explode(';', $schema)));
                    foreach ($statements as $statement) {
                        if (!empty($statement)) {
                            $pdo->exec($statement);
                        }
                    }
                    echo '<div class="success">✅ Database tables created successfully!</div>';
                } else {
                    echo '<div class="error">❌ Schema file not found: ' . $schemaFile . '</div>';
                }
                
                echo '<div class="success">✅ Installation completed successfully!</div>';
                echo '<div class="info">📝 <strong>Default Login Credentials:</strong><br>';
                echo 'Username: <code>admin</code><br>';
                echo 'Password: <code>admin123</code><br>';
                echo '<br>⚠️ <strong>Please change the default password after first login!</strong></div>';
                echo '<div class="info">🔒 <strong>Security:</strong> Please delete <code>install.php</code> file now!</div>';
                echo '<div class="info"><a href="index.php">➡️ Go to Login Page</a></div>';
                
            } catch (PDOException $e) {
                echo '<div class="error">❌ Error: ' . htmlspecialchars($e->getMessage()) . '</div>';
            }
        } else {
        ?>
        
        <p>This script will:</p>
        <ul>
            <li>Create the database (if it doesn't exist)</li>
            <li>Create all required tables</li>
            <li>Insert default admin user</li>
        </ul>
        
        <form method="POST">
            <h3>Database Configuration</h3>
            <p>
                <label>Database Host:</label><br>
                <input type="text" name="db_host" value="<?php echo htmlspecialchars($db_host); ?>" style="width: 100%; padding: 8px; margin-top: 5px;">
            </p>
            <p>
                <label>Database User:</label><br>
                <input type="text" name="db_user" value="<?php echo htmlspecialchars($db_user); ?>" style="width: 100%; padding: 8px; margin-top: 5px;">
            </p>
            <p>
                <label>Database Password:</label><br>
                <input type="password" name="db_pass" value="<?php echo htmlspecialchars($db_pass); ?>" style="width: 100%; padding: 8px; margin-top: 5px;">
            </p>
            <p>
                <label>Database Name:</label><br>
                <input type="text" name="db_name" value="<?php echo htmlspecialchars($db_name); ?>" style="width: 100%; padding: 8px; margin-top: 5px;">
            </p>
            <p>
                <button type="submit" style="background: #2563eb; color: white; padding: 12px 24px; border: none; border-radius: 5px; cursor: pointer; font-size: 16px;">
                    Install Database
                </button>
            </p>
        </form>
        
        <?php } ?>
    </div>
</body>
</html>
