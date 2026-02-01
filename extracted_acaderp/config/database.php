<?php
/**
 * Database Configuration File
 * Centralized database connection settings for the Academic Management System
 */

// Database credentials
define('DB_HOST', 'localhost');
define('DB_USER', 'u753744081_acaderp');
define('DB_PASS', 'Itp17_acaderp');
define('DB_NAME', 'u753744081_acaderp');

/**
 * Create database connection using PDO with prepared statements
 * @return PDO|null Returns PDO connection object or null on failure
 */
function getDBConnection() {
    try {
        $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4";
        $options = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ];
        
        $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
        return $pdo;
    } catch (PDOException $e) {
        error_log("Database Connection Error: " . $e->getMessage());
        return null;
    }
}

/**
 * Initialize database and create tables if they don't exist
 * This should be run once during installation
 */
function initializeDatabase() {
    $pdo = getDBConnection();
    if (!$pdo) {
        return false;
    }
    
    // Read and execute schema file
    $schemaFile = __DIR__ . '/../database/schema.sql';
    if (file_exists($schemaFile)) {
        $schema = file_get_contents($schemaFile);
        $pdo->exec($schema);
        return true;
    }
    return false;
}
?>
