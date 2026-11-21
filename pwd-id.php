<?php
session_start();
require_once __DIR__ . '/global/model.php';
require_once __DIR__ . '/global/priority.php';
if (empty($_SESSION['sess']) || !SmartServeValidation::id($_GET['id'] ?? '')) { http_response_code(403); exit('Access denied.'); }
$model = new Model();
$viewer = $model->displayAccountDetails((int)$_SESSION['sess'])[0] ?? null;
$target = (int)$_GET['id'];
if (!smartserve_can_view_pwd($viewer, $target)) { http_response_code(403); exit('Access denied.'); }
$account = $model->displayStudentAccountDetails($target);
$filename = $account['pwd_id_path'] ?? '';
if (!$account || (int)$account['access'] !== 3 || ($account['priority_type'] ?? '') !== 'PWD' || !preg_match('/\A[a-f0-9]{48}\.(?:jpg|png)\z/', $filename)) { http_response_code(404); exit('ID not found.'); }
$path = __DIR__ . '/storage/private/pwd/' . $filename;
if (!is_file($path)) { http_response_code(404); exit('ID not found.'); }
header('Content-Type: ' . (str_ends_with($filename, '.png') ? 'image/png' : 'image/jpeg'));
header('Content-Disposition: inline; filename="pwd-id.' . pathinfo($filename, PATHINFO_EXTENSION) . '"');
header('Cache-Control: private, no-store');
header('X-Content-Type-Options: nosniff');
readfile($path);
