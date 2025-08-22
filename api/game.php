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
            $stmt = $pdo->prepare('UPDATE games SET status = "active", started_at = NOW() WHERE join_code = ?');
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

        default:
            send_response(400, ['error' => 'Invalid action']);
    }
} catch (Exception $e) {
    send_response(500, ['error' => 'Server error']);
}
?>
