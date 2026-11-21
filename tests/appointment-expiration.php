<?php
// Deterministic arrival-deadline tests. Disposable database and capture-only mail delivery.
if (PHP_SAPI !== 'cli') { http_response_code(403); exit('CLI only.'); }
putenv('SMARTSERVE_MAIL_AUTOSEND=0');
require_once __DIR__ . '/../global/model.php';
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
$checks = 0;
function arrivalCheck($condition, $label) {
    global $checks;
    $checks++;
    if (!$condition) throw new RuntimeException($label);
}
$db = new mysqli('127.0.0.1', 'root', '', 'smartserve');
$db->set_charset('utf8mb4');
$db->query("SET time_zone = '+08:00'");
$testDb = 'smartserve_arrival_test_' . bin2hex(random_bytes(6));
arrivalCheck((bool)preg_match('/^smartserve_arrival_test_[a-f0-9]{12}$/', $testDb), 'Validated disposable database name');
$db->query("CREATE DATABASE `$testDb` CHARACTER SET utf8mb4");
try {
    $db->query('SET FOREIGN_KEY_CHECKS=0');
    foreach (['accounts', 'offices', 'appointments', 'appointment_offices', 'announcements', 'blocked_schedules', 'blocked_schedule_appointments'] as $table) {
        $create = $db->query("SHOW CREATE TABLE smartserve.`$table`")->fetch_row()[1];
        $create = preg_replace('/^CREATE TABLE `[^`]+`/', "CREATE TABLE `$testDb`.`$table`", $create, 1);
        $db->query($create);
    }
    $db->select_db($testDb);
    $db->query('SET FOREIGN_KEY_CHECKS=1');
    $db->query("INSERT INTO accounts (id,fname,mname,lname,position,uname,pword,photo,access,status) VALUES (1,'Juan','','Dela Cruz','Student','juan@example.com','test','default.jpg',3,1)");
    $db->query("INSERT INTO offices (id,office_name,status) VALUES (1,'Registrar',1),(2,'Cashier',1)");
    $captured = [];
    $emails = new SmartServeEmails($db, function ($row) use (&$captured) { $captured[] = $row; return true; });
    $reflection = new ReflectionClass(Model::class);
    $model = $reflection->newInstanceWithoutConstructor();
    foreach (['conn' => $db, 'emails' => $emails] as $name => $value) {
        $property = $reflection->getProperty($name);
        $property->setAccessible(true);
        $property->setValue($model, $value);
    }
    $today = date('Y-m-d');
    $yesterday = date('Y-m-d', strtotime('-1 day'));
    $clock = function ($time) use ($db, $today) { $db->query("SET timestamp = UNIX_TIMESTAMP('$today $time')"); };
    $insert = function ($tracking, $time, $status = 0, $walkin = 0, $office = 1, $date = null) use ($db, $today) {
        $date = $date ?? $today;
        $stmt = $db->prepare("INSERT INTO appointments (tracking_no,student_id,appointment_date,appointment_time,notes,is_walkin,status) VALUES (?,1,?,?,'Document request',?,0)");
        $stmt->bind_param('sssi', $tracking, $date, $time, $walkin);
        $stmt->execute();
        $stmt->close();
        $stmt = $db->prepare("INSERT INTO appointment_offices (tracking_no,office_id,status,queue_number) VALUES (?,?,?,'ONL-001')");
        $stmt->bind_param('sii', $tracking, $office, $status);
        $stmt->execute();
        $stmt->close();
    };
    $state = function ($tracking, $office = 1) use ($db) {
        $stmt = $db->prepare('SELECT ao.id,ao.status,a.status AS booking_status FROM appointment_offices ao JOIN appointments a ON ao.tracking_no=a.tracking_no WHERE ao.tracking_no=? AND ao.office_id=?');
        $stmt->bind_param('si', $tracking, $office);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        return $row;
    };
    $count = function () use ($db) { return (int)$db->query('SELECT COUNT(*) FROM email_outbox')->fetch_row()[0]; };
    $clock('07:09:59');
    $insert('TEST-DEADLINE', '07:00 AM');
    arrivalCheck($model->autoCancelExpiredAppointments(1) === 0 && (int)$state('TEST-DEADLINE')['status'] === 0, '7 AM booking survives until 7:09:59');
    arrivalCheck($count() === 0, 'No email before the ten-minute deadline');
    $insert('TEST-WAITING', '07:00 AM', 1);
    $insert('TEST-SERVING', '07:00 AM', 2);
    $insert('TEST-COMPLETED', '07:00 AM', 3);
    $insert('TEST-WALKIN', '07:00 AM', 0, 1);
    $insert('TEST-FUTURE', '08:00 AM');
    $insert('TEST-WITHIN', '07:01 AM');
    $insert('TEST-24H', '07:00:00');
    $insert('TEST-HHMM', '07:00');
    $insert('TEST-PREVIOUS-DAY', '07:00 AM', 0, 0, 1, $yesterday);
    $insert('TEST-MULTI', '07:00 AM');
    $db->query("INSERT INTO appointment_offices (tracking_no,office_id,status,queue_number) VALUES ('TEST-MULTI',2,1,'CAS-001')");
    $insert('TEST-OFFICE-TWO', '07:00 AM', 0, 0, 2);
    $clock('07:10:00');
    arrivalCheck($model->autoCancelExpiredAppointments(1) === 5, 'Cancel eligible office visits exactly at 7:10 AM');
    foreach (['TEST-DEADLINE', 'TEST-24H', 'TEST-HHMM', 'TEST-PREVIOUS-DAY'] as $tracking) {
        arrivalCheck((int)$state($tracking)['status'] === 4 && (int)$state($tracking)['booking_status'] === 3, 'Office and student ticket cancelled: ' . $tracking);
    }
    foreach (['TEST-WAITING' => 1, 'TEST-SERVING' => 2, 'TEST-COMPLETED' => 3, 'TEST-WALKIN' => 0, 'TEST-FUTURE' => 0, 'TEST-WITHIN' => 0] as $tracking => $status) {
        arrivalCheck((int)$state($tracking)['status'] === $status, 'Protect arrived/walk-in/not-yet-due visit: ' . $tracking);
    }
    arrivalCheck((int)$state('TEST-OFFICE-TWO', 2)['status'] === 0, 'Office-scoped check does not cancel another office');
    arrivalCheck((int)$state('TEST-MULTI')['status'] === 4 && (int)$state('TEST-MULTI', 2)['status'] === 1 && (int)$state('TEST-MULTI')['booking_status'] === 0, 'Keep another office visit and overall ticket active');
    arrivalCheck($count() === 5, 'One cancellation email per changed office visit');
    arrivalCheck($model->autoCancelExpiredAppointments(1) === 0 && $count() === 5, 'Repeat checks create no duplicate emails');
    $body = $db->query('SELECT html_body FROM email_outbox ORDER BY id LIMIT 1')->fetch_row()[0];
    arrivalCheck(strpos($body, 'Your appointment has been cancelled') !== false && strpos($body, '10 minutes') !== false && strpos($body, '15 minutes') === false, 'Email explains cancellation and ten-minute rule');
    arrivalCheck(strpos($body, 'TEST-DEADLINE') !== false && strpos($body, '07:00 AM') !== false && strpos($body, 'Registrar') !== false, 'Email includes tracking, schedule, and affected office');
    $logo = 'data:image/png;base64,' . base64_encode(file_get_contents(__DIR__ . '/../assets/img/icon.png'));
    file_put_contents(__DIR__ . '/fixtures/email-cancelled-preview.html', str_replace('cid:smartserve-school-logo', $logo, $body));
    $late = $model->verifyAndQueueQR('TEST-DEADLINE', 1);
    arrivalCheck($late['status'] === 'error' && stripos($late['msg'], 'cancelled') !== false, 'Staff scan cannot revive cancelled booking');
    $late = $model->verifyQRCode('TEST-DEADLINE', 1);
    arrivalCheck($late['status'] === 'error' && stripos($late['msg'], 'cancelled') !== false, 'Kiosk scan cannot revive cancelled booking');
    arrivalCheck(!$model->updateQueueStatus($state('TEST-DEADLINE')['id'], 2, '', 'Window 1') && (int)$state('TEST-DEADLINE')['status'] === 4, 'Stale serving action cannot revive cancelled booking');
    $insert('TEST-SCAN-IN-TIME', '07:01 AM');
    arrivalCheck($model->verifyAndQueueQR('TEST-SCAN-IN-TIME', 1)['status'] === 'success', 'Check-in inside grace period succeeds');
    $insert('TEST-LATE-SCAN', '07:00 AM');
    $before = $count();
    arrivalCheck($model->verifyQRCode('TEST-LATE-SCAN', 1)['status'] === 'error' && (int)$state('TEST-LATE-SCAN')['status'] === 4 && $count() === $before + 1, 'Kiosk independently cancels and emails overdue arrivals');
    $db->query("CREATE TRIGGER reject_cancel BEFORE UPDATE ON appointments FOR EACH ROW BEGIN IF OLD.tracking_no='TEST-ROLLBACK' AND NEW.status=3 THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Test cancellation failure'; END IF; END");
    $insert('TEST-ROLLBACK', '07:00 AM');
    $before = $count();
    arrivalCheck($model->autoCancelExpiredAppointments(1) === 0 && (int)$state('TEST-ROLLBACK')['status'] === 0 && $count() === $before, 'Failed cancellation rolls back status and email');
    $db->query('DROP TRIGGER reject_cancel');
    arrivalCheck($model->autoCancelExpiredAppointments(1) === 1, 'Failed cancellation can be retried');
    $insert('TEST-WORKER', '07:00 AM', 0, 0, 2);
    $result = $model->runEmailNotifications();
    arrivalCheck((int)$state('TEST-WORKER', 2)['status'] === 4 && (int)$state('TEST-OFFICE-TWO', 2)['status'] === 4, 'Worker cancels across offices without a dashboard request');
    arrivalCheck($result['failed'] === 0 && $result['sent'] === $count() && count($captured) === $count(), 'Worker delivers captured cancellation emails');
    arrivalCheck($model->runEmailNotifications()['sent'] === 0, 'Repeat worker does not resend');
    $insert('TEST-LEGACY-CANCELLED', '07:00 AM', 0, 0, 1, $yesterday);
    $db->query("UPDATE appointments SET status=3 WHERE tracking_no='TEST-LEGACY-CANCELLED'");
    $model->getOfficeHistoryByDate(1, $yesterday);
    arrivalCheck((int)$state('TEST-LEGACY-CANCELLED')['status'] === 4, 'History reconciles pending office visit on a legacy cancelled ticket');
    $insert('TEST-HISTORY', '07:00 AM', 0, 0, 1, $yesterday);
    $history = $model->getOfficeHistoryByDate(1, $yesterday);
    $historyRow = array_values(array_filter($history, fn($row) => $row['tracking_no'] === 'TEST-HISTORY'))[0];
    arrivalCheck((int)$historyRow['status'] === 4, 'History returns overdue pending visit as cancelled immediately');
    $insert('TEST-REPORTS', '07:00 AM', 0, 0, 2, $yesterday);
    $report = $model->getAdminReports(2, $yesterday, $yesterday, 4);
    arrivalCheck(count(array_filter($report['records'], fn($row) => $row['tracking_no'] === 'TEST-REPORTS')) === 1, 'Admin cancelled filter includes newly expired booking');
    $insert('TEST-STUDENT-LIST', '07:00 AM', 0, 0, 1, $yesterday);
    $studentRows = $model->getStudentAppointments(1);
    $studentRow = array_values(array_filter($studentRows, fn($row) => $row['tracking_no'] === 'TEST-STUDENT-LIST'))[0];
    arrivalCheck((int)$studentRow['status'] === 3, 'Student booking list updates overdue pending ticket');
    arrivalCheck((int)$model->getAppointmentDetails('TEST-STUDENT-LIST', 1)['status'] === 3, 'Student booking details reflect cancelled ticket');
    arrivalCheck((int)$state('TEST-WAITING')['status'] === 1 && (int)$state('TEST-SERVING')['status'] === 2 && (int)$state('TEST-WALKIN')['status'] === 0, 'History reads preserve checked-in students and walk-ins');
    echo "Passed $checks arrival deadline checks. No email or SMS sent.\n";
} finally {
    $db->query('SET timestamp = 0');
    $db->select_db('smartserve');
    $db->query("DROP DATABASE `$testDb`");
    $db->close();
}
