<?php
// Preview the production profile form without account access or database writes.
if (PHP_SAPI !== 'cli') { http_response_code(403); exit('CLI only.'); }
$source=file_get_contents(__DIR__.'/../student/update-profile.php');
preg_match('~<form enctype="multipart/form-data" class="edit-profile".*?</form>~s',$source,$match);
$form=preg_replace('~<select class="form-control" name="account_priority".*?</select>~s','<select class="form-control" name="account_priority" id="account_priority"><option>N/A</option><option>PWD</option><option>Senior Citizen</option><option>Pregnant</option></select>',$match[0]);
$form=preg_replace_callback('~<\?php(.*?)\?>~s',function($match){
    $php=$match[1];
    if(str_contains($php,'filemtime('))return (string)filemtime(__DIR__.'/../assets/js/priority.js');
    foreach(['account_fname'=>'Sample','account_mname'=>'','account_lname'=>'Student','account_contact'=>'09123456789','account_address'=>'123 Sample Street'] as $name=>$value) if(str_contains($php,'$'.$name))return $value;
    if(str_contains($php,"!== 'PWD'"))return str_contains($php,"'disabled'")?'disabled':'hidden';
    return '';
},$form);
$form=str_replace('src="../assets/','src="../../assets/',$form);
preg_match('~<style type="text/css">(.*?)</style>~s',$source,$match);
$css=preg_replace('~<\?php.*?\?>~s','#005141',$match[1]);
$styles='';foreach(['assets.css','typography.css','shortcodes/shortcodes.css','style.css','dashboard.css'] as $file)$styles.='<link rel="stylesheet" href="../../dashboard/assets/css/'.$file.'">';
$scripts='';foreach(['js/jquery.min.js','vendors/bootstrap/js/popper.min.js','vendors/bootstrap/js/bootstrap.min.js','vendors/bootstrap-select/bootstrap-select.min.js','js/functions.js'] as $file)$scripts.='<script src="../../dashboard/assets/'.$file.'"></script>';
$html='<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Student priority field preview</title><link rel="stylesheet" href="../../assets/css/forms.css?v='.filemtime(__DIR__.'/../assets/css/forms.css').'">'.$styles.'<script src="../../assets/js/forms.js"></script><style>'.$css.'body{background:#f5f8f6;padding:30px}.preview-wrap{max-width:950px;margin:auto;background:#fff;padding:30px;border-radius:18px}.preview-wrap>p{color:#73857b;font-size:13px}</style></head><body><main class="preview-wrap"><h3>Update Profile</h3><p>Student profile preview · no accounts are changed</p>'.$form.'</main>'.$scripts.'<script>document.addEventListener("submit",event=>event.preventDefault(),true);document.querySelector("a[href=my-profile]").addEventListener("click",event=>event.preventDefault());</script></body></html>';
file_put_contents(__DIR__.'/fixtures/student-priority-preview.html',$html);
echo "Generated student priority preview using the production form, styles and script.\n";
