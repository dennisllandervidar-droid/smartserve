<?php
// Production-schema integration checks in a disposable database; capture-only email transport.
if (PHP_SAPI !== 'cli') { http_response_code(403); exit('CLI only.'); }
putenv('SMARTSERVE_MAIL_AUTOSEND=0');
require_once __DIR__ . '/../global/model.php';
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

function bookingTestModel($db, &$captured) {
    $emails = new SmartServeEmails($db, function ($row) use (&$captured) { $captured[] = $row; return true; });
    $reflection = new ReflectionClass(Model::class);
    $model = $reflection->newInstanceWithoutConstructor();
    foreach (['conn' => $db, 'emails' => $emails] as $key => $value) {
        $property = $reflection->getProperty($key);
        $property->setAccessible(true);
        $property->setValue($model, $value);
    }
    return $model;
}

// Each concurrent request uses a separate MySQL connection and the real booking method.
if (($argv[1] ?? '') === '--race') {
    $testDb = $argv[2] ?? '';
    if (!preg_match('/\Asmartserve_booking_test_[a-f0-9]{12}\z/', $testDb)) throw new RuntimeException('Invalid disposable database');
    $db = new mysqli('127.0.0.1', 'root', '', $testDb);
    $db->set_charset('utf8mb4');
    $db->query("SET time_zone='+08:00'");
    $db->query('SET innodb_lock_wait_timeout=5');
    $today = date('Y-m-d');
    $db->query("SET timestamp=UNIX_TIMESTAMP('$today 06:00:00')");
    $captured = [];
    $model = bookingTestModel($db, $captured);
    echo "READY\n";
    flush();
    echo $model->addAppointment($argv[3], 3, [3], $today, '10:00 AM', 'Concurrent booking test') ? "SAVED\n" : "BLOCKED\n";
    $db->close();
    exit;
}

