document.addEventListener('DOMContentLoaded', () => {
    const form = document.getElementById('create-game-form');
    const nameInput = document.getElementById('game-name');
    const intervalInput = document.getElementById('photo-interval');
    const intervalDisplay = document.getElementById('interval-display');
    const submitBtn = document.getElementById('submit-btn');
    const message = document.getElementById('message');
    const result = document.getElementById('result');
    const joinCodeSpan = document.getElementById('join-code');
    const copyBtn = document.getElementById('copy-code');

    intervalInput.addEventListener('input', () => {
        intervalDisplay.textContent = intervalInput.value;
    });

    form.addEventListener('submit', async (e) => {
        e.preventDefault();
        message.textContent = '';
        const name = nameInput.value.trim();
        if (!name) {
            message.textContent = 'Please enter a game name.';
            return;
        }
        submitBtn.disabled = true;
        submitBtn.textContent = 'Creating...';
        try {
            const response = await fetch('api/game.php?action=create', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    name,
                    photo_interval_seconds: parseInt(intervalInput.value, 10)
                })
            });
            const data = await response.json();
            if (response.ok && data.join_code) {
                joinCodeSpan.textContent = data.join_code;
                result.classList.remove('hidden');
                message.textContent = 'Game created!';
            } else {
                message.textContent = data.error || 'Error creating game.';
            }
        } catch (err) {
            message.textContent = 'Network error.';
        } finally {
            submitBtn.disabled = false;
            submitBtn.textContent = 'Create Game';
        }
    });

    copyBtn.addEventListener('click', async () => {
        try {
            await navigator.clipboard.writeText(joinCodeSpan.textContent);
            message.textContent = 'Join code copied to clipboard.';
        } catch (err) {
            message.textContent = 'Unable to copy join code.';
        }
    });
});
