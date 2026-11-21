# Revision review — 31 October 2026

All requested revisions were checked against the local PHP application and MariaDB schema.

- Unchanged student, staff, and admin profile submissions show `No changes to save.` without a database write. Actual edits still save; student priority and ID replacement count as changes.
- Unchanged staff edits use an informational message instead of success. Editing preserves the existing capitalization of names and addresses.
- Closing an Edit Staff or Edit Service modal restores its saved fields and styled dropdowns, clears validation state, and discards the abandoned edits.
- Password confirmation labels say `Confirm Password`. Requirements use characters and correctly count Unicode characters toward the minimum. The existing bcrypt length protection remains in place with a separate shorten-password message.
- Students can book different services on the same day. A second active booking for the same service/date is rejected, including simultaneous submissions. Completed or cancelled service visits permit another booking.
- The homepage, guide, student appointment details, report cancellation notes, and cancellation logic use the shared ten-minute arrival deadline. The separate thirty-minute advance-booking cutoff remains unchanged.
- Student details and history derive their status from the office visits. Scanned/serving visits appear as `In Progress`; finished visits appear as `Completed`. A ticket covering multiple offices stays active until every visit is finished or cancelled. Visible student pages check for changes every five seconds and on focus, reloading only when their data changes.
- Staff completion and parent ticket completion are saved together. A stale student cancellation cannot cancel a visit currently being served and leave its service window stuck.
- The profile navigation label is `Profile` for all three roles.

## Local database application

Applied `database/review-revisions-2026-10-31.sql` to the local `smartserve` database. Four login emails were corrected:

- `itservices@dlsau.edu.ph`
- `clinic@dlsau.edu.ph`
- `cashier@dlsau.edu.ph`
- `itcid@dlsau.edu.ph`

Passwords, student emails, account creation timestamps, account counts, and appointment counts were preserved. One older finished ticket was reconciled with its office completion status. There are no remaining staff/admin email typos or finished-ticket status mismatches. The actual notification website domain was left as configured.

The existing tables support the revision; no new columns are required. The migration changes only the requested account emails and finished ticket statuses, and is safe to repeat. A conflicting corrected email must be resolved before retrying a failed migration.

## Verification

Verified the production staff/service modal controls in a browser using synthetic data: changing text and office/window selections, closing, and reopening restores saved values, including after a validation error. PHP syntax checks pass across the application and PHP tests. Targeted automated checks cover profile edits, staff edits and migration, password validation, concurrent bookings, student status refresh, phone uniqueness, ten-minute expiry boundaries, and notification/transaction rollback. Tests use disposable databases and captured transports; no real email or SMS is sent.

The affected account, appointment, service, blocked-schedule, and email tables all return `OK` from MariaDB `CHECK TABLE`. The local MariaDB startup log still contains pre-existing InnoDB log-sequence errors, including the `smartserve/appointments` tablespace. No recovery-mode setting, database file replacement, or destructive database repair was performed as part of these revisions; the underlying server errors are a separate maintenance concern despite the passing table checks.

## Hosting

These changes are applied locally; the hosted site has not been deployed. Deploy these application files together and run the SQL migration against the hosting database after backup:

```text
global/model.php
global/validation.php
assets/js/forms.js
assets/js/edit-modal-reset.js
assets/js/appointment-live.js
admin/account-offices.php
admin/office-management.php
admin/update-profile.php
admin/update-password.php
admin/main/admin-nav.php
office/update-profile.php
office/update-password.php
office/main/admin-nav.php
student/update-profile.php
student/update-password.php
student/main/admin-nav.php
student/booking.php
student/booking-details.php
student/history.php
guide.php
database/review-revisions-2026-10-31.sql
```

Keep the existing configured appointment worker running once per minute so expiry is processed even when no page is open.
