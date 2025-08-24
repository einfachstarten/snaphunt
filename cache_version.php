<?php
// Helper function for dynamic cache busting
function get_cache_version($type = 'css') {
    $config = include __DIR__ . '/config/version.php';
    
    switch ($type) {
        case 'css':
            return $config['css_version'];
        case 'js':
            return $config['js_version'];
        case 'dynamic':
            return $config['cache_timestamp'];
        default:
            return time(); // Fallback to current timestamp
    }
}

function cache_bust_url($file_path, $type = 'auto') {
    // Auto-detect type from file extension
    if ($type === 'auto') {
        $extension = pathinfo($file_path, PATHINFO_EXTENSION);
        $type = ($extension === 'css') ? 'css' : 'js';
    }
    
    $version = get_cache_version($type);
    $separator = (strpos($file_path, '?') !== false) ? '&' : '?';
    
    return $file_path . $separator . 'v=' . $version;
}

// Output current version for debugging
function show_cache_versions() {
    $config = include __DIR__ . '/config/version.php';
    header('Content-Type: application/json');
    echo json_encode([
        'current_time' => date('Y-m-d H:i:s'),
        'versions' => $config,
        'cache_busted_urls' => [
            'main_css' => cache_bust_url('assets/css/main.css'),
            'game_js' => cache_bust_url('assets/js/game.js'),
            'admin_js' => cache_bust_url('assets/js/admin.js')
        ]
    ]);
}

// If called directly, show versions
if (basename($_SERVER['PHP_SELF']) === 'cache_version.php') {
    show_cache_versions();
}
?>
