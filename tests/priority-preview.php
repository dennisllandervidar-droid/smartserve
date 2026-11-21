<?php
// Render the production card with synthetic data; no account or review is changed.
if (PHP_SAPI !== 'cli') { http_response_code(403); exit('CLI only.'); }
require_once __DIR__ . '/../global/priority.php';
$_SESSION['pwd_csrf'] = 'synthetic-preview';
$priority = $argv[1] ?? 'PWD';
if (!in_array($priority, ['N/A','PWD','Senior Citizen','Pregnant'], true)) throw new InvalidArgumentException('Invalid preview priority');
$account = ['id'=>0, 'priority_type'=>$priority, 'pwd_status'=>'pending', 'pwd_id_path'=>'synthetic'];
ob_start();
smartserve_pwd_card($account, true);
$card = ob_get_clean();
$profileSource = file_get_contents(__DIR__ . '/../admin/account-student-profile.php');
preg_match('~<style type="text/css">(.*?)</style>~s', $profileSource, $styleMatch);
$profileCss = preg_replace('~<\?php.*?\?>~s', '#005141', $styleMatch[1]);
$version = filemtime(__DIR__ . '/../assets/css/forms.css');
$styles = '';
foreach (['assets.css', 'typography.css', 'shortcodes/shortcodes.css', 'style.css', 'dashboard.css'] as $css) {
    $styles .= '<link rel="stylesheet" href="../../dashboard/assets/css/' . $css . '">';
}
$html = '<!DOCTYPE html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Priority verification preview</title><link rel="stylesheet" href="../../assets/css/forms.css?v=' . $version . '">' . $styles . '<style>' . $profileCss . '</style><style>body{margin:0;background:#fcfcfc}.preview-top{background:#005141;color:#fff;padding:18px 36px;font-size:18px;font-weight:600}.preview-top small{font-size:12px;font-weight:400;margin-left:18px;opacity:.75}.preview-main{max-width:1160px;margin:30px auto;padding:0 24px}.preview-breadcrumb{margin-bottom:25px;font-size:20px;color:#234439}.preview-breadcrumb span{margin-left:15px;padding-left:15px;border-left:1px solid #ddd;font-size:14px;color:#89939c}.preview-status{font-size:13px;color:#718579;margin:0}@media(max-width:480px){.preview-main{padding:0 12px}.preview-top{padding:15px}.preview-top small{display:block;margin-left:0}.preview-breadcrumb{font-size:17px}}</style></head><body><header class="preview-top">SmartServe <small>De La Salle Araneta University · Design preview</small></header><main class="preview-main"><div class="preview-breadcrumb">Accounts <span>Student Profile</span></div><div class="modern-profile-card"><div class="profile-header"><img class="profile-avatar" src="../../assets/img/profile-pictures/default.jpg" alt="Default student avatar"><div class="profile-info"><div class="profile-badge">Student Account</div><h3>Sample Student</h3><p class="text-muted m-0">Member since October 2026</p></div></div>' . $card . '<div class="info-grid"><div class="info-item"><div class="info-icon"><i class="ti-calendar"></i></div><div class="info-content"><label>Year level</label><span>1st Year</span></div></div><div class="info-item"><div class="info-icon"><i class="ti-book"></i></div><div class="info-content"><label>Course program</label><span>Doctor of Veterinary Medicine</span></div></div><div class="info-item"><div class="info-icon"><i class="ti-email"></i></div><div class="info-content"><label>Email address</label><span>student@example.com</span></div></div><div class="info-item"><div class="info-icon"><i class="ti-mobile"></i></div><div class="info-content"><label>Contact number</label><span>09123456789</span></div></div></div></div><p class="preview-status" role="status">Synthetic preview. Review controls do not change accounts.</p></main><script>document.addEventListener("submit",event=>event.preventDefault(),true);document.querySelector(".ss-priority-actions").addEventListener("click",event=>{if(event.target.closest("a,button")){event.preventDefault();document.querySelector(".preview-status").textContent="Preview only. No ID was opened and no review was saved.";}});</script></body></html>';
$html = str_replace('document.querySelector(".ss-priority-actions").addEventListener', 'document.querySelector(".ss-priority-actions")?.addEventListener', $html);
file_put_contents(__DIR__ . '/fixtures/' . ($priority === 'PWD' ? 'priority-preview.html' : 'priority-non-pwd-preview.html'), $html);
echo "Generated priority verification preview from the production component.\n";
