<?php
require_once __DIR__ . '/validation.php';

function smartserve_sms_config() {
    $local = is_file(__DIR__ . '/sms.local.php') ? require __DIR__ . '/sms.local.php' : [];
    return [
        'api_key' => getenv('SMARTSERVE_SMS_API_KEY') ?: ($local['api_key'] ?? ''),
        'device_id' => getenv('SMARTSERVE_SMS_DEVICE_ID') ?: ($local['device_id'] ?? ''),
    ];
}

function normalizePhilippineMobileNumber($number) {
    if (!SmartServeValidation::phone($number)) return '';
    return str_starts_with($number, '09') ? '+63' . substr($number, 1) : $number;
}

/** True means the gateway accepted the message; handset delivery happens afterward. */
function sendTextbeeSMS($recipient, $message) {
    $recipient = normalizePhilippineMobileNumber($recipient);
    $config = smartserve_sms_config();
    if (!$recipient || !$config['api_key'] || !preg_match('/\A[a-f0-9]{24}\z/i', $config['device_id'])) return false;
    $ch = curl_init('https://api.textbee.dev/api/v1/gateway/send-sms');
    curl_setopt_array($ch, [CURLOPT_POST => true, CURLOPT_POSTFIELDS => json_encode(['deviceId'=>$config['device_id'], 'recipients'=>[$recipient], 'message'=>$message]), CURLOPT_HTTPHEADER=>['x-api-key: '.$config['api_key'],'Content-Type: application/json'], CURLOPT_RETURNTRANSFER=>true, CURLOPT_SSL_VERIFYPEER=>true, CURLOPT_CONNECTTIMEOUT=>5, CURLOPT_TIMEOUT=>15]);
    $raw = curl_exec($ch); $status = curl_getinfo($ch, CURLINFO_HTTP_CODE); curl_close($ch);
    $data = is_string($raw) ? json_decode($raw, true) : null;
    $accepted = $raw !== false && $status >= 200 && $status < 300 && is_array($data) && ($data['data']['success'] ?? false) === true && !empty($data['data']['smsBatchId']);
    if (!$accepted) error_log('SmartServe: SMS gateway rejected the request (HTTP '.(int)$status.').');
    return $accepted;
}
