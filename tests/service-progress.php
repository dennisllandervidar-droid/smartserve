<?php
if (PHP_SAPI !== 'cli') { http_response_code(403); exit('CLI only.'); }
require_once __DIR__ . '/../student/partials/service-progress.php';
$checks = 0;
function serviceViewCheck($ok, $label) { global $checks; $checks++; if (!$ok) throw new RuntimeException($label); }
function serviceViewBooking(array $statuses): array {
    $names = ['Cashier', 'Clinic', 'IT Services'];
    $services = [];
    foreach ($statuses as $i => $status) $services[] = ['office_id'=>$i+1, 'office_name'=>$names[$i], 'status'=>$status];
    return ['status'=>0, 'tracking_no'=>'SYNTHETIC-ORIGINAL', 'services'=>$services];
}
$cases = [
    [[0,0], 'pending',0], [[3,0], 'progress',1], [[3,1,0], 'progress',1], [[3,2], 'progress',1],
    [[3,3], 'completed',2], [[3,4], 'partial',1], [[4,4], 'cancelled',0], [[0,4], 'pending',0],
];
foreach ($cases as [$statuses,$bucket,$completed]) {
    $summary = smartserve_student_service_summary(serviceViewBooking($statuses));
    serviceViewCheck($summary['bucket'] === $bucket, 'Correct aggregate bucket for ' . implode(',', $statuses));
    serviceViewCheck($summary['completed'] === $completed && $summary['total'] === count($statuses), 'Accurate completed service count');
}
$blocked = serviceViewBooking([3,0,1]);
$blocked['services'][0]['time_completed'] = '2026-11-10 09:03:00';
$blocked['services'][1] += ['blocked_notice_id'=>42, 'block_reason'=>'Clinic unavailable today.'];
$blocked['services'][2]['queue_number'] = 'IT-003';
serviceViewCheck(smartserve_service_state($blocked['services'][1])[0] === 'blocked', 'Pending blocked office has an explicit action state');
serviceViewCheck(smartserve_student_service_summary($blocked)['bucket'] === 'progress', 'Completed plus blocked plus queued stays In Progress');
$rescheduled = serviceViewBooking([3,4]);
$rescheduled['services'][1] += ['rescheduled_tracking_no'=>'ONL-NEW-TICKET','rescheduled_date'=>'2026-11-11','rescheduled_time'=>'10:00 AM'];
serviceViewCheck(smartserve_student_service_summary($rescheduled)['bucket'] === 'partial', 'Completed plus rescheduled is Partially completed');
serviceViewCheck(smartserve_service_state($rescheduled['services'][1])[1] === 'Rescheduled', 'Superseded visit is not labeled cancelled');
$singleRescheduled = ['services'=>[$rescheduled['services'][1]]];
serviceViewCheck(smartserve_student_service_summary($singleRescheduled)['bucket'] === 'rescheduled', 'Fully rescheduled ticket belongs to its own filter');
$capture = static function($booking,$compact=false) { ob_start(); smartserve_render_service_progress($booking,$compact); return ob_get_clean(); };
$html = $capture($blocked);
serviceViewCheck(str_contains($html,'1 of 3 services completed') && str_contains($html,'2 still open'), 'Details explain exactly how many services remain');
serviceViewCheck(str_contains($html,'booking#blocked-appointment-42') && str_contains($html,'Clinic unavailable today.'), 'Blocked service links to the correct response form');
serviceViewCheck(str_contains($html,'IT-003') && str_contains($html,'Completed Nov 10, 2026'), 'Queue and completed time are shown per service');
$html = $capture($rescheduled);
serviceViewCheck(str_contains($html,'booking-details?id=ONL-NEW-TICKET') && str_contains($html,'Nov 11, 2026'), 'Rescheduled service links to its new ticket and schedule');
$unsafe = serviceViewBooking([4]);
$unsafe['services'][0] += ['office_notes'=>'<script>bad()</script>','rescheduled_tracking_no'=>'x" onclick="bad()'];
$unsafe['services'][0]['office_name'] = '<img src=x onerror=bad()>';
$html = $capture($unsafe);
serviceViewCheck(!str_contains($html,'<img') && !str_contains($html,'onclick="bad()'), 'Service names and replacement tracking parameters cannot inject markup');
unset($unsafe['services'][0]['rescheduled_tracking_no']);
serviceViewCheck(str_contains($capture($unsafe),'&lt;script&gt;bad()&lt;/script&gt;'), 'Office notes are escaped');
serviceViewCheck(!str_contains($capture($blocked,true),'Clinic unavailable today.') && str_contains($capture($blocked,true),'Schedule blocked'), 'Compact history retains each status without full details');

$sections = '';
foreach (['Active booking'=>$blocked, 'Completed and rescheduled'=>$rescheduled, 'All services completed'=>serviceViewBooking([3,3,3])] as $title=>$booking) {
    $summary = smartserve_student_service_summary($booking);
    $sections .= '<section><h2>' . $title . '</h2><p><span class="ss-ticket-status ss-ticket-' . $summary['bucket'] . '">' . $summary['label'] . '</span></p>' . $capture($booking) . '</section>';
}
$preview = '<!doctype html><html lang="en"><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Service progress verification</title><link rel="stylesheet" href="../../assets/css/appointment-services.css"><style>body{font-family:Arial,sans-serif;background:#f4f7f6;color:#263c32;margin:0;padding:30px}main{max-width:850px;margin:auto}section{padding:25px;margin:20px 0;background:white;border-radius:16px}h1{font-size:26px}h2{font-size:20px}</style><main><h1>Service progress verification</h1><p>Synthetic preview using the production service cards.</p>' . $sections . '</main></html>';
file_put_contents(__DIR__ . '/fixtures/service-progress-preview.html', $preview);
echo "Passed $checks service presentation checks. Synthetic preview generated; no database writes.\n";
