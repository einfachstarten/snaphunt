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
        case 'stats':
            // Get comprehensive statistics
            $stats = [];
            
            // Total games
            $stmt = $pdo->query('SELECT COUNT(*) as count FROM games');
            $stats['total_games'] = (int)$stmt->fetch()['count'];
            
            // Active games
            $stmt = $pdo->query('SELECT COUNT(*) as count FROM games WHERE status = "active"');
            $stats['active_games'] = (int)$stmt->fetch()['count'];
            
            // Total players
            $stmt = $pdo->query('SELECT COUNT(*) as count FROM players');
            $stats['total_players'] = (int)$stmt->fetch()['count'];
            
            // Online players (last 5 minutes)
            $stmt = $pdo->query('SELECT COUNT(*) as count FROM players WHERE last_seen > DATE_SUB(NOW(), INTERVAL 5 MINUTE)');
            $stats['online_players'] = (int)$stmt->fetch()['count'];
            
            // Total captures
            $stmt = $pdo->query('SELECT COUNT(*) as count FROM captures');
            $stats['total_captures'] = (int)$stmt->fetch()['count'];
            
            // Table count
            $stmt = $pdo->query('SHOW TABLES');
            $stats['table_count'] = $stmt->rowCount();
            
            send_response(200, [
                'success' => true,
                'stats' => $stats,
                'timestamp' => date('Y-m-d H:i:s')
            ]);
            break;
            
        case 'games':
            // Get all games with team and player counts
            $stmt = $pdo->query('
                SELECT 
                    g.id,
                    g.name,
                    g.join_code,
                    g.status,
                    g.created_at,
                    g.started_at,
                    g.ended_at,
                    COUNT(DISTINCT t.id) as team_count,
                    COUNT(DISTINCT p.id) as player_count,
                    COUNT(DISTINCT c.id) as capture_count
                FROM games g
                LEFT JOIN teams t ON g.id = t.game_id
                LEFT JOIN players p ON t.id = p.team_id
                LEFT JOIN captures c ON g.id = c.game_id
                GROUP BY g.id, g.name, g.join_code, g.status, g.created_at, g.started_at, g.ended_at
                ORDER BY g.created_at DESC
            ');
            $games = $stmt->fetchAll();
            
            send_response(200, [
                'success' => true,
                'games' => $games
            ]);
            break;
            
        case 'activity':
            // Get recent game events
            $stmt = $pdo->query('
                SELECT 
                    ge.event_type,
                    ge.created_at,
                    g.name as game_name,
                    g.join_code,
                    t.name as team_name,
                    p.display_name as player_name,
                    ge.event_data
                FROM game_events ge
                JOIN games g ON ge.game_id = g.id
                LEFT JOIN teams t ON ge.team_id = t.id
                LEFT JOIN players p ON ge.player_id = p.id
                ORDER BY ge.created_at DESC
                LIMIT 20
            ');
            $activity = $stmt->fetchAll();
            
            send_response(200, [
                'success' => true,
                'activity' => $activity
            ]);
            break;
            
        case 'locations':
            // Get all player locations with game context
            $stmt = $pdo->query('
                SELECT 
                    p.id,
                    p.display_name,
                    t.name as team_name,
                    t.role,
                    g.name as game_name,
                    g.join_code,
                    lp.latitude,
                    lp.longitude,
                    lp.created_at as last_ping,
                    p.last_seen,
                    TIMESTAMPDIFF(SECOND, p.last_seen, NOW()) as seconds_offline
                FROM players p
                JOIN teams t ON p.team_id = t.id
                JOIN games g ON t.game_id = g.id
                LEFT JOIN location_pings lp ON p.id = lp.player_id
                WHERE p.last_seen > DATE_SUB(NOW(), INTERVAL 1 HOUR)
                ORDER BY p.last_seen DESC
            ');
            $locations = $stmt->fetchAll();
            
            send_response(200, [
                'success' => true,
                'locations' => $locations
            ]);
            break;
            
        case 'game_details':
            $game_id = (int)($_GET['game_id'] ?? 0);
            if (!$game_id) {
                send_response(400, ['error' => 'Missing game_id']);
            }
            
            // Get game with full details
            $stmt = $pdo->prepare('
                SELECT 
                    g.*,
                    COUNT(DISTINCT t.id) as team_count,
                    COUNT(DISTINCT p.id) as player_count,
                    COUNT(DISTINCT c.id) as capture_count
                FROM games g
                LEFT JOIN teams t ON g.id = t.game_id
                LEFT JOIN players p ON t.id = p.team_id
                LEFT JOIN captures c ON g.id = c.game_id
                WHERE g.id = ?
                GROUP BY g.id
            ');
            $stmt->execute([$game_id]);
            $game = $stmt->fetch();
            
            if (!$game) {
                send_response(404, ['error' => 'Game not found']);
            }
            
            // Get teams
            $stmt = $pdo->prepare('
                SELECT 
                    t.*,
                    COUNT(p.id) as player_count
                FROM teams t
                LEFT JOIN players p ON t.id = p.team_id
                WHERE t.game_id = ?
                GROUP BY t.id
                ORDER BY t.created_at
            ');
            $stmt->execute([$game_id]);
            $teams = $stmt->fetchAll();
            
            // Get captures
            $stmt = $pdo->prepare('
                SELECT 
                    c.*,
                    hp.display_name as hunter_name,
                    tp.display_name as target_name,
                    ht.name as hunter_team,
                    tt.name as target_team
                FROM captures c
                JOIN players hp ON c.hunter_player_id = hp.id
                JOIN players tp ON c.hunted_player_id = tp.id
                JOIN teams ht ON hp.team_id = ht.id
                JOIN teams tt ON tp.team_id = tt.id
                WHERE c.game_id = ?
                ORDER BY c.created_at DESC
            ');
            $stmt->execute([$game_id]);
            $captures = $stmt->fetchAll();
            
            send_response(200, [
                'success' => true,
                'game' => $game,
                'teams' => $teams,
                'captures' => $captures
            ]);
            break;
            
        case 'delete_game':
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                send_response(400, ['error' => 'Invalid request method']);
            }
            
            $input = json_decode(file_get_contents('php://input'), true);
            $game_id = (int)($input['game_id'] ?? 0);
            
            if (!$game_id) {
                send_response(400, ['error' => 'Missing game_id']);
            }
            
            // Delete game (cascading deletes will handle related records)
            $stmt = $pdo->prepare('DELETE FROM games WHERE id = ?');
            $stmt->execute([$game_id]);
            
            if ($stmt->rowCount() === 0) {
                send_response(404, ['error' => 'Game not found']);
            }
            
            send_response(200, [
                'success' => true,
                'message' => 'Game deleted successfully'
            ]);
            break;
            
        case 'reset_game':
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                send_response(400, ['error' => 'Invalid request method']);
            }
            
            $input = json_decode(file_get_contents('php://input'), true);
            $game_id = (int)($input['game_id'] ?? 0);
            
            if (!$game_id) {
                send_response(400, ['error' => 'Missing game_id']);
            }
            
            // Reset game to waiting status and clear timestamps
            $stmt = $pdo->prepare('
                UPDATE games 
                SET status = "waiting", started_at = NULL, ended_at = NULL, winner_team_id = NULL
                WHERE id = ?
            ');
            $stmt->execute([$game_id]);
            
            // Clear all captures
            $stmt = $pdo->prepare('DELETE FROM captures WHERE game_id = ?');
            $stmt->execute([$game_id]);
            
            // Clear all location pings for players in this game
            $stmt = $pdo->prepare('
                DELETE lp FROM location_pings lp 
                JOIN players p ON lp.player_id = p.id 
                JOIN teams t ON p.team_id = t.id 
                WHERE t.game_id = ?
            ');
            $stmt->execute([$game_id]);
            
            send_response(200, [
                'success' => true,
                'message' => 'Game reset successfully'
            ]);
            break;
            
        case 'system_info':
            // Get system information
            $info = [];
            
            // Database info
            $stmt = $pdo->query('SELECT VERSION() as version');
            $info['mysql_version'] = $stmt->fetch()['version'];
            
            // Table sizes
            $stmt = $pdo->query('
                SELECT 
                    table_name,
                    table_rows,
                    ROUND(((data_length + index_length) / 1024 / 1024), 2) AS size_mb
                FROM information_schema.TABLES 
                WHERE table_schema = DATABASE()
                ORDER BY size_mb DESC
            ');
            $info['tables'] = $stmt->fetchAll();
            
            // Recent activity summary
            $stmt = $pdo->query('
                SELECT 
                    DATE(created_at) as date,
                    COUNT(*) as events
                FROM game_events 
                WHERE created_at > DATE_SUB(NOW(), INTERVAL 7 DAY)
                GROUP BY DATE(created_at)
                ORDER BY date DESC
            ');
            $info['daily_activity'] = $stmt->fetchAll();
            
            send_response(200, [
                'success' => true,
                'system_info' => $info
            ]);
            break;
            
        default:
            send_response(400, ['error' => 'Invalid action']);
    }
} catch (Exception $e) {
    error_log("Admin API Error: " . $e->getMessage());
    send_response(500, ['error' => 'Server error: ' . $e->getMessage()]);
}
?>