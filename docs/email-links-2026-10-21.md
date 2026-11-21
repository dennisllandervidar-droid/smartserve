Email notification link update — 21 October 2026
==============================================

Upload these two files together into the existing site's global/ folder:

```
global/email.php
global/email-template.php
```

The default notification URL is https://dlsua-smartserve.site. Legacy localhost/loopback URLs, including stale app_url settings and older queued email buttons, are upgraded to that domain. Destination pages, appointment tracking numbers, queries and fragments are retained.

Emails display a named button such as View your appointment or Sign in to SmartServe. The raw URL beneath the button and the plain-text action URL are removed. All account, appointment, reminder, blocked-schedule and advisory notifications use the shared template. Password recovery continues to display its OTP.

Older pending and failed queue entries are corrected when the existing email worker next sends/retries them. Already delivered emails remain unchanged and are not resent. No database migration is needed. Keep the hosted SMTP credentials and existing cron job in place; this package contains no credentials.

Verified using the rendered email preview, standalone link checks, capture-only notification tests in a disposable database, and appointment expiration checks. No real email or SMS was sent.
