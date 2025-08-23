<?php
require_once __DIR__ . '/api/database.php';

// Simple authentication
session_start();
$admin_password = 'snaphunt2024';

if (isset($_POST['password']) && $_POST['password'] === $admin_password) {
    $_SESSION['admin_auth'] = true;
}

$is_authenticated = isset($_SESSION['admin_auth']) && $_SESSION['admin_auth'];

if (isset($_GET['logout'])) {
    session_destroy();
    header('Location: admin_game_tester.php');
    exit;
}

try {
    $db = new Database();
    $pdo = $db->getConnection();
    $db_connected = true;
} catch (Exception $e) {
    $db_connected = false;
    $db_error = $e->getMessage();
}

// Handle game actions
if (isset($_POST['action']) && $db_connected && $is_authenticated) {
    switch ($_POST['action']) {
        case 'start_game':
            $game_id = (int)$_POST['game_id'];
            $stmt = $pdo->prepare('UPDATE games SET status = "active", started_at = CURRENT_TIMESTAMP WHERE id = ?');
            $stmt->execute([$game_id]);
            $message = "Game started successfully!";
            break;
            
        case 'reset_game':
            $game_id = (int)$_POST['game_id'];
            $stmt = $pdo->prepare('UPDATE games SET status = "waiting", started_at = NULL, ended_at = NULL WHERE id = ?');
            $stmt->execute([$game_id]);
            $message = "Game reset to waiting status!";
            break;
            
        case 'add_test_locations':
            $game_id = (int)$_POST['game_id'];
            // Add dummy locations for testing (Vienna area)
            $locations = [
                ['lat' => 48.2082, 'lng' => 16.3738], // Vienna center
                ['lat' => 48.2102, 'lng' => 16.3758], // Nearby
                ['lat' => 48.2062, 'lng' => 16.3718], // Nearby
            ];
            
            $stmt = $pdo->prepare('SELECT p.id FROM players p JOIN teams t ON p.team_id = t.id WHERE t.game_id = ?');
            $stmt->execute([$game_id]);
            $players = $stmt->fetchAll();
            
            foreach ($players as $i => $player) {
                if (isset($locations[$i])) {
                    $loc = $locations[$i];
                    $stmt = $pdo->prepare('INSERT INTO location_pings (player_id, latitude, longitude) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE latitude = VALUES(latitude), longitude = VALUES(longitude)');
                    $stmt->execute([$player['id'], $loc['lat'], $loc['lng']]);
                }
            }
            $message = "Test locations added!";
            break;
            
        case 'delete_game':
            $game_id = (int)$_POST['game_id'];
            $confirm = $_POST['confirm'] ?? '';
            
            if ($confirm === 'DELETE') {
                // Get game name for confirmation message
                $stmt = $pdo->prepare('SELECT name FROM games WHERE id = ?');
                $stmt->execute([$game_id]);
                $game = $stmt->fetch();
                
                if ($game) {
                    // Delete game (CASCADE will handle all related data)
                    $stmt = $pdo->prepare('DELETE FROM games WHERE id = ?');
                    $stmt->execute([$game_id]);
                    
                    if ($stmt->rowCount() > 0) {
                        $message = "Game '{$game['name']}' and all its data deleted successfully!";
                    } else {
                        $error = "Failed to delete game.";
                    }
                } else {
                    $error = "Game not found.";
                }
            } else {
                $error = "Delete confirmation failed. Please type 'DELETE' to confirm.";
            }
            break;

        case 'toggle_bot_simulation':
            $game_id = (int)$_POST['game_id'];
            $action_type = $_POST['bot_action'];

            if ($action_type === 'start') {
                // Start bot simulation in background
                $command = "php " . __DIR__ . "/bot_simulation.php > /dev/null 2>&1 &";
                exec($command);
                $message = "Bot simulation started in background!";
            } else {
                // Stopping bots would require tracking process IDs
                $message = "Bot simulation stop requested (manual process termination required)";
            }
            break;
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>🎮 Snaphunt Game Tester</title>
    <style>
        body { font-family: monospace; padding: 20px; background: #f5f5f5; }
        .container { max-width: 1000px; background: white; padding: 20px; border-radius: 8px; }
        .game-card { border: 1px solid #ddd; margin: 10px 0; padding: 15px; border-radius: 6px; }
        .game-active { border-color: #22c55e; background: #f0fdf4; }
        .game-waiting { border-color: #f59e0b; background: #fffbeb; }
        .btn { padding: 8px 16px; margin: 5px; border: none; border-radius: 4px; cursor: pointer; }
        .btn-success { background: #22c55e; color: white; }
        .btn-warning { background: #f59e0b; color: white; }
        .btn-danger { background: #ef4444; color: white; }
        .btn-primary { background: #3b82f6; color: white; }
        .stats { display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 10px; margin: 20px 0; }
        .stat-box { background: #f8fafc; padding: 15px; border-radius: 6px; text-align: center; }
        .locations { max-height: 200px; overflow-y: auto; background: #f8fafc; padding: 10px; border-radius: 4px; }
        .login-form { max-width: 400px; margin: 100px auto; background: white; padding: 30px; border-radius: 8px; }
        .alert { padding: 10px; margin: 10px 0; border-radius: 4px; }
        .alert-success { background: #dcfce7; color: #166534; }
        .alert-error { background: #fef2f2; color: #dc2626; }
    </style>
</head>
<body>

<?php if (!$is_authenticated): ?>
    <div class="login-form">
        <h2>🎮 Game Tester Login</h2>
        <form method="post">
            <p><input type="password" name="password" placeholder="Admin Password" required style="width: 100%; padding: 10px; margin: 10px 0;"></p>
            <p><button type="submit" class="btn btn-primary" style="width: 100%;">Login</button></p>
        </form>
    </div>
<?php else: ?>

<div class="container">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
        <h1>🎮 Snaphunt Game Tester</h1>
        <a href="?logout=1" class="btn btn-danger">Logout</a>
    </div>
    
    <?php if (isset($message)): ?>
        <div class="alert alert-success"><?php echo $message; ?></div>
    <?php endif; ?>
    
    <?php if (isset($error)): ?>
        <div class="alert alert-error"><?php echo $error; ?></div>
    <?php endif; ?>
    
    <?php if (!$db_connected): ?>
        <div class="alert alert-error">Database connection failed: <?php echo $db_error; ?></div>
    <?php else: ?>
    
    <div style="margin-bottom: 20px;">
        <a href="admin.php" class="btn btn-primary">📊 Admin Dashboard</a>
        <a href="index.html" class="btn btn-primary">🎮 Game Interface</a>
        <a href="admin.html" class="btn btn-success">➕ Create New Game</a>
    </div>

    <?php
    // Get all games with details
    $stmt = $pdo->query('
        SELECT 
            g.*,
            COUNT(DISTINCT t.id) as team_count,
            COUNT(DISTINCT p.id) as player_count,
            COUNT(DISTINCT CASE WHEN p.last_seen > DATE_SUB(NOW(), INTERVAL 5 MINUTE) THEN p.id END) as online_players
        FROM games g
        LEFT JOIN teams t ON g.id = t.game_id
        LEFT JOIN players p ON t.id = p.team_id
        GROUP BY g.id
        ORDER BY g.created_at DESC
        LIMIT 10
    ');
    $games = $stmt->fetchAll();

    foreach ($games as $game):
        $gameClass = $game['status'] === 'active' ? 'game-active' : 'game-waiting';
    ?>
    
    <div class="game-card <?php echo $gameClass; ?>">
        <div style="display: flex; justify-content: space-between; align-items: center;">
            <div>
                <h3><?php echo htmlspecialchars($game['name']); ?> (<?php echo $game['join_code']; ?>)</h3>
                <p><strong>Status:</strong> <?php echo strtoupper($game['status']); ?> | 
                   <strong>Teams:</strong> <?php echo $game['team_count']; ?> | 
                   <strong>Players:</strong> <?php echo $game['player_count']; ?> | 
                   <strong>Online:</strong> <?php echo $game['online_players']; ?></p>
            </div>
            <div>
                <?php if ($game['status'] === 'waiting'): ?>
                    <form method="post" style="display: inline;">
                        <input type="hidden" name="action" value="start_game">
                        <input type="hidden" name="game_id" value="<?php echo $game['id']; ?>">
                        <button type="submit" class="btn btn-success">▶️ Start Game</button>
                    </form>
                <?php else: ?>
                    <form method="post" style="display: inline;">
                        <input type="hidden" name="action" value="reset_game">
                        <input type="hidden" name="game_id" value="<?php echo $game['id']; ?>">
                        <button type="submit" class="btn btn-warning">🔄 Reset Game</button>
                    </form>
                <?php endif; ?>
                
                <form method="post" style="display: inline;">
                    <input type="hidden" name="action" value="add_test_locations">
                    <input type="hidden" name="game_id" value="<?php echo $game['id']; ?>">
                    <button type="submit" class="btn btn-primary">📍 Add Test Locations</button>
                </form>

                <button class="btn btn-warning" onclick="toggleBotSimulation(<?php echo $game['id']; ?>, 'start')">🤖 Start Bots</button>

                <button class="btn btn-danger" onclick="confirmDeleteGame(<?php echo $game['id']; ?>, '<?php echo htmlspecialchars($game['name'], ENT_QUOTES); ?>')">🗑️ Delete</button>

                <a href="index.html#<?php echo $game['join_code']; ?>" target="_blank" class="btn btn-primary">🔗 Join Game</a>
            </div>
        </div>

        <?php if ($game['team_count'] > 0): ?>
        <div class="stats">
            <?php
            // Get teams for this game
            $stmt = $pdo->prepare('
                SELECT t.*, COUNT(p.id) as player_count,
                       COUNT(CASE WHEN p.last_seen > DATE_SUB(NOW(), INTERVAL 5 MINUTE) THEN 1 END) as online_count
                FROM teams t
                LEFT JOIN players p ON t.id = p.team_id
                WHERE t.game_id = ?
                GROUP BY t.id
            ');
            $stmt->execute([$game['id']]);
            $teams = $stmt->fetchAll();
            
            foreach ($teams as $team):
            ?>
            <div class="stat-box">
                <h4><?php echo htmlspecialchars($team['name']); ?></h4>
                <p><strong><?php echo strtoupper($team['role']); ?></strong></p>
                <p><?php echo $team['online_count']; ?>/<?php echo $team['player_count']; ?> online</p>
                <small>Code: <?php echo $team['join_code']; ?></small>
            </div>
            <?php endforeach; ?>
        </div>
        
        <?php
        // Get recent locations for this game
        $stmt = $pdo->prepare('
            SELECT p.display_name, t.name as team_name, t.role,
                   lp.latitude, lp.longitude, lp.created_at,
                   TIMESTAMPDIFF(SECOND, lp.created_at, NOW()) as age_seconds
            FROM location_pings lp
            JOIN players p ON lp.player_id = p.id
            JOIN teams t ON p.team_id = t.id
            WHERE t.game_id = ?
            ORDER BY lp.created_at DESC
            LIMIT 10
        ');
        $stmt->execute([$game['id']]);
        $locations = $stmt->fetchAll();
        
        if (!empty($locations)):
        ?>
        <div class="locations">
            <h4>📍 Recent Locations:</h4>
            <?php foreach ($locations as $loc): ?>
            <p><strong><?php echo htmlspecialchars($loc['display_name']); ?></strong> (<?php echo $loc['role']; ?>) 
               - <?php echo number_format($loc['latitude'], 4); ?>, <?php echo number_format($loc['longitude'], 4); ?>
               <small>(<?php echo $loc['age_seconds']; ?>s ago)</small></p>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
        <?php endif; ?>
    </div>
    
    <?php endforeach; ?>
    
    <?php if (empty($games)): ?>
        <div class="alert alert-error">
            No games found. <a href="admin.html">Create a new game</a> to get started!
        </div>
    <?php endif; ?>
    
    <div style="margin-top: 30px; padding: 20px; background: #f8fafc; border-radius: 8px;">
        <h3>🔧 Testing Guide:</h3>
        <ol>
            <li><strong>Create Game:</strong> Use "Create New Game" button</li>
            <li><strong>Join Teams:</strong> Open game interface and create Hunter/Hunted teams</li>
            <li><strong>Start Game:</strong> Use "▶️ Start Game" button here</li>
            <li><strong>Add Test Locations:</strong> Use "📍 Add Test Locations" for Vienna coordinates</li>
            <li><strong>Test Map:</strong> Game interface should show map with player markers</li>
            <li><strong>Mobile Test:</strong> Open game on phone for real GPS tracking</li>
        </ol>
        
        <p><strong>🎯 Current Status:</strong> Location tracking implemented, capture mechanics in next ticket!</p>
    </div>
    
    <?php endif; ?>
</div>

<!-- Hidden form for delete confirmation -->
<form id="deleteForm" method="post" style="display: none;">
    <input type="hidden" name="action" value="delete_game">
    <input type="hidden" name="game_id" id="deleteGameId">
    <input type="hidden" name="confirm" id="deleteConfirm">
</form>

<script>
function toggleBotSimulation(gameId, action) {
    if (action === 'start') {
        if (confirm('Start bot simulation for this game? Bots will move and perform captures automatically.')) {
            const form = document.createElement('form');
            form.method = 'post';
            form.innerHTML = `
                <input type="hidden" name="action" value="toggle_bot_simulation">
                <input type="hidden" name="game_id" value="${gameId}">
                <input type="hidden" name="bot_action" value="${action}">
            `;
            document.body.appendChild(form);
            form.submit();
        }
    }
}

function confirmDeleteGame(gameId, gameName) {
    const confirmation = prompt(
        `⚠️ WARNING: This will permanently delete "${gameName}" and ALL associated data!\n\n` +
        `This includes:\n` +
        `• All teams and players\n` +
        `• All location data\n` +
        `• All captures and events\n` +
        `• Game history\n\n` +
        `Type 'DELETE' to confirm:`
    );
    
    if (confirmation === 'DELETE') {
        document.getElementById('deleteGameId').value = gameId;
        document.getElementById('deleteConfirm').value = confirmation;
        document.getElementById('deleteForm').submit();
    } else if (confirmation !== null) {
        alert('Delete cancelled. You must type "DELETE" exactly to confirm.');
    }
}
</script>

<?php endif; ?>
</body>
</html>