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

// SHARED STORAGE: File-based device discovery (works even without database)
$discovery_file = __DIR__ . '/../uploads/devices_shared.json';

function safe_file_operation($file_path, $operation) {
    $max_retries = 3;
    $retry_count = 0;

    while ($retry_count < $max_retries) {
        $lock_file = $file_path . '.lock.' . uniqid();
        $lock = fopen($lock_file, 'w');

        if (!$lock) {
            $retry_count++;
            usleep(100000); // 100ms
            continue;
        }

        if (flock($lock, LOCK_EX | LOCK_NB)) {
            try {
                $result = $operation($file_path);
                flock($lock, LOCK_UN);
                fclose($lock);
                unlink($lock_file);
                return $result;
            } catch (Exception $e) {
                flock($lock, LOCK_UN);
                fclose($lock);
                unlink($lock_file);
                throw $e;
            }
        } else {
            fclose($lock);
            unlink($lock_file);
            $retry_count++;
            usleep(100000);
        }
    }

    throw new Exception('Could not acquire file lock after retries');
}

function ensure_shared_file($file_path) {
    if (!file_exists($file_path) || filesize($file_path) === 0) {
        $dir = dirname($file_path);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $initial_data = [
            'devices' => [],
            'notifications' => [],
            'last_cleanup' => time(),
            'created' => date('Y-m-d H:i:s'),
            'version' => '1.0'
        ];

        file_put_contents($file_path, json_encode($initial_data));
        chmod($file_path, 0666);
        return;
    }

    $data = json_decode(file_get_contents($file_path), true);
    if (!$data || json_last_error() !== JSON_ERROR_NONE) {
        error_log("Corrupted discovery file detected, recreating: " . $file_path);

        $backup_file = $file_path . '.corrupted.' . time();
        copy($file_path, $backup_file);

        $initial_data = [
            'devices' => [],
            'notifications' => [],
            'last_cleanup' => time(),
            'created' => date('Y-m-d H:i:s'),
            'version' => '1.0'
        ];

        file_put_contents($file_path, json_encode($initial_data));
    }
}

function cleanup_old_devices($file_path, $max_age = 180) {
    ensure_shared_file($file_path);

    return safe_file_operation($file_path, function($path) use ($max_age) {
        $data = json_decode(file_get_contents($path), true);
        if (!$data) $data = ['devices' => [], 'notifications' => [], 'last_cleanup' => time(), 'created' => date('Y-m-d H:i:s'), 'version' => '1.0'];

        $current_time = time();
        $data['devices'] = array_values(array_filter($data['devices'], function($device) use ($current_time, $max_age) {
            return ($current_time - ($device['timestamp'] / 1000)) < $max_age;
        }));

        $data['last_cleanup'] = $current_time;
        file_put_contents($path, json_encode($data, JSON_PRETTY_PRINT));
        return $data;
    });
}

function update_shared_devices($file_path, $device_info) {
    ensure_shared_file($file_path);

    return safe_file_operation($file_path, function($path) use ($device_info) {
        $data = json_decode(file_get_contents($path), true);
        if (!$data) $data = ['devices' => [], 'notifications' => [], 'last_cleanup' => time(), 'created' => date('Y-m-d H:i:s'), 'version' => '1.0'];

        $updated = false;
        foreach ($data['devices'] as &$device) {
            if ($device['id'] === $device_info['id']) {
                $device = $device_info;
                $updated = true;
                break;
            }
        }

        if (!$updated) {
            $data['devices'][] = $device_info;
        }

        file_put_contents($path, json_encode($data, JSON_PRETTY_PRINT));
        return true;
    });
}

