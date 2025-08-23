<?php
echo "🎮 Snaphunt Demo Game Launcher\n";
echo "===============================\n\n";

// Step 1: Setup demo game
echo "Step 1: Setting up demo game...\n";
include __DIR__ . '/setup_demo_game.php';

echo "\nStep 2: Demo game is ready!\n";
echo "Game Code: DEMO01\n";
echo "Join URL: " . (isset($_SERVER['HTTP_HOST']) ? "https://{$_SERVER['HTTP_HOST']}" : "your-domain.com") . "/index.html#DEMO01\n\n";

echo "Step 3: To start bot simulation, run:\n";
echo "php bot_simulation.php\n\n";

echo "🚀 Demo game is live and ready for testing!\n";
?>
