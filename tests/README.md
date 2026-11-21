Run the shared validation regression checks without a database:

```powershell
& 'C:\xampp\php\php.exe' tests\validation.php
node tests\validation.js
```

Login allows five failed attempts. After the first failure, the page shows the remaining count; the fifth failure disables Login Account for five minutes with a countdown. The database deadline and session retain this state across refreshes, and failed POSTs redirect to GET so refresh does not use another attempt. The server enforces the cooldown and resets all five attempts at expiry. Successful login clears the counter. The existing email/IP scope is preserved, and a session already in cooldown cannot bypass it by changing the email field.

Apply `database/login-cooldown.sql` before deploying this update. It adds the unique email/IP index used for atomic failure counts. Run `php tests/login-cooldown.php`, `node tests/login-cooldown.js`, and `php tests/login-http.php` to verify attempt counts, cooldown boundaries, successful-login reset, refresh behavior, server enforcement, and the countdown. The PHP unit checks use a disposable database; the HTTP checks use nonexistent synthetic emails and remove their attempt counters afterward. No live account is changed and no notifications are sent.

To check direct HTTP submissions without database writes, start Apache in XAMPP and run:

```powershell
python tests\http-validation.py
```

Open `tests/fixtures/forms.html` through the local web server to exercise field errors, notification icons, focus handling, and confirmation buttons. This fixture never saves data.

Generate `tests/fixtures/priority-preview.html` with `php tests/priority-preview.php` to inspect the production priority verification card within the profile styles. It uses synthetic student details and disables review controls; no account or ID is changed.

Use `php tests/priority-preview.php Pregnant` to generate `tests/fixtures/priority-non-pwd-preview.html`, including a retained ID and stale pending status to verify that non-PWD profiles show no ID link or review controls. The same current-priority rule applies to student/admin/office profiles, office queue/history, admin reports, and the protected ID route. `php tests/student-services.php` covers switching priorities and checks the card and queue/history/report metadata for every priority type.

Generate `tests/fixtures/student-priority-preview.html` with `php tests/student-priority-preview.php` to check the student profile's PWD upload visibility. It includes the production Bootstrap Select plugin and dropdown initialization, so use the styled dropdown to switch from PWD to another priority. The upload should hide, disable and stop being required. Profile saves are disabled in this synthetic preview.

Blocked schedule time ranges accept whole hours from 7 AM through 4 PM, including noon. End time must be strictly later than start time. The PHP request validator, model, and browser all enforce this rule; earlier/equal end-time options are disabled and stale end selections are cleared when the start changes. 4 PM is available as an end time. Full-day and purpose-specific blocks do not require times.

Generate `tests/fixtures/block-schedules-preview.html` with `php tests/block-schedules-preview.php` to exercise the production dropdown controls and script without saving blocks or sending notifications.

Run `C:\xampp\php\php.exe tests/appointment-expiration.php` for the online arrival deadline checks. The tests use a frozen database clock and a disposable database to verify the ten-minute boundary, arrived students, walk-ins, time formats, multiple offices, late QR scans, transaction rollback, worker delivery, and email deduplication. They generate `tests/fixtures/email-cancelled-preview.html` from a captured production-template email and send no real email or SMS.

`php tests/email-links.php` verifies the live notification domain, conversion of legacy localhost/loopback URLs, preservation of paths/queries/tracking numbers, one labeled action button, absence of visible raw URLs in HTML/plain text, and repeatable cleanup of older queued bodies. `php tests/email-links.php invalid-url` also checks fallback from invalid configuration. The email notification integration tests capture every notification type and verify pending/retry upgrades while leaving sent messages untouched. No real mail is sent.

Names allow Unicode letters, combining accents, spaces, and hyphens, with at least one letter. Account creation limits each name to 30 characters; profile updates allow 50. Middle names remain optional. Contact numbers are required in every role's profile update and other contact forms, with matching browser, request, and model checks. Addresses allow numbers and punctuation up to 120 characters.

Philippine mobile numbers must match `09` plus nine digits, or `+639` plus nine digits. Emails must have a dotted domain and a letter-only extension of at least two characters. Existing institutional-email restrictions remain in force.

Phone numbers cannot be assigned to another account during registration, profile updates, or managed account creation/editing. `09` and `+639` versions of the same number share one claim, including numbers on inactive accounts. Existing numbers can be retained during edits, so legacy shared contacts do not block unrelated profile changes. Database advisory locks serialize competing assignments without changing existing records or requiring a schema migration. Run `php tests/phone-uniqueness.php` for disposable-account checks of duplicate messages, self exclusion, both formats, profile/priority preservation, welcome emails, and concurrent saves. It also generates a captured production notification at `tests/fixtures/phone-duplicate-preview.html`.

New passwords require at least 8 characters, a letter, a number, a special character, and no whitespace. Passwords exceeding bcrypt's 72-byte input limit receive a separate shorten-password message; Unicode characters count correctly toward the minimum. The server checks confirmation fields. Existing passwords retain their login behavior.

