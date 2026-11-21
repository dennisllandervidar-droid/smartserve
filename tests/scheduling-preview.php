<?php
// Build a synthetic UI fixture from the actual booking form, CSS and scripts.
if (PHP_SAPI !== 'cli') { http_response_code(403); exit('CLI only.'); }
date_default_timezone_set('Asia/Manila');
$source=file_get_contents(__DIR__.'/../student/booking.php');
preg_match('/<form method="POST" id="appointmentForm">.*?<\/form>/s',$source,$match);
$form=$match[0];
$calendarTarget='appointment_date'; $calendarMode='booking';
ob_start();require __DIR__.'/../global/calendar-fields.php';$calendar=ob_get_clean();
$form=preg_replace_callback('/<\?php(.*?)\?>/s',function($m)use($calendar){
    if(str_contains($m[1],'getOffices'))return '<label class="office-card-label"><input type="checkbox" name="office_ids[]" class="office-checkbox" value="1"><div class="office-card"><i class="ti-briefcase"></i> Registrar</div></label><label class="office-card-label"><input type="checkbox" name="office_ids[]" class="office-checkbox" value="2"><div class="office-card"><i class="ti-briefcase"></i> Cashier</div></label>';
    if(str_contains($m[1],'$calendarTarget'))return $calendar;
    if(str_contains($m[1],'echo $min_date'))return date('Y-m-d');
    if(str_contains($m[1],'echo $max_date'))return date('Y-m-d',strtotime('+7 days'));
    if(str_contains($m[1],'htmlspecialchars($accountPriority)'))return 'N/A';
    return '';
},$form);
$form=preg_replace('/<small class="text-muted">Priority comes.*?<\/small>/s','<small class="text-muted">Priority comes from your verified student profile.</small>',$form);
preg_match('/<style>(.*?)<\/style>/s',$source,$style);$css=preg_replace('/<\?php.*?\?>/s','#005141',$style[1]);
preg_match('/<script>\s*\$\(document\).*?<\/script>/s',$source,$script);
$api= <<<'PHP'
<?php
date_default_timezone_set('Asia/Manila');
require_once __DIR__.'/../../global/validation.php';
if ($_SERVER['REQUEST_METHOD']==='POST') {
 header('Content-Type: application/json');
 if(isset($_POST['fetch_calendar'])) { $days=[]; for($i=0;$i<=7;$i++){$day=date('Y-m-d',strtotime("+$i days"));$weekday=SmartServeValidation::weekdayDate($day);$days[$day]=['available'=>$weekday && $i!==1,'note'=>!$weekday?'Weekdays only':($i===1?'Blocked date':'Available times')];}echo json_encode($days);exit; }
 if(isset($_POST['fetch_slots'])) { $slots=[];$weekday=SmartServeValidation::bookingDate($_POST['date'] ?? '');foreach(SmartServeValidation::SLOTS as $time)$slots[$time]=['available'=>!$weekday||in_array($time,['09:00 AM','10:00 AM'])?0:5,'label'=>!$weekday?'Weekdays only':($time==='09:00 AM'?'Blocked':($time==='10:00 AM'?'Reserved':'5 slot(s) left')),'note'=>'Synthetic test schedule'];echo json_encode($slots);exit; }
 if(isset($_POST['check_purpose_block'])){echo json_encode(['blocked'=>false]);exit;}
 if(isset($_POST['submit_appointment'])) {
  $errors=SmartServeValidation::request($_POST,'/student/booking.php');
  http_response_code($errors ? 422 : 200);
  header('Content-Type: text/html; charset=utf-8');
  echo '<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Booking preview result</title></head><body><h1>'.($errors ? 'Preview request needs correction' : 'Preview request accepted').'</h1><p>No booking is saved.</p>';
  foreach($errors as $error) echo '<p>'.htmlspecialchars($error,ENT_QUOTES).'</p>';
  foreach(['appointment_date'=>'Date','appointment_time'=>'Time','purpose_option'=>'Purpose'] as $field=>$label) {
   $value=$_POST[$field] ?? '';echo '<p>'.$label.': '.(is_string($value) ? htmlspecialchars($value,ENT_QUOTES) : '').'</p>';
  }
  echo '<a href="scheduling-preview.php">Return to booking preview</a></body></html>';exit;
 }
 echo json_encode(['status'=>'error','msg'=>'Preview only; no booking is saved.']);exit;
}
?>
PHP;
$html='<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>SmartServe booking preview</title><link rel="stylesheet" href="../../dashboard/assets/css/assets.css"><link rel="stylesheet" href="../../dashboard/assets/css/style.css"><link rel="stylesheet" href="../../assets/css/forms.css"><link rel="stylesheet" href="../../assets/css/schedule-calendar.css"><script src="../../assets/js/forms.js"></script><script src="../../assets/js/schedule-calendar.js" defer></script><style>'.$css.'body{background:#f5f8f6;padding:24px}.preview-wrap{max-width:850px;margin:auto;background:white;padding:32px;border-radius:18px}</style></head><body><main class="preview-wrap"><h3>Book an appointment</h3><p class="text-muted">Synthetic preview · no accounts or bookings are saved</p>'.$form.'</main><script src="../../dashboard/assets/js/jquery.min.js"></script>'.$script[0].'</body></html>';
// Keep the production wrapper so malformed closing tags cannot hide form ownership bugs.
$html=str_replace('are saved</p>'.$form,'are saved</p><div class="appointment-card">'.$form.'</div>',$html);
file_put_contents(__DIR__.'/fixtures/scheduling-preview.php',$api.$html);
echo "Generated scheduling UI preview.\n";
