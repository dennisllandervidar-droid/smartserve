<?php
if (PHP_SAPI !== 'cli') { http_response_code(403); exit('CLI only.'); }
putenv('SMARTSERVE_MAIL_AUTOSEND=0');
require_once __DIR__.'/../global/model.php';
mysqli_report(MYSQLI_REPORT_ERROR|MYSQLI_REPORT_STRICT);
$db=new mysqli('localhost','root','','smartserve');$db->set_charset('utf8mb4');
$testDb='smartserve_registration_test_'.bin2hex(random_bytes(6));
if(!preg_match('/\Asmartserve_registration_test_[a-f0-9]{12}\z/',$testDb))throw new RuntimeException('Invalid test database');
$db->query("CREATE DATABASE `$testDb` CHARACTER SET utf8mb4");
$db->query("CREATE TABLE `$testDb`.accounts LIKE smartserve.accounts");$db->select_db($testDb);
$emails=new SmartServeEmails($db,fn()=>true);$reflection=new ReflectionClass(Model::class);$model=$reflection->newInstanceWithoutConstructor();
foreach(['conn'=>$db,'emails'=>$emails]as$key=>$value){$property=$reflection->getProperty($key);$property->setAccessible(true);$property->setValue($model,$value);}
ob_start();
register_shutdown_function(function()use($db,$testDb){
    $output=ob_get_clean();$checks=0;
    try {
        $row=$db->query("SELECT mname,year,priority_type,pwd_status,pwd_id_path FROM accounts WHERE uname='pwd.test@example.com'")->fetch_assoc();
        foreach([$row && $row['mname']==='', $row && $row['year']==='6th Year', $row && $row['priority_type']==='PWD', $row && $row['pwd_status']==='pending', $row && $row['pwd_id_path']===str_repeat('a',48).'.png', str_contains($output,'Your SmartServe account has been created successfully.'), str_contains($output,'regular bookings are available'), (int)$db->query('SELECT COUNT(*) FROM email_outbox')->fetch_row()[0]===1]as$ok){$checks++;if(!$ok)throw new RuntimeException('Registration check '.$checks.' failed');}
        echo "Passed $checks registration priority checks. No real account or email created.\n";
    }finally{$db->select_db('smartserve');$db->query("DROP DATABASE `$testDb`");}
});
$model->addRegAccount('Juan','','Dela Cruz','pwd.test@example.com',password_hash('Secure#123',PASSWORD_DEFAULT),'09123456789','123 Street','Student','default',3,'6th Year','BS Computer Science',1,'PWD',str_repeat('a',48).'.png');
