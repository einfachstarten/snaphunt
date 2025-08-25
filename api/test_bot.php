<?php
require_once __DIR__ . '/database.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

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
        case 'create_test_bot':
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                send_response(400, ['error' => 'Invalid request method']);
            }

            $input = json_decode(file_get_contents('php://input'), true);
            $game_code = $input['game_code'] ?? '';
            $center_lat = (float)($input['center_lat'] ?? 48.2082); // Vienna center
            $center_lng = (float)($input['center_lng'] ?? 16.3738);
            $max_radius_m = (int)($input['max_radius_m'] ?? 500);
            $bot_type = $input['bot_type'] ?? 'hunted'; // hunted or hunter

            if (!preg_match('/^[A-Z0-9]{6}$/', $game_code)) {
                send_response(400, ['error' => 'Invalid game code format']);
            }

            // Get game
            $stmt = $pdo->prepare('SELECT id, status FROM games WHERE join_code = ?');
            $stmt->execute([$game_code]);
            $game = $stmt->fetch();
            if (!$game) {
                send_response(404, ['error' => 'Game not found']);
            }

            // Find or create appropriate team
            $stmt = $pdo->prepare('SELECT id, name FROM teams WHERE game_id = ? AND role = ?');
            $stmt->execute([$game['id'], $bot_type]);
            $team = $stmt->fetch();

            if (!$team) {
                // Create team for bots
                $team_name = $bot_type === 'hunter' ? 'Bot Hunters' : 'Bot Runners';
                $team_code = 'BOT' . rand(10, 99);

                $stmt = $pdo->prepare('INSERT INTO teams (game_id, name, role, join_code) VALUES (?, ?, ?, ?)');
                $stmt->execute([$game['id'], $team_name, $bot_type, $team_code]);
                $team_id = $pdo->lastInsertId();

                $team = ['id' => $team_id, 'name' => $team_name];
            } else {
                $team_id = $team['id'];
            }

            // Generate realistic bot position within radius
            $radius_deg = $max_radius_m / 111000; // Convert meters to degrees (approximate)

            // Use polar coordinates for uniform distribution
            $angle = mt_rand(0, 360) * (M_PI / 180);
            $distance = sqrt(mt_rand(50, $max_radius_m * $max_radius_m)) / 111000; // sqrt for uniform area distribution

            $bot_lat = $center_lat + ($distance * cos($angle));
            $bot_lng = $center_lng + ($distance * sin($angle));

            // Create unique test bot
            $bot_number = mt_rand(100, 999);
            $bot_name = ($bot_type === 'hunter' ? 'Hunter Bot ' : 'Runner Bot ') . $bot_number;
            $device_id = 'test_bot_' . $bot_type . '_' . uniqid();

            $stmt = $pdo->prepare('INSERT INTO players (team_id, device_id, display_name, is_captain, last_seen) VALUES (?, ?, ?, 0, NOW())');
            $stmt->execute([$team_id, $device_id, $bot_name]);
            $bot_id = $pdo->lastInsertId();

            // Set initial bot location
            $stmt = $pdo->prepare('INSERT INTO location_pings (player_id, latitude, longitude) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE latitude = VALUES(latitude), longitude = VALUES(longitude), created_at = NOW()');
            $stmt->execute([$bot_id, $bot_lat, $bot_lng]);

            send_response(200, [
                'success' => true,
                'bot' => [
                    'id' => $bot_id,
                    'name' => $bot_name,
                    'device_id' => $device_id,
                    'team_id' => $team_id,
                    'team_name' => $team['name'],
                    'role' => $bot_type,
                    'latitude' => round($bot_lat, 6),
                    'longitude' => round($bot_lng, 6),
                    'distance_from_center' => round($distance * 111000) // meters
                ],
                'message' => 'Test bot created successfully'
            ]);
            break;

        case 'remove_test_bots':
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                send_response(400, ['error' => 'Invalid request method']);
            }

            $input = json_decode(file_get_contents('php://input'), true);
            $game_code = $input['game_code'] ?? '';
            $bot_type = $input['bot_type'] ?? null; // Optional: 'hunter', 'hunted', or null for all

            if (!preg_match('/^[A-Z0-9]{6}$/', $game_code)) {
                send_response(400, ['error' => 'Invalid game code format']);
            }

            // Build query based on bot type filter
            if ($bot_type && in_array($bot_type, ['hunter', 'hunted'])) {
                $stmt = $pdo->prepare('
                    DELETE p FROM players p
                    JOIN teams t ON p.team_id = t.id
                    JOIN games g ON t.game_id = g.id
                    WHERE g.join_code = ? 
                    AND p.device_id LIKE "test_bot_%" 
                    AND t.role = ?
                ');
                $stmt->execute([$game_code, $bot_type]);
            } else {
                $stmt = $pdo->prepare('
                    DELETE p FROM players p
                    JOIN teams t ON p.team_id = t.id
                    JOIN games g ON t.game_id = g.id
                    WHERE g.join_code = ? AND p.device_id LIKE "test_bot_%"
                ');
                $stmt->execute([$game_code]);
            }

            $removed_count = $stmt->rowCount();

            send_response(200, [
                'success' => true,
                'removed_count' => $removed_count,
                'message' => "$removed_count test bot(s) removed successfully"
            ]);
            break;

        case 'list_test_bots':
            $game_code = $_GET['game_code'] ?? '';

            if (!preg_match('/^[A-Z0-9]{6}$/', $game_code)) {
                send_response(400, ['error' => 'Invalid game code format']);
            }

            // Get all test bots for the game with their current locations
            $stmt = $pdo->prepare('
                SELECT 
                    p.id,
                    p.display_name,
                    p.device_id,
                    p.last_seen,
                    t.name as team_name,
                    t.role,
                    lp.latitude,
                    lp.longitude,
                    lp.created_at as last_location_update,
                    TIMESTAMPDIFF(SECOND, p.last_seen, NOW()) as seconds_offline
                FROM players p
                JOIN teams t ON p.team_id = t.id
                JOIN games g ON t.game_id = g.id
                LEFT JOIN location_pings lp ON p.id = lp.player_id
                WHERE g.join_code = ? 
                AND p.device_id LIKE "test_bot_%"
                ORDER BY t.role, p.display_name
            ');
            $stmt->execute([$game_code]);
            $bots = $stmt->fetchAll();

            send_response(200, [
                'success' => true,
                'bots' => $bots,
                'total_count' => count($bots)
            ]);
            break;

        case 'move_test_bot':
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                send_response(400, ['error' => 'Invalid request method']);
            }

            $input = json_decode(file_get_contents('php://input'), true);
            $bot_id = (int)($input['bot_id'] ?? 0);
            $new_lat = (float)($input['latitude'] ?? 0);
            $new_lng = (float)($input['longitude'] ?? 0);
            $movement_type = $input['movement_type'] ?? 'manual'; // manual, random, towards_target

            if (!$bot_id || !$new_lat || !$new_lng) {
                send_response(400, ['error' => 'Missing bot_id, latitude, or longitude']);
            }

            // Validate coordinates
            if ($new_lat < -90 || $new_lat > 90 || $new_lng < -180 || $new_lng > 180) {
                send_response(400, ['error' => 'Invalid coordinates']);
            }

            // Verify bot exists and is a test bot
            $stmt = $pdo->prepare('SELECT id, display_name FROM players WHERE id = ? AND device_id LIKE "test_bot_%"');
            $stmt->execute([$bot_id]);
            $bot = $stmt->fetch();

            if (!$bot) {
                send_response(404, ['error' => 'Test bot not found']);
            }

            // Update bot location
            $stmt = $pdo->prepare('
                INSERT INTO location_pings (player_id, latitude, longitude) 
                VALUES (?, ?, ?) 
                ON DUPLICATE KEY UPDATE 
                latitude = VALUES(latitude), 
                longitude = VALUES(longitude), 
                created_at = NOW()
            ');
            $stmt->execute([$bot_id, $new_lat, $new_lng]);

            // Update last_seen
            $stmt = $pdo->prepare('UPDATE players SET last_seen = NOW() WHERE id = ?');
            $stmt->execute([$bot_id]);

            send_response(200, [
                'success' => true,
                'bot' => [
                    'id' => $bot_id,
                    'name' => $bot['display_name'],
                    'latitude' => $new_lat,
                    'longitude' => $new_lng,
                    'movement_type' => $movement_type
                ],
                'message' => 'Bot moved successfully'
            ]);
            break;

        case 'simulate_bot_movement':
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                send_response(400, ['error' => 'Invalid request method']);
            }

            $input = json_decode(file_get_contents('php://input'), true);
            $game_code = $input['game_code'] ?? '';
            $simulation_seconds = (int)($input['duration_seconds'] ?? 30);
            $movement_speed = (float)($input['speed_mps'] ?? 2.0); // meters per second

            if (!preg_match('/^[A-Z0-9]{6}$/', $game_code)) {
                send_response(400, ['error' => 'Invalid game code format']);
            }

            // Get all test bots for the game
            $stmt = $pdo->prepare('
                SELECT p.id, p.display_name, t.role, lp.latitude, lp.longitude
                FROM players p
                JOIN teams t ON p.team_id = t.id
                JOIN games g ON t.game_id = g.id
                LEFT JOIN location_pings lp ON p.id = lp.player_id
                WHERE g.join_code = ? AND p.device_id LIKE "test_bot_%"
            ');
            $stmt->execute([$game_code]);
            $bots = $stmt->fetchAll();

            if (empty($bots)) {
                send_response(404, ['error' => 'No test bots found in this game']);
            }

            $movements = [];

            foreach ($bots as $bot) {
                if (!$bot['latitude'] || !$bot['longitude']) {
                    continue; // Skip bots without location
                }

                // Calculate random movement within realistic constraints
                $current_lat = (float)$bot['latitude'];
                $current_lng = (float)$bot['longitude'];

                // Random direction
                $angle = mt_rand(0, 360) * (M_PI / 180);

                // Movement distance based on speed and time
                $max_distance_m = $movement_speed * $simulation_seconds;
                $distance_m = mt_rand(10, $max_distance_m);
                $distance_deg = $distance_m / 111000;

                $new_lat = $current_lat + ($distance_deg * cos($angle));
                $new_lng = $current_lng + ($distance_deg * sin($angle));

                // Update bot location
                $stmt = $pdo->prepare('
                    INSERT INTO location_pings (player_id, latitude, longitude) 
                    VALUES (?, ?, ?) 
                    ON DUPLICATE KEY UPDATE 
                    latitude = VALUES(latitude), 
                    longitude = VALUES(longitude), 
                    created_at = NOW()
                ');
                $stmt->execute([$bot['id'], $new_lat, $new_lng]);

                // Update last_seen
                $stmt = $pdo->prepare('UPDATE players SET last_seen = NOW() WHERE id = ?');
                $stmt->execute([$bot['id']]);

                $movements[] = [
                    'bot_id' => $bot['id'],
                    'bot_name' => $bot['display_name'],
                    'role' => $bot['role'],
                    'from' => ['lat' => $current_lat, 'lng' => $current_lng],
                    'to' => ['lat' => round($new_lat, 6), 'lng' => round($new_lng, 6)],
                    'distance_moved_m' => round($distance_m)
                ];
            }

            send_response(200, [
                'success' => true,
                'movements' => $movements,
                'simulation_duration' => $simulation_seconds,
                'bots_moved' => count($movements),
                'message' => count($movements) . ' bots moved in simulated movement'
            ]);
            break;

        default:
            send_response(400, ['error' => 'Invalid action. Available: create_test_bot, remove_test_bots, list_test_bots, move_test_bot, simulate_bot_movement']);
    }
} catch (Exception $e) {
    error_log("Test Bot API Error: " . $e->getMessage());
    send_response(500, ['error' => 'Server error: ' . $e->getMessage()]);
}

?>

