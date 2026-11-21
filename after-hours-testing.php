<?php
session_start();
require_once __DIR__ . '/global/kiosk-settings.php';

header('Cache-Control: no-store, max-age=0');
header('X-Frame-Options: DENY');
header("Content-Security-Policy: frame-ancestors 'none'; form-action 'self'; base-uri 'self'");
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
if (!in_array($method, ['GET', 'HEAD', 'POST'], true)) {
    header('Allow: GET, HEAD, POST');
    http_response_code(405);
    exit('Method not allowed.');
}

// This page is intentionally public. The token ties a save to this page visit.
if (empty($_SESSION['kiosk_testing_csrf'])) {
    $_SESSION['kiosk_testing_csrf'] = bin2hex(random_bytes(32));
}
$error = '';
$notice = $_SESSION['kiosk_testing_notice'] ?? '';
unset($_SESSION['kiosk_testing_notice']);
if ($method === 'POST') {
    $token = $_POST['csrf_token'] ?? '';
    $value = $_POST['allow_after_hours_testing'] ?? null;
    if (!is_string($token) || !hash_equals($_SESSION['kiosk_testing_csrf'], $token)) {
        http_response_code(403);
        $error = 'This page has expired. Please try the switch again.';
    } elseif (!is_string($value) || !in_array($value, ['0', '1'], true)) {
        http_response_code(422);
        $error = 'Choose On or Off using the switch below.';
    } else {
        try {
            $enabled = $value === '1';
            $changed = smartserve_save_kiosk_testing($enabled);
            $_SESSION['kiosk_testing_notice'] = $changed
                ? 'After-hours testing is now ' . ($enabled ? 'on' : 'off') . '. Refresh your kiosk tab to apply the change.'
                : 'No changes needed. After-hours testing is already ' . ($enabled ? 'on' : 'off') . '.';
            header('Location: after-hours-testing.php', true, 303);
            exit;
        } catch (Throwable $e) {
            error_log('SmartServe kiosk testing save failed: ' . $e->getMessage());
            http_response_code(500);
            $error = 'The setting could not be saved. Check that the server can write to global/kiosk-config.php and its directory, then try again.';
        }
    }
}

$hours = null;
try {
    $hours = smartserve_kiosk_hours(smartserve_load_kiosk_config());
} catch (Throwable $e) {
    error_log('SmartServe kiosk testing configuration failed: ' . $e->getMessage());
    http_response_code(500);
    $error = 'The kiosk settings could not be loaded. Check global/kiosk-config.php before trying again.';
}

