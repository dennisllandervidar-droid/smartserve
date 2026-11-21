<?php
if (PHP_SAPI !== 'cli') { http_response_code(403); exit('CLI only.'); }
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
$db = new mysqli('localhost', 'root', '', 'smartserve');
$db->query("SET time_zone='+08:00'");
$username = 'cooldown.test.' . bin2hex(random_bytes(6)) . '@example.com';
$other = 'blocked.test.' . bin2hex(random_bytes(6)) . '@example.com';
$curl = curl_init(); $checks = 0;
curl_setopt_array($curl, [CURLOPT_RETURNTRANSFER=>true,CURLOPT_FOLLOWLOCATION=>true,CURLOPT_COOKIEFILE=>'',CURLOPT_TIMEOUT=>10]);
function httpLoginCheck($ok,$label) { global $checks; $checks++; if(!$ok)throw new RuntimeException($label); }
function loginRequest($path,$post=null) {
    global $curl;
    $statuses=[];
    curl_setopt($curl,CURLOPT_URL,'http://127.0.0.1/smartserve/' . $path);
    curl_setopt($curl,CURLOPT_HEADERFUNCTION,function($handle,$line)use(&$statuses){if(preg_match('/^HTTP\/\S+ (\d+)/',$line,$m))$statuses[]=(int)$m[1];return strlen($line);});
    if($post===null)curl_setopt($curl,CURLOPT_HTTPGET,true);
    else {curl_setopt($curl,CURLOPT_POST,true);curl_setopt($curl,CURLOPT_POSTFIELDS,http_build_query($post));}
    $body=curl_exec($curl);if($body===false)throw new RuntimeException(curl_error($curl));
    if(curl_getinfo($curl,CURLINFO_RESPONSE_CODE)!==200)throw new RuntimeException('HTTP login request failed.');
    return [$body,$statuses];
}
function loginPageState($body) {
    if(!preg_match('/data-login-state="([^"]+)"/',$body,$m))throw new RuntimeException('Missing login state.');
    return json_decode(html_entity_decode($m[1],ENT_QUOTES,'UTF-8'),true,512,JSON_THROW_ON_ERROR);
}
try {
    foreach([$username,$other]as$name){$stmt=$db->prepare('SELECT COUNT(*) FROM accounts WHERE uname=?');$stmt->bind_param('s',$name);$stmt->execute();httpLoginCheck((int)$stmt->get_result()->fetch_row()[0]===0,'Synthetic login email is not a live account');$stmt->close();}
    loginRequest('login.php');
    for($i=1;$i<=5;$i++) {
        [$body,$statuses]=loginRequest('login.php',['submit'=>'1','username'=>$username,'password'=>'Synthetic incorrect password']);
        $state=loginPageState($body);
        httpLoginCheck($statuses===[303,200],'Failed POST redirects to a GET');
        httpLoginCheck($state['attempts']===$i&&$state['remaining']===5-$i,'HTTP attempt count');
        [$refreshed]=loginRequest('login.php');
        httpLoginCheck(loginPageState($refreshed)['attempts']===$i,'Refresh does not consume another attempt');
        httpLoginCheck(!str_contains($refreshed,'SmartServe.notifyFromServer('),'Refresh does not repeat the old failure popup');
    }
    $deadline=$state['unlock_at'];
    httpLoginCheck($state['locked']&&preg_match('/<button[^>]*id="login-button"[^>]*disabled/',$body),'Fifth failure disables rendered button');
    [$json]=loginRequest('login.php?login_status=1');$state=json_decode($json,true,512,JSON_THROW_ON_ERROR);
    httpLoginCheck($state['locked']&&$state['unlock_at']===$deadline,'Polling preserves original deadline');
    [$body]=loginRequest('login.php',['submit'=>'1','username'=>$other,'password'=>'Synthetic incorrect password']);
    httpLoginCheck(loginPageState($body)['unlock_at']===$deadline,'Changing email cannot bypass session cooldown');
    $stmt=$db->prepare('SELECT COUNT(*) FROM login_attempts WHERE username=?');$stmt->bind_param('s',$other);$stmt->execute();httpLoginCheck((int)$stmt->get_result()->fetch_row()[0]===0,'Blocked submissions do not create another counter');$stmt->close();
    $stmt=$db->prepare('UPDATE login_attempts SET last_attempt=DATE_SUB(NOW(),INTERVAL 300 SECOND) WHERE username=?');$stmt->bind_param('s',$username);$stmt->execute();$stmt->close();
    [$json]=loginRequest('login.php?login_status=1');$state=json_decode($json,true,512,JSON_THROW_ON_ERROR);
    httpLoginCheck(!$state['locked']&&$state['remaining']===5,'HTTP server confirms cooldown expiry');
    [$body]=loginRequest('login.php');
    httpLoginCheck(!preg_match('/<button[^>]*id="login-button"[^>]*disabled/',$body),'Expired cooldown renders enabled button');
    [$body]=loginRequest('login.php',['submit'=>'1','username'=>$username,'password'=>'Synthetic incorrect password']);
    httpLoginCheck(loginPageState($body)['remaining']===4,'Next failure begins a fresh set of attempts');
    echo "Passed $checks login HTTP checks. Synthetic attempt counters removed; no accounts or messages changed.\n";
} finally {
    $stmt=$db->prepare('DELETE FROM login_attempts WHERE username IN (?,?)');$stmt->bind_param('ss',$username,$other);$stmt->execute();$stmt->close();curl_close($curl);
}
