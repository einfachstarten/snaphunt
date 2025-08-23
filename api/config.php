<?php
// CORS headers
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

// Database connection constants
define('DB_HOST', 'mysqlsvr84.world4you.com');
define('DB_NAME', '7951508db1');
define('DB_USER', 'sql7549177');
define('DB_PASS', 'jg*ha@di');

// Upload settings
define('UPLOAD_DIR', __DIR__ . '/../uploads/');
define('MAX_FILE_SIZE', 5 * 1024 * 1024); // 5MB
define('ALLOWED_EXTENSIONS', ['jpg', 'jpeg', 'png']);
?>
