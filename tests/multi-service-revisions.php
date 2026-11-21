<?php
// Run only against disposable schema clones; outgoing notifications are captured.
if (PHP_SAPI !== 'cli') { http_response_code(403); exit('CLI only.'); }
putenv('SMARTSERVE_MAIL_AUTOSEND=0');
require_once __DIR__ . '/../global/model.php';
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
$db = new mysqli('127.0.0.1', 'root', '', 'smartserve');
$db->set_charset('utf8mb4');
$db->query("SET time_zone='+08:00'");
$testDb = 'smartserve_multi_test_' . bin2hex(random_bytes(6));
if (!preg_match('/\Asmartserve_multi_test_[a-f0-9]{12}\z/', $testDb)) throw new RuntimeException('Invalid disposable schema');
$db->query("CREATE DATABASE `$testDb` CHARACTER SET utf8mb4");
$checks = 0;
function multiCheck($ok, $label) {
    global $checks;
    $checks++;
    if (!$ok) throw new RuntimeException($label);
}
try {
    $db->query('SET FOREIGN_KEY_CHECKS=0');
    foreach (['accounts','offices','appointments','appointment_offices','blocked_schedules','blocked_schedule_appointments'] as $table) {
        $create = $db->query("SHOW CREATE TABLE smartserve.`$table`")->fetch_row()[1];
        $db->query(preg_replace('/^CREATE TABLE `[^`]+`/', "CREATE TABLE `$testDb`.`$table`", $create, 1));
    }
    $db->select_db($testDb);
    $db->query('SET FOREIGN_KEY_CHECKS=1');
    $db->query("INSERT INTO accounts(id,fname,mname,lname,position,uname,pword,photo,access,status) VALUES
        (1,'Test','','Student','Student','student@example.com','test','default',3,1),
        (2,'Other','','Student','Student','other@example.com','test','default',3,1),
        (3,'Test','','Admin','Administrator','admin@example.com','test','default',1,1)");
    $db->query("INSERT INTO offices(id,office_name,status) VALUES(1,'Registrar',1),(2,'Cashier',1),(3,'Clinic',1)");
    $captured = [];
    $emails = new SmartServeEmails($db, function($row) use (&$captured) { $captured[] = $row; return true; });
    $model = (new ReflectionClass(Model::class))->newInstanceWithoutConstructor();
    foreach (['conn'=>$db,'emails'=>$emails] as $key=>$value) {
        $property = new ReflectionProperty(Model::class, $key);
        $property->setAccessible(true); $property->setValue($model, $value);
    }
    $today = date('Y-m-d');
    $nextWeekday = static function($after) {
        do { $after = date('Y-m-d', strtotime($after . ' +1 day')); } while (!SmartServeValidation::weekdayDate($after));
        return $after;
    };
    $tomorrow = $nextWeekday($today);
    $later = $nextWeekday($tomorrow);
    $clock = function($date, $time) use ($db) { $db->query("SET timestamp=UNIX_TIMESTAMP('$date $time')"); };
    $clock($today, '07:00:00');
    $insert = function($tracking, $date, array $states, $time='08:00 AM') use ($db) {
        $stmt = $db->prepare("INSERT INTO appointments(tracking_no,student_id,appointment_date,appointment_time,notes,status) VALUES(?,1,?,?,'Multi-service request',0)");
        $stmt->bind_param('sss', $tracking, $date, $time); $stmt->execute(); $id = $db->insert_id; $stmt->close();
        foreach ($states as $office=>$status) {
            $stmt = $db->prepare("INSERT INTO appointment_offices(tracking_no,office_id,status,queue_number,time_queued,time_started,time_completed,office_notes,served_at_window) VALUES(?,?,?,'Q-TEST',IF(? IN(1,2,3),NOW(),NULL),IF(? IN(2,3),NOW(),NULL),IF(?=3,NOW(),NULL),'Preserved office notes','Window 1')");
            $stmt->bind_param('siiiii', $tracking, $office, $status, $status, $status, $status); $stmt->execute(); $stmt->close();
        }
        return $id;
    };
    $visit = function($tracking, $office) use ($db) {
        $stmt = $db->prepare('SELECT * FROM appointment_offices WHERE tracking_no=? AND office_id=?');
        $stmt->bind_param('si',$tracking,$office); $stmt->execute(); $row=$stmt->get_result()->fetch_assoc(); $stmt->close(); return $row;
    };
    $ticket = function($tracking) use ($db) {
        $stmt=$db->prepare('SELECT * FROM appointments WHERE tracking_no=?'); $stmt->bind_param('s',$tracking); $stmt->execute(); $row=$stmt->get_result()->fetch_assoc(); $stmt->close(); return $row;
    };
    $notice = function($tracking,$office,$date,$time='08:00 AM') use ($model,$db,$ticket) {
        $block=$model->createBlockedSchedule($office,$date,null,null,'full_day','Office unavailable','Please choose another day.',3);
        $appt=$ticket($tracking);
        multiCheck($model->addBlockedScheduleAppointment($block,(int)$appt['id'],$tracking,1,$office,$date,$time),'Office-specific blocking notice saves');
        $stmt=$db->prepare('SELECT id FROM blocked_schedule_appointments WHERE block_id=? AND tracking_no=? AND office_id=?');
        $stmt->bind_param('isi',$block,$tracking,$office);$stmt->execute();$id=(int)$stmt->get_result()->fetch_row()[0];$stmt->close();return $id;
    };
    $counts = fn()=>[(int)$db->query('SELECT COUNT(*) FROM appointments')->fetch_row()[0],(int)$db->query('SELECT COUNT(*) FROM appointment_offices')->fetch_row()[0],(int)$db->query('SELECT COUNT(*) FROM email_outbox')->fetch_row()[0]];

    $originalId=$insert('MULTI-ORIGINAL',$today,[1=>3,2=>0,3=>0]);
    $original=$ticket('MULTI-ORIGINAL');
    $completed=$visit('MULTI-ORIGINAL',1);
    $noticeId=$notice('MULTI-ORIGINAL',2,$today);
    $details=$model->getAppointmentDetails('MULTI-ORIGINAL',1);
    multiCheck((int)$details['status']===1,'Completed plus pending services keeps booking In Progress');
    multiCheck(count($details['services'])===3,'Details expose all three service visits');
    $services=array_column($details['services'],null,'office_id');
    multiCheck((int)$services[1]['status']===3 && (int)$services[2]['status']===0 && (int)$services[3]['status']===0,'Each service has its independent status');
    multiCheck((int)$services[2]['blocked_notice_id']===$noticeId && $services[2]['block_reason']==='Office unavailable','Student sees which service is blocked and why');
    multiCheck($model->getAppointmentDetails('MULTI-ORIGINAL',2)===[],'Service details remain private to booking owner');
    multiCheck($model->verifyQRCode('MULTI-ORIGINAL',2)['status']==='error','Kiosk cannot activate a blocked service');
    multiCheck($model->verifyAndQueueQR('MULTI-ORIGINAL',2)['status']==='error','Staff scanner cannot activate a blocked service');
    $clock($today,'08:10:00');
    multiCheck($model->autoCancelExpiredAppointments()===1,'At ten minutes only the unblocked unscanned service expires');
    multiCheck($visit('MULTI-ORIGINAL',1)===$completed,'Expiry preserves completed service details and timestamps');
    multiCheck((int)$visit('MULTI-ORIGINAL',2)['status']===0 && (int)$visit('MULTI-ORIGINAL',3)['status']===4,'Blocked office remains actionable while unrelated missed service is cancelled');
    multiCheck(count($model->getStudentBlockedAppointments(1))===1,'Blocking notice remains available after original arrival deadline');
    $before=$counts();
    multiCheck(!$model->submitBlockedAppointmentResponse($noticeId,2,'reschedule',$tomorrow,'09:00 AM')['ok'],'Another student cannot reschedule this service');
    multiCheck($counts()===$before,'Rejected ownership check saves and queues nothing');
    $response=$model->submitBlockedAppointmentResponse($noticeId,1,'reschedule',$tomorrow,'09:00 AM');
    multiCheck($response['ok'] && !empty($response['tracking_no']) && $response['tracking_no']!=='MULTI-ORIGINAL','Reschedule returns a new tracking number for a separate QR');
    $replacementTracking=$response['tracking_no'];
    $replacement=$ticket($replacementTracking);
    multiCheck((int)$replacement['rescheduled_from_id']===$originalId && $replacement['appointment_date']===$tomorrow && $replacement['appointment_time']==='09:00 AM','Replacement links back to original ticket and has its own schedule');
    multiCheck($replacement['notes']===$original['notes'] && (int)$replacement['student_id']===1,'Replacement preserves purpose and ownership');
    $replacementServices=$db->query("SELECT office_id,status FROM appointment_offices WHERE tracking_no='".$db->real_escape_string($replacementTracking)."'")->fetch_all(MYSQLI_ASSOC);
    multiCheck(count($replacementServices)===1 && (int)$replacementServices[0]['office_id']===2 && (int)$replacementServices[0]['status']===0,'Only the blocked office gets a new pending service visit');
    $afterOriginal=$ticket('MULTI-ORIGINAL');
    multiCheck($afterOriginal['appointment_date']===$original['appointment_date'] && $afterOriginal['appointment_time']===$original['appointment_time'],'Reschedule leaves original shared date and time unchanged');
    multiCheck($visit('MULTI-ORIGINAL',1)===$completed && (int)$visit('MULTI-ORIGINAL',3)['status']===4,'Reschedule neither resets completed service nor revives expired service');
    multiCheck((int)$visit('MULTI-ORIGINAL',2)['status']===4,'Old office visit is retired so old QR cannot serve the replacement');
    $details=$model->getAppointmentDetails('MULTI-ORIGINAL',1);
    $services=array_column($details['services'],null,'office_id');
    multiCheck($services[2]['rescheduled_tracking_no']===$replacementTracking && $services[2]['rescheduled_date']===$tomorrow,'Original details link to the affected service replacement');
    multiCheck($model->getAppointmentDetails($replacementTracking,1)['rescheduled_from_tracking_no']==='MULTI-ORIGINAL','Replacement details link to original booking');
    $history=array_column($model->getStudentAppointments(1),null,'tracking_no');
    multiCheck(isset($history['MULTI-ORIGINAL'],$history[$replacementTracking]) && count($history[$replacementTracking]['services'])===1,'History shows both original and separate replacement booking');
    $before=$counts();
    multiCheck(!$model->submitBlockedAppointmentResponse($noticeId,1,'reschedule',$later,'10:00 AM')['ok'] && $counts()===$before,'Repeated response cannot create another replacement or notification');
    multiCheck($model->verifyQRCode('MULTI-ORIGINAL',2)['status']==='error','Old QR is rejected at the rescheduled service');
    $completedReport=$model->getAdminReports(null,$today,$today,3)['records'];
    $closedReport=$model->getAdminReports(null,$today,$today,4)['records'];
    multiCheck(count($completedReport)===1 && $completedReport[0]['office_name']==='Registrar','Completed report contains only the service actually completed');
    multiCheck(count($closedReport)===2 && count(array_filter($closedReport,fn($r)=>$r['office_name']==='Cashier' && $r['rescheduled_tracking_no']===$replacementTracking))===1,'Closed report independently includes expired and rescheduled services with replacement link');
    $clock($tomorrow,'09:00:00');
    multiCheck($model->verifyQRCode($replacementTracking,1)['status']==='error' && $model->verifyQRCode($replacementTracking,3)['status']==='error','New QR cannot activate unrelated original services');
    multiCheck($model->verifyQRCode($replacementTracking,2)['status']==='success','New QR activates only the rescheduled service on its new date');
    $clock($tomorrow,'09:10:00');
    multiCheck($model->autoCancelExpiredAppointments()===0 && (int)$visit($replacementTracking,2)['status']===1,'Replacement check-in survives its own ten-minute deadline');
    multiCheck($model->updateQueueStatus($visit($replacementTracking,2)['id'],2,'','Window 2') && $model->updateQueueStatus($visit($replacementTracking,2)['id'],3,'Done'),'Rescheduled office can call and complete its own visit');
    multiCheck((int)$model->getAppointmentDetails($replacementTracking,1)['status']===2 && $visit('MULTI-ORIGINAL',1)===$completed,'New ticket completion preserves historical completed office');

    // Cancelling or moving a blocked office must not affect another office serving the student.
    $clock($today,'07:00:00');
    $insert('MULTI-CANCEL',$tomorrow,[1=>0,2=>0,3=>2],'10:00 AM');
    $cancelNotice=$notice('MULTI-CANCEL',2,$tomorrow,'10:00 AM');
    $unaffected=[$visit('MULTI-CANCEL',1),$visit('MULTI-CANCEL',3),$ticket('MULTI-CANCEL')];
    multiCheck($model->submitBlockedAppointmentResponse($cancelNotice,1,'cancel')['ok'],'Blocked service can be cancelled while another service is serving');
    multiCheck($visit('MULTI-CANCEL',1)===$unaffected[0] && $visit('MULTI-CANCEL',3)===$unaffected[1] && $ticket('MULTI-CANCEL')['status']===$unaffected[2]['status'],'Service-only cancellation preserves other visits and active parent');
    multiCheck((int)$visit('MULTI-CANCEL',2)['status']===4,'Only requested blocked service is cancelled');
    $before=$counts();
    multiCheck(!$model->submitBlockedAppointmentResponse($cancelNotice,1,'cancel')['ok'] && $counts()===$before,'Repeated service cancellation is idempotent');

    $insert('MULTI-ROLLBACK',$tomorrow,[1=>3,2=>0,3=>2],'11:00 AM');
    $rollbackNotice=$notice('MULTI-ROLLBACK',2,$tomorrow,'11:00 AM');
    $unchanged=[$ticket('MULTI-ROLLBACK'),$visit('MULTI-ROLLBACK',1),$visit('MULTI-ROLLBACK',2),$visit('MULTI-ROLLBACK',3),$counts()];
    $db->query("CREATE TRIGGER reject_split BEFORE UPDATE ON blocked_schedule_appointments FOR EACH ROW BEGIN IF OLD.tracking_no='MULTI-ROLLBACK' AND NEW.action_status='rescheduled' THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Synthetic split failure'; END IF; END");
    multiCheck(!$model->submitBlockedAppointmentResponse($rollbackNotice,1,'reschedule',$later,'10:00 AM')['ok'],'Final notice failure rejects split reschedule');
    multiCheck([$ticket('MULTI-ROLLBACK'),$visit('MULTI-ROLLBACK',1),$visit('MULTI-ROLLBACK',2),$visit('MULTI-ROLLBACK',3),$counts()]===$unchanged,'Failed split rolls back replacement, service state, schedule, and notifications');
    $db->query('DROP TRIGGER reject_split');
    $retry=$model->submitBlockedAppointmentResponse($rollbackNotice,1,'reschedule',$later,'10:00 AM');
    multiCheck($retry['ok'] && !empty($retry['tracking_no']),'Reschedule can retry after rollback while unrelated service is serving');
    multiCheck($visit('MULTI-ROLLBACK',3)===$unchanged[3] && $visit('MULTI-ROLLBACK',1)===$unchanged[1],'Successful split also preserves serving and completed sibling visits');

    // A stale office notice cannot change a visit that has already finished.
    $insert('MULTI-STALE-NOTICE',$tomorrow,[1=>3,2=>0],'01:00 PM');
    $staleNotice=$notice('MULTI-STALE-NOTICE',1,$tomorrow,'01:00 PM');
    $staleBefore=[$ticket('MULTI-STALE-NOTICE'),$visit('MULTI-STALE-NOTICE',1),$visit('MULTI-STALE-NOTICE',2),$counts()];
    multiCheck(!$model->submitBlockedAppointmentResponse($staleNotice,1,'cancel')['ok'],'Stale notice cannot cancel a completed target service');
    multiCheck(!$model->submitBlockedAppointmentResponse($staleNotice,1,'reschedule',$later,'02:00 PM')['ok'],'Stale notice cannot recreate a completed target service');
    multiCheck([$ticket('MULTI-STALE-NOTICE'),$visit('MULTI-STALE-NOTICE',1),$visit('MULTI-STALE-NOTICE',2),$counts()]===$staleBefore,'Stale service response preserves both sibling visits and notifications');
    multiCheck(count(array_filter($model->getStudentBlockedAppointments(1),fn($row)=>(int)$row['id']===$staleNotice))===0,'Completed target service has no actionable blocking notice');

    // Separate rescheduling obeys the same per-service/date duplicate rule as new booking.
    $conflictDay=$nextWeekday($later);
    $insert('MULTI-CONFLICT',$tomorrow,[1=>0,3=>2],'02:00 PM');
    $conflictNotice=$notice('MULTI-CONFLICT',1,$tomorrow,'02:00 PM');
    $insert('EXISTING-SERVICE',$conflictDay,[1=>0],'09:00 AM');
    $conflictBefore=[$ticket('MULTI-CONFLICT'),$visit('MULTI-CONFLICT',1),$visit('MULTI-CONFLICT',3),$counts()];
    multiCheck(!$model->submitBlockedAppointmentResponse($conflictNotice,1,'reschedule',$conflictDay,'10:00 AM')['ok'],'Existing active same-service booking blocks replacement on that date');
    multiCheck([$ticket('MULTI-CONFLICT'),$visit('MULTI-CONFLICT',1),$visit('MULTI-CONFLICT',3),$counts()]===$conflictBefore,'Duplicate rejection leaves original service and other active offices intact');

    // A newer disabled block must not hide an older active hold on the same service.
    $insert('MULTI-HELD-QUEUE',$today,[1=>3,3=>1]);
    $activeNotice=$notice('MULTI-HELD-QUEUE',3,$today);
    $disabledNotice=$notice('MULTI-HELD-QUEUE',3,$today);
    $db->query("UPDATE blocked_schedules bs JOIN blocked_schedule_appointments bsa ON bsa.block_id=bs.id SET bs.status=0 WHERE bsa.id=$disabledNotice");
    $heldServices=array_column($model->getAppointmentDetails('MULTI-HELD-QUEUE',1)['services'],null,'office_id');
    multiCheck((int)$heldServices[3]['blocked_notice_id']===$activeNotice,'Details prioritize an active service hold over a newer disabled notice');
    $heldBefore=[$visit('MULTI-HELD-QUEUE',1),$visit('MULTI-HELD-QUEUE',3),$counts()];
    multiCheck($model->verifyQRCode('MULTI-HELD-QUEUE',3)['status']==='error','Already-queued service cannot bypass an active block at kiosk');
    multiCheck(!$model->updateQueueStatus($visit('MULTI-HELD-QUEUE',3)['id'],2,'','Window 3'),'Staff cannot call a blocked queued service before its response');
    multiCheck([$visit('MULTI-HELD-QUEUE',1),$visit('MULTI-HELD-QUEUE',3),$counts()]===$heldBefore,'Blocked queue actions preserve state and create no notification');
    $newHoldDate=$nextWeekday($conflictDay);
    $heldResponse=$model->submitBlockedAppointmentResponse($activeNotice,1,'reschedule',$newHoldDate,'09:00 AM');
    multiCheck($heldResponse['ok'] && !empty($heldResponse['tracking_no']),'Held queued service receives a separate replacement ticket');
    $resolvedNotices=$db->query("SELECT action_status,new_appointment_id FROM blocked_schedule_appointments WHERE tracking_no='MULTI-HELD-QUEUE' ORDER BY id")->fetch_all(MYSQLI_ASSOC);
    multiCheck(count($resolvedNotices)===2 && $resolvedNotices[0]['action_status']==='rescheduled' && $resolvedNotices[1]===$resolvedNotices[0],'Overlapping notices resolve to the same replacement ticket');

    multiCheck(count($captured)===0,'Automatic outgoing delivery stays disabled during regression checks');
    $result=$emails->dispatch();
    multiCheck($result['failed']===0 && count($captured)>0,'Notification delivery uses capture-only transport');
    echo "Passed $checks multi-service revision checks. Disposable database; no real messages sent.\n";
} finally {
    $db->rollback();
    $db->query('SET timestamp=0');
    $db->select_db('smartserve');
    $db->query("DROP DATABASE `$testDb`");
    $db->close();
}
