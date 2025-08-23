<?php
require_once __DIR__ . '/api/database.php';

// HTML output styling
?>
<!DOCTYPE html>
<html>
<head>
    <title>Snaphunt Database Migration v2</title>
    <style>
        body { font-family: monospace; background: #f5f5f5; padding: 20px; }
        .container { max-width: 800px; background: white; padding: 20px; border-radius: 8px; }
        .success { color: #22c55e; font-weight: bold; }
        .error { color: #ef4444; font-weight: bold; }
        .warning { color: #f59e0b; font-weight: bold; }
        .info { color: #3b82f6; }
        .step { margin: 10px 0; padding: 10px; border-left: 4px solid #ddd; }
        .step.success { border-color: #22c55e; background: #f0fdf4; }
        .step.error { border-color: #ef4444; background: #fef2f2; }
        .step.warning { border-color: #f59e0b; background: #fffbeb; }
        .code { background: #f1f5f9; padding: 10px; border-radius: 4px; margin: 5px 0; }
        .table { border-collapse: collapse; width: 100%; margin: 10px 0; }
        .table th, .table td { border: 1px solid #ddd; padding: 8px; text-align: left; }
        .table th { background: #f8f9fa; }
    </style>
</head>
<body>
<div class="container">
<h1>🚀 Snaphunt Database Migration v2</h1>
<p>This will update your database to support capture mechanics and game completion features.</p>

<?php
$results = [];
$hasErrors = false;

try {
    echo "<div class='step'><strong>Step 1: Database Connection Test</strong></div>\n";
    
    $db = new Database();
    $pdo = $db->getConnection();
    echo "<div class='step success'>✅ Database connection successful</div>\n";
    $results['connection'] = true;
    
} catch (Exception $e) {
    echo "<div class='step error'>❌ Database connection failed: " . $e->getMessage() . "</div>\n";
    echo "<p class='error'>Cannot continue without database connection. Check your config.php settings.</p>\n";
    $hasErrors = true;
    $results['connection'] = false;
}

if (!$hasErrors) {
    // Step 2: Check current schema
    echo "<div class='step'><strong>Step 2: Current Schema Analysis</strong></div>\n";
    
    try {
        // Get current tables
        $stmt = $pdo->query("SHOW TABLES");
        $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
        echo "<div class='info'>Current tables: " . implode(', ', $tables) . "</div>\n";
        
        // Check if migration already applied
        $migrationNeeded = [];
        
        // Check for new columns in games table
        $stmt = $pdo->query("DESCRIBE games");
        $gameColumns = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        if (!in_array('started_at', $gameColumns)) $migrationNeeded[] = 'games.started_at column';
        if (!in_array('ended_at', $gameColumns)) $migrationNeeded[] = 'games.ended_at column';
        if (!in_array('winner_team_id', $gameColumns)) $migrationNeeded[] = 'games.winner_team_id column';
        
        // Check for captures table
        if (!in_array('captures', $tables)) $migrationNeeded[] = 'captures table';
        
        // Check for game_stats view
        $stmt = $pdo->query("SHOW FULL TABLES WHERE Table_type = 'VIEW'");
        $views = $stmt->fetchAll(PDO::FETCH_COLUMN);
        if (!in_array('game_stats', $views)) $migrationNeeded[] = 'game_stats view';
        
        if (empty($migrationNeeded)) {
            echo "<div class='step warning'>⚠️  Migration appears to already be applied</div>\n";
            echo "<div class='info'>All expected tables and columns are present. Skipping to verification...</div>\n";
            $results['migration_needed'] = false;
        } else {
            echo "<div class='step info'>📋 Migration needed for: " . implode(', ', $migrationNeeded) . "</div>\n";
            $results['migration_needed'] = true;
        }
        
    } catch (Exception $e) {
        echo "<div class='step error'>❌ Schema analysis failed: " . $e->getMessage() . "</div>\n";
        $hasErrors = true;
    }
}

if (!$hasErrors && $results['migration_needed']) {
    // Step 3: Execute Migration
    echo "<div class='step'><strong>Step 3: Executing Migration</strong></div>\n";
    
    try {
        // Read migration file
        $migrationFile = __DIR__ . '/sql/migrate_v2.sql';
        if (!file_exists($migrationFile)) {
            throw new Exception("Migration file not found: $migrationFile");
        }
        
        $sql = file_get_contents($migrationFile);
        if (!$sql) {
            throw new Exception("Could not read migration file");
        }
        
        echo "<div class='info'>📂 Migration file loaded (" . number_format(strlen($sql)) . " characters)</div>\n";
        
        // Execute migration in transaction
        $pdo->beginTransaction();
        
        // Split SQL by semicolons and execute each statement
        $statements = preg_split('/;\s*$/m', $sql);
        $executed = 0;
        
        foreach ($statements as $statement) {
            $statement = trim($statement);
            if (empty($statement)) continue;
            
            // Skip DELIMITER statements (not needed in PHP)
            if (strpos($statement, 'DELIMITER') === 0) continue;
            
            try {
                $pdo->exec($statement);
                $executed++;
            } catch (PDOException $e) {
                // Ignore "already exists" errors
                if (strpos($e->getMessage(), 'already exists') !== false) {
                    echo "<div class='warning'>⚠️  Skipped (already exists): " . substr($statement, 0, 50) . "...</div>\n";
                } else {
                    throw $e;
                }
            }
        }
        
        $pdo->commit();
        echo "<div class='step success'>✅ Migration executed successfully ($executed statements)</div>\n";
        $results['migration_executed'] = true;
        
    } catch (Exception $e) {
        $pdo->rollBack();
        echo "<div class='step error'>❌ Migration failed: " . $e->getMessage() . "</div>\n";
        $hasErrors = true;
        $results['migration_executed'] = false;
    }
}

// Step 4: Verification
echo "<div class='step'><strong>Step 4: Post-Migration Verification</strong></div>\n";

try {
    // Verify games table columns
    echo "<h3>📋 Games Table Structure:</h3>\n";
    $stmt = $pdo->query("DESCRIBE games");
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "<table class='table'><tr><th>Column</th><th>Type</th><th>Null</th><th>Default</th></tr>";
    foreach ($columns as $col) {
        echo "<tr><td>{$col['Field']}</td><td>{$col['Type']}</td><td>{$col['Null']}</td><td>{$col['Default']}</td></tr>";
    }
    echo "</table>";
    
    $requiredColumns = ['started_at', 'ended_at', 'winner_team_id'];
    $currentColumns = array_column($columns, 'Field');
    
    foreach ($requiredColumns as $required) {
        if (in_array($required, $currentColumns)) {
            echo "<div class='success'>✅ Column '$required' exists</div>\n";
        } else {
            echo "<div class='error'>❌ Column '$required' missing</div>\n";
            $hasErrors = true;
        }
    }
    
    // Verify captures table
    echo "<h3>📋 Captures Table:</h3>\n";
    $stmt = $pdo->query("SHOW TABLES LIKE 'captures'");
    if ($stmt->fetch()) {
        echo "<div class='success'>✅ Captures table exists</div>\n";
        
        $stmt = $pdo->query("DESCRIBE captures");
        $captureColumns = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo "<table class='table'><tr><th>Column</th><th>Type</th><th>Key</th></tr>";
        foreach ($captureColumns as $col) {
            echo "<tr><td>{$col['Field']}</td><td>{$col['Type']}</td><td>{$col['Key']}</td></tr>";
        }
        echo "</table>";
    } else {
        echo "<div class='error'>❌ Captures table missing</div>\n";
        $hasErrors = true;
    }
    
    // Verify indexes
    echo "<h3>📋 Index Verification:</h3>\n";
    $stmt = $pdo->query("SHOW INDEX FROM location_pings WHERE Key_name = 'unique_player_location'");
    if ($stmt->fetch()) {
        echo "<div class='success'>✅ Location pings unique constraint exists</div>\n";
    } else {
        echo "<div class='warning'>⚠️  Location pings unique constraint missing</div>\n";
    }
    
    // Verify view
    echo "<h3>📋 Views:</h3>\n";
    $stmt = $pdo->query("SHOW FULL TABLES WHERE Table_type = 'VIEW'");
    $views = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    if (in_array('game_stats', $views)) {
        echo "<div class='success'>✅ game_stats view exists</div>\n";
        
        // Test view
        $stmt = $pdo->query("SELECT COUNT(*) as count FROM game_stats");
        $count = $stmt->fetch();
        echo "<div class='info'>📊 Game stats view working (shows {$count['count']} games)</div>\n";
    } else {
        echo "<div class='warning'>⚠️  game_stats view missing</div>\n";
    }
    
    // Test new functionality
    echo "<h3>🧪 Functionality Tests:</h3>\n";
    
    // Test 1: Insert test capture (if data exists)
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM players p JOIN teams t ON p.team_id = t.id WHERE t.role IN ('hunter', 'hunted')");
    $playerCount = $stmt->fetch();
    
    if ($playerCount['count'] >= 2) {
        echo "<div class='info'>🎯 Found {$playerCount['count']} players - capture mechanics ready for testing</div>\n";
    } else {
        echo "<div class='info'>📝 No test data yet - create some games/teams to test capture mechanics</div>\n";
    }
    
    // Test 2: Foreign key constraints
    try {
        $stmt = $pdo->query("SELECT CONSTRAINT_NAME FROM information_schema.TABLE_CONSTRAINTS 
                            WHERE TABLE_NAME = 'captures' AND CONSTRAINT_TYPE = 'FOREIGN KEY'");
        $fkCount = $stmt->rowCount();
        echo "<div class='success'>✅ Captures table has $fkCount foreign key constraints</div>\n";
    } catch (Exception $e) {
        echo "<div class='warning'>⚠️  Could not verify foreign keys</div>\n";
    }
    
} catch (Exception $e) {
    echo "<div class='step error'>❌ Verification failed: " . $e->getMessage() . "</div>\n";
    $hasErrors = true;
}

// Final summary
echo "<hr><h2>🏁 Migration Summary</h2>\n";

if ($hasErrors) {
    echo "<div class='step error'>❌ Migration completed with errors - please review the issues above</div>\n";
} else {
    echo "<div class='step success'>✅ Migration completed successfully!</div>\n";
    echo "<div class='info'>Your database is now ready for:</div>";
    echo "<ul>";
    echo "<li>🎯 Player capture mechanics</li>";
    echo "<li>🏆 Game winner tracking</li>";
    echo "<li>📊 Game statistics and history</li>";
    echo "<li>⏱️  Game timing (started_at, ended_at)</li>";
    echo "<li>🔒 Data integrity with constraints and triggers</li>";
    echo "</ul>";
    
    echo "<div class='code'>";
    echo "<strong>Next Steps:</strong><br>";
    echo "1. Continue with Ticket 4: Frontend JavaScript Architecture<br>";
    echo "2. Test the capture API endpoints<br>";
    echo "3. Create some test games to verify everything works<br>";
    echo "</div>";
}

echo "<p><strong>Migration completed at:</strong> " . date('Y-m-d H:i:s') . "</p>\n";
?>

<p><a href="index.html">← Back to Snaphunt</a> | <a href="debug.php">Run Debug Check</a></p>

</div>
</body>
</html>