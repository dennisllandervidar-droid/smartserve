<?php

require_once __DIR__ . '/email-template.php';
require_once __DIR__ . '/appointment-rules.php';
require_once __DIR__ . '/../Phpmailer/src/Exception.php';
require_once __DIR__ . '/../Phpmailer/src/PHPMailer.php';
require_once __DIR__ . '/../Phpmailer/src/SMTP.php';

function smartserve_mail_config() {
    static $config;
    if ($config !== null) return $config;
    $local = is_file(__DIR__ . '/email.local.php') ? require __DIR__ . '/email.local.php' : [];
    $read = function ($env, $key, $default = '') use ($local) {
        $value = getenv($env);
        return $value !== false ? $value : ($local[$key] ?? $default);
    };
    $config = [
        'host' => $read('SMARTSERVE_SMTP_HOST', 'host', 'smtp.gmail.com'),
        'port' => (int)$read('SMARTSERVE_SMTP_PORT', 'port', 587),
        'username' => $read('SMARTSERVE_SMTP_USERNAME', 'username'),
        'password' => $read('SMARTSERVE_SMTP_PASSWORD', 'password'),
        'encryption' => $read('SMARTSERVE_SMTP_ENCRYPTION', 'encryption', 'tls'),
        'from' => $read('SMARTSERVE_MAIL_FROM', 'from', $read('SMARTSERVE_SMTP_USERNAME', 'username')),
        'app_url' => rtrim(SmartServeEmailTemplate::actionUrl($read('SMARTSERVE_APP_URL', 'app_url', SmartServeEmailTemplate::APP_URL)) ?? SmartServeEmailTemplate::APP_URL, '/'),
        'autosend' => $read('SMARTSERVE_MAIL_AUTOSEND', 'autosend', '1') !== '0',
    ];
    return $config;
}

function smartserve_mailer() {
    $config = smartserve_mail_config();
    if (!$config['username'] || !$config['password']) throw new RuntimeException('SMTP configuration is missing.');
    $mail = new \PHPMailer\PHPMailer\PHPMailer(true);
    $mail->isSMTP();
    $mail->Host = $config['host'];
    $mail->SMTPAuth = true;
    $mail->Username = $config['username'];
    $mail->Password = $config['password'];
    $mail->SMTPSecure = $config['encryption'];
    $mail->Port = $config['port'];
    $mail->Timeout = 8;
    $mail->CharSet = 'UTF-8';
    $mail->setFrom($config['from'], 'SmartServe');
    $mail->addEmbeddedImage(__DIR__ . '/../assets/img/icon.png', 'smartserve-school-logo', 'dlsau-logo.png');
    $mail->isHTML(true);
    return $mail;
}

function smartserve_send_email($recipient, $subject, $html, $text) {
    [$html, $text] = SmartServeEmailTemplate::deliveryBodies($html, $text);
    $mail = smartserve_mailer();
    $mail->addAddress($recipient);
    $mail->Subject = $subject;
    $mail->Body = $html;
    $mail->AltBody = $text;
    return $mail->send();
}

/** Durable notifications; only a successful database change should call these methods. */
final class SmartServeEmails {
    private $db;
    private $transport;
    private $requestIds = [];

    public function __construct(mysqli $db, $transport = null) {
        $this->db = $db;
        $this->transport = $transport;
        // Initialize before business transactions; DDL must never commit a booking transaction.
        $this->db->query(file_get_contents(__DIR__ . '/../database/email-outbox.sql'));
        if (PHP_SAPI !== 'cli' && smartserve_mail_config()['autosend'] && $transport === null) {
            register_shutdown_function(function () {
                if (function_exists('fastcgi_finish_request')) fastcgi_finish_request();
                // Limit the request work. The cron worker drains remaining broadcasts/retries.
                try {
                    $this->dispatch(5, array_slice($this->requestIds, 0, 5));
                } catch (Throwable $e) {
                    error_log('SmartServe: email delivery deferred to the cron worker.');
                }
            });
        }
    }

    private function one($query, $types, ...$params) {
        $stmt = $this->db->prepare($query);
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        return $row;
    }

    private function url($path) {
        return smartserve_mail_config()['app_url'] . '/' . ltrim($path, '/');
    }

