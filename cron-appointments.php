<?php
// Run once per minute when the email/SMS worker is not already scheduled.
if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('CLI only.');
}
require_once __DIR__ . '/global/model.php';
$model = new Model();
echo json_encode(['cancelled' => $model->autoCancelExpiredAppointments()]) . PHP_EOL;
