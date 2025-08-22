console.log('🚀 app.js executing!');

// Auto-switch to join screen after 1 second
setTimeout(() => {
    console.log('⏱ Switching to join screen...');

    const loading = document.getElementById('loading-screen');
    const join = document.getElementById('join-screen');

    if (loading && join) {
        loading.classList.remove('active');
        join.classList.remove('hidden');
        join.classList.add('active');
        console.log('✅ Auto-switch completed!');

        // Setup join functionality
        setupJoinScreen();
    }
}, 1000);

function setupJoinScreen() {
    console.log('🔧 Setting up join screen...');

    const joinButton = document.getElementById('join-btn');
    const joinCodeInput = document.getElementById('join-code');
    const playerNameInput = document.getElementById('player-name');

    if (joinButton && joinCodeInput && playerNameInput) {
        joinButton.addEventListener('click', handleJoin);

        // Auto-uppercase join code
        joinCodeInput.addEventListener('input', (e) => {
            e.target.value = e.target.value.toUpperCase();
        });

        console.log('✅ Join screen setup complete!');
    } else {
        console.log('❌ Join elements not found!');
    }
}

async function handleJoin() {
    console.log('🎮 Join button clicked!');

    const joinCode = document.getElementById('join-code').value.trim();
    const playerName = document.getElementById('player-name').value.trim();

    if (!joinCode || !playerName) {
        alert('Please enter both join code and name!');
        return;
    }

    try {
        const response = await fetch(`api/game.php?action=get&code=${joinCode}`);
        const data = await response.json();

        if (response.ok) {
            alert(`Found game: ${data.name}! Team selection coming soon.`);
        } else {
            alert(`Error: ${data.error}`);
        }
    } catch (error) {
        alert('Network error!');
        console.error(error);
    }
}

