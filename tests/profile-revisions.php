<?php
if (PHP_SAPI !== 'cli') { http_response_code(403); exit('CLI only.'); }
putenv('SMARTSERVE_MAIL_AUTOSEND=0');
require_once __DIR__ . '/../global/model.php';
require_once __DIR__ . '/../global/priority.php';
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
$db = new mysqli('localhost', 'root', '', 'smartserve');
$db->set_charset('utf8mb4');
$child = ($argv[1] ?? '') === '--child';
$testDb = $child ? ($argv[2] ?? '') : 'smartserve_profile_test_' . bin2hex(random_bytes(6));
if (!preg_match('/\Asmartserve_profile_test_[a-f0-9]{12}\z/', $testDb)) throw new RuntimeException('Invalid test database');
if (!$child) {
    $db->query("CREATE DATABASE `$testDb` CHARACTER SET utf8mb4");
    $db->query("CREATE TABLE `$testDb`.accounts LIKE smartserve.accounts");
}
$db->select_db($testDb);
$model = (new ReflectionClass(Model::class))->newInstanceWithoutConstructor();
$connection = new ReflectionProperty(Model::class, 'conn');
$connection->setAccessible(true); $connection->setValue($model, $db);

if ($child) {
    $role = $argv[3]; $mode = $argv[4];
    $id = ['admin' => 1, 'office' => 2, 'student' => 3][$role];
    $account = $model->displayAccountDetails($id)[0];
    foreach (['fname', 'mname', 'lname', 'contact', 'address'] as $field) ${'account_' . $field} = $account[$field];
    $priorityAccount = $account;
    $_SESSION = ['sess' => $id];
    $_POST = ['save_profile' => '', 'new_fname' => $account['fname'], 'new_mname' => $account['mname'], 'new_lname' => $account['lname'], 'new_contact' => $account['contact'], 'new_address' => $account['address']];
    $_FILES = [];
    if ($mode === 'whitespace') { $_POST['new_fname'] = '  ' . $account['fname'] . '  '; $_POST['new_address'] .= '  '; }
    if ($mode === 'changed') $_POST['new_address'] = '456 Revised Street';
    if ($mode === 'priority') $_POST['account_priority'] = 'Senior Citizen';
    if ($mode === 'id-upload') $_FILES['pwd_id'] = ['error' => UPLOAD_ERR_PARTIAL, 'tmp_name' => '', 'size' => 10];
    // Execute the production POST handler using the isolated model and loaded account.
    // Authentication/bootstrap and HTML are excluded; no live database connection is constructed.
    $source = file_get_contents(__DIR__ . '/../' . $role . '/update-profile.php');
    $start = strpos($source, "if (isset(\$_POST['save_profile']))");
    $end = strpos($source, '?>', $start);
    if ($start === false || $end === false) throw new RuntimeException('Profile handler was not found');
    eval(substr($source, $start, $end - $start));
    exit;
}

$checks = 0;
function profileCheck($ok, $label) { global $checks; $checks++; if (!$ok) throw new RuntimeException($label); }
function runProfileHandler($role, $mode) {
    global $testDb;
    $pipes = [];
    $process = proc_open([PHP_BINARY, __FILE__, '--child', $testDb, $role, $mode], [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
    if (!is_resource($process)) throw new RuntimeException('Unable to start profile worker');
    fclose($pipes[0]); $output = stream_get_contents($pipes[1]); $error = stream_get_contents($pipes[2]);
    fclose($pipes[1]); fclose($pipes[2]);
    if (proc_close($process) !== 0 || $error !== '') throw new RuntimeException('Profile worker failed: ' . $error);
    if (!preg_match('/SmartServe\.notifyFromServer\((\{.*?\})\);/s', $output, $match)) throw new RuntimeException('No profile notification returned');
    return json_decode($match[1], true, 512, JSON_THROW_ON_ERROR);
}

try {
    $db->query("INSERT INTO accounts(id,fname,mname,lname,position,uname,pword,contact,address,photo,access,status) VALUES
        (1,'Ana','','Admin','Administrator','admin@example.com','test','09123456781','123 Original Street','default',1,1),
        (2,'Juan','','Staff','Office','staff@example.com','test','09123456782','123 Original Street','default',2,1),
        (3,'Maria','','Student','Student','student@example.com','test','09123456783','123 Original Street','default',3,1)");
    foreach (['admin' => 1, 'office' => 2, 'student' => 3] as $role => $id) {
        foreach (['unchanged', 'whitespace'] as $mode) {
            $before = $db->query("SELECT * FROM accounts WHERE id=$id")->fetch_assoc();
            $notice = runProfileHandler($role, $mode);
            profileCheck($notice['type'] === 'info' && $notice['message'] === 'No changes to save.', "$role $mode never reports success");
            profileCheck($before === $db->query("SELECT * FROM accounts WHERE id=$id")->fetch_assoc(), "$role $mode leaves account unchanged");
        }
        $notice = runProfileHandler($role, 'changed');
        profileCheck($notice['type'] === 'success', "$role actual edit reports success");
        profileCheck($db->query("SELECT address FROM accounts WHERE id=$id")->fetch_row()[0] === '456 Revised Street', "$role actual edit is persisted");
        $notice = runProfileHandler($role, 'unchanged');
        profileCheck($notice['type'] === 'info', "$role repeat save is unchanged");
    }
    $notice = runProfileHandler('student', 'priority');
    profileCheck($notice['type'] === 'success', 'Priority-only edit reports success');
    profileCheck($db->query('SELECT priority_type FROM accounts WHERE id=3')->fetch_row()[0] === 'Senior Citizen', 'Priority-only edit is persisted');
    $idPath = str_repeat('a', 48) . '.png';
    $db->query("UPDATE accounts SET priority_type='PWD',pwd_status='approved',pwd_id_path='$idPath' WHERE id=3");
    $before = $db->query('SELECT * FROM accounts WHERE id=3')->fetch_assoc();
    $notice = runProfileHandler('student', 'unchanged');
    profileCheck($notice['type'] === 'info', 'Existing PWD ID is not treated as a new upload');
    profileCheck($before === $db->query('SELECT * FROM accounts WHERE id=3')->fetch_assoc(), 'Unchanged PWD profile preserves approved status');
    $notice = runProfileHandler('student', 'id-upload');
    profileCheck($notice['type'] === 'danger' && str_contains($notice['message'], 'upload'), 'An attempted ID replacement reaches upload validation instead of the no-op path');
    profileCheck($before === $db->query('SELECT * FROM accounts WHERE id=3')->fetch_assoc(), 'Failed ID upload preserves the profile');
    echo "Passed $checks profile revision checks. Disposable accounts only.\n";
} finally {
    $db->select_db('smartserve'); $db->query("DROP DATABASE `$testDb`");
}
