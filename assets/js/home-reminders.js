(function () {
    function start() {
        const element = document.getElementById('importantRemindersModal');
        if (!element) return;
        const key = 'smartserve.hideHomepageReminders.v1';
        let hidden = false;
        try { hidden = localStorage.getItem(key) === '1'; } catch (error) { /* Storage may be disabled. */ }
        document.getElementById('hide-homepage-reminders')?.addEventListener('click', () => {
            try { localStorage.setItem(key, '1'); } catch (error) { /* The current dialog still closes. */ }
        });
        if (!hidden) new bootstrap.Modal(element).show();
    }
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', start, { once: true });
    else start();
}());
