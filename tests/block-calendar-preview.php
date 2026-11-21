<?php
if(PHP_SAPI!=='cli'){http_response_code(403);exit('CLI only.');}
date_default_timezone_set('Asia/Manila');
require_once __DIR__.'/../global/validation.php';
$source=file_get_contents(__DIR__.'/../office/block-schedules.php');preg_match('/<form method="POST">.*?<\/form>/s',$source,$match);
ob_start();require __DIR__.'/../office/main/block-time-fields.php';$times=ob_get_clean();
$calendarTarget='block_date';$calendarMode='block';ob_start();require __DIR__.'/../global/calendar-fields.php';$calendar=ob_get_clean();
$form=preg_replace_callback('/<\?php(.*?)\?>/s',function($m)use($times,$calendar){if(str_contains($m[1],'block-time-fields'))return $times;if(str_contains($m[1],'$calendarTarget'))return $calendar;if(str_contains($m[1],"date('Y-m-d')"))return date('Y-m-d');if(str_contains($m[1],'htmlspecialchars($purpose_option)'))return 'Medical Checkup';return '';},$match[0]);
$api= <<<'PHP'
<?php
require_once __DIR__.'/../../global/validation.php';
if($_SERVER['REQUEST_METHOD']==='POST') {header('Content-Type: application/json');$days=[];$start=$_POST['calendar_start']??'';$end=$_POST['calendar_end']??'';if(!SmartServeValidation::date($start)||!SmartServeValidation::date($end)){echo '{}';exit;}
for($day=$start;$day<=$end;$day=date('Y-m-d',strtotime($day.' +1 day'))){$slots=[];foreach(SmartServeValidation::SLOTS as $time)$slots[$time]=['available'=>$time==='09:00 AM'?0:5,'label'=>$time==='09:00 AM'?'Reserved':'5 slot(s) left'];$days[$day]=['available'=>true,'slots'=>$slots];}echo json_encode($days);exit;}
?>
PHP;
$html='<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>SmartServe office block calendar preview</title><link rel="stylesheet" href="../../dashboard/assets/css/assets.css"><link rel="stylesheet" href="../../dashboard/assets/css/style.css"><link rel="stylesheet" href="../../assets/css/forms.css"><link rel="stylesheet" href="../../assets/css/schedule-calendar.css"><script src="../../assets/js/forms.js"></script><script src="../../assets/js/schedule-calendar.js" defer></script><style>:root{--primary-green:#005141}body{background:#f5f8f6;padding:24px}.preview-wrap{max-width:750px;margin:auto;background:white;padding:32px;border-radius:18px}</style></head><body><main class="preview-wrap"><h3>Block a schedule</h3><p class="text-muted">Synthetic preview · no schedules are saved</p>'.$form.'</main><script src="../../assets/js/block-schedules.js"></script></body></html>';
file_put_contents(__DIR__.'/fixtures/block-calendar-preview.php',$api.$html);echo "Generated block calendar preview.\n";
