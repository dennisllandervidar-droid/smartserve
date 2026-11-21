SmartServe uses one email layout in `global/email-template.php`. Every notification has the university seal in its header and footer, “WELCOME TO SmartServe,” and “De La Salle Araneta University.” The seal is embedded in the message, so recipients do not need access to a localhost image URL. Messages include an HTML body and a plain-text alternative.

Notifications cover account creation, activation/deactivation, password changes, password recovery codes, appointment confirmation/cancellation/rescheduling, check-in (including registered student walk-ins), queue calls, completion, no-show/late expiration, blocked schedules, upcoming appointment reminders, and school advisories. Guest walk-ins have no email field and therefore do not receive emails. Advisory forms have separate **Email Notify** and **SMS Notify** recipient choices; selecting **None** disables that channel.

SMTP settings are shared by all messages. The previously configured password-recovery sender has been moved to `global/email.local.php`, which is ignored by Git. Configure these environment variables, or copy `global/email.local.example.php` to `global/email.local.php`:

| Environment variable | Purpose |
| --- | --- |
| `SMARTSERVE_SMTP_HOST` | SMTP server (default `smtp.gmail.com`) |
| `SMARTSERVE_SMTP_PORT` | SMTP port (default `587`) |
| `SMARTSERVE_SMTP_ENCRYPTION` | `tls` for STARTTLS or `ssl` for SMTPS |
| `SMARTSERVE_SMTP_USERNAME` | SMTP account |
| `SMARTSERVE_SMTP_PASSWORD` | SMTP app password |
| `SMARTSERVE_MAIL_FROM` | Sender email (defaults to SMTP account) |
| `SMARTSERVE_APP_URL` | Public SmartServe URL used by email buttons |
| `SMARTSERVE_MAIL_AUTOSEND` | Set to `0` to disable automatic delivery after web requests |

Email action links default to `https://dlsua-smartserve.site`. `SMARTSERVE_APP_URL` takes precedence over `app_url` in the local configuration; absent or invalid settings fall back to the deployed URL. Legacy localhost/loopback settings and queued links are automatically upgraded to the deployed HTTPS domain, removing the old `/smartserve` prefix and preserving the destination path, tracking number, query and fragment. Never commit SMTP credentials.

The HTML email displays a labeled action button only. The former "Or open" paragraph and raw URLs in the plain-text alternative have been removed. Before dispatch, pending/retry messages compiled by an older version receive the same link cleanup, and their stored delivery bodies are updated. Messages already marked sent are not changed or resent. OTP emails keep their verification code without an unnecessary link.

Routine notifications are stored in `email_outbox`. The application initializes this table from `database/email-outbox.sql` before workflow transactions. Web requests attempt up to five newly queued emails after the response is generated. Remaining broadcasts and failed deliveries are processed by the worker. Delivery failures do not undo saved appointments. Each event has a unique key; repeated submissions and reminder runs do not enqueue duplicates. Failed SMTP deliveries retry up to five times, with increasing delays. A crashed worker's claim can be recovered after ten minutes. As with ordinary SMTP queues, a crash after SMTP acceptance but before recording success can cause a repeated delivery.

Run the following command **once per minute** with the server's task scheduler or cron:

```powershell
& 'C:\xampp\php\php.exe' 'C:\xampp\htdocs\smartserve\cron-email.php'
```

`cron-email.php` is CLI-only and processes up to 50 due messages per run. If `cron-sms.php` is already scheduled once per minute, it now also processes email reminders and the outbox; a separate email job is optional. Reminders are queued within 16 minutes before the scheduled time, independently of SMS flags. Cancelled, rescheduled, checked-in, or expired reminders are skipped before delivery. Recovery codes are sent immediately and expire after ten minutes; they are never stored in the outbox.

Online office appointments that have not checked in are cancelled at the ten-minute arrival deadline (a 7 AM booking expires at 7:10 AM). Checked-in students and walk-ins keep their queue positions. The office queue checks every 15 seconds while open, and the scheduled email/SMS worker applies the same rule across offices when the dashboard is closed. Both QR check-in paths enforce the deadline too. Each affected office visit queues one cancellation email using the shared template, with its tracking number, schedule, office, and ten-minute reason. The student's overall ticket becomes cancelled when all its office visits are cancelled; active visits at other offices remain available.

History and admin report reads also reconcile overdue pending visits, including legacy cancelled tickets whose office visits remained pending. Student history and booking details refresh the deadline before displaying statuses. Only unconfirmed online visits expire; checked-in, serving, completed and walk-in visits are preserved.

For cancellation without dispatching email or SMS, schedule `C:\xampp\php\php.exe C:\xampp\htdocs\smartserve\cron-appointments.php` once per minute. This queues the usual cancellation emails for the existing email worker. A current-user Windows task named `SmartServe Appointment Expiration` is configured on this development PC using `php-win.exe` so it runs without a console window while the user is signed in. The deployment server needs its own minute-by-minute worker; if `cron-email.php` or `cron-sms.php` is already scheduled there, it already handles cancellation.

The local database also required `database/blocked-purpose.sql`: the schedule-block form submits `specific_purpose`, while its old database enum accepted `specific_slot`. This migration adds the missing value and preserves existing values. Apply it when deploying to another database so purpose-specific blocks and their notifications work.

Inspect `email_outbox.status`, `attempts`, and `last_error` for delivery problems. Fix SMTP configuration before retrying exhausted rows (set `status = 'pending'`, `attempts = 0`, and `available_at = NOW()` for the specific reviewed rows). Retain sent messages only as long as needed for operations because the table contains appointment details. Database credentials must allow the application to initialize the table, or an administrator can apply `database/email-outbox.sql` first.

Tests use an isolated disposable database and a capture transport; they never send email or SMS:

```powershell
& 'C:\xampp\php\php.exe' tests\email-notifications.php
& 'C:\xampp\php\php.exe' tests\email-links.php
& 'C:\xampp\php\php.exe' tests\appointment-expiration.php
& 'C:\xampp\php\php.exe' tests\email-preview.php
```

The preview generator writes `tests/fixtures/email-preview.html` and `tests/fixtures/email-otp-preview.html` using the same template as delivered mail.
