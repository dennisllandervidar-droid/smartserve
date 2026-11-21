Local database recovery — October 13, 2026
=========================================

MariaDB could not start because its Aria recovery log was damaged. Before repair, the full data directory was copied to:

`C:\xampp\mysql\smartserve-recovery-20261013-185325\data`

Original Aria logs were retained in the backup's `original-aria-logs` directory. Damaged system tables were repaired. The three database grant records were recovered from the pre-existing grant-table backup and restored; table checks now pass. No school tables were replaced. Counts before and after the application migration and captured tests were 27 accounts, 13 appointments, and 9 advisories.

The application migration in `database/student-service-updates.sql` was applied to the local SmartServe database. Test databases were isolated from live records. The recovery copy is kept outside the web root.
