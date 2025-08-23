<?php
require_once __DIR__ . '/database.php';

header('Content-Type: application/json');

function send_response(int $status, array $data): void
{
    http_response_code($status);
    echo json_encode($data);
    exit;
}

$action = $_GET['action'] ?? '';
$db = new Database();
$pdo = $db->getConnection();

try {
    switch ($action) {
        case 'update':
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                send_response(400, ['error' => 'Invalid request method']);
            }
            
            $input = json_decode(file_get_contents('php://input'), true);
            $player_id = (int)($input['player_id'] ?? 0);
            $latitude = (float)($input['latitude'] ?? 0);
            $longitude = (float)($input['longitude'] ?? 0);
            
            if (!$player_id || !$latitude || !$longitude) {
                send_response(400, ['error' => 'Invalid input data']);
            }
            
            // Validate latitude/longitude ranges
            if ($latitude < -90 || $latitude > 90 || $longitude < -180 || $longitude > 180) {
                send_response(400, ['error' => 'Invalid coordinates']);
            }
            
            // Insert or update location ping
            $stmt = $pdo->prepare('
                INSERT INTO location_pings (player_id, latitude, longitude) 
                VALUES (?, ?, ?) 
                ON DUPLICATE KEY UPDATE 
                latitude = VALUES(latitude), 
                longitude = VALUES(longitude), 
                created_at = CURRENT_TIMESTAMP
            ');
            $stmt->execute([$player_id, $latitude, $longitude]);
            
            // Update player last_seen timestamp
            $stmt = $pdo->prepare('UPDATE players SET last_seen = CURRENT_TIMESTAMP WHERE id = ?');
            $stmt->execute([$player_id]);
            
            send_response(200, ['success' => true, 'message' => 'Location updated']);
            break;
            
        case 'get':
            $game_id = (int)($_GET['game_id'] ?? 0);
            
            if (!$game_id) {
                send_response(400, ['error' => 'Invalid game_id']);
            }
            
            // Get all active players' locations from the specified game
            $stmt = $pdo->prepare('
                SELECT 
                    p.id,
                    p.display_name,
                    t.role,
                    t.name as team_name,
                    t.id as team_id,
                    lp.latitude,
                    lp.longitude,
                    lp.created_at as last_ping,
                    TIMESTAMPDIFF(SECOND, p.last_seen, NOW()) as seconds_offline
                FROM players p
                JOIN teams t ON p.team_id = t.id
                LEFT JOIN location_pings lp ON p.id = lp.player_id
                WHERE t.game_id = ? 
                AND p.last_seen > DATE_SUB(NOW(), INTERVAL 10 MINUTE)
                ORDER BY p.display_name
            ');
            $stmt->execute([$game_id]);
            $locations = $stmt->fetchAll();
            
            send_response(200, [
                'success' => true, 
                'locations' => $locations,
                'timestamp' => date('Y-m-d H:i:s')
            ]);
            break;
            
        case 'ping':
            // Simple endpoint to check if player is still active
            $player_id = (int)($_GET['player_id'] ?? 0);
            
            if (!$player_id) {
                send_response(400, ['error' => 'Invalid player_id']);
            }
            
            $stmt = $pdo->prepare('UPDATE players SET last_seen = CURRENT_TIMESTAMP WHERE id = ?');
            $stmt->execute([$player_id]);
            
            send_response(200, ['success' => true, 'ping' => 'ok']);
            break;
            
        default:
            send_response(400, ['error' => 'Invalid action']);
    }
} catch (Exception $e) {
    error_log("Location API Error: " . $e->getMessage());
    send_response(500, ['error' => 'Server error']);
}
?>
