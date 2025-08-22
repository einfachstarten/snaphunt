console.log('🔥 app.js EXECUTING - before DOMContentLoaded');
console.log('📜 app.js loaded');

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', () => {
        console.log('📦 DOMContentLoaded fired');
        initializeSnaphunt();
    });
} else {
    initializeSnaphunt();
}

function initializeSnaphunt() {
    console.log('🚀 Snaphunt App Starting...');

    try {
        // DOM Elements
        const screens = {
            loading: document.getElementById('loading-screen'),
            join: document.getElementById('join-screen'),
            game: document.getElementById('game-screen')
        };

        // Check if all elements exist
        const missingElements = Object.entries(screens)
            .filter(([name, element]) => !element)
            .map(([name]) => name);

        if (missingElements.length > 0) {
            console.error('❌ Missing DOM elements:', missingElements);
            return;
        }

        console.log('✅ All DOM elements found');

        // Screen management
        function showScreen(screenName) {
            // Hide all screens
            Object.values(screens).forEach(screen => {
                screen.classList.remove('active');
                screen.classList.add('hidden');
            });

            // Show target screen
            if (screens[screenName]) {
                screens[screenName].classList.add('active');
                screens[screenName].classList.remove('hidden');
                console.log(`📱 Switched to: ${screenName} screen`);
            } else {
                console.warn('⚠️ Attempted to show unknown screen:', screenName);
            }
        }

        // Initialize app
        function initializeApp() {
            console.log('🔧 Initializing app...');

        // Check if Leaflet is loaded
        if (typeof L === 'undefined') {
            console.error('❌ Leaflet not loaded');
            showErrorMessage('Map library failed to load');
            return;
        }

        console.log('✅ Leaflet loaded successfully');

        // Switch to join screen after short delay
        setTimeout(() => {
            console.log('⏱ Preparing join screen...');
            showScreen('join');
            setupJoinScreen();
        }, 1000);
    }

        // Setup join screen functionality
        function setupJoinScreen() {
            const joinCodeInput = document.getElementById('join-code');
            const playerNameInput = document.getElementById('player-name');
            const joinButton = document.getElementById('join-btn');

        if (joinButton) {
            joinButton.addEventListener('click', handleJoinGame);
            console.log('✅ Join button listener attached');
        }

        if (joinCodeInput) {
            // Auto-uppercase and limit length
            joinCodeInput.addEventListener('input', (e) => {
                e.target.value = e.target.value.toUpperCase().slice(0, 8);
            });
        }
    }

        // Handle join game attempt
        async function handleJoinGame() {
            const joinCodeInput = document.getElementById('join-code');
            const playerNameInput = document.getElementById('player-name');
            const joinButton = document.getElementById('join-btn');

        const joinCode = joinCodeInput?.value?.trim();
        const playerName = playerNameInput?.value?.trim();

        console.log('🎮 Join attempt with values:', { joinCode, playerName });

        // Validation
        if (!joinCode) {
            showErrorMessage('Please enter a join code');
            return;
        }

        if (!playerName) {
            showErrorMessage('Please enter your name');
            return;
        }

        if (joinCode.length !== 6) {
            showErrorMessage('Join code must be 6 characters');
            return;
        }

        // Show loading state
        const originalText = joinButton.textContent;
        joinButton.disabled = true;
        joinButton.textContent = 'Joining...';

        try {
            // Check if game exists
            console.log('🔍 Checking game code:', joinCode);
            const response = await fetch(`api/game.php?action=get&code=${joinCode}`);
            console.log('📡 Response status:', response.status);
            const data = await response.json();
            console.log('📦 Response data:', data);

            if (!response.ok) {
                throw new Error(data.error || 'Game not found');
            }

            console.log('✅ Game found:', data);

            // Store game info for later use
            window.currentGame = {
                joinCode: joinCode,
                playerName: playerName,
                gameData: data
            };

            // Switch to team selection (placeholder for now)
            showTeamSelection(data);

        } catch (error) {
            console.error('❌ Join game error:', error);
            showErrorMessage(error.message || 'Failed to join game');
        } finally {
            // Restore button state
            joinButton.disabled = false;
            joinButton.textContent = originalText;
        }
    }

        // Show team selection (placeholder implementation)
        function showTeamSelection(gameData) {
            // For now, just show a simple alert
            // This will be expanded in the Team Join System ticket
            alert(`Successfully found game: ${gameData.name}\nTeam selection coming soon!`);
            console.log('🎯 Ready for Team Join System implementation');
        }

        // Utility: Show error message
        function showErrorMessage(message) {
            // Simple implementation - can be improved later
            alert('Error: ' + message);
            console.error('❌', message);
        }

        // Utility: Initialize map when needed
        function initializeMap() {
            const mapContainer = document.getElementById('map');
            if (!mapContainer || typeof L === 'undefined') return null;

        try {
            const map = L.map('map').setView([48.2082, 16.3738], 13); // Vienna default
            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                attribution: '© OpenStreetMap contributors'
            }).addTo(map);

            console.log('✅ Map initialized');
            return map;
        } catch (error) {
            console.error('❌ Map initialization failed:', error);
            return null;
        }
    }

        // Global utilities
        window.snaphunt = {
            showScreen,
            initializeMap,
            screens
        };

        // Start the app
        initializeApp();

        console.log('✅ Snaphunt App initialized successfully');
    } catch (error) {
        console.error('❌ Fatal error during initialization:', error);
    }
}


