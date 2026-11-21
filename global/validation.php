<?php
/** Shared validation for browser forms and direct HTTP requests. */
final class SmartServeValidation
{
    public const NAME_PATTERN = '[\\p{L}\\p{M} \\-]+';
    public const PHONE_PATTERN = '(?:09[0-9]{9}|\\+639[0-9]{9})';
    public const EMAIL_PATTERN = '[^@\\s]+@[A-Za-z0-9](?:[A-Za-z0-9\\-]*[A-Za-z0-9])?(?:\\.[A-Za-z0-9](?:[A-Za-z0-9\\-]*[A-Za-z0-9])?)*\\.[A-Za-z]{2,63}';
    public const SLOTS = ['07:00 AM', '08:00 AM', '09:00 AM', '10:00 AM', '11:00 AM', '01:00 PM', '02:00 PM', '03:00 PM', '04:00 PM'];
    public const BLOCK_HOURS = ['07:00', '08:00', '09:00', '10:00', '11:00', '12:00', '13:00', '14:00', '15:00', '16:00'];
    public const YEARS = ['1st Year', '2nd Year', '3rd Year', '4th Year', '5th Year', '6th Year'];

    public static function fieldLabel($field)
    {
        if ($field === 'new_password') return 'New Password';
        $labels = ['fname' => 'First Name', 'mname' => 'Middle Name', 'lname' => 'Last Name', 'uname' => 'Email Address', 'username' => 'Email Address', 'email' => 'Email Address', 'contact' => 'Phone Number', 'pword' => 'Password', 'cpass' => 'Current Password', 'npass' => 'New Password', 'rpass' => 'Confirm Password', 'confirm_password' => 'Confirm Password', 'year' => 'Year Level', 'course' => 'Degree Program', 'office_ids' => 'Offices / Departments', 'purpose_option' => 'Purpose of Visit', 'pwd_id' => 'PWD ID Photo', 'otp' => 'OTP Code'];
        $field = preg_replace('/^new_/', '', $field);
        return $labels[$field] ?? ucwords(str_replace(['_', '-'], ' ', $field));
    }
    public const PURPOSES = [1 => ['Medical Checkup', 'Dental', 'Injury/Wound Care'], 2 => ['Payment'], 3 => ['Valid ID', 'Teams/Tech Concern']];
    public const COURSES = ['Doctor of Veterinary Medicine', 'BS Agriculture', 'BS Food Technology', 'BS Accountancy', 'BSBA Financial Management', 'BSBA Human Resource Management', 'BSBA Marketing Management', 'BS Hotel & Restaurant Management', 'BS Tourism Management', 'AB Psychology', 'BS Computer Science', 'BS Computer Engineering', 'Bachelor of Elementary Education', 'Bachelor of Secondary Education', 'Bachelor of Early Childhood Education'];

    public static function length($value)
    {
        return mb_strlen($value, 'UTF-8');
    }

    public static function name($value, $required = true, $max = 50)
    {
        if (!is_string($value)) return false;
        $value = trim($value);
        if ($value === '') return !$required;
        return self::length($value) <= $max
            && preg_match('/\A' . self::NAME_PATTERN . '\z/u', $value) === 1
            && preg_match('/\p{L}/u', $value) === 1;
    }

    public static function phone($value)
    {
        if (!is_string($value)) return false;
        return preg_match('/\A' . self::PHONE_PATTERN . '\z/', $value) === 1;
    }

    public static function phoneKey($value)
    {
        if (!self::phone($value)) return null;
        return str_starts_with($value, '09') ? '+63' . substr($value, 1) : $value;
    }

    public static function email($value, $max = 50)
    {
        if (!is_string($value)) return false;
        $value = trim($value);
        if (strlen($value) > $max || !filter_var($value, FILTER_VALIDATE_EMAIL)) return false;
        return preg_match('/\.[A-Za-z]{2,63}\z/', substr(strrchr($value, '@'), 1)) === 1;
    }

    public static function password($value)
    {
        // Count user-visible Unicode code points while retaining bcrypt's storage limit.
        return is_string($value) && self::length($value) >= 8 && self::length($value) <= 72 && strlen($value) <= 72
            && preg_match('/[A-Za-z]/', $value) && preg_match('/[0-9]/', $value)
            && preg_match('/[^A-Za-z0-9\s]/u', $value) && !preg_match('/[\s\x00]/u', $value);
    }

