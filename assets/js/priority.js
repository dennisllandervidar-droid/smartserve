(function () {
    function init() {
        const type = document.getElementById('account_priority');
        const wrap = document.getElementById('pwd-id-wrap');
        const file = document.getElementById('pwd_id');
        if (!type || !wrap || !file) return;
        function toggle() {
            const pwd = type.value === 'PWD';
            wrap.hidden = !pwd;
            if (pwd) wrap.style.removeProperty('display');
            else wrap.style.setProperty('display', 'none', 'important');
            file.disabled = !pwd;
            file.required = pwd && !file.dataset.hasId;
            if (!pwd) {
                file.value = '';
                file.setCustomValidity('');
                file.classList.remove('ss-invalid', 'is-invalid');
                file.setAttribute('aria-invalid', 'false');
                const error = document.getElementById(file.dataset.ssErrorId);
                if (error) { error.textContent = ''; error.hidden = true; }
            }
        }
        type.addEventListener('change', toggle);
        if (window.jQuery) {
            window.jQuery(type).on('change changed.bs.select loaded.bs.select refreshed.bs.select', toggle);
        }
        window.addEventListener('pageshow', toggle);
        toggle();
    }
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init); else init();
}());
