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

function generate_join_code(PDO $pdo): string
{
    $chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
    do {
        $code = '';
        for ($i = 0; $i < 6; $i++) {
            $code .= $chars[random_int(0, strlen($chars) - 1)];
        }
        $stmt = $pdo->prepare('SELECT id FROM games WHERE join_code = ?');
        $stmt->execute([$code]);
        $exists = $stmt->fetch();
    } while ($exists);
    return $code;
}

function generate_slot_code(): string
{
    // Emoji target plus two random alphanumeric characters
    $chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
    $code = '🎯';
    for ($i = 0; $i < 2; $i++) {
        $code .= $chars[random_int(0, strlen($chars) - 1)];
    }
    return $code;
}

try {
    switch ($action) {
        case 'create':
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                send_response(400, ['error' => 'Invalid request method']);
            }
            $input = json_decode(file_get_contents('php://input'), true);
            $name = trim($input['name'] ?? '');
            $interval = (int)($input['photo_interval_seconds'] ?? 0);
            if ($name === '' || $interval < 60 || $interval > 300) {
                send_response(400, ['error' => 'Invalid input']);
            }
            $join_code = generate_join_code($pdo);
            $stmt = $pdo->prepare('INSERT INTO games (name, join_code, photo_interval_seconds) VALUES (?, ?, ?)');
            try {
                $stmt->execute([$name, $join_code, $interval]);
            } catch (PDOException $e) {
                send_response(409, ['error' => 'Duplicate join code']);
            }
            send_response(200, [
                'success' => true,
                'game_id' => $pdo->lastInsertId(),
                'join_code' => $join_code,
            ]);
            break;

        case 'get':
            $code = $_GET['code'] ?? '';
            if (!preg_match('/^[A-Z0-9]{6}$/', $code)) {
                send_response(400, ['error' => 'Invalid join code']);
            }
            $stmt = $pdo->prepare('SELECT id, name, status FROM games WHERE join_code = ?');
            $stmt->execute([$code]);
            $game = $stmt->fetch();
            if (!$game) {
                send_response(404, ['error' => 'Game not found']);
            }
            $stmt = $pdo->prepare('SELECT COUNT(*) AS teams FROM teams WHERE game_id = ?');
            $stmt->execute([$game['id']]);
            $teams = (int)$stmt->fetch()['teams'];
            send_response(200, [
                'success' => true,
                'name' => $game['name'],
                'status' => $game['status'],
                'teams' => $teams,
            ]);
            break;

        case 'start':
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                send_response(400, ['error' => 'Invalid request method']);
            }
            $code = $_GET['code'] ?? '';
            if (!preg_match('/^[A-Z0-9]{6}$/', $code)) {
                send_response(400, ['error' => 'Invalid join code']);
            }
            $stmt = $pdo->prepare('UPDATE games SET status = "active", started_at = CURRENT_TIMESTAMP WHERE join_code = ?');
            $stmt->execute([$code]);
            if ($stmt->rowCount() === 0) {
                send_response(404, ['error' => 'Game not found']);
            }
            send_response(200, ['success' => true]);
            break;

        case 'status':
            $code = $_GET['code'] ?? '';
            if (!preg_match('/^[A-Z0-9]{6}$/', $code)) {
                send_response(400, ['error' => 'Invalid join code']);
            }
            $stmt = $pdo->prepare('SELECT id, status FROM games WHERE join_code = ?');
            $stmt->execute([$code]);
            $game = $stmt->fetch();
            if (!$game) {
                send_response(404, ['error' => 'Game not found']);
            }
            $stmt = $pdo->prepare('SELECT COUNT(*) AS teams FROM teams WHERE game_id = ?');
            $stmt->execute([$game['id']]);
            $teams = (int)$stmt->fetch()['teams'];
            $stmt = $pdo->prepare('SELECT COALESCE(MAX(slot_number),0) AS slot FROM photo_slots WHERE game_id = ?');
            $stmt->execute([$game['id']]);
            $slot = (int)$stmt->fetch()['slot'];
            send_response(200, [
                'success' => true,
                'status' => $game['status'],
                'active_teams' => $teams,
                'current_photo_slot' => $slot,
            ]);
            break;

        case 'current_slot':
            $code = $_GET['code'] ?? '';
            if (!preg_match('/^[A-Z0-9]{6}$/', $code)) {
                send_response(400, ['error' => 'Invalid join code']);
            }
            $stmt = $pdo->prepare('SELECT id, photo_interval_seconds FROM games WHERE join_code = ?');
            $stmt->execute([$code]);
            $game = $stmt->fetch();
            if (!$game) {
                send_response(404, ['error' => 'Game not found']);
            }
            $stmt = $pdo->prepare('SELECT * FROM photo_slots WHERE game_id = ? ORDER BY slot_number DESC LIMIT 1');
            $stmt->execute([$game['id']]);
            $slot = $stmt->fetch();
            if (!$slot || strtotime($slot['deadline']) < time()) {
                // create new slot if none or expired
                $nextNum = $slot ? ($slot['slot_number'] + 1) : 1;
                $codeSlot = generate_slot_code();
                $deadline = date('Y-m-d H:i:s', time() + (int)$game['photo_interval_seconds']);
                $stmt = $pdo->prepare('INSERT INTO photo_slots (game_id, slot_number, slot_code, deadline) VALUES (?, ?, ?, ?)');
                $stmt->execute([$game['id'], $nextNum, $codeSlot, $deadline]);
                $slot = [
                    'id' => $pdo->lastInsertId(),
                    'slot_number' => $nextNum,
                    'slot_code' => $codeSlot,
                    'deadline' => $deadline
                ];
            }
            $time_remaining = max(0, strtotime($slot['deadline']) - time());
            send_response(200, [
                'success' => true,
                'slot_id' => (int)$slot['id'],
                'slot_number' => (int)$slot['slot_number'],
                'slot_code' => $slot['slot_code'],
                'deadline' => $slot['deadline'],
                'time_remaining' => $time_remaining
            ]);
            break;

        case 'create_slot':
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                send_response(400, ['error' => 'Invalid request method']);
            }
            $code = $_GET['code'] ?? '';
            if (!preg_match('/^[A-Z0-9]{6}$/', $code)) {
                send_response(400, ['error' => 'Invalid join code']);
            }
            $stmt = $pdo->prepare('SELECT id, photo_interval_seconds FROM games WHERE join_code = ?');
            $stmt->execute([$code]);
            $game = $stmt->fetch();
            if (!$game) {
                send_response(404, ['error' => 'Game not found']);
            }
            $stmt = $pdo->prepare('SELECT COALESCE(MAX(slot_number),0) AS slot FROM photo_slots WHERE game_id = ?');
            $stmt->execute([$game['id']]);
            $nextNum = (int)$stmt->fetch()['slot'] + 1;
            $codeSlot = generate_slot_code();
            $deadline = date('Y-m-d H:i:s', time() + (int)$game['photo_interval_seconds']);
            $stmt = $pdo->prepare('INSERT INTO photo_slots (game_id, slot_number, slot_code, deadline) VALUES (?, ?, ?, ?)');
            $stmt->execute([$game['id'], $nextNum, $codeSlot, $deadline]);
            send_response(200, [
                'success' => true,
                'slot_id' => $pdo->lastInsertId(),
                'slot_code' => $codeSlot,
                'deadline' => $deadline
            ]);
            break;

        case 'capture':
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                send_response(400, ['error' => 'Invalid request method']);
            }

            $input = json_decode(file_get_contents('php://input'), true);
            $hunter_id = (int)($input['hunter_id'] ?? 0);
            $target_id = (int)($input['target_id'] ?? 0);

            if (!$hunter_id || !$target_id) {
                send_response(400, ['error' => 'Invalid hunter_id or target_id']);
            }

            // Verify capture conditions: roles, proximity, and game state
            $stmt = $pdo->prepare('
                SELECT 
                    h.id as hunter_id,
                    h.display_name as hunter_name,
                    ht.id as hunter_team_id,
                    ht.role as hunter_role,
                    ht.game_id,
                    t.id as target_id,
                    t.display_name as target_name,
                    tt.id as target_team_id,
                    tt.role as target_role,
                    hlp.latitude as hunter_lat,
                    hlp.longitude as hunter_lng,
                    tlp.latitude as target_lat,
                    tlp.longitude as target_lng,
                    g.status as game_status,
                    (6371000 * acos(
                        cos(radians(hlp.latitude)) * cos(radians(tlp.latitude)) *
                        cos(radians(tlp.longitude) - radians(hlp.longitude)) +
                        sin(radians(hlp.latitude)) * sin(radians(tlp.latitude))
                    )) as distance_meters
                FROM players h
                JOIN teams ht ON h.team_id = ht.id
                JOIN games g ON ht.game_id = g.id
                JOIN location_pings hlp ON h.id = hlp.player_id
                JOIN players t ON t.id = ?
                JOIN teams tt ON t.team_id = tt.id
                JOIN location_pings tlp ON t.id = tlp.player_id
                WHERE h.id = ? 
                AND ht.role = "hunter" 
                AND tt.role = "hunted"
                AND ht.game_id = tt.game_id
                AND g.status = "active"
                AND hlp.created_at > DATE_SUB(NOW(), INTERVAL 2 MINUTE)
                AND tlp.created_at > DATE_SUB(NOW(), INTERVAL 2 MINUTE)
                HAVING distance_meters <= 50
            ');
            $stmt->execute([$target_id, $hunter_id]);
            $capture_data = $stmt->fetch();

            if (!$capture_data) {
                send_response(400, ['error' => 'Capture not valid - check proximity, roles, or game status']);
            }

            // Check if target was already captured recently (prevent spam)
            $stmt = $pdo->prepare('
                SELECT id FROM captures 
                WHERE hunted_player_id = ? 
                AND created_at > DATE_SUB(NOW(), INTERVAL 30 SECOND)
            ');
            $stmt->execute([$target_id]);
            if ($stmt->fetch()) {
                send_response(409, ['error' => 'Target already captured recently']);
            }

            // Record the capture
            $stmt = $pdo->prepare('
                INSERT INTO captures (game_id, hunter_player_id, hunted_player_id, distance_meters) 
                VALUES (?, ?, ?, ?)
            ');
            $stmt->execute([
                $capture_data['game_id'],
                $hunter_id,
                $target_id,
                round($capture_data['distance_meters'])
            ]);
            $capture_id = $pdo->lastInsertId();

            // Log capture event
            $stmt = $pdo->prepare('
                INSERT INTO game_events (game_id, team_id, player_id, event_type, event_data) 
                VALUES (?, ?, ?, "capture", ?)
            ');
            $stmt->execute([
                $capture_data['game_id'],
                $capture_data['hunter_team_id'],
                $hunter_id,
                json_encode([
                    'target_player_id' => $target_id,
                    'target_player_name' => $capture_data['target_name'],
                    'distance_meters' => round($capture_data['distance_meters']),
                    'capture_id' => $capture_id
                ])
            ]);

            // Check if game should end (all hunted players captured)
            $stmt = $pdo->prepare('
                SELECT COUNT(*) as remaining_hunted
                FROM players p
                JOIN teams t ON p.team_id = t.id
                WHERE t.game_id = ? 
                AND t.role = "hunted"
                AND p.id NOT IN (
                    SELECT DISTINCT hunted_player_id 
                    FROM captures 
                    WHERE game_id = ?
                )
            ');
            $stmt->execute([$capture_data['game_id'], $capture_data['game_id']]);
            $remaining = $stmt->fetch();

            $game_ended = false;
            if ($remaining['remaining_hunted'] == 0) {
                // End game - hunters win
                $stmt = $pdo->prepare('
                    UPDATE games 
                    SET status = "finished", ended_at = CURRENT_TIMESTAMP, winner_team_id = ?
                    WHERE id = ?
                ');
                $stmt->execute([$capture_data['hunter_team_id'], $capture_data['game_id']]);
                $game_ended = true;
            }

            send_response(200, [
                'success' => true,
                'capture_confirmed' => true,
                'capture_id' => $capture_id,
                'distance_meters' => round($capture_data['distance_meters']),
                'hunter_name' => $capture_data['hunter_name'],
                'target_name' => $capture_data['target_name'],
                'game_ended' => $game_ended,
                'remaining_hunted' => $remaining['remaining_hunted']
            ]);
            break;

        case 'captures':
            $game_id = (int)($_GET['game_id'] ?? 0);

            if (!$game_id) {
                send_response(400, ['error' => 'Invalid game_id']);
            }

            // Get all captures for the game
            $stmt = $pdo->prepare('
                SELECT 
                    c.id,
                    c.distance_meters,
                    c.created_at,
                    hp.display_name as hunter_name,
                    ht.name as hunter_team,
                    tp.display_name as target_name,
                    tt.name as target_team
                FROM captures c
                JOIN players hp ON c.hunter_player_id = hp.id
                JOIN teams ht ON hp.team_id = ht.id
                JOIN players tp ON c.hunted_player_id = tp.id  
                JOIN teams tt ON tp.team_id = tt.id
                WHERE c.game_id = ?
                ORDER BY c.created_at DESC
            ');
            $stmt->execute([$game_id]);
            $captures = $stmt->fetchAll();

            send_response(200, [
                'success' => true,
                'captures' => $captures
            ]);
            break;

        default:
            send_response(400, ['error' => 'Invalid action']);
    }
} catch (Exception $e) {
    send_response(500, ['error' => 'Server error']);
}
?>