    public static function text($value, $required = true, $max = 1000)
    {
        if (!is_string($value)) return false;
        $value = trim($value);
        return ($value !== '' || !$required) && self::length($value) <= $max
            && !preg_match('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', $value);
    }

    public static function id($value)
    {
        return (is_string($value) || is_int($value)) && preg_match('/\A[1-9][0-9]{0,9}\z/', (string)$value)
            && (int)$value <= 2147483647;
    }

    public static function date($value)
    {
        if (!is_string($value)) return false;
        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);
        return $date && $date->format('Y-m-d') === $value;
    }

    public static function dateTime($value)
    {
        if (!is_string($value)) return false;
        foreach (['Y-m-d\TH:i', 'Y-m-d\TH:i:s'] as $format) {
            $date = DateTimeImmutable::createFromFormat('!' . $format, $value);
            if ($date && $date->format($format) === $value) return true;
        }
        return false;
    }

    public static function bookingDate($value)
    {
        return self::bookingDateRange($value) && self::weekdayDate($value);
    }

    public static function bookingDateRange($value)
    {
        return self::date($value) && $value >= date('Y-m-d') && $value <= date('Y-m-d', strtotime('+7 days'));
    }

    public static function weekdayDate($value)
    {
        return self::date($value) && (int)(new DateTimeImmutable($value))->format('N') <= 5;
    }

    public static function blockHour($value)
    {
        return is_string($value) && in_array($value, self::BLOCK_HOURS, true);
    }

    public static function blockTimeRange($start, $end)
    {
        return self::blockHour($start) && self::blockHour($end) && $start < $end;
    }

    /** Returns field-specific errors without mutating input or accessing the database. */
    public static function request(array $data, $path)
    {
        $errors = [];
        $page = basename($path, '.php');
        $account = in_array($page, ['account-admins', 'account-offices', 'account-students'], true);
        $require = function (array $fields) use ($data, &$errors) {
            foreach ($fields as $field) {
                if (!array_key_exists($field, $data) || (!is_array($data[$field]) && trim((string)$data[$field]) === '') || $data[$field] === []) {
                    $errors[$field] = self::fieldLabel($field) . ' is required.';
                }
            }
        };

        foreach ($data as $field => $value) {
            if (is_array($value) && $field !== 'office_ids') {
                $errors[$field] = 'Please enter a single value for ' . str_replace('_', ' ', $field) . '.';
                continue;
            }
            if ($field === 'office_ids') {
                if (!is_array($value) || count($value) > 100 || array_filter($value, function ($id) { return !self::id($id); }) || count(array_unique($value, SORT_REGULAR)) !== count($value)) {
                    $errors[$field] = 'Please select valid offices.';
                }
                continue;
            }
            if (!is_string($value)) continue;
            $value = trim($value);
            if (($data['response_action'] ?? '') === 'cancel' && in_array($field, ['reschedule_date', 'reschedule_time'], true)) continue;
            if (($data['block_type'] ?? '') !== 'time_range' && in_array($field, ['start_time', 'end_time'], true)) continue;
            if (in_array($field, ['fname', 'mname', 'lname', 'new_fname', 'new_mname', 'new_lname', 'guest_name', 'manual_name_input'], true)) {
                if ($field === 'manual_name_input' && !empty($data['student_id'])) continue;
                $optional = in_array($field, ['mname', 'new_mname'], true);
                $max = $account ? 30 : ($field === 'guest_name' || $field === 'manual_name_input' ? 100 : 50);
                if (!self::name($value, !$optional, $max)) $errors[$field] = 'Names must contain letters, spaces, and hyphens only (maximum ' . $max . ' characters).';
            } elseif (in_array($field, ['contact', 'new_contact', 'phone', 'telephone', 'mobile'], true)) {
                if (!self::phone($data[$field])) $errors[$field] = 'Use 09XXXXXXXXX (11 characters) or +639XXXXXXXXX (13 characters), without spaces or other characters.';
            } elseif (in_array($field, ['uname', 'username', 'email'], true)) {
                if (!self::email($value)) $errors[$field] = 'Enter a valid email with a domain extension, such as sample@edu.ph (maximum 50 characters).';
                $institutional = $account && ($page === 'account-students' || ($page === 'account-offices' && !str_contains(str_replace('\\', '/', $path), '/admin/')));
                if ($institutional && !preg_match('/@g\.batstate-u\.edu\.ph\z/i', $value)) $errors[$field] = 'Please use your institutional email ending in @g.batstate-u.edu.ph.';
            } elseif (in_array($field, ['address', 'new_address'], true)) {
                if (!self::text($value, true, 120)) $errors[$field] = 'Address is required and must be at most 120 characters. Numbers and punctuation are allowed.';
            } elseif (in_array($field, ['office_name', 'office', 'course', 'title', 'question', 'answer', 'details', 'notes', 'office_notes', 'reason', 'note', 'custom_purpose', 'purpose_option', 'purpose', 'target_purpose'], true)) {
                $max = in_array($field, ['details', 'answer'], true) ? 10000 : (in_array($field, ['title', 'question', 'office_name', 'office', 'course', 'reason', 'target_purpose', 'purpose_option', 'purpose'], true) ? 255 : 1000);
                if (!self::text($value, false, $max)) $errors[$field] = ucfirst(str_replace('_', ' ', $field)) . ' must be at most ' . $max . ' characters.';
            } elseif (in_array($field, ['id', 'edit_id', 'edit-id', 'archive-id', 'approve_hidden', 'status_id', 'faq_id', 'office_id', 'student_id', 'blocked_row_id', 'ao_id'], true) && $value !== '') {
                if (!self::id($value)) $errors[$field] = 'Please select a valid record.';
            } elseif (in_array($field, ['appointment_date', 'reschedule_date', 'block_date', 'start_date', 'end_date', 'date'], true) && $value !== '') {
                $valid = $field === 'date' && $page === 'announcement' ? self::dateTime($value) : self::date($value);
                if (!$valid) $errors[$field] = 'Please enter a valid date.';
                elseif (in_array($field, ['appointment_date', 'reschedule_date'], true) && !self::bookingDate($value)) $errors[$field] = 'Choose a weekday (Monday to Friday) from today through the next seven days.';
                elseif ($field === 'block_date' && $value < date('Y-m-d')) $errors[$field] = 'A blocked schedule cannot be in the past.';
            } elseif (in_array($field, ['start_time', 'end_time'], true) && $value !== '') {
                if (!preg_match('/\A(?:[01][0-9]|2[0-3]):[0-5][0-9]\z/', $value)) $errors[$field] = 'Please enter a valid time.';
            } elseif (in_array($field, ['appointment_time', 'reschedule_time'], true) && $value !== '') {
                if (!in_array($value, self::SLOTS, true)) $errors[$field] = 'Please select an available appointment time.';
            } elseif ($field === 'otp') {
                if (!preg_match('/\A[0-9]{6}\z/', $value)) $errors[$field] = 'Enter the six-digit OTP code.';
            } elseif ($field === 'tracking_no') {
                if (!preg_match('/\A(?:ONL|WLK)-[0-9]{8}-[A-F0-9]{5}\z/', $value)) $errors[$field] = 'Please scan or enter a valid tracking number.';
            }
        }

        $enums = ['priority' => ['N/A', 'No', 'PWD', 'Senior Citizen', 'Pregnant'], 'is_priority' => ['0', '1'], 'block_type' => ['full_day', 'time_range', 'specific_purpose'], 'response_action' => ['cancel', 'reschedule'], 'sms_notify' => ['none', 'all_students', 'students_pending_today_my_office', 'all_pending_students'], 'email_notify' => ['none', 'all_students', 'students_pending_today_my_office', 'all_pending_students'], 'status_val' => ['0', '1'], 'window' => array_map(function ($i) { return 'Window ' . $i; }, range(1, 10))];
        foreach ($enums as $field => $values) {
            if (isset($data[$field]) && !in_array($data[$field], $values, true)) $errors[$field] = 'Please select a valid ' . str_replace('_', ' ', $field) . '.';
        }
        if (isset($data['submit_verify'])) {
            $require(['fname', 'lname', 'uname', 'contact', 'address', 'password', 'confirm_password', 'year', 'course']);
            if (!in_array($data['year'] ?? '', self::YEARS, true)) $errors['year'] = 'Year Level: please select 1st Year through 6th Year.';
            if (!in_array($data['course'] ?? '', self::COURSES, true)) $errors['course'] = 'Please select a valid program.';
        }
        if (isset($data['account_priority']) && !in_array($data['account_priority'], ['N/A', 'PWD', 'Senior Citizen', 'Pregnant'], true)) $errors['account_priority'] = 'Please select a valid priority type.';
        if ($account && (isset($data['add_user']) || isset($data['edit_user']))) {
            $require(['fname', 'lname', 'uname', 'contact', 'address']);
            if (isset($data['add_user'])) $require(['pword']);
            if (isset($data['edit_user'])) $require(['edit_id']);
            if ($page !== 'account-admins') $require(['office']);
            if ($page === 'account-offices' && str_contains(str_replace('\\', '/', $path), '/admin/')) {
                $require(['window']);
                if (!self::id($data['office'] ?? '')) $errors['office'] = 'Please select a valid office.';
            }
        }
        if ($page === 'login' && isset($data['submit'])) $require(['username', 'password']);
        foreach ($page === 'login' ? ['password'] : ['cpass'] as $passwordField) {
            if (isset($data[$passwordField]) && is_string($data[$passwordField]) && strlen($data[$passwordField]) > 72) $errors[$passwordField] = 'Password is too long. Please shorten it or use fewer accented characters or symbols.';
        }
        if (isset($data['save_profile'])) $require(['new_fname', 'new_lname', 'new_contact', 'new_address']);
        if (isset($data['send_otp'])) $require(['email']);
        if (isset($data['verify_otp'])) $require(['otp']);
        if (isset($data['save_pass'])) $require(['cpass', 'npass', 'rpass']);
        if (isset($data['reset_password'])) $require(['new_password', 'confirm_password']);

        $newPasswordField = isset($data['save_pass']) ? 'npass' : (isset($data['reset_password']) ? 'new_password' : (isset($data['submit_verify']) ? 'password' : ($account && isset($data['add_user']) ? 'pword' : null)));
        if ($newPasswordField) {
            if (!isset($errors[$newPasswordField]) && !self::password($data[$newPasswordField] ?? '')) {
                $value = $data[$newPasswordField] ?? '';
                $errors[$newPasswordField] = is_string($value) && self::length($value) <= 72 && strlen($value) > 72
                    ? 'Password is too long. Please shorten it or use fewer accented characters or symbols.'
                    : 'Password must be 8–72 characters and include a letter, a number, and a special character, without spaces.';
            }
            $confirmation = ['npass' => 'rpass', 'new_password' => 'confirm_password', 'password' => 'confirm_password'][$newPasswordField] ?? null;
            if ($confirmation && !isset($errors[$confirmation]) && ($data[$confirmation] ?? null) !== ($data[$newPasswordField] ?? null)) $errors[$confirmation] = 'Passwords do not match.';
        }
        if ($page === 'announcement' && (isset($data['add_announcement']) || isset($data['edit']))) {
            $require(['title', 'details', 'date']);
            if (isset($data['edit'])) $require(['edit-id']);
            if (isset($data['details']) && is_string($data['details']) && trim(html_entity_decode(strip_tags($data['details']))) === '') $errors['details'] = 'Announcement details are required.';
        }
        if (isset($data['add_faq']) || isset($data['edit_faq'])) $require(['question', 'answer']);
        if (isset($data['edit_faq']) || isset($data['delete_faq'])) $require(['faq_id']);
        if (isset($data['add_office']) || isset($data['edit_office'])) $require(['office_name']);
        if (isset($data['edit_office'])) $require(['edit_id']);
        if (isset($data['update_status'])) $require(['status_id', 'status_val']);
        if (isset($data['archive']) || isset($data['restore'])) $require([$account ? 'approve_hidden' : 'archive-id']);

        $action = $data['action'] ?? '';
        if (($action === 'submit_walkin' || isset($data['submit_appointment'])) && isset($data['purpose_option'])) {
            $selectedOffices = $action === 'submit_walkin' ? [$data['office_id'] ?? ''] : ($data['office_ids'] ?? []);
            $purposes = ['Others'];
            if (is_array($selectedOffices)) foreach ($selectedOffices as $office) {
                if (self::id($office)) $purposes = array_merge($purposes, self::PURPOSES[(int)$office] ?? []);
            }
            if (!in_array($data['purpose_option'], $purposes, true)) $errors['purpose_option'] = 'Please select a purpose offered by your selected office.';
        }
        if ($action === 'submit_walkin') $require(['guest_name', 'office_id', 'purpose_option']);
        if (isset($data['submit_walkin'])) {
            $require(['notes']);
            if (empty($data['student_id'])) $require(['manual_name_input']);
        }
        if ($action === 'verify_qr') $require(['tracking_no', 'office_id']);
        if ($action === 'scan_qr') $require(['tracking_no']);
        if (in_array($action, ['start_serving', 'complete_serving', 'no_show'], true)) $require(['ao_id']);
        if (isset($data['submit_appointment'])) $require(['office_ids', 'appointment_date', 'appointment_time', 'purpose_option']);
        if (isset($data['fetch_slots']) || isset($data['check_purpose_block'])) {
            $require(['date']);
            if (!self::bookingDate($data['date'] ?? '')) $errors['date'] = 'Choose a weekday (Monday to Friday) within the next seven days.';
        }
        if (($data['purpose_option'] ?? '') === 'Others' && ($action === 'submit_walkin' || isset($data['submit_appointment']))) $require(['custom_purpose']);
        if (isset($data['submit_block_response'])) {
            $require(['blocked_row_id', 'response_action']);
            if (($data['response_action'] ?? '') === 'reschedule') $require(['reschedule_date', 'reschedule_time']);
        }
        if (isset($data['submit_block'])) {
            $require(['block_type', 'block_date', 'reason']);
            if (($data['block_type'] ?? '') === 'time_range') {
                $require(['start_time', 'end_time']);
                foreach (['start_time', 'end_time'] as $field) {
                    if (!self::blockHour($data[$field] ?? null)) $errors[$field] = 'Select an hourly time from 7 AM to 4 PM.';
                }
                if (self::blockHour($data['start_time'] ?? null) && self::blockHour($data['end_time'] ?? null) && $data['start_time'] >= $data['end_time']) $errors['end_time'] = 'End time must be later than start time.';
            }
            if (($data['block_type'] ?? '') === 'specific_purpose') $require(['target_purpose']);
        }
        if (!empty($data['start_date']) && !empty($data['end_date']) && is_string($data['start_date']) && is_string($data['end_date']) && $data['start_date'] > $data['end_date']) $errors['end_date'] = 'End date must be on or after start date.';
        return $errors;
    }

    public static function image($file, $required = false)
    {
        if (!$file) return $required ? 'Please select a JPG or PNG image.' : null;
        if (!is_array($file) || !isset($file['error'], $file['size'], $file['tmp_name']) || is_array($file['error']) || is_array($file['tmp_name'])) return 'Invalid image upload.';
        if ($file['error'] === UPLOAD_ERR_NO_FILE) return $required ? 'Please select a JPG or PNG image.' : null;
        if ($file['error'] !== UPLOAD_ERR_OK) return 'Image upload failed. Please try again (maximum 2 MB).';
        if ($file['size'] > 2 * 1024 * 1024) return 'Image must be at most 2 MB.';
        if (!is_string($file['tmp_name']) || !is_file($file['tmp_name'])) return 'Invalid image upload.';
        $info = @getimagesize($file['tmp_name']);
        $mime = (new finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']);
        if (!$info || !in_array($mime, ['image/jpeg', 'image/png'], true) || ($info['mime'] ?? '') !== $mime) return 'Only valid JPG and PNG images are allowed.';
        return null;
    }
}

