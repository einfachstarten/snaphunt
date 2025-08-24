class SnaphuntGame {
    constructor() {
        // Enhanced singleton check with debug info
        if (SnaphuntGame.instance) {
            console.warn('🚨 SnaphuntGame DUPLICATE construction attempt!');
            console.warn('📍 Call stack:', new Error().stack);
            console.warn('🔄 Returning existing instance');
            return SnaphuntGame.instance;
        }

        console.log('🎬 NEW SnaphuntGame instance created');
        console.log('📍 Creation call stack:', new Error().stack);

        SnaphuntGame.instance = this;
        SnaphuntGame.createdAt = Date.now();

        this.state = {
            game: null,
            team: null,
            player: null,
            map: null,
            markers: new Map(),
            watchId: null,
            intervals: new Map(),
            status: 'loading',
            initialized: false,
            debugId: Math.random().toString(36).substr(2, 9) // Unique ID per instance
        };

        console.log(`🆔 SnaphuntGame instance ID: ${this.state.debugId}`);
        this.init();
    }

    static getInstance() {
        if (!SnaphuntGame.instance) {
            console.log('🏗️ Creating new SnaphuntGame instance via getInstance()');
            SnaphuntGame.instance = new SnaphuntGame();
        } else {
            console.log('♻️ Returning existing SnaphuntGame instance');
        }
        return SnaphuntGame.instance;
    }

    static getInstanceInfo() {
        return {
            exists: !!SnaphuntGame.instance,
            createdAt: SnaphuntGame.createdAt,
            debugId: SnaphuntGame.instance?.state?.debugId || 'none',
            status: SnaphuntGame.instance?.state?.status || 'none'
        };
    }

    init() {
        console.log(`🚀 Initializing Snaphunt Game (ID: ${this.state.debugId})`);
        console.log('📍 Init call stack:', new Error().stack);
        
        if (this.state.initialized) {
            console.warn('🚨 Game already initialized, skipping');
            console.warn(`📊 Current status: ${this.state.status}`);
            return;
        }

        console.log('🔧 Setting up event listeners...');
        this.setupEventListeners();
        
        console.log('🔍 Checking existing session...');
        const hasExistingSession = this.checkExistingSession();
        console.log(`📋 Session exists: ${hasExistingSession}`);
        
        console.log('🔗 Handling URL hash...');
        this.handleURLHash();
        
        this.state.initialized = true;
        
        // Only show join screen if NO existing session
        if (!hasExistingSession) {
            console.log('⏰ Scheduling join screen show in 1s...');
            setTimeout(() => {
                if (this.state.status === 'loading') {
                    console.log('✅ Showing join screen (no session found)');
                    this.showScreen('join');
                    this.state.status = 'ready';
                } else {
                    console.log(`🚫 NOT showing join screen, status is: ${this.state.status}`);
                }
            }, 1000);
        } else {
            console.log('✅ Session exists, marking as ready immediately');
            this.state.status = 'ready';
        }
    }

    handleURLHash() {
        const hash = window.location.hash.substring(1);
        if (hash && /^[A-Z0-9]{6}$/.test(hash)) {
            console.log(`🔗 Auto-filling join code from URL: ${hash}`);
            setTimeout(() => {
                const joinCodeInput = document.getElementById('join-code');
                if (joinCodeInput) {
                    joinCodeInput.value = hash;
                    const playerNameInput = document.getElementById('player-name');
                    if (playerNameInput) {
                        playerNameInput.focus();
                    }
                }
            }, 100);
        }
    }

    // Screen Management
    showScreen(screenName) {
        console.log(`📱 Switching to ${screenName} screen`);
        console.log(`🆔 Instance ID: ${this.state.debugId}`);
        console.log('📍 showScreen call stack:', new Error().stack);
        
        // Additional protection against unwanted join screen switches
        if (screenName === 'join' && this.state.status === 'game') {
            console.error('🚨 BLOCKED: Attempt to switch to join screen while in game!');
            console.error('📍 Blocked call stack:', new Error().stack);
            return; // BLOCK the switch!
        }
        
        document.querySelectorAll('.screen').forEach(screen => {
            screen.classList.remove('active');
            screen.classList.add('hidden');
        });
        
        const targetScreen = document.getElementById(`${screenName}-screen`);
        if (targetScreen) {
            targetScreen.classList.remove('hidden');
            targetScreen.classList.add('active');
        } else {
            console.error(`❌ Screen not found: ${screenName}-screen`);
        }

        // If switching to game screen, refresh map size
        if (screenName === 'game' && this.state.map) {
            setTimeout(() => {
                console.log('🗺️ Refreshing map size after screen change...');
                this.state.map.invalidateSize();
            }, 100);
        }
    }

    // Session Management
    checkExistingSession() {
        const gameData = localStorage.getItem('currentGame');
        const teamData = localStorage.getItem('currentTeam');
        const playerData = localStorage.getItem('currentPlayer');

        if (gameData && teamData && playerData) {
            try {
                console.log('🔄 Resuming existing session');
                this.state.game = JSON.parse(gameData);
                this.state.team = JSON.parse(teamData);
                this.state.player = JSON.parse(playerData);

                // Validate session data
                if (!this.state.game.code || !this.state.team.id || !this.state.player.id) {
                    throw new Error('Invalid session data');
                }

                // Start game immediately for existing sessions
                setTimeout(() => {
                    if (this.state.status !== 'game') {
                        this.startGame();
                    }
                }, 100);

                return true;
            } catch (error) {
                console.error('❌ Invalid session data:', error);
                this.clearSession();
                return false;
            }
        }

        return false;
    }

    validateSession() {
        // Validate that session data is still valid
        if (!this.state.game || !this.state.team || !this.state.player) {
            console.warn('⚠️ Invalid session data detected, clearing session');
            this.clearSession();
            return false;
        }

        // Check if game still exists on server
        if (this.state.game.code) {
            this.validateGameExists();
        }

        return true;
    }

    async validateGameExists() {
        try {
            const response = await fetch(`api/game.php?action=get&code=${this.state.game.code}`);
            if (!response.ok) {
                console.warn('⚠️ Game no longer exists, clearing session');
                this.clearSession();
                this.showScreen('join');
            }
        } catch (error) {
            console.warn('Could not validate game existence:', error);
        }
    }

    storeSession(gameData, teamData, playerData) {
        if (!gameData || !teamData || !playerData) {
            console.error('Cannot store invalid session data');
            return;
        }

        try {
            localStorage.setItem('currentGame', JSON.stringify(gameData));
            localStorage.setItem('currentTeam', JSON.stringify(teamData));
            localStorage.setItem('currentPlayer', JSON.stringify(playerData));
            console.log('✅ Session stored successfully');
        } catch (error) {
            console.error('Failed to store session:', error);
        }
    }

    clearSession() {
        try {
            localStorage.removeItem('currentGame');
            localStorage.removeItem('currentTeam');
            localStorage.removeItem('currentPlayer');
            console.log('🗑️ Session cleared');

            // Reset state
            this.state.game = null;
            this.state.team = null;
            this.state.player = null;
        } catch (error) {
            console.error('Error clearing session:', error);
        }
    }

    debugSession() {
        console.group('🔍 Session Debug Info');
        console.log('Game State:', this.state.game);
        console.log('Team State:', this.state.team);
        console.log('Player State:', this.state.player);
        console.log('LocalStorage Game:', localStorage.getItem('currentGame'));
        console.log('LocalStorage Team:', localStorage.getItem('currentTeam'));
        console.log('LocalStorage Player:', localStorage.getItem('currentPlayer'));
        console.groupEnd();
    }

    // Device ID Management
    getDeviceId() {
        let deviceId = localStorage.getItem('deviceId');
        if (!deviceId) {
            deviceId = crypto.randomUUID();
            localStorage.setItem('deviceId', deviceId);
        }
        return deviceId;
    }

    // Event Listeners Setup
    setupEventListeners() {
        // Join Game Form
        const joinBtn = document.getElementById('join-btn');
        const joinCode = document.getElementById('join-code');
        const playerName = document.getElementById('player-name');

        if (joinBtn && joinCode && playerName) {
            // Format join code to uppercase
            joinCode.oninput = (e) => {
                e.target.value = e.target.value.toUpperCase();
            };

            joinBtn.onclick = () => {
                const code = joinCode.value.trim();
                const name = playerName.value.trim();
                if (!code || !name) {
                    this.showError('Please enter both join code and player name');
                    return;
                }
                this.joinGame(code, name);
            };
        }

        // Team Creation Form
        const createTeamBtn = document.getElementById('create-team-btn');
        const teamNameInput = document.getElementById('team-name');
        const teamRoleSelect = document.getElementById('team-role');

        if (createTeamBtn && teamNameInput && teamRoleSelect) {
            createTeamBtn.onclick = () => {
                const teamName = teamNameInput.value.trim();
                const role = teamRoleSelect.value;
                if (!teamName) {
                    this.showError('Please enter a team name');
                    return;
                }
                this.createTeam(teamName, role);
            };
        }

        // Back Navigation
        const backBtn = document.getElementById('back-to-join');
        if (backBtn) {
            backBtn.onclick = () => this.showScreen('join');
        }

        // Start Game Button
        const startGameBtn = document.getElementById('start-game-simple');
        if (startGameBtn) {
            startGameBtn.onclick = () => this.startGameRequest();
        }

        // Leave Game Button
        const leaveGameBtn = document.getElementById('leave-game');
        if (leaveGameBtn) {
            leaveGameBtn.onclick = () => this.leaveGame();
        }
    }

    // API Calls
    async joinGame(code, playerName) {
        // Store player name for future auto-fill
        localStorage.setItem('lastPlayerName', playerName);

        this.showLoading();
        try {
            const response = await fetch(`api/game.php?action=get&code=${code}`);
            const data = await response.json();
            
            if (response.ok) {
                this.showTeamSelection(data, code, playerName);
            } else {
                this.showError(data.error || 'Failed to join game');
            }
        } catch (error) {
            this.showError('Network error - please check your connection');
            console.error('Join game error:', error);
        } finally {
            this.hideLoading();
        }
    }

    async createTeam(teamName, role) {
        if (!this.tempGameData || !this.tempPlayerName) {
            this.showError('Missing game or player data');
            return;
        }

        this.showLoading();
        try {
            const response = await fetch('api/team.php?action=create', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    game_code: this.tempGameData.code,
                    team_name: teamName,
                    role: role,
                    player_name: this.tempPlayerName,
                    device_id: this.getDeviceId()
                })
            });
            const data = await response.json();
            
            if (response.ok) {
                this.state.game = this.tempGameData;
                this.state.team = data.team;
                this.state.player = data.player;
                this.storeSession(this.state.game, this.state.team, this.state.player);
                this.setupLobby();
            } else {
                this.showError(data.error || 'Failed to create team');
            }
        } catch (error) {
            this.showError('Network error while creating team');
            console.error('Create team error:', error);
        } finally {
            this.hideLoading();
        }
    }

    async joinTeam(teamCode) {
        if (!this.tempPlayerName) {
            this.showError('Missing player name');
            return;
        }

        this.showLoading();
        try {
            const response = await fetch('api/team.php?action=join', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    team_join_code: teamCode,
                    player_name: this.tempPlayerName,
                    device_id: this.getDeviceId()
                })
            });
            const data = await response.json();
            
            if (response.ok) {
                this.state.game = this.tempGameData;
                this.state.team = data.team;
                this.state.player = data.player;
                this.storeSession(this.state.game, this.state.team, this.state.player);
                this.setupLobby();
            } else {
                this.showError(data.error || 'Failed to join team');
            }
        } catch (error) {
            this.showError('Network error while joining team');
            console.error('Join team error:', error);
        } finally {
            this.hideLoading();
        }
    }

    async startGameRequest() {
        if (!this.state.game) return;
        
        this.showLoading();
        try {
            await fetch(`api/game.php?action=start&code=${this.state.game.code}`, {
                method: 'POST'
            });
            // Don't handle response - let polling detect game start
        } catch (error) {
            this.showError('Failed to start game');
            console.error('Start game error:', error);
        } finally {
            this.hideLoading();
        }
    }

    // UI Helper Methods
    showLoading() {
        const loadingScreen = document.getElementById('loading-screen');
        if (loadingScreen) {
            loadingScreen.classList.add('active');
        }
    }

    hideLoading() {
        const loadingScreen = document.getElementById('loading-screen');
        if (loadingScreen) {
            loadingScreen.classList.remove('active');
        }
    }

    showError(message) {
        console.error('Game Error:', message);
        
        // Remove existing error toasts
        document.querySelectorAll('.error-toast').forEach(toast => toast.remove());
        
        // Create new error toast
        const toast = document.createElement('div');
        toast.className = 'error-toast';
        toast.textContent = message;
        document.body.appendChild(toast);
        
        // Auto-remove after 3 seconds
        setTimeout(() => {
            if (toast.parentNode) {
                toast.remove();
            }
        }, 3000);
    }

    showMessage(message, type = 'info') {
        // Remove existing messages
        document.querySelectorAll('.message-success, .message-error').forEach(msg => msg.remove());
        
        // Create new message
        const messageEl = document.createElement('div');
        messageEl.className = `message-${type}`;
        messageEl.textContent = message;
        
        // Add to bot controls area
        const botControls = document.querySelector('.bot-test-controls');
        if (botControls) {
            botControls.appendChild(messageEl);
            
            // Auto-remove after 3 seconds
            setTimeout(() => {
                if (messageEl.parentNode) {
                    messageEl.remove();
                }
            }, 3000);
        }
    }

    // Team Selection Screen
    showTeamSelection(gameData, code, playerName) {
        this.tempGameData = { ...gameData, code };
        this.tempPlayerName = playerName;

        const gameTitle = document.getElementById('game-title');
        if (gameTitle) {
            gameTitle.textContent = gameData.name;
        }

        if (gameData.status === 'active') {
            console.log('🎮 Game is active - showing quick join options');
            this.showActiveGameJoin(gameData, code, playerName);
        } else {
            console.log('⏳ Game is waiting - showing team lobby');
            this.showWaitingGameLobby(gameData, code, playerName);
        }
    }

    showActiveGameJoin(gameData, code, playerName) {
        const gameControlSection = document.getElementById('game-control-section');
        if (gameControlSection) {
            gameControlSection.innerHTML = `
                <div class="active-game-notice">
                    <h3>🎮 Game is Active!</h3>
                    <p>This game has already started. Join a team to play immediately.</p>
                </div>
            `;
            gameControlSection.classList.remove('hidden');
        }

        this.loadTeams(code);
        this.showScreen('team');
    }

    showWaitingGameLobby(gameData, code, playerName) {
        this.loadTeams(code);
        this.showScreen('team');
        this.setupLobbyPolling();
    }

    async loadTeams(gameCode) {
        const teamsList = document.getElementById('teams-list');
        if (!teamsList) return;
        
        try {
            const response = await fetch(`api/team.php?action=list&game_code=${gameCode}`);
            const data = await response.json();
            
            if (response.ok) {
                teamsList.innerHTML = '';
                
                if (data.teams.length === 0) {
                    teamsList.innerHTML = '<p class="no-teams">No teams created yet. Be the first!</p>';
                } else {
                    data.teams.forEach(team => {
                        const teamElement = this.createTeamCard(team);
                        teamsList.appendChild(teamElement);
                    });
                }
            } else {
                teamsList.innerHTML = '<p class="error">Failed to load teams</p>';
            }
        } catch (error) {
            teamsList.innerHTML = '<p class="error">Network error loading teams</p>';
            console.error('Load teams error:', error);
        }
    }

    createTeamCard(team) {
        const div = document.createElement('div');
        div.className = `team-card role-${team.role}`;
        
        const players = team.players.map(p => p.display_name).join(', ');
        
        div.innerHTML = `
            <div class="team-info">
                <h3 class="team-name">${team.name}</h3>
                <div class="team-meta">
                    <span class="role-badge ${team.role}">${team.role.toUpperCase()}</span>
                    <span class="player-count">${team.player_count} players</span>
                </div>
                <div class="player-list">${players}</div>
            </div>
            <button class="btn btn-secondary join-team-btn" data-code="${team.join_code}">
                Join Team
            </button>
        `;
        
        const joinBtn = div.querySelector('.join-team-btn');
        joinBtn.onclick = () => this.joinTeam(team.join_code);
        
        return div;
    }

    // Lobby Management
    setupLobby() {
        console.log('🏁 Setting up lobby experience');

        if (this.state.game && this.state.game.status === 'active') {
            console.log('🎮 Game already active - starting immediately');
            this.startGame();
            return;
        }

        const gameControlSection = document.getElementById('game-control-section');
        if (gameControlSection) {
            gameControlSection.classList.remove('hidden');
        }

        this.setupLobbyPolling();
    }

    setupLobbyPolling() {
        if (this.state.intervals.has('lobbyPoll')) {
            clearInterval(this.state.intervals.get('lobbyPoll'));
        }

        console.log('🔄 Starting lobby polling');
        const pollInterval = setInterval(async () => {
            if (!this.state.game || !this.state.game.code) return;

            try {
                const response = await fetch(`api/game.php?action=status&code=${this.state.game.code}`);
                const data = await response.json();

                if (data.success && data.status === 'active') {
                    console.log('🎮 Game started! Transitioning to game screen');
                    clearInterval(pollInterval);
                    this.state.intervals.delete('lobbyPoll');

                    this.state.game.status = 'active';
                    this.storeSession(this.state.game, this.state.team, this.state.player);

                    this.startGame();
                }
            } catch (error) {
                console.warn('Lobby poll error:', error);
            }
        }, 2000);

        this.state.intervals.set('lobbyPoll', pollInterval);
    }

    setupRoleSpecificUI() {
        const roleControls = document.getElementById('role-specific-controls');
        if (!roleControls) return;

        const roleInfo = document.getElementById('team-role');
        if (roleInfo) {
            roleInfo.textContent = this.state.team.role.toUpperCase();
            roleInfo.className = `role-badge ${this.state.team.role}`;
        }

        // Clear existing controls
        roleControls.innerHTML = '';

        if (this.state.team.role === 'hunter') {
            roleControls.innerHTML = `
                <div class="hunter-controls">
                    <h3>🏹 Hunter Controls</h3>
                    <p>Get within 50m of hunted players to capture them!</p>
                    <div class="capture-stats">
                        <span id="capture-count">Captures: 0</span>
                    </div>
                </div>
                
                <!-- NEW: Bot Test Controls -->
                <div class="bot-test-controls">
                    <h3>🤖 Bot Testing</h3>
                    <div class="bot-controls">
                        <button id="create-test-bot" class="btn btn-secondary">Create Test Bot</button>
                        <button id="toggle-bot-visibility" class="btn btn-secondary">Hide Bots</button>
                        <button id="remove-test-bots" class="btn btn-danger">Remove All Bots</button>
                    </div>
                    <div class="bot-status">
                        <span id="bot-count">Test Bots: 0</span>
                        <span id="bot-movement-status">Movement: Stopped</span>
                    </div>
                </div>
            `;

            // Setup bot control event listeners
            this.setupBotControls();
        } else if (this.state.team.role === 'hunted') {
            roleControls.innerHTML = `
                <div class="hunted-controls">
                    <h3>🏃 Hunted Controls</h3>
                    <p>Avoid hunters and survive as long as possible!</p>
                    <div class="survival-stats">
                        <span id="survival-time">Survival: 00:00</span>
                    </div>
                </div>
            `;

            // Start survival timer for hunted players
            this.startSurvivalTimer();
        }
    }

    startSurvivalTimer() {
        const startTime = Date.now();

        const updateTimer = () => {
            const elapsed = Math.floor((Date.now() - startTime) / 1000);
            const minutes = Math.floor(elapsed / 60);
            const seconds = elapsed % 60;

            const timerEl = document.getElementById('survival-time');
            if (timerEl) {
                timerEl.textContent = `Survival: ${minutes.toString().padStart(2, '0')}:${seconds.toString().padStart(2, '0')}`;
            }
        };

        // Update immediately and then every second
        updateTimer();
        const timerInterval = setInterval(updateTimer, 1000);
        this.state.intervals.set('survivalTimer', timerInterval);
    }

    // New method for bot controls:
    setupBotControls() {
        const createBotBtn = document.getElementById('create-test-bot');
        const toggleVisibilityBtn = document.getElementById('toggle-bot-visibility');
        const removeBotBtn = document.getElementById('remove-test-bots');

        this.state.botControls = {
            botsVisible: true,
            movementActive: false,
            movementInterval: null
        };

        if (createBotBtn) {
            createBotBtn.onclick = () => this.createTestBot();
        }

        if (toggleVisibilityBtn) {
            toggleVisibilityBtn.onclick = () => this.toggleBotVisibility();
        }

        if (removeBotBtn) {
            removeBotBtn.onclick = () => this.removeTestBots();
        }

        // Start bot movement polling
        this.startBotMovement();
    }

    async createTestBot() {
        if (!this.state.game || !this.state.game.code) return;

        // Get user's current position for bot placement
        navigator.geolocation.getCurrentPosition(async (position) => {
            const { latitude, longitude } = position.coords;
            
            try {
                const response = await fetch('api/test_bot.php?action=create_test_bot', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        game_code: this.state.game.code,
                        center_lat: latitude,
                        center_lng: longitude,
                        max_radius_m: 500
                    })
                });

                const data = await response.json();
                if (response.ok) {
                    console.log('✅ Test bot created:', data.bot);
                    this.showMessage(`Bot "${data.bot.name}" created nearby!`, 'success');
                    this.updateBotCounter();
                } else {
                    this.showError(data.error || 'Failed to create test bot');
                }
            } catch (error) {
                this.showError('Network error creating test bot');
                console.error('Create test bot error:', error);
            }
        }, () => {
            // Fallback to default coordinates if geolocation fails
            this.createTestBotAt(48.4366, 15.5809);
        });
    }

    async createTestBotAt(lat, lng) {
        try {
            const response = await fetch('api/test_bot.php?action=create_test_bot', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    game_code: this.state.game.code,
                    center_lat: lat,
                    center_lng: lng,
                    max_radius_m: 500
                })
            });

            const data = await response.json();
            if (response.ok) {
                console.log('✅ Test bot created at fallback location:', data.bot);
                this.showMessage(`Bot "${data.bot.name}" created!`, 'success');
                this.updateBotCounter();
            } else {
                this.showError(data.error || 'Failed to create test bot');
            }
        } catch (error) {
            this.showError('Network error creating test bot');
            console.error('Create test bot error:', error);
        }
    }

    toggleBotVisibility() {
        this.state.botControls.botsVisible = !this.state.botControls.botsVisible;
        const toggleBtn = document.getElementById('toggle-bot-visibility');
        
        // Toggle bot marker visibility
        this.state.markers.forEach((marker, key) => {
            if (key.startsWith('player_') && this.isTestBot(key)) {
                if (this.state.botControls.botsVisible) {
                    marker.addTo(this.state.map);
                } else {
                    this.state.map.removeLayer(marker);
                }
            }
        });

        if (toggleBtn) {
            toggleBtn.textContent = this.state.botControls.botsVisible ? 'Hide Bots' : 'Show Bots';
        }

        console.log(`🤖 Bots ${this.state.botControls.botsVisible ? 'shown' : 'hidden'}`);
    }

    async removeTestBots() {
        if (!confirm('Remove all test bots from this game?')) return;

        try {
            const response = await fetch('api/test_bot.php?action=remove_test_bots', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    game_code: this.state.game.code
                })
            });

            const data = await response.json();
            if (response.ok) {
                console.log('✅ Test bots removed');
                this.showMessage('All test bots removed!', 'success');
                
                // Remove bot markers from map
                this.state.markers.forEach((marker, key) => {
                    if (key.startsWith('player_') && this.isTestBot(key)) {
                        this.state.map.removeLayer(marker);
                        this.state.markers.delete(key);
                    }
                });

                this.updateBotCounter();
            } else {
                this.showError(data.error || 'Failed to remove test bots');
            }
        } catch (error) {
            this.showError('Network error removing test bots');
            console.error('Remove test bots error:', error);
        }
    }

    startBotMovement() {
        if (this.state.botControls?.movementInterval) return;

        this.state.botControls.movementActive = true;
        
        const moveBots = async () => {
            if (!this.state.game?.code) return;

            // Get user's position for bot movement center
            navigator.geolocation.getCurrentPosition(async (position) => {
                const { latitude, longitude } = position.coords;
                
                try {
                    const response = await fetch('api/test_bot.php?action=move_bots', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({
                            game_code: this.state.game.code,
                            center_lat: latitude,
                            center_lng: longitude,
                            max_radius_m: 500
                        })
                    });

                    const data = await response.json();
                    if (response.ok && data.updated_bots) {
                        // Update bot markers on map
                        data.updated_bots.forEach(bot => {
                            this.updatePlayerMarker(bot.id, bot.latitude, bot.longitude, 'test_bot');
                        });
                    }
                } catch (error) {
                    console.warn('Bot movement error:', error);
                }
            }, () => {
                // Fallback movement with default center
                console.warn('Using fallback coordinates for bot movement');
            });
        };

        // Move bots every 5 seconds
        this.state.botControls.movementInterval = setInterval(moveBots, 5000);
        
        // Update status
        const statusEl = document.getElementById('bot-movement-status');
        if (statusEl) {
            statusEl.textContent = 'Movement: Active';
        }

        console.log('🤖 Bot movement started');
    }

    updateBotCounter() {
        // Count test bot markers
        let botCount = 0;
        this.state.markers.forEach((marker, key) => {
            if (key.startsWith('player_') && this.isTestBot(key)) {
                botCount++;
            }
        });

        const counterEl = document.getElementById('bot-count');
        if (counterEl) {
            counterEl.textContent = `Test Bots: ${botCount}`;
        }
    }

    isTestBot(markerKey) {
        // Check if this is a test bot marker (simplified check)
        // In practice, you'd store bot IDs or check against bot player data
        return markerKey.includes('test_bot') || this.state.testBotIds?.includes(markerKey);
    }

    startGame() {
        console.log(`🎮 startGame() called (Instance ID: ${this.state.debugId})`);
        console.log('📍 startGame call stack:', new Error().stack);
        
        if (this.state.status === 'game') {
            console.log('🚫 Game already started, skipping');
            console.log(`📊 Current game: ${this.state.game?.name || 'unknown'}`);
            return;
        }

        console.log('🎬 Starting game...');
        this.state.status = 'game';

        // Store game state
        this.state.game.id = this.state.game.id || Date.now();

        console.log('🗺️ Initializing map...');
        this.initializeMap();

        console.log('🎭 Setting up role-specific UI...');
        this.setupRoleSpecificUI();

        console.log('📍 Starting location tracking...');
        this.startLocationTracking();

        console.log('🔄 Starting game state polling...');
        this.startGameStatePolling();

        console.log('📱 Showing game screen...');
        this.showScreen('game');

        console.log(`✅ Game started as ${this.state.team.role}`);
    }

    startLocationTracking() {
        console.log('📍 Starting location tracking');

        if (!navigator.geolocation) {
            this.showError('Geolocation not supported on this device');
            return;
        }

        const options = {
            enableHighAccuracy: true,
            timeout: 10000,
            maximumAge: 30000
        };

        this.state.watchId = navigator.geolocation.watchPosition(
            (position) => this.handleLocationUpdate(position),
            (error) => this.handleLocationError(error),
            options
        );

        console.log('✅ Location tracking started');
    }

    async handleLocationUpdate(position) {
        if (!this.state.player) return;

        const { latitude, longitude } = position.coords;

        console.log(`📍 Location update: ${latitude.toFixed(4)}, ${longitude.toFixed(4)}`);

        // Update own marker on map
        this.updatePlayerMarker(this.state.player.id, latitude, longitude, 'own');

        // Send location to server
        try {
            await fetch('api/location.php?action=update', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    player_id: this.state.player.id,
                    latitude,
                    longitude
                })
            });
        } catch (error) {
            console.warn('Failed to update location:', error);
        }
    }

    handleLocationError(error) {
        console.error('Location error:', error);

        switch(error.code) {
            case error.PERMISSION_DENIED:
                this.showError('Location access denied. Please enable location sharing to play.');
                break;
            case error.POSITION_UNAVAILABLE:
                this.showError('Location information unavailable.');
                break;
            case error.TIMEOUT:
                this.showError('Location request timed out.');
                break;
            default:
                this.showError('An unknown location error occurred.');
                break;
        }
    }

    stopLocationTracking() {
        if (this.state.watchId) {
            navigator.geolocation.clearWatch(this.state.watchId);
            this.state.watchId = null;
            console.log('📍 Location tracking stopped');
        }
    }

    initializeMap() {
        console.log(`🗺️ Initializing map (Instance ID: ${this.state.debugId})`);

        const mapContainer = document.getElementById('map');
        if (!mapContainer) {
            console.error('❌ Map container not found');
            return;
        }

        // Complete cleanup of any existing map
        if (this.state.map) {
            console.log('🧹 Cleaning up existing map...');
            try {
                this.state.map.remove();
                this.state.map = null;
            } catch (e) {
                console.warn('⚠️ Error removing existing map:', e);
            }
            this.state.markers.clear();
        }

        // Nuclear cleanup of map container
        if (mapContainer._leaflet_id) {
            console.log('☢️ Nuclear cleanup of map container...');
            delete mapContainer._leaflet_id;
            mapContainer.innerHTML = '';
        }

        // Wait for screen transition to complete, then initialize map
        setTimeout(() => {
            try {
                console.log('🏗️ Creating new Leaflet map...');
                
                // Ensure container has dimensions
                if (mapContainer.offsetWidth === 0 || mapContainer.offsetHeight === 0) {
                    console.warn('⚠️ Map container has no dimensions, forcing size...');
                    mapContainer.style.width = '100%';
                    mapContainer.style.height = '400px';
                }
                
                this.state.map = L.map('map').setView([48.2082, 16.3738], 13); // Vienna center

                L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                    attribution: '© OpenStreetMap contributors'
                }).addTo(this.state.map);

                // Force map to recognize its size
                setTimeout(() => {
                    if (this.state.map) {
                        this.state.map.invalidateSize();
                        console.log('🔄 Map size invalidated for proper display');
                    }
                }, 100);

                this.setupMapControls();

                console.log('✅ Map initialized successfully');
            } catch (error) {
                console.error('❌ Map initialization failed:', error);
                console.error('🗺️ Map container state:', {
                    hasLeafletId: !!mapContainer._leaflet_id,
                    offsetWidth: mapContainer.offsetWidth,
                    offsetHeight: mapContainer.offsetHeight,
                    innerHTML: mapContainer.innerHTML,
                    children: mapContainer.children.length
                });
                
                // Try complete reset
                mapContainer.innerHTML = '';
                delete mapContainer._leaflet_id;
            }
        }, 200); // Wait 200ms for screen transition
    }

    setupMapControls() {
        const centerBtn = document.getElementById('center-map');
        const fullscreenBtn = document.getElementById('toggle-fullscreen');

        if (centerBtn) {
            centerBtn.onclick = () => this.centerMapOnPlayer();
        }

        if (fullscreenBtn) {
            fullscreenBtn.onclick = () => this.toggleMapFullscreen();
        }

        console.log('🎮 Map controls setup complete');
    }

    centerMapOnPlayer() {
        const ownMarker = this.state.markers.get(`player_${this.state.player.id}`);
        if (ownMarker && this.state.map) {
            this.state.map.setView(ownMarker.getLatLng(), 16);
            console.log('🎯 Map centered on player');
        } else {
            console.warn('⚠️ Cannot center map: no own marker found');
        }
    }

    toggleMapFullscreen() {
        const mapContainer = document.getElementById('map-container');

        if (mapContainer.classList.contains('fullscreen')) {
            mapContainer.classList.remove('fullscreen');
            document.exitFullscreen?.();
            console.log('🔄 Exited map fullscreen');
        } else {
            mapContainer.classList.add('fullscreen');
            mapContainer.requestFullscreen?.();
            console.log('🔄 Entered map fullscreen');
        }

        // Refresh map size after fullscreen change
        setTimeout(() => {
            if (this.state.map) {
                this.state.map.invalidateSize();
                console.log('🔄 Map size refreshed after fullscreen toggle');
            }
        }, 200);
    }

    updatePlayerMarker(playerId, lat, lng, type) {
        const key = `player_${playerId}`;
        const isOwnPlayer = playerId === this.state.player.id;
        const isTestBot = type === 'test_bot' || (type && type.includes('bot'));

        if (this.state.markers.has(key)) {
            // Update existing marker
            this.state.markers.get(key).setLatLng([lat, lng]);
        } else {
            // Create new marker with appropriate icon
            let markerType = type;
            if (isTestBot) {
                markerType = 'test_bot';
            } else if (isOwnPlayer) {
                markerType = 'own';
            }

            const marker = L.marker([lat, lng], {
                icon: this.createMarkerIcon(markerType, isOwnPlayer)
            }).addTo(this.state.map);

            // Add popup with player/bot info
            const popupContent = isTestBot 
                ? `Test Bot: ${this.getBotName(playerId)}<br>Role: AI Hunted`
                : `Player: ${this.getPlayerName(playerId)}<br>Role: ${type}`;
            
            marker.bindPopup(popupContent);
            
            // Store marker
            this.state.markers.set(key, marker);

            // Track test bot IDs
            if (isTestBot) {
                this.state.testBotIds = this.state.testBotIds || [];
                if (!this.state.testBotIds.includes(key)) {
                    this.state.testBotIds.push(key);
                }
            }
        }

        // Handle bot visibility
        if (isTestBot && !this.state.botControls?.botsVisible) {
            this.state.map.removeLayer(this.state.markers.get(key));
        }

        // Auto-center on own player initially
        if (isOwnPlayer && this.state.markers.size === 1) {
            this.state.map.setView([lat, lng], 16);
        }
    }

    createMarkerIcon(type, isOwn = false) {
        const colors = {
            'own': '#4CAF50',
            'hunter': '#FF5722', 
            'hunted': '#2196F3',
            'test_bot': '#9C27B0'  // Purple for test bots
        };

        const size = isOwn ? 24 : 20;
        const borderWidth = isOwn ? 4 : 2;

        // Special styling for test bots
        const isBot = type === 'test_bot';
        const botAnimation = isBot ? 'animation: pulse 2s infinite;' : '';
        
        return L.divIcon({
            className: `player-marker player-marker-${type}`,
            html: `<div style="
            background-color: ${colors[type]}; 
            width: ${size}px; 
            height: ${size}px; 
            border-radius: 50%; 
            border: ${borderWidth}px solid white; 
            box-shadow: 0 2px 4px rgba(0,0,0,0.3);
            ${isOwn ? 'box-shadow: 0 0 0 3px rgba(76,175,80,0.3);' : ''}
            ${botAnimation}
        ">
        ${isBot ? '🤖' : ''}
        </div>`,
            iconSize: [size + borderWidth * 2, size + borderWidth * 2],
            iconAnchor: [(size + borderWidth * 2) / 2, (size + borderWidth * 2) / 2]
        });
    }

    getPlayerName(playerId) {
        // Helper method to get player name by ID
        // This will be expanded when we have full player data
        if (playerId === this.state.player.id) {
            return this.state.player.name;
        }
        return `Player ${playerId}`;
    }

    getBotName(playerId) {
        // Helper to get bot name by ID (you'd enhance this with actual bot data)
        return `Bot ${playerId}`;
    }

    startGameStatePolling() {
        // Clear any existing interval
        if (this.state.intervals.has('gameState')) {
            clearInterval(this.state.intervals.get('gameState'));
        }

        // Poll every 5 seconds
        const interval = setInterval(() => {
            this.pollGameState();
        }, 5000);

        this.state.intervals.set('gameState', interval);
        console.log('🔄 Game state polling started');
    }

    async pollGameState() {
        if (!this.state.game || !this.state.game.id) return;

        try {
            const response = await fetch(`api/location.php?action=get&game_id=${this.state.game.id}`);
            const data = await response.json();

            if (response.ok) {
                this.updateOtherPlayersMarkers(data.locations);

                // Add proximity detection
                this.calculateProximities(data.locations);

                // Update UI with online player count
                this.updateGameStats(data.locations);
            }
        } catch (error) {
            console.warn('Failed to poll game state:', error);
        }
    }

    updateGameStats(locations) {
        const onlineCount = locations.filter(l => l.latitude && l.longitude).length;

        // Update header if exists
        const gameTimer = document.getElementById('game-timer');
        if (gameTimer) {
            gameTimer.textContent = `Online: ${onlineCount} players`;
        }
    }

    calculateProximities(locations) {
        const ownLocation = locations.find(l => l.id === this.state.player.id);
        if (!ownLocation || !ownLocation.latitude) return;

        const proximities = locations
            .filter(l => l.id !== this.state.player.id && l.latitude)
            .map(target => {
                const distance = this.calculateDistance(
                    parseFloat(ownLocation.latitude), parseFloat(ownLocation.longitude),
                    parseFloat(target.latitude), parseFloat(target.longitude)
                );
                return { ...target, distance };
            })
            .sort((a, b) => a.distance - b.distance);

        this.updateProximityUI(proximities);

        // Auto-capture logic for hunters
        if (this.state.team.role === 'hunter') {
            const capturable = proximities.find(p => p.role === 'hunted' && p.distance <= 50);
            if (capturable) {
                this.showCaptureOpportunity(capturable);
            } else {
                this.hideCaptureOpportunity();
            }
        }
    }

    calculateDistance(lat1, lon1, lat2, lon2) {
        const R = 6371000; // Earth radius in meters
        const dLat = (lat2 - lat1) * Math.PI / 180;
        const dLon = (lon2 - lon1) * Math.PI / 180;
        const a = Math.sin(dLat/2) * Math.sin(dLat/2) +
                Math.cos(lat1 * Math.PI / 180) * Math.cos(lat2 * Math.PI / 180) *
                Math.sin(dLon/2) * Math.sin(dLon/2);
        const c = 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1-a));
        return R * c;
    }

    updateProximityUI(proximities) {
        // Create proximity panel if it doesn't exist
        let proximityPanel = document.getElementById('proximity-panel');
        if (!proximityPanel) {
            proximityPanel = this.createProximityPanel();
        }

        // Update proximity list
        const proximityList = proximityPanel.querySelector('.proximity-list');
        proximityList.innerHTML = '';

        if (proximities.length === 0) {
            proximityList.innerHTML = '<p class="no-players">No other players nearby</p>';
            return;
        }

        proximities.slice(0, 5).forEach(player => {
            const item = document.createElement('div');
            item.className = 'proximity-item';

            const distanceText = player.distance < 1000 
                ? `${Math.round(player.distance)}m`
                : `${(player.distance / 1000).toFixed(1)}km`;

            const statusClass = player.distance <= 50 ? 'proximity-close' : 
                               player.distance <= 200 ? 'proximity-near' : 'proximity-far';

            item.innerHTML = `
                <div class="player-info">
                    <span class="player-name">${player.display_name}</span>
                    <span class="role-badge role-${player.role}">${player.role.toUpperCase()}</span>
                </div>
                <div class="distance-info ${statusClass}">
                    ${distanceText}
                    ${player.distance <= 50 ? '🎯' : ''}
                </div>
            `;

            proximityList.appendChild(item);
        });
    }

    createProximityPanel() {
        const panel = document.createElement('div');
        panel.id = 'proximity-panel';
        panel.className = 'proximity-panel';
        panel.innerHTML = `
            <div class="proximity-header">
                <h4>📡 Nearby Players</h4>
                <button class="toggle-proximity" onclick="this.parentElement.parentElement.classList.toggle('minimized')">_</button>
            </div>
            <div class="proximity-list"></div>
        `;

        // Add to game screen
        const gameScreen = document.getElementById('game-screen');
        if (gameScreen) {
            gameScreen.appendChild(panel);
        }

        return panel;
    }

    showCaptureOpportunity(target) {
        // Prevent spam captures
        if (this.state.captureTimeout) return;

        let captureAlert = document.getElementById('capture-alert');
        if (!captureAlert) {
            captureAlert = this.createCaptureAlert(target);
        } else {
            this.updateCaptureAlert(captureAlert, target);
        }
    }

    createCaptureAlert(target) {
        const alert = document.createElement('div');
        alert.id = 'capture-alert';
        alert.className = 'capture-alert';
        alert.innerHTML = `
            <div class="capture-content">
                <h3>🎯 Capture Opportunity!</h3>
                <p>You are within range of <strong>${target.display_name}</strong></p>
                <p class="distance">Distance: ${Math.round(target.distance)}m</p>
                <div class="capture-actions">
                    <button class="btn btn-capture" onclick="snaphuntGame.attemptCapture(${target.id})">
                        ⚡ CAPTURE
                    </button>
                    <button class="btn btn-dismiss" onclick="snaphuntGame.hideCaptureOpportunity()">
                        Dismiss
                    </button>
                </div>
            </div>
        `;

        document.body.appendChild(alert);
        return alert;
    }

    updateCaptureAlert(alert, target) {
        const distanceEl = alert.querySelector('.distance');
        const captureBtn = alert.querySelector('.btn-capture');

        if (distanceEl) {
            distanceEl.textContent = `Distance: ${Math.round(target.distance)}m`;
        }

        if (captureBtn) {
            captureBtn.onclick = () => this.attemptCapture(target.id);
        }
    }

    hideCaptureOpportunity() {
        const alert = document.getElementById('capture-alert');
        if (alert) {
            alert.remove();
        }
    }

    async attemptCapture(targetId) {
        if (!this.state.player || !targetId) return;

        console.log(`🎯 Attempting capture: Hunter ${this.state.player.id} → Target ${targetId}`);

        // Disable capture button temporarily
        this.state.captureTimeout = setTimeout(() => {
            delete this.state.captureTimeout;
        }, 30000); // 30 second cooldown

        const captureBtn = document.querySelector('.btn-capture');
        if (captureBtn) {
            captureBtn.disabled = true;
            captureBtn.textContent = 'Capturing...';
        }

        try {
            const response = await fetch('api/game.php?action=capture', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    hunter_id: this.state.player.id,
                    target_id: targetId
                })
            });

            const data = await response.json();

            if (response.ok && data.success) {
                this.showCaptureSuccess(data);
            } else {
                this.showCaptureFailure(data.error || 'Capture failed');
            }

        } catch (error) {
            console.error('Capture error:', error);
            this.showCaptureFailure('Network error during capture');
        } finally {
            // Re-enable button after delay
            setTimeout(() => {
                if (captureBtn) {
                    captureBtn.disabled = false;
                    captureBtn.textContent = '⚡ CAPTURE';
                }
            }, 2000);
        }
    }

    showCaptureSuccess(data) {
        console.log('✅ Capture successful!', data);

        // Hide capture opportunity
        this.hideCaptureOpportunity();

        // Show success message
        this.showCaptureNotification({
            type: 'success',
            title: '🎉 Capture Successful!',
            message: `You captured ${data.target_name} from ${Math.round(data.distance_meters)}m away!`,
            data: data
        });

        // Check if game ended
        if (data.game_ended) {
            setTimeout(() => {
                this.showGameEndScreen('hunters_win', data);
            }, 3000);
        }
    }

    showCaptureFailure(error) {
        console.log('❌ Capture failed:', error);

        this.showCaptureNotification({
            type: 'error',
            title: '❌ Capture Failed',
            message: error,
            timeout: 5000
        });
    }

    showCaptureNotification(options) {
        // Remove existing notifications
        document.querySelectorAll('.capture-notification').forEach(n => n.remove());

        const notification = document.createElement('div');
        notification.className = `capture-notification capture-${options.type}`;
        notification.innerHTML = `
            <div class="notification-content">
                <h4>${options.title}</h4>
                <p>${options.message}</p>
                ${options.data && options.data.remaining_hunted !== undefined 
                    ? `<small>Remaining hunted players: ${options.data.remaining_hunted}</small>` 
                    : ''}
            </div>
        `;

        document.body.appendChild(notification);

        // Auto-remove notification
        setTimeout(() => {
            if (notification.parentNode) {
                notification.remove();
            }
        }, options.timeout || 8000);
    }

    updateOtherPlayersMarkers(locations) {
        locations.forEach(location => {
            if (location.id !== this.state.player.id && location.latitude && location.longitude) {
                this.updatePlayerMarker(
                    location.id,
                    parseFloat(location.latitude),
                    parseFloat(location.longitude),
                    location.role
                );
            }
        });
    }

    leaveGame() {
        if (confirm('Are you sure you want to leave the game?')) {
            console.log('🚪 Leaving game...');

            // Stop location tracking
            this.stopLocationTracking();

            // Clear all intervals
            this.state.intervals.forEach((interval, key) => {
                clearInterval(interval);
            });
            this.state.intervals.clear();

            // Clean up map properly
            if (this.state.map) {
                this.state.map.remove();
                this.state.map = null;
            }
            this.state.markers.clear();

            // Clear session
            this.clearSession();

            // Reset state
            this.state.status = 'ready';

            // Return to join screen
            this.showScreen('join');

            console.log('👋 Left game and cleaned up resources');
        }
    }
}

