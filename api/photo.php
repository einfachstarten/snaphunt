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
$upload_dir = __DIR__ . '/../uploads/';

try {
    switch ($action) {
        case 'upload':
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                send_response(400, ['error' => 'Invalid request method']);
            }
            $slot_id = (int)($_POST['slot_id'] ?? 0);
            $team_id = (int)($_POST['team_id'] ?? 0);
            $player_id = (int)($_POST['player_id'] ?? 0);
            $code = $_POST['slot_code_verification'] ?? '';
            if (!$slot_id || !$player_id || !$team_id || $code === '' || !isset($_FILES['photo'])) {
                send_response(400, ['error' => 'Missing parameters']);
            }
            $file = $_FILES['photo'];
            if ($file['error'] !== UPLOAD_ERR_OK) {
                send_response(400, ['error' => 'Upload error']);
            }
            if ($file['size'] > 5 * 1024 * 1024) {
                send_response(400, ['error' => 'File too large']);
            }
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $mime = finfo_file($finfo, $file['tmp_name']);
            finfo_close($finfo);
            if (!in_array($mime, ['image/jpeg', 'image/png'])) {
                send_response(400, ['error' => 'Invalid file format']);
            }
            $stmt = $pdo->prepare('SELECT slot_code, deadline FROM photo_slots WHERE id = ?');
            $stmt->execute([$slot_id]);
            $slot = $stmt->fetch();
            if (!$slot) {
                send_response(404, ['error' => 'Slot not found']);
            }
            if ($slot['slot_code'] !== $code) {
                send_response(400, ['error' => 'Invalid slot code']);
            }
            if (strtotime($slot['deadline']) < time()) {
                send_response(400, ['error' => 'Slot deadline passed']);
            }
            $ext = $mime === 'image/png' ? '.png' : '.jpg';
            $filename = 'slot' . $slot_id . '_team' . $team_id . '_' . time() . $ext;
            $target = $upload_dir . $filename;
            if (!move_uploaded_file($file['tmp_name'], $target)) {
                send_response(500, ['error' => 'Failed to save file']);
            }
            $stmt = $pdo->prepare('INSERT INTO photos (slot_id, player_id, file_path, original_filename, file_size, mime_type) VALUES (?, ?, ?, ?, ?, ?)');
            $stmt->execute([$slot_id, $player_id, 'uploads/' . $filename, $file['name'], $file['size'], $mime]);
            send_response(200, [
                'success' => true,
                'photo_url' => 'uploads/' . $filename,
                'validation_status' => 'ok'
            ]);
            break;
        case 'recent':
            $game_id = (int)($_GET['game_id'] ?? 0);
            if (!$game_id) {
                send_response(400, ['error' => 'Missing game id']);
            }
            $stmt = $pdo->prepare('SELECT p.file_path, p.created_at, ps.slot_number, ps.slot_code, t.name AS team_name FROM photos p JOIN photo_slots ps ON p.slot_id = ps.id JOIN players pl ON p.player_id = pl.id JOIN teams t ON pl.team_id = t.id WHERE t.game_id = ? ORDER BY p.created_at DESC LIMIT 20');
            $stmt->execute([$game_id]);
            $photos = $stmt->fetchAll();
            send_response(200, [
                'success' => true,
                'photos' => $photos
            ]);
            break;
        default:
            send_response(400, ['error' => 'Invalid action']);
    }
} catch (Exception $e) {
    send_response(500, ['error' => 'Server error']);
}
?>
