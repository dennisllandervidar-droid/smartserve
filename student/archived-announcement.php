<?php
// Students read current advisories through the newsfeed; management is staff-only.
if (!in_array($_SERVER['REQUEST_METHOD'] ?? 'GET', ['GET', 'HEAD'], true)) {
    http_response_code(405);
    header('Allow: GET, HEAD');
    exit('Student advisories are read-only.');
}
header('Location: newsfeed', true, 302);
exit;