// Match homepage branding, while keeping the setting usable without a database.
$web_name = 'De La Salle Araneta University';
// Model's legacy global POST validator must not re-handle this page's errors.
if ($method !== 'POST') {
    ob_start();
    try {
        require_once __DIR__ . '/global/model.php';
        $model = new Model();
        $rows = $model->website_details();
        foreach ($rows ?: [] as $row) {
            $web_name = $row['web_name'];
        }
    } catch (Throwable $e) {
        error_log('SmartServe kiosk testing branding unavailable: ' . get_class($e));
    } finally {
        ob_end_clean();
    }
}
$escape = static fn($value) => htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
$enabled = $hours !== null && $hours['testing_enabled'];
$clock = new DateTimeImmutable('now', new DateTimeZone($hours['timezone'] ?? 'Asia/Manila'));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>After-hours Testing | <?= $escape($web_name) ?></title>
    <link href="assets/img/icon.png" rel="icon">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
    <style>
        :root { color-scheme: dark; --green: #005141; --deep-green: #002d24; --white: #fff; }
        * { box-sizing: border-box; }
        body { margin: 0; min-height: 100vh; display: flex; flex-direction: column; font-family: 'Inter', sans-serif; color: #f8f9fa; background-color: var(--green); background-image: radial-gradient(circle at 0% 0%, rgba(20,164,77,.15), transparent 50%), radial-gradient(circle at 100% 100%, rgba(0,45,36,.8), transparent 50%), radial-gradient(circle at 50% 50%, rgba(255,255,255,.03), transparent 60%); background-attachment: fixed; }
        a { color: inherit; }
        button, a { -webkit-tap-highlight-color: transparent; }
        a:focus-visible, button:focus-visible { outline: 3px solid #b9f5d5; outline-offset: 6px; }
        .container { width: min(1140px, calc(100% - 40px)); margin-inline: auto; }
        .navbar-custom { background: rgba(0,61,49,.4); backdrop-filter: blur(12px); border-bottom: 1px solid rgba(255,255,255,.08); padding: 16px 0; }
        .navbar-inner, .brand, .nav-links { display: flex; align-items: center; }
        .navbar-inner { justify-content: space-between; gap: 24px; }
        .brand { text-decoration: none; }
        .brand img { width: 50px; height: 50px; object-fit: contain; filter: brightness(0) invert(1); }
        .brand-text { border-left: 2px solid rgba(255,255,255,.5); margin-left: 15px; padding-left: 15px; }
        .brand-text small { display: block; color: rgba(255,255,255,.65); font-size: 9px; font-weight: 700; letter-spacing: 1px; margin-bottom: 4px; }
        .brand-text strong { font-size: 1.1rem; font-weight: 800; letter-spacing: .5px; }
        .nav-links { gap: 20px; font-size: .9rem; font-weight: 600; }
        .nav-home { text-decoration: none; }
        .nav-home:hover, .footer-links a:hover { text-decoration: underline; }
        .btn { border: 1px solid rgba(255,255,255,.3); border-radius: 30px; padding: 11px 23px; display: inline-flex; align-items: center; justify-content: center; gap: 10px; font-size: .9rem; font-weight: 600; text-decoration: none; background: linear-gradient(135deg, rgba(255,255,255,.1), rgba(255,255,255,.02)); }
        .btn:hover { background: #fff; color: var(--green); }
        .btn-primary { background: #fff; color: var(--green); border-color: #fff; }
        .btn-primary:hover { background: #dff6e9; }
        main { flex: 1; padding: 56px 0 64px; }
        .hero { text-align: center; max-width: 700px; margin: 0 auto 30px; }
        .sub-badge { display: inline-block; padding: 8px 18px; border: 1px solid rgba(255,255,255,.15); background: linear-gradient(90deg, rgba(255,255,255,.1), rgba(255,255,255,.03)); border-radius: 50px; font-size: .75rem; font-weight: 700; letter-spacing: 1.5px; text-transform: uppercase; }
        h1 { font-size: clamp(2.1rem, 5vw, 3.3rem); font-weight: 900; letter-spacing: -1.5px; line-height: 1.15; margin: 18px 0 14px; }
        .hero p { color: rgba(255,255,255,.75); font-size: 1rem; line-height: 1.7; margin: 0; }
        .settings { max-width: 760px; margin: auto; background: rgba(0,45,36,.5); border: 1px solid rgba(255,255,255,.16); border-radius: 24px; box-shadow: 0 18px 50px rgba(0,0,0,.13); padding: 32px; }
        .setting-top { display: flex; align-items: center; justify-content: space-between; gap: 24px; }
        h2 { font-size: 1.25rem; margin: 0 0 9px; }
        .setting-description { margin: 0; color: rgba(255,255,255,.7); line-height: 1.6; font-size: .9rem; }
        .switch { border: 0; padding: 8px 0 8px 8px; color: #fff; cursor: pointer; display: flex; align-items: center; gap: 12px; background: none; font: inherit; font-weight: 700; }
        .switch:disabled { cursor: wait; opacity: .55; }
        .switch-track { width: 64px; height: 36px; border-radius: 30px; background: #687d76; border: 1px solid rgba(255,255,255,.3); position: relative; flex-shrink: 0; }
        .switch-track::after { content: ''; position: absolute; width: 26px; height: 26px; left: 4px; top: 4px; border-radius: 50%; background: #fff; box-shadow: 0 2px 6px rgba(0,0,0,.2); }
        .switch[aria-checked="true"] .switch-track { background: #32b871; border-color: #80e7b0; }
        .switch[aria-checked="true"] .switch-track::after { transform: translateX(28px); }
        .switch-label { min-width: 26px; }
        .state-summary { display: flex; align-items: flex-start; gap: 12px; padding: 18px; margin: 26px 0; border-radius: 14px; background: rgba(255,255,255,.055); border: 1px solid rgba(255,255,255,.08); }
        .status-dot { width: 9px; height: 9px; border-radius: 50%; margin-top: 6px; background: #b8c5bf; flex-shrink: 0; }
        .is-enabled .status-dot { background: #6fe6a5; box-shadow: 0 0 0 4px rgba(111,230,165,.1); }
        .state-summary strong { display: block; margin-bottom: 6px; font-size: .9rem; }
        .state-summary p { margin: 0; font-size: .85rem; line-height: 1.6; color: rgba(255,255,255,.7); }
        .hours { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin: 0; }
        .hours dt { font-size: .75rem; color: rgba(255,255,255,.6); margin-bottom: 8px; }
        .hours dd { margin: 0; font-size: .95rem; font-weight: 600; line-height: 1.6; }
        .actions { display: flex; flex-wrap: wrap; gap: 12px; padding-top: 26px; margin-top: 26px; border-top: 1px solid rgba(255,255,255,.1); }
        .help-text { margin: 18px 0 0; color: rgba(255,255,255,.6); font-size: .8rem; line-height: 1.65; }
        .alert { padding: 15px 18px; border-radius: 12px; margin: 0 0 24px; line-height: 1.6; font-size: .9rem; background: #125b42; border: 1px solid #539e73; }
        .alert-error { background: #57362d; border-color: #cd927c; }
        .main-footer { background: rgba(0,45,36,.7); border-top: 1px solid rgba(255,255,255,.05); padding-top: 32px; }
        .footer-grid { display: grid; grid-template-columns: 2fr 1fr 1fr; gap: 28px; }
        .footer-brand { font-weight: 700; font-size: 1rem; }
        .footer-copy, .footer-links { color: rgba(255,255,255,.65); font-size: .85rem; line-height: 1.8; }
        .footer-copy { max-width: 400px; }
        .footer-heading { font-size: .75rem; font-weight: 700; letter-spacing: 1px; text-transform: uppercase; margin: 0 0 12px; }
        .footer-links { list-style: none; margin: 0; padding: 0; }
        .footer-links a { text-decoration: none; }
        .footer-bottom { background: rgba(0,0,0,.25); text-align: center; padding: 18px 20px; font-size: .75rem; color: rgba(255,255,255,.55); margin-top: 28px; }
        @media (max-width: 680px) {
            .container { width: calc(100% - 32px); }
            .navbar-inner { flex-wrap: wrap; gap: 16px; }
            .brand-text strong { font-size: .86rem; }
            .brand img { width: 40px; height: 40px; }
            .brand-text { padding-left: 12px; margin-left: 12px; }
            .nav-links { width: 100%; justify-content: flex-end; }
            .nav-links .btn { padding: 8px 20px; }
            main { padding: 34px 0 40px; }
            .settings { padding: 24px 20px; }
            .setting-top { gap: 16px; align-items: flex-start; flex-wrap: wrap; }
            .hours { grid-template-columns: 1fr; gap: 18px; }
            .footer-grid { grid-template-columns: 1fr 1fr; }
            .footer-intro { grid-column: 1 / -1; }
        }
    </style>
</head>
<body>
    <nav class="navbar-custom" aria-label="Main navigation">
        <div class="container navbar-inner">
            <a class="brand" href="index.php" aria-label="SmartServe homepage">
                <img src="assets/img/icon.png" alt="">
                <span class="brand-text"><small>SMARTSERVE</small><strong><?= $escape($web_name) ?></strong></span>
            </a>
            <div class="nav-links"><a class="nav-home" href="index.php">Home</a><a class="btn" href="login">Login <span aria-hidden="true">↗</span></a></div>
        </div>
    </nav>
    <main class="container">
        <header class="hero">
            <span class="sub-badge">Kiosk settings</span>
            <h1>After-hours testing</h1>
            <p>Control kiosk access outside normal office hours.</p>
        </header>
        <section class="settings" aria-labelledby="testing-label">
            <?php if ($error !== ''): ?><p class="alert alert-error" role="alert"><?= $escape($error) ?></p><?php endif; ?>
            <?php if ($notice !== '' && $error === ''): ?><p class="alert" role="status"><?= $escape($notice) ?></p><?php endif; ?>
            <div class="setting-top">
                <div><h2 id="testing-label">Allow after-hours testing</h2><p class="setting-description" id="switch-help">Changes save immediately.</p></div>
                <form method="post" action="after-hours-testing.php" id="testing-form">
                    <input type="hidden" name="csrf_token" value="<?= $escape($_SESSION['kiosk_testing_csrf']) ?>">
                    <input type="hidden" name="allow_after_hours_testing" value="<?= $enabled ? '0' : '1' ?>">
                    <button class="switch" type="submit" role="switch" aria-checked="<?= $enabled ? 'true' : 'false' ?>" aria-labelledby="testing-label" aria-describedby="switch-help" <?= $hours === null ? 'disabled' : '' ?>>
                        <span class="switch-track" aria-hidden="true"></span><span class="switch-label"><?= $hours === null ? 'Unavailable' : ($enabled ? 'On' : 'Off') ?></span>
                    </button>
                </form>
            </div>
            <div class="state-summary <?= $enabled ? 'is-enabled' : '' ?>">
                <span class="status-dot" aria-hidden="true"></span>
                <div>
                    <strong><?= $hours === null ? 'Settings unavailable' : ($enabled ? 'Testing is enabled' : 'Normal office hours apply') ?></strong>
                    <p><?= $hours === null ? 'The switch will be available once the kiosk configuration is restored.' : ($enabled ? 'The kiosk can accept check-ins and walk-ins outside normal office hours.' : 'The kiosk opens and closes according to the schedule below.') ?></p>
                </div>
            </div>
            <dl class="hours">
                <div><dt>Normal kiosk hours</dt><dd><?= $escape($hours['hours_label'] ?? 'Unavailable') ?></dd></div>
                <div><dt>Current time · <?= $escape($hours['timezone'] ?? 'Asia/Manila') ?></dt><dd><?= $escape($clock->format('g:i A · M j, Y')) ?></dd></div>
            </dl>
            <div class="actions"><a class="btn btn-primary" href="kiosk.php" target="_blank" rel="noopener">Open kiosk <span aria-hidden="true">↗</span></a><a class="btn" href="index.php">Back to homepage</a></div>
            <p class="help-text">Refresh an open kiosk tab after changing this setting. Appointment dates, service blocks, and the 10-minute QR check-in limit still apply.</p>
        </section>
    </main>
    <footer class="main-footer">
        <div class="container footer-grid">
            <div class="footer-intro"><div class="footer-brand"><?= $escape($web_name) ?></div><p class="footer-copy">Streamlined digital solutions designed to empower and connect the Lasallian community.</p></div>
            <div><h2 class="footer-heading">Quick links</h2><ul class="footer-links"><li><a href="advisories">Advisories</a></li><li><a href="live-home">Live Queue Directory</a></li><li><a href="kiosk">Kiosk</a></li></ul></div>
            <div><h2 class="footer-heading">Connect</h2><p class="footer-copy">Malabon City, Philippines</p></div>
        </div>
        <div class="footer-bottom">&copy; <?= $clock->format('Y') ?> <?= $escape($web_name) ?>. All Rights Reserved.</div>
    </footer>
    <script>
        document.getElementById('testing-form').addEventListener('submit', function () {
            this.querySelector('button').disabled = true;
            document.getElementById('switch-help').textContent = 'Saving…';
        });
        window.addEventListener('pageshow', function (event) { if (event.persisted) window.location.reload(); });
    </script>
</body>
</html>
