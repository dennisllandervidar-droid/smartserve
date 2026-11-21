<?php
require_once __DIR__ . '/validation.php';

function smartserve_store_pwd_id($file) {
    $error = SmartServeValidation::image($file, true);
    if ($error) throw new InvalidArgumentException($error);
    if (!is_uploaded_file($file['tmp_name'])) throw new InvalidArgumentException('Invalid PWD ID upload.');
    $folder = __DIR__ . '/../storage/private/pwd';
    if (!is_dir($folder) && !mkdir($folder, 0700, true)) throw new RuntimeException('Unable to store the PWD ID.');
    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']);
    $filename = bin2hex(random_bytes(24)) . ($mime === 'image/png' ? '.png' : '.jpg');
    if (!move_uploaded_file($file['tmp_name'], $folder . '/' . $filename)) throw new RuntimeException('Unable to store the PWD ID.');
    return $filename;
}

function smartserve_priority_label($account) {
    if (($account['priority_type'] ?? 'N/A') === 'PWD') return ($account['pwd_status'] ?? '') === 'approved' ? 'PWD' : 'N/A';
    return in_array($account['priority_type'] ?? '', ['Senior Citizen', 'Pregnant'], true) ? $account['priority_type'] : 'N/A';
}

function smartserve_can_view_pwd($viewer, $studentId) {
    if (!$viewer || (int)($viewer['status'] ?? 0) !== 1) return false;
    $role = (int)($viewer['access'] ?? 0);
    return in_array($role, [1, 2], true) || ($role === 3 && (int)$viewer['id'] === (int)$studentId);
}

function smartserve_priority_icon($name) {
    $paths = [
        'shield' => '<path d="M12 3 20 6v6c0 5-8 9-8 9s-8-4-8-9V6z"/><path d="m8 12 3 3 5-6"/>',
        'eye' => '<path d="M2 12s4-7 10-7 10 7 10 7-4 7-10 7S2 12 2 12Z"/><circle cx="12" cy="12" r="3"/>',
        'check' => '<circle cx="12" cy="12" r="9"/><path d="m8 12 3 3 5-6"/>',
        'close' => '<circle cx="12" cy="12" r="9"/><path d="m9 9 6 6m0-6-6 6"/>',
        'clock' => '<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/>',
        'info' => '<circle cx="12" cy="12" r="9"/><path d="M12 11v6m0-10v.01"/>'
    ];
    return '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">' . ($paths[$name] ?? $paths['info']) . '</svg>';
}

function smartserve_pwd_card($account, $admin = false) {
    $id = (int)($account['id'] ?? 0);
    $isPwd = ($account['priority_type'] ?? '') === 'PWD';
    $status = $isPwd && in_array($account['pwd_status'] ?? '', ['pending', 'approved', 'rejected'], true) ? $account['pwd_status'] : 'none';
    $labels = ['pending'=>'Pending review', 'approved'=>'Verified', 'rejected'=>'Needs resubmission', 'none'=>$isPwd ? 'Not submitted' : 'No review required'];
    $label = $labels[$status];
    $priority = htmlspecialchars(smartserve_priority_label($account), ENT_QUOTES);
    $icon = ['pending'=>'clock', 'approved'=>'check', 'rejected'=>'close', 'none'=>'info'][$status];
    echo '<section class="ss-priority-card" data-review="' . $status . '"><div class="ss-priority-heading"><div class="ss-priority-title"><span class="ss-priority-emblem">' . smartserve_priority_icon('shield') . '</span><div><h5>Priority verification</h5><p>' . ($admin ? 'Review eligibility for priority service.' : 'Priority status and supporting identification.') . '</p></div></div><span class="ss-priority-status">' . smartserve_priority_icon($icon) . $label . '</span></div>';
    echo '<div class="ss-priority-details"><div><span class="ss-priority-label">Current priority</span><strong class="ss-priority-value">' . $priority . '</strong></div><div><span class="ss-priority-label">Booking access</span><strong>' . ($priority === 'N/A' ? 'Regular bookings' : 'Priority bookings') . '</strong></div></div>';
    if ($isPwd) {
        $notes = ['pending'=>'Regular bookings are available while the PWD ID is being reviewed.', 'approved'=>'The PWD ID has been approved. Priority is applied automatically to bookings.', 'rejected'=>'Upload a clear, valid PWD ID from Update Profile for another review.', 'none'=>'Upload a PWD ID from Update Profile to request priority verification.'];
        echo '<p class="ss-priority-note">' . smartserve_priority_icon('info') . '<span>' . $notes[$status] . '</span></p>';
    }
    if ($isPwd && !empty($account['pwd_id_path'])) {
        echo '<div class="ss-priority-actions"><a class="ss-priority-btn ss-priority-view" href="../pwd-id.php?id=' . $id . '" target="_blank" rel="noopener">' . smartserve_priority_icon('eye') . 'View PWD ID</a>';
        if ($admin && $status === 'pending') {
            echo '<form method="post" data-ss-confirm="Confirm your decision after reviewing the PWD ID."><input type="hidden" name="pwd_account_id" value="' . $id . '"><input type="hidden" name="csrf_token" value="' . htmlspecialchars($_SESSION['pwd_csrf'] ?? '', ENT_QUOTES) . '"><button type="submit" name="pwd_decision" value="approved" class="ss-priority-btn ss-priority-approve">' . smartserve_priority_icon('check') . 'Approve PWD</button><button type="submit" name="pwd_decision" value="rejected" class="ss-priority-btn ss-priority-reject">' . smartserve_priority_icon('close') . 'Reject ID</button></form>';
        }
        echo '</div>';
    }
    echo '</section>';
}
