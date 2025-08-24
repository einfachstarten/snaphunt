<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Snaphunt</title>
    
    <!-- Aggressive Cache Control -->
    <meta http-equiv="Cache-Control" content="no-cache, no-store, must-revalidate, max-age=0" />
    <meta http-equiv="Pragma" content="no-cache" />
    <meta http-equiv="Expires" content="Thu, 01 Jan 1970 00:00:00 GMT" />
    
    <?php 
    require_once 'cache_version.php';
    $css_version = get_cache_version('css');
    $js_version = get_cache_version('js');
    ?>
    
    <!-- Dynamic Cache-Busted CSS -->
    <link rel="stylesheet" href="assets/css/main.css?v=<?php echo $css_version; ?>&t=<?php echo time(); ?>" />
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
</head>
<body>
    <div id="loading-screen" class="screen active"><div class="spinner"></div></div>
    
    <!-- ... all existing HTML content ... -->

    <!-- Dynamic Cache-Busted JavaScript -->
    <script>
        // Client-side cache busting validation
        console.log('🔄 Cache versions loaded:', {
            css: '<?php echo $css_version; ?>',
            js: '<?php echo $js_version; ?>',
            timestamp: '<?php echo date('Y-m-d H:i:s'); ?>'
        });
    </script>
    
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    <script src="assets/js/game.js?v=<?php echo $js_version; ?>&t=<?php echo time(); ?>"></script>
</body>
</html>
