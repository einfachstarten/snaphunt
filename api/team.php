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

function generate_team_code(PDO $pdo): string
{
    $chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
    do {
        $code = '';
        for ($i = 0; $i < 6; $i++) {
            $code .= $chars[random_int(0, strlen($chars) - 1)];
        }
        $stmt = $pdo->prepare('SELECT id FROM teams WHERE join_code = ?');
        $stmt->execute([$code]);
        $exists = $stmt->fetch();
    } while ($exists);
    return $code;
}

try {
    switch ($action) {
        case 'list':
            $code = $_GET['game_code'] ?? '';
            if (!preg_match('/^[A-Z0-9]{6}$/', $code)) {
                send_response(400, ['error' => 'Invalid game code']);
            }
            $stmt = $pdo->prepare('SELECT id FROM games WHERE join_code = ?');
            $stmt->execute([$code]);
            $game = $stmt->fetch();
            if (!$game) {
                send_response(404, ['error' => 'Game not found']);
            }
            $stmt = $pdo->prepare('SELECT t.id, t.name, t.role, t.join_code, (SELECT COUNT(*) FROM players p WHERE p.team_id = t.id) AS player_count FROM teams t WHERE t.game_id = ?');
            $stmt->execute([$game['id']]);
            $teams = $stmt->fetchAll();
            send_response(200, ['success' => true, 'teams' => $teams]);
            break;

        case 'create':
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                send_response(400, ['error' => 'Invalid request method']);
            }
            $input = json_decode(file_get_contents('php://input'), true);
            $game_code = $input['game_code'] ?? '';
            $team_name = trim($input['team_name'] ?? '');
            $role = $input['role'] ?? '';
            $player_name = trim($input['player_name'] ?? '');
            $device_id = trim($input['device_id'] ?? '');
            if (!preg_match('/^[A-Z0-9]{6}$/', $game_code) || $team_name === '' || $player_name === '' || !in_array($role, ['hunted', 'hunter'], true) || $device_id === '') {
                send_response(400, ['error' => 'Invalid input']);
            }
            $stmt = $pdo->prepare('SELECT id FROM games WHERE join_code = ?');
            $stmt->execute([$game_code]);
            $game = $stmt->fetch();
            if (!$game) {
                send_response(404, ['error' => 'Game not found']);
            }
            $game_id = $game['id'];
            $stmt = $pdo->prepare('SELECT p.id FROM players p JOIN teams t ON p.team_id = t.id WHERE t.game_id = ? AND p.device_id = ?');
            $stmt->execute([$game_id, $device_id]);
            if ($stmt->fetch()) {
                send_response(409, ['error' => 'Device already joined a team']);
            }
            $stmt = $pdo->prepare('SELECT id FROM teams WHERE game_id = ? AND name = ?');
            $stmt->execute([$game_id, $team_name]);
            if ($stmt->fetch()) {
                send_response(409, ['error' => 'Team name already exists']);
            }
            if ($role === 'hunted') {
                $stmt = $pdo->prepare("SELECT id FROM teams WHERE game_id = ? AND role = 'hunted'");
                $stmt->execute([$game_id]);
                if ($stmt->fetch()) {
                    send_response(409, ['error' => 'Hunted team already exists']);
                }
            }
            $team_code = generate_team_code($pdo);
            $stmt = $pdo->prepare('INSERT INTO teams (game_id, name, role, join_code) VALUES (?,?,?,?)');
            $stmt->execute([$game_id, $team_name, $role, $team_code]);
            $team_id = $pdo->lastInsertId();
            $stmt = $pdo->prepare('INSERT INTO players (team_id, device_id, display_name, is_captain) VALUES (?,?,?,1)');
            $stmt->execute([$team_id, $device_id, $player_name]);
            $player_id = $pdo->lastInsertId();
            send_response(200, [
                'success' => true,
                'team' => ['id' => $team_id, 'name' => $team_name, 'role' => $role, 'join_code' => $team_code],
                'player' => ['id' => $player_id, 'name' => $player_name]
            ]);
            break;

        case 'join':
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                send_response(400, ['error' => 'Invalid request method']);
            }
            $input = json_decode(file_get_contents('php://input'), true);
            $team_code = $input['team_join_code'] ?? '';
            $player_name = trim($input['player_name'] ?? '');
            $device_id = trim($input['device_id'] ?? '');
            if (!preg_match('/^[A-Z0-9]{6}$/', $team_code) || $player_name === '' || $device_id === '') {
                send_response(400, ['error' => 'Invalid input']);
            }
            $stmt = $pdo->prepare('SELECT t.id, t.name, t.role, t.game_id, t.join_code FROM teams t WHERE t.join_code = ?');
            $stmt->execute([$team_code]);
            $team = $stmt->fetch();
            if (!$team) {
                send_response(404, ['error' => 'Team not found']);
            }
            $stmt = $pdo->prepare('SELECT p.id FROM players p JOIN teams t ON p.team_id = t.id WHERE t.game_id = ? AND p.device_id = ?');
            $stmt->execute([$team['game_id'], $device_id]);
            if ($stmt->fetch()) {
                send_response(409, ['error' => 'Device already joined a team']);
            }
            $stmt = $pdo->prepare('INSERT INTO players (team_id, device_id, display_name, is_captain) VALUES (?,?,?,0)');
            $stmt->execute([$team['id'], $device_id, $player_name]);
            $player_id = $pdo->lastInsertId();
            send_response(200, [
                'success' => true,
                'team' => ['id' => $team['id'], 'name' => $team['name'], 'role' => $team['role'], 'join_code' => $team['join_code']],
                'player' => ['id' => $player_id, 'name' => $player_name]
            ]);
            break;

        default:
            send_response(400, ['error' => 'Invalid action']);
    }
} catch (Exception $e) {
    send_response(500, ['error' => 'Server error']);
}
?>
