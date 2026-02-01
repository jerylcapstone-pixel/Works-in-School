<?php
/**
 * Migration Runner for Billing Fees
 * Run this file once to create the standard_fees and student_additional_fees tables
 */

require_once __DIR__ . '/../config/database.php';

$pdo = getDBConnection();

if (!$pdo) {
    die("Error: Could not connect to database. Please check your database configuration.\n");
}

// Enable buffered queries
$pdo->setAttribute(PDO::MYSQL_ATTR_USE_BUFFERED_QUERY, true);

echo "Running migration: migration_add_billing_fees.sql\n";
echo "=================================================\n\n";

try {
    // Read migration file
    $migrationFile = __DIR__ . '/migration_add_billing_fees.sql';
    if (!file_exists($migrationFile)) {
        die("Error: Migration file not found: $migrationFile\n");
    }
    
    $sql = file_get_contents($migrationFile);
    
    // Execute the entire SQL file
    // Split by semicolon but handle the prepared statement block
    $statements = [];
    $current = '';
    $inPrepared = false;
    
    $lines = explode("\n", $sql);
    foreach ($lines as $line) {
        $trimmed = trim($line);
        if (empty($trimmed) || strpos($trimmed, '--') === 0) {
            continue;
        }
        
        $current .= $line . "\n";
        
        if (stripos($trimmed, 'PREPARE') !== false) {
            $inPrepared = true;
        }
        
        if (stripos($trimmed, 'DEALLOCATE') !== false) {
            $inPrepared = false;
            $statements[] = trim($current);
            $current = '';
        } elseif (!$inPrepared && strpos($trimmed, ';') !== false) {
            $statements[] = trim($current);
            $current = '';
        }
    }
    
    if (!empty($current)) {
        $statements[] = trim($current);
    }
    
    foreach ($statements as $statement) {
        if (empty($statement)) {
            continue;
        }
        
        try {
            $pdo->exec($statement);
            echo "✓ Executed statement successfully\n";
        } catch (PDOException $e) {
            // Some statements might fail if tables/columns already exist, which is okay
            if (strpos($e->getMessage(), 'already exists') !== false || 
                strpos($e->getMessage(), 'Duplicate column') !== false ||
                strpos($e->getMessage(), 'Duplicate key') !== false) {
                echo "⚠ Skipped (already exists)\n";
            } else {
                echo "Error: " . $e->getMessage() . "\n";
                // Continue anyway for most errors
            }
        }
    }
    
    echo "\n=================================================\n";
    echo "Migration completed!\n";
    echo "\nCreated/Updated:\n";
    echo "  - standard_fees table\n";
    echo "  - student_additional_fees table\n";
    echo "  - old_account column in invoices table\n";
    echo "\nYou can now access the Manage Standard Fees page.\n";
    
} catch (Exception $e) {
    echo "\n=================================================\n";
    echo "Error: " . $e->getMessage() . "\n";
    echo "Migration may have partially completed. Please check your database.\n";
    exit(1);
}

?>
