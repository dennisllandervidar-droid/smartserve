<?php
// Run once per minute using PHP CLI. Never expose a mail-draining web endpoint.
if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('This worker is available from the command line only.');
}
require_once __DIR__ . '/global/model.php';
$model = new Model();
$result = $model->runEmailNotifications();
echo 'Email worker: ' . json_encode($result) . PHP_EOL;
