# Graph, student advisory, booking, and kiosk revisions — 11 November 2026

Both graph axes now show a minimum range of zero to five and expand automatically for larger real counts. Counts are not inflated: a single visit remains one visit. Integer ticks avoid fractional people/visits. Regression data containing thirteen visits verifies that neither daily traffic nor peak-hour totals are capped at one.

Graphs now show Monday through Friday only. Historical Saturday/Sunday visits are excluded from both the weekday and peak-hour graphs but remain in the records report. The booking calendar uses five weekday columns. Manual date entry, slot requests, direct appointment submissions, and blocked-service rescheduling all reject weekends. The existing limit of today through seven calendar days ahead is retained. The calendar can still load on Saturday or Sunday to offer the following weekdays. Existing appointments were not rewritten.

Student advisory pages show current announcements without Add, Edit, or Archive controls. Legacy student management/archive URLs redirect to the read-only newsfeed. Direct student POST requests are rejected, and students cannot use the admin/office advisory management routes. Staff access for the appropriate role is preserved.

## One file for kiosk hours and testing

Edit `global/kiosk-config.php`. Its defaults are:

```php
'timezone' => 'Asia/Manila',
'opening_time' => '07:00',
'closing_time' => '17:00',
'allow_after_hours_testing' => false,
```

Open `after-hours-testing.php` to turn `allow_after_hours_testing` on or off with a switch. This standalone public page requires no login and uses the homepage's green styling, branding and navigation. Each toggle saves immediately to `global/kiosk-config.php`; no database migration or second override file is needed. Refresh an already-open kiosk after changing the switch. Turn testing off afterward.

You can still edit the same file manually. To start normal hours at 8 AM instead, set `opening_time` to `08:00`. The closing time is exclusive: normal 07:00–17:00 hours close at 5 PM. The PHP server needs write access to the configuration file and its directory for the page to save. Saves are serialized and replace a fully validated file atomically; failed saves retain the previous setting. Page-visit tokens prevent unrelated sites from submitting the switch on a visitor's behalf, without requiring an account.

The same configuration controls the visible kiosk and direct QR/walk-in requests. Testing mode displays an indicator. It opens the kiosk but retains appointment-date, blocked-service, and ten-minute check-in rules. Use walk-ins to exercise queues after hours; an expired appointment QR remains expired. This setting does not add a weekend restriction to walk-ins.

## Verification and deployment

Passed: 48 graph configuration checks, 23 report integration checks, 28 kiosk-hours checks, 26 student-advisory access checks, 271 PHP validation checks, 204 JavaScript validation checks, 61 multi-service checks, 135 student-service checks, and 55 direct HTTP validation checks plus two private-ID access checks. Earlier booking and email regression suites also passed. Database tests use disposable schemas and captured notification transports.

Browser previews verified both graphs rendering a count of thirteen and the five-column weekday booking calendar with selectable weekdays. The kiosk-hours tests include opening/closing boundaries, 8 AM configuration, after-hours testing, and closed direct requests before database access. Advisory access tests use synthetic sessions, including direct student attempts against staff routes.

The public testing page additionally passes 45 isolated configuration checks and 38 local HTTP checks. Browser verification confirms both switch states save without a login, with the homepage design adapting to narrow and desktop layouts. The setting was restored to Off after testing. A visual preview is saved at `tests/fixtures/after-hours-testing-preview.jpg`.

No database migration is required. Changes are local and have not been deployed to hosting. Deploy these application files together, preserving hosting credentials:

```text
admin/reports-graph.php
admin/announcement.php
admin/archived-announcement.php
office/announcement.php
office/archived-announcement.php
student/newsfeed.php
student/announcement.php
student/archived-announcement.php
student/booking.php
global/model.php
global/validation.php
global/kiosk-config.php
global/kiosk-hours.php
global/kiosk-settings.php
after-hours-testing.php
kiosk.php
assets/js/forms.js
assets/js/schedule-calendar.js
assets/css/schedule-calendar.css
```

Preserve the intended kiosk testing setting when uploading `global/kiosk-config.php`.
