Minor revisions — 21 October 2026
================================

Homepage reminders now offer Do not show again. This stores a preference in the current browser; I Understand still permits reminders on a later visit. The reminders use the configured ten-minute arrival deadline instead of a hardcoded fifteen minutes.

Required-field messages identify the missing field, mark it red and scroll/focus the first invalid field after the message is closed. Office-group errors sit outside the selectable cards, preserving the checkbox/card relationship after validation. Cashier remains selectable and offers Payment after an incomplete submission.

Registration accepts 1st through 6th Year in the browser and request validator. The existing year column accepts these values; no schema migration is required.

Shared form setup adds accessible show/hide buttons to password fields that do not already have a toggle. This covers Forgot Password and the student, office and admin Update Password screens used from profiles. Existing login/registration toggles are retained.

My Appointments displays pending schedule-block notices with the affected office, original schedule, reason and office note. Review update opens the existing reschedule/cancel response form. Cancelled appointments and notices already handled do not appear.

Deploy these files together, preserving their paths:

```
assets/css/forms.css
assets/js/forms.js
assets/js/home-reminders.js
global/validation.php
index.php
register.php
student/booking.php
student/history.php
student/partials/blocked-appointment-notices.php
```

The update package excludes database and SMTP/SMS credentials. Keep the hosting configuration in place. It assumes the earlier SmartServe updates, including global/appointment-rules.php and the blocked-schedule database tables, are already deployed. The homepage script and shared form assets use modification-time cache versions.
