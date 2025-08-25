<?php
if (isset($_POST['action'])) {
    header('Location: api/admin_ajax.php');
    exit;
}

session_start();
$admin_password = 'snaphunt2024';

if (isset($_POST['password']) && $_POST['password'] === $admin_password) {
    $_SESSION['admin_auth'] = true;
}

$is_authenticated = isset($_SESSION['admin_auth']) && $_SESSION['admin_auth'];

if (isset($_GET['logout'])) {
    session_destroy();
    header('Location: admin.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>🎯 Snaphunt Admin Cockpit</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { 
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: #f8fafc;
            color: #1e293b;
        }
        
        .container { max-width: 1400px; margin: 0 auto; padding: 20px; }
        
        .header {
            background: linear-gradient(135deg, #6366f1, #8b5cf6);
            color: white;
            padding: 2rem;
            border-radius: 12px;
            margin-bottom: 2rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .header h1 { font-size: 2.5rem; font-weight: 800; }
        
        .grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(350px, 1fr));
            gap: 2rem;
            margin-bottom: 2rem;
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
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .card-content { padding: 1.5rem; }
        
        .btn {
            padding: 0.75rem 1.5rem;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-size: 0.875rem;
            font-weight: 600;
            text-decoration: none;
            display: inline-block;
            text-align: center;
            transition: all 0.2s;
            margin: 0.25rem;
        }
        
        .btn-primary { background: #6366f1; color: white; }
        .btn-success { background: #10b981; color: white; }
        .btn-warning { background: #f59e0b; color: white; }
        .btn-danger { background: #ef4444; color: white; }
        .btn-secondary { background: #6b7280; color: white; }
        .btn:hover { opacity: 0.9; transform: translateY(-1px); }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(120px, 1fr));
            gap: 1rem;
        }
        
        .stat-box {
            background: #f8fafc;
            padding: 1rem;
            border-radius: 8px;
            text-align: center;
        }
        
        .stat-number {
            font-size: 1.875rem;
            font-weight: 800;
            color: #6366f1;
        }
        
        .stat-label {
            font-size: 0.75rem;
            color: #64748b;
            text-transform: uppercase;
            font-weight: 600;
            margin-top: 0.25rem;
        }
        
        .control-group {
            display: flex;
            flex-wrap: wrap;
            gap: 0.5rem;
            align-items: center;
        }
        
        .input-group {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            margin: 0.5rem 0;
        }
        
        .input-group input, .input-group select {
            padding: 0.5rem;
            border: 1px solid #d1d5db;
            border-radius: 6px;
            font-size: 0.875rem;
        }
        
        .alert {
            padding: 1rem;
            border-radius: 8px;
            margin: 1rem 0;
            font-weight: 500;
        }
        
        .alert-success { background: #dcfce7; color: #166534; border: 1px solid #bbf7d0; }
        .alert-error { background: #fef2f2; color: #dc2626; border: 1px solid #fecaca; }
        
        .live-indicator {
            width: 8px;
            height: 8px;
            background: #10b981;
            border-radius: 50%;
            display: inline-block;
            animation: pulse 2s infinite;
            margin-right: 0.5rem;
        }
        
        @keyframes pulse {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.5; }
        }
        
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
    </style>
</head>
<body>

<?php if (!$is_authenticated): ?>
    <div class="login-form">
        <h2 style="margin-bottom: 1.5rem; text-align: center;">🎯 Snaphunt Admin Cockpit</h2>
        <form method="post">
            <div class="form-group">
                <label for="password">Admin Password:</label>
                <input type="password" name="password" id="password" required>
            </div>
            <button type="submit" class="btn btn-primary" style="width: 100%;">Login</button>
        </form>
    </div>
<?php else: ?>

<div class="container">
    <div class="header">
        <div>
            <h1>🎯 Snaphunt Admin</h1>
            <p>Centralized Game Management Cockpit</p>
        </div>
        <div>
            <span class="live-indicator"></span>
            <span>Live Dashboard</span>
            <a href="?logout=1" class="btn btn-danger" style="margin-left: 1rem;">Logout</a>
        </div>
    </div>

    <div id="alerts"></div>

    <div class="grid">
        <!-- Live Stats -->
        <div class="card">
            <div class="card-header">
                📊 Live Statistics
                <button onclick="refreshStats()" class="btn btn-secondary btn-sm">Refresh</button>
            </div>
            <div class="card-content">
                <div class="stats-grid" id="live-stats">
                    <div class="stat-box">
                        <div class="stat-number" id="demo-status">-</div>
                        <div class="stat-label">Demo Status</div>
                    </div>
                    <div class="stat-box">
                        <div class="stat-number" id="demo-players">-</div>
                        <div class="stat-label">Demo Players</div>
                    </div>
                    <div class="stat-box">
                        <div class="stat-number" id="demo-bots">-</div>
                        <div class="stat-label">Demo Bots</div>
                    </div>
                    <div class="stat-box">
                        <div class="stat-number" id="online-players">-</div>
                        <div class="stat-label">Online Now</div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Demo Game Controls -->
        <div class="card">
            <div class="card-header">🎮 Demo Game Controls</div>
            <div class="card-content">
                <div class="control-group">
                    <button onclick="resetDemoGame()" class="btn btn-warning">🔄 Reset Demo Game</button>
                    <button onclick="openDemoGame()" class="btn btn-primary">🚀 Open Demo Game</button>
                </div>
                
                <hr style="margin: 1.5rem 0;">
                
                <h4 style="margin-bottom: 1rem;">Bot Management:</h4>
                <div class="input-group">
                    <label>Create Bots:</label>
                    <input type="number" id="bot-count" min="1" max="5" value="2" />
                    <button onclick="createDemoBots()" class="btn btn-success">➕ Create Bots</button>
                </div>
                <div class="control-group">
                    <button onclick="removeDemoBots()" class="btn btn-danger">❌ Remove All Bots</button>
                </div>
            </div>
        </div>

        <!-- System Controls -->
        <div class="card">
            <div class="card-header">⚙️ System Controls</div>
            <div class="card-content">
                <div class="control-group">
                    <button onclick="clearBrowserCache()" class="btn btn-warning">🗑️ Clear Browser Cache</button>
                    <button onclick="checkSystemHealth()" class="btn btn-secondary">🔍 System Health</button>
                </div>
                
                <hr style="margin: 1.5rem 0;">
                
                <div class="control-group">
                    <button onclick="openGameTester()" class="btn btn-primary">🧪 Game Tester</button>
                    <button onclick="openCreateGame()" class="btn btn-success">➕ Create Game</button>
                </div>
            </div>
        </div>

        <!-- Quick Links -->
        <div class="card">
            <div class="card-header">🔗 Quick Access</div>
            <div class="card-content">
                <div class="control-group">
                    <a href="index.html#DEMO01" target="_blank" class="btn btn-primary">🎯 Join Demo (DEMO01)</a>
                    <a href="admin_game_tester.php" target="_blank" class="btn btn-secondary">🧪 Game Tester</a>
                </div>
                <div class="control-group">
                    <a href="admin.html" target="_blank" class="btn btn-success">➕ Create Game</a>
                    <a href="debug.php" target="_blank" class="btn btn-warning">🔍 Debug Info</a>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-header">
                🔍 Debug Panel
                <button onclick="toggleDebugLog()" class="btn btn-secondary btn-sm">Show Logs</button>
            </div>
            <div class="card-content" id="debug-panel" style="display: none;">
                <div id="error-log" style="max-height: 200px; overflow-y: auto; background: #f8f8f8; padding: 10px; font-family: monospace; font-size: 12px;">
                    Loading debug logs...
                </div>
                <button onclick="refreshDebugLog()" class="btn btn-secondary btn-sm">Refresh Log</button>
                <button onclick="clearDebugLog()" class="btn btn-warning btn-sm">Clear Log</button>
            </div>
        </div>
    </div>
</div>

<script>
async function adminAction(action, data = {}) {
    try {
        const formData = new FormData();
        formData.append('action', action);

        for (const key in data) {
            formData.append(key, data[key]);
        }

        const response = await fetch('api/admin_ajax.php', {
            method: 'POST',
            body: formData
        });

        if (!response.ok) {
            throw new Error(`HTTP ${response.status}: ${response.statusText}`);
        }

        const contentType = response.headers.get('content-type');
        if (!contentType || !contentType.includes('application/json')) {
            const text = await response.text();
            console.error('Non-JSON response received:', text.substring(0, 200));
            throw new Error('Server returned invalid response format');
        }

        const result = await response.json();

        if (result.success) {
            if (result.message) {
                showAlert(result.message, 'success');
            }
        } else {
            showAlert(result.error || 'Action failed', 'error');
        }

        // Only refresh stats after non-stat actions
        if (action !== 'get_live_stats' && result.success) {
            setTimeout(() => refreshStats(), 1000);
        }

        return result;

    } catch (error) {
        console.error('Admin action failed:', error);
        showAlert(`Error: ${error.message}`, 'error');
        return { success: false, error: error.message };
    }
}

let refreshStatsRunning = false;
let autoRefreshInterval = null;

async function refreshStats() {
    if (refreshStatsRunning) {
        return;
    }

    refreshStatsRunning = true;

    try {
        const result = await adminAction('get_live_stats');

        if (result && result.success) {
            const demo = result.demo_stats || {};
            const system = result.system_stats || {};

            // Update UI elements safely
            const statusEl = document.getElementById('demo-status');
            const playersEl = document.getElementById('demo-players');
            const botsEl = document.getElementById('demo-bots');
            const onlineEl = document.getElementById('online-players');

            if (statusEl) statusEl.textContent = (demo.status || 'unknown').toUpperCase();
            if (playersEl) playersEl.textContent = demo.total_players || '0';
            if (botsEl) botsEl.textContent = demo.bot_players || '0';
            if (onlineEl) onlineEl.textContent = demo.online_players || '0';

        } else {
            // Show error state
            ['demo-status', 'demo-players', 'demo-bots', 'online-players'].forEach(id => {
                const el = document.getElementById(id);
                if (el) el.textContent = 'Error';
            });
        }
    } catch (error) {
        console.error('RefreshStats failed:', error);

        // Show error state
        ['demo-status', 'demo-players', 'demo-bots', 'online-players'].forEach(id => {
            const el = document.getElementById(id);
            if (el) el.textContent = 'Error';
        });
    } finally {
        refreshStatsRunning = false;
    }
}

function startAutoRefresh() {
    if (autoRefreshInterval) {
        clearInterval(autoRefreshInterval);
    }

    autoRefreshInterval = setInterval(() => {
        if (!refreshStatsRunning) {
            refreshStats();
        }
    }, 10000);
}

function showAlert(message, type) {
    const alerts = document.getElementById('alerts');
    const alert = document.createElement('div');
    alert.className = `alert alert-${type}`;
    alert.textContent = message;

    alerts.appendChild(alert);

    setTimeout(() => {
        alert.remove();
    }, 5000);
}

async function resetDemoGame() {
    if (confirm('Reset demo game to clean state? This will:\n• Clear all captures\n• Remove all test bots\n• Reset game status\n• Keep real players')) {
        await adminAction('reset_demo_game');
    }
}

async function createDemoBots() {
    const count = document.getElementById('bot-count').value;
    await adminAction('create_demo_bots', { bot_count: count });
}

async function removeDemoBots() {
    if (confirm('Remove all demo bots?')) {
        await adminAction('remove_demo_bots');
    }
}

function clearBrowserCache() {
    if (confirm('This will force-reload the page to clear browser cache. Continue?')) {
        window.location.reload(true);
    }
}

function checkSystemHealth() {
    window.open('debug.php', '_blank');
}

function openGameTester() {
    window.open('admin_game_tester.php', '_blank');
}

function openCreateGame() {
    window.open('admin.html', '_blank');
}

function openDemoGame() {
    window.open('index.html#DEMO01', '_blank');
}

function toggleDebugLog() {
    const panel = document.getElementById('debug-panel');
    const isHidden = panel.style.display === 'none';
    panel.style.display = isHidden ? 'block' : 'none';

    if (isHidden) {
        refreshDebugLog();
    }
}

async function refreshDebugLog() {
    document.getElementById('error-log').textContent = 'Debug logging active. Check browser console for details.';
}

function clearDebugLog() {
    document.getElementById('error-log').textContent = '';
}

document.addEventListener('DOMContentLoaded', () => {
    refreshStats();
    startAutoRefresh();
});
</script>

<?php endif; ?>
</body>
</html>
