-- Apply before deploying the login countdown update (MariaDB).
-- A unique key prevents concurrent failures from losing attempt increments.
ALTER TABLE login_attempts ADD UNIQUE INDEX IF NOT EXISTS login_username_ip (username, ip_address);
