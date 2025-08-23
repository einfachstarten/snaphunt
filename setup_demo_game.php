<?php
require_once __DIR__ . '/api/database.php';

try {
    $db = new Database();
    $pdo = $db->getConnection();
    
    // Fixed demo game code
    $demo_code = 'DEMO01';
    $demo_name = 'Live Demo Game - Join Anytime!';
    
    // Check if demo game already exists
    $stmt = $pdo->prepare('SELECT id FROM games WHERE join_code = ?');
    $stmt->execute([$demo_code]);
    $existing = $stmt->fetch();
    
    if ($existing) {
        echo "Demo game already exists with code: $demo_code\n";
        exit;
    }
    
    // Create demo game
    $stmt = $pdo->prepare('INSERT INTO games (name, join_code, status, photo_interval_seconds) VALUES (?, ?, "active", 120)');
    $stmt->execute([$demo_name, $demo_code]);
    $game_id = $pdo->lastInsertId();
    
    echo "Created demo game: $demo_name (ID: $game_id)\n";
    
    // Create bot teams
    $teams = [
        ['name' => 'Red Hunters', 'role' => 'hunter', 'code' => 'HUNT01'],
        ['name' => 'Blue Runners', 'role' => 'hunted', 'code' => 'RUN01']
    ];
    
    foreach ($teams as $team_data) {
        $stmt = $pdo->prepare('INSERT INTO teams (game_id, name, role, join_code) VALUES (?, ?, ?, ?)');
        $stmt->execute([$game_id, $team_data['name'], $team_data['role'], $team_data['code']]);
        $team_id = $pdo->lastInsertId();
        
        // Create bot players for each team
        $bot_count = $team_data['role'] === 'hunter' ? 2 : 3; // 2 hunters, 3 hunted
        
        for ($i = 1; $i <= $bot_count; $i++) {
            $bot_name = $team_data['role'] === 'hunter' ? "Hunter Bot $i" : "Runner Bot $i";
            $device_id = "bot_{$team_data['role']}_$i";
            
            $stmt = $pdo->prepare('INSERT INTO players (team_id, device_id, display_name, is_captain, last_seen) VALUES (?, ?, ?, 0, NOW())');
            $stmt->execute([$team_id, $device_id, $bot_name]);
            
            echo "Created bot: $bot_name ($device_id)\n";
        }
    }
    
    echo "\n✅ Demo game setup complete!\n";
    echo "Game Code: $demo_code\n";
    echo "Join URL: index.html#$demo_code\n";
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>
