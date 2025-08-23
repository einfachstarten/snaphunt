<?php
require_once __DIR__ . '/api/database.php';

class BotSimulation {
    private $pdo;
    private $game_id;
    private $running = true;
    
    // Vienna city center coordinates
    private $center_lat = 48.2082;
    private $center_lng = 16.3738;
    private $movement_radius = 0.01; // Roughly 1km radius
    
    public function __construct() {
        $db = new Database();
        $this->pdo = $db->getConnection();
        
        // Get demo game ID
        $stmt = $this->pdo->prepare('SELECT id FROM games WHERE join_code = "DEMO01"');
        $stmt->execute();
        $game = $stmt->fetch();
        
        if (!$game) {
            throw new Exception('Demo game not found. Run setup_demo_game.php first.');
        }
        
        $this->game_id = $game['id'];
        echo "🤖 Bot simulation started for demo game (ID: {$this->game_id})\n";
    }
    
    public function run() {
        echo "🎮 Starting continuous bot simulation...\n";
        
        // Signal handlers for clean shutdown
        if (function_exists('pcntl_signal')) {
            pcntl_signal(SIGTERM, [$this, 'shutdown']);
            pcntl_signal(SIGINT, [$this, 'shutdown']);
        }
        
        while ($this->running) {
            try {
                $this->simulateMovement();
                $this->checkCaptures();
                $this->updateGameState();
                
                sleep(5); // Update every 5 seconds
                
                if (function_exists('pcntl_signal_dispatch')) {
                    pcntl_signal_dispatch();
                }
                
            } catch (Exception $e) {
                echo "❌ Simulation error: " . $e->getMessage() . "\n";
                sleep(10); // Wait before retrying
            }
        }
    }
    
