<?php
/**
 * Edit this file to change kiosk opening hours or permit after-hours testing.
 * Times use the 24-hour clock in Philippine time. Opening is inclusive;
 * closing is exclusive (07:00–17:00 means 7:00 AM until before 5:00 PM).
 *
 * Open after-hours-testing.php to turn testing on or off without signing in.
 * The page saves allow_after_hours_testing here; you can also edit it manually.
 * Refresh the kiosk after changing the setting. Turn testing off when finished.
 * This opens the kiosk only: appointment dates, service blocks and the
 * ten-minute QR check-in deadline still apply. Walk-ins can be used to test
 * queue handling after hours. No browser query parameter enables testing.
 */
return [
    'timezone' => 'Asia/Manila',
    'opening_time' => '07:00',
    'closing_time' => '17:00',
    'allow_after_hours_testing' => true,
];
