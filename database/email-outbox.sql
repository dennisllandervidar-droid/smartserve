CREATE TABLE IF NOT EXISTS email_outbox (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    event_key CHAR(64) NOT NULL UNIQUE,
    recipient VARCHAR(254) NOT NULL,
    subject VARCHAR(255) NOT NULL,
    html_body LONGTEXT NOT NULL,
    text_body LONGTEXT NOT NULL,
    context TEXT NOT NULL,
    status ENUM('pending','processing','sent','failed','skipped') NOT NULL DEFAULT 'pending',
    attempts TINYINT UNSIGNED NOT NULL DEFAULT 0,
    available_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    sent_at DATETIME NULL,
    last_error VARCHAR(255) NULL,
    INDEX email_delivery (status, available_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