    public function enqueue($key, $recipient, array $message, array $context = []) {
        if (!filter_var($recipient, FILTER_VALIDATE_EMAIL) || !preg_match('/@[^@]+\.[a-z]{2,}$/i', $recipient)) return false;
        try {
            $key = hash('sha256', $key);
            $subject = $message['title'] . ' | SmartServe';
            $html = SmartServeEmailTemplate::render($message);
            $text = SmartServeEmailTemplate::text($message);
            $context = json_encode($context, JSON_THROW_ON_ERROR);
            $stmt = $this->db->prepare('INSERT IGNORE INTO email_outbox (event_key, recipient, subject, html_body, text_body, context) VALUES (?, ?, ?, ?, ?, ?)');
            $stmt->bind_param('ssssss', $key, $recipient, $subject, $html, $text, $context);
            $ok = $stmt->execute();
            if ($stmt->affected_rows === 1) $this->requestIds[] = $this->db->insert_id;
            $stmt->close();
            return $ok;
        } catch (Throwable $e) {
            error_log('SmartServe: unable to queue an email notification (' . get_class($e) . ').');
            return false;
        }
    }

    public function account($id, $event) {
        $account = $this->one('SELECT id, fname, mname, lname, uname, access FROM accounts WHERE id = ?', 'i', $id);
        if (!$account) return false;
        $messages = [
            'welcome' => ['Your SmartServe account is ready', 'Your account has been created. You can sign in to SmartServe using your registered email address.'],
            'activated' => ['Your account is active', 'Your SmartServe account has been activated. You can now sign in.'],
            'deactivated' => ['Your account has been deactivated', 'Your SmartServe account is currently inactive. Please contact the school office if you need assistance.'],
            'password' => ['Your password has been changed', 'The password for your SmartServe account was successfully changed.'],
            'pwd_approved' => ['Your PWD priority is approved', 'Admin has verified your PWD ID. PWD priority will now apply automatically to your new appointments.'],
            'pwd_rejected' => ['Your PWD ID needs another review', 'Admin could not approve the submitted PWD ID. You can still book as a regular student. Please upload a clear, valid PWD ID from Update Profile to request another review.'],
        ];
        if (!isset($messages[$event])) return false;
        [$title, $intro] = $messages[$event];
        if ($event === 'cancelled' && $officeId !== null) {
            $title = 'Your service visit is cancelled';
            $intro = 'Your visit to the office listed below has been cancelled. Your other services keep their existing schedules and progress.';
        }
        $message = ['title' => $title, 'name' => trim($account['fname'] . ' ' . $account['lname']), 'intro' => $intro,
            'details' => ['Account email' => $account['uname']], 'action_label' => 'Sign in to SmartServe', 'action_url' => $this->url('login')];
        if ($event === 'password') $message['paragraphs'] = ['If you did not make this change, reset your password immediately or contact the school office for help.'];
        return $this->enqueue('account:' . $id . ':' . $event . ':' . bin2hex(random_bytes(12)), $account['uname'], $message);
    }

