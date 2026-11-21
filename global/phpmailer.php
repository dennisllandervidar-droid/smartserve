<?php
// Compatibility entry point; all SMTP configuration lives in email.php.
require_once __DIR__ . '/email.php';
$mail = smartserve_mailer();
