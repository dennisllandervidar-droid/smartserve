<?php

require_once __DIR__ . '/kiosk-hours.php';

/** Read the shared config afresh, including on hosts with opcode caching. */
function smartserve_load_kiosk_config(?string $path = null): array {
    $path = $path ?? __DIR__ . '/kiosk-config.php';
    if (function_exists('opcache_invalidate')) {
        @opcache_invalidate($path, true);
    }
    $config = require $path;
    if (!is_array($config) || !isset($config['allow_after_hours_testing']) || !is_bool($config['allow_after_hours_testing'])) {
        throw new RuntimeException('The kiosk configuration must return an array with a boolean testing setting.');
    }
    smartserve_kiosk_hours($config);
    return $config;
}

/** Change only the literal testing flag; readers always see a complete config. */
function smartserve_save_kiosk_testing(bool $enabled, ?string $path = null): bool {
    $path = $path ?? __DIR__ . '/kiosk-config.php';
    $lock = @fopen($path . '.lock', 'c');
    if ($lock === false) {
        throw new RuntimeException('The kiosk configuration directory is not writable.');
    }
    $temporary = null;
    try {
        if (!flock($lock, LOCK_EX)) {
            throw new RuntimeException('Unable to lock the kiosk configuration.');
        }
        $source = @file_get_contents($path);
        if ($source === false) {
            throw new RuntimeException('Unable to read the kiosk configuration.');
        }
        $pattern = '/^([ \t]*[\'\"]allow_after_hours_testing[\'\"][ \t]*=>[ \t]*)(true|false)([ \t]*,)/m';
        if (preg_match_all($pattern, $source) !== 1) {
            throw new RuntimeException('Expected one literal allow_after_hours_testing setting.');
        }
        $config = smartserve_load_kiosk_config($path);
        if ($config['allow_after_hours_testing'] === $enabled) {
            return false;
        }
        $updated = preg_replace_callback($pattern, static function ($match) use ($enabled) {
            return $match[1] . ($enabled ? 'true' : 'false') . $match[3];
        }, $source);
        $temporary = @tempnam(dirname($path), '.kiosk-');
        if ($temporary === false || realpath(dirname($temporary)) !== realpath(dirname($path))) {
            throw new RuntimeException('Unable to create a temporary kiosk configuration.');
        }
        if (@file_put_contents($temporary, $updated) !== strlen($updated)) {
            throw new RuntimeException('Unable to write the kiosk configuration.');
        }
        $expected = $config;
        $expected['allow_after_hours_testing'] = $enabled;
        if (smartserve_load_kiosk_config($temporary) !== $expected) {
            throw new RuntimeException('The updated configuration did not preserve the expected settings.');
        }
        $permissions = fileperms($path);
        if ($permissions !== false && !@chmod($temporary, $permissions & 0777)) {
            throw new RuntimeException('Unable to preserve kiosk configuration permissions.');
        }
        if (!@rename($temporary, $path)) {
            throw new RuntimeException('Unable to replace the kiosk configuration.');
        }
        $temporary = null;
        clearstatcache(true, $path);
        if (function_exists('opcache_invalidate')) {
            @opcache_invalidate($path, true);
        }
        return true;
    } finally {
        if (is_string($temporary) && is_file($temporary)) {
            @unlink($temporary);
        }
        flock($lock, LOCK_UN);
        fclose($lock);
    }
}
