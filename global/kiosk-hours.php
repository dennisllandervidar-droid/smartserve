<?php

/** Evaluate kiosk hours once for both page rendering and request enforcement. */
function smartserve_kiosk_hours(array $config, ?DateTimeImmutable $now = null): array {
    $timezone = new DateTimeZone($config['timezone'] ?? 'Asia/Manila');
    $opening = $config['opening_time'] ?? '';
    $closing = $config['closing_time'] ?? '';
    $testing = $config['allow_after_hours_testing'] ?? false;
    $validTime = static fn($value) => is_string($value) && preg_match('/\A(?:[01][0-9]|2[0-3]):[0-5][0-9]\z/', $value);
    if (!$validTime($opening) || !$validTime($closing) || $opening >= $closing || !is_bool($testing)) {
        throw new InvalidArgumentException('Kiosk hours require valid same-day opening/closing times and a boolean testing setting.');
    }
    $now = ($now ?? new DateTimeImmutable('now', $timezone))->setTimezone($timezone);
    $current = $now->format('H:i');
    $normalHours = $current >= $opening && $current < $closing;
    $label = static fn($time) => DateTimeImmutable::createFromFormat('!H:i', $time, $timezone)->format('g:i A');
    return [
        'is_open' => $normalHours || $testing,
        'normal_hours' => $normalHours,
        'testing_enabled' => $testing,
        'hours_label' => $label($opening) . ' to ' . $label($closing),
        'timezone' => $timezone->getName(),
    ];
}
