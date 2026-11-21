Student service updates
=======================

Apply `database/student-service-updates.sql` once before deployment. The migration preserves accounts, appointments, and advisory history; existing `No` priority values become `N/A`.

PWD registration accepts a clear JPG or PNG ID photo up to 2 MB. Images are stored under `storage/private/pwd`, blocked from direct web access, and served only through the authenticated `pwd-id.php` endpoint. Admin and office staff can inspect a student's ID from the profile, queue, and history. Only admin can approve or reject a pending ID. A pending or rejected applicant can still make regular bookings. Replacing an ID requires another review. Approval and rejection send branded email notifications. Priority is derived from the verified account when registered students book or walk in; submitted priority values cannot override it.

Advisories expire one calendar month after publication. Expired records move to archived history instead of being deleted. Admin can see all archived advisories; office users can see their own archived advisories. Active advisory reads and the notification worker apply expiry automatically. Publication time is preserved when an advisory is edited.

Online booking and rescheduling are limited to today through seven days ahead. Calendar and direct date-entry modes share the same validation. The booking calendar disables dates with no available times and grays out blocked or fully reserved times. Office block calendars show existing reservations so staff can inspect the affected schedule before blocking and notifying students. The booking Confirm Request button displays missing-detail messages for each required step.

Schedule `C:\xampp\php\php.exe C:\xampp\htdocs\smartserve\cron-sms.php` once per minute using the server scheduler. This CLI worker sends SMS reminders within ten minutes before an appointment, performs ten-minute no-scan cancellations, archives advisories, and dispatches email. `cron-email.php` can be used separately if only email is needed. Do not schedule both SMS workers concurrently. No scheduler task is installed by the application.

The SMS sender reads `global/sms.local.php` (ignored by source control) or `SMARTSERVE_SMS_API_KEY` and `SMARTSERVE_SMS_DEVICE_ID`. Only a successful Textbee acceptance response marks the reminder as sent; failures stay eligible for retry. An SMS claim prevents overlapping workers from repeating a send. Gateway acceptance is distinct from delivery to the recipient's phone. The paired Android phone must remain connected with its SIM and SMS permission available. Run `php tests/sms-health.php` for a read-only device check; it does not send SMS or print credentials.

Verification commands:

```
php tests/student-services.php
php tests/registration-priority.php
php tests/appointment-expiration.php
php tests/email-notifications.php
php tests/validation.php
node tests/validation.js
python tests/http-validation.py
```

Integration tests use disposable databases and captured notification transports. No live accounts are created and no real messages are sent by those tests.
