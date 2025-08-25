<?php
// Dedicated AJAX endpoint for admin actions
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

require_once __DIR__ . '/database.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

function send_json($data, $status = 200) {
    http_response_code($status);
    echo json_encode($data, JSON_PRETTY_PRINT);
    exit;
}

try {
    $db = new Database();
    $pdo = $db->getConnection();

    $action = $_POST['action'] ?? '';

    if (empty($action)) {
        send_json(['error' => 'No action specified'], 400);
    }

    switch ($action) {
        case 'get_live_stats':
            // Get demo game stats with NULL safety
            $stmt = $pdo->prepare('
                SELECT 
                    g.status,
                    g.name,
                    COALESCE(COUNT(DISTINCT p.id), 0) as total_players,
                    COALESCE(COUNT(DISTINCT CASE 
                        WHEN p.device_id LIKE "test_bot_%" OR p.device_id LIKE "demo_bot_%" OR p.device_id LIKE "bot_%" 
                        THEN p.id END), 0) as bot_players,
                    COALESCE(COUNT(DISTINCT CASE 
                        WHEN p.last_seen > DATE_SUB(NOW(), INTERVAL 5 MINUTE) 
                        THEN p.id END), 0) as online_players,
                    COALESCE(COUNT(DISTINCT c.id), 0) as total_captures
                FROM games g
                LEFT JOIN teams t ON g.id = t.game_id
                LEFT JOIN players p ON t.id = p.team_id
                LEFT JOIN captures c ON g.id = c.game_id
                WHERE g.join_code = "DEMO01"
                GROUP BY g.id, g.status, g.name
                LIMIT 1
            ');
            $stmt->execute();
            $demo_stats = $stmt->fetch();

            if (!$demo_stats) {
                $demo_stats = [
                    'status' => 'not_found',
                    'name' => 'Demo game not found',
                    'total_players' => 0,
                    'bot_players' => 0,
                    'online_players' => 0,
                    'total_captures' => 0
                ];
            }

            // System stats
            $stmt = $pdo->query('SELECT COUNT(*) as count FROM games');
            $total_games = $stmt->fetch()['count'];

            $stmt = $pdo->query('SELECT COUNT(*) as count FROM games WHERE status = "active"');
            $active_games = $stmt->fetch()['count'];

            send_json([
                'success' => true,
                'demo_stats' => $demo_stats,
                'system_stats' => [
                    'total_games' => (int)$total_games,
                    'active_games' => (int)$active_games,
                    'timestamp' => date('Y-m-d H:i:s')
                ]
            ]);
            break;

        case 'reset_demo_game':
            // Get demo game
            $stmt = $pdo->prepare('SELECT id FROM games WHERE join_code = "DEMO01"');
            $stmt->execute();
            $demo = $stmt->fetch();

            if (!$demo) {
                send_json(['success' => false, 'error' => 'Demo game not found'], 404);
            }

            $game_id = $demo['id'];

            // Reset operations in transaction
            $pdo->beginTransaction();

            try {
                // Reset game status
                $stmt = $pdo->prepare('UPDATE games SET status = "active", started_at = NOW() WHERE id = ?');
                $stmt->execute([$game_id]);

                // Clear all captures
                $stmt = $pdo->prepare('DELETE FROM captures WHERE game_id = ?');
                $stmt->execute([$game_id]);
                $captures_cleared = $stmt->rowCount();

                // Remove all bots
                $stmt = $pdo->prepare('
                    DELETE p FROM players p
                    JOIN teams t ON p.team_id = t.id
                    WHERE t.game_id = ? AND (
                        p.device_id LIKE "test_bot_%" OR 
                        p.device_id LIKE "demo_bot_%" OR 
                        p.device_id LIKE "bot_%"
                    )
                ');
                $stmt->execute([$game_id]);
                $bots_removed = $stmt->rowCount();

                // Update real players last_seen
                $stmt = $pdo->prepare('
                    UPDATE players p
                    JOIN teams t ON p.team_id = t.id
                    SET p.last_seen = NOW()
                    WHERE t.game_id = ? AND p.device_id NOT LIKE "%bot%"
                ');
                $stmt->execute([$game_id]);

                $pdo->commit();

                send_json([
                    'success' => true,
                    'message' => 'Demo game reset successfully',
                    'details' => [
                        'captures_cleared' => $captures_cleared,
                        'bots_removed' => $bots_removed
                    ]
                ]);

            } catch (Exception $e) {
                $pdo->rollBack();
                throw $e;
            }
            break;

        case 'create_demo_bots':
            $count = max(1, min(5, (int)($_POST['bot_count'] ?? 2)));

            // Find demo game and ensure hunted team exists
            $stmt = $pdo->prepare('
                SELECT g.id as game_id, t.id as team_id
                FROM games g
                LEFT JOIN teams t ON g.id = t.game_id AND t.role = "hunted"
                WHERE g.join_code = "DEMO01"
                LIMIT 1
            ');
            $stmt->execute();
            $result = $stmt->fetch();

            if (!$result || !$result['game_id']) {
                send_json(['success' => false, 'error' => 'Demo game not found'], 404);
            }

            $game_id = $result['game_id'];
            $team_id = $result['team_id'];

            // Create hunted team if it doesn't exist
            if (!$team_id) {
                $team_code = 'RUN' . rand(10, 99);
                $stmt = $pdo->prepare('INSERT INTO teams (game_id, name, role, join_code) VALUES (?, "Demo Runners", "hunted", ?)');
                $stmt->execute([$game_id, $team_code]);
                $team_id = $pdo->lastInsertId();
            }

            $created_bots = [];

            // Vienna coordinates for bot placement
            $base_lat = 48.2082;
            $base_lng = 16.3738;
            $radius = 0.01; // ~1km

            for ($i = 1; $i <= $count; $i++) {
                // Create bot player
                $bot_name = 'Demo Bot ' . $i;
                $device_id = 'demo_bot_' . uniqid();

                $stmt = $pdo->prepare('INSERT INTO players (team_id, device_id, display_name, is_captain, last_seen) VALUES (?, ?, ?, 0, NOW())');
                $stmt->execute([$team_id, $device_id, $bot_name]);
                $bot_id = $pdo->lastInsertId();

                // Random Vienna area location
                $lat = $base_lat + (mt_rand(-100, 100) / 10000) * $radius;
                $lng = $base_lng + (mt_rand(-100, 100) / 10000) * $radius;

                $stmt = $pdo->prepare('INSERT INTO location_pings (player_id, latitude, longitude) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE latitude = VALUES(latitude), longitude = VALUES(longitude), created_at = NOW()');
                $stmt->execute([$bot_id, $lat, $lng]);

                $created_bots[] = [
                    'id' => $bot_id,
                    'name' => $bot_name,
                    'device_id' => $device_id,
                    'lat' => round($lat, 6),
                    'lng' => round($lng, 6)
                ];
            }

            send_json([
                'success' => true,
                'message' => "$count demo bots created",
                'bots' => $created_bots
            ]);
            break;

        case 'remove_demo_bots':
            $stmt = $pdo->prepare('
                DELETE p FROM players p
                JOIN teams t ON p.team_id = t.id
                JOIN games g ON t.game_id = g.id
                WHERE g.join_code = "DEMO01" AND (
                    p.device_id LIKE "demo_bot_%" OR 
                    p.device_id LIKE "test_bot_%" OR 
                    p.device_id LIKE "bot_%"
                )
            ');
            $stmt->execute();
            $removed = $stmt->rowCount();

            send_json([
                'success' => true,
                'message' => "$removed demo bots removed"
            ]);
            break;

        default:
            send_json(['success' => false, 'error' => 'Unknown action: ' . $action], 400);
    }

} catch (Exception $e) {
    error_log("Admin AJAX Error: " . $e->getMessage() . " in " . $e->getFile() . " on line " . $e->getLine());

    send_json([
        'success' => false,
        'error' => 'Server error occurred',
        'details' => $e->getMessage(),
        'timestamp' => date('Y-m-d H:i:s')
    ], 500);
}
?>

