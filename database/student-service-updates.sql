-- Apply once before deploying the student service updates (MariaDB).
ALTER TABLE accounts
 ADD COLUMN IF NOT EXISTS priority_type VARCHAR(30) NOT NULL DEFAULT 'N/A',
 ADD COLUMN IF NOT EXISTS pwd_id_path VARCHAR(80) DEFAULT NULL,
 ADD COLUMN IF NOT EXISTS pwd_status ENUM('none','pending','approved','rejected') NOT NULL DEFAULT 'none',
 ADD COLUMN IF NOT EXISTS pwd_reviewed_by INT DEFAULT NULL,
 ADD COLUMN IF NOT EXISTS pwd_reviewed_at DATETIME DEFAULT NULL;
ALTER TABLE announcements
 ADD COLUMN IF NOT EXISTS published_at DATETIME DEFAULT NULL,
 ADD COLUMN IF NOT EXISTS expires_at DATETIME DEFAULT NULL,
 ADD COLUMN IF NOT EXISTS archived_at DATETIME DEFAULT NULL,
 ADD COLUMN IF NOT EXISTS archive_reason VARCHAR(30) DEFAULT NULL;
UPDATE announcements SET published_at = COALESCE(date, NOW()) WHERE published_at IS NULL;
UPDATE announcements SET expires_at = DATE_ADD(published_at, INTERVAL 1 MONTH) WHERE expires_at IS NULL;
ALTER TABLE appointments
 ADD COLUMN IF NOT EXISTS sms_claimed_at DATETIME DEFAULT NULL;
UPDATE appointments SET priority = 'N/A' WHERE priority = 'No';
