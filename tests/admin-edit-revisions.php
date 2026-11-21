<?php
// Uses synthetic records in a disposable database; never changes live accounts.
if (PHP_SAPI !== 'cli') { http_response_code(403); exit('CLI only.'); }
putenv('SMARTSERVE_MAIL_AUTOSEND=0');
require_once __DIR__ . '/../global/model.php';
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
$db = new mysqli('127.0.0.1', 'root', '', 'smartserve');
$db->set_charset('utf8mb4');
$testDb = 'smartserve_admin_edit_test_' . bin2hex(random_bytes(6));
if (!preg_match('/\Asmartserve_admin_edit_test_[a-f0-9]{12}\z/', $testDb)) throw new RuntimeException('Invalid test database');
$db->query("CREATE DATABASE `$testDb` CHARACTER SET utf8mb4");
$checks = 0;
function adminEditCheck($ok, $label) {
    global $checks;
    $checks++;
    if (!$ok) throw new RuntimeException($label);
}
function applyRevisionMigration($db) {
    try {
        $db->multi_query(file_get_contents(__DIR__ . '/../database/review-revisions-2026-10-31.sql'));
        do {
            if ($result = $db->store_result()) $result->free();
            if (!$db->more_results()) break;
        } while ($db->next_result());
    } catch (Throwable $e) {
        $db->rollback();
        throw $e;
    }
}
function runAdminEditHandler($model, $account, $overrides = []) {
    $_POST = array_merge(['edit_user' => '', 'edit_id' => $account['id'],
        'fname' => $account['fname'], 'mname' => $account['mname'], 'lname' => $account['lname'],
        'uname' => $account['uname'], 'contact' => $account['contact'], 'address' => $account['address'],
        'window' => $account['year'], 'office' => $account['course']], $overrides);
    $source = file_get_contents(__DIR__ . '/../admin/account-offices.php');
    $start = strpos($source, "if (isset(\$_POST['edit_user']))");
    $end = strpos($source, '?>', $start);
    if ($start === false || $end === false) throw new RuntimeException('Missing production edit handler');
    ob_start();
    try { eval(substr($source, $start, $end - $start)); $output = ob_get_contents(); }
    finally { ob_end_clean(); }
    if (!preg_match('/SmartServe\.notifyFromServer\((\{.*?\})\);/s', $output, $match)) throw new RuntimeException('Missing edit notification');
    return json_decode($match[1], true, 512, JSON_THROW_ON_ERROR);
}
try {
    foreach (['accounts', 'offices', 'appointments', 'appointment_offices'] as $table) {
        $db->query("CREATE TABLE `$testDb`.`$table` LIKE smartserve.`$table`");
    }
    $db->select_db($testDb);
    $db->query("INSERT INTO offices(id,office_name,status) VALUES(1,'Cashier',1),(2,'Clinic',1)");
    $db->query("INSERT INTO accounts(id,fname,mname,lname,position,uname,pword,contact,address,photo,access,status,year,course,date_created) VALUES
        (1,'Juan','','de la Cruz','Staff','staff@dlsua.edu.ph','preserved-staff-hash','09123456781','12 McArthur Ave, BLDG B','default',2,1,'Window 1','1','2026-01-01'),
        (2,'Ana','','Admin','Admin','admin@dlsua.edu.ph','preserved-admin-hash','09123456782','1 Original Street','default',1,1,NULL,NULL,'2026-01-01'),
        (3,'Maria','','Student','Student','student@dlsua.edu.ph','preserved-student-hash','09123456783','2 Original Street','default',3,1,NULL,NULL,'2026-01-01')");
    $model = (new ReflectionClass(Model::class))->newInstanceWithoutConstructor();
    $property = new ReflectionProperty(Model::class, 'conn');
    $property->setAccessible(true); $property->setValue($model, $db);
    $getAccount = fn($id) => $db->query('SELECT * FROM accounts WHERE id=' . (int)$id)->fetch_assoc();
    $original = $getAccount(1);
    $args = [1, $original['fname'], $original['mname'], $original['lname'], $original['uname'], $original['contact'], $original['address'], $original['year'], $original['course']];
    $db->query("CREATE TRIGGER reject_unchanged_write BEFORE UPDATE ON accounts FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='No-op should not write'");
    adminEditCheck($model->editAccount(...$args) === 'unchanged', 'Unchanged model edit returns a distinct result without attempting SQL UPDATE');
    $notice = runAdminEditHandler($model, $original);
    adminEditCheck($notice['type'] === 'info' && $notice['message'] === 'No changes to save.', 'Production staff handler never shows success for unchanged data');
    adminEditCheck($getAccount(1) === $original, 'No-op preserves legacy capitalization, every field and timestamp');
    $notice = runAdminEditHandler($model, $original, ['fname' => ' Juan ', 'address' => ' ' . $original['address'] . ' ']);
    adminEditCheck($notice['type'] === 'info' && $getAccount(1) === $original, 'Trimmed form values are still recognized as unchanged');
    $db->query('DROP TRIGGER reject_unchanged_write');
    $notice = runAdminEditHandler($model, $original, ['address' => '34 McArthur Ave, BLDG C']);
    adminEditCheck($notice['type'] === 'success', 'Actual staff edit reports success');
    adminEditCheck($getAccount(1)['address'] === '34 McArthur Ave, BLDG C', 'Actual staff edit persists without recasing');
    $notice = runAdminEditHandler($model, $getAccount(1));
    adminEditCheck($notice['type'] === 'info', 'Repeated save returns unchanged');
    $notice = runAdminEditHandler($model, $getAccount(1), ['edit_id' => 99999]);
    adminEditCheck($notice['type'] === 'danger' && str_contains($notice['message'], 'no longer exists'), 'Missing staff account returns an error instead of success');

    $insertTicket = function ($tracking, $parentStatus, $visitStatuses) use ($db) {
        $stmt = $db->prepare("INSERT INTO appointments(tracking_no,student_id,appointment_date,appointment_time,status) VALUES(?,3,'2026-10-31','09:00 AM',?)");
        $stmt->bind_param('si', $tracking, $parentStatus); $stmt->execute(); $stmt->close();
        foreach ($visitStatuses as $offset => $status) {
            $office = $offset + 1;
            $stmt = $db->prepare('INSERT INTO appointment_offices(tracking_no,office_id,status) VALUES(?,?,?)');
            $stmt->bind_param('sii', $tracking, $office, $status); $stmt->execute(); $stmt->close();
        }
    };
    $insertTicket('TEST-COMPLETED', 0, [3]);
    $insertTicket('TEST-CANCELLED', 0, [4]);
    $insertTicket('TEST-MIXED', 0, [3,4]);
    $insertTicket('TEST-ACTIVE', 0, [3,1]);
    $insertTicket('TEST-OFFICELESS', 0, []);
    $insertTicket('TEST-LEGACY-CANCELLED-COMPLETE', 3, [3]);
    $beforeAccounts = $db->query('SELECT * FROM accounts ORDER BY id')->fetch_all(MYSQLI_ASSOC);
    applyRevisionMigration($db);
    adminEditCheck($getAccount(1)['uname'] === 'staff@dlsau.edu.ph', 'Migration corrects staff email');
    adminEditCheck($getAccount(2)['uname'] === 'admin@dlsau.edu.ph', 'Migration corrects admin email');
    adminEditCheck($getAccount(3)['uname'] === 'student@dlsua.edu.ph', 'Migration leaves student email unchanged');
    foreach ($beforeAccounts as $before) {
        $after = $getAccount($before['id']);
        unset($before['uname'], $after['uname']);
        adminEditCheck($before === $after, 'Migration preserves password hash and all other account fields: ' . $before['id']);
    }
    $states = array_column($db->query('SELECT tracking_no,status FROM appointments')->fetch_all(MYSQLI_ASSOC), 'status', 'tracking_no');
    foreach (['TEST-COMPLETED'=>2, 'TEST-CANCELLED'=>3, 'TEST-MIXED'=>2, 'TEST-ACTIVE'=>0, 'TEST-OFFICELESS'=>0, 'TEST-LEGACY-CANCELLED-COMPLETE'=>2] as $tracking => $expected) {
        adminEditCheck((int)$states[$tracking] === $expected, 'Migration reconciles only terminal visits: ' . $tracking);
    }
    $snapshot = [$db->query('SELECT * FROM accounts ORDER BY id')->fetch_all(MYSQLI_ASSOC), $db->query('SELECT * FROM appointments ORDER BY id')->fetch_all(MYSQLI_ASSOC)];
    applyRevisionMigration($db);
    adminEditCheck($snapshot === [$db->query('SELECT * FROM accounts ORDER BY id')->fetch_all(MYSQLI_ASSOC), $db->query('SELECT * FROM appointments ORDER BY id')->fetch_all(MYSQLI_ASSOC)], 'Migration is idempotent');

    $db->query("UPDATE accounts SET uname='admin@dlsua.edu.ph' WHERE id=2");
    $db->query("INSERT INTO accounts(id,fname,lname,position,uname,pword,photo,access,status) VALUES(4,'Existing','Admin','Admin','admin@dlsau.edu.ph','untouched','default',1,1)");
    $db->query("UPDATE appointments SET status=0 WHERE tracking_no='TEST-COMPLETED'");
    $collisionSnapshot = [$db->query('SELECT * FROM accounts ORDER BY id')->fetch_all(MYSQLI_ASSOC), $db->query('SELECT * FROM appointments ORDER BY id')->fetch_all(MYSQLI_ASSOC)];
    $collisionRejected = false;
    try { applyRevisionMigration($db); } catch (mysqli_sql_exception $e) { $collisionRejected = (int)$e->getCode() === 1062; }
    adminEditCheck($collisionRejected, 'Unique email collision rejects migration');
    adminEditCheck($collisionSnapshot === [$db->query('SELECT * FROM accounts ORDER BY id')->fetch_all(MYSQLI_ASSOC), $db->query('SELECT * FROM appointments ORDER BY id')->fetch_all(MYSQLI_ASSOC)], 'Failed migration leaves accounts and appointment states unchanged');
    echo "Passed $checks admin edit and database migration checks. No live records changed.\n";
} finally {
    $db->rollback();
    $db->select_db('smartserve');
    $db->query("DROP DATABASE `$testDb`");
    $db->close();
}