function createDiscoveryNotifications($pdo, $discovered_devices, $requester_id, $requester_location) {
    try {
        // Get discoverer info
        $stmt = $pdo->prepare('SELECT device_data FROM device_discovery WHERE id = ?');
        $stmt->execute([$requester_id]);
        $discoverer_row = $stmt->fetch();
        
        if (!$discoverer_row) return;
        
        $discoverer_data = json_decode($discoverer_row['device_data'], true);
        $discoverer_type = $discoverer_data['deviceType'] ?? 'unknown';
        
        // Create notifications for each discovered device
        $stmt = $pdo->prepare('
            INSERT INTO discovery_notifications (target_device_id, discoverer_id, discoverer_location, discoverer_type) 
            VALUES (?, ?, ?, ?)
        ');
        
        foreach ($discovered_devices as $device) {
            $stmt->execute([
                $device['id'],
                $requester_id,
                json_encode($requester_location),
                $discoverer_type
            ]);
        }
        
        error_log("Created " . count($discovered_devices) . " discovery notifications in database");
        
    } catch (Exception $e) {
        error_log("Failed to create database notifications: " . $e->getMessage());
    }
}

function createFileNotifications($file_path, $discovered_devices, $requester_id, $requester_location) {
    try {
        $data = json_decode(file_get_contents($file_path), true);
        if (!$data) return;
        
        if (!isset($data['notifications'])) {
            $data['notifications'] = [];
        }
        
        $timestamp = time();
        
        // Find discoverer device type
        $discoverer_type = 'unknown';
        foreach ($data['devices'] as $device) {
            if ($device['id'] === $requester_id) {
                $discoverer_type = $device['deviceType'] ?? 'unknown';
                break;
            }
        }
        
        // Create notifications for each discovered device
        foreach ($discovered_devices as $device) {
            $data['notifications'][] = [
                'id' => uniqid(),
                'target_device_id' => $device['id'],
                'discoverer_id' => $requester_id,
                'discoverer_location' => $requester_location,
                'discoverer_type' => $discoverer_type,
                'timestamp' => $timestamp * 1000, // JavaScript timestamp
                'is_read' => false
            ];
        }
        
        // Clean old notifications (older than 2 minutes)
        $data['notifications'] = array_filter($data['notifications'], function($notification) use ($timestamp) {
            return ($timestamp - ($notification['timestamp'] / 1000)) < 120;
        });
        
        file_put_contents($file_path, json_encode($data, JSON_PRETTY_PRINT));
        
        error_log("Created " . count($discovered_devices) . " discovery notifications in file");
        
    } catch (Exception $e) {
        error_log("Failed to create file notifications: " . $e->getMessage());
    }
}

function getFileNotifications($file_path, $device_id) {
    try {
        $data = json_decode(file_get_contents($file_path), true);
        if (!$data || !isset($data['notifications'])) {
            return [];
        }
        
        // Get unread notifications for this device
        $notifications = array_filter($data['notifications'], function($notification) use ($device_id) {
            return $notification['target_device_id'] === $device_id && !$notification['is_read'];
        });
        
        // Mark as read by updating the file
        foreach ($data['notifications'] as &$notification) {
            if ($notification['target_device_id'] === $device_id && !$notification['is_read']) {
                $notification['is_read'] = true;
            }
        }
        
        file_put_contents($file_path, json_encode($data, JSON_PRETTY_PRINT));
        
        return array_values($notifications);
        
    } catch (Exception $e) {
        error_log("Failed to get file notifications: " . $e->getMessage());
        return [];
    }
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
            
            // Try database first (if available), then shared file
            $storage_method = 'file'; // Default to file
            
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
                
                // Insert or update device in database
                $stmt = $pdo->prepare('
                    INSERT INTO device_discovery (id, device_data, last_seen) 
                    VALUES (?, ?, NOW()) 
                    ON DUPLICATE KEY UPDATE 
                    device_data = VALUES(device_data), 
                    last_seen = NOW()
                ');
                $stmt->execute([$device_info['id'], json_encode($device_info)]);
                $storage_method = 'database';
                
            } catch (Exception $db_error) {
                error_log("Database storage failed, using file: " . $db_error->getMessage());
                // Continue with file storage below
            }
            
            // Always also store in shared file as backup/fallback
            update_shared_devices($discovery_file, $device_info);
            
            send_response(200, [
                'success' => true, 
                'method' => $storage_method,
                'device_count' => count(cleanup_old_devices($discovery_file)['devices'])
            ]);
            break;
            
        case 'ping_discover':
            // Enhanced discover that also notifies discovered devices
            $requester_id = $input['requester'] ?? '';
            $requester_location = $input['requester_location'] ?? null;
            $devices = [];
            $storage_method = 'file';
            
            // Try database first
            try {
                $db = new Database();
                $pdo = $db->getConnection();
                
                // Get devices seen in last 3 minutes
                $stmt = $pdo->prepare('
                    SELECT device_data 
                    FROM device_discovery 
                    WHERE last_seen > DATE_SUB(NOW(), INTERVAL 3 MINUTE)
                    ORDER BY last_seen DESC
                ');
                $stmt->execute();
                
                while ($row = $stmt->fetch()) {
                    $device_data = json_decode($row['device_data'], true);
                    if ($device_data && $device_data['id'] !== $requester_id) {
                        $devices[] = $device_data;
                    }
                }
                
                if (count($devices) > 0) {
                    $storage_method = 'database';
                    
                    // Create notifications for discovered devices
                    createDiscoveryNotifications($pdo, $devices, $requester_id, $requester_location);
                }
                
            } catch (Exception $db_error) {
                error_log("Database ping_discover failed: " . $db_error->getMessage());
            }
            
            // If database failed or returned no results, use shared file
            if (count($devices) === 0) {
                $data = cleanup_old_devices($discovery_file);
                $devices = array_filter($data['devices'], function($device) use ($requester_id) {
                    return $device['id'] !== $requester_id;
                });
                $devices = array_values($devices);
                $storage_method = 'shared_file';
                
                // Create notifications in file for discovered devices
                if (count($devices) > 0) {
                    createFileNotifications($discovery_file, $devices, $requester_id, $requester_location);
                }
            }
            
            send_response(200, [
                'success' => true,
                'devices' => $devices,
                'count' => count($devices),
                'method' => $storage_method,
                'requester' => $requester_id
            ]);
            break;
            
        case 'check_notifications':
            // Check for discovery notifications for a specific device
            $device_id = $input['device_id'] ?? '';
            if (!$device_id) {
                send_response(400, ['error' => 'Missing device_id']);
            }
            
            $notifications = [];
            
            // Try database first
            try {
                $db = new Database();
                $pdo = $db->getConnection();
                
                // Create notifications table if not exists
                $pdo->exec('
                    CREATE TABLE IF NOT EXISTS discovery_notifications (
                        id INT AUTO_INCREMENT PRIMARY KEY,
                        target_device_id VARCHAR(50),
                        discoverer_id VARCHAR(50),
                        discoverer_location JSON,
                        discoverer_type VARCHAR(20),
                        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                        is_read BOOLEAN DEFAULT FALSE,
                        INDEX idx_target_device (target_device_id, is_read),
                        INDEX idx_created_at (created_at)
                    )
                ');
                
                // Get unread notifications for this device
                $stmt = $pdo->prepare('
                    SELECT * FROM discovery_notifications 
                    WHERE target_device_id = ? AND is_read = FALSE 
                    ORDER BY created_at DESC
                ');
                $stmt->execute([$device_id]);
                
                while ($row = $stmt->fetch()) {
                    $notifications[] = [
                        'id' => $row['id'],
                        'discoverer_id' => $row['discoverer_id'],
                        'discoverer_location' => json_decode($row['discoverer_location'], true),
                        'discoverer_type' => $row['discoverer_type'],
                        'timestamp' => strtotime($row['created_at']) * 1000
                    ];
                }
                
                // Mark notifications as read
                if (count($notifications) > 0) {
                    $stmt = $pdo->prepare('
                        UPDATE discovery_notifications 
                        SET is_read = TRUE 
                        WHERE target_device_id = ? AND is_read = FALSE
                    ');
                    $stmt->execute([$device_id]);
                }
                
            } catch (Exception $db_error) {
                // Fallback to file-based notifications
                $notifications = getFileNotifications($discovery_file, $device_id);
            }
            
            send_response(200, [
                'success' => true,
                'notifications' => $notifications,
                'count' => count($notifications)
            ]);
            break;
            
        case 'discover':
            $requester_id = $input['requester'] ?? '';
            $devices = [];
            $storage_method = 'file';
            
            // Try database first
            try {
                $db = new Database();
                $pdo = $db->getConnection();
                
                // Get devices seen in last 3 minutes
                $stmt = $pdo->prepare('
                    SELECT device_data 
                    FROM device_discovery 
                    WHERE last_seen > DATE_SUB(NOW(), INTERVAL 3 MINUTE)
                    ORDER BY last_seen DESC
                ');
                $stmt->execute();
                
                while ($row = $stmt->fetch()) {
                    $device_data = json_decode($row['device_data'], true);
                    if ($device_data && $device_data['id'] !== $requester_id) {
                        $devices[] = $device_data;
                    }
                }
                
                if (count($devices) > 0) {
                    $storage_method = 'database';
                }
                
            } catch (Exception $db_error) {
                error_log("Database discovery failed: " . $db_error->getMessage());
                // Fall through to file method
            }
            
            // If database failed or returned no results, use shared file
            if (count($devices) === 0) {
                $data = cleanup_old_devices($discovery_file);
                $devices = array_filter($data['devices'], function($device) use ($requester_id) {
                    return $device['id'] !== $requester_id; // Exclude requester
                });
                $devices = array_values($devices); // Reindex array
                $storage_method = 'shared_file';
            }
            
            send_response(200, [
                'success' => true,
                'devices' => $devices,
                'count' => count($devices),
                'method' => $storage_method,
                'requester' => $requester_id
            ]);
            break;
            
        case 'status':
            // Get status of shared discovery system
            $data = cleanup_old_devices($discovery_file);
            
            send_response(200, [
                'success' => true,
                'total_devices' => count($data['devices']),
                'last_cleanup' => date('Y-m-d H:i:s', $data['last_cleanup']),
                'file_exists' => file_exists($discovery_file),
                'file_writable' => is_writable(dirname($discovery_file))
            ]);
            break;
            
        default:
            send_response(400, ['error' => 'Invalid action. Available: register, discover, status']);
    }
    
} catch (Exception $e) {
    error_log("Device Discovery Error: " . $e->getMessage());
    send_response(500, ['error' => 'Server error']);
}
?>
