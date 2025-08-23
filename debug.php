<?php
// debug.php - Diagnose Script
echo "<h2>Snaphunt Setup Diagnose</h2>\n";

echo "<h3>1. File Structure Check</h3>\n";

// Check if required files exist
$requiredFiles = [
    'api/config.php',
    'api/database.php', 
    'sql/schema.sql',
    'index.html',
    'assets/css/main.css'
];

foreach ($requiredFiles as $file) {
    if (file_exists($file)) {
        echo "✅ $file exists<br>\n";
    } else {
        echo "❌ $file MISSING<br>\n";
    }
}

echo "<h3>2. Directory Structure</h3>\n";
echo "Current directory: " . getcwd() . "<br>\n";
echo "Files in current directory:<br>\n";
$files = scandir('.');
foreach ($files as $file) {
    if ($file != '.' && $file != '..') {
        echo "- $file" . (is_dir($file) ? '/' : '') . "<br>\n";
    }
}

if (is_dir('sql')) {
    echo "<br>Files in sql/ directory:<br>\n";
    $sqlFiles = scandir('sql');
    foreach ($sqlFiles as $file) {
        if ($file != '.' && $file != '..') {
            echo "- sql/$file<br>\n";
        }
    }
}

echo "<h3>3. Config Check</h3>\n";
if (file_exists('api/config.php')) {
    echo "Config file exists. Checking contents...<br>\n";
    
    // Read config without executing to avoid errors
    $configContent = file_get_contents('api/config.php');
    
    if (strpos($configContent, 'username') !== false) {
        echo "⚠️ DB_USER still contains placeholder 'username'<br>\n";
    }
    if (strpos($configContent, 'password') !== false) {
        echo "⚠️ DB_PASS still contains placeholder 'password'<br>\n";
    }
    
    echo "Config content preview:<br>\n";
    echo "<pre>" . htmlspecialchars(substr($configContent, 0, 500)) . "</pre>\n";
} else {
    echo "❌ api/config.php missing<br>\n";
}

echo "<h3>4. Database Connection Test</h3>\n";
if (file_exists('api/config.php')) {
    try {
        require_once 'api/config.php';
        
        echo "Attempting connection with:<br>\n";
        echo "Host: " . DB_HOST . "<br>\n";
        echo "Database: " . DB_NAME . "<br>\n";
        echo "User: " . DB_USER . "<br>\n";
        echo "Password: " . (DB_PASS === 'password' ? 'PLACEHOLDER - NEEDS UPDATE' : '***SET***') . "<br>\n";
        
        if (DB_USER === 'username' || DB_PASS === 'password') {
            echo "<br>❌ <strong>Database credentials not configured!</strong><br>\n";
            echo "You need to edit api/config.php with your real world4you database credentials.<br>\n";
        } else {
            // Try connection
            $pdo = new PDO(
                "mysql:host=" . DB_HOST . ";charset=utf8mb4",
                DB_USER, 
                DB_PASS,
                [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
            );
            echo "✅ Database connection successful!<br>\n";
            
            // Check if database exists
            $result = $pdo->query("SHOW DATABASES LIKE '" . DB_NAME . "'");
            if ($result->rowCount() > 0) {
                echo "✅ Database '" . DB_NAME . "' exists<br>\n";
            } else {
                echo "⚠️ Database '" . DB_NAME . "' does not exist yet<br>\n";
            }
        }
        
    } catch (Exception $e) {
        echo "❌ Database connection failed: " . $e->getMessage() . "<br>\n";
    }
}

echo "<h3>5. Schema File Check</h3>\n";
if (file_exists('sql/schema.sql')) {
    $schemaContent = file_get_contents('sql/schema.sql');
    echo "✅ schema.sql exists (" . strlen($schemaContent) . " bytes)<br>\n";
    echo "Schema preview:<br>\n";
    echo "<pre>" . htmlspecialchars(substr($schemaContent, 0, 300)) . "...</pre>\n";
} else {
    echo "❌ sql/schema.sql missing<br>\n";
}

?>
<!DOCTYPE html>
<html>
<head>
    <title>Snaphunt Debug</title>
    <style>
        body { font-family: Arial, sans-serif; max-width: 800px; margin: 20px auto; padding: 20px; }
        h2, h3 { color: #2563eb; }
        pre { background: #f8f9fa; padding: 10px; border-radius: 4px; overflow-x: auto; }
    </style>
</head>
<body>
    <hr>
    <h3>Next Steps:</h3>
    <ol>
        <li>If files are missing: Re-run the setup ticket</li>
        <li>If config has placeholders: Edit api/config.php with your real world4you credentials</li>
        <li>If database connection fails: Check your world4you database settings</li>
        <li>Once everything is ✅: Run setup_db.php again</li>
    </ol>
</body>
</html>