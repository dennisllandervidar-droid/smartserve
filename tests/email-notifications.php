<?php
// Capture-only integration tests in a disposable database. No SMTP/SMS requests.
if (PHP_SAPI !== 'cli') { http_response_code(403); exit('CLI only.'); }
putenv('SMARTSERVE_MAIL_AUTOSEND=0');
putenv('SMARTSERVE_SMTP_USERNAME=notifications@example.com');
putenv('SMARTSERVE_SMTP_PASSWORD=test-only');
putenv('SMARTSERVE_MAIL_FROM=notifications@example.com');
require_once __DIR__ . '/../global/model.php';
$checks = 0;
function checkEmail($condition, $label) {
    global $checks;
    $checks++;
    if (!$condition) throw new RuntimeException($label);
}
$sample = ['title' => 'Test <script>', 'name' => 'Juan & María', 'intro' => '<img src=x onerror=alert(1)>', 'highlight' => '<code>', 'details' => ['Purpose' => '<b>Test</b>'], 'action_url' => 'javascript:alert(1)'];
$html = SmartServeEmailTemplate::render($sample);
checkEmail(strpos($html, 'WELCOME TO') !== false && strpos($html, '>SmartServe<') !== false, 'Brand header');
checkEmail(substr_count($html, 'cid:smartserve-school-logo') === 2, 'Seal in header and footer');
checkEmail(substr_count($html, 'De La Salle Araneta University') >= 2, 'University header and footer');
checkEmail(strpos($html, '<script>') === false && strpos($html, '<img src=x') === false && strpos($html, '<b>Test</b>') === false, 'User-controlled content escaped');
checkEmail(strpos($html, 'href="javascript:') === false, 'Unsafe button URL excluded');
checkEmail(strpos(SmartServeEmailTemplate::text($sample), 'Juan & María') !== false, 'Plain text preserves accented names');
$mail = smartserve_mailer();
$mail->addAddress('student@example.com');
$mail->Subject = 'Preview | SmartServe';
$mail->Body = $html;
$mail->AltBody = SmartServeEmailTemplate::text($sample);
checkEmail($mail->preSend(), 'Compile MIME without SMTP');
$mime = $mail->getSentMIMEMessage();
checkEmail(strpos($mime, 'Content-ID: <smartserve-school-logo>') !== false, 'Logo embedded in MIME');
checkEmail(strpos($mime, 'Content-Type: text/plain') !== false && strpos($mime, 'Content-Type: text/html') !== false, 'HTML and plain text MIME parts');

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
$db = new mysqli('127.0.0.1', 'root', '', 'smartserve');
$db->set_charset('utf8mb4');
$db->query("SET time_zone = '+08:00'");
$testDb = 'smartserve_email_test_' . bin2hex(random_bytes(6));
checkEmail((bool)preg_match('/^smartserve_email_test_[a-f0-9]{12}$/', $testDb), 'Disposable database name validated');
$db->query("CREATE DATABASE `$testDb` CHARACTER SET utf8mb4");
try {
    $db->query('SET FOREIGN_KEY_CHECKS=0');
    foreach (['accounts', 'offices', 'appointments', 'appointment_offices', 'blocked_schedules', 'blocked_schedule_appointments', 'announcements'] as $table) {
        $create = $db->query("SHOW CREATE TABLE smartserve.`$table`")->fetch_row()[1];
        $create = preg_replace('/^CREATE TABLE `[^`]+`/', "CREATE TABLE `$testDb`.`$table`", $create, 1);
        $db->query($create);
    }
    $db->select_db($testDb);
    $db->query('SET FOREIGN_KEY_CHECKS=1');
    $db->query("INSERT INTO accounts (id,fname,mname,lname,position,uname,pword,contact,address,photo,access,status) VALUES (1,'Juan','', 'Dela Cruz','Student','juan@example.com','test','09123456789','123 Test Street','default.jpg',3,1),(2,'Ana','','Santos','Student','ana@example.com','test','09123456789','123 Test Street','default.jpg',3,1),(3,'Staff','','Member','Staff','staff@example.com','test','09123456789','123 Test Street','default.jpg',2,1)");
    $db->query("INSERT INTO offices (id,office_name,status) VALUES (1,'Registrar',1),(2,'Cashier',1)");
    $captured = [];
    $emails = new SmartServeEmails($db, function ($row) use (&$captured) { $captured[] = $row; return true; });
    $modelReflection = new ReflectionClass(Model::class);
    $model = $modelReflection->newInstanceWithoutConstructor();
    foreach (['conn' => $db, 'emails' => $emails] as $name => $value) {
        $property = $modelReflection->getProperty($name);
        $property->setAccessible(true);
        $property->setValue($model, $value);
    }
    $count = function () use ($db) { return (int)$db->query('SELECT COUNT(*) FROM email_outbox')->fetch_row()[0]; };
    $future = date('Y-m-d', strtotime('+1 day'));
    checkEmail($model->addAppointment('TEST-BOOKED', 1, [1,2], $future, '09:00 AM', 'Transcript request'), 'Booking saved');
    checkEmail($count() === 1, 'Booking email queued once');
    $body = $db->query('SELECT html_body FROM email_outbox ORDER BY id LIMIT 1')->fetch_row()[0];
    checkEmail(strpos($body, 'Registrar, Cashier') !== false && strpos($body, 'TEST-BOOKED') !== false, 'Booking includes all offices and tracking');
    checkEmail(!$model->addAppointment('INVALID', 1, [999], $future, '09:00 AM', 'Test') && $count() === 1, 'Failed booking has no email');
    $db->query("CREATE TRIGGER reject_test_office BEFORE INSERT ON appointment_offices FOR EACH ROW BEGIN IF NEW.tracking_no = 'TEST-ROLLBACK' AND NEW.office_id = 2 THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Test office failure'; END IF; END");
    checkEmail(!$model->addAppointment('TEST-ROLLBACK', 2, [1,2], $future, '09:00 AM', 'Test'), 'Mid-booking office failure reported');
    checkEmail($count() === 1 && (int)$db->query("SELECT COUNT(*) FROM appointments WHERE tracking_no='TEST-ROLLBACK'")->fetch_row()[0] === 0 && (int)$db->query("SELECT COUNT(*) FROM appointment_offices WHERE tracking_no='TEST-ROLLBACK'")->fetch_row()[0] === 0, 'Failed multi-office booking rolls back every row and email');
    $db->query('DROP TRIGGER reject_test_office');
    checkEmail($model->cancelAppointment('TEST-BOOKED', 1), 'Cancellation saved');
    checkEmail(!$model->cancelAppointment('TEST-BOOKED', 1) && $count() === 2, 'Repeated cancellation has no duplicate');
    checkEmail((int)$db->query("SELECT COUNT(*) FROM appointment_offices WHERE tracking_no='TEST-BOOKED' AND status=4")->fetch_row()[0] === 2, 'Cancellation removes all pending office queues');
    $today = date('Y-m-d');
    checkEmail($model->addAppointment('TEST-QUEUE', 1, [1], $today, '09:00 AM', 'Test purpose'), 'Today booking saved');
    $db->query("UPDATE appointments SET appointment_time=DATE_FORMAT(NOW(),'%H:%i:%s') WHERE tracking_no='TEST-QUEUE'");
    checkEmail($model->verifyAndQueueQR('TEST-QUEUE', 1)['status'] === 'success', 'Staff QR check-in');
    $n = $count();
    checkEmail($model->verifyAndQueueQR('TEST-QUEUE', 1)['status'] === 'error' && $count() === $n, 'Repeat staff QR produces no duplicate');
    $id = (int)$db->query("SELECT id FROM appointment_offices WHERE tracking_no='TEST-QUEUE'")->fetch_row()[0];
    checkEmail($model->updateQueueStatus($id, 2, '', 'Window 1'), 'Queue called');
    $n = $count();
    checkEmail($model->updateQueueStatus($id, 2, '', 'Window 1') && $count() === $n, 'Repeat queue call produces no duplicate');
    $body = $db->query('SELECT html_body FROM email_outbox ORDER BY id DESC LIMIT 1')->fetch_row()[0];
    checkEmail(strpos($body, 'Window 1') !== false && strpos($body, 'Q-') !== false, 'Queue email includes number and window');
    checkEmail($model->updateQueueStatus($id, 3, 'Request released'), 'Completion saved');
    checkEmail($model->addAppointment('TEST-KIOSK', 2, [2], $today, '10:00 AM', 'Payment'), 'Kiosk test booking');
    $db->query("UPDATE appointments SET appointment_time=DATE_FORMAT(NOW(),'%H:%i:%s') WHERE tracking_no='TEST-KIOSK'");
    checkEmail($model->verifyQRCode('TEST-KIOSK', 2)['status'] === 'success', 'Kiosk QR check-in');
    $n = $count();
    checkEmail($model->verifyQRCode('TEST-KIOSK', 2)['status'] === 'success' && $count() === $n, 'Already checked in kiosk has no duplicate');
    $walkin = $model->addWalkInAppointment(2, null, 1, 'Walk-in request');
    checkEmail($walkin['status'] === 'success' && $count() === $n + 1, 'Registered walk-in email');
    $n = $count();
    checkEmail($model->addWalkInAppointment(null, 'Guest Visitor', 1, 'Guest visit')['status'] === 'success' && $count() === $n, 'Guest without email does not receive guessed email');
    $walkinId = (int)$db->query("SELECT ao.id FROM appointment_offices ao JOIN appointments a ON a.tracking_no=ao.tracking_no WHERE a.student_id=2 AND a.is_walkin=1 ORDER BY ao.id DESC LIMIT 1")->fetch_row()[0];
    checkEmail($model->updateQueueStatus($walkinId, 4, 'Staff marked as No Show.') && $count() === $n + 1, 'No-show notification');
    $n = $count();
    $model->updateAccountStatus(0, 2);
    checkEmail($count() === $n + 1, 'Deactivation email');
    $model->updateAccountStatus(0, 2);
    checkEmail($count() === $n + 1, 'Repeated account status has no duplicate');
    $model->updateAccountStatus(1, 2);
    checkEmail($model->updatePassword('ana@example.com', 'ValidPass1!'), 'Password reset saved');
    checkEmail($count() === $n + 3, 'Reactivation and password security emails');
    $emails->account(1, 'welcome');
    checkEmail($count() === $n + 4, 'Welcome email');
    checkEmail($model->addAppointment('TEST-BLOCKED', 1, [1], $future, '11:00 AM', 'Blocked purpose'), 'Blocked test booking');
    $apptId = (int)$db->query("SELECT id FROM appointments WHERE tracking_no='TEST-BLOCKED'")->fetch_row()[0];
    $blockId = $model->createBlockedSchedule(1, $future, null, null, 'full_day', 'Office maintenance', 'Please select another schedule.', 3);
    checkEmail($blockId > 0, 'Blocked schedule saved');
    $blockCount = (int)$db->query('SELECT COUNT(*) FROM blocked_schedules')->fetch_row()[0];
    foreach ([['10:00', '07:00'], ['10:00', '10:00'], ['07:30', '09:00'], ['06:00', '09:00'], ['07:00', '17:00']] as [$start, $end]) {
        checkEmail($model->createBlockedSchedule(1, $future, $start, $end, 'time_range', 'Invalid range', '', 3) === 0, 'Model rejects invalid blocked time range');
    }
    checkEmail((int)$db->query('SELECT COUNT(*) FROM blocked_schedules')->fetch_row()[0] === $blockCount, 'Invalid ranges do not save a block');
    checkEmail($model->createBlockedSchedule(1, date('Y-m-d', strtotime('+3 days')), '07:00', '16:00', 'time_range', 'Valid hourly range', '', 3) > 0, 'Model saves valid hourly range');
    $purposeId = $model->createBlockedSchedule(2, $future, null, null, 'specific_purpose', 'Purpose unavailable', '', 3, 'Payment');
    checkEmail($purposeId > 0 && count($model->getPendingAppointmentsForBlock(2, $future, null, null, 'specific_purpose', 'Payment')) === 0, 'Purpose-specific schedule block supported by schema');
    checkEmail($model->addBlockedScheduleAppointment($blockId, $apptId, 'TEST-BLOCKED', 1, 1, $future, '11:00 AM'), 'Affected appointment notice saved');
    $blockedId = (int)$db->insert_id;
    // Notification insertion changes connection insert_id, so obtain the actual blocked row.
    $blockedId = (int)$db->query("SELECT id FROM blocked_schedule_appointments WHERE tracking_no='TEST-BLOCKED'")->fetch_row()[0];
    $body = $db->query('SELECT html_body FROM email_outbox ORDER BY id DESC LIMIT 1')->fetch_row()[0];
    checkEmail(strpos($body, 'Office maintenance') !== false, 'Blocked email includes reason');
    $newDate = date('Y-m-d', strtotime('+2 days'));
    checkEmail($model->submitBlockedAppointmentResponse($blockedId, 1, 'reschedule', $newDate, '01:00 PM')['ok'], 'Reschedule saved');
    $n = $count();
    checkEmail(!$model->submitBlockedAppointmentResponse($blockedId, 1, 'reschedule', $newDate, '01:00 PM')['ok'] && $count() === $n, 'Repeat reschedule prevented');
    $body = $db->query('SELECT html_body FROM email_outbox ORDER BY id DESC LIMIT 1')->fetch_row()[0];
    checkEmail(strpos($body, date('F j, Y', strtotime($newDate))) !== false && strpos($body, '01:00 PM') !== false, 'Reschedule email uses new schedule');
    checkEmail($model->addAppointment('TEST-BLOCK-CANCEL', 2, [2], $future, '02:00 PM', 'Cancel test'), 'Blocked cancellation booking');
    $cancelApptId = (int)$db->query("SELECT id FROM appointments WHERE tracking_no='TEST-BLOCK-CANCEL'")->fetch_row()[0];
    $model->addBlockedScheduleAppointment($blockId, $cancelApptId, 'TEST-BLOCK-CANCEL', 2, 2, $future, '02:00 PM');
    $cancelRowId = (int)$db->query("SELECT id FROM blocked_schedule_appointments WHERE tracking_no='TEST-BLOCK-CANCEL'")->fetch_row()[0];
    $n = $count();
    checkEmail(!$model->submitBlockedAppointmentResponse($cancelRowId, 1, 'cancel')['ok'] && $count() === $n, 'Wrong student cannot respond or notify');
    checkEmail($model->submitBlockedAppointmentResponse($cancelRowId, 2, 'cancel')['ok'] && $count() === $n + 1, 'Blocked cancellation notification');
    $n = $count();
    checkEmail(!$model->submitBlockedAppointmentResponse($cancelRowId, 2, 'cancel')['ok'] && $count() === $n, 'Repeated blocked cancellation prevented');
    $advisoryId = $model->addAnnouncement('Office update', 'Please bring your school ID.', 'default.jpg', '', date('Y-m-d H:i:s'), 3, 1);
    checkEmail($advisoryId > 0, 'Advisory returns saved ID');
    checkEmail($model->notifyAnnouncementEmail($advisoryId, 'none') === 0 && $count() === $n, 'None disables advisory emails');
    checkEmail($model->notifyAnnouncementEmail($advisoryId, 'all_students') === 2 && $count() === $n + 2, 'Advisory targets active students only');
    $model->notifyAnnouncementEmail($advisoryId, 'all_students');
    checkEmail($count() === $n + 2, 'Repeated advisory has no duplicate');
    $db->query("INSERT INTO appointments (tracking_no,student_id,appointment_date,appointment_time,notes,status,sms_notif) VALUES ('TEST-REMINDER',1,CURDATE(),DATE_FORMAT(DATE_ADD(NOW(),INTERVAL 10 MINUTE),'%h:%i %p'),'Reminder purpose',0,1),('TEST-EXPIRED',1,CURDATE(),DATE_FORMAT(DATE_SUB(NOW(),INTERVAL 20 MINUTE),'%H:%i:%s'),'Late arrival',0,0)");
    $db->query("INSERT INTO appointment_offices (tracking_no,office_id,status) VALUES ('TEST-REMINDER',1,0),('TEST-EXPIRED',1,0)");
    $n = $count();
    $emails->reminders();
    checkEmail($count() === $n + 1, 'Reminder independent of SMS sent flag');
    $emails->reminders();
    checkEmail($count() === $n + 1, 'Repeat reminder deduplicated');
    $model->autoCancelExpiredAppointments(1);
    checkEmail($count() === $n + 2, 'Late expiration email');
    $model->autoCancelExpiredAppointments(1);
    checkEmail($count() === $n + 2, 'Repeat auto-expiration produces no duplicate');
    $db->query("UPDATE appointments SET status=3 WHERE tracking_no='TEST-REMINDER'");
    checkEmail(!$emails->enqueue('invalid-email', 'sample@edu', ['title' => 'Invalid email']) && $count() === $n + 2, 'Invalid recipient rejected');
    $queued = $count();
    $stats = $emails->dispatch(100);
    checkEmail($stats['sent'] === $queued - 1 && $stats['skipped'] === 1 && $stats['failed'] === 0, 'Capture delivery sends events and skips stale reminder');
    checkEmail(count($captured) === $stats['sent'], 'No external transport used');
    checkEmail($emails->dispatch(100)['sent'] === 0, 'Delivered rows not resent');
    $emails->enqueue('retry-test', 'juan@example.com', ['title' => 'Retry notification', 'intro' => 'Test']);
    $failure = new SmartServeEmails($db, function () { return false; });
    $stats = $failure->dispatch();
    checkEmail($stats['failed'] === 1, 'Transport failure retained');
    $row = $db->query("SELECT status,attempts,available_at > NOW() AS is_delayed FROM email_outbox WHERE status='failed'")->fetch_assoc();
    checkEmail($row['status'] === 'failed' && (int)$row['attempts'] === 1 && (int)$row['is_delayed'] === 1, 'Retry has backoff and attempt count');
    checkEmail($failure->dispatch()['failed'] === 0, 'Backoff respected');
    $db->query("UPDATE email_outbox SET available_at=NOW() WHERE status='failed'");
    checkEmail($emails->dispatch()['sent'] === 1, 'Retry succeeds when due');
    $emails->enqueue('claim-test', 'juan@example.com', ['title' => 'Claim notification']);
    $db->query("UPDATE email_outbox SET status='processing',attempts=1,updated_at=NOW() WHERE status='pending'");
    checkEmail($emails->dispatch()['sent'] === 0, 'Another active worker claim is respected');
    $db->query("UPDATE email_outbox SET updated_at=DATE_SUB(NOW(),INTERVAL 11 MINUTE) WHERE status='processing'");
    checkEmail($emails->dispatch()['sent'] === 1, 'Stale worker claim recovered');
    $legacyUrl = 'http://localhost/smartserve/student/booking-details?id=LEGACY&view=details#ticket';
    $liveUrl = 'https://dlsua-smartserve.site/student/booking-details?id=LEGACY&view=details#ticket';
    $legacyHtml = str_replace(htmlspecialchars($liveUrl, ENT_QUOTES), htmlspecialchars($legacyUrl, ENT_QUOTES), SmartServeEmailTemplate::render(['title' => 'Legacy appointment update', 'action_label' => 'View your appointment', 'action_url' => $liveUrl]));
    $legacyHtml = str_replace('</body>', '<p>Or open: <a href="' . htmlspecialchars($legacyUrl, ENT_QUOTES) . '">' . htmlspecialchars($legacyUrl, ENT_QUOTES) . '</a></p></body>', $legacyHtml);
    $legacyText = "Legacy appointment update\n\nView your appointment: $legacyUrl\n\nSmartServe";
    $legacyIds = [];
    foreach (['pending', 'failed', 'sent'] as $legacyStatus) {
        $emails->enqueue('legacy-link-' . $legacyStatus, 'juan@example.com', ['title' => 'Legacy appointment update']);
        $legacyId = (int)$db->insert_id;
        $legacyIds[$legacyStatus] = $legacyId;
        $update = $db->prepare('UPDATE email_outbox SET html_body=?,text_body=?,status=? WHERE id=?');
        $update->bind_param('sssi', $legacyHtml, $legacyText, $legacyStatus, $legacyId);
        $update->execute();
        $update->close();
    }
    checkEmail($emails->dispatch()['sent'] === 2, 'Queued pending and failed messages are upgraded and delivered once');
    foreach (['pending', 'failed'] as $legacyStatus) {
        $legacy = $db->query('SELECT html_body,text_body FROM email_outbox WHERE id=' . $legacyIds[$legacyStatus])->fetch_assoc();
        checkEmail(str_contains($legacy['html_body'], htmlspecialchars($liveUrl, ENT_QUOTES)) && !str_contains($legacy['html_body'], 'localhost') && !str_contains($legacy['html_body'], 'Or open:'), 'Stored queued HTML has only its updated action button');
        checkEmail(!str_contains($legacy['text_body'], 'http'), 'Stored queued plain text has no raw action URL');
    }
    checkEmail($db->query('SELECT html_body FROM email_outbox WHERE id=' . $legacyIds['sent'])->fetch_row()[0] === $legacyHtml, 'Already delivered messages are not changed or resent');
    foreach ($captured as $message) {
        checkEmail(substr_count($message['html_body'], 'cid:smartserve-school-logo') === 2 && strpos($message['html_body'], 'WELCOME TO') !== false, 'Every captured email shares header/footer');
        checkEmail(!str_contains($message['html_body'], 'localhost') && !str_contains($message['text_body'], 'http'), 'Every captured notification excludes localhost and raw text links');
        checkEmail(!str_contains(strip_tags($message['html_body']), 'https://dlsua-smartserve.site'), 'No visible domain below notification buttons');
        preg_match_all('/href="([^"]+)"/', $message['html_body'], $links);
        foreach ($links[1] as $link) checkEmail(str_starts_with($link, 'https://dlsua-smartserve.site/'), 'Every notification button uses the deployed site');
    }
    echo "Passed $checks email notification checks. No email or SMS sent.\n";
} finally {
    $db->select_db('smartserve');
    $db->query("DROP DATABASE `$testDb`");
    $db->close();
}