$checks = 0;
function bookingCheck($ok, $label) {
    global $checks;
    $checks++;
    if (!$ok) throw new RuntimeException($label);
}
$db = new mysqli('127.0.0.1', 'root', '', 'smartserve');
$db->set_charset('utf8mb4');
$db->query("SET time_zone='+08:00'");
$testDb = 'smartserve_booking_test_' . bin2hex(random_bytes(6));
if (!preg_match('/\Asmartserve_booking_test_[a-f0-9]{12}\z/', $testDb)) throw new RuntimeException('Invalid disposable database');
$db->query("CREATE DATABASE `$testDb` CHARACTER SET utf8mb4");
$processes = [];
try {
    $db->query('SET FOREIGN_KEY_CHECKS=0');
    foreach (['accounts', 'offices', 'appointments', 'appointment_offices', 'blocked_schedules', 'blocked_schedule_appointments'] as $table) {
        $create = $db->query("SHOW CREATE TABLE smartserve.`$table`")->fetch_row()[1];
        $db->query(preg_replace('/^CREATE TABLE `[^`]+`/', "CREATE TABLE `$testDb`.`$table`", $create, 1));
    }
    $db->select_db($testDb);
    $db->query('SET FOREIGN_KEY_CHECKS=1');
    $db->query("INSERT INTO accounts (id,fname,mname,lname,position,uname,pword,photo,access,status,priority_type) VALUES
        (1,'Juan','','Santos','Student','juan@example.com','test','default.jpg',3,1,'N/A'),
        (2,'Ana','','Reyes','Student','ana@example.com','test','default.jpg',3,1,'N/A'),
        (3,'Race','','Student','Student','race@example.com','test','default.jpg',3,1,'N/A')");
    $db->query("INSERT INTO offices (id,office_name,status) VALUES (1,'Registrar',1),(2,'Cashier',1),(3,'Clinic',1)");
    $captured = [];
    $model = bookingTestModel($db, $captured);
    $today = date('Y-m-d');
    $tomorrow = date('Y-m-d', strtotime('+1 day'));
    $db->query("SET timestamp=UNIX_TIMESTAMP('$today 06:00:00')");
    $state = function ($tracking, $office = 2) use ($db) {
        $stmt = $db->prepare('SELECT ao.id,ao.status,ao.time_started,ao.time_completed,a.status AS booking_status FROM appointment_offices ao JOIN appointments a ON ao.tracking_no=a.tracking_no WHERE ao.tracking_no=? AND ao.office_id=?');
        $stmt->bind_param('si', $tracking, $office);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        return $row;
    };
    $status = fn($tracking, $student = 1) => (int)$model->getAppointmentDetails($tracking, $student)['status'];
    $mailCount = fn() => (int)$db->query('SELECT COUNT(*) FROM email_outbox')->fetch_row()[0];
    $bookingCount = fn() => (int)$db->query('SELECT COUNT(*) FROM appointments')->fetch_row()[0];

    bookingCheck($model->addAppointment('TEST-CASHIER', 1, [2], $today, '08:00 AM', 'Payment'), 'Cashier booking succeeds');
    bookingCheck($model->addAppointment('TEST-REGISTRAR', 1, [1], $today, '09:00 AM', 'Document request'), 'Another service on the same day succeeds after Cashier');
    bookingCheck($model->checkExistingBooking(1, $today, [2]), 'Active duplicate is detected for the selected office');
    bookingCheck(!$model->checkExistingBooking(1, $today, [3]), 'An unrelated service is still available');
    $before = [$bookingCount(), $mailCount()];
    bookingCheck(!$model->addAppointment('TEST-DUPLICATE', 1, [2], $today, '10:00 AM', 'Duplicate payment'), 'Direct model submission cannot bypass same-service duplicate guard');
    bookingCheck([$bookingCount(), $mailCount()] === $before, 'Rejected duplicate creates no booking or notification');
    bookingCheck($status('TEST-CASHIER') === 0, 'Unscanned student ticket is Pending');
    bookingCheck($model->verifyQRCode('TEST-CASHIER', 2)['status'] === 'success', 'Cashier kiosk scan succeeds');
    bookingCheck($status('TEST-CASHIER') === 1 && (int)$state('TEST-CASHIER')['booking_status'] === 0, 'Student sees In Progress while parent remains active after scan');
    $cashierId = $state('TEST-CASHIER')['id'];
    bookingCheck($model->updateQueueStatus($cashierId, 2, '', 'Window 1'), 'Cashier calls the student');
    bookingCheck($status('TEST-CASHIER') === 1 && $state('TEST-CASHIER')['time_started'] !== null, 'Serving remains In Progress and records its start');
    $before = $mailCount();
    bookingCheck(!$model->cancelAppointment('TEST-CASHIER', 1), 'A stale student cancellation cannot cancel a visit being served');
    bookingCheck((int)$state('TEST-CASHIER')['booking_status'] === 0 && (int)$state('TEST-CASHIER')['status'] === 2 && $mailCount() === $before, 'Rejected cancellation preserves the active parent, staff visit, and notification queue');
    bookingCheck($model->updateQueueStatus($cashierId, 3, 'Payment received'), 'Cashier completes service');
    bookingCheck($status('TEST-CASHIER') === 2 && (int)$state('TEST-CASHIER')['booking_status'] === 2, 'Completion updates both student details and stored parent');
    bookingCheck($state('TEST-CASHIER')['time_completed'] !== null, 'Completion timestamp is retained for reports');
    bookingCheck(!$model->checkExistingBooking(1, $today, [2]), 'Completed Cashier visit no longer blocks another visit');
    bookingCheck($model->addAppointment('TEST-CASHIER-AGAIN', 1, [2], $today, '10:00 AM', 'Another payment'), 'Same-service booking is allowed after completion');
    bookingCheck($model->cancelAppointment('TEST-CASHIER-AGAIN', 1), 'Student cancellation succeeds');
    bookingCheck($status('TEST-CASHIER-AGAIN') === 3, 'Cancelled ticket is shown as Cancelled');
    bookingCheck($model->addAppointment('TEST-CASHIER-REBOOK', 1, [2], $today, '11:00 AM', 'Rebook payment'), 'Same-service booking is allowed after cancellation');
    bookingCheck($model->addAppointment('TEST-DEDUP-OFFICES', 2, [3, 3, '3'], $tomorrow, '09:00 AM', 'Clinic visit'), 'Duplicate submitted office IDs are accepted once');
    bookingCheck((int)$db->query("SELECT COUNT(*) FROM appointment_offices WHERE tracking_no='TEST-DEDUP-OFFICES'")->fetch_row()[0] === 1, 'Duplicate IDs do not create duplicate office visits');
    bookingCheck($model->getAppointmentDetails('TEST-CASHIER', 2) === [], 'Student cannot retrieve another student ticket');
    bookingCheck(count($model->getStudentAppointments(2)) === 1, 'Student history contains only their own tickets');

    $insert = function ($tracking, $officeStatuses) use ($db, $today) {
        $stmt = $db->prepare("INSERT INTO appointments (tracking_no,student_id,appointment_date,appointment_time,notes,status) VALUES (?,1,?,'08:00 AM','Synthetic state test',0)");
        $stmt->bind_param('ss', $tracking, $today);
        $stmt->execute();
        $stmt->close();
        foreach ($officeStatuses as $office => $officeStatus) {
            $stmt = $db->prepare('INSERT INTO appointment_offices (tracking_no,office_id,status) VALUES (?,?,?)');
            $stmt->bind_param('sii', $tracking, $office, $officeStatus);
            $stmt->execute();
            $stmt->close();
        }
    };
    $cases = [
        'TEST-LEGACY-PENDING' => [[2 => 0], 0],
        'TEST-LEGACY-QUEUED' => [[2 => 1], 1],
        'TEST-LEGACY-SERVING' => [[2 => 2], 1],
        'TEST-LEGACY-COMPLETED' => [[2 => 3], 2],
        'TEST-LEGACY-CANCELLED' => [[2 => 4], 3],
        'TEST-MIXED-TERMINAL' => [[1 => 3, 2 => 4], 2],
        'TEST-ALL-CANCELLED' => [[1 => 4, 2 => 4], 3],
        'TEST-PARTIAL-PENDING' => [[1 => 3, 2 => 0], 1],
        'TEST-PARTIAL-QUEUED' => [[1 => 3, 2 => 1], 1],
        'TEST-PARTIAL-CANCELLED' => [[1 => 4, 2 => 0], 0]
    ];
    foreach ($cases as $tracking => [$visits, $expected]) {
        $insert($tracking, $visits);
        bookingCheck($status($tracking) === $expected, 'Details derive office status despite stale legacy parent: ' . $tracking);
    }
    $history = array_column($model->getStudentAppointments(1), null, 'tracking_no');
    foreach ($cases as $tracking => [$visits, $expected]) {
        bookingCheck((int)$history[$tracking]['status'] === $expected, 'History matches details: ' . $tracking);
    }
    bookingCheck((int)$history['TEST-CASHIER']['status'] === 2, 'Cashier completion appears in student history');

    $insert('TEST-MULTI-COMPLETE', [1 => 2, 2 => 2]);
    bookingCheck($model->updateQueueStatus($state('TEST-MULTI-COMPLETE', 1)['id'], 3, 'Done at Registrar'), 'First office can complete multi-service booking');
    bookingCheck((int)$state('TEST-MULTI-COMPLETE')['booking_status'] === 0 && $status('TEST-MULTI-COMPLETE') === 1, 'Other active service keeps ticket In Progress');
    bookingCheck($model->updateQueueStatus($state('TEST-MULTI-COMPLETE')['id'], 4, 'No show'), 'Remaining office can close its visit');
    bookingCheck((int)$state('TEST-MULTI-COMPLETE')['booking_status'] === 2 && $status('TEST-MULTI-COMPLETE') === 2, 'Mixed completed/cancelled ticket finishes without losing completed service');

    $insert('TEST-MIXED-STUDENT-CANCEL', [1 => 3, 2 => 0]);
    bookingCheck($model->cancelAppointment('TEST-MIXED-STUDENT-CANCEL', 1), 'Student can cancel the pending remainder of a partially completed ticket');
    bookingCheck((int)$state('TEST-MIXED-STUDENT-CANCEL', 1)['status'] === 3 && (int)$state('TEST-MIXED-STUDENT-CANCEL')['status'] === 4 && (int)$state('TEST-MIXED-STUDENT-CANCEL')['booking_status'] === 2 && $status('TEST-MIXED-STUDENT-CANCEL') === 2, 'Partial student cancellation preserves completed service and synchronizes the parent as Completed');

    $insert('TEST-ROLLBACK-COMPLETE', [2 => 2]);
    $db->query("CREATE TRIGGER reject_completion BEFORE UPDATE ON appointments FOR EACH ROW BEGIN IF OLD.tracking_no='TEST-ROLLBACK-COMPLETE' AND NEW.status=2 THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Synthetic completion failure'; END IF; END");
    $before = $mailCount();
    bookingCheck(!$model->updateQueueStatus($state('TEST-ROLLBACK-COMPLETE')['id'], 3, 'Must roll back'), 'Parent synchronization failure rejects completion');
    bookingCheck((int)$state('TEST-ROLLBACK-COMPLETE')['status'] === 2 && (int)$state('TEST-ROLLBACK-COMPLETE')['booking_status'] === 0 && $state('TEST-ROLLBACK-COMPLETE')['time_completed'] === null, 'Failure rolls back office, parent, and completion time together');
    bookingCheck($mailCount() === $before, 'Failed completion queues no email');
    $db->query('DROP TRIGGER reject_completion');
    bookingCheck($model->updateQueueStatus($state('TEST-ROLLBACK-COMPLETE')['id'], 3, 'Retry succeeded'), 'Completion can safely be retried');
    $before = $mailCount();
    bookingCheck($model->updateQueueStatus($state('TEST-ROLLBACK-COMPLETE')['id'], 3, 'Retry') && $mailCount() === $before, 'Repeated completion does not duplicate notifications');

    $insert('TEST-BLOCKED-SERVING', [2 => 2]);
    $blockId = $model->createBlockedSchedule(2, $today, null, null, 'full_day', 'Synthetic blocked schedule', '', 1);
    $appointmentId = (int)$db->query("SELECT id FROM appointments WHERE tracking_no='TEST-BLOCKED-SERVING'")->fetch_row()[0];
    bookingCheck($model->addBlockedScheduleAppointment($blockId, $appointmentId, 'TEST-BLOCKED-SERVING', 1, 2, $today, '08:00 AM'), 'Synthetic blocked schedule notice is created');
    $noticeId = (int)$db->query("SELECT id FROM blocked_schedule_appointments WHERE tracking_no='TEST-BLOCKED-SERVING'")->fetch_row()[0];
    $before = $mailCount();
    bookingCheck(!$model->submitBlockedAppointmentResponse($noticeId, 1, 'cancel')['ok'], 'A stale blocked-schedule cancellation cannot cancel a serving visit');
    bookingCheck((int)$state('TEST-BLOCKED-SERVING')['booking_status'] === 0 && (int)$state('TEST-BLOCKED-SERVING')['status'] === 2 && $mailCount() === $before, 'Rejected blocked cancellation leaves the appointment and messages unchanged');
    bookingCheck($model->updateQueueStatus($state('TEST-BLOCKED-SERVING')['id'], 3, 'Still able to finish'), 'Staff can finish after a rejected stale cancellation');

    $insert('TEST-MIXED-BLOCKED-CANCEL', [1 => 3, 2 => 0]);
    $appointmentId = (int)$db->query("SELECT id FROM appointments WHERE tracking_no='TEST-MIXED-BLOCKED-CANCEL'")->fetch_row()[0];
    bookingCheck($model->addBlockedScheduleAppointment($blockId, $appointmentId, 'TEST-MIXED-BLOCKED-CANCEL', 1, 2, $today, '08:00 AM'), 'Synthetic notice targets the pending remainder of a partially completed ticket');
    $noticeId = (int)$db->query("SELECT id FROM blocked_schedule_appointments WHERE tracking_no='TEST-MIXED-BLOCKED-CANCEL'")->fetch_row()[0];
    bookingCheck($model->submitBlockedAppointmentResponse($noticeId, 1, 'cancel')['ok'], 'Student can cancel a blocked pending visit after another service completed');
    bookingCheck((int)$state('TEST-MIXED-BLOCKED-CANCEL', 1)['status'] === 3 && (int)$state('TEST-MIXED-BLOCKED-CANCEL')['status'] === 4 && (int)$state('TEST-MIXED-BLOCKED-CANCEL')['booking_status'] === 2 && $status('TEST-MIXED-BLOCKED-CANCEL') === 2, 'Blocked partial cancellation preserves completed service and synchronizes the parent as Completed');

    // An unrelated overdue visit must not cause an inner transaction to commit
    // the reschedule before its final notice update succeeds.
    $insert('TEST-RESCHEDULE-ATOMIC', [1 => 0]);
    $db->query("UPDATE appointments SET appointment_date='$tomorrow' WHERE tracking_no='TEST-RESCHEDULE-ATOMIC'");
    $rescheduleBlock = $model->createBlockedSchedule(1, $tomorrow, null, null, 'full_day', 'Synthetic reschedule', '', 1);
    $appointmentId = (int)$db->query("SELECT id FROM appointments WHERE tracking_no='TEST-RESCHEDULE-ATOMIC'")->fetch_row()[0];
    bookingCheck($model->addBlockedScheduleAppointment($rescheduleBlock, $appointmentId, 'TEST-RESCHEDULE-ATOMIC', 1, 1, $tomorrow, '08:00 AM'), 'Synthetic reschedule notice is created');
    $noticeId = (int)$db->query("SELECT id FROM blocked_schedule_appointments WHERE tracking_no='TEST-RESCHEDULE-ATOMIC'")->fetch_row()[0];
    $insert('TEST-UNRELATED-OVERDUE', [1 => 0]);
    $yesterday = date('Y-m-d', strtotime('-1 day'));
    $db->query("UPDATE appointments SET appointment_date='$yesterday' WHERE tracking_no='TEST-UNRELATED-OVERDUE'");
    $db->query("CREATE TRIGGER reject_reschedule BEFORE UPDATE ON blocked_schedule_appointments FOR EACH ROW BEGIN IF OLD.tracking_no='TEST-RESCHEDULE-ATOMIC' AND NEW.action_status='rescheduled' THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Synthetic notice failure'; END IF; END");
    $before = $mailCount();
    $rescheduleDate = date('Y-m-d', strtotime('+2 days'));
    bookingCheck(!$model->submitBlockedAppointmentResponse($noticeId, 1, 'reschedule', $rescheduleDate, '10:00 AM')['ok'], 'Final notice failure rejects the reschedule');
    $savedSchedule = $db->query("SELECT appointment_date,appointment_time FROM appointments WHERE tracking_no='TEST-RESCHEDULE-ATOMIC'")->fetch_assoc();
    bookingCheck($savedSchedule === ['appointment_date' => $tomorrow, 'appointment_time' => '08:00 AM'] && (int)$state('TEST-RESCHEDULE-ATOMIC', 1)['status'] === 0 && $mailCount() === $before, 'Failed reschedule preserves original schedule, office state, and notifications');
    bookingCheck((int)$state('TEST-UNRELATED-OVERDUE', 1)['status'] === 0, 'Reschedule transaction does not run nested automatic cancellations');
    $db->query('DROP TRIGGER reject_reschedule');
    bookingCheck($model->autoCancelExpiredAppointments(1) === 1, 'Unrelated overdue visit is still cancelled by the ordinary outer expiration check');

    $db->query("CREATE TRIGGER reject_booking BEFORE INSERT ON appointment_offices FOR EACH ROW BEGIN IF NEW.tracking_no='TEST-ROLLBACK-BOOKING' AND NEW.office_id=3 THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Synthetic booking failure'; END IF; END");
    $before = [$bookingCount(), $mailCount()];
    bookingCheck(!$model->addAppointment('TEST-ROLLBACK-BOOKING', 2, [1, 3], $today, '10:00 AM', 'Rollback test'), 'Failure in a later office rejects the whole booking');
    bookingCheck([$bookingCount(), $mailCount()] === $before && (int)$db->query("SELECT COUNT(*) FROM appointment_offices WHERE tracking_no='TEST-ROLLBACK-BOOKING'")->fetch_row()[0] === 0, 'Failed booking leaves no parent, partial office visits or mail');
    $db->query('DROP TRIGGER reject_booking');

    // Hold the per-student lock until both competing processes are waiting.
    $db->begin_transaction();
    $db->query('SELECT id FROM accounts WHERE id=3 FOR UPDATE');
    for ($i = 0; $i < 2; $i++) {
        $pipes = [];
        $process = proc_open([PHP_BINARY, __FILE__, '--race', $testDb, 'TEST-RACE-' . $i], [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, __DIR__);
        if (!is_resource($process)) throw new RuntimeException('Unable to start concurrent booking worker');
        fclose($pipes[0]);
        $processes[] = ['process' => $process, 'stdout' => $pipes[1], 'stderr' => $pipes[2], 'output' => ''];
    }
    // Windows process pipes may ignore nonblocking mode. Read only the flushed
    // READY line, not the whole stream (which waits for the blocked child to exit).
    $ready = 0;
    foreach ($processes as &$worker) {
        $worker['output'] .= (string)fgets($worker['stdout']);
        if (str_contains($worker['output'], 'READY')) $ready++;
    }
    unset($worker);
    $db->commit();
    bookingCheck($ready === 2, 'Both separate requests reached the contested booking lock');
    $outputs = [];
    foreach ($processes as &$worker) {
        stream_set_blocking($worker['stdout'], true);
        $worker['output'] .= stream_get_contents($worker['stdout']);
        $errors = stream_get_contents($worker['stderr']);
        fclose($worker['stdout']);
        fclose($worker['stderr']);
        $exit = proc_close($worker['process']);
        bookingCheck($exit === 0, 'Concurrent worker exits successfully: ' . $errors);
        $outputs[] = $worker['output'];
    }
    unset($worker);
    $processes = [];
    bookingCheck(count(array_filter($outputs, fn($out) => str_contains($out, 'SAVED'))) === 1 && count(array_filter($outputs, fn($out) => str_contains($out, 'BLOCKED'))) === 1, 'Exactly one concurrent same-service booking succeeds: ' . json_encode($outputs));
    bookingCheck((int)$db->query('SELECT COUNT(*) FROM appointments WHERE student_id=3')->fetch_row()[0] === 1, 'Concurrent duplicate guard leaves exactly one stored booking');

    bookingCheck(count($captured) === 0, 'Autosend is disabled throughout integration checks');
    $emailProperty = new ReflectionProperty(Model::class, 'emails');
    $emailProperty->setAccessible(true);
    $delivery = $emailProperty->getValue($model)->dispatch();
    bookingCheck(count($captured) > 0 && $delivery['failed'] === 0, 'Queued notifications use the capture-only transport');
    echo "Passed $checks booking/status revision checks. No real email or SMS sent.\n";
} finally {
    $db->rollback();
    foreach ($processes as $worker) {
        if (is_resource($worker['process'])) proc_terminate($worker['process']);
        if (is_resource($worker['stdout'])) fclose($worker['stdout']);
        if (is_resource($worker['stderr'])) fclose($worker['stderr']);
        if (is_resource($worker['process'])) proc_close($worker['process']);
    }
    $db->query('SET timestamp=0');
    $db->select_db('smartserve');
    $db->query("DROP DATABASE `$testDb`");
    $db->close();
}
