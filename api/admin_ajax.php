<?php
// api/admin_ajax.php - Dedicated AJAX endpoint
error_reporting(E_ALL);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/../error.log');

require_once __DIR__ . '/database.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin', '*');
header('Access-Control-Allow-Methods', 'POST');
header('Access-Control-Allow-Headers', 'Content-Type');

function send_json_response($data, $status = 200) {
    http_response_code($status);
    echo json_encode($data, JSON_PRETTY_PRINT);
    exit;
}

function log_debug($message, $data = null) {
    $timestamp = date('Y-m-d H:i:s');
    $log_entry = "[$timestamp] ADMIN DEBUG: $message";
    if ($data) {
        $log_entry .= ' | Data: ' . json_encode($data);
    }
    error_log($log_entry);
}

try {
    log_debug('AJAX request received', $_POST);

    // Database connection test
    $db = new Database();
    $pdo = $db->getConnection();
    log_debug('Database connection successful');

    $action = $_POST['action'] ?? '';

    if (empty($action)) {
        send_json_response(['error' => 'No action specified'], 400);
    }

    log_debug('Processing action: ' . $action);

    switch ($action) {
        case 'get_live_stats':
            try {
                // Demo game stats
                $stmt = $pdo->prepare('
                    SELECT 
                        g.status,
                        g.name,
                        COUNT(DISTINCT p.id) as total_players,
                        COUNT(DISTINCT CASE WHEN p.device_id LIKE "test_bot_%" OR p.device_id LIKE "bot_%" THEN p.id END) as bot_players,
                        COUNT(DISTINCT CASE WHEN p.last_seen > DATE_SUB(NOW(), INTERVAL 2 MINUTE) THEN p.id END) as online_players,
                        COUNT(DISTINCT c.id) as total_captures
                    FROM games g
                    LEFT JOIN teams t ON g.id = t.game_id
                    LEFT JOIN players p ON t.id = p.team_id
                    LEFT JOIN captures c ON g.id = c.game_id
                    WHERE g.join_code = "DEMO01"
                    GROUP BY g.id, g.status, g.name
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

                log_debug('Demo stats retrieved', $demo_stats);

                // System stats
                $stmt = $pdo->query('SELECT COUNT(*) as total_games FROM games');
                $total_games = $stmt->fetch()['total_games'];

                $stmt = $pdo->query('SELECT COUNT(*) as active_games FROM games WHERE status = "active"');
                $active_games = $stmt->fetch()['active_games'];

                $response = [
                    'success' => true,
                    'demo_stats' => $demo_stats,
                    'system_stats' => [
                        'total_games' => (int)$total_games,
                        'active_games' => (int)$active_games,
                        'timestamp' => date('Y-m-d H:i:s')
                    ]
                ];

                log_debug('Full stats response prepared', $response);
                send_json_response($response);

            } catch (Exception $e) {
                log_debug('Stats error', ['error' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
                send_json_response(['error' => 'Stats query failed: ' . $e->getMessage()], 500);
            }
            break;

        case 'reset_demo_game':
            try {
                log_debug('Starting demo game reset');

                // Get demo game
                $stmt = $pdo->prepare('SELECT id FROM games WHERE join_code = "DEMO01"');
                $stmt->execute();
                $demo = $stmt->fetch();

                if (!$demo) {
                    send_json_response(['error' => 'Demo game not found'], 404);
                }

                $game_id = $demo['id'];
                log_debug('Found demo game', ['game_id' => $game_id]);

                // Begin transaction for safe reset
                $pdo->beginTransaction();

                // Reset game status
                $stmt = $pdo->prepare('UPDATE games SET status = "active", started_at = NOW() WHERE id = ?');
                $stmt->execute([$game_id]);

                // Clear all captures
                $stmt = $pdo->prepare('DELETE FROM captures WHERE game_id = ?');
                $stmt->execute([$game_id]);
                $captures_deleted = $stmt->rowCount();

                // Remove all test bots
                $stmt = $pdo->prepare('
                    DELETE p FROM players p
                    JOIN teams t ON p.team_id = t.id
                    WHERE t.game_id = ? AND (p.device_id LIKE "test_bot_%" OR p.device_id LIKE "bot_%")
                ');
                $stmt->execute([$game_id]);
                $bots_deleted = $stmt->rowCount();

                // Reset real player last_seen
                $stmt = $pdo->prepare('
                    UPDATE players p
                    JOIN teams t ON p.team_id = t.id
                    SET p.last_seen = NOW()
                    WHERE t.game_id = ? AND p.device_id NOT LIKE "test_bot_%" AND p.device_id NOT LIKE "bot_%"
                ');
                $stmt->execute([$game_id]);

                $pdo->commit();

                log_debug('Demo reset complete', [
                    'captures_deleted' => $captures_deleted,
                    'bots_deleted' => $bots_deleted
                ]);

                send_json_response([
                    'success' => true,
                    'message' => 'Demo game reset successfully',
                    'details' => [
                        'captures_cleared' => $captures_deleted,
                        'bots_removed' => $bots_deleted
                    ]
                ]);

            } catch (Exception $e) {
                $pdo->rollBack();
                log_debug('Reset failed', ['error' => $e->getMessage()]);
                send_json_response(['error' => 'Reset failed: ' . $e->getMessage()], 500);
            }
            break;

        case 'create_demo_bots':
            try {
                $count = max(1, min(5, (int)($_POST['bot_count'] ?? 1)));
                log_debug('Creating demo bots', ['count' => $count]);

                // Get demo game and teams
                $stmt = $pdo->prepare('
                    SELECT g.id as game_id, 
                           ht.id as hunter_team_id, ht.name as hunter_team,
                           rt.id as hunted_team_id, rt.name as hunted_team
                    FROM games g 
                    LEFT JOIN teams ht ON g.id = ht.game_id AND ht.role = "hunter"
                    LEFT JOIN teams rt ON g.id = rt.game_id AND rt.role = "hunted"
                    WHERE g.join_code = "DEMO01"
                ');
                $stmt->execute();
                $result = $stmt->fetch();

                if (!$result || !$result['game_id']) {
                    send_json_response(['error' => 'Demo game not found'], 404);
                }

                // Ensure hunted team exists
                if (!$result['hunted_team_id']) {
                    $stmt = $pdo->prepare('INSERT INTO teams (game_id, name, role, join_code) VALUES (?, "Demo Runners", "hunted", "RUN" . ?)');
                    $stmt->execute([$result['game_id'], rand(10, 99)]);
                    $hunted_team_id = $pdo->lastInsertId();
                    log_debug('Created hunted team', ['team_id' => $hunted_team_id]);
                } else {
                    $hunted_team_id = $result['hunted_team_id'];
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
                    $stmt->execute([$hunted_team_id, $device_id, $bot_name]);
                    $bot_id = $pdo->lastInsertId();

                    // Random location in Vienna area
                    $lat = $base_lat + (mt_rand(-100, 100) / 10000) * $radius;
                    $lng = $base_lng + (mt_rand(-100, 100) / 10000) * $radius;

                    $stmt = $pdo->prepare('INSERT INTO location_pings (player_id, latitude, longitude) VALUES (?, ?, ?)');
                    $stmt->execute([$bot_id, $lat, $lng]);

                    $created_bots[] = [
                        'id' => $bot_id,
                        'name' => $bot_name,
                        'device_id' => $device_id,
                        'lat' => round($lat, 6),
                        'lng' => round($lng, 6)
                    ];
                }

                log_debug('Bots created successfully', ['bots' => $created_bots]);

                send_json_response([
                    'success' => true,
                    'message' => "$count demo bots created",
                    'bots' => $created_bots
                ]);

            } catch (Exception $e) {
                log_debug('Bot creation failed', ['error' => $e->getMessage()]);
                send_json_response(['error' => 'Bot creation failed: ' . $e->getMessage()], 500);
            }
            break;

        case 'remove_demo_bots':
            try {
                log_debug('Removing demo bots');

                $stmt = $pdo->prepare('
                    DELETE p FROM players p
                    JOIN teams t ON p.team_id = t.id
                    JOIN games g ON t.game_id = g.id
                    WHERE g.join_code = "DEMO01" AND (p.device_id LIKE "demo_bot_%" OR p.device_id LIKE "test_bot_%" OR p.device_id LIKE "bot_%")
                ');
                $stmt->execute();
                $removed = $stmt->rowCount();

                log_debug('Bots removed', ['count' => $removed]);

                send_json_response([
                    'success' => true,
                    'message' => "$removed demo bots removed"
                ]);

            } catch (Exception $e) {
                log_debug('Bot removal failed', ['error' => $e->getMessage()]);
                send_json_response(['error' => 'Bot removal failed: ' . $e->getMessage()], 500);
            }
            break;

        default:
            log_debug('Unknown action requested', ['action' => $action]);
            send_json_response(['error' => 'Unknown action: ' . $action], 400);
    }

} catch (Exception $e) {
    log_debug('Fatal error', ['error' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
    send_json_response([
        'error' => 'Server error occurred',
        'details' => $e->getMessage(),
        'timestamp' => date('Y-m-d H:i:s')
    ], 500);
}
?>