`php tests/student-services.php` covers PWD review and automatic priority, profile rollback, strict phone whitespace, seven-day booking limits, SMS reminder timing and retries, one-month advisory archives, and calendar availability. `php tests/registration-priority.php` checks registration with an optional middle name and a pending PWD ID. Both use disposable databases and captured notifications.

`php tests/scheduling-preview.php` generates a synthetic booking preview using the actual form, calendar module, styles, and validation scripts. Open `tests/fixtures/scheduling-preview.php` through the web server to inspect disabled dates/times, date selection popups, manual entry, and per-step errors without saving a booking. `php tests/sms-health.php` checks the configured SMS device without sending messages or printing credentials.

The booking preview preserves the production appointment-card wrapper. Select an office, a calendar date, an available time and a purpose, then click Confirm Request. The preview validates the submitted fields and returns the received date and time without saving a booking. Repeat with Enter date, and check that closing the calendar popup without choosing a time shows the step 4 error before submission.

For manual date entry, try incomplete years such as `0002`, `0020`, and `0202` before completing the current year. No invalid-date modal should open. A complete date inside the seven-day range loads times; editing it clears the previous time immediately. Responses for an earlier date or office selection must not restore stale times or purpose warnings.

Minor revision checks: `node tests/home-reminders.js` verifies permanent dismissal, ordinary dismissal and disabled browser storage. `php tests/minor-revisions-preview.php` creates a synthetic preview of the actual Forgot Password and all three profile password forms, plus the My Appointments blocking notice. Each password field should have one accessible show/hide control. No password change is saved. Student service checks also verify that blocking notices belong to the affected student and disappear after response/cancellation; registration checks verify persistence of 6th Year.

In the booking preview, submit the empty form, dismiss the named field errors and select Cashier. The card should turn green, the office error should clear and the purpose dropdown should offer Payment. Select a valid date/time and Payment, then confirm: the preview should accept the request without saving a booking. In registration/login, empty submissions name each missing field, show red borders and scroll/focus the first invalid field after closing the message. Registration offers 1st through 6th Year.

October 31 revision checks:

```powershell
& 'C:\xampp\php\php.exe' tests\profile-revisions.php
& 'C:\xampp\php\php.exe' tests\admin-edit-revisions.php
& 'C:\xampp\php\php.exe' tests\booking-revisions.php
node tests\appointment-live.js
```

These use disposable databases, synthetic accounts, and captured email transports. Profile checks exercise the production save handlers, including unchanged saves and priority-only changes. Staff checks exercise the production edit handler and the repeatable account/status data migration. Booking checks cover different services on one day, duplicate and simultaneous submissions, QR check-in through completion, ownership, legacy statuses, multiple offices, and rollback. Live refresh checks cover five-second polling, visibility/focus, failed requests, and reloading only after a status change.

`php tests/admin-edit-preview.php` generates `tests/fixtures/admin-edit-preview.html` with the production service/staff edit modals and Bootstrap/selectpicker controls. Change text and dropdowns, close with Cancel/Close, X, or Escape, and reopen: saved defaults and validation state must be restored. The preview never saves real data.

November 10 multi-service checks: `php tests/multi-service-revisions.php` exercises separate blocked-service rescheduling/QRs, per-office expiry and reporting, sibling preservation, duplicate/stale notices and rollback in a disposable database. `php tests/service-reports.php` verifies that all five office status filters and graphs agree for mixed bookings. `php tests/service-progress.php` checks the presentation summaries and escaped service details and generates `tests/fixtures/service-progress-preview.html` from production service-card code. No real messages or appointments are created by these tests. See `docs/multi-service-revisions-2026-11-10.md` for the resulting behavior and deployment file list.

November 11 checks: `node tests/graph-scales.js` checks the production chart options for empty, single, and larger counts and generates a graph preview. `php tests/service-reports.php` also verifies weekday-only graph totals and uncapped volume. `php tests/kiosk-hours.php` checks configured hours and the actual closed-request guard without database access. `php tests/student-advisories.php` exercises read-only student pages and staff role guards with synthetic sessions. The validation and student-service suites reject weekend bookings and rescheduling, while calendar range requests may start on a weekend. See `docs/weekday-kiosk-revisions-2026-11-11.md` for the kiosk testing switch and deployment file list.

The public `after-hours-testing.php` switch saves the existing configuration. `php tests/kiosk-settings.php` uses isolated temporary files to check enable/disable persistence, unchanged saves, preserved hours/comments, malformed configuration and write failures. With local Apache/MySQL running, `python tests/after-hours-http.py` checks anonymous access, request validation, redirects, state across sessions and the kiosk's testing banner. The HTTP test temporarily toggles testing and restores its original value in `finally`; it does not create appointments or send messages.
