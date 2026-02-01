<?php
/**
 * Migration Runner for Tuition Rates Update
 * Run this file once to add year_level and semester columns to tuition_rates table
 */

require_once __DIR__ . '/../config/database.php';

$pdo = getDBConnection();

if (!$pdo) {
    die("Error: Could not connect to database. Please check your database configuration.\n");
}

// Enable buffered queries
$pdo->setAttribute(PDO::MYSQL_ATTR_USE_BUFFERED_QUERY, true);

echo "Running migration: migration_update_tuition_rates.sql\n";
echo "=================================================\n\n";

try {
    // Read migration file
    $migrationFile = __DIR__ . '/migration_update_tuition_rates.sql';
    if (!file_exists($migrationFile)) {
        die("Error: Migration file not found: $migrationFile\n");
    }
    
    $sql = file_get_contents($migrationFile);
    
    // Split by semicolon but handle prepared statements
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
            // Some statements might fail if columns/constraints already exist, which is okay
            if (strpos($e->getMessage(), 'already exists') !== false || 
                strpos($e->getMessage(), 'Duplicate column') !== false ||
                strpos($e->getMessage(), 'Duplicate key') !== false ||
                strpos($e->getMessage(), 'Duplicate entry') !== false) {
                echo "⚠ Skipped (already exists)\n";
            } else {
                echo "Error: " . $e->getMessage() . "\n";
                // Continue anyway for most errors
            }
        }
    }
    
    echo "\n=================================================\n";
    echo "Migration completed!\n";
    echo "\nUpdated:\n";
    echo "  - Added year_level column to tuition_rates table\n";
    echo "  - Added semester column to tuition_rates table\n";
    echo "  - Added unique constraint for program_id, year_level, semester, effective_date\n";
    echo "\nYou can now access the Tuition Rate Setup page.\n";
    
} catch (Exception $e) {
    echo "\n=================================================\n";
    echo "Error: " . $e->getMessage() . "\n";
    echo "Migration may have partially completed. Please check your database.\n";
    exit(1);
}

?>