    public function appointment($tracking, $event, $officeId = null, array $extra = [], $eventKey = null) {
        $a = $this->one('SELECT a.*, acc.fname, acc.lname, acc.uname FROM appointments a JOIN accounts acc ON a.student_id = acc.id WHERE a.tracking_no = ?', 's', $tracking);
        if (!$a) return false; // Guests have no email address; never guess a recipient.
        $stmt = $this->db->prepare('SELECT ao.*, o.office_name FROM appointment_offices ao JOIN offices o ON ao.office_id = o.id WHERE ao.tracking_no = ?');
        $stmt->bind_param('s', $tracking);
        $stmt->execute();
        $offices = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        $selected = null;
        foreach ($offices as $office) if ((int)$office['office_id'] === (int)$officeId) $selected = $office;
        if ($officeId !== null && !$selected) return false;
        $messages = [
            'booked' => ['Your appointment is confirmed', 'Your appointment has been booked successfully. Please review your schedule below.'],
            'cancelled' => ['Your appointment is cancelled', 'Your appointment has been cancelled. You can book a new appointment when you are ready.'],
            'queued' => ['You are checked in', 'You have joined the office queue. Please wait for your queue number to be called.'],
            'serving' => ['Your queue number has been called', 'Please proceed to the office or service window shown below.'],
            'completed' => ['Your appointment is completed', 'Your visit to the office has been marked as completed. Thank you for using SmartServe.'],
            'no_show' => ['Your appointment was marked as a no-show', 'This office appointment is no longer in the active queue. Please contact the office if you need help or book another schedule.'],
            'expired' => ['Your appointment has been cancelled', 'Your appointment at this office was automatically cancelled because you did not check in within ' . SmartServeAppointmentRules::ARRIVAL_GRACE_MINUTES . ' minutes of your scheduled time. Please book a new schedule if you still need assistance.'],
            'reminder' => ['Your appointment is coming up', 'Your appointment starts soon. Please go to the campus kiosk and scan your appointment QR code to check in.'],
            'blocked' => ['Your appointment schedule has changed', 'This office has blocked your scheduled service visit. Open SmartServe to reschedule or cancel this service only. Your other services keep their existing schedules and progress.'],
            'rescheduled' => ['Your service visit has been rescheduled', 'A separate booking has been created for the affected service. Use its new tracking number and QR code for this service at the date and time below. Your other services keep their existing schedules and progress.'],
        ];
        if (!isset($messages[$event])) return false;
        [$title, $intro] = $messages[$event];
        $details = ['Date' => date('F j, Y', strtotime($a['appointment_date'])), 'Time' => date('h:i A', strtotime($a['appointment_time'])),
            'Office' => $selected ? $selected['office_name'] : implode(', ', array_column($offices, 'office_name')),
            'Purpose of visit' => $a['notes']];
        if ($selected && in_array($event, ['queued', 'serving', 'completed', 'no_show'], true)) {
            $details['Queue number'] = $selected['queue_number'];
            if ($event !== 'queued') $details['Service window'] = $selected['served_at_window'];
            $details['Office notes'] = $selected['office_notes'];
        }
        $message = ['title' => $title, 'name' => trim($a['fname'] . ' ' . $a['lname']), 'intro' => $intro,
            'highlight_label' => 'Appointment tracking number', 'highlight' => $tracking, 'details' => array_merge($details, $extra),
            'action_label' => 'View your appointment', 'action_url' => $this->url('student/booking-details?id=' . rawurlencode($tracking))];
        if (in_array($event, ['booked', 'rescheduled'], true)) $message['paragraphs'] = ['Please arrive on time and scan your appointment QR code at the campus kiosk to receive your queue number. Sign in to view your QR code and appointment details.'];
        if ($event === 'rescheduled') $message['action_label'] = 'View new service booking and QR';
        if ($event === 'blocked') {
            $message['action_label'] = 'Manage your appointment';
            $message['action_url'] = $this->url('student/booking');
        }
        $key = $eventKey ?? ('appointment:' . $a['id'] . ':' . $event . ':' . ($officeId ?? 'all') . ':' . $a['appointment_date'] . ':' . $a['appointment_time']);
        return $this->enqueue($key, $a['uname'], $message, ['event' => $event, 'appointment_id' => (int)$a['id'], 'date' => $a['appointment_date'], 'time' => $a['appointment_time']]);
    }

    public function blocked($blockId, $tracking, $officeId) {
        $block = $this->one('SELECT reason, note FROM blocked_schedules WHERE id = ?', 'i', $blockId);
        if (!$block) return false;
        return $this->appointment($tracking, 'blocked', $officeId, ['Reason' => $block['reason'], 'Office advisory' => $block['note']], 'blocked:' . $blockId . ':' . $tracking . ':' . $officeId);
    }

    public function announcement($id, $scope, $officeId = 0) {
        if ($scope === 'none') return 0;
        if (!in_array($scope, ['all_students', 'students_pending_today_my_office', 'all_pending_students'], true)) return 0;
        if ($scope === 'students_pending_today_my_office' && $officeId <= 0) return 0;
        $advisory = $this->one('SELECT title, details, date FROM announcements WHERE id = ? AND status = 1', 'i', $id);
        if (!$advisory) return 0;
        $sql = 'SELECT acc.id, acc.fname, acc.lname, acc.uname FROM accounts acc WHERE acc.access = 3 AND acc.status = 1';
        if ($scope !== 'all_students') {
            $sql .= ' AND EXISTS (SELECT 1 FROM appointments a JOIN appointment_offices ao ON a.tracking_no = ao.tracking_no WHERE a.student_id = acc.id AND a.status = 0 AND ao.status IN (0,1)';
            if ($scope === 'students_pending_today_my_office') $sql .= ' AND a.appointment_date = CURDATE() AND ao.office_id = ' . (int)$officeId;
            else $sql .= ' AND a.appointment_date >= CURDATE()';
            $sql .= ')';
        }
        $count = 0;
        foreach ($this->db->query($sql) as $account) {
            $message = ['title' => 'School advisory: ' . $advisory['title'], 'name' => trim($account['fname'] . ' ' . $account['lname']),
                'intro' => $advisory['details'], 'details' => ['Advisory date' => date('F j, Y h:i A', strtotime($advisory['date']))],
                'action_label' => 'Read school advisories', 'action_url' => $this->url('student/announcement')];
            if ($this->enqueue('advisory:' . $id . ':' . $account['id'], $account['uname'], $message)) $count++;
        }
        return $count;
    }

