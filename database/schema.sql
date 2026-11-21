-- SmartServe database structure only. No live records or credentials.
SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS=0;

CREATE TABLE `accounts` (
  `id` int(11) NOT NULL,
  `fname` varchar(100) NOT NULL,
  `mname` varchar(100) DEFAULT NULL,
  `lname` varchar(100) NOT NULL,
  `position` varchar(100) NOT NULL,
  `uname` varchar(100) NOT NULL,
  `pword` varchar(100) NOT NULL,
  `contact` varchar(100) DEFAULT NULL,
  `address` varchar(200) DEFAULT NULL,
  `photo` varchar(100) NOT NULL,
  `access` int(11) NOT NULL,
  `year` varchar(200) DEFAULT NULL,
  `course` varchar(100) DEFAULT NULL,
  `date_created` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `status` int(11) NOT NULL,
  `priority_type` varchar(30) NOT NULL DEFAULT 'N/A',
  `pwd_id_path` varchar(80) DEFAULT NULL,
  `pwd_status` enum('none','pending','approved','rejected') NOT NULL DEFAULT 'none',
  `pwd_reviewed_by` int(11) DEFAULT NULL,
  `pwd_reviewed_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `announcements` (
  `id` int(11) NOT NULL,
  `title` varchar(200) NOT NULL,
  `details` mediumtext NOT NULL,
  `price` mediumtext DEFAULT NULL,
  `image` varchar(300) NOT NULL,
  `image_unique` varchar(300) NOT NULL,
  `date` datetime DEFAULT NULL,
  `account_id` int(20) DEFAULT NULL,
  `category` int(11) NOT NULL,
  `status` int(11) NOT NULL,
  `redirect` varchar(100) DEFAULT NULL,
  `published_at` datetime DEFAULT NULL,
  `expires_at` datetime DEFAULT NULL,
  `archived_at` datetime DEFAULT NULL,
  `archive_reason` varchar(30) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `appointments` (
  `id` int(11) NOT NULL,
  `tracking_no` varchar(50) NOT NULL,
  `student_id` int(11) DEFAULT NULL,
  `guest_name` varchar(100) DEFAULT NULL,
  `is_priority` tinyint(1) NOT NULL DEFAULT 0,
  `priority` varchar(100) DEFAULT NULL,
  `appointment_date` date NOT NULL,
  `appointment_time` varchar(20) NOT NULL,
  `notes` text DEFAULT NULL,
  `is_walkin` tinyint(1) NOT NULL DEFAULT 0 COMMENT '1=Walk-in, 0=Online',
  `status` int(11) NOT NULL DEFAULT 0 COMMENT '0=Pending, 1=Approved, 2=Completed, 3=Cancelled',
  `date_created` datetime NOT NULL DEFAULT current_timestamp(),
  `sms_notif` int(11) DEFAULT NULL,
  `blocked_schedule_id` int(11) DEFAULT NULL,
  `reschedule_status` enum('none','pending','rescheduled','cancelled') NOT NULL DEFAULT 'none',
  `reschedule_requested_at` datetime DEFAULT NULL,
  `reschedule_responded_at` datetime DEFAULT NULL,
  `rescheduled_from_id` int(11) DEFAULT NULL,
  `sms_claimed_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `appointment_offices` (
  `id` int(11) NOT NULL,
  `tracking_no` varchar(50) NOT NULL,
  `office_id` int(11) NOT NULL,
  `queue_number` varchar(20) DEFAULT NULL COMMENT 'e.g., REG-001',
  `status` int(11) NOT NULL DEFAULT 0 COMMENT '0=Pending Arrival, 1=Queued, 2=Serving, 3=Completed, 4=No Show',
  `time_queued` datetime DEFAULT NULL COMMENT 'When they scanned QR at THIS office',
  `time_started` datetime DEFAULT NULL COMMENT 'When THIS office called them',
  `time_completed` datetime DEFAULT NULL COMMENT 'When THIS office finished',
  `office_notes` text DEFAULT NULL COMMENT 'Notes from THIS specific office',
  `served_at_window` varchar(50) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `blocked_schedules` (
  `id` int(11) NOT NULL,
  `office_id` int(11) NOT NULL,
  `block_date` date NOT NULL,
  `start_time` time DEFAULT NULL,
  `end_time` time DEFAULT NULL,
  `block_type` enum('full_day','time_range','specific_slot','specific_purpose') NOT NULL DEFAULT 'full_day',
  `target_purpose` varchar(100) DEFAULT NULL,
  `reason` varchar(150) NOT NULL,
  `note` text DEFAULT NULL,
  `status` int(11) NOT NULL DEFAULT 1,
  `created_by` int(11) NOT NULL,
  `sms_sent` int(11) DEFAULT 0,
  `sms_sent_at` datetime DEFAULT NULL,
  `date_created` datetime NOT NULL DEFAULT current_timestamp(),
  `date_updated` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `blocked_schedule_appointments` (
  `id` int(11) NOT NULL,
  `block_id` int(11) NOT NULL,
  `appointment_id` int(11) NOT NULL,
  `tracking_no` varchar(50) NOT NULL,
  `student_id` int(11) DEFAULT NULL,
  `office_id` int(11) NOT NULL,
  `original_date` date NOT NULL,
  `original_time` varchar(20) NOT NULL,
  `action_status` enum('pending','notified','reschedule_requested','rescheduled','cancelled') NOT NULL DEFAULT 'pending',
  `notified_at` datetime DEFAULT NULL,
  `response_at` datetime DEFAULT NULL,
  `new_appointment_id` int(11) DEFAULT NULL,
  `reschedule_date_choice` date DEFAULT NULL,
  `reschedule_time_choice` varchar(20) DEFAULT NULL,
  `date_created` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `email_outbox` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `event_key` char(64) NOT NULL,
  `recipient` varchar(254) NOT NULL,
  `subject` varchar(255) NOT NULL,
  `html_body` longtext NOT NULL,
  `text_body` longtext NOT NULL,
  `context` text NOT NULL,
  `status` enum('pending','processing','sent','failed','skipped') NOT NULL DEFAULT 'pending',
  `attempts` tinyint(3) UNSIGNED NOT NULL DEFAULT 0,
  `available_at` datetime NOT NULL DEFAULT current_timestamp(),
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp(),
  `sent_at` datetime DEFAULT NULL,
  `last_error` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `faqs` (
  `faq_id` int(11) NOT NULL,
  `question` longtext NOT NULL,
  `answer` longtext NOT NULL,
  `status` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `login_attempts` (
  `id` int(11) NOT NULL,
  `username` varchar(255) NOT NULL,
  `ip_address` varchar(45) NOT NULL,
  `attempts` int(11) NOT NULL DEFAULT 1,
  `last_attempt` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `offices` (
  `id` int(11) NOT NULL,
  `office_name` varchar(255) NOT NULL,
  `status` int(11) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `web_details` (
  `web_id` int(11) NOT NULL,
  `web_name` varchar(70) NOT NULL,
  `web_code` varchar(30) NOT NULL,
  `web_header` longtext NOT NULL,
  `web_url` varchar(60) NOT NULL,
  `admin_email` varchar(40) NOT NULL,
  `admin_pword` varchar(20) NOT NULL,
  `primary_color` varchar(10) NOT NULL,
  `secondary_color` varchar(10) NOT NULL,
  `web_icon` varchar(100) NOT NULL,
  `date_updated` datetime NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

ALTER TABLE `accounts`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uname` (`uname`);

ALTER TABLE `announcements`
  ADD PRIMARY KEY (`id`);

ALTER TABLE `appointments`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `tracking_no` (`tracking_no`),
  ADD KEY `idx_appointments_blocked_schedule_id` (`blocked_schedule_id`),
  ADD KEY `idx_appointments_reschedule_status` (`reschedule_status`),
  ADD KEY `fk_appointments_rescheduled_from` (`rescheduled_from_id`);

ALTER TABLE `appointment_offices`
  ADD PRIMARY KEY (`id`);

ALTER TABLE `blocked_schedules`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_blocked_schedules_office_date` (`office_id`,`block_date`),
  ADD KEY `idx_blocked_schedules_status` (`status`),
  ADD KEY `fk_blocked_schedules_created_by` (`created_by`);

ALTER TABLE `blocked_schedule_appointments`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_block_appointment` (`block_id`,`appointment_id`),
  ADD KEY `idx_bsa_block_id` (`block_id`),
  ADD KEY `idx_bsa_appointment_id` (`appointment_id`),
  ADD KEY `idx_bsa_tracking_no` (`tracking_no`),
  ADD KEY `idx_bsa_student_id` (`student_id`),
  ADD KEY `idx_bsa_office_id` (`office_id`),
  ADD KEY `idx_bsa_action_status` (`action_status`),
  ADD KEY `fk_bsa_new_appointment` (`new_appointment_id`);

ALTER TABLE `email_outbox`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `event_key` (`event_key`),
  ADD KEY `email_delivery` (`status`,`available_at`);

ALTER TABLE `faqs`
  ADD PRIMARY KEY (`faq_id`);

ALTER TABLE `login_attempts`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `login_username_ip` (`username`,`ip_address`);

ALTER TABLE `offices`
  ADD PRIMARY KEY (`id`);

ALTER TABLE `web_details`
  ADD PRIMARY KEY (`web_id`);

ALTER TABLE `accounts`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=1;

ALTER TABLE `announcements`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=1;

ALTER TABLE `appointments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=1;

ALTER TABLE `appointment_offices`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=1;

ALTER TABLE `blocked_schedules`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=1;

ALTER TABLE `blocked_schedule_appointments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=1;

ALTER TABLE `email_outbox`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=1;

ALTER TABLE `faqs`
  MODIFY `faq_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=1;

ALTER TABLE `login_attempts`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=1;

ALTER TABLE `offices`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=1;

ALTER TABLE `web_details`
  MODIFY `web_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=1;

ALTER TABLE `appointments`
  ADD CONSTRAINT `fk_appointments_blocked_schedule` FOREIGN KEY (`blocked_schedule_id`) REFERENCES `blocked_schedules` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_appointments_rescheduled_from` FOREIGN KEY (`rescheduled_from_id`) REFERENCES `appointments` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;

ALTER TABLE `blocked_schedules`
  ADD CONSTRAINT `fk_blocked_schedules_created_by` FOREIGN KEY (`created_by`) REFERENCES `accounts` (`id`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_blocked_schedules_office` FOREIGN KEY (`office_id`) REFERENCES `offices` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

ALTER TABLE `blocked_schedule_appointments`
  ADD CONSTRAINT `fk_bsa_appointment` FOREIGN KEY (`appointment_id`) REFERENCES `appointments` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_bsa_block` FOREIGN KEY (`block_id`) REFERENCES `blocked_schedules` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_bsa_new_appointment` FOREIGN KEY (`new_appointment_id`) REFERENCES `appointments` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_bsa_office` FOREIGN KEY (`office_id`) REFERENCES `offices` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_bsa_student` FOREIGN KEY (`student_id`) REFERENCES `accounts` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;

SET FOREIGN_KEY_CHECKS=1;
