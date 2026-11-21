<?php
// Copy to email.local.php, or use the environment variables documented in docs/email-notifications.md.
return [
    'host' => 'smtp.gmail.com',
    'port' => 587,
    'encryption' => 'tls',
    'username' => 'your-sender@example.com',
    'password' => 'your-smtp-app-password',
    'from' => 'your-sender@example.com',
    'app_url' => 'https://dlsua-smartserve.site',
    'autosend' => '1',
];
