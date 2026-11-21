<?php
// Verify office-level reporting with a mixed-outcome booking. No live data or messages.
if (PHP_SAPI !== 'cli') { http_response_code(403); exit('CLI only.'); }
putenv('SMARTSERVE_MAIL_AUTOSEND=0');
require_once __DIR__ . '/../global/model.php';
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
$db = new mysqli('127.0.0.1', 'root', '', 'smartserve');
$db->set_charset('utf8mb4');
$db->query("SET time_zone='+08:00'");
$testDb = 'smartserve_reports_test_' . bin2hex(random_bytes(6));
if (!preg_match('/\Asmartserve_reports_test_[a-f0-9]{12}\z/', $testDb)) throw new RuntimeException('Invalid test database');
$db->query("CREATE DATABASE `$testDb` CHARACTER SET utf8mb4");
$checks = 0;
function serviceReportCheck($ok, $label) { global $checks; $checks++; if (!$ok) throw new RuntimeException($label); }
try {
    foreach (['accounts','offices','appointments','appointment_offices','blocked_schedules','blocked_schedule_appointments'] as $table) {
        $db->query("CREATE TABLE `$testDb`.`$table` LIKE smartserve.`$table`");
    }
    $db->select_db($testDb);
    $db->query("INSERT INTO accounts(id,fname,lname,position,uname,pword,photo,access,status) VALUES(1,'Test','Student','Student','student@example.com','test','default',3,1)");
    $db->query("INSERT INTO offices(id,office_name,status) VALUES(1,'Cashier',1),(2,'Clinic',1),(3,'IT Services',1)");
    $day = date('Y-m-d', strtotime('+1 day'));
    while ((int)date('N', strtotime($day)) > 5) $day = date('Y-m-d', strtotime($day . ' +1 day'));
    $db->query("INSERT INTO appointments(tracking_no,student_id,appointment_date,appointment_time,notes,status) VALUES
        ('MIXED-REPORT',1,'$day','09:00 AM','Mixed services',0),
        ('PENDING-REPORT',1,'$day','10:00 AM','Pending service',0),
        ('SERVING-REPORT',1,'$day','11:00 AM','Serving service',0)");
    $db->query("INSERT INTO appointment_offices(tracking_no,office_id,status,office_notes) VALUES
        ('MIXED-REPORT',1,3,'Completed payment'),('MIXED-REPORT',2,4,'System: Auto-cancelled (no check-in within 10 minutes)'),
        ('MIXED-REPORT',3,1,''),('PENDING-REPORT',2,0,''),('SERVING-REPORT',3,2,'')");
    $model = (new ReflectionClass(Model::class))->newInstanceWithoutConstructor();
    $captured = [];
    $emails = new SmartServeEmails($db, function($row) use (&$captured) { $captured[] = $row; return true; });
    foreach (['conn'=>$db,'emails'=>$emails] as $name=>$value) {
        $property = new ReflectionProperty(Model::class, $name);
        $property->setAccessible(true); $property->setValue($model, $value);
    }
    $all = $model->getAdminReports(null, $day, $day);
    serviceReportCheck(count($all['records']) === 5, 'One report row per office visit, not one per booking');
    serviceReportCheck($all['metrics']['total_completed'] === 1 && $all['metrics']['total_noshow'] === 1, 'Completed and cancelled office visits count separately');
    $expectedOffice = [0=>'Clinic',1=>'IT Services',2=>'IT Services',3=>'Cashier',4=>'Clinic'];
    foreach ($expectedOffice as $status=>$office) {
        $report = $model->getAdminReports(null, $day, $day, $status);
        serviceReportCheck(count($report['records']) === 1 && (int)$report['records'][0]['status'] === $status && $report['records'][0]['office_name'] === $office, "Status $status filters by office status");
        $graph = $model->getGraphData(null, $day, $day, $status);
        serviceReportCheck(array_sum($graph['days_data']) === 1 && array_sum($graph['hours_data']) === 1, "Graph includes status $status and agrees with report");
    }
    $graph = $model->getGraphData(null, $day, $day);
    serviceReportCheck(array_sum($graph['days_data']) === 5, 'All-status graph includes every matching service visit');
    serviceReportCheck(count($model->getAdminReports(1, $day, $day, 4)['records']) === 0, 'Another office cancellation never appears under completed Cashier visit');
    serviceReportCheck(array_sum($model->getGraphData(2, $day, $day, 4)['days_data']) === 1, 'Cancelled service graph obeys office filter');
    serviceReportCheck(count($model->getOfficeHistoryByDate(1, $day)) === 1 && (int)$model->getOfficeHistoryByDate(1, $day)[0]['status'] === 3, 'Cashier history retains own completed result');
    serviceReportCheck(count($model->getOfficeHistoryByDate(2, $day)) === 2, 'Clinic history includes own cancelled and pending visits only');
    serviceReportCheck($graph['days_labels'] === ['Monday','Tuesday','Wednesday','Thursday','Friday'], 'Graphs only offer Monday through Friday');
    for ($i=0; $i<12; $i++) {
        $tracking = 'VOLUME-' . $i;
        $db->query("INSERT INTO appointments(tracking_no,student_id,appointment_date,appointment_time,notes,status) VALUES('$tracking',1,'$day','09:00 AM','Volume test',2)");
        $db->query("INSERT INTO appointment_offices(tracking_no,office_id,status) VALUES('$tracking',1,3)");
    }
    $volume = $model->getGraphData(1, $day, $day, 3);
    serviceReportCheck(max($volume['days_data']) === 13 && max($volume['hours_data']) === 13, 'Daily and hourly counts retain all thirteen visits instead of capping at one');
    $saturday = date('Y-m-d', strtotime('next Saturday', strtotime($day)));
    $sunday = date('Y-m-d', strtotime($saturday . ' +1 day'));
    foreach (['WEEKEND-SAT'=>$saturday, 'WEEKEND-SUN'=>$sunday] as $tracking=>$weekend) {
        $db->query("INSERT INTO appointments(tracking_no,student_id,appointment_date,appointment_time,notes,status) VALUES('$tracking',1,'$weekend','09:00 AM','Historical weekend visit',2)");
        $db->query("INSERT INTO appointment_offices(tracking_no,office_id,status) VALUES('$tracking',1,3)");
    }
    $weekendGraph = $model->getGraphData(null, $saturday, $sunday);
    serviceReportCheck(array_sum($weekendGraph['days_data']) === 0 && array_sum($weekendGraph['hours_data']) === 0, 'Weekend records contribute to neither graph');
    serviceReportCheck(count($model->getAdminReports(null, $saturday, $sunday)['records']) === 2, 'Historical weekend records remain available in the records report');
    $allDates = $model->getGraphData(1, $day, $sunday, 3);
    serviceReportCheck(array_sum($allDates['days_data']) === 13 && array_sum($allDates['hours_data']) === 13, 'Mixed weekday/weekend date range includes only weekday volume');
    serviceReportCheck($captured === [], 'Reporting sends no real notifications');
    echo "Passed $checks service report checks. Disposable database only.\n";
} finally {
    $db->select_db('smartserve');
    $db->query("DROP DATABASE `$testDb`");
    $db->close();
}
