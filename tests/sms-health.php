<?php
if (PHP_SAPI !== 'cli') { http_response_code(403); exit('CLI only.'); }
require_once __DIR__ . '/../global/sms.php';
$config=smartserve_sms_config();
if (!$config['api_key'] || !$config['device_id']) { echo "SMS configuration missing. No SMS sent.\n"; exit(1); }
$ch=curl_init('https://api.textbee.dev/api/v1/gateway/devices/'.$config['device_id']);
curl_setopt_array($ch,[CURLOPT_HTTPHEADER=>['x-api-key: '.$config['api_key']],CURLOPT_RETURNTRANSFER=>true,CURLOPT_SSL_VERIFYPEER=>true,CURLOPT_CONNECTTIMEOUT=>5,CURLOPT_TIMEOUT=>15]);
$raw=curl_exec($ch); $http=curl_getinfo($ch,CURLINFO_HTTP_CODE); $error=curl_errno($ch); curl_close($ch);
$data=is_string($raw)?json_decode($raw,true):null;
$device=$data['data']??null;
echo json_encode(['http_status'=>$http,'connection_error'=>$error,'credentials_valid'=>$http===200,'device_enabled'=>$http===200?($device['enabled']??null):null,'last_heartbeat'=>$http===200?($device['lastHeartbeat']??$device['lastHeartbeatAt']??null):null,'sms_sent'=>false]) . PHP_EOL;
