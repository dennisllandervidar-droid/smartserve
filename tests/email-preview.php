<?php
if (PHP_SAPI !== 'cli') { http_response_code(403); exit('CLI only.'); }
require_once __DIR__ . '/../global/email-template.php';
$logo = 'data:image/png;base64,' . base64_encode(file_get_contents(__DIR__ . '/../assets/img/icon.png'));
$appointment = [
    'title' => 'Your appointment is confirmed',
    'name' => 'Juan Dela Cruz',
    'intro' => 'Your appointment has been booked successfully. Please review your schedule below.',
    'highlight_label' => 'Appointment tracking number',
    'highlight' => 'ONL-20261013-A7C42',
    'details' => ['Date' => 'October 13, 2026', 'Time' => '09:00 AM', 'Office' => 'Registrar', 'Purpose of visit' => 'Request for Transcript of Records'],
    'paragraphs' => ['Please arrive on time and scan your appointment QR code at the campus kiosk to receive your queue number. Sign in to view your QR code and appointment details.'],
    'action_label' => 'View your appointment',
    'action_url' => SmartServeEmailTemplate::APP_URL . '/student/booking-details?id=ONL-20261013-A7C42',
];
$otp = [
    'title' => 'Reset your SmartServe password',
    'name' => 'Juan Dela Cruz',
    'intro' => 'We received a request to reset the password for your SmartServe account.',
    'highlight_label' => 'Password reset verification code',
    'highlight' => '482619',
    'paragraphs' => ['This code expires in 10 minutes. Enter it on the password recovery page. Never share this code with anyone.', 'If you did not request a password reset, you can safely ignore this email.'],
];
file_put_contents(__DIR__ . '/fixtures/email-preview.html', SmartServeEmailTemplate::render($appointment, $logo));
file_put_contents(__DIR__ . '/fixtures/email-otp-preview.html', SmartServeEmailTemplate::render($otp, $logo));
echo "Generated booking and password recovery email previews.\n";
