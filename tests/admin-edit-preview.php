<?php
// Render the real edit modals against synthetic data; no account/database access.
if (PHP_SAPI !== 'cli') { http_response_code(403); exit('CLI only.'); }
$primary_color = '#005141';
$id = 1;
$fname = 'Sample';
$mname = '';
$lname = 'Staff';
$uname = 'sample@dlsau.edu.ph';
$contact = '09123456789';
$address = '123 Sample Street';
$course = 1;
$window_name = 'Window 1';
$office_name = 'Cashier';
$offices = [['id' => 1, 'office_name' => 'Cashier', 'status' => 1], ['id' => 2, 'office_name' => 'Clinic', 'status' => 1]];
$modals = '';
foreach (['account-offices.php' => 'edit-modal-', 'office-management.php' => 'edit-'] as $file => $prefix) {
    $source = file_get_contents(__DIR__ . '/../admin/' . $file);
    if (!preg_match('~<div id="' . $prefix . '<\?php echo \$id; \?>".*?</form>\s*</div>~s', $source, $match)) {
        throw new RuntimeException('Production edit modal not found: ' . $file);
    }
    ob_start();
    eval('?>' . $match[0]);
    $modals .= ob_get_clean();
}
$styles = '';
foreach (['assets.css', 'typography.css', 'shortcodes/shortcodes.css', 'style.css', 'dashboard.css'] as $file) {
    $styles .= '<link rel="stylesheet" href="../../dashboard/assets/css/' . $file . '">';
}
$scripts = '';
foreach (['js/jquery.min.js', 'vendors/bootstrap/js/popper.min.js', 'vendors/bootstrap/js/bootstrap.min.js', 'vendors/bootstrap-select/bootstrap-select.min.js', 'js/functions.js'] as $file) {
    $scripts .= '<script src="../../dashboard/assets/' . $file . '"></script>';
}
$html = '<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Admin edit reset preview</title>' . $styles . '<link rel="stylesheet" href="../../assets/css/forms.css"><script src="../../assets/js/forms.js"></script><style>body{padding:32px;background:#f4f8f5}.preview{max-width:800px;margin:auto;background:white;padding:28px;border-radius:14px}</style></head><body><main class="preview"><h2>Admin edit reset preview</h2><p>Production forms with synthetic data. No account or service is changed.</p><button class="btn btn-success" data-toggle="modal" data-target="#edit-modal-1">Edit Staff</button> <button class="btn btn-success" data-toggle="modal" data-target="#edit-1">Edit Service</button></main>' . $modals . $scripts . '<script src="../../assets/js/edit-modal-reset.js"></script><script>document.addEventListener("submit",event=>event.preventDefault(),true);</script></body></html>';
file_put_contents(__DIR__ . '/fixtures/admin-edit-preview.html', $html);
echo "Generated synthetic previews from the production staff and service edit modals.\n";
