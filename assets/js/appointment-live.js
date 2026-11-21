(function () {
    const page = document.querySelector('[data-ss-appointment-version]');
    if (!page) return;
    const version = page.dataset.ssAppointmentVersion;
    let checking = false, reloading = false;

    async function refresh() {
        if (document.hidden || checking || reloading) return;
        checking = true;
        try {
            const url = new URL(window.location.href);
            url.searchParams.set('appointment_status', '1');
            const response = await fetch(url, {cache: 'no-store', credentials: 'same-origin'});
            if (!response.ok || response.redirected) return;
            const next = await response.json();
            if (typeof next.version === 'string' && next.version !== version) {
                reloading = true;
                window.location.reload();
            }
        } catch (error) {
            // Leave the current ticket visible during temporary connection failures.
        } finally {
            checking = false;
        }
    }

    setInterval(refresh, 5000);
    window.addEventListener('pageshow', refresh);
    window.addEventListener('focus', refresh);
    document.addEventListener('visibilitychange', refresh);
}());
