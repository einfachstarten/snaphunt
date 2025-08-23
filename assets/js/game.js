class SnaphuntGame {
    constructor() {
        this.state = {
            game: null,
            team: null,
            player: null,
            map: null,
            markers: new Map(),
            watchId: null,
            intervals: new Map(),
            status: 'loading'
        };
        this.init();
    }

    init() {
        console.log('🚀 Initializing Snaphunt Game');
        this.setupEventListeners();
        this.checkExistingSession();
        setTimeout(() => {
            this.showScreen('join');
            this.state.status = 'ready';
        }, 1000);
    }

    // Screen Management
    showScreen(screenName) {
        console.log(`📱 Switching to ${screenName} screen`);
        document.querySelectorAll('.screen').forEach(screen => {
            screen.classList.remove('active');
            screen.classList.add('hidden');
        });
        const targetScreen = document.getElementById(`${screenName}-screen`);
        if (targetScreen) {
            targetScreen.classList.remove('hidden');
            targetScreen.classList.add('active');
        }
    }

    // Session Management
    checkExistingSession() {
        const gameData = localStorage.getItem('currentGame');
        const teamData = localStorage.getItem('currentTeam');
        const playerData = localStorage.getItem('currentPlayer');
        
        if (gameData && teamData && playerData) {
            console.log('🔄 Resuming existing session');
            this.state.game = JSON.parse(gameData);
            this.state.team = JSON.parse(teamData);
            this.state.player = JSON.parse(playerData);
            this.startGame();
        }
    }

    storeSession(gameData, teamData, playerData) {
        localStorage.setItem('currentGame', JSON.stringify(gameData));
        localStorage.setItem('currentTeam', JSON.stringify(teamData));
        localStorage.setItem('currentPlayer', JSON.stringify(playerData));
    }

    clearSession() {
        localStorage.removeItem('currentGame');
        localStorage.removeItem('currentTeam');
        localStorage.removeItem('currentPlayer');
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

    // Team Selection Screen
    showTeamSelection(gameData, code, playerName) {
        this.tempGameData = { ...gameData, code };
        this.tempPlayerName = playerName;
        
        const gameTitle = document.getElementById('game-title');
        if (gameTitle) {
            gameTitle.textContent = gameData.name;
        }
        
        this.loadTeams(code);
        this.showScreen('team');
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

    // Placeholder methods for future tickets
    setupLobby() {
        console.log('🏁 Setting up lobby - to be implemented in next tickets');
        // This will be implemented in Ticket 5
    }

    startGame() {
        console.log('🎮 Starting game');

        // Store game state
        this.state.game.id = this.state.game.id || Date.now(); // Temporary ID if missing

        // Initialize map
        this.initializeMap();

        // Start location tracking
        this.startLocationTracking();

        // Start polling for other players
        this.startGameStatePolling();

        // Show game screen
        this.showScreen('game');

        console.log('✅ Game started successfully');
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
        console.log('🗺️ Initializing map');

        // Initialize Leaflet map
        this.state.map = L.map('map').setView([51.505, -0.09], 13);

        // Add tile layer
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            attribution: '© OpenStreetMap contributors'
        }).addTo(this.state.map);

        // Set up map controls
        this.setupMapControls();

        console.log('✅ Map initialized');
    }

    setupMapControls() {
        // Add custom control buttons
        const centerBtn = document.getElementById('center-map');
        const fullscreenBtn = document.getElementById('toggle-fullscreen');

        if (centerBtn) {
            centerBtn.onclick = () => this.centerMapOnPlayer();
        }

        if (fullscreenBtn) {
            fullscreenBtn.onclick = () => this.toggleMapFullscreen();
        }
    }

    centerMapOnPlayer() {
        const ownMarker = this.state.markers.get(`player_${this.state.player.id}`);
        if (ownMarker && this.state.map) {
            this.state.map.setView(ownMarker.getLatLng(), 16);
            console.log('🎯 Map centered on player');
        }
    }

    toggleMapFullscreen() {
        const mapContainer = document.getElementById('map-container');

        if (mapContainer.classList.contains('fullscreen')) {
            mapContainer.classList.remove('fullscreen');
            document.exitFullscreen?.();
        } else {
            mapContainer.classList.add('fullscreen');
            mapContainer.requestFullscreen?.();
        }

        // Refresh map size after fullscreen change
        setTimeout(() => {
            if (this.state.map) {
                this.state.map.invalidateSize();
            }
        }, 100);
    }

    updatePlayerMarker(playerId, lat, lng, type) {
        const key = `player_${playerId}`;

        if (this.state.markers.has(key)) {
            // Update existing marker
            this.state.markers.get(key).setLatLng([lat, lng]);
        } else {
            // Create new marker
            const marker = L.marker([lat, lng], {
                icon: this.createMarkerIcon(type, playerId === this.state.player.id)
            }).addTo(this.state.map);

            // Add popup with player info
            marker.bindPopup(`Player: ${this.getPlayerName(playerId)}<br>Role: ${type}`);
            this.state.markers.set(key, marker);
        }

        // Auto-center on own player initially
        if (type === 'own' && this.state.markers.size === 1) {
            this.state.map.setView([lat, lng], 16);
        }
    }

    createMarkerIcon(type, isOwn = false) {
        const colors = {
            'own': '#4CAF50',
            'hunter': '#FF5722', 
            'hunted': '#2196F3'
        };

        const size = isOwn ? 24 : 20;
        const borderWidth = isOwn ? 4 : 2;

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
        "></div>`,
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
            }
        } catch (error) {
            console.warn('Failed to poll game state:', error);
        }
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
            // Stop location tracking
            this.stopLocationTracking();

            // Clear all intervals
            this.state.intervals.forEach((interval, key) => {
                clearInterval(interval);
            });
            this.state.intervals.clear();

            // Clear map and markers
            if (this.state.map) {
                this.state.map.remove();
                this.state.map = null;
            }
            this.state.markers.clear();

            // Clear session
            this.clearSession();

            // Return to join screen
            this.showScreen('join');

            console.log('👋 Left game and cleaned up resources');
        }
    }
}

// Initialize when DOM is ready
document.addEventListener('DOMContentLoaded', () => {
    window.snaphuntGame = new SnaphuntGame();
    console.log('✅ Snaphunt Game initialized');
});