function smartserve_asset_url($file)
{
    $root = str_replace('\\', '/', realpath(__DIR__ . '/..'));
    $documentRoot = str_replace('\\', '/', realpath($_SERVER['DOCUMENT_ROOT'] ?? __DIR__ . '/..'));
    $base = $documentRoot && str_starts_with(strtolower($root), strtolower(rtrim($documentRoot, '/') . '/')) ? substr($root, strlen(rtrim($documentRoot, '/'))) : '';
    return $base . '/assets/' . $file;
}

function smartserve_form_assets()
{
    static $rendered = false;
    if ($rendered) return;
    $rendered = true;
    $styleUrl = smartserve_asset_url('css/forms.css') . '?v=' . filemtime(__DIR__ . '/../assets/css/forms.css');
    echo '<link rel="stylesheet" href="' . htmlspecialchars($styleUrl, ENT_QUOTES) . '">';
    $scriptUrl = smartserve_asset_url('js/forms.js') . '?v=' . filemtime(__DIR__ . '/../assets/js/forms.js');
    echo '<script src="' . htmlspecialchars($scriptUrl, ENT_QUOTES) . '"></script>';
}

/** Redirects happen only after the user dismisses the notification. */
function smartserve_notify($message, $type = 'danger', $redirect = null)
{
    smartserve_form_assets();
    $payload = json_encode(['message' => $message, 'type' => $type, 'redirect' => $redirect], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_INVALID_UTF8_SUBSTITUTE);
    echo '<script>SmartServe.notifyFromServer(' . $payload . ');</script>';
}

