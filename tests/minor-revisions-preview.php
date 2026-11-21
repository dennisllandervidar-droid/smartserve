<?php
// Synthetic previews using production password fields and blocked-schedule notices.
if (PHP_SAPI !== 'cli') { http_response_code(403); exit('CLI only.'); }
date_default_timezone_set('Asia/Manila');
$forgot = file_get_contents(__DIR__ . '/../forgot-password.php');
preg_match('/<\?php if \(\$step == 3\): \?>(.*?)<\?php endif; \?>/s', $forgot, $resetFields);
if (empty($resetFields[1])) throw new RuntimeException('Password reset fields not found');
$forms = '<h2>Forgot Password</h2><form>' . $resetFields[1] . '</form>';
foreach (['student', 'admin', 'office'] as $role) {
    $source = file_get_contents(__DIR__ . '/../' . $role . '/update-password.php');
    preg_match('/<form class="edit-profile" method="POST">.*?<\/form>/s', $source, $match);
    if (empty($match[0])) throw new RuntimeException('Profile password form not found');
    $forms .= '<h2>' . ucfirst($role) . ' profile · Update Password</h2>' . preg_replace('/<\?php.*?\?>/s', '', $match[0]);
}
$student_blocked_rows = [['id' => 12, 'office_name' => 'Cashier', 'original_date' => date('Y-m-d', strtotime('+1 day')), 'original_time' => '09:00 AM', 'tracking_no' => 'PREVIEW-BOOKING', 'reason' => 'Office maintenance', 'note' => 'Please choose another time. <script>alert("unsafe")</script>']];
ob_start(); require __DIR__ . '/../student/partials/blocked-appointment-notices.php'; $notice = ob_get_clean();
if (str_contains($notice, '<script>') || !str_contains($notice, '&lt;script&gt;')) throw new RuntimeException('Office notes must be escaped');
$student_blocked_rows = [];
ob_start(); require __DIR__ . '/../student/partials/blocked-appointment-notices.php'; $empty = ob_get_clean();
if (trim($empty) !== '') throw new RuntimeException('No banner expected without pending notices');
$html = '<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>SmartServe revision preview</title><link rel="stylesheet" href="../../dashboard/assets/css/assets.css"><link rel="stylesheet" href="../../dashboard/assets/css/style.css"><link rel="stylesheet" href="../../assets/css/forms.css"><script src="../../assets/js/forms.js"></script><style>body{background:#f4f8f5;padding:24px}main{max-width:850px;margin:auto;background:white;padding:24px;border-radius:16px}h2{font-size:20px;margin-top:28px}.form-control{margin-bottom:4px}form{padding:15px;border:1px solid #ddd}button[type=submit]{margin-top:12px}</style></head><body><main><h1>Revision preview</h1><p>Synthetic data · no profiles, bookings or passwords are saved.</p>' . $notice . $forms . '</main><script>document.querySelectorAll("form").forEach(form => form.addEventListener("submit", event => { event.preventDefault(); SmartServe.notify("Preview only; nothing saved.", "info"); }));</script></body></html>';
file_put_contents(__DIR__ . '/fixtures/minor-revisions-preview.html', $html);
echo "Generated password and blocked-notice previews; escaping and empty-state checks passed.\n";
