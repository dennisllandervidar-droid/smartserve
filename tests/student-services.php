<?php
if (PHP_SAPI !== 'cli') { http_response_code(403); exit('CLI only.'); }
putenv('SMARTSERVE_MAIL_AUTOSEND=0');
require_once __DIR__.'/../global/model.php';
require_once __DIR__.'/../global/priority.php';
require_once __DIR__.'/../global/sms.php';
date_default_timezone_set('Asia/Manila');
mysqli_report(MYSQLI_REPORT_ERROR|MYSQLI_REPORT_STRICT);
$db=new mysqli('localhost','root','','smartserve'); $db->set_charset('utf8mb4'); $db->query("SET time_zone='+08:00'");
$testDb='smartserve_service_test_'.bin2hex(random_bytes(6));
if (!preg_match('/\Asmartserve_service_test_[a-f0-9]{12}\z/',$testDb)) throw new RuntimeException('Invalid test database');
$db->query("CREATE DATABASE `$testDb` CHARACTER SET utf8mb4");
$checks=0;
function serviceCheck($ok,$label) { global $checks; $checks++; if(!$ok)throw new RuntimeException($label); }
try {
    $db->query('SET FOREIGN_KEY_CHECKS=0');
    foreach(['accounts','offices','appointments','appointment_offices','blocked_schedules','blocked_schedule_appointments','announcements'] as $table) {
        $sql=$db->query("SHOW CREATE TABLE smartserve.`$table`")->fetch_row()[1];
        $db->query(preg_replace('/^CREATE TABLE `[^`]+`/',"CREATE TABLE `$testDb`.`$table`",$sql,1));
    }
    $db->select_db($testDb); $db->query('SET FOREIGN_KEY_CHECKS=1');
    $idPhoto=str_repeat('a',48).'.png';
    $db->query("INSERT INTO accounts (id,fname,lname,position,uname,pword,contact,address,photo,access,status,priority_type,pwd_id_path,pwd_status) VALUES
    (1,'Juan','Dela Cruz','Student','juan@example.com','test','09123456789','123 Street','default',3,1,'N/A',NULL,'none'),
    (2,'Ana','Santos','Student','ana@example.com','test','09123456789','123 Street','default',3,1,'PWD','$idPhoto','pending'),
    (3,'Admin','Santos','Admin','admin@example.com','test','09123456789','123 Street','default',1,1,'N/A',NULL,'none'),
    (4,'Office','Santos','Staff','office@example.com','test','09123456789','123 Street','default',2,1,'N/A',NULL,'none')");
    $db->query("INSERT INTO offices(id,office_name,status) VALUES(1,'Registrar',1),(2,'Cashier',1)");
    $emails=new SmartServeEmails($db,fn($row)=>true);
    $reflection=new ReflectionClass(Model::class); $model=$reflection->newInstanceWithoutConstructor();
    foreach(['conn'=>$db,'emails'=>$emails]as$key=>$value){$property=$reflection->getProperty($key);$property->setAccessible(true);$property->setValue($model,$value);}
    $today=date('Y-m-d');$future=date('Y-m-d',strtotime('+1 day'));
    serviceCheck($model->studentPriority(2)==='N/A','Pending PWD has regular priority');
    serviceCheck($model->addAppointment('PENDING-PWD',2,[1],$future,'09:00 AM','Test',1,'PWD'),'Pending applicant can book');
    serviceCheck($db->query("SELECT priority FROM appointments WHERE tracking_no='PENDING-PWD'")->fetch_row()[0]==='N/A','Forged PWD booking does not enable priority');
    serviceCheck(!$model->reviewPwdId(2,'approved',4),'Office cannot approve an ID');
    serviceCheck(!$model->reviewPwdId(2,'approved',1),'Student cannot approve an ID');
    serviceCheck($model->reviewPwdId(2,'approved',3),'Admin approves pending ID');
    serviceCheck($model->studentPriority(2)==='PWD','Verified account gets PWD priority');
    $walkin=$model->addWalkInAppointment(2,null,1,'Test',0,'N/A');
    serviceCheck($walkin['status']==='success','Registered walk-in uses profile priority');
    $walkinRow=$db->query('SELECT * FROM appointments WHERE student_id=2 AND is_walkin=1 LIMIT 1')->fetch_assoc();
    $walkinTracking=$walkinRow['tracking_no'];
    serviceCheck($walkinRow['priority']==='PWD' && (int)$walkinRow['is_priority']===1,'Walk-in cannot override verified PWD with N/A');
    $todayQueue=$model->getTodayQueue(1);
    serviceCheck(count(array_filter($todayQueue,fn($row)=>$row['tracking_no']===$walkinTracking && (int)$row['student_id']===2 && (int)$row['has_pwd_id']===1))===1,'Queue includes ID link metadata');
    $history=$model->getOfficeHistoryByDate(1,$today);
    serviceCheck(count(array_filter($history,fn($row)=>$row['tracking_no']===$walkinTracking && (int)$row['has_pwd_id']===1))===1,'Office history includes ID link metadata');
    $reports=$model->getAdminReports();
    serviceCheck(count(array_filter($reports['records'],fn($row)=>$row['tracking_no']===$walkinTracking && (int)$row['has_pwd_id']===1))===1,'Admin history includes ID link metadata');
    foreach([1,2]as$role)serviceCheck(smartserve_can_view_pwd(['id'=>3,'access'=>$role,'status'=>1],2),'Active staff can inspect IDs');
    serviceCheck(smartserve_can_view_pwd(['id'=>2,'access'=>3,'status'=>1],2),'Student can inspect own ID');
    serviceCheck(!smartserve_can_view_pwd(['id'=>1,'access'=>3,'status'=>1],2),'Other student cannot inspect ID');
    serviceCheck(!smartserve_can_view_pwd(['id'=>3,'access'=>1,'status'=>0],2),'Inactive staff denied ID access');
    serviceCheck(!smartserve_can_view_pwd(null,2),'Anonymous ID access denied');
    serviceCheck(!$model->reviewPwdId(2,'approved',3),'Repeated review is ignored');
    serviceCheck($model->addAppointment('APPROVED-PWD',2,[2],$future,'10:00 AM','Test',0,'N/A'),'Approved applicant books');
    serviceCheck($db->query("SELECT is_priority FROM appointments WHERE tracking_no='APPROVED-PWD'")->fetch_row()[0]==1,'Verified PWD is applied automatically');
    serviceCheck($model->updateStudentPriority(2,'PWD',str_repeat('b',48).'.jpg'),'New ID submission saved');
    serviceCheck($model->studentPriority(2)==='N/A','Replacing ID resets approval');
    serviceCheck($model->reviewPwdId(2,'rejected',3),'Admin rejects pending ID');
    serviceCheck($model->studentPriority(2)==='N/A','Rejected account remains regular');
    serviceCheck(!$model->updateStudentPriority(1,'PWD'),'PWD request requires an ID');
    serviceCheck(!$model->updateStudentPriority(1,'PWD','../../file.png'),'ID path traversal denied');
    serviceCheck($model->updateStudentProfile('Juan','','Dela Cruz','09123456789','123 Street',1,'N/A'),'Middle name is optional on update');
    foreach (['', '   ', '0912345678', '09123456789 ', '09 123456789'] as $phone) {
        serviceCheck(!$model->updateAccountProfile('Changed','','Student','Student',$phone,'123 Street',1),'Model rejects empty or malformed profile contact');
        serviceCheck($model->displayStudentAccountDetails(1)['fname']==='Juan' && $model->displayStudentAccountDetails(1)['contact']==='09123456789','Rejected contact preserves stored profile');
    }
    serviceCheck(!$model->updateStudentProfile('Juan','','Dela Cruz','09123456789 ','123 Street',1,'PWD',$idPhoto),'Bad phone prevents profile save');
    serviceCheck($model->displayStudentAccountDetails(1)['priority_type']==='N/A','Failed profile update rolls back priority');
    $queue=$db->query("SELECT COUNT(*) FROM email_outbox WHERE subject LIKE '%PWD%'")->fetch_row()[0];
    serviceCheck($queue==2,'Approval and rejection each queue one email');
    foreach (['N/A','Senior Citizen','Pregnant','PWD'] as $type) {
        serviceCheck($model->updateStudentPriority(2,$type),'Current priority changes with a retained ID');
        $account=$model->displayStudentAccountDetails(2);
        serviceCheck($account['pwd_id_path']===str_repeat('b',48).'.jpg','Prior ID remains stored when priority changes');
        foreach ([false,true] as $admin) {
            ob_start();smartserve_pwd_card($account,$admin);$card=ob_get_clean();
            serviceCheck(str_contains($card,'View PWD ID')===($type==='PWD'),'Student, office and admin card link follows current PWD type');
            serviceCheck(str_contains($card,'Approve PWD')===($admin && $type==='PWD'),'Admin review controls follow current PWD type');
        }
        foreach ([$model->getTodayQueue(1),$model->getOfficeHistoryByDate(1,$today),$model->getAdminReports()['records']] as $rows) {
            $studentRows=array_values(array_filter($rows,fn($row)=>(int)$row['student_id']===2));
            serviceCheck(count($studentRows)>0,'Queue/history/report fixture contains this student');
            serviceCheck(count(array_filter($studentRows,fn($row)=>(bool)$row['has_pwd_id']!==($type==='PWD')))===0,'Office queue/history and admin reports show ID only for current PWD type');
        }
    }
    foreach([' 09123456789','09123456789 ','09 123456789','09123-456789','09123456789\n','   ']as$phone) serviceCheck(!SmartServeValidation::phone($phone),'Phone spaces and characters rejected');
    $boundary=date('Y-m-d',strtotime('+7 days'));
    serviceCheck(SmartServeValidation::bookingDate($boundary)===SmartServeValidation::weekdayDate($boundary),'Seven-day boundary allowed only on a weekday');
    foreach (['next Saturday','next Sunday'] as $weekendDay) {
        $weekend=date('Y-m-d',strtotime($weekendDay));
        serviceCheck(!$model->addAppointment('WEEKEND-'.$weekendDay,1,[1],$weekend,'09:00 AM','Test'), 'Model rejects weekend booking: '.$weekendDay);
        serviceCheck(count(array_filter($model->scheduleAvailability([1],$weekend), fn($slot)=>$slot['available']>0))===0, 'Weekend never exposes an available time');
        $rejected=$model->submitBlockedAppointmentResponse(1,1,'reschedule',$weekend,'09:00 AM');
        serviceCheck(!$rejected['ok'] && str_contains($rejected['msg'],'weekday'), 'Weekend reschedule rejected before saving');
    }
    serviceCheck(!SmartServeValidation::bookingDate(date('Y-m-d',strtotime('+8 days'))),'Eighth day denied');
    serviceCheck(!$model->addAppointment('TOO-FAR',1,[1],date('Y-m-d',strtotime('+8 days')),'09:00 AM','Test'),'Model rejects bookings beyond seven days');
    serviceCheck(isset(SmartServeValidation::request(['submit_verify'=>'','contact'=>'09123456789 '],'/register.php')['contact']),'HTTP validation retains raw phone whitespace');
    $db->query("SET timestamp=UNIX_TIMESTAMP('$today 06:49:59')");
    $add=function($tracking,$time,$status=0,$walkin=0)use($db,$today){$stmt=$db->prepare("INSERT INTO appointments(tracking_no,student_id,appointment_date,appointment_time,is_walkin,status)VALUES(?,1,?,?,?,0)");$stmt->bind_param('sssi',$tracking,$today,$time,$walkin);$stmt->execute();$stmt->close();$stmt=$db->prepare('INSERT INTO appointment_offices(tracking_no,office_id,status)VALUES(?,1,?)');$stmt->bind_param('si',$tracking,$status);$stmt->execute();$stmt->close();};
    $add('REMIND-AMPM','07:00 AM');$add('REMIND-24H','07:00:00');$add('REMIND-HHMM','07:00');$add('CHECKED-IN','07:00 AM',1);$add('WALK-IN','07:00 AM',0,1);$add('CANCELLED','07:00 AM',4);$add('LATER','08:00 AM');$add('PAST','06:00 AM');
    serviceCheck(count($model->getPendingSMSAppointments())===0,'Reminder not sent at 10 minutes and one second before');
    $db->query("SET timestamp=UNIX_TIMESTAMP('$today 06:50:00')");
    serviceCheck(count($model->getPendingSMSAppointments())===3,'Reminder window opens exactly ten minutes before in all formats');
    $captured=[];$stats=$model->runSmsReminders(function($number,$message)use(&$captured){$captured[]=[$number,$message];return true;});
    serviceCheck($stats['sent']===3 && $stats['failed']===0,'Reminder sends only pending online appointments');
    serviceCheck($captured[0][0]==='+639123456789' && str_contains($captured[0][1],'10 minutes after'),'Reminder uses strict PH number and ten-minute cutoff');
    serviceCheck($model->runSmsReminders(fn()=>true)['sent']===0,'Reminders are not duplicated');
    $add('RETRY','07:00 AM');
    serviceCheck($model->runSmsReminders(fn()=>false)['failed']===1,'Gateway failure reported');
    serviceCheck($db->query("SELECT COALESCE(sms_notif,0) FROM appointments WHERE tracking_no='RETRY'")->fetch_row()[0]==0,'Failed reminder is not marked sent');
    serviceCheck($model->runSmsReminders(fn()=>true)['sent']===1,'Failed reminder can retry');
    $add('CLAIMED','07:00 AM');$db->query("UPDATE appointments SET sms_claimed_at=NOW() WHERE tracking_no='CLAIMED'");
    serviceCheck($model->runSmsReminders(fn()=>true)['sent']===0,'Another worker claim is respected');
    $db->query("UPDATE appointments SET sms_claimed_at=DATE_SUB(NOW(),INTERVAL 3 MINUTE) WHERE tracking_no='CLAIMED'");
    serviceCheck($model->runSmsReminders(fn()=>true)['sent']===1,'Stale SMS worker claim recovers');
    $db->query("SET timestamp=UNIX_TIMESTAMP('2026-03-01 09:00:00')");
    $one=$model->addAnnouncement('Monthly advisory','Details','default','default','2026-02-01 09:00:00',3,1);
    $own=$model->addAnnouncement('Office advisory','Details','default','default','2026-03-01 09:00:00',4,1);
    $db->query("SET timestamp=UNIX_TIMESTAMP('2026-04-01 08:59:59')");
    serviceCheck($model->autoArchiveAdvisories()===0,'Advisory survives until calendar month boundary');
    $db->query("SET timestamp=UNIX_TIMESTAMP('2026-04-01 09:00:00')");
    serviceCheck($model->autoArchiveAdvisories()===2,'Archives exactly one calendar month after publication');
    serviceCheck($model->autoArchiveAdvisories()===0,'Auto archive is idempotent');
    serviceCheck(count($model->getArchivedAdvisories(3,true))===2,'Admin sees all archived advisories');
    serviceCheck(count($model->getArchivedAdvisories(4,false))===1,'Office archive contains own advisory');
    serviceCheck($model->displayAnnouncements(1,1)===[],'Expired advisories disappear from active list');
    $model->archiveAnnouncement(1,$own);
    serviceCheck(count($model->displayMyAnnouncements(4,1,1))===1,'Restored advisory gets a new month');
    $db->query("SET timestamp=UNIX_TIMESTAMP('$today 06:00:00')");
    $block=$model->createBlockedSchedule(1,$future,'09:00','10:00','time_range','Unavailable','',4);
    $slots=$model->scheduleAvailability([1],$future);
    serviceCheck($slots['09:00 AM']['available']===0 && $slots['09:00 AM']['label']==='Blocked','Calendar disables blocked time');
    serviceCheck($slots['11:00 AM']['available']>0,'Time following block is available');
    for($i=0;$i<5;$i++) {
        $capacityStudent=100+$i;
        $db->query("INSERT INTO accounts(id,fname,lname,position,uname,pword,photo,access,status) VALUES($capacityStudent,'Capacity','Student','Student','capacity.$i@example.com','test','default',3,1)");
        serviceCheck($model->addAppointment('CAPACITY-'.$i,$capacityStudent,[2],$future,'11:00 AM','Test'), 'Different student reserves a capacity slot');
    }
    serviceCheck($model->scheduleAvailability([2],$future)['11:00 AM']['available']===0,'Calendar disables fully reserved time');
    $affectedId = (int)$db->query("SELECT id FROM appointments WHERE tracking_no='PENDING-PWD'")->fetch_row()[0];
    serviceCheck($model->addBlockedScheduleAppointment($block,$affectedId,'PENDING-PWD',2,1,$future,'09:00 AM'),'Create blocked notice for affected student');
    $notices = $model->getStudentBlockedAppointments(2);
    serviceCheck(count($notices) === 1 && $notices[0]['office_name'] === 'Registrar' && $notices[0]['reason'] === 'Unavailable', 'My Appointments receives the affected office and blocking reason');
    serviceCheck($model->getStudentBlockedAppointments(1) === [], 'Blocking notifications are private to the affected student');
    $db->query("UPDATE blocked_schedule_appointments SET action_status='rescheduled' WHERE appointment_id=$affectedId");
    serviceCheck($model->getStudentBlockedAppointments(2) === [], 'Handled blocking notifications disappear');
    $db->query("UPDATE blocked_schedule_appointments SET action_status='notified' WHERE appointment_id=$affectedId");
    $db->query("UPDATE appointments SET status=3 WHERE id=$affectedId");
    serviceCheck($model->getStudentBlockedAppointments(2) === [], 'Cancelled appointments have no pending blocking notification');
    echo "Passed $checks student service checks. No real email or SMS sent.\n";
} finally {$db->query('SET timestamp=0');$db->select_db('smartserve');$db->query("DROP DATABASE `$testDb`");}
