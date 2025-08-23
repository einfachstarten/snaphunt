<?php
require_once __DIR__ . '/api/database.php';

// Simple authentication - change this password!
$admin_password = 'snaphunt2024';
$is_authenticated = false;

session_start();

if (isset($_POST['password']) && $_POST['password'] === $admin_password) {
    $_SESSION['admin_auth'] = true;
}

$is_authenticated = isset($_SESSION['admin_auth']) && $_SESSION['admin_auth'];

if (isset($_GET['logout'])) {
    session_destroy();
    header('Location: admin.php');
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
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Snaphunt Admin Dashboard</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { 
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: #f8fafc;
            color: #1e293b;
            line-height: 1.6;
        }
        
        .container { max-width: 1200px; margin: 0 auto; padding: 20px; }
        
        .header {
            background: white;
            padding: 1.5rem;
            border-radius: 12px;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
            margin-bottom: 2rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .header h1 {
            font-size: 2rem;
            font-weight: 800;
            color: #6366f1;
        }
        
        .status-badge {
            padding: 0.5rem 1rem;
            border-radius: 20px;
            font-size: 0.875rem;
            font-weight: 600;
            text-transform: uppercase;
        }
        
        .status-online { background: #dcfce7; color: #166534; }
        .status-offline { background: #fef2f2; color: #dc2626; }
        
        .grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 2rem;
        }
        
        .card {
            background: white;
            border-radius: 12px;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
            overflow: hidden;
        }
        
        .card-header {
            padding: 1.5rem;
            background: #f8fafc;
            border-bottom: 1px solid #e2e8f0;
            font-weight: 700;
            font-size: 1.125rem;
        }
        
        .card-content { padding: 1.5rem; }
        
        .stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(120px, 1fr));
            gap: 1rem;
            margin-bottom: 2rem;
        }
        
        .stat-card {
            background: white;
            padding: 1.5rem;
            border-radius: 8px;
            text-align: center;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        
        .stat-number {
            font-size: 2rem;
            font-weight: 800;
            color: #6366f1;
        }
        
        .stat-label {
            font-size: 0.875rem;
            color: #64748b;
            text-transform: uppercase;
            font-weight: 600;
            letter-spacing: 0.5px;
        }
        
        .btn {
            padding: 0.5rem 1rem;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-size: 0.875rem;
            font-weight: 500;
            text-decoration: none;
            display: inline-block;
            text-align: center;
        }
        
        .btn-primary { background: #6366f1; color: white; }
        .btn-danger { background: #dc2626; color: white; }
        .btn-secondary { background: #6b7280; color: white; }
        .btn:hover { opacity: 0.9; }
        
        .login-form {
            max-width: 400px;
            margin: 10% auto;
            background: white;
            padding: 2rem;
            border-radius: 12px;
            box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1);
        }
        
        .form-group {
            margin-bottom: 1rem;
        }
        
        .form-group label {
            display: block;
            font-weight: 600;
            margin-bottom: 0.5rem;
        }
        
        .form-group input {
            width: 100%;
            padding: 0.75rem;
            border: 1px solid #d1d5db;
            border-radius: 6px;
            font-size: 1rem;
        }
        
        .alert {
            padding: 1rem;
            border-radius: 6px;
            margin-bottom: 1rem;
        }
        
        .alert-error { background: #fef2f2; color: #dc2626; border: 1px solid #fecaca; }
        
        .quick-stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            margin-bottom: 2rem;
        }
        
        .info-box {
            background: white;
            padding: 1rem;
            border-radius: 8px;
            border-left: 4px solid #6366f1;
        }
    </style>
</head>
<body>

<?php if (!$is_authenticated): ?>
    <div class="login-form">
        <h2 style="margin-bottom: 1.5rem; text-align: center;">🎯 Snaphunt Admin</h2>
        <form method="post">
            <div class="form-group">
                <label for="password">Admin Password:</label>
                <input type="password" name="password" id="password" required>
            </div>
            <button type="submit" class="btn btn-primary" style="width: 100%;">Login</button>
        </form>
        <p style="margin-top: 1rem; font-size: 0.875rem; color: #6b7280; text-align: center;">
            Default password: <code>snaphunt2024</code>
        </p>
    </div>
<?php else: ?>

<div class="container">
    <div class="header">
        <h1>🎯 Snaphunt Admin</h1>
        <div>
            <?php if ($db_connected): ?>
                <span class="status-badge status-online">Database Online</span>
            <?php else: ?>
                <span class="status-badge status-offline">Database Offline</span>
            <?php endif; ?>
            <a href="?logout=1" class="btn btn-secondary" style="margin-left: 1rem;">Logout</a>
        </div>
    </div>

    <?php if (!$db_connected): ?>
        <div class="alert alert-error">
            <strong>Database Connection Failed:</strong> <?php echo htmlspecialchars($db_error); ?>
        </div>
    <?php else: ?>

    <!-- Quick Stats from Database -->
    <div class="quick-stats">
        <?php
        try {
            // Get basic stats
            $stmt = $pdo->query('SELECT COUNT(*) as count FROM games');
            $totalGames = $stmt->fetch()['count'];
            
            $stmt = $pdo->query('SELECT COUNT(*) as count FROM games WHERE status = "active"');
            $activeGames = $stmt->fetch()['count'];
            
            $stmt = $pdo->query('SELECT COUNT(*) as count FROM players');
            $totalPlayers = $stmt->fetch()['count'];
            
            $stmt = $pdo->query('SELECT COUNT(*) as count FROM players WHERE last_seen > DATE_SUB(NOW(), INTERVAL 5 MINUTE)');
            $onlinePlayers = $stmt->fetch()['count'];
            
            $stmt = $pdo->query('SELECT COUNT(*) as count FROM captures');
            $totalCaptures = $stmt->fetch()['count'];
            
            echo "<div class='stat-card'>";
            echo "<div class='stat-number'>$totalGames</div>";
            echo "<div class='stat-label'>Total Games</div>";
            echo "</div>";
            
            echo "<div class='stat-card'>";
            echo "<div class='stat-number'>$activeGames</div>";
            echo "<div class='stat-label'>Active Games</div>";
            echo "</div>";
            
            echo "<div class='stat-card'>";
            echo "<div class='stat-number'>$totalPlayers</div>";
            echo "<div class='stat-label'>Total Players</div>";
            echo "</div>";
            
            echo "<div class='stat-card'>";
            echo "<div class='stat-number'>$onlinePlayers</div>";
            echo "<div class='stat-label'>Online Players</div>";
            echo "</div>";
            
            echo "<div class='stat-card'>";
            echo "<div class='stat-number'>$totalCaptures</div>";
            echo "<div class='stat-label'>Total Captures</div>";
            echo "</div>";
            
        } catch (Exception $e) {
            echo "<div class='alert alert-error'>Error loading stats: " . $e->getMessage() . "</div>";
        }
        ?>
    </div>

    <div class="grid">
        <!-- Games Overview -->
        <div class="card">
            <div class="card-header">🎮 Recent Games</div>
            <div class="card-content">
                <?php
                try {
                    $stmt = $pdo->query('
                        SELECT g.name, g.join_code, g.status, g.created_at,
                               COUNT(DISTINCT t.id) as teams,
                               COUNT(DISTINCT p.id) as players
                        FROM games g
                        LEFT JOIN teams t ON g.id = t.game_id
                        LEFT JOIN players p ON t.id = p.team_id
                        GROUP BY g.id
                        ORDER BY g.created_at DESC
                        LIMIT 10
                    ');
                    $games = $stmt->fetchAll();
                    
                    if (empty($games)) {
                        echo "<p>No games yet.</p>";
                    } else {
                        echo "<table style='width:100%; border-collapse: collapse;'>";
                        echo "<tr style='border-bottom: 1px solid #eee;'>";
                        echo "<th style='text-align: left; padding: 8px;'>Game</th>";
                        echo "<th style='text-align: left; padding: 8px;'>Status</th>";
                        echo "<th style='text-align: left; padding: 8px;'>Teams</th>";
                        echo "<th style='text-align: left; padding: 8px;'>Players</th>";
                        echo "</tr>";
                        
                        foreach ($games as $game) {
                            echo "<tr style='border-bottom: 1px solid #f0f0f0;'>";
                            echo "<td style='padding: 8px;'>";
                            echo "<strong>{$game['name']}</strong><br>";
                            echo "<small>{$game['join_code']}</small>";
                            echo "</td>";
                            echo "<td style='padding: 8px;'>{$game['status']}</td>";
                            echo "<td style='padding: 8px;'>{$game['teams']}</td>";
                            echo "<td style='padding: 8px;'>{$game['players']}</td>";
                            echo "</tr>";
                        }
                        echo "</table>";
                    }
                } catch (Exception $e) {
                    echo "<p>Error loading games: " . $e->getMessage() . "</p>";
                }
                ?>
            </div>
        </div>

        <!-- System Info -->
        <div class="card">
            <div class="card-header">🔧 System Status</div>
            <div class="card-content">
                <div class="info-box" style="margin-bottom: 1rem;">
                    <strong>Database:</strong> ✅ Connected<br>
                    <strong>PHP Version:</strong> <?php echo phpversion(); ?><br>
                    <strong>Last Check:</strong> <?php echo date('Y-m-d H:i:s'); ?>
                </div>
                
                <div style="margin-top: 1rem;">
                    <a href="migrate.php" class="btn btn-secondary">🔄 Run Migration</a>
                    <a href="debug.php" class="btn btn-secondary" style="margin-left: 0.5rem;">🔍 Debug Check</a>
                </div>
                
                <div style="margin-top: 1rem;">
                    <a href="index.html" class="btn btn-primary">🎮 Game Interface</a>
                    <a href="admin.html" class="btn btn-secondary" style="margin-left: 0.5rem;">📱 Create Game</a>
                </div>
            </div>
        </div>

        <!-- Recent Activity -->
        <div class="card">
            <div class="card-header">⚡ Recent Activity</div>
            <div class="card-content">
                <?php
                try {
                    $stmt = $pdo->query('
                        SELECT ge.event_type, ge.created_at, g.name as game_name
                        FROM game_events ge
                        JOIN games g ON ge.game_id = g.id
                        ORDER BY ge.created_at DESC
                        LIMIT 10
                    ');
                    $events = $stmt->fetchAll();
                    
                    if (empty($events)) {
                        echo "<p>No recent activity.</p>";
                    } else {
                        foreach ($events as $event) {
                            echo "<div style='margin-bottom: 0.5rem; padding: 0.5rem; background: #f8fafc; border-radius: 4px;'>";
                            echo "<strong>{$event['event_type']}</strong> in {$event['game_name']}<br>";
                            echo "<small>" . date('M j, H:i', strtotime($event['created_at'])) . "</small>";
                            echo "</div>";
                        }
                    }
                } catch (Exception $e) {
                    echo "<p>No activity data available yet.</p>";
                }
                ?>
            </div>
        </div>
    </div>

    <?php endif; ?>
</div>

<?php endif; ?>
</body>
</html>