    public function reminders() {
        $time = "TIMESTAMP(a.appointment_date, TIME(COALESCE(STR_TO_DATE(a.appointment_time, '%h:%i %p'), STR_TO_DATE(a.appointment_time, '%H:%i:%s'), STR_TO_DATE(a.appointment_time, '%H:%i'))))";
        $rows = $this->db->query("SELECT a.tracking_no FROM appointments a WHERE a.status = 0 AND a.is_walkin = 0 AND a.student_id IS NOT NULL AND $time > NOW() AND $time <= DATE_ADD(NOW(), INTERVAL 16 MINUTE) AND EXISTS (SELECT 1 FROM appointment_offices ao WHERE ao.tracking_no = a.tracking_no AND ao.status = 0)");
        foreach ($rows as $row) $this->appointment($row['tracking_no'], 'reminder');
    }

    public function dispatch($limit = 50, $ids = null) {
        $limit = max(1, min(100, (int)$limit));
        $stats = ['sent' => 0, 'failed' => 0, 'skipped' => 0];
        if ($ids !== null && !$ids) return $stats;
        $this->db->query("UPDATE email_outbox SET status = 'failed', last_error = 'Delivery interrupted after the final attempt; review before retrying.', updated_at = NOW() WHERE status = 'processing' AND attempts >= 5 AND updated_at < DATE_SUB(NOW(), INTERVAL 10 MINUTE)");
        $eligible = "((status IN ('pending','failed') AND attempts < 5 AND available_at <= NOW()) OR (status = 'processing' AND updated_at < DATE_SUB(NOW(), INTERVAL 10 MINUTE) AND attempts < 5))";
        $where = $ids === null ? '' : ' AND id IN (' . implode(',', array_map('intval', $ids)) . ')';
        $rows = $this->db->query("SELECT * FROM email_outbox WHERE $eligible $where ORDER BY id ASC LIMIT $limit");
        foreach ($rows as $row) {
            $id = (int)$row['id'];
            $this->db->query("UPDATE email_outbox SET status = 'processing', attempts = attempts + 1, updated_at = NOW() WHERE id = $id AND $eligible");
            if ($this->db->affected_rows !== 1) continue; // Another worker already claimed it.
            $context = json_decode($row['context'], true) ?: [];
            if (($context['event'] ?? '') === 'reminder') {
                $a = $this->one('SELECT appointment_date, appointment_time, status, tracking_no FROM appointments WHERE id = ?', 'i', $context['appointment_id']);
                $pending = $a ? $this->one('SELECT id FROM appointment_offices WHERE tracking_no = ? AND status = 0 LIMIT 1', 's', $a['tracking_no']) : null;
                if (!$a || !$pending || (int)$a['status'] !== 0 || $a['appointment_date'] !== $context['date'] || $a['appointment_time'] !== $context['time'] || strtotime($a['appointment_date'] . ' ' . $a['appointment_time']) <= time()) {
                    $this->db->query("UPDATE email_outbox SET status = 'skipped', updated_at = NOW() WHERE id = $id");
                    $stats['skipped']++;
                    continue;
                }
            }
            try {
                [$html, $text] = SmartServeEmailTemplate::deliveryBodies($row['html_body'], $row['text_body']);
                if ($html !== $row['html_body'] || $text !== $row['text_body']) {
                    $update = $this->db->prepare("UPDATE email_outbox SET html_body = ?, text_body = ? WHERE id = ? AND status = 'processing'");
                    $update->bind_param('ssi', $html, $text, $id);
                    $update->execute();
                    $update->close();
                    $row['html_body'] = $html;
                    $row['text_body'] = $text;
                }
                $ok = $this->transport ? call_user_func($this->transport, $row) : smartserve_send_email($row['recipient'], $row['subject'], $row['html_body'], $row['text_body']);
                if (!$ok) throw new RuntimeException('Delivery failed.');
                $this->db->query("UPDATE email_outbox SET status = 'sent', sent_at = NOW(), updated_at = NOW(), last_error = NULL WHERE id = $id");
                $stats['sent']++;
            } catch (Throwable $e) {
                $delay = min(60, 2 ** ((int)$row['attempts'] + 1));
                $this->db->query("UPDATE email_outbox SET status = 'failed', last_error = 'SMTP delivery failed; check server mail configuration and logs.', available_at = DATE_ADD(NOW(), INTERVAL $delay MINUTE), updated_at = NOW() WHERE id = $id");
                error_log('SmartServe email #' . $id . ': delivery failed (' . get_class($e) . ').');
                $stats['failed']++;
            }
        }
        return $stats;
    }
}
