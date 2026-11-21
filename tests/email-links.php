<?php
if (PHP_SAPI !== 'cli') { http_response_code(403); exit('CLI only.'); }
putenv('SMARTSERVE_MAIL_AUTOSEND=0');
putenv('SMARTSERVE_APP_URL=' . ($argv[1] ?? 'http://localhost/smartserve/'));
require_once __DIR__ . '/../global/email.php';
$checks = 0;
function linkCheck($ok, $label) {
    global $checks;
    $checks++;
    if (!$ok) throw new RuntimeException($label);
}
$base = 'https://dlsua-smartserve.site';
linkCheck(smartserve_mail_config()['app_url'] === $base, 'Legacy or missing configuration uses the live domain');
$route = '/student/booking-details?id=ONL-20261021-ABC12&view=details#ticket';
foreach (['http://localhost/smartserve', 'http://localhost:8080/smartserve', 'http://127.0.0.1:8087/smartserve', 'http://[::1]/smartserve', 'http://localhost', 'http://dlsua-smartserve.site', 'https://www.dlsua-smartserve.site', $base] as $origin) {
    $message = ['title' => 'Appointment update', 'intro' => 'Please review your appointment.', 'action_label' => 'View your appointment', 'action_url' => $origin . $route];
    $html = SmartServeEmailTemplate::render($message);
    $text = SmartServeEmailTemplate::text($message);
    linkCheck(str_contains($html, 'href="' . htmlspecialchars($base . $route, ENT_QUOTES) . '"'), 'Button preserves route, tracking number, query and fragment');
    linkCheck(substr_count($html, '<a ') === 1 && str_contains($html, '>View your appointment</a>'), 'Only the named button is linked');
    linkCheck(!str_contains(strip_tags($html), 'http') && !str_contains($html, 'localhost'), 'HTML has no visible address or localhost');
    linkCheck(!str_contains($text, 'http') && !str_contains($text, 'Or open:'), 'Plain text does not display the action URL');
}
foreach (['javascript:alert(1)', 'data:text/html,test', '/login', '', null] as $invalid) {
    linkCheck(SmartServeEmailTemplate::actionUrl($invalid) === null, 'Unsafe or incomplete URL rejected');
}
$oldUrl = 'http://localhost/smartserve' . $route;
$oldHtml = '<p>Your appointment details are ready.</p><table><tr><td><a href="' . htmlspecialchars($oldUrl, ENT_QUOTES) . '">View your appointment</a></td></tr></table><p style="color:#555">Or open: <a href="' . htmlspecialchars($oldUrl, ENT_QUOTES) . '">' . htmlspecialchars($oldUrl, ENT_QUOTES) . '</a></p>';
$oldText = "Your appointment details are ready.\n\nView your appointment: $oldUrl\n\nSmartServe";
[$html, $text] = SmartServeEmailTemplate::deliveryBodies($oldHtml, $oldText);
linkCheck(substr_count($html, '<a ') === 1 && str_contains($html, 'href="' . htmlspecialchars($base . $route, ENT_QUOTES) . '"'), 'Queued email keeps its button on the live domain');
linkCheck(!str_contains($html, 'Or open:') && !str_contains($html, 'localhost'), 'Queued fallback paragraph removed');
linkCheck(!str_contains($text, 'http') && str_contains($text, 'Your appointment details are ready.'), 'Queued plain text keeps content without its raw URL');
linkCheck(SmartServeEmailTemplate::deliveryBodies($html, $text) === [$html, $text], 'Repeated delivery preparation is stable');
linkCheck(!str_contains(SmartServeEmailTemplate::render(['title' => 'Reset password', 'highlight' => '123456']), '<a '), 'OTP email does not invent an action link');
echo "Passed $checks email link checks. No email or SMS sent.\n";
