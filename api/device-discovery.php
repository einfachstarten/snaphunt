<?php
require_once __DIR__ . '/database.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

function send_response(int $status, array $data): void
{
    http_response_code($status);
    echo json_encode($data);
    exit;
}

// Simple file-based discovery as fallback if database fails
$discovery_file = __DIR__ . '/../uploads/device_discovery.json';

function ensure_discovery_file($file_path) {
    if (!file_exists($file_path)) {
        $dir = dirname($file_path);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        file_put_contents($file_path, json_encode(['devices' => [], 'last_cleanup' => time()]));
    }
}

function cleanup_old_devices($file_path, $max_age = 300) {
    ensure_discovery_file($file_path);
    
    $data = json_decode(file_get_contents($file_path), true);
    $current_time = time();
    
    // Clean up devices older than max_age seconds
    $data['devices'] = array_filter($data['devices'], function($device) use ($current_time, $max_age) {
        return ($current_time - ($device['timestamp'] / 1000)) < $max_age;
    });
    
    $data['last_cleanup'] = $current_time;
    file_put_contents($file_path, json_encode($data));
    
    return $data;
}

try {
    $input = json_decode(file_get_contents('php://input'), true);
    $action = $input['action'] ?? '';
    
    switch ($action) {
        case 'register':
            $device_info = $input['device'] ?? null;
            
            if (!$device_info || !isset($device_info['id'])) {
                send_response(400, ['error' => 'Invalid device information']);
            }
            
            // Try database first, fallback to file
            try {
                $db = new Database();
                $pdo = $db->getConnection();
                
                // Create discovery table if not exists
                $pdo->exec('
                    CREATE TABLE IF NOT EXISTS device_discovery (
                        id VARCHAR(50) PRIMARY KEY,
                        device_data JSON,
                        last_seen TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                        INDEX idx_last_seen (last_seen)
                    )
                ');
                
                // Insert or update device
                $stmt = $pdo->prepare('
                    INSERT INTO device_discovery (id, device_data, last_seen) 
                    VALUES (?, ?, NOW()) 
                    ON DUPLICATE KEY UPDATE 
                    device_data = VALUES(device_data), 
                    last_seen = NOW()
                ');
                $stmt->execute([$device_info['id'], json_encode($device_info)]);
                
                send_response(200, ['success' => true, 'method' => 'database']);
                
            } catch (Exception $db_error) {
                // Fallback to file-based discovery
                cleanup_old_devices($discovery_file);
                
                $data = json_decode(file_get_contents($discovery_file), true);
                
                // Update or add device
                $found = false;
                foreach ($data['devices'] as &$device) {
                    if ($device['id'] === $device_info['id']) {
                        $device = $device_info;
                        $found = true;
                        break;
                    }
                }
                
                if (!$found) {
                    $data['devices'][] = $device_info;
                }
                
                file_put_contents($discovery_file, json_encode($data));
                
                send_response(200, ['success' => true, 'method' => 'file_fallback']);
            }
            break;
            
        case 'discover':
            $requester_id = $input['requester'] ?? '';
            
            try {
                $db = new Database();
                $pdo = $db->getConnection();
                
                // Get devices seen in last 5 minutes
                $stmt = $pdo->prepare('
                    SELECT device_data 
                    FROM device_discovery 
                    WHERE last_seen > DATE_SUB(NOW(), INTERVAL 5 MINUTE)
                    ORDER BY last_seen DESC
                ');
                $stmt->execute();
                
                $devices = [];
                while ($row = $stmt->fetch()) {
                    $device_data = json_decode($row['device_data'], true);
                    if ($device_data) {
                        $devices[] = $device_data;
                    }
                }
                
                send_response(200, [
                    'success' => true,
                    'devices' => $devices,
                    'count' => count($devices),
                    'method' => 'database'
                ]);
                
            } catch (Exception $db_error) {
                // Fallback to file-based discovery
                cleanup_old_devices($discovery_file);
                
                $data = json_decode(file_get_contents($discovery_file), true);
                
                send_response(200, [
                    'success' => true,
                    'devices' => $data['devices'],
                    'count' => count($data['devices']),
                    'method' => 'file_fallback'
                ]);
            }
            break;
            
        default:
            send_response(400, ['error' => 'Invalid action']);
    }
    
} catch (Exception $e) {
    error_log("Device Discovery Error: " . $e->getMessage());
    send_response(500, ['error' => 'Server error']);
}
?>
