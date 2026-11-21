<?php
require_once '../global/model.php';
$model = new Model();
require 'main/department.php';
    if ((int)($_SESSION['access'] ?? 0) !== 1) {
        header_remove('Location');
        http_response_code(403);
        exit('You cannot manage advisories with this account.');
    }
require '../global/advisory-archive.php';
