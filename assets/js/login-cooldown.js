(function () {
    const form = document.getElementById('login-form');
    if (!form) return;
    const button = document.getElementById('login-button');
    const label = document.getElementById('login-button-label');
    const status = document.getElementById('login-attempt-status');
    const message = document.getElementById('login-attempt-message');
    let state = JSON.parse(form.dataset.loginState);
    let receivedAt = performance.now(), checking = false, retryAt = 0;

    function setText(element, text) {
        if (element.textContent !== text) element.textContent = text;
    }

    function render() {
        button.disabled = state.locked;
        status.hidden = !state.attempts;
        if (!state.locked) {
            setText(label, 'Login Account');
            setText(message, state.attempts ? state.remaining + ' attempt' + (state.remaining === 1 ? '' : 's') + ' remaining' : '');
            return;
        }
        const seconds = Math.max(0, Math.ceil(state.unlock_at - state.server_now - (performance.now() - receivedAt) / 1000));
        setText(label, seconds ? 'Try again in ' + String(Math.floor(seconds / 60)).padStart(2, '0') + ':' + String(seconds % 60).padStart(2, '0') : 'Checking cooldown…');
        setText(message, 'All 5 attempts have been used. Please wait before trying again.');
        // The server must confirm expiry before the button is enabled.
        if (!seconds && performance.now() >= retryAt) refresh();
    }

    async function refresh() {
        if (checking) return;
        checking = true;
        try {
            const url = new URL(window.location.href);
            url.searchParams.set('login_status', '1');
            const response = await fetch(url, { cache: 'no-store', credentials: 'same-origin' });
            if (!response.ok) throw new Error('Unable to check cooldown.');
            const next = await response.json();
            if (typeof next.locked !== 'boolean' || !Number.isFinite(next.server_now) || !Number.isFinite(next.unlock_at)) throw new Error('Invalid cooldown status.');
            state = next;
            receivedAt = performance.now();
            retryAt = receivedAt + 1000;
        } catch (error) {
            retryAt = performance.now() + 2000;
        } finally {
            checking = false;
            render();
        }
    }

    form.addEventListener('submit', event => {
        if (state.locked) { event.preventDefault(); render(); }
    }, true);
    setInterval(render, 250);
    setInterval(refresh, 30000);
    window.addEventListener('pageshow', refresh);
    window.addEventListener('focus', refresh);
    document.addEventListener('visibilitychange', () => { if (!document.hidden) refresh(); });
    render();
}());