    private function simulateMovement() {
        // Get all bot players
        $stmt = $this->pdo->prepare('
            SELECT p.id, p.display_name, t.role, 
                   lp.latitude, lp.longitude
            FROM players p
            JOIN teams t ON p.team_id = t.id
            LEFT JOIN location_pings lp ON p.id = lp.player_id
            WHERE t.game_id = ? AND p.device_id LIKE "bot_%"
        ');
        $stmt->execute([$this->game_id]);
        $bots = $stmt->fetchAll();
        
        foreach ($bots as $bot) {
            $new_location = $this->generateMovement($bot);
            
            // Update bot location
            $stmt = $this->pdo->prepare('
                INSERT INTO location_pings (player_id, latitude, longitude) 
                VALUES (?, ?, ?) 
                ON DUPLICATE KEY UPDATE 
                latitude = VALUES(latitude), 
                longitude = VALUES(longitude), 
                created_at = NOW()
            ');
            $stmt->execute([$bot['id'], $new_location['lat'], $new_location['lng']]);
            
            // Update last_seen
            $stmt = $this->pdo->prepare('UPDATE players SET last_seen = NOW() WHERE id = ?');
            $stmt->execute([$bot['id']]);
        }
        
        echo "📍 Updated " . count($bots) . " bot locations\n";
    }
    
    private function generateMovement($bot) {
        // Initialize bot position if not exists
        if (!$bot['latitude']) {
            return [
                'lat' => $this->center_lat + (rand(-100, 100) / 10000),
                'lng' => $this->center_lng + (rand(-100, 100) / 10000)
            ];
        }
        
        $current_lat = (float)$bot['latitude'];
        $current_lng = (float)$bot['longitude'];
        
        // Different movement patterns based on role
        if ($bot['role'] === 'hunter') {
            // Hunters move more aggressively toward hunted players
            $target = $this->findNearestHunted($current_lat, $current_lng);
            if ($target) {
                // Move toward target with some randomness
                $move_factor = 0.0003; // Moderate movement speed
                $lat_diff = ($target['lat'] - $current_lat) * $move_factor;
                $lng_diff = ($target['lng'] - $current_lng) * $move_factor;
                
                // Add randomness
                $lat_diff += (rand(-50, 50) / 100000);
                $lng_diff += (rand(-50, 50) / 100000);
                
                return [
                    'lat' => $current_lat + $lat_diff,
                    'lng' => $current_lng + $lng_diff
                ];
            }
        } else {
            // Hunted players move away from hunters
            $threat = $this->findNearestHunter($current_lat, $current_lng);
            if ($threat && $threat['distance'] < 200) { // If hunter within 200m
                // Move away from hunter
                $move_factor = 0.0005; // Faster escape movement
                $lat_diff = ($current_lat - $threat['lat']) * $move_factor;
                $lng_diff = ($current_lng - $threat['lng']) * $move_factor;
                
                return [
                    'lat' => $current_lat + $lat_diff,
                    'lng' => $current_lng + $lng_diff
                ];
            }
        }
        
        // Default random movement
        $move_distance = 0.0002; // Base movement distance
        return [
            'lat' => $current_lat + (rand(-100, 100) / 1000000) * $move_distance * 100,
            'lng' => $current_lng + (rand(-100, 100) / 1000000) * $move_distance * 100
        ];
    }
    
    private function findNearestHunted($hunter_lat, $hunter_lng) {
        $stmt = $this->pdo->prepare('
            SELECT lp.latitude as lat, lp.longitude as lng
            FROM players p
            JOIN teams t ON p.team_id = t.id
            JOIN location_pings lp ON p.id = lp.player_id
            WHERE t.game_id = ? AND t.role = "hunted" AND p.device_id LIKE "bot_%"
            ORDER BY 
                (6371000 * acos(cos(radians(?)) * cos(radians(lp.latitude)) * 
                cos(radians(lp.longitude) - radians(?)) + 
                sin(radians(?)) * sin(radians(lp.latitude))))
            LIMIT 1
        ');
        $stmt->execute([$this->game_id, $hunter_lat, $hunter_lng, $hunter_lat]);
        return $stmt->fetch();
    }
    
    private function findNearestHunter($hunted_lat, $hunted_lng) {
        $stmt = $this->pdo->prepare('
            SELECT lp.latitude as lat, lp.longitude as lng,
                   (6371000 * acos(cos(radians(?)) * cos(radians(lp.latitude)) * 
                    cos(radians(lp.longitude) - radians(?)) + 
                    sin(radians(?)) * sin(radians(lp.latitude)))) as distance
            FROM players p
            JOIN teams t ON p.team_id = t.id
            JOIN location_pings lp ON p.id = lp.player_id
            WHERE t.game_id = ? AND t.role = "hunter" AND p.device_id LIKE "bot_%"
            ORDER BY distance
            LIMIT 1
        ');
        $stmt->execute([$hunted_lat, $hunted_lng, $hunted_lat, $this->game_id]);
        return $stmt->fetch();
    }
    
    private function checkCaptures() {
        // Get all hunter bots
        $stmt = $this->pdo->prepare('
            SELECT p.id as hunter_id, lp.latitude as h_lat, lp.longitude as h_lng
            FROM players p
            JOIN teams t ON p.team_id = t.id
            JOIN location_pings lp ON p.id = lp.player_id
            WHERE t.game_id = ? AND t.role = "hunter" AND p.device_id LIKE "bot_%"
        ');
        $stmt->execute([$this->game_id]);
        $hunters = $stmt->fetchAll();
        
        foreach ($hunters as $hunter) {
            // Find nearby hunted players
            $stmt = $this->pdo->prepare('
                SELECT p.id as target_id, lp.latitude as t_lat, lp.longitude as t_lng,
                       (6371000 * acos(cos(radians(?)) * cos(radians(lp.latitude)) * 
                        cos(radians(lp.longitude) - radians(?)) + 
                        sin(radians(?)) * sin(radians(lp.latitude)))) as distance
                FROM players p
                JOIN teams t ON p.team_id = t.id
                JOIN location_pings lp ON p.id = lp.player_id
                WHERE t.game_id = ? AND t.role = "hunted" AND p.device_id LIKE "bot_%"
                HAVING distance <= 50
                ORDER BY distance
                LIMIT 1
            ');
            $stmt->execute([$hunter['h_lat'], $hunter['h_lng'], $hunter['h_lat'], $this->game_id]);
            $target = $stmt->fetch();
            
            if ($target) {
                // Check if not recently captured
                $stmt = $this->pdo->prepare('
                    SELECT id FROM captures 
                    WHERE hunter_player_id = ? AND hunted_player_id = ? 
                    AND created_at > DATE_SUB(NOW(), INTERVAL 2 MINUTE)
                ');
                $stmt->execute([$hunter['hunter_id'], $target['target_id']]);
                
                if (!$stmt->fetch()) {
                    // Execute capture with 30% probability (makes it more realistic)
                    if (rand(1, 100) <= 30) {
                        $this->executeCapture($hunter['hunter_id'], $target['target_id'], $target['distance']);
                    }
                }
            }
        }
    }
    
    private function executeCapture($hunter_id, $target_id, $distance) {
        // Record the capture
        $stmt = $this->pdo->prepare('
            INSERT INTO captures (game_id, hunter_player_id, hunted_player_id, distance_meters) 
            VALUES (?, ?, ?, ?)
        ');
        $stmt->execute([$this->game_id, $hunter_id, $target_id, round($distance)]);
        
        // Log capture event
        $stmt = $this->pdo->prepare('
            INSERT INTO game_events (game_id, player_id, event_type, event_data) 
            VALUES (?, ?, "bot_capture", ?)
        ');
        $stmt->execute([$this->game_id, $hunter_id, json_encode([
            'target_player_id' => $target_id,
            'distance_meters' => round($distance),
            'simulated' => true
        ])]);
        
        echo "🎯 Bot capture executed! Hunter $hunter_id captured target $target_id from " . round($distance) . "m\n";
        
        $this->checkGameEnd();
    }
    
    private function checkGameEnd() {
        // Check if all hunted players are captured
        $stmt = $this->pdo->prepare('
            SELECT COUNT(*) as remaining_hunted
            FROM players p
            JOIN teams t ON p.team_id = t.id
            WHERE t.game_id = ? AND t.role = "hunted" AND p.device_id LIKE "bot_%"
            AND p.id NOT IN (
                SELECT DISTINCT hunted_player_id 
                FROM captures 
                WHERE game_id = ?
            )
        ');
        $stmt->execute([$this->game_id, $this->game_id]);
        $result = $stmt->fetch();
        
        if ($result['remaining_hunted'] == 0) {
            echo "🏆 Game ended! All hunted players captured. Resetting game...\n";
            $this->resetGame();
        }
    }
    
    private function resetGame() {
        // Clear all captures
        $stmt = $this->pdo->prepare('DELETE FROM captures WHERE game_id = ?');
        $stmt->execute([$this->game_id]);
        
        // Reset game status
        $stmt = $this->pdo->prepare('UPDATE games SET started_at = NOW() WHERE id = ?');
        $stmt->execute([$this->game_id]);
        
        echo "🔄 Demo game reset complete. New round starting!\n";
    }
    
    private function updateGameState() {
        // Keep game marked as active
        $stmt = $this->pdo->prepare('UPDATE games SET status = "active" WHERE id = ?');
        $stmt->execute([$this->game_id]);
    }
    
    public function shutdown() {
        echo "\n🛑 Shutting down bot simulation...\n";
        $this->running = false;
    }
}

// Run simulation if called directly
if (php_sapi_name() === 'cli') {
    try {
        $simulation = new BotSimulation();
        $simulation->run();
    } catch (Exception $e) {
        echo "Fatal error: " . $e->getMessage() . "\n";
        exit(1);
    }
}
?>
