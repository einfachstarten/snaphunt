<?php
// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h1>🔍 Admin Debug Check</h1>";
echo "<style>body{font-family:monospace;padding:20px;} .success{color:green;} .error{color:red;} .info{color:blue;}</style>";

// Check 1: PHP version
echo "<h2>PHP Environment</h2>";
echo "<p class='info'>PHP Version: " . phpversion() . "</p>";

// Check 2: Required files
echo "<h2>File Check</h2>";
$files = [
    'admin.php' => __DIR__ . '/admin.php',
    'api/admin.php' => __DIR__ . '/api/admin.php',
    'api/database.php' => __DIR__ . '/api/database.php',
    'api/config.php' => __DIR__ . '/api/config.php'
];

foreach ($files as $name => $path) {
    if (file_exists($path)) {
        echo "<p class='success'>✅ $name exists</p>";
    } else {
        echo "<p class='error'>❌ $name MISSING</p>";
    }
}

// Check 3: Database connection
echo "<h2>Database Connection Test</h2>";
try {
    require_once __DIR__ . '/api/database.php';
    $db = new Database();
    $pdo = $db->getConnection();
    echo "<p class='success'>✅ Database connection works</p>";
    
    // Test admin API
    echo "<h2>Admin API Test</h2>";
    $testUrl = 'api/admin.php?action=stats';
    echo "<p class='info'>Testing: $testUrl</p>";
    
    // Simple test of admin API
    $_GET['action'] = 'stats';
    ob_start();
    try {
        include __DIR__ . '/api/admin.php';
        $output = ob_get_contents();
        ob_end_clean();
        
        $data = json_decode($output, true);
        if ($data && isset($data['success']) && $data['success']) {
            echo "<p class='success'>✅ Admin API working</p>";
            echo "<p class='info'>Sample stats: " . json_encode($data['stats']) . "</p>";
        } else {
            echo "<p class='error'>❌ Admin API returns invalid data</p>";
            echo "<pre>$output</pre>";
        }
    } catch (Exception $e) {
        ob_end_clean();
        echo "<p class='error'>❌ Admin API error: " . $e->getMessage() . "</p>";
    }
    
} catch (Exception $e) {
    echo "<p class='error'>❌ Database connection failed: " . $e->getMessage() . "</p>";
}

// Check 4: Session support
echo "<h2>Session Support</h2>";
if (function_exists('session_start')) {
    echo "<p class='success'>✅ Session support available</p>";
} else {
    echo "<p class='error'>❌ Session support missing</p>";
}

// Check 5: Try to run admin.php and catch errors
echo "<h2>Admin.php Execution Test</h2>";
try {
    ob_start();
    $error_occurred = false;
    
    // Capture any errors
    set_error_handler(function($severity, $message, $file, $line) use (&$error_occurred) {
        global $admin_errors;
        $admin_errors[] = "Error: $message in $file on line $line";
        $error_occurred = true;
    });
    
    // Try to include admin.php
    include __DIR__ . '/admin.php';
    $admin_output = ob_get_contents();
    ob_end_clean();
    
    restore_error_handler();
    
    if (!$error_occurred && strlen($admin_output) > 100) {
        echo "<p class='success'>✅ admin.php loads successfully (" . strlen($admin_output) . " bytes)</p>";
    } else if ($error_occurred) {
        echo "<p class='error'>❌ admin.php has errors:</p>";
        if (isset($admin_errors)) {
            foreach ($admin_errors as $error) {
                echo "<p class='error'>- $error</p>";
            }
        }
    } else {
        echo "<p class='error'>❌ admin.php produced no output</p>";
    }
    
} catch (Exception $e) {
    ob_end_clean();
    echo "<p class='error'>❌ admin.php execution failed: " . $e->getMessage() . "</p>";
}

// Check 6: Permissions
echo "<h2>File Permissions</h2>";
$adminFile = __DIR__ . '/admin.php';
if (file_exists($adminFile)) {
    $perms = fileperms($adminFile);
    echo "<p class='info'>admin.php permissions: " . sprintf('%o', $perms & 0777) . "</p>";
    
    if (is_readable($adminFile)) {
        echo "<p class='success'>✅ admin.php is readable</p>";
    } else {
        echo "<p class='error'>❌ admin.php is not readable</p>";
    }
}

// Direct link test
echo "<h2>🔗 Direct Access Links</h2>";
$baseUrl = 'http' . (isset($_SERVER['HTTPS']) ? 's' : '') . '://' . $_SERVER['HTTP_HOST'] . dirname($_SERVER['REQUEST_URI']);
echo "<p><a href='$baseUrl/admin.php' target='_blank'>Try admin.php directly</a></p>";
echo "<p><a href='$baseUrl/api/admin.php?action=stats' target='_blank'>Try admin API directly</a></p>";

echo "<hr><p><strong>Next Steps:</strong></p>";
echo "<ul>";
echo "<li>First run <a href='check_migration.php'>check_migration.php</a> to verify database</li>";
echo "<li>Then try the direct links above</li>";
echo "<li>If admin.php still doesn't work, check PHP error logs</li>";
echo "</ul>";
?>