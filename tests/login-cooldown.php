<?php
if (PHP_SAPI !== 'cli') { http_response_code(403); exit('CLI only.'); }
putenv('SMARTSERVE_MAIL_AUTOSEND=0');
require_once __DIR__ . '/../global/model.php';
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

function loginTestModel($db) {
    $reflection = new ReflectionClass(Model::class);
    $model = $reflection->newInstanceWithoutConstructor();
    $property = $reflection->getProperty('conn');
    $property->setAccessible(true);
    $property->setValue($model, $db);
    return $model;
}

$db = new mysqli('localhost', 'root', '', 'smartserve');
$db->set_charset('utf8mb4');
$db->query("SET time_zone='+08:00'");
$_SERVER['REMOTE_ADDR'] = '192.0.2.10';

if (($argv[1] ?? '') === 'success') {
    $testDb = $argv[2] ?? '';
    if (!preg_match('/\Asmartserve_login_test_[a-f0-9]{12}\z/', $testDb)) exit(1);
    $db->select_db($testDb);
    session_id('smartserve-login-test-' . bin2hex(random_bytes(6)));
    session_start();
    $oldSession = session_id();
    $_SESSION['login_attempt_username'] = 'known@example.com';
    $_SESSION['login_error'] = 'Old error';
    register_shutdown_function(function () use ($db, $oldSession) {
        $ok = ($_SESSION['sess'] ?? 0) === 1 && ($_SESSION['access'] ?? 0) === 3
            && session_id() !== $oldSession && !isset($_SESSION['login_attempt_username']) && !isset($_SESSION['login_error'])
            && (int)$db->query("SELECT COUNT(*) FROM login_attempts WHERE username='known@example.com'")->fetch_row()[0] === 0;
        session_destroy();
        echo $ok ? 'SUCCESS_RESET_PASSED' : 'SUCCESS_RESET_FAILED';
    });
    loginTestModel($db)->signIn('known@example.com', 'Test#12345');
    exit(1);
}

$testDb = 'smartserve_login_test_' . bin2hex(random_bytes(6));
$db->query("CREATE DATABASE `$testDb` CHARACTER SET utf8mb4");
$checks = 0;
function loginCheck($ok, $label) { global $checks; $checks++; if (!$ok) throw new RuntimeException($label); }
try {
    $db->query("CREATE TABLE `$testDb`.accounts LIKE smartserve.accounts");
    $db->query("CREATE TABLE `$testDb`.login_attempts LIKE smartserve.login_attempts");
    $db->select_db($testDb);
    $hash = password_hash('Test#12345', PASSWORD_DEFAULT);
    $stmt = $db->prepare("INSERT INTO accounts(id,fname,lname,position,uname,pword,contact,address,photo,access,status) VALUES(1,'Test','Student','Student','known@example.com',?,'09123456789','Test Street','default',3,1)");
    $stmt->bind_param('s', $hash); $stmt->execute(); $stmt->close();
    $model = loginTestModel($db);
    $baseTime = time();
    for ($i = 1; $i <= 5; $i++) {
        $db->query('SET timestamp=' . ($baseTime + $i * 10));
        loginCheck($model->signIn('  known@example.com  ', 'wrong') === 'Invalid username or password.', 'Wrong password is counted');
        $state = $model->getLoginAttemptStatus('known@example.com');
        loginCheck($state['attempts'] === $i && $state['remaining'] === 5 - $i, 'Correct remaining attempt count');
        loginCheck($state['locked'] === ($i === 5), 'Lock begins on fifth failure');
    }
    $deadline = $baseTime + 50 + 300;
    loginCheck($state['unlock_at'] === $deadline, 'Cooldown lasts exactly five minutes from fifth failure');
    $stateAfterRefresh = loginTestModel($db)->getLoginAttemptStatus('KNOWN@example.com');
    loginCheck($stateAfterRefresh === $state, 'Fresh model and email case change retain cooldown');
    $db->query('SET timestamp=' . ($baseTime + 60));
    loginCheck(str_contains($model->signIn('known@example.com', 'Test#12345'), 'cooldown'), 'Correct password cannot bypass active cooldown');
    loginCheck(str_contains($model->signIn('known@example.com', 'wrong'), 'cooldown'), 'Wrong password remains blocked');
    $record = (new ReflectionClass(Model::class))->getMethod('recordFailedLogin');
    $record->setAccessible(true);
    $record->invoke($model, 'known@example.com', '192.0.2.10');
    loginCheck($model->getLoginAttemptStatus('known@example.com')['unlock_at'] === $deadline, 'In-flight sixth failure does not extend cooldown');
    loginCheck((int)$db->query('SELECT COUNT(*) FROM login_attempts')->fetch_row()[0] === 1, 'Unique email/IP counter');
    loginCheck(!$model->getLoginAttemptStatus('known@example.com', '192.0.2.11')['locked'], 'Existing IP scope is preserved');
    loginCheck($model->getLoginAttemptStatus('another@example.com')['remaining'] === 5, 'Another email has its own counter');
    $db->query('SET timestamp=' . ($deadline - 1));
    $state = $model->getLoginAttemptStatus('known@example.com');
    loginCheck($state['locked'] && $state['unlock_at'] - $state['server_now'] === 1, 'Locked until last second');
    $db->query('SET timestamp=' . $deadline);
    $state = $model->getLoginAttemptStatus('known@example.com');
    loginCheck(!$state['locked'] && $state['attempts'] === 0 && $state['remaining'] === 5, 'Expiry restores all five attempts');
    loginCheck($model->signIn('known@example.com', 'wrong') === 'Invalid username or password.', 'New failure is allowed after expiry');
    loginCheck($model->getLoginAttemptStatus('known@example.com')['remaining'] === 4, 'Failure after cooldown does not immediately relock');
    $model->signIn('missing@example.com', 'wrong');
    loginCheck($model->getLoginAttemptStatus('missing@example.com')['remaining'] === 4, 'Unknown accounts receive the same attempt handling');
    $db->query('SET timestamp=0');
    $process = proc_open([PHP_BINARY, __FILE__, 'success', $testDb], [0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']], $pipes);
    fclose($pipes[0]); $output = stream_get_contents($pipes[1]); fclose($pipes[1]);
    $errors = stream_get_contents($pipes[2]); fclose($pipes[2]);
    loginCheck(proc_close($process) === 0 && $output === 'SUCCESS_RESET_PASSED', 'Successful login clears attempts and renews session: ' . $errors);
    echo "Passed $checks login cooldown checks. No live accounts or notifications changed.\n";
} finally {
    $db->query('SET timestamp=0'); $db->select_db('smartserve'); $db->query("DROP DATABASE `$testDb`");
}
