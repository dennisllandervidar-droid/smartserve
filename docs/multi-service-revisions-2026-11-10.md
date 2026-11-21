# Multi-service booking review — 10 November 2026

The client's concern was valid: the previous blocked-schedule response moved or cancelled the entire appointment. It could reset other office visits and could not issue a separate QR for just the affected service. Student details also lacked a per-service breakdown, and a ticket with mixed terminal results could be labelled Completed.

## Resulting behavior

- Booking Details and My Appointments show each service's status, along with a completed-service count. Details include queue/window information, completion time, office notes, blocking reason, and replacement-ticket links where applicable.
- An unfinished multi-service ticket remains Pending or In Progress. Only a ticket whose services all completed displays Completed. A closed ticket containing completed and cancelled/rescheduled services displays Partially completed. Rescheduled tickets link back to their original booking.
- Responding to a blocked service affects that office only. Rescheduling creates a new appointment, tracking number, and QR containing only that service. The original visit is marked Rescheduled and links to the replacement. Dates, completion records, queue state, and notes for sibling services remain intact, including when another service is currently serving the student.
- A blocked service awaiting a response is held and is not treated as a no-show. It cannot be checked in or called while that hold is active. Unblocked services still have independent ten-minute check-in deadlines. The replacement ticket uses its new scheduled date/time and ten-minute deadline.
- Repeated responses, wrong-student responses, stale notices for completed visits, and replacement dates with an existing active booking for the same service are rejected. Failed saves roll back the replacement and original visit changes together.
- Overlapping block notices for the same visit resolve together. A newer disabled block does not hide an older active notice.

## Reports

Reports and graphs count individual office visits. For a booking with Cashier completed and Clinic cancelled, Cashier appears in Completed and Clinic appears in Cancelled / No-show / Rescheduled. A rescheduled original visit is labelled Rescheduled with its replacement tracking number; the replacement has its own status and schedule.

The graph previously excluded pending and cancelled visits even when those filters were selected. It now agrees with the report records. Filter labels distinguish Pending arrival, Queued, Serving, Completed, and Cancelled / No-show / Rescheduled.

## Database and verification

The existing `appointments.rescheduled_from_id` and `blocked_schedule_appointments.new_appointment_id` columns provide the parent/replacement links. No schema migration or live appointment rewrite was needed. The local read-only check found no closed parent tickets with unfinished visits.

Passed 61 multi-service integration checks, 18 service-report checks, 28 service-presentation checks, and 16 live-refresh checks. Existing suites also passed: 78 booking/status, 36 expiry, 186 notification, 129 student-service, 247 PHP validation, and 193 browser-validation checks. Integration tests use disposable databases and captured notification transports. The browser preview verified completed + blocked + queued, completed + rescheduled, and all-completed displays using production service cards with synthetic data.

These changes are local; the hosted site has not been updated. Deploy the following together, preserving paths and existing hosting credentials:

```text
global/model.php
global/appointment-rules.php
global/validation.php
global/email.php
student/booking.php
student/booking-details.php
student/history.php
student/partials/service-progress.php
student/partials/blocked-appointment-notices.php
assets/css/appointment-services.css
admin/reports.php
admin/reports-graph.php
office/history.php
office/block-schedules.php
```

The existing `assets/js/appointment-live.js` is still required for live refresh. Existing installations must already have the blocked-schedule tables and rescheduling-link columns used by the earlier application.
