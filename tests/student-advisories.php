<?php
// Exercise production page guards with synthetic sessions and a database-free model.
if (PHP_SAPI !== 'cli') { http_response_code(403); exit('CLI only.'); }
if (($argv[1] ?? '') === '--child') {
    $route = $argv[2] ?? '';
    if (!in_array($route, ['student/announcement.php','student/archived-announcement.php','student/newsfeed.php','admin/announcement.php','admin/archived-announcement.php','office/announcement.php','office/archived-announcement.php'], true)) exit(2);
    $_SERVER['REQUEST_METHOD'] = $argv[3];
    $_SESSION = ['sess'=>1,'access'=>(int)$argv[4],'username'=>'synthetic@example.com'];
    $_POST = ['add_announcement'=>'','edit'=>'','archive'=>'','archive-id'=>1,'title'=>'Test','details'=>'Test details','date'=>'2026-11-11T09:00'];
    if ($_SERVER['REQUEST_METHOD'] === 'GET') $_POST = [];
    $mutations = [];
    class Model {
        public function displayFaqs($status) { return []; }
        public function displayAnnouncements($category, $status) {
            return [['id'=>1,'title'=>'Synthetic current advisory','details'=>'A readable office announcement.','date'=>'2026-11-11 09:00:00','image_unique'=>'','course'=>'Cashier','fname'=>'Test','lname'=>'Staff']];
        }
        public function __call($method, $args) { global $mutations; $mutations[]=$method; throw new RuntimeException('Unexpected model operation: '.$method); }
    }
    $model = new Model();
    $web_name='SmartServe'; $web_icon='icon'; $primary_color='#087830'; $secondary_color='#14a44d';
    register_shutdown_function(function() use (&$mutations) {
        echo "\nGUARD_RESULT=" . json_encode(['status'=>http_response_code() ?: 200,'mutations'=>$mutations]);
    });
    $source=file_get_contents(__DIR__.'/../'.$route);
    // Replace only bootstrap/session fixtures; leave guards, POST handlers, and
    // page content intact. No production Model or database connection is loaded.
    $source=str_replace(["include('../global/model.php');", "require_once '../global/model.php';", "include('main/department.php');", "require 'main/department.php';", "include 'main/admin-nav.php';", "include 'main/faqs-widget.php';"], '', $source);
    if (!str_starts_with($route, 'student/')) {
        // Exercise staff page authorization without invoking authorized publishing
        // or archive actions. The guard itself is the unmodified production code.
        $guardExit = strpos($source, "exit('You cannot manage advisories with this account.');");
        if ($guardExit === false) throw new RuntimeException('Missing role guard');
        $guardEnd = strpos($source, '}', $guardExit);
        $source = substr($source, 0, $guardEnd + 1) . "\necho 'ADVISORY_MANAGEMENT_ALLOWED';";
    }
    // Resolve the original page's relative asset includes from its directory.
    chdir(__DIR__.'/../'.dirname($route));
    eval('?>'.$source);
    exit;
}

$checks=0;
function advisoryCheck($ok,$label) { global $checks; $checks++; if (!$ok) throw new RuntimeException($label); }
function runAdvisoryPage($route,$method,$access=3) {
    $pipes=[];
    $process=proc_open([PHP_BINARY,__FILE__,'--child',$route,$method,(string)$access],[0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],$pipes);
    if (!is_resource($process)) throw new RuntimeException('Unable to launch page test');
    fclose($pipes[0]); $body=stream_get_contents($pipes[1]); $errors=stream_get_contents($pipes[2]);
    fclose($pipes[1]); fclose($pipes[2]); $exit=proc_close($process);
    if ($exit!==0 || $errors!=='') throw new RuntimeException($route.': '.$errors);
    if (!preg_match('/GUARD_RESULT=(\{[^\n]+\})/', $body, $match)) throw new RuntimeException('Missing guard result');
    return [json_decode($match[1],true,512,JSON_THROW_ON_ERROR),$body];
}

foreach (['student/announcement.php','student/archived-announcement.php','student/newsfeed.php'] as $route) {
    [$result,$body]=runAdvisoryPage($route,'POST');
    advisoryCheck($result['status']===405, $route.' rejects forged advisory mutations');
    advisoryCheck($result['mutations']===[], $route.' reaches no mutation method');
}
foreach (['student/announcement.php','student/archived-announcement.php'] as $route) {
    [$result,$body]=runAdvisoryPage($route,'GET');
    advisoryCheck($result['status']===302 && $result['mutations']===[], $route.' redirects old bookmarks to read-only advisories');
}
[$result,$body]=runAdvisoryPage('student/newsfeed.php','GET');
advisoryCheck($result['status']===200 && str_contains($body,'Synthetic current advisory'), 'Student can still read current office advisories');
advisoryCheck(!preg_match('/ADD NEW ADVISORY|Archived Advisories|Archive Advisory|Edit Advisory|archived-announcement|add_announcement/i',$body), 'Student newsfeed renders no management or archive controls');
foreach (['admin/announcement.php','admin/archived-announcement.php','office/announcement.php','office/archived-announcement.php'] as $route) {
    foreach (['GET','POST'] as $method) {
        [$result,$body]=runAdvisoryPage($route,$method);
        advisoryCheck($result['status']===403 && $result['mutations']===[], 'Student cannot access '.$method.' '.$route);
    }
    $allowedRole = str_starts_with($route, 'admin/') ? 1 : 2;
    [$result,$body]=runAdvisoryPage($route,'GET',$allowedRole);
    advisoryCheck($result['status']===200 && str_contains($body,'ADVISORY_MANAGEMENT_ALLOWED'), 'Own role still reaches management on '.$route);
    [$result,$body]=runAdvisoryPage($route,'GET',$allowedRole===1 ? 2 : 1);
    advisoryCheck($result['status']===403 && !str_contains($body,'ADVISORY_MANAGEMENT_ALLOWED'), 'Other staff role cannot use '.$route);
}
echo "Passed $checks student advisory access checks. Synthetic sessions only; no database writes or messages.\n";
