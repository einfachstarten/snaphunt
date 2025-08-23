<?php
require_once __DIR__ . '/api/database.php';

try {
    $db = new Database();
    $pdo = $db->getConnection();
    
    echo "🔧 Fixing Demo Game Status...\n";
    
    // Check current demo game status
    $stmt = $pdo->prepare('SELECT * FROM games WHERE join_code = "DEMO01"');
    $stmt->execute();
    $demo = $stmt->fetch();
    
    if (!$demo) {
        echo "❌ Demo game not found. Run setup_demo_game.php first.\n";
        exit(1);
    }
    
    echo "Current status: {$demo['status']}\n";
    
    // Force demo game to active status
    $stmt = $pdo->prepare('UPDATE games SET status = "active", started_at = CURRENT_TIMESTAMP WHERE join_code = "DEMO01"');
    $stmt->execute();
    
    echo "✅ Demo game set to ACTIVE status\n";
    
    // Check team and player counts
    $stmt = $pdo->prepare('
        SELECT 
            COUNT(DISTINCT t.id) as team_count,
            COUNT(DISTINCT p.id) as player_count,
            COUNT(DISTINCT CASE WHEN p.device_id LIKE "bot_%" THEN p.id END) as bot_count
        FROM teams t
        LEFT JOIN players p ON t.id = p.team_id
        WHERE t.game_id = ?
    ');
    $stmt->execute([$demo['id']]);
    $stats = $stmt->fetch();
    
    echo "Game Stats:\n";
    echo "- Teams: {$stats['team_count']}\n";
    echo "- Players: {$stats['player_count']}\n";
    echo "- Bots: {$stats['bot_count']}\n";
    
    // Update bot last_seen to make them appear online
    $stmt = $pdo->prepare('
        UPDATE players p 
        JOIN teams t ON p.team_id = t.id 
        SET p.last_seen = CURRENT_TIMESTAMP 
        WHERE t.game_id = ? AND p.device_id LIKE "bot_%"
    ');
    $stmt->execute([$demo['id']]);
    
    echo "✅ Bot players marked as online\n";
    
    echo "\n🎮 Demo game is ready!\n";
    echo "Join URL: index.html#DEMO01\n";
    echo "Status: ACTIVE (should go directly to game screen)\n";
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}
?>
