<?php
if (PHP_SAPI !== 'cli') { http_response_code(403); exit('CLI only.'); }
require_once __DIR__ . '/global/model.php';
$model = new Model();
$emailResult = $model->runEmailNotifications();
$smsResult = $model->runSmsReminders();
echo json_encode(['email'=>$emailResult,'sms'=>$smsResult]) . PHP_EOL;