window.debugSnaphunt = {
    getInstanceInfo: () => SnaphuntGame.getInstanceInfo(),
    getInstance: () => SnaphuntGame.getInstance(),
    clearInstance: () => {
        if (SnaphuntGame.instance) {
            console.log('🗑️ Manually clearing SnaphuntGame instance');
            SnaphuntGame.instance = null;
            SnaphuntGame.createdAt = null;
        }
    },
    forceJoin: () => {
        const game = SnaphuntGame.getInstance();
        game.showScreen('join');
    },
    getState: () => {
        const game = SnaphuntGame.getInstance();
        return {
            status: game.state.status,
            debugId: game.state.debugId,
            hasGame: !!game.state.game,
            hasTeam: !!game.state.team,
            hasPlayer: !!game.state.player,
            initialized: game.state.initialized
        };
    }
};

console.log('🔧 Global debug functions available: window.debugSnaphunt');

document.addEventListener('DOMContentLoaded', () => {
    console.log('📄 DOM Content Loaded event fired');
    console.log('🔍 Checking for existing SnaphuntGame...');
    
    const existingInfo = SnaphuntGame.getInstanceInfo();
    console.log('📊 Existing instance info:', existingInfo);
    
    if (window.snaphuntGame || existingInfo.exists) {
        console.warn('🚨 SnaphuntGame already exists, skipping DOM ready initialization');
        console.log('📍 Existing game info:', existingInfo);
        return;
    }
    
    console.log('🆕 Creating new SnaphuntGame from DOM ready...');
    window.snaphuntGame = SnaphuntGame.getInstance();
    console.log('✅ Snaphunt Game initialized from DOM ready');
});

// Additional safety check for already loaded DOM
if (document.readyState === 'loading') {
    console.log('📄 DOM still loading, waiting for DOMContentLoaded...');
} else {
    console.log('📄 DOM already loaded, checking immediate initialization...');
    if (!window.snaphuntGame && !SnaphuntGame.getInstanceInfo().exists) {
        console.log('🚀 Immediate initialization (DOM already loaded)');
        window.snaphuntGame = SnaphuntGame.getInstance();
    } else {
        console.log('🚫 Skipping immediate init, game already exists');
    }
}