function smartserve_validate_request()
{
    $path = $_SERVER['SCRIPT_NAME'] ?? '';
    $isPost = ($_SERVER['REQUEST_METHOD'] ?? '') === 'POST';
    $isFilter = ($_SERVER['REQUEST_METHOD'] ?? '') === 'GET' && in_array(basename($path, '.php'), ['reports', 'reports-graph', 'history'], true);
    if (!$isPost && !$isFilter) return;
    $data = $isPost ? $_POST : $_GET;
    $errors = SmartServeValidation::request($data, $path);
    if (isset($_FILES['pwd_id']) || (isset($data['submit_verify']) && ($data['account_priority'] ?? '') === 'PWD')) {
        $error = SmartServeValidation::image($_FILES['pwd_id'] ?? null, isset($data['submit_verify']) && ($data['account_priority'] ?? '') === 'PWD');
        if ($error) $errors['pwd_id'] = 'PWD ID: ' . $error;
    }
    $allowedStatuses = in_array(basename($path, '.php'), ['reports', 'reports-graph'], true) ? ['0', '1', '2', '3', '4'] : ['1', '2', '3', '4'];
    if (isset($data['status']) && $data['status'] !== '' && !in_array($data['status'], $allowedStatuses, true)) $errors['status'] = 'Please select a valid status.';
    if (isset($_FILES['image']) || (basename($path, '.php') === 'update-photo' && isset($_POST['submit'])) || isset($_POST['add_announcement'])) {
        $error = SmartServeValidation::image($_FILES['image'] ?? null, isset($_POST['add_announcement']) || basename($path, '.php') === 'update-photo');
        if ($error) $errors['image'] = $error;
    }
    if (!$errors) {
        foreach ($_POST as $field => $value) {
            if (!is_string($value) || in_array($field, ['password', 'pword', 'cpass', 'npass', 'new_password', 'confirm_password', 'rpass'], true)) continue;
            $_POST[$field] = trim($value);
            if (in_array($field, ['uname', 'username', 'email'], true)) $_POST[$field] = strtolower($_POST[$field]);
        }
        return;
    }
    $isAjax = in_array($_POST['action'] ?? '', ['verify_qr', 'scan_qr', 'submit_walkin', 'check_purpose_block', 'start_serving', 'complete_serving', 'no_show'], true) || isset($_POST['fetch_slots']) || isset($_POST['check_purpose_block']);
    if ($isAjax) {
        while (ob_get_level()) ob_end_clean();
        header('Content-Type: application/json; charset=utf-8');
        // Existing AJAX callers expect a JSON error response on the success callback.
        echo json_encode(['status' => 'error', 'ok' => false, 'blocked' => true, 'msg' => implode("\n", $errors), 'message' => implode("\n", $errors), 'errors' => $errors]);
    } else {
        http_response_code(422);
        echo '<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Please check your form</title>';
        smartserve_form_assets();
        echo '</head><body class="ss-validation-page"><main><h1>Please check your form</h1><p>Your submission could not be saved. Correct the highlighted details and try again.</p><button type="button" onclick="history.back()">Return to form</button></main>';
        smartserve_notify(implode("\n", array_unique($errors)), 'danger', 'back');
        echo '</body></html>';
    }
    exit;
}
