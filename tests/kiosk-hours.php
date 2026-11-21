<?php
if (PHP_SAPI !== 'cli') { http_response_code(403); exit('CLI only.'); }
require_once __DIR__ . '/../global/kiosk-hours.php';
$config = require __DIR__ . '/../global/kiosk-config.php';
$normalConfig = $config;
$normalConfig['opening_time'] = '07:00';
$normalConfig['closing_time'] = '17:00';
$normalConfig['allow_after_hours_testing'] = false;

// Run the exact production hours gate in a separate process; it exits on closed
// requests before the application model or any database connection is created.
if (($argv[1] ?? '') === '--gate') {
    $_POST = ['action'=>$argv[2]];
    $_GET = ['allow_after_hours_testing'=>'true'];
    $normalConfig['allow_after_hours_testing'] = ($argv[3] ?? '') === 'testing';
    $kioskHours = smartserve_kiosk_hours($normalConfig, new DateTimeImmutable('2026-11-11 23:00:00', new DateTimeZone('Asia/Manila')));
    $is_business_hours = $kioskHours['is_open'];
    $source = file_get_contents(__DIR__ . '/../kiosk.php');
    $start = strpos($source, '// Enforce the same schedule');
    $end = strpos($source, "include('global/model.php');", $start);
    if ($start === false || $end === false) throw new RuntimeException('Production hours gate not found.');
    eval(substr($source, $start, $end - $start));
    echo 'CONTINUE';
    exit;
}

$checks = 0;
function kioskCheck($ok, $label) { global $checks; $checks++; if (!$ok) throw new RuntimeException($label); }
$hours = static fn($time, $settings = null) => smartserve_kiosk_hours($settings ?? $normalConfig, new DateTimeImmutable('2026-11-11 ' . $time, new DateTimeZone('Asia/Manila')));
foreach (['00:00:00'=>false, '06:59:59'=>false, '07:00:00'=>true, '08:00:00'=>true, '16:59:59'=>true, '17:00:00'=>false, '23:59:59'=>false] as $time=>$expected) {
    kioskCheck($hours($time)['is_open'] === $expected, 'Correct opening/closing boundary: ' . $time);
}
kioskCheck($hours('12:00:00')['hours_label'] === '7:00 AM to 5:00 PM', 'Closed-page text uses the evaluated configuration');
$eight = $normalConfig; $eight['opening_time'] = '08:00';
kioskCheck(!$hours('07:59:59', $eight)['is_open'] && $hours('08:00:00', $eight)['is_open'], 'Changing one config opening time supports 8 AM');
$testing = $normalConfig; $testing['allow_after_hours_testing'] = true;
kioskCheck($hours('23:00:00', $testing)['is_open'] && !$hours('23:00:00', $testing)['normal_hours'], 'Testing opens the kiosk after hours');
kioskCheck($hours('23:00:00', $testing)['testing_enabled'], 'Testing state is available for the visible banner');
$utc = smartserve_kiosk_hours($normalConfig, new DateTimeImmutable('2026-11-10 23:00:00', new DateTimeZone('UTC')));
kioskCheck($utc['is_open'], 'Opening is evaluated in Philippine time regardless of input timezone');
foreach ([['opening_time'=>'25:00'], ['closing_time'=>'06:00'], ['closing_time'=>'07:00'], ['allow_after_hours_testing'=>'false']] as $invalid) {
    $rejected = false;
    try { $hours('12:00:00', array_replace($normalConfig, $invalid)); } catch (InvalidArgumentException $e) { $rejected = true; }
    kioskCheck($rejected, 'Invalid configuration is rejected instead of opening the kiosk');
}
foreach (['verify_qr', 'submit_walkin', 'check_purpose_block'] as $action) {
    foreach (['normal', 'testing'] as $mode) {
        $pipes = [];
        $process = proc_open([PHP_BINARY,__FILE__,'--gate',$action,$mode],[0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],$pipes);
        if (!is_resource($process)) throw new RuntimeException('Cannot start gate test worker.');
        fclose($pipes[0]); $output=stream_get_contents($pipes[1]); $error=stream_get_contents($pipes[2]);
        fclose($pipes[1]); fclose($pipes[2]);
        kioskCheck(proc_close($process) === 0 && $error === '', 'Gate worker runs cleanly for ' . $action . ' ' . $mode);
        if ($mode === 'normal') {
            $response = json_decode($output, true, 512, JSON_THROW_ON_ERROR);
            kioskCheck($response['status'] === 'error' && $response['blocked'] && str_contains($response['msg'],'7:00 AM to 5:00 PM'), 'Closed request is blocked before database access, despite a query-string override: ' . $action);
        } else kioskCheck($output === 'CONTINUE', 'Configured testing permits the ordinary action checks: ' . $action);
    }
}
echo "Passed $checks kiosk-hours checks. No database writes or messages.\n";
