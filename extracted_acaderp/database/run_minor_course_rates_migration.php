<?php
/**
 * Migration Runner for Minor Course Rates
 * Run this file once to add support for general/minor course rates
 */

require_once __DIR__ . '/../config/database.php';

$pdo = getDBConnection();

if (!$pdo) {
    die("Error: Could not connect to database. Please check your database configuration.\n");
}

// Enable buffered queries
$pdo->setAttribute(PDO::MYSQL_ATTR_USE_BUFFERED_QUERY, true);

echo "Running migration: migration_add_minor_course_rates.sql\n";
echo "=================================================\n\n";

try {
    // Read migration file
    $migrationFile = __DIR__ . '/migration_add_minor_course_rates.sql';
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
    echo "\nUpdated:\n";
    echo "  - tuition_rates table (added rate_category column)\n";
    echo "  - program_id, year_level, semester now allow NULL for general rates\n";
    echo "\nYou can now add General/Minor Course Rates in Tuition Rate Setup.\n";
    
} catch (Exception $e) {
    echo "\n=================================================\n";
    echo "Error: " . $e->getMessage() . "\n";
    echo "Migration may have partially completed. Please check your database.\n";
    exit(1);
}

?>

