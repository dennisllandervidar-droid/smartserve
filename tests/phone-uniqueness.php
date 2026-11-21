<?php
if (PHP_SAPI !== 'cli') { http_response_code(403); exit('CLI only.'); }
putenv('SMARTSERVE_MAIL_AUTOSEND=0');
require_once __DIR__ . '/../global/model.php';
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
$db = new mysqli('localhost', 'root', '', 'smartserve');
$db->set_charset('utf8mb4');
$child = ($argv[1] ?? '') === '--child';
$testDb = $child ? ($argv[2] ?? '') : 'smartserve_phone_test_' . bin2hex(random_bytes(6));
if (!preg_match('/\Asmartserve_phone_test_[a-f0-9]{12}\z/', $testDb)) throw new RuntimeException('Invalid test database');
if (!$child) {
    $db->query("CREATE DATABASE `$testDb` CHARACTER SET utf8mb4");
    $db->query("CREATE TABLE `$testDb`.accounts LIKE smartserve.accounts");
}
$db->select_db($testDb);
$model = (new ReflectionClass(Model::class))->newInstanceWithoutConstructor();
foreach (['conn'=>$db, 'emails'=>new SmartServeEmails($db, fn()=>true)] as $name=>$value) {
    $property = new ReflectionProperty(Model::class, $name); $property->setAccessible(true); $property->setValue($model, $value);
}
if ($child) {
    $mode = $argv[3]; $phone = $argv[4];
    if (in_array($mode, ['profile', 'student'], true)) {
        echo "READY\n"; flush();
        $saved = $mode === 'student'
            ? $model->updateStudentProfile('Updated','','Student',$phone,'123 Street',(int)$argv[5],'Senior Citizen')
            : $model->updateAccountProfile('Updated','','Student','Student',$phone,'123 Street',(int)$argv[5]);
        echo $saved ? 'SAVED' : $model->getProfileUpdateError();
        exit;
    }
    $email = $mode . '.' . substr(sha1($phone),0,10) . '@example.com';
    $args = ['Test','','Student',$email,password_hash('Secure#123',PASSWORD_DEFAULT),$phone,'123 Street','Student','default',3,'1st Year','BS Computer Science',1];
    $mode === 'register' ? $model->addRegAccount(...$args) : $model->addAccount(...$args);
    exit;
}
$checks = 0;
function phoneCheck($ok, $label) { global $checks; $checks++; if (!$ok) throw new RuntimeException($label); }
function startPhoneChild($mode, $phone, $id=0) {
    global $testDb;
    $pipes=[];
    $process=proc_open([PHP_BINARY,__FILE__,'--child',$testDb,$mode,$phone,(string)$id],[0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],$pipes);
    if (!is_resource($process)) throw new RuntimeException('Unable to start test worker');
    fclose($pipes[0]); return [$process,$pipes];
}
function finishPhoneChild($child) {
    [$process,$pipes]=$child;
    $output=stream_get_contents($pipes[1]); $error=stream_get_contents($pipes[2]); fclose($pipes[1]); fclose($pipes[2]);
    if (proc_close($process)!==0 || $error!=='') throw new RuntimeException('Test worker failed: '.$error);
    return $output;
}
try {
    $db->query("INSERT INTO accounts(id,fname,lname,position,uname,pword,contact,address,photo,access,status) VALUES
    (1,'Juan','Student','Student','juan@example.com','test','09123456789','123 Street','default',3,1),
    (2,'Ana','Staff','Office','office@example.com','test','+639234567890','123 Street','default',2,1),
    (3,'Admin','Staff','Administrator','admin@example.com','test','09345678901','123 Street','default',1,1),
    (4,'Legacy','Student','Student','legacy@example.com','test','09123456789','123 Street','default',3,0),
    (5,'Race','Student','Student','race.one@example.com','test','09000000005','123 Street','default',3,1),
    (6,'Race','Student','Student','race.two@example.com','test','09000000006','123 Street','default',3,1)");
    phoneCheck(SmartServeValidation::phoneKey('09123456789')==='+639123456789','Local phone canonicalized');
    phoneCheck(SmartServeValidation::phoneKey('+639123456789')==='+639123456789','International phone canonicalized');
    phoneCheck(SmartServeValidation::phoneKey('09123456789 ')===null,'Whitespace remains invalid');
    foreach (['09123456789','+639123456789','09234567890','+639234567890'] as $phone) phoneCheck($model->checkPhoneExists($phone),'Both formats recognize existing number');
    phoneCheck(!$model->checkPhoneExists('09444444444'),'Unused number allowed');
    phoneCheck(!$model->checkPhoneExists('+639123456789',1),'Own current number and legacy duplicate retained');
    phoneCheck($model->checkPhoneExists('09123456789',2),'Other account cannot claim legacy shared number');
    foreach ([1,2,3] as $id) {
        $before=$db->query("SELECT * FROM accounts WHERE id=$id")->fetch_assoc();
        $phone=$id===1 ? '09234567890' : '+639123456789';
        phoneCheck(!$model->updateAccountProfile('Changed','','Student',$before['position'],$phone,'Changed address',$id),'Each role rejects another user number');
        phoneCheck($model->getProfileUpdateError()==='Phone number is already registered.','Duplicate gets precise message');
        phoneCheck($before===$db->query("SELECT * FROM accounts WHERE id=$id")->fetch_assoc(),'Rejected save preserves complete profile');
        phoneCheck($model->updateAccountProfile($before['fname'],'',$before['lname'],$before['position'],$before['contact'],$before['address'],$id),'Each role can retain own number');
    }
    phoneCheck(!$model->updateStudentProfile('Changed','','Student','09234567890','Changed address',1,'PWD',str_repeat('a',48).'.png'),'Duplicate prevents student priority change');
    phoneCheck($db->query('SELECT priority_type FROM accounts WHERE id=1')->fetch_row()[0]==='N/A','Priority preserved after rejected contact');
    phoneCheck($model->updateStudentProfile('Juan','','Student','09444444444','123 Street',1,'Senior Citizen'),'Unused phone and priority commit together');
    phoneCheck($model->checkPhoneExists('+639444444444'),'Committed number recognized in other format');
    foreach (['+639444444444', '+639234567890'] as $phone) {
        $stmt=$db->prepare("SELECT IS_USED_LOCK(CONCAT('ss.phone.',SHA1(CONCAT(DATABASE(),':',?))))");
        $stmt->bind_param('s',$phone);$stmt->execute();
        phoneCheck($stmt->get_result()->fetch_row()[0]===null,'Successful and rejected profile transactions release phone locks');$stmt->close();
    }
    phoneCheck($model->checkPhoneExists('+639123456789'),'Inactive account still owns number');
    $preview='';
    foreach (['register','staff'] as $mode) {
        foreach (['09234567890','+639234567890'] as $phone) {
            $output=finishPhoneChild(startPhoneChild($mode,$phone));
            phoneCheck(str_contains($output,'Phone number is already registered.'),'Account creation rejects both duplicate formats');
            $preview=$output;
        }
    }
    phoneCheck((int)$db->query('SELECT COUNT(*) FROM accounts')->fetch_row()[0]===6,'Rejected registrations create no accounts');
    phoneCheck((int)$db->query('SELECT COUNT(*) FROM email_outbox')->fetch_row()[0]===0,'Rejected registrations send no welcome emails');
    $preview=str_replace('/assets/', '../../assets/', $preview);
    file_put_contents(__DIR__.'/fixtures/phone-duplicate-preview.html','<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Phone validation preview</title></head><body style="background:#005141">'.$preview.'</body></html>');
    foreach (['register'=>'09555555555','staff'=>'+639666666666'] as $mode=>$phone) {
        $output=finishPhoneChild(startPhoneChild($mode,$phone));
        phoneCheck(str_contains($output,$mode==='register'?'account has been created successfully':'Successfully registered'),'Unused number can create an account');
        phoneCheck($model->checkPhoneExists($phone),'Saved account claims number');
    }
    phoneCheck((int)$db->query('SELECT COUNT(*) FROM email_outbox')->fetch_row()[0]===2,'Successful creations retain welcome notifications');
    $lock=$db->query("SELECT CONCAT('ss.phone.',SHA1(CONCAT(DATABASE(),':','+639777777777')))")->fetch_row()[0];
    $stmt=$db->prepare('SELECT GET_LOCK(?,5)');$stmt->bind_param('s',$lock);$stmt->execute();$stmt->close();
    $one=startPhoneChild('profile','09777777777',5); $two=startPhoneChild('student','+639777777777',6);
    phoneCheck(trim(fgets($one[1][1]))==='READY' && trim(fgets($two[1][1]))==='READY','Both competing workers ready');
    $stmt=$db->prepare('SELECT RELEASE_LOCK(?)');$stmt->bind_param('s',$lock);$stmt->execute();$stmt->close();
    $outputs=[finishPhoneChild($one),finishPhoneChild($two)];
    phoneCheck(count(array_filter($outputs,fn($value)=>$value==='SAVED'))===1,'Only one competing assignment succeeds');
    phoneCheck(count(array_filter($outputs,fn($value)=>$value==='Phone number is already registered.'))===1,'Other competing assignment reports duplicate');
    phoneCheck((int)$db->query("SELECT COUNT(*) FROM accounts WHERE contact IN ('09777777777','+639777777777')")->fetch_row()[0]===1,'No duplicate created by concurrent saves');
    phoneCheck($db->query('SELECT priority_type FROM accounts WHERE id=6')->fetch_row()[0]===($outputs[1]==='SAVED'?'Senior Citizen':'N/A'),'Competing student priority commits only with a successful contact save');
    phoneCheck($db->query("SELECT IS_USED_LOCK('$lock')")->fetch_row()[0]===null,'Phone lock released');
    echo "Passed $checks phone uniqueness checks. Disposable accounts and captured emails only.\n";
} finally {
    $db->select_db('smartserve'); $db->query("DROP DATABASE `$testDb`");
}
