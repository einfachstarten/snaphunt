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
        case 'create_test_bot':
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                send_response(400, ['error' => 'Invalid request method']);
            }

            $input = json_decode(file_get_contents('php://input'), true);
            $game_code = $input['game_code'] ?? '';
            $center_lat = (float)($input['center_lat'] ?? 48.4366);
            $center_lng = (float)($input['center_lng'] ?? 15.5809);
            $max_radius_m = (int)($input['max_radius_m'] ?? 500);

            if (!preg_match('/^[A-Z0-9]{6}$/', $game_code)) {
                send_response(400, ['error' => 'Invalid game code']);
            }

            // Get game
            $stmt = $pdo->prepare('SELECT id FROM games WHERE join_code = ?');
            $stmt->execute([$game_code]);
            $game = $stmt->fetch();
            if (!$game) {
                send_response(404, ['error' => 'Game not found']);
            }

            // Check if hunted team exists, create if not
            $stmt = $pdo->prepare('SELECT id FROM teams WHERE game_id = ? AND role = "hunted"');
            $stmt->execute([$game['id']]);
            $hunted_team = $stmt->fetch();

            if (!$hunted_team) {
                // Create hunted team
                $team_code = 'TEST' . rand(10, 99);
                $stmt = $pdo->prepare('INSERT INTO teams (game_id, name, role, join_code) VALUES (?, ?, "hunted", ?)');
                $stmt->execute([$game['id'], 'Test Hunted Team', $team_code]);
                $team_id = $pdo->lastInsertId();
            } else {
                $team_id = $hunted_team['id'];
            }

            // Generate random position within radius
            $radius_deg = $max_radius_m / 111000; // Convert meters to degrees (rough)
            $angle = mt_rand(0, 360) * (M_PI / 180);
            $distance = mt_rand(50, $max_radius_m) / 111000;

            $bot_lat = $center_lat + ($distance * cos($angle));
            $bot_lng = $center_lng + ($distance * sin($angle));

            // Create test bot player
            $bot_name = 'Test Bot ' . rand(100, 999);
            $device_id = 'test_bot_' . uniqid();

            $stmt = $pdo->prepare('INSERT INTO players (team_id, device_id, display_name, is_captain, last_seen) VALUES (?, ?, ?, 0, NOW())');
            $stmt->execute([$team_id, $device_id, $bot_name]);
            $bot_id = $pdo->lastInsertId();

            // Set initial bot location
            $stmt = $pdo->prepare('INSERT INTO location_pings (player_id, latitude, longitude) VALUES (?, ?, ?)');
            $stmt->execute([$bot_id, $bot_lat, $bot_lng]);

            send_response(200, [
                'success' => true,
                'bot' => [
                    'id' => $bot_id,
                    'name' => $bot_name,
                    'device_id' => $device_id,
                    'team_id' => $team_id,
                    'latitude' => $bot_lat,
                    'longitude' => $bot_lng
                ],
                'message' => 'Test bot created successfully'
            ]);
            break;

        case 'move_bots':
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                send_response(400, ['error' => 'Invalid request method']);
            }

            $input = json_decode(file_get_contents('php://input'), true);
            $game_code = $input['game_code'] ?? '';
            $center_lat = (float)($input['center_lat'] ?? 48.4366);
            $center_lng = (float)($input['center_lng'] ?? 15.5809);
            $max_radius_m = (int)($input['max_radius_m'] ?? 500);

            // Get all test bots in game
            $stmt = $pdo->prepare('
                SELECT p.id, p.display_name, lp.latitude, lp.longitude
                FROM players p
                JOIN teams t ON p.team_id = t.id
                JOIN games g ON t.game_id = g.id
                LEFT JOIN location_pings lp ON p.id = lp.player_id
                WHERE g.join_code = ? AND p.device_id LIKE "test_bot_%"
            ');
            $stmt->execute([$game_code]);
            $bots = $stmt->fetchAll();

            $updated_bots = [];
            foreach ($bots as $bot) {
                // Generate new position within radius
                $current_lat = (float)$bot['latitude'] ?: $center_lat;
                $current_lng = (float)$bot['longitude'] ?: $center_lng;

                // Small random movement (max 50m per update)
                $move_distance = mt_rand(10, 50) / 111000; // Convert to degrees
                $move_angle = mt_rand(0, 360) * (M_PI / 180);

                $new_lat = $current_lat + ($move_distance * cos($move_angle));
                $new_lng = $current_lng + ($move_distance * sin($move_angle));

                // Ensure bot stays within radius of center
                $distance_from_center = sqrt(
                    pow(($new_lat - $center_lat) * 111000, 2) + 
                    pow(($new_lng - $center_lng) * 111000, 2)
                );

                if ($distance_from_center > $max_radius_m) {
                    // Move towards center
                    $angle_to_center = atan2($center_lng - $current_lng, $center_lat - $current_lat);
                    $new_lat = $current_lat + ($move_distance * cos($angle_to_center));
                    $new_lng = $current_lng + ($move_distance * sin($angle_to_center));
                }

                // Update bot location
                $stmt = $pdo->prepare('
                    INSERT INTO location_pings (player_id, latitude, longitude) 
                    VALUES (?, ?, ?) 
                    ON DUPLICATE KEY UPDATE 
                    latitude = VALUES(latitude), longitude = VALUES(longitude), created_at = NOW()
                ');
                $stmt->execute([$bot['id'], $new_lat, $new_lng]);

                // Update last_seen
                $stmt = $pdo->prepare('UPDATE players SET last_seen = NOW() WHERE id = ?');
                $stmt->execute([$bot['id']]);

                $updated_bots[] = [
                    'id' => $bot['id'],
                    'name' => $bot['display_name'],
                    'latitude' => $new_lat,
                    'longitude' => $new_lng
                ];
            }

            send_response(200, [
                'success' => true,
                'updated_bots' => $updated_bots,
                'message' => 'Bots moved successfully'
            ]);
            break;

        case 'remove_test_bots':
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                send_response(400, ['error' => 'Invalid request method']);
            }

            $input = json_decode(file_get_contents('php://input'), true);
            $game_code = $input['game_code'] ?? '';

            // Remove all test bots from game
            $stmt = $pdo->prepare('
                DELETE p FROM players p
                JOIN teams t ON p.team_id = t.id
                JOIN games g ON t.game_id = g.id
                WHERE g.join_code = ? AND p.device_id LIKE "test_bot_%"
            ');
            $stmt->execute([$game_code]);

            send_response(200, [
                'success' => true,
                'message' => 'Test bots removed successfully'
            ]);
            break;

        default:
            send_response(400, ['error' => 'Invalid action']);
    }
} catch (Exception $e) {
    error_log("Test Bot API Error: " . $e->getMessage());
    send_response(500, ['error' => 'Server error']);
}
?>
