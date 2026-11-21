<?php
if (PHP_SAPI !== 'cli') { http_response_code(403); exit('CLI only.'); }
require_once __DIR__ . '/../global/kiosk-settings.php';

$checks = 0;
function settingsCheck(bool $ok, string $label): void {
    global $checks;
    $checks++;
    if (!$ok) throw new RuntimeException($label);
}
function settingsRejected(callable $operation, string $label): void {
    $rejected = false;
    try { $operation(); } catch (Throwable $error) { $rejected = true; }
    settingsCheck($rejected, $label);
}

$productionPath = __DIR__ . '/../global/kiosk-config.php';
$productionHash = hash_file('sha256', $productionPath);
$directory = sys_get_temp_dir() . '/smartserve-kiosk-settings-' . bin2hex(random_bytes(8));
if (!mkdir($directory, 0700)) throw new RuntimeException('Cannot create isolated settings fixture.');
$path = $directory . '/kiosk-config.php';
$source = "<?php\r\n// Keep the configured office hours and this comment.\r\nreturn [\r\n"
    . "    'timezone' => 'Asia/Manila',\r\n"
    . "    'opening_time' => '08:00',\r\n"
    . "    'closing_time' => '17:00',\r\n"
    . "    'allow_after_hours_testing' => false, // Change only this value.\r\n"
    . "    'unrelated_setting' => false,\r\n];\r\n";
$enabledSource = str_replace("'allow_after_hours_testing' => false,", "'allow_after_hours_testing' => true,", $source);
$late = new DateTimeImmutable('2026-11-11 23:00:00', new DateTimeZone('Asia/Manila'));

try {
    file_put_contents($path, $source);
    $initial = smartserve_load_kiosk_config($path);
    settingsCheck($initial['allow_after_hours_testing'] === false, 'Initial persisted value is a boolean false');
    settingsCheck(!smartserve_kiosk_hours($initial, $late)['is_open'], 'Normal hours close the kiosk at 11 PM');
    settingsCheck(smartserve_save_kiosk_testing(true, $path), 'Enabling reports an actual change');
    settingsCheck(file_get_contents($path) === $enabledSource, 'Enable preserves hours, comments, line endings and unrelated values');
    $enabled = smartserve_load_kiosk_config($path);
    settingsCheck($enabled['allow_after_hours_testing'] === true, 'Fresh configuration read sees persisted true');
    $enabledHours = smartserve_kiosk_hours($enabled, $late);
    settingsCheck($enabledHours['is_open'] && !$enabledHours['normal_hours'], 'Persisted testing opens the kiosk after hours');
    settingsCheck($enabledHours['hours_label'] === '8:00 AM to 5:00 PM', 'Testing retains configured opening and closing hours');
    settingsCheck(!smartserve_save_kiosk_testing(true, $path), 'Repeated enable reports no change');
    settingsCheck(file_get_contents($path) === $enabledSource, 'Repeated enable leaves configuration content unchanged');
    settingsCheck(smartserve_save_kiosk_testing(false, $path), 'Disabling reports an actual change');
    settingsCheck(file_get_contents($path) === $source, 'Disable restores the original configuration exactly');
    $disabled = smartserve_load_kiosk_config($path);
    settingsCheck($disabled['allow_after_hours_testing'] === false, 'Fresh configuration read sees persisted false');
    settingsCheck(!smartserve_kiosk_hours($disabled, $late)['is_open'], 'Disabling restores after-hours closure');
    settingsCheck(!smartserve_save_kiosk_testing(false, $path), 'Repeated disable reports no change');
    settingsCheck(glob($directory . '/.kiosk-*') === [], 'Successful and unchanged saves leave no temporary files');

    $doubleQuoted = str_replace("'allow_after_hours_testing' => false,", '"allow_after_hours_testing" => false,', $source);
    file_put_contents($path, $doubleQuoted);
    settingsCheck(smartserve_save_kiosk_testing(true, $path), 'A double-quoted setting is supported');
    settingsCheck(smartserve_load_kiosk_config($path)['allow_after_hours_testing'] === true, 'Double-quoted setting persists as a boolean');

    $invalidSources = [
        'missing flag' => str_replace("    'allow_after_hours_testing' => false, // Change only this value.\r\n", '', $source),
        'string flag' => str_replace("'allow_after_hours_testing' => false,", "'allow_after_hours_testing' => 'false',", $source),
        'duplicate literal keys' => str_replace("    'unrelated_setting' => false,", "    'allow_after_hours_testing' => false,", $source),
        'inline duplicate override' => str_replace("    'unrelated_setting' => false,", "    'unrelated_setting' => false, 'allow_after_hours_testing' => false,", $source),
        'invalid office hours' => str_replace("'opening_time' => '08:00'", "'opening_time' => '25:00'", $source),
        'PHP syntax error' => str_replace("'timezone' =>", "'timezone' =", $source),
        'comment-only flag' => "<?php\n/*\n    'allow_after_hours_testing' => false,\n*/\nreturn ['timezone'=>'Asia/Manila', 'opening_time'=>'08:00', 'closing_time'=>'17:00'];\n",
    ];
    foreach ($invalidSources as $label => $invalidSource) {
        file_put_contents($path, $invalidSource);
        settingsRejected(static fn() => smartserve_save_kiosk_testing(true, $path), 'Reject ' . $label);
        settingsCheck(file_get_contents($path) === $invalidSource, 'Rejected ' . $label . ' leaves the original file intact');
        settingsCheck(glob($directory . '/.kiosk-*') === [], 'Rejected ' . $label . ' leaves no temporary files');
    }

    file_put_contents($path, "<?php\nreturn false;\n");
    settingsRejected(static fn() => smartserve_load_kiosk_config($path), 'Loading a non-array configuration is rejected');

    // A directory at the stable lock path reliably makes the write fail on
    // Windows and Unix, without depending on the test runner's permissions.
    file_put_contents($path, $source);
    unlink($path . '.lock');
    mkdir($path . '.lock');
    settingsRejected(static fn() => smartserve_save_kiosk_testing(true, $path), 'An unavailable write lock reports failure');
    settingsCheck(file_get_contents($path) === $source, 'Write failure keeps the original setting and office hours');
    settingsCheck(!smartserve_kiosk_hours(smartserve_load_kiosk_config($path), $late)['is_open'], 'Failed enable does not open the kiosk');
    rmdir($path . '.lock');
    settingsCheck(smartserve_save_kiosk_testing(true, $path), 'Saving succeeds after the write failure is resolved');
    settingsRejected(static fn() => smartserve_save_kiosk_testing(true, $directory . '/missing/config.php'), 'A missing configuration directory is rejected');
    settingsCheck(hash_file('sha256', $productionPath) === $productionHash, 'Tests never change the live kiosk configuration');
} finally {
    // Only files created inside this unique fixture directory are removed.
    foreach (glob($directory . '/.kiosk-*') ?: [] as $temporary) unlink($temporary);
    if (is_file($path)) unlink($path);
    if (is_file($path . '.lock')) unlink($path . '.lock');
    if (is_dir($path . '.lock')) rmdir($path . '.lock');
    rmdir($directory);
}

echo "Passed $checks kiosk-settings checks. Isolated files only; no database writes or messages.\n";
