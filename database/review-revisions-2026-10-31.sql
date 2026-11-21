-- Run against the SmartServe database. Safe to run again.
-- Only staff/admin login emails are corrected; passwords and student emails stay intact.
START TRANSACTION;

UPDATE accounts
SET uname = REPLACE(uname, 'dlsua', 'dlsau'), date_created = date_created
WHERE access IN (1, 2) AND BINARY uname LIKE '%dlsua%';

-- Reconcile older finished tickets with their office visits.
-- Active and office-less tickets retain their existing state.
UPDATE appointments a
JOIN (
    SELECT tracking_no,
        CASE WHEN SUM(status = 3) > 0 THEN 2 ELSE 3 END AS final_status
    FROM appointment_offices
    GROUP BY tracking_no
    HAVING SUM(status IN (0, 1, 2)) = 0
) visits ON visits.tracking_no = a.tracking_no
SET a.status = visits.final_status
WHERE a.status <> visits.final_status;

COMMIT;
