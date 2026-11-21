<?php

require_once __DIR__ . '/validation.php';
require_once __DIR__ . '/email.php';

date_default_timezone_set('Asia/Manila');
smartserve_validate_request();

class Model {

    public const LOGIN_MAX_ATTEMPTS = 5;
    public const LOGIN_COOLDOWN_SECONDS = 300;

    private $server;
    private $username;
    private $password;
    private $dbname;

    private $conn;
    private $emails;
    private $profileUpdateError = 'Update failed, please try again.';

    public function __construct() {
        $local = is_file(__DIR__ . '/database.local.php') ? require __DIR__ . '/database.local.php' : [];
        $read = static function ($env, $key, $default = '') use ($local) {
            $value = getenv($env);
            return $value !== false ? $value : ($local[$key] ?? $default);
        };
        $this->server = $read('SMARTSERVE_DB_HOST', 'host', 'localhost');
        $this->username = $read('SMARTSERVE_DB_USER', 'username', 'root');
        $this->password = $read('SMARTSERVE_DB_PASSWORD', 'password');
        $this->dbname = $read('SMARTSERVE_DB_NAME', 'database', 'smartserve');
        try {
            $this->conn = new mysqli($this->server, $this->username, $this->password, $this->dbname);    
            $this->conn->set_charset('utf8mb4');
            // 1. SET PHP TIMEZONE TO PHILIPPINES
            date_default_timezone_set('Asia/Manila');
            
            // 2. SET MYSQL TIMEZONE TO +08:00 (Philippine Time)
            $this->conn->query("SET time_zone = '+08:00'");
            try {
                $this->emails = new SmartServeEmails($this->conn);
            } catch (Throwable $e) {
                error_log('SmartServe: email queue initialization failed (' . get_class($e) . ').');
            }
        } 
        catch (Exception $e) {
            echo "Connection failed" . $e->getMessage();
        }
    }

    private function emailEvent($method, ...$args) {
        try {
            return $this->emails ? $this->emails->$method(...$args) : false;
        } catch (Throwable $e) {
            error_log('SmartServe: email notification failed (' . get_class($e) . ').');
            return false;
        }
    }

    public function runEmailNotifications() {
        $this->autoArchiveAdvisories();
        $this->autoCancelExpiredAppointments();
        if (!$this->emails) return ['sent' => 0, 'failed' => 0, 'skipped' => 0, 'error' => 'Email queue unavailable'];
        $this->emailEvent('reminders');
        return $this->emailEvent('dispatch');
    }

    public function notifyAnnouncementEmail($id, $scope, $office_id = 0) {
        return $this->emailEvent('announcement', $id, $scope, $office_id);
    }

    public function signIn($uname, $pword) {
        $uname = trim($uname);
        $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
        if ($this->isLocked($uname, $ip)) {
            return "All 5 login attempts have been used. Please wait for the five-minute cooldown to finish.";
        }
        $query = "SELECT id, access, pword FROM accounts WHERE uname = ? LIMIT 1";
        if ($stmt = $this->conn->prepare($query)) {
            $stmt->bind_param("s", $uname);
            $stmt->execute();
            $stmt->store_result();
            if ($stmt->num_rows === 1) {
                $stmt->bind_result($id, $access, $hashed_pass);
                $stmt->fetch();
                if (password_verify($pword, $hashed_pass)) {
                    if ($this->isLocked($uname, $ip)) {
                        $stmt->close();
                        return "All 5 login attempts have been used. Please wait for the five-minute cooldown to finish.";
                    }
                    $this->clearAttempts($uname, $ip);
                    unset($_SESSION['login_attempt_username'], $_SESSION['login_error']);
                    session_regenerate_id(true);
                    $_SESSION['sess'] = $id;
                    $_SESSION['username'] = $uname;
                    $_SESSION['access'] = $access;
                    $_SESSION['last_login'] = time();
                    switch ($access) {
                        case 1: header("Location: admin/index"); break;
                        case 2: header("Location: office/index"); break;
                        case 3: header("Location: student/index"); break;
                        default: return "Access level not recognized.";
                    }
                    exit();
                } 
                else {
                    $this->recordFailedLogin($uname, $ip);
                    return "Invalid username or password.";
                }
            } 
            else {
                $this->recordFailedLogin($uname, $ip);
                return "Invalid username or password.";
            }
            $stmt->close();
        }
        $this->conn->close();
        return "Something went wrong. Please try again.";
    }

    private function recordFailedLogin($uname, $ip) {
        // The unique email/IP key makes increments atomic. Requests already in
        // progress cannot extend a cooldown after another request uses attempt 5.
        $stmt = $this->conn->prepare("INSERT INTO login_attempts (username, ip_address, attempts, last_attempt) VALUES (?, ?, 1, NOW())
            ON DUPLICATE KEY UPDATE last_attempt=IF(attempts < 5,NOW(),last_attempt), attempts=LEAST(5,attempts+1)");
        $stmt->bind_param("ss", $uname, $ip);
        $stmt->execute();
        $stmt->close();
    }

    public function getLoginAttemptStatus($uname, $ip = null) {
        $ip = $ip ?? ($_SERVER['REMOTE_ADDR'] ?? '127.0.0.1');
        $uname = trim($uname);
        $state = ['attempts'=>0, 'remaining'=>self::LOGIN_MAX_ATTEMPTS, 'locked'=>false, 'unlock_at'=>0, 'server_now'=>time()];
        if ($uname === '') return $state;
        $stmt = $this->conn->prepare("DELETE FROM login_attempts WHERE username=? AND ip_address=? AND attempts >= 5 AND last_attempt <= DATE_SUB(NOW(), INTERVAL 300 SECOND)");
        $stmt->bind_param("ss", $uname, $ip);
        $stmt->execute();
        $stmt->close();
        $stmt = $this->conn->prepare("SELECT attempts, UNIX_TIMESTAMP(last_attempt) AS last_attempt, UNIX_TIMESTAMP(NOW()) AS server_now FROM login_attempts WHERE username=? AND ip_address=? LIMIT 1");
        $stmt->bind_param('ss', $uname, $ip);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if ($row) {
            $state['server_now'] = (int)$row['server_now'];
            $state['attempts'] = min(self::LOGIN_MAX_ATTEMPTS, (int)$row['attempts']);
            $state['remaining'] = self::LOGIN_MAX_ATTEMPTS - $state['attempts'];
            $state['locked'] = $state['attempts'] >= self::LOGIN_MAX_ATTEMPTS;
            if ($state['locked']) $state['unlock_at'] = (int)$row['last_attempt'] + self::LOGIN_COOLDOWN_SECONDS;
        }
        return $state;
    }

    private function isLocked($uname, $ip) {
        return $this->getLoginAttemptStatus($uname, $ip)['locked'];
    }

    private function clearAttempts($uname, $ip) {
        $stmt = $this->conn->prepare("DELETE FROM login_attempts WHERE username=? AND ip_address=?");
        $stmt->bind_param("ss", $uname, $ip);
        $stmt->execute();
        $stmt->close();
    }

    public function displayAccountDetails($id) {
        $data = null;
        $query = "SELECT * FROM accounts WHERE id = ?";
        if ($stmt = $this->conn->prepare($query)) {
            $stmt->bind_param('i', $id);
            $stmt->execute();
            $result = $stmt->get_result();
            while ($row = $result->fetch_assoc()) {
                $data[] = $row;
            }
            $stmt->close();
        }    
        return $data;
    }
    
    public function displayOfficeAccountDetails2($id) {
        $data = null;
        // Grab everything from accounts, plus the office_name from the offices table
        $query = "SELECT a.*, o.office_name 
                  FROM accounts a 
                  LEFT JOIN offices o ON a.course = o.id 
                  WHERE a.id = ?";
                  
        if ($stmt = $this->conn->prepare($query)) {
            $stmt->bind_param('i', $id);
            $stmt->execute();
            $result = $stmt->get_result();
            while ($row = $result->fetch_assoc()) {
                $data[] = $row;
            }
            $stmt->close();
        }    
        return $data;
    }
    
    public function displayStudentAccounts($access) {
        $data = [];
        $query = "SELECT a.*, p.sr_code, p.year, p.course, p.birthdate, p.nationality, p.gender, p.height, p.weight, p.fat_name, p.fat_occupation, p.mot_name, p.mot_occupation, p.adviser_id, (SELECT CONCAT(fname, ' ', lname) FROM accounts WHERE id = p.adviser_id) AS adviser_name FROM accounts a INNER JOIN acc_student_profile p ON a.student_id = p.student_id WHERE a.access = ?";
        if ($stmt = $this->conn->prepare($query)) {
            $stmt->bind_param('i', $access);
            if ($stmt->execute()) {
                $result = $stmt->get_result();
                if ($result->num_rows > 0) {
                    while ($row = $result->fetch_assoc()) {
                        $data[] = $row;
                    }
                }
            }
            $stmt->close();
        }
        return $data;
    }

    public function displayStudentDetails($id) {
        $data = null;
        $query = "
            SELECT 
                a.*, 
                p.sr_code, 
                p.year, 
                p.course, 
                p.birthdate, 
                p.nationality, 
                p.gender, 
                p.height, 
                p.weight, 
                p.fat_name, 
                p.fat_occupation, 
                p.mot_name, 
                p.mot_occupation, 
                p.adviser_id,
                (SELECT CONCAT(fname, ' ', lname) FROM accounts WHERE id = p.adviser_id) AS adviser_name
            FROM accounts a
            INNER JOIN acc_student_profile p ON a.student_id = p.student_id
            WHERE a.id = ? AND a.access = '3'
            LIMIT 1";

        if ($stmt = $this->conn->prepare($query)) {
            $stmt->bind_param('i', $id); 
            if ($stmt->execute()) {
                $result = $stmt->get_result();
                if ($row = $result->fetch_assoc()) {
                    $data = $row;
                }
            }
            $stmt->close();
        }
        return $data;
    }

    public function displayStudentDetailsSrCode($sr_code) {
        $data = null;
        $query = "
            SELECT 
                a.*, 
                p.sr_code, 
                p.year, 
                p.course, 
                p.birthdate, 
                p.nationality, 
                p.gender, 
                p.height, 
                p.weight, 
                p.fat_name, 
                p.fat_occupation, 
                p.mot_name, 
                p.mot_occupation, 
                p.adviser_id,
                (SELECT CONCAT(fname, ' ', lname) FROM accounts WHERE id = p.adviser_id) AS adviser_name
            FROM accounts a
            INNER JOIN acc_student_profile p ON a.student_id = p.student_id
            WHERE a.id = ? AND a.access = '3'
            LIMIT 1";

        if ($stmt = $this->conn->prepare($query)) {
            $stmt->bind_param('i', $sr_code); 
            if ($stmt->execute()) {
                $result = $stmt->get_result();
                if ($row = $result->fetch_assoc()) {
                    $data = $row;
                }
            }
            $stmt->close();
        }
        return $data;
    }

    public function displayAdminAccountDetails($id) {
        $data = null;
        $query = "SELECT * FROM accounts WHERE id = ? AND access = '1'";
        if ($stmt = $this->conn->prepare($query)) {
            $stmt->bind_param('s', $id);
            if ($stmt->execute()) {
                $result = $stmt->get_result();
                if ($row = $result->fetch_assoc()) {
                    $data = $row;
                }
            }
            $stmt->close();
        }
        return $data;
    }
    
    public function displayOfficeAccountDetails($id) {
        $data = null;
        $query = "SELECT * FROM accounts WHERE id = ? AND access = '2'";
        if ($stmt = $this->conn->prepare($query)) {
            $stmt->bind_param('s', $id);
            if ($stmt->execute()) {
                $result = $stmt->get_result();
                if ($row = $result->fetch_assoc()) {
                    $data = $row; 
                }
            }
            $stmt->close();
        }
        return $data;
    }

    public function displayStudentAccountDetails($id) {
        $data = null;
        $query = "SELECT * FROM accounts WHERE id = ? AND access = '3'";
        if ($stmt = $this->conn->prepare($query)) {
            $stmt->bind_param('s', $id);
            if ($stmt->execute()) {
                $result = $stmt->get_result();
                if ($row = $result->fetch_assoc()) {
                    $data = $row; 
                }
            }
            $stmt->close();
        }
        return $data;
    }

    public function updateAccountPhoto($photo, $id) {
        $query = "UPDATE accounts SET photo = ? WHERE id = ?";
        if ($stmt = $this->conn->prepare($query)) {
            $stmt->bind_param('ss', $photo, $id);
            $stmt->execute();
            $stmt->close();
        }
    }

    public function updateAccountPassword($id, $currentPassword, $newPassword) {
        if (!SmartServeValidation::id($id) || !SmartServeValidation::password($newPassword)) {
            smartserve_notify('Please enter a valid new password.', 'danger', 'update-password');
            return;
        }
        $query = "SELECT pword FROM accounts WHERE id = ? LIMIT 1";
        if ($stmt = $this->conn->prepare($query)) {
            $stmt->bind_param("i", $id);
            $stmt->execute();
            $stmt->bind_result($hashed_pass);
            $stmt->store_result();

            if ($stmt->num_rows > 0 && $stmt->fetch()) {
                if (password_verify($currentPassword, $hashed_pass)) {
                    $newHashed = password_hash($newPassword, PASSWORD_DEFAULT);
                    $sql = "UPDATE accounts SET pword = ? WHERE id = ?";
                    if ($update = $this->conn->prepare($sql)) {
                        $update->bind_param("si", $newHashed, $id);
                        if ($update->execute()) {
                            $this->emailEvent('account', $id, 'password');
                            smartserve_notify('Password has been updated.', 'success', 'my-profile');
                            exit();
                        } else {
                            smartserve_notify('Error updating password. Try again.', 'danger', 'update-password');
                            return;
                        }
                        $update->close();
                    }
                } else {
                    smartserve_notify('Incorrect current password!', 'danger', 'update-password');
                    return;
                }
            } else {
                smartserve_notify('User not found!', 'danger', 'update-password');
                return;
            }
            $stmt->close();
        }
        smartserve_notify('Unable to update your password. Please try again.', 'danger', 'update-password');
    }

    public function checkPhoneExists($contact, $excludeId = 0) {
        $key = SmartServeValidation::phoneKey($contact);
        if ($key === null) return false; // Format validation runs separately.
        $local = '0' . substr($key, 3);
        // Keep an existing number, including legacy shared numbers, on unrelated edits.
        if ($excludeId) {
            $own = $this->conn->prepare('SELECT id FROM accounts WHERE id=? AND contact IN (?,?) LIMIT 1');
            $own->bind_param('iss', $excludeId, $local, $key);
            $own->execute(); $unchanged = $own->get_result()->num_rows > 0; $own->close();
            if ($unchanged) return false;
        }
        $stmt = $this->conn->prepare('SELECT id FROM accounts WHERE contact IN (?,?) AND id<>? LIMIT 1');
        $stmt->bind_param('ssi', $local, $key, $excludeId);
        $stmt->execute(); $exists = $stmt->get_result()->num_rows > 0; $stmt->close();
        return $exists;
    }

    private function withUniquePhone($contact, $excludeId, callable $save) {
        $key = SmartServeValidation::phoneKey($contact);
        if ($key === null) throw new InvalidArgumentException('Please enter a valid Philippine mobile number.');
        // Serialize competing assignments without changing legacy account data.
        $stmt = $this->conn->prepare("SELECT CONCAT('ss.phone.',SHA1(CONCAT(DATABASE(),':',?)))");
        $stmt->bind_param('s', $key); $stmt->execute();
        $lock = $stmt->get_result()->fetch_row()[0]; $stmt->close();
        $stmt = $this->conn->prepare('SELECT GET_LOCK(?,5)');
        $stmt->bind_param('s', $lock); $stmt->execute();
        $acquired = (int)$stmt->get_result()->fetch_row()[0] === 1; $stmt->close();
        if (!$acquired) throw new RuntimeException('Unable to check the phone number. Please try again.');
        try {
            if ($this->checkPhoneExists($contact, $excludeId)) throw new InvalidArgumentException('Phone number is already registered.');
            return $save();
        } finally {
            $stmt = $this->conn->prepare('SELECT RELEASE_LOCK(?)');
            $stmt->bind_param('s', $lock); $stmt->execute(); $stmt->close();
        }
    }

    public function getProfileUpdateError() { return $this->profileUpdateError; }

    public function updateAccountProfile($fname, $mname, $lname, $position, $contact, $address, $id) {
        $this->profileUpdateError = 'Update failed, please try again.';
        if (!SmartServeValidation::name($fname) || !SmartServeValidation::name($mname, false) || !SmartServeValidation::name($lname) || !SmartServeValidation::phone($contact) || !SmartServeValidation::text($address, true, 120) || !SmartServeValidation::id($id)) return false;
        try {
            return $this->withUniquePhone($contact, $id, function() use ($fname, $mname, $lname, $position, $contact, $address, $id) {
                $stmt = $this->conn->prepare('UPDATE accounts SET fname=?,mname=?,lname=?,position=?,contact=?,address=? WHERE id=?');
                $stmt->bind_param('ssssssi', $fname, $mname, $lname, $position, $contact, $address, $id);
                try { return $stmt->execute(); } finally { $stmt->close(); }
            });
        } catch (Throwable $e) {
            if ($e instanceof InvalidArgumentException) $this->profileUpdateError = $e->getMessage();
            else error_log('Profile update failed: ' . $e->getMessage());
            return false;
        }
    }

    public function displayAccounts($access) {
        $data = [];
        $query = "SELECT * FROM accounts WHERE access = ? ORDER BY id DESC";
        if ($stmt = $this->conn->prepare($query)) {
            $stmt->bind_param('i', $access);
            if ($stmt->execute()) {
                $result = $stmt->get_result();
                if ($result->num_rows > 0) {
                    while ($row = $result->fetch_assoc()) {
                        $data[] = $row;
                    }
                }
            }
            $stmt->close();
        }
        return $data;
    }

    public function updateAccountStatus($status, $id) {
        $query = "UPDATE accounts SET status = ? WHERE id = ? AND status <> ?";
        if ($stmt = $this->conn->prepare($query)) {
            $stmt->bind_param('iii', $status, $id, $status);
            $stmt->execute();
            if ($stmt->affected_rows === 1) $this->emailEvent('account', $id, (int)$status === 1 ? 'activated' : 'deactivated');
            $stmt->close();
        }
    }

    public function addAccount($fname, $mname, $lname, $uname, $temp_pword, $contact, $address, $position, $photo, $access, $year, $course, $status) {
        if (!SmartServeValidation::name($fname, true, 30) || !SmartServeValidation::name($mname, false, 30) || !SmartServeValidation::name($lname, true, 30) || !SmartServeValidation::email($uname) || !SmartServeValidation::phone($contact) || !SmartServeValidation::text($address, true, 120)) {
            smartserve_notify('Please check the account names, email, contact number, and address.', 'danger', 'back');
            exit;
        }
        if ($this->checkEmailExists($uname)) {
            smartserve_notify('Email is already registered. Please use a different email.', 'danger', 'back');
            exit;
        }
        if ($this->checkPhoneExists($contact)) {
            smartserve_notify('Phone number is already registered.', 'danger', 'back'); exit;
        }
        if ((int)$access === 2 && ctype_digit((string)$course) && !$this->hasActiveOffices([$course])) {
            smartserve_notify('Please select a valid active office.', 'danger', 'back');
            exit;
        }
        mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
        $query = "INSERT INTO accounts (fname, mname, lname, uname, pword, contact, address, position, photo, access, year, course, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        
        if ($stmt = $this->conn->prepare($query)) {
            $stmt->bind_param(
                'sssssssssissi', 
                $fname, 
                $mname, 
                $lname, 
                $uname, 
                $temp_pword,
                $contact, 
                $address, 
                $position, 
                $photo, 
                $access, 
                $year, 
                $course, 
                $status 
            );

            try {
                $newId = $this->withUniquePhone($contact, 0, function() use ($stmt) { $stmt->execute(); return $this->conn->insert_id; });
                $this->emailEvent('account', $newId, 'welcome');
                $stmt->close();
                smartserve_notify('Successfully registered!', 'success', 'back');
                exit();
            } 
            catch (InvalidArgumentException $e) {
                $stmt->close(); smartserve_notify($e->getMessage(), 'danger', 'back'); exit;
            }
            catch (Throwable $e) {
                $stmt->close();
                if ($e->getCode() == 1062) {
                    smartserve_notify('Email is already registered. Please use a different email.', 'danger', 'back');
                    exit();
                } 
                else {
                    error_log($e->getMessage());
                    smartserve_notify('Unable to save the account. Please try again.', 'danger', 'back');
                    exit();
                }
            }
        } 
        else {
            smartserve_notify('Failed to prepare query.', 'danger', 'back');
            exit();
        }
    }
    
    public function addRegAccount($fname, $mname, $lname, $uname, $temp_pword, $contact, $address, $position, $photo, $access, $year, $course, $status, $priority_type = 'N/A', $pwd_id_path = null) {
        if (!SmartServeValidation::name($fname) || !SmartServeValidation::name($mname, false) || !SmartServeValidation::name($lname) || !SmartServeValidation::email($uname) || !SmartServeValidation::phone($contact) || !SmartServeValidation::text($address, true, 120)) {
            smartserve_notify('Please check the account names, email, contact number, and address.', 'danger', 'back');
            exit;
        }
        if ($this->checkPhoneExists($contact)) {
            smartserve_notify('Phone number is already registered.', 'danger', 'back'); exit;
        }
        mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
        if (!in_array($priority_type, ['N/A', 'PWD', 'Senior Citizen', 'Pregnant'], true) || ($priority_type === 'PWD' && !preg_match('/\A[a-f0-9]{48}\.(jpg|png)\z/', $pwd_id_path ?? ''))) {
            smartserve_notify('Please upload a valid PWD ID when applying for PWD priority.', 'danger', 'back');
            exit;
        }
        $pwd_status = $priority_type === 'PWD' ? 'pending' : 'none';
        $query = "INSERT INTO accounts (fname, mname, lname, uname, pword, contact, address, position, photo, access, year, course, status, priority_type, pwd_id_path, pwd_status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        
        if ($stmt = $this->conn->prepare($query)) {
            $stmt->bind_param(
                'sssssssssississs', 
                $fname, 
                $mname, 
                $lname, 
                $uname, 
                $temp_pword,
                $contact, 
                $address, 
                $position, 
                $photo, 
                $access, 
                $year, 
                $course, 
                $status,
                $priority_type,
                $pwd_id_path,
                $pwd_status
            );

            try {
                $newId = $this->withUniquePhone($contact, 0, function() use ($stmt) { $stmt->execute(); return $this->conn->insert_id; });
                $this->emailEvent('account', $newId, 'welcome');
                $stmt->close();
                smartserve_notify('Your SmartServe account has been created successfully. You can now log in.' . ($pwd_status === 'pending' ? ' Your PWD ID is pending admin review; regular bookings are available while you wait.' : ''), 'success', 'login');
                exit();
            } 
            catch (InvalidArgumentException $e) {
                $stmt->close(); smartserve_notify($e->getMessage(), 'danger', 'back'); exit;
            }
            catch (Throwable $e) {
                $stmt->close();
                if ($e->getCode() == 1062) {
                    smartserve_notify('Email is already registered. Please use a different email.', 'danger', 'back');
                    exit();
                } 
                else {
                    error_log($e->getMessage());
                    smartserve_notify('Unable to save the account. Please try again.', 'danger', 'back');
                    exit();
                }
            }
        } 
        else {
            smartserve_notify('Failed to prepare query.', 'danger', 'back');
            exit();
        }
    }

    public function fetchEmailID($email) {
        $query = "SELECT uname FROM accounts WHERE uname = ? LIMIT 1";
        if($stmt = $this->conn->prepare($query)) {
            $stmt->bind_param("s", $email);
            $stmt->execute();
            $stmt->bind_result($id);
            $stmt->store_result();
            if($stmt->num_rows > 0) {
                if($stmt->fetch()) {
                    return $id;
                }
            }
            else {
                return false;
            }
            $stmt->close();
        }
        $this->conn->close();
    }

    public function displayBasicProfile($email) {
        $data = null;
        $query = "SELECT * FROM accounts WHERE uname = ?";
        if ($stmt = $this->conn->prepare($query)) {
            $stmt->bind_param('s', $email);
            $stmt->execute();
            $result = $stmt->get_result();
            while ($row = $result->fetch_assoc()) {
                $data[] = $row;
            }
            $stmt->close();
        }
        return $data;
    }

    public function verifiedChangePassword($id, $password) {
        $query = "UPDATE accounts SET pword = ? WHERE uname = ?";    
        if($stmt = $this->conn->prepare($query)) {
            $stmt->bind_param('ss', $password, $id);
            $stmt->execute();
            if ($stmt->affected_rows === 1) {
                $account = $this->displayBasicProfile($id);
                if (!empty($account[0]['id'])) $this->emailEvent('account', $account[0]['id'], 'password');
            }
            $stmt->close();
        }
    }

    public function updateStudentPriority($id, $type, $filename = null) {
        if (!in_array($type, ['N/A', 'PWD', 'Senior Citizen', 'Pregnant'], true)) return false;
        $account = $this->displayStudentAccountDetails($id);
        if (!$account || (int)$account['access'] !== 3) return false;
        if ($type === 'PWD' && !$filename && empty($account['pwd_id_path'])) return false;
        $path = $filename ?: ($account['pwd_id_path'] ?? null);
        if ($filename && !preg_match('/\A[a-f0-9]{48}\.(jpg|png)\z/', $filename)) return false;
        $status = $type === 'PWD' ? ($filename || ($account['priority_type'] ?? '') !== 'PWD' ? 'pending' : $account['pwd_status']) : 'none';
        $stmt = $this->conn->prepare('UPDATE accounts SET priority_type=?, pwd_id_path=?, pwd_status=?, pwd_reviewed_by=IF(?="pending",NULL,pwd_reviewed_by), pwd_reviewed_at=IF(?="pending",NULL,pwd_reviewed_at), date_created=date_created WHERE id=? AND access=3');
        $stmt->bind_param('sssssi', $type, $path, $status, $status, $status, $id);
        $ok = $stmt->execute(); $stmt->close();
        return $ok;
    }

    public function updateStudentProfile($fname, $mname, $lname, $contact, $address, $id, $type, $filename = null) {
        $this->profileUpdateError = 'Update failed, please try again.';
        try {
            return $this->withUniquePhone($contact, $id, function() use ($fname, $mname, $lname, $contact, $address, $id, $type, $filename) {
                $this->conn->begin_transaction();
                try {
                    if (!$this->updateStudentPriority($id, $type, $filename) || !$this->updateAccountProfile($fname, $mname, $lname, 'Student', $contact, $address, $id)) {
                        $this->conn->rollback(); return false;
                    }
                    $this->conn->commit(); return true;
                } catch (Throwable $e) { $this->conn->rollback(); throw $e; }
            });
        } catch (Throwable $e) {
            if ($e instanceof InvalidArgumentException) $this->profileUpdateError = $e->getMessage();
            else error_log('Student profile update failed: ' . $e->getMessage());
            return false;
        }
    }

    public function scheduleAvailability($officeIds, $date) {
        $slots = [];
        foreach (SmartServeValidation::SLOTS as $time) {
            if (!SmartServeValidation::weekdayDate($date)) {
                $slots[$time] = ['available'=>0, 'label'=>'Closed', 'note'=>'Appointments are available Monday to Friday only.'];
                continue;
            }
            $left = 5; $label = ''; $note = '';
            foreach ($officeIds as $oid) {
                $blocked = $this->getSlotBlockInfo((int)$oid, $date, $time);
                if ($blocked) { $left = 0; $label = 'Blocked'; $note = ($blocked['office_name'] ?? 'Office') . ': ' . ($blocked['reason'] ?? 'Unavailable'); break; }
                $left = min($left, max(0, 5 - $this->getBookedSlots($oid, $date, $time)));
            }
            if ($date === date('Y-m-d') && time() >= strtotime($date . ' ' . $time) - 1800) { $left = 0; $label = $label ?: 'Closed'; }
            $slots[$time] = ['available' => $left, 'label' => $label ?: ($left > 0 ? "$left slot(s) left" : 'Reserved'), 'note' => $note];
        }
        return $slots;
    }

    public function reviewPwdId($id, $decision, $adminId) {
        $admin = $this->displayAccountDetails($adminId)[0] ?? null;
        if (!$admin || (int)$admin['access'] !== 1 || (int)$admin['status'] !== 1 || !in_array($decision, ['approved', 'rejected'], true)) return false;
        $stmt = $this->conn->prepare("UPDATE accounts SET pwd_status=?, pwd_reviewed_by=?, pwd_reviewed_at=NOW(), date_created=date_created WHERE id=? AND access=3 AND priority_type='PWD' AND pwd_status='pending' AND pwd_id_path IS NOT NULL");
        $stmt->bind_param('sii', $decision, $adminId, $id); $stmt->execute();
        $changed = $stmt->affected_rows === 1; $stmt->close();
        if ($changed) $this->emailEvent('account', $id, 'pwd_' . $decision);
        return $changed;
    }

    public function studentPriority($id) {
        require_once __DIR__ . '/priority.php';
        return smartserve_priority_label($this->displayStudentAccountDetails($id) ?: []);
    }

    public function autoArchiveAdvisories() {
        $this->conn->query("UPDATE announcements SET status=2, archived_at=NOW(), archive_reason='expired' WHERE category=1 AND status=1 AND expires_at <= NOW()");
        return $this->conn->affected_rows;
    }

    public function getArchivedAdvisories($accountId, $admin = false) {
        $this->autoArchiveAdvisories();
        $sql = "SELECT a.*, acc.fname, acc.lname FROM announcements a JOIN accounts acc ON acc.id=a.account_id WHERE a.category=1 AND a.status=2";
        if (!$admin) $sql .= ' AND a.account_id=?';
        $stmt = $this->conn->prepare($sql . ' ORDER BY COALESCE(a.archived_at,a.date) DESC');
        if (!$admin) $stmt->bind_param('i', $accountId);
        $stmt->execute(); $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC); $stmt->close(); return $rows;
    }

    public function displayMyAnnouncements($account_id,$category, $status) {
        $this->autoArchiveAdvisories();
        $data = null;
        $query = "SELECT * FROM announcements WHERE account_id = ? AND category = ? AND status = ? ORDER BY id DESC";
        if ($stmt = $this->conn->prepare($query)) {
            $stmt->bind_param('iii', $account_id, $category, $status);
            $stmt->execute();
            $result = $stmt->get_result();
            while ($row = $result->fetch_assoc()) {
                $data[] = $row;
            }
            $stmt->close();
        }
        return $data;
    }

    public function displayAnnouncements($category, $status) {
        $this->autoArchiveAdvisories();
        $data = [];
        $query = "SELECT a.*, acc.fname, acc.lname, 
                         CASE 
                            WHEN acc.access = 1 THEN 'Admin'
                            WHEN acc.access = 2 THEN COALESCE(o.office_name, 'Unknown Office')
                            ELSE 'General'
                         END as course
                  FROM announcements a 
                  INNER JOIN accounts acc ON a.account_id = acc.id 
                  LEFT JOIN offices o ON acc.course = o.id
                  WHERE a.category = ? AND a.status = ? 
                  ORDER BY a.id DESC";
                  
        if ($stmt = $this->conn->prepare($query)) {
            $stmt->bind_param('ii', $category, $status);
            $stmt->execute();
            $result = $stmt->get_result();
            while ($row = $result->fetch_assoc()) {
                $data[] = $row;
            }
            $stmt->close();
        }
        return $data;
    }

    public function displayRecentAnnouncements($category, $status) {
        $this->autoArchiveAdvisories();
        $data = null;
        $query = "SELECT * FROM announcements WHERE category = ? AND status = ? ORDER BY date DESC LIMIT 5";
        if ($stmt = $this->conn->prepare($query)) {
            $stmt->bind_param('ii', $category, $status);
            $stmt->execute();
            $result = $stmt->get_result();
            while ($row = $result->fetch_assoc()) {
                $data[] = $row;
            }
            $stmt->close();
        }
        return $data;
    }
    
    public function displayAnnouncementDetailsX($category, $id) {
        $data = null;
        $query = "SELECT * FROM announcements WHERE category = ? AND id = ?";
        if ($stmt = $this->conn->prepare($query)) {
            $stmt->bind_param('ii', $category, $id);
            $stmt->execute();
            $result = $stmt->get_result();
            while ($row = $result->fetch_assoc()) {
                $data[] = $row;
            }
            $stmt->close();
        }
        return $data;
    }

    public function addAnnouncement($title, $details, $base, $unique, $date, $account_id, $category) {
        $query = "INSERT INTO announcements (title, details, image, image_unique, date, account_id, status, category, published_at, expires_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW(), DATE_ADD(NOW(), INTERVAL 1 MONTH))";
        if ($stmt = $this->conn->prepare($query)) {
            $status = 1;
            $stmt->bind_param('ssssssii', $title, $details, $base, $unique, $date, $account_id, $status, $category);
            $ok = $stmt->execute();
            $id = $this->conn->insert_id;
            $stmt->close();
            return $ok ? $id : 0;
        }
        return 0;
    }

    public function editAnnouncement($title, $details, $date, $id) {
        $query = "UPDATE announcements SET title = ?, details = ?, date = ? WHERE id = ?";
        if ($stmt = $this->conn->prepare($query)) {
            $stmt->bind_param('sssi', $title, $details, $date, $id);
            $stmt->execute();
            $stmt->close();
        }
    }
    
    public function editImageAnnouncement($image, $image_unique, $id) {
        $query = "UPDATE announcements SET image = ?, image_unique = ? WHERE id = ?";
        if ($stmt = $this->conn->prepare($query)) {
            $stmt->bind_param('ssi', $image, $image_unique, $id);
            $stmt->execute();
            $stmt->close();
        }
    }

    public function archiveAnnouncement($status, $id) {
        $query = "UPDATE announcements SET status = ?, archived_at=IF(?=2,NOW(),NULL), archive_reason=IF(?=2,'manual',NULL), expires_at=IF(?=1,DATE_ADD(NOW(),INTERVAL 1 MONTH),expires_at) WHERE id = ?";
        if ($stmt = $this->conn->prepare($query)) {
            $stmt->bind_param('iiiii', $status, $status, $status, $status, $id);
            $stmt->execute();
            $stmt->close();
        }
    }

    public function getOffices() {
        $data = [];
        $query = "SELECT id, office_name FROM offices WHERE status = 1 ORDER BY office_name ASC";
        if ($stmt = $this->conn->prepare($query)) {
            $stmt->execute();
            $result = $stmt->get_result();
            while ($row = $result->fetch_assoc()) {
                $data[] = $row;
            }
            $stmt->close();
        }
        return $data;
    }

    private function hasActiveOffices($office_ids) {
        if (!is_array($office_ids) || empty($office_ids)) return false;
        $active = array_map('intval', array_column($this->getOffices(), 'id'));
        foreach ($office_ids as $id) {
            if (!SmartServeValidation::id($id) || !in_array((int)$id, $active, true)) return false;
        }
        return true;
    }

    public function getBookedSlots($office_id, $date, $time) {
        $count = 0;
        $query = "SELECT COUNT(ao.id) as total 
                  FROM appointment_offices ao
                  JOIN appointments a ON ao.tracking_no = a.tracking_no
                  WHERE ao.office_id = ? AND a.appointment_date = ? AND a.appointment_time = ? AND a.status != 3 AND ao.status <> 4";
        if ($stmt = $this->conn->prepare($query)) {
            $stmt->bind_param('iss', $office_id, $date, $time);
            $stmt->execute();
            $result = $stmt->get_result();
            if ($row = $result->fetch_assoc()) {
                $count = $row['total'];
            }
            $stmt->close();
        }
        return $count;
    }

    public function getAppointmentDetails($tracking_no, $student_id) {
        $this->autoCancelExpiredAppointments();
        $data = [];
        $statusSql = $this->studentAppointmentStatusSQL();
        $query = "SELECT a.tracking_no, a.appointment_date, a.appointment_time, a.notes, $statusSql AS status, a.date_created, a.is_priority, a.priority,
                         (SELECT original.tracking_no FROM appointments original WHERE original.id=a.rescheduled_from_id AND original.student_id=a.student_id) AS rescheduled_from_tracking_no,
                         GROUP_CONCAT(o.office_name ORDER BY o.id SEPARATOR ', ') as office_names
                  FROM appointments a
                  LEFT JOIN appointment_offices ao ON a.tracking_no = ao.tracking_no
                  LEFT JOIN offices o ON ao.office_id = o.id
                  WHERE a.tracking_no = ? AND a.student_id = ?
                  GROUP BY a.tracking_no";
                  
        if ($stmt = $this->conn->prepare($query)) {
            $stmt->bind_param('ss', $tracking_no, $student_id);
            $stmt->execute();
            $result = $stmt->get_result();
            if ($row = $result->fetch_assoc()) {
                $data = $row;
            }
            $stmt->close();
        }
        if ($data) $data['services'] = $this->getStudentServiceVisits($student_id, $tracking_no)[$tracking_no] ?? [];
        return $data;
    }

    public function getStudentAppointments($student_id) {
        $this->autoCancelExpiredAppointments();
        $data = [];
        $statusSql = $this->studentAppointmentStatusSQL();
        $query = "SELECT a.tracking_no, a.appointment_date, a.appointment_time, $statusSql AS status, a.date_created,
                         (SELECT original.tracking_no FROM appointments original WHERE original.id=a.rescheduled_from_id AND original.student_id=a.student_id) AS rescheduled_from_tracking_no,
                         GROUP_CONCAT(o.office_name ORDER BY o.id SEPARATOR ', ') as office_names
                  FROM appointments a
                  LEFT JOIN appointment_offices ao ON a.tracking_no = ao.tracking_no
                  LEFT JOIN offices o ON ao.office_id = o.id
                  WHERE a.student_id = ?
                  GROUP BY a.tracking_no
                  ORDER BY a.date_created DESC, a.id DESC";
                  
        if ($stmt = $this->conn->prepare($query)) {
            $stmt->bind_param('s', $student_id);
            $stmt->execute();
            $result = $stmt->get_result();
            while ($row = $result->fetch_assoc()) {
                $data[] = $row;
            }
            $stmt->close();
        }
        $visits = $this->getStudentServiceVisits($student_id);
        foreach ($data as &$booking) $booking['services'] = $visits[$booking['tracking_no']] ?? [];
        unset($booking);
        return $data;
    }

    private function getStudentServiceVisits($student_id, $tracking_no = null) {
        $sql = "SELECT ao.*, o.office_name,
            CASE WHEN ao.status IN (0,1) AND bs.status=1 AND bsa.action_status IN ('pending','notified','reschedule_requested') THEN bsa.id END AS blocked_notice_id,
            bs.reason AS block_reason,
            replacement.tracking_no AS rescheduled_tracking_no,
            replacement.appointment_date AS rescheduled_date, replacement.appointment_time AS rescheduled_time
            FROM appointments a JOIN appointment_offices ao ON ao.tracking_no=a.tracking_no
            JOIN offices o ON o.id=ao.office_id
            LEFT JOIN blocked_schedule_appointments bsa ON bsa.id=(
                SELECT notice.id FROM blocked_schedule_appointments notice
                JOIN blocked_schedules notice_block ON notice_block.id=notice.block_id
                WHERE notice.tracking_no=ao.tracking_no AND notice.office_id=ao.office_id
                ORDER BY (notice_block.status=1 AND notice.action_status IN ('pending','notified','reschedule_requested')) DESC, notice.id DESC LIMIT 1)
            LEFT JOIN blocked_schedules bs ON bs.id=bsa.block_id
            LEFT JOIN appointments replacement ON replacement.id=bsa.new_appointment_id
                AND replacement.id<>a.id AND replacement.student_id=a.student_id AND bsa.action_status='rescheduled'
            WHERE a.student_id=?";
        if ($tracking_no !== null) $sql .= ' AND a.tracking_no=?';
        $stmt = $this->conn->prepare($sql . ' ORDER BY a.id,ao.office_id,ao.id');
        if ($tracking_no !== null) $stmt->bind_param('is', $student_id, $tracking_no);
        else $stmt->bind_param('i', $student_id);
        $stmt->execute();
        $visits = [];
        foreach ($stmt->get_result()->fetch_all(MYSQLI_ASSOC) as $visit) $visits[$visit['tracking_no']][] = $visit;
        $stmt->close();
        return $visits;
    }

    private function studentAppointmentStatusSQL() {
        // Office status codes differ from the student ticket codes. Read the
        // visits directly so older tickets also show the current staff status.
        return "CASE WHEN COUNT(ao.id)=0 THEN a.status
            WHEN SUM(ao.status IN (0,1,2))>0 THEN
                CASE WHEN a.status=3 THEN 3 WHEN SUM(ao.status IN (1,2,3))>0 THEN 1 ELSE 0 END
            WHEN SUM(ao.status=3)>0 THEN 2 ELSE 3 END";
    }

    private function syncAppointmentCompletion($tracking_no) {
        // Keep active parent tickets at 0: scanning and rescheduling use that
        // state. A ticket finishes only after every selected office finishes.
        $stmt = $this->conn->prepare("UPDATE appointments a SET a.status =
            CASE WHEN EXISTS (SELECT 1 FROM appointment_offices ao WHERE ao.tracking_no=a.tracking_no AND ao.status=3) THEN 2 ELSE 3 END
            WHERE a.tracking_no=? AND a.status IN (0,1,3)
            AND EXISTS (SELECT 1 FROM appointment_offices ao WHERE ao.tracking_no=a.tracking_no)
            AND NOT EXISTS (SELECT 1 FROM appointment_offices ao WHERE ao.tracking_no=a.tracking_no AND ao.status IN (0,1,2))");
        $stmt->bind_param('s', $tracking_no);
        $stmt->execute();
        $stmt->close();
    }

    public function cancelAppointment($tracking_no, $student_id) {
        try {
            $this->conn->begin_transaction();
            $lock = $this->conn->prepare('SELECT status FROM appointments WHERE tracking_no=? AND student_id=? FOR UPDATE');
            $lock->bind_param('si', $tracking_no, $student_id);
            $lock->execute();
            $parent = $lock->get_result()->fetch_assoc();
            $lock->close();
            if (!$parent || (int)$parent['status'] !== 0 || $this->appointmentIsBeingServed($tracking_no)) {
                $this->conn->rollback();
                return false;
            }
            $stmt = $this->conn->prepare('UPDATE appointments SET status = 3 WHERE tracking_no = ? AND student_id = ? AND status = 0');
            $stmt->bind_param('si', $tracking_no, $student_id);
            $stmt->execute();
            $changed = $stmt->affected_rows === 1;
            $stmt->close();
            if (!$changed) {
                $this->conn->rollback();
                return false;
            }
            $stmt = $this->conn->prepare("UPDATE appointment_offices SET status = 4, office_notes = 'Cancelled by student.' WHERE tracking_no = ? AND status IN (0,1)");
            $stmt->bind_param('s', $tracking_no);
            $stmt->execute();
            $stmt->close();
            $this->syncAppointmentCompletion($tracking_no);
            $this->emailEvent('appointment', $tracking_no, 'cancelled');
            $this->conn->commit();
            return true;
        } catch (Throwable $e) {
            $this->conn->rollback();
            error_log('SmartServe: unable to cancel appointment.');
            return false;
        }
    }

    private function appointmentIsBeingServed($tracking_no) {
        // Caller holds the parent ticket lock, also used by staff queue actions.
        $stmt = $this->conn->prepare('SELECT id FROM appointment_offices WHERE tracking_no=? AND status=2 LIMIT 1 FOR UPDATE');
        $stmt->bind_param('s', $tracking_no);
        $stmt->execute();
        $serving = $stmt->get_result()->num_rows > 0;
        $stmt->close();
        return $serving;
    }

    public function getTodayQueue($office_id) {
        $data = [];
        $query = "SELECT ao.*, a.appointment_date, a.appointment_time, a.tracking_no, a.guest_name, a.student_id, a.is_walkin, a.is_priority, a.priority, (acc.priority_type='PWD' AND acc.pwd_id_path IS NOT NULL AND acc.pwd_id_path<>'') AS has_pwd_id,
                         acc.fname, acc.lname, a.notes as student_notes
                  FROM appointment_offices ao
                  JOIN appointments a ON ao.tracking_no = a.tracking_no
                  LEFT JOIN accounts acc ON a.student_id = acc.id
                  WHERE ao.office_id = ? AND a.appointment_date = CURDATE()
                  ORDER BY ao.status DESC, a.is_priority DESC, ao.time_queued ASC, a.appointment_time ASC";

        if ($stmt = $this->conn->prepare($query)) {
            $stmt->bind_param('i', $office_id);
            $stmt->execute();
            $result = $stmt->get_result();
            while ($row = $result->fetch_assoc()) {
                $data[] = $row;
            }
            $stmt->close();
        }
        return $data;
    }
    
    public function autoCancelExpiredAppointments($office_id = null) {
        $scheduledAt = SmartServeAppointmentRules::SCHEDULED_AT_SQL;
        $grace = SmartServeAppointmentRules::ARRIVAL_GRACE_MINUTES;
        $unblocked = SmartServeAppointmentRules::unblockedServiceSQL();
        // Include legacy cancelled tickets whose unconfirmed office rows stayed pending.
        $due = "ao.status = 0 AND a.status IN (0,3) AND a.is_walkin = 0
                AND $scheduledAt <= DATE_SUB(NOW(), INTERVAL $grace MINUTE) AND $unblocked";
        $query = "SELECT ao.id, ao.tracking_no, ao.office_id FROM appointment_offices ao
                  JOIN appointments a ON ao.tracking_no = a.tracking_no WHERE $due";
        if ($office_id !== null) $query .= ' AND ao.office_id = ?';
        $query .= ' ORDER BY ao.id';
        $stmt = $this->conn->prepare($query);
        if ($office_id !== null) $stmt->bind_param('i', $office_id);
        $stmt->execute();
        $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        $cancelled = 0;
        foreach ($rows as $row) {
            try {
                $this->conn->begin_transaction();
                // Recheck arrival and deadline under the row lock; a scan may have just succeeded.
                $update = $this->conn->prepare("UPDATE appointment_offices ao
                    JOIN appointments a ON ao.tracking_no = a.tracking_no
                    SET ao.status = 4, ao.office_notes = 'System: Auto-cancelled (no check-in within $grace minutes)'
                    WHERE ao.id = ? AND $due");
                $update->bind_param('i', $row['id']);
                $update->execute();
                $changed = $update->affected_rows === 1;
                $update->close();
                if (!$changed) {
                    $this->conn->rollback();
                    continue;
                }
                $this->syncAppointmentCompletion($row['tracking_no']);
                $this->emailEvent('appointment', $row['tracking_no'], 'expired', $row['office_id']);
                $this->conn->commit();
                $cancelled++;
            } catch (Throwable $e) {
                $this->conn->rollback();
                error_log('SmartServe: automatic appointment cancellation failed (' . get_class($e) . ').');
            }
        }
        return $cancelled;
    }

    public function isWindowBusy($office_id, $window_name) {
        $query = "SELECT COUNT(*) as total FROM appointment_offices WHERE office_id = ? AND served_at_window = ? AND status = 2";
        if ($stmt = $this->conn->prepare($query)) {
            $stmt->bind_param('is', $office_id, $window_name);
            $stmt->execute();
            $result = $stmt->get_result();
            if ($row = $result->fetch_assoc()) {
                return $row['total'] > 0;
            }
            $stmt->close();
        }
        return false;
    }

    public function verifyAndQueueQR($tracking_no, $office_id) {
        $this->autoCancelExpiredAppointments($office_id);
        $unblocked = SmartServeAppointmentRules::unblockedServiceSQL();
        $query = "SELECT ao.id, a.appointment_date, a.status AS appointment_status, ao.status, $unblocked AS service_available
                  FROM appointment_offices ao
                  JOIN appointments a ON ao.tracking_no = a.tracking_no
                  WHERE ao.tracking_no = ? AND ao.office_id = ?";
                  
        if ($stmt = $this->conn->prepare($query)) {
            $stmt->bind_param('si', $tracking_no, $office_id);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($row = $result->fetch_assoc()) {
                $today = date('Y-m-d');
                if (!$row['service_available'] && in_array((int)$row['status'], [0,1], true)) return ['status'=>'error', 'msg'=>'This service schedule is blocked. Please review the service update and use the new QR after rescheduling.'];
                if ((int)$row['appointment_status'] !== 0) return ['status' => 'error', 'msg' => (int)$row['appointment_status'] === 3 ? 'This appointment has been cancelled. Please book a new schedule.' : 'This booking is no longer active.'];
                
                if ($row['appointment_date'] != $today) {
                    return ['status' => 'error', 'msg' => 'This appointment is scheduled for ' . date('M d, Y', strtotime($row['appointment_date'])) . ', not today.'];
                }
                
                if ($row['status'] == 1) return ['status' => 'error', 'msg' => 'Student is already in the queue.'];
                if ($row['status'] == 2) return ['status' => 'error', 'msg' => 'Student is currently being served.'];
                if ($row['status'] > 2) return ['status' => 'error', 'msg' => 'This appointment has already been completed, cancelled, or marked no-show.'];
                
                $ao_id = $row['id'];
                
                $countQuery = "SELECT COUNT(id) as total FROM appointment_offices WHERE office_id = $office_id AND DATE(time_queued) = CURDATE()";
                $countResult = $this->conn->query($countQuery);
                $total = $countResult->fetch_assoc()['total'];
                
                $queue_number = "Q-" . str_pad($total + 1, 3, "0", STR_PAD_LEFT);
                
                $arrivalOpen = SmartServeAppointmentRules::arrivalOpenSQL();
                $updateQuery = "UPDATE appointment_offices ao JOIN appointments a ON ao.tracking_no = a.tracking_no
                    SET ao.status = 1, ao.queue_number = ?, ao.time_queued = NOW()
                    WHERE ao.id = ? AND ao.status = 0 AND a.status = 0 AND $arrivalOpen AND $unblocked";
                $upStmt = $this->conn->prepare($updateQuery);
                $upStmt->bind_param('si', $queue_number, $ao_id);
                $upStmt->execute();
                if ($upStmt->affected_rows !== 1) return ['status' => 'error', 'msg' => 'This appointment was already updated. Please refresh the queue.'];
                $upStmt->close();
                $this->emailEvent('appointment', $tracking_no, 'queued', $office_id);
                
                return ['status' => 'success', 'msg' => "Successfully queued! Queue Number: $queue_number"];
            } else {
                return ['status' => 'error', 'msg' => 'Invalid QR Code or not scheduled for this office.'];
            }
        }
        return ['status' => 'error', 'msg' => 'Database error.'];
    }

    public function updateQueueStatus($id, $status, $office_notes = "", $window = null) {
        $lookup = $this->conn->prepare('SELECT tracking_no, office_id, status FROM appointment_offices WHERE id = ?');
        $lookup->bind_param('i', $id);
        $lookup->execute();
        $previous = $lookup->get_result()->fetch_assoc();
        $lookup->close();
        if (!$previous || !in_array((int)$status, [1, 2, 3, 4], true)) return false;
        if ((int)$previous['status'] === (int)$status) return true;
        // Stale dashboard actions must not revive a cancelled or completed ticket.
        $allowed = [0 => [1, 4], 1 => [2, 4], 2 => [3, 4]];
        if (!in_array((int)$status, $allowed[(int)$previous['status']] ?? [], true)) return false;
        $time_sql = "";
        if ($status == 2) {
            $time_sql = ", time_started = NOW()";
        } elseif ($status == 3) {
            $time_sql = ", time_completed = NOW()";
        }

        try {
            $this->conn->begin_transaction();
            // Serialize completion across offices sharing this ticket.
            $lock = $this->conn->prepare('SELECT status FROM appointments WHERE tracking_no=? FOR UPDATE');
            $lock->bind_param('s', $previous['tracking_no']);
            $lock->execute();
            $parent = $lock->get_result()->fetch_assoc();
            $lock->close();
            if (!$parent || in_array((int)$parent['status'], [2,3], true)) {
                $this->conn->rollback();
                return false;
            }
            if (in_array((int)$status, [1,2], true)) {
                $unblocked = SmartServeAppointmentRules::unblockedServiceSQL();
                $check = $this->conn->prepare("SELECT ao.id FROM appointment_offices ao WHERE ao.id=? AND $unblocked FOR UPDATE");
                $check->bind_param('i', $id); $check->execute();
                $available = $check->get_result()->num_rows === 1; $check->close();
                if (!$available) { $this->conn->rollback(); return false; }
            }
            if ($window !== null) {
                $query = "UPDATE appointment_offices SET status = ?, office_notes = ?, served_at_window = ? {$time_sql} WHERE id = ? AND status = ?";
                $stmt = $this->conn->prepare($query);
                $stmt->bind_param('issii', $status, $office_notes, $window, $id, $previous['status']);
            } else {
                $query = "UPDATE appointment_offices SET status = ?, office_notes = ? {$time_sql} WHERE id = ? AND status = ?";
                $stmt = $this->conn->prepare($query);
                $stmt->bind_param('isii', $status, $office_notes, $id, $previous['status']);
            }
            $result = $stmt->execute() && $stmt->affected_rows === 1;
            $stmt->close();
            if (!$result) { $this->conn->rollback(); return false; }
            $this->syncAppointmentCompletion($previous['tracking_no']);
            $this->emailEvent('appointment', $previous['tracking_no'], [1 => 'queued', 2 => 'serving', 3 => 'completed', 4 => 'no_show'][(int)$status], $previous['office_id']);
            $this->conn->commit();
            return true;
        } catch (Throwable $e) {
            $this->conn->rollback();
            error_log('SmartServe: unable to update queue status (' . get_class($e) . ').');
            return false;
        }
    }
    
    public function getStudents() {
        $data = [];
        $query = "SELECT id, fname, lname, uname as email, course, CASE WHEN priority_type='PWD' AND pwd_status='approved' THEN 'PWD' WHEN priority_type IN ('Senior Citizen','Pregnant') THEN priority_type ELSE 'N/A' END AS effective_priority FROM accounts WHERE access = 3 AND status = 1 ORDER BY fname ASC";
        if ($stmt = $this->conn->prepare($query)) {
            $stmt->execute();
            $result = $stmt->get_result();
            while ($row = $result->fetch_assoc()) {
                $data[] = $row;
            }
            $stmt->close();
        }
        return $data;
    }

    public function addWalkInAppointment($student_id, $guest_name, $office_id, $notes, $is_priority = 0, $priority = 'N/A') {
        if (!$this->hasActiveOffices([$office_id])) return ['status' => 'error', 'msg' => 'Please select a valid active office.'];
        if (empty($student_id) && !SmartServeValidation::name($guest_name, true, 100)) return ['status' => 'error', 'msg' => 'Names must contain letters, spaces, and hyphens only.'];
        if (!SmartServeValidation::text($notes, true, 1000)) return ['status' => 'error', 'msg' => 'Please enter a purpose of visit (maximum 1000 characters).'];
        if (!empty($student_id)) {
            if (!SmartServeValidation::id($student_id)) return ['status' => 'error', 'msg' => 'Please select a valid student.'];
            $student = $this->conn->prepare('SELECT id FROM accounts WHERE id = ? AND access = 3 AND status = 1 LIMIT 1');
            $student->bind_param('i', $student_id);
            $student->execute();
            $exists = $student->get_result()->num_rows > 0;
            $student->close();
            if (!$exists) return ['status' => 'error', 'msg' => 'Please select an active student.'];
        }
        if (!empty($student_id)) $priority = $this->studentPriority($student_id);
        if (!in_array($priority, ['N/A', 'No', 'PWD', 'Senior Citizen', 'Pregnant'], true)) $priority = 'N/A';
        if ($priority === 'No') $priority = 'N/A';
        $is_priority = $priority === 'N/A' ? 0 : 1;
        $response = ['status' => 'error', 'msg' => 'System error processing request.'];
        
        // 1. Fetch the Office Name to create the Prefix
        $office_name = "UNK";
        $queryOffice = "SELECT office_name FROM offices WHERE id = ?";
        if ($stmtO = $this->conn->prepare($queryOffice)) {
            $stmtO->bind_param('i', $office_id);
            $stmtO->execute();
            $resO = $stmtO->get_result();
            if ($rowO = $resO->fetch_assoc()) {
                $office_name = $rowO['office_name'];
            }
            $stmtO->close();
        }
        
        // 2. Find the HIGHEST queue number used today
        $qMax = 0;
        $maxQuery = "SELECT MAX(CAST(SUBSTRING_INDEX(ao.queue_number, '-', -1) AS UNSIGNED)) as max_num 
                     FROM appointment_offices ao 
                     JOIN appointments a ON ao.tracking_no = a.tracking_no 
                     WHERE ao.office_id = ? 
                     AND a.appointment_date = CURDATE() 
                     AND ao.queue_number IS NOT NULL 
                     AND ao.queue_number != ''";
                       
        if ($stmtC = $this->conn->prepare($maxQuery)) {
            $stmtC->bind_param('i', $office_id);
            $stmtC->execute();
            $resC = $stmtC->get_result();
            if ($rowC = $resC->fetch_assoc()) {
                $qMax = $rowC['max_num'] ? (int)$rowC['max_num'] : 0;
            }
            $stmtC->close();
        }
        
        // 3. Generate the Smart Queue Number (E.g., "REG-001")
        $prefix = strtoupper(substr(preg_replace('/[^a-zA-Z]/', '', $office_name), 0, 3));
        $next_num = sprintf("%03d", $qMax + 1);
        $final_queue_number = $prefix . '-' . $next_num;
        
        // 4. Generate a unique Tracking Number matching the ONL format
        $tracking_no = 'WLK-' . date('Ymd') . '-' . strtoupper(substr(uniqid(), -5));
        
        // 5. Insert into main appointments table
        $current_date = date('Y-m-d');
        $current_time = date('H:i:s');
        $is_walkin = 1; 
        
        try {
            $this->conn->begin_transaction();
            $insertAppt = "INSERT INTO appointments (student_id, guest_name, tracking_no, appointment_date, appointment_time, notes, is_priority, priority, is_walkin) 
                           VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
                           
            if ($stmtA = $this->conn->prepare($insertAppt)) {
                $stmtA->bind_param('isssssisi', $student_id, $guest_name, $tracking_no, $current_date, $current_time, $notes, $is_priority, $priority, $is_walkin);
                
                if ($stmtA->execute()) {
                    
                    // 6. Insert into appointment_offices
                    $insertAO = "INSERT INTO appointment_offices (tracking_no, office_id, status, queue_number) 
                                 VALUES (?, ?, 1, ?)";
                                 
                    if ($stmtAO = $this->conn->prepare($insertAO)) {
                        $stmtAO->bind_param('sis', $tracking_no, $office_id, $final_queue_number);
                        
                        if ($stmtAO->execute()) {
                            $this->emailEvent('appointment', $tracking_no, 'queued', $office_id);
                            $response = [
                                'status' => 'success',
                                'queue_number' => $final_queue_number,
                                'msg' => 'Walk-in Registered successfully!'
                            ];
                        }
                        $stmtAO->close();
                    }
                }
                $stmtA->close();
            }
            
            if ($response['status'] === 'success') $this->conn->commit();
            else $this->conn->rollback();
        } catch (Throwable $e) {
            $this->conn->rollback();
            error_log('SmartServe: walk-in registration failed.');
            $response = ['status' => 'error', 'msg' => 'Unable to register the walk-in. Please try again.'];
        }
        return $response;
    }
    
    public function addAppointment($tracking_no, $student_id, $office_ids, $date, $time, $notes, $is_priority = 0, $priority = 'N/A') {
        if (!$this->hasActiveOffices($office_ids) || !SmartServeValidation::bookingDate($date) || !in_array($time, SmartServeValidation::SLOTS, true) || !SmartServeValidation::text($notes, true, 1000)) return false;
        $office_ids = array_values(array_unique(array_map('intval', $office_ids)));
        $this->autoCancelExpiredAppointments();
        $priority = $this->studentPriority($student_id);
        $is_priority = $priority === 'N/A' ? 0 : 1;
        // DO NOT generate a tracking number here. We use the one passed from booking.php!
        
        if (empty($student_id)) { $student_id = NULL; }
        $guest_name = NULL; // Online bookings are always registered students, so this is NULL

        try {
            $this->conn->begin_transaction();
            // Concurrent submissions by one student must see the first booking.
            $lock = $this->conn->prepare('SELECT id FROM accounts WHERE id=? AND access=3 AND status=1 FOR UPDATE');
            $lock->bind_param('i', $student_id);
            $lock->execute();
            $studentExists = $lock->get_result()->num_rows === 1;
            $lock->close();
            if (!$studentExists || $this->checkExistingBooking($student_id, $date, $office_ids)) {
                $this->conn->rollback();
                return false;
            }
            // Added guest_name to explicitly match the walk-in table structure
            $query1 = "INSERT INTO appointments (tracking_no, student_id, guest_name, appointment_date, appointment_time, notes, is_priority, priority, status, date_created) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 0, NOW())";
            
            if ($stmt1 = $this->conn->prepare($query1)) {
                // Binding: 6 strings, 1 integer, 1 string
                $stmt1->bind_param('ssssssis', $tracking_no, $student_id, $guest_name, $date, $time, $notes, $is_priority, $priority);
                
                if ($stmt1->execute()) {
                    $stmt1->close(); 
                    
                    if (!is_array($office_ids)) { $office_ids = [$office_ids]; }
                    
                    foreach ($office_ids as $oid) {
                        $next_num = 1;
                        $q_query = "SELECT COUNT(*) as total FROM appointment_offices ao 
                                    JOIN appointments a ON ao.tracking_no = a.tracking_no 
                                    WHERE a.appointment_date = ? 
                                    AND ao.office_id = ? 
                                    AND ao.queue_number LIKE 'ONL-%'";
                                    
                        if ($q_stmt = $this->conn->prepare($q_query)) {
                            $q_stmt->bind_param('si', $date, $oid);
                            $q_stmt->execute();
                            $q_result = $q_stmt->get_result();
                            if ($q_row = $q_result->fetch_assoc()) {
                                $next_num = $q_row['total'] + 1;
                            }
                            $q_stmt->close(); 
                        }
                        
                        // Generates the Online Queue Number (e.g., O-001)
                        $queue_number = 'ONL-' . str_pad($next_num, 3, '0', STR_PAD_LEFT);
                        
                        $query2 = "INSERT INTO appointment_offices (tracking_no, office_id, status, queue_number) VALUES (?, ?, 0, ?)";
                        if ($stmt2 = $this->conn->prepare($query2)) {
                            $stmt2->bind_param('sis', $tracking_no, $oid, $queue_number);
                            if (!$stmt2->execute()) throw new RuntimeException('Unable to save appointment office.');
                            $stmt2->close(); 
                        } else throw new RuntimeException('Unable to prepare appointment office.');
                    }
                    $this->emailEvent('appointment', $tracking_no, 'booked');
                    $this->conn->commit();
                    return true;
                } else {
                    error_log("Appointment Insert Failed: " . $stmt1->error);
                }
            }
        } catch (Exception $e) {
            $this->conn->rollback();
            error_log("Appointment Database Crash: " . $e->getMessage());
        }
        $this->conn->rollback();
        return false;
    }
    
    public function getOfficeHistoryByDate($office_id, $date) {
        $this->autoCancelExpiredAppointments($office_id);
        $data = [];
        $query = "SELECT ao.*, a.appointment_date, a.appointment_time, a.tracking_no, a.guest_name, a.student_id, a.is_priority, a.priority, (acc.priority_type='PWD' AND acc.pwd_id_path IS NOT NULL AND acc.pwd_id_path<>'') AS has_pwd_id,
                         acc.fname, acc.lname, acc.course, a.notes as student_notes
                  FROM appointment_offices ao
                  JOIN appointments a ON ao.tracking_no = a.tracking_no
                  LEFT JOIN accounts acc ON a.student_id = acc.id
                  WHERE ao.office_id = ? AND a.appointment_date = ?
                  ORDER BY ao.time_completed DESC, ao.time_queued DESC, a.appointment_time ASC";
                  
        if ($stmt = $this->conn->prepare($query)) {
            $stmt->bind_param('is', $office_id, $date);
            $stmt->execute();
            $result = $stmt->get_result();
            while ($row = $result->fetch_assoc()) {
                $data[] = $row;
            }
            $stmt->close();
        }
        return $data;
    }
    
    public function displayOffices() {
        $data = [];
        $query = "SELECT * FROM offices ORDER BY office_name ASC";
        if ($stmt = $this->conn->prepare($query)) {
            $stmt->execute();
            $result = $stmt->get_result();
            while ($row = $result->fetch_assoc()) {
                $data[] = $row;
            }
            $stmt->close();
        }
        return $data;
    }

    public function addOffice($office_name) {
        $query = "INSERT INTO offices (office_name, status) VALUES (?, 1)";
        if ($stmt = $this->conn->prepare($query)) {
            $stmt->bind_param('s', $office_name);
            $result = $stmt->execute();
            $stmt->close();
            return $result;
        }
        return false;
    }

    public function editOffice($id, $office_name) {
        $query = "UPDATE offices SET office_name = ? WHERE id = ?";
        if ($stmt = $this->conn->prepare($query)) {
            $stmt->bind_param('si', $office_name, $id);
            $result = $stmt->execute();
            $stmt->close();
            return $result;
        }
        return false;
    }

    public function updateOfficeStatus($id, $status) {
        $query = "UPDATE offices SET status = ? WHERE id = ?";
        if ($stmt = $this->conn->prepare($query)) {
            $stmt->bind_param('ii', $status, $id);
            $result = $stmt->execute();
            $stmt->close();
            return $result;
        }
        return false;
    }

    public function countOfficeWindows($office_name) {
        $count = 0;
        $query = "SELECT COUNT(*) as total FROM accounts WHERE course = ? AND access = 2 AND status = 1";
        if ($stmt = $this->conn->prepare($query)) {
            $stmt->bind_param('s', $office_name);
            $stmt->execute();
            $result = $stmt->get_result();
            if ($row = $result->fetch_assoc()) {
                $count = $row['total'];
            }
            $stmt->close();
        }
        return $count;
    }
    
    public function editAccount($id, $fname, $mname, $lname, $uname, $contact, $address, $window, $office) {
        $current = $this->displayAccountDetails($id)[0] ?? null;
        if ($current) {
            $submitted = ['fname'=>$fname, 'mname'=>$mname, 'lname'=>$lname, 'uname'=>$uname,
                'contact'=>$contact, 'address'=>$address, 'year'=>$window, 'course'=>$office];
            $changed = false;
            foreach ($submitted as $field => $value) {
                if ((string)($current[$field] ?? '') !== (string)$value) { $changed = true; break; }
            }
            if (!$changed) return 'unchanged';
        } else {
            smartserve_notify('This account no longer exists.', 'danger', 'back');
            return false;
        }
        if (!SmartServeValidation::id($id) || !SmartServeValidation::name($fname, true, 30) || !SmartServeValidation::name($mname, false, 30) || !SmartServeValidation::name($lname, true, 30) || !SmartServeValidation::email($uname) || !SmartServeValidation::phone($contact) || !SmartServeValidation::text($address, true, 120) || !$this->hasActiveOffices([$office])) {
            smartserve_notify('Please check the account details and selected office.', 'danger', 'back');
            return false;
        }
        if ($this->checkPhoneExists($contact, $id)) {
            smartserve_notify('Phone number is already registered.', 'danger', 'back'); return false;
        }
        $duplicate = $this->conn->prepare('SELECT id FROM accounts WHERE uname = ? AND id <> ? LIMIT 1');
        $duplicate->bind_param('si', $uname, $id);
        $duplicate->execute();
        $exists = $duplicate->get_result()->num_rows > 0;
        $duplicate->close();
        if ($exists) {
            smartserve_notify('Email is already registered. Please use a different email.', 'danger', 'back');
            return false;
        }
        $query = "UPDATE accounts SET fname = ?, mname = ?, lname = ?, uname = ?, contact = ?, address = ?, year = ?, course = ? WHERE id = ?";

        try {
            if ($stmt = $this->conn->prepare($query)) {
                $stmt->bind_param('ssssssssi', $fname, $mname, $lname, $uname, $contact, $address, $window, $office, $id);
                $result = $this->withUniquePhone($contact, $id, function() use ($stmt) {
                    if (!$stmt->execute()) return false;
                    return $stmt->affected_rows > 0 ? true : 'unchanged';
                });
                $stmt->close();
                if ($result) return $result;
            }
        } catch (InvalidArgumentException $e) {
            if (isset($stmt)) $stmt->close();
            smartserve_notify($e->getMessage(), 'danger', 'back'); return false;
        } catch (Throwable $e) {
            if (isset($stmt)) $stmt->close();
            error_log($e->getMessage());
        }
        smartserve_notify('Unable to update this account. Please try again.', 'danger', 'back');
        return false;
    }
    
    public function getAccountsByOffice($office_id) {
        $data = [];
        $query = "SELECT fname, lname, photo, year FROM accounts WHERE course = ? AND access = 2 AND status = 1 ORDER BY year ASC";
        
        if ($stmt = $this->conn->prepare($query)) {
            $stmt->bind_param('s', $office_id); 
            $stmt->execute();
            $result = $stmt->get_result();
            while ($row = $result->fetch_assoc()) {
                $data[] = $row;
            }
            $stmt->close();
        }
        return $data;
    }
    
    public function checkExistingBooking($student_id, $date, $office_ids = [], $exclude_tracking = null) {
        $query = "SELECT a.id FROM appointments a
            JOIN appointment_offices ao ON ao.tracking_no=a.tracking_no
            WHERE a.student_id=? AND a.appointment_date=? AND a.status NOT IN (2,3) AND ao.status IN (0,1,2)";
        if ($office_ids) {
            if (!is_array($office_ids)) return true;
            foreach ($office_ids as $officeId) if (!SmartServeValidation::id($officeId)) return true;
            $query .= ' AND ao.office_id IN (' . implode(',', array_map('intval', $office_ids)) . ')';
        }
        if ($exclude_tracking !== null) $query .= ' AND a.tracking_no<>?';
        $query .= ' LIMIT 1';
        if ($stmt = $this->conn->prepare($query)) {
            if ($exclude_tracking !== null) $stmt->bind_param('sss', $student_id, $date, $exclude_tracking);
            else $stmt->bind_param('ss', $student_id, $date);
            $stmt->execute();
            $result = $stmt->get_result();
            $exists = $result->num_rows > 0;
            $stmt->close();
            return $exists;
        }
        return false;
    }
    
   public function verifyQRCode($tracking_no, $office_id) {
        $this->autoCancelExpiredAppointments($office_id);
        $response = ['status' => 'error', 'msg' => 'System error processing request.'];
        $unblocked = SmartServeAppointmentRules::unblockedServiceSQL();
        
        // 1. Verify the appointment exists for THIS office and is scheduled for TODAY
        $query = "SELECT ao.id, ao.status, ao.queue_number, a.status AS appointment_status, o.office_name, $unblocked AS service_available
                  FROM appointment_offices ao
                  JOIN appointments a ON ao.tracking_no = a.tracking_no
                  JOIN offices o ON ao.office_id = o.id
                  WHERE ao.tracking_no = ? 
                  AND ao.office_id = ? 
                  AND a.appointment_date = CURDATE()";
                  
        if ($stmt = $this->conn->prepare($query)) {
            $stmt->bind_param('si', $tracking_no, $office_id);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows > 0) {
                $row = $result->fetch_assoc();
                
                // 2. Route based on the current status
                if (!$row['service_available'] && in_array((int)$row['status'], [0,1], true)) {
                    $response['msg'] = 'This service schedule is blocked. Please review the service update and use the new QR after rescheduling.';
                } elseif ((int)$row['appointment_status'] !== 0) {
                    $response['msg'] = (int)$row['appointment_status'] === 3 ? 'This appointment has been cancelled. Please book a new schedule.' : 'This booking is no longer active.';
                } elseif ($row['status'] == 1) {
                    $response = [
                        'status' => 'success', 
                        'queue_number' => $row['queue_number'],
                        'msg' => 'You are already checked in!'
                    ];
                } elseif ($row['status'] == 2 || $row['status'] == 3) {
                    $response['msg'] = 'This ticket has already been served.';
                } elseif ($row['status'] == 4) {
                    $response['msg'] = 'This appointment was cancelled or marked as a no-show. Please book a new schedule.';
                } elseif ($row['status'] == 0) {
                    
                    // 3. GENERATE THE NEW QUEUE NUMBER USING MAX()
                    // This finds the highest number used today (e.g., if CAS-004 exists, it grabs 4)
                    $maxQuery = "SELECT MAX(CAST(SUBSTRING_INDEX(ao.queue_number, '-', -1) AS UNSIGNED)) as max_num 
                                 FROM appointment_offices ao 
                                 JOIN appointments a ON ao.tracking_no = a.tracking_no 
                                 WHERE ao.office_id = ? 
                                 AND a.appointment_date = CURDATE() 
                                 AND ao.queue_number IS NOT NULL 
                                 AND ao.queue_number != ''";
                                   
                    $qMax = 0;
                    if ($stmt2 = $this->conn->prepare($maxQuery)) {
                        $stmt2->bind_param('i', $office_id);
                        $stmt2->execute();
                        $res2 = $stmt2->get_result();
                        if ($row2 = $res2->fetch_assoc()) {
                            $qMax = $row2['max_num'] ? (int)$row2['max_num'] : 0;
                        }
                        $stmt2->close();
                    }
                    
                    // Create the prefix (e.g., "Registrar" becomes "REG")
                    $prefix = strtoupper(substr(preg_replace('/[^a-zA-Z]/', '', $row['office_name']), 0, 3));
                    // Add 1 to the highest number found
                    $next_num = sprintf("%03d", $qMax + 1);
                    $final_queue_number = $prefix . '-' . $next_num;
                    
                    // 4. UPDATE THE STATUS TO 'WAITING' (1)
                    $arrivalOpen = SmartServeAppointmentRules::arrivalOpenSQL();
                    $updateQ = "UPDATE appointment_offices ao JOIN appointments a ON ao.tracking_no = a.tracking_no
                                SET ao.status = 1, ao.queue_number = ?, ao.time_queued = NOW()
                                WHERE ao.id = ? AND ao.status = 0 AND a.status = 0 AND $arrivalOpen AND $unblocked";
                                
                    if ($stmt3 = $this->conn->prepare($updateQ)) {
                        $stmt3->bind_param('si', $final_queue_number, $row['id']);
                        if ($stmt3->execute() && $stmt3->affected_rows === 1) {
                            $this->emailEvent('appointment', $tracking_no, 'queued', $office_id);
                            $response = [
                                'status' => 'success',
                                'queue_number' => $final_queue_number,
                                'msg' => 'Check-in successful! Welcome.'
                            ];
                        } else {
                            $response['msg'] = 'The check-in window has closed or this ticket was already updated.';
                        }
                        $stmt3->close();
                    }
                }
            } else {
                $response['msg'] = 'Invalid tracking number or not scheduled for this department today.';
            }
            $stmt->close();
        }
        
        return $response;
    }
    

    public function getAdminReports($office_id = null, $start_date = null, $end_date = null, $status = null) {
        $this->autoCancelExpiredAppointments($office_id);
        $where_clauses = [];
        $params = [];
        $types = "";

        // Switched a.status to ao.status to match Staff-side logic exactly
        $query = "SELECT a.tracking_no, a.student_id, a.guest_name, a.appointment_date, a.appointment_time, 
                 a.is_priority, a.priority, 
                         (SELECT replacement.tracking_no FROM blocked_schedule_appointments notice
                          JOIN appointments replacement ON replacement.id=notice.new_appointment_id
                          WHERE notice.tracking_no=ao.tracking_no AND notice.office_id=ao.office_id
                          AND notice.action_status='rescheduled' AND replacement.id<>a.id
                          ORDER BY notice.id DESC LIMIT 1) AS rescheduled_tracking_no,
                         ao.status, ao.time_queued, ao.queue_number, ao.time_started, ao.time_completed, ao.served_at_window, ao.office_notes, 
                         o.office_name, 
                         s.fname, s.lname, (s.priority_type='PWD' AND s.pwd_id_path IS NOT NULL AND s.pwd_id_path<>'') AS has_pwd_id
                  FROM appointments a
                  JOIN appointment_offices ao ON a.tracking_no = ao.tracking_no
                  JOIN offices o ON ao.office_id = o.id
                  LEFT JOIN accounts s ON a.student_id = s.id";

        if (!empty($office_id)) {
            $where_clauses[] = "ao.office_id = ?";
            $params[] = $office_id;
            $types .= "i";
        }
        if (!empty($start_date)) {
            $where_clauses[] = "a.appointment_date >= ?";
            $params[] = $start_date;
            $types .= "s";
        }
        if (!empty($end_date)) {
            $where_clauses[] = "a.appointment_date <= ?";
            $params[] = $end_date;
            $types .= "s";
        }
        if ($status !== null && $status !== '') {
            $where_clauses[] = "ao.status = ?";
            $params[] = (int)$status;
            $types .= "i";
        }

        if (count($where_clauses) > 0) {
            $query .= " WHERE " . implode(" AND ", $where_clauses);
        }

        // Matched Staff-side sorting: Completed first, then Queued, then Appointment Time
        $query .= " ORDER BY ao.time_completed DESC, ao.time_queued DESC, a.appointment_time ASC";

        $records = [];
        $total_completed = 0;
        $total_noshow = 0;
        $total_process_seconds = 0;
        $process_count = 0;
        $hour_counts = [];

        if ($stmt = $this->conn->prepare($query)) {
            if ($types) {
                $stmt->bind_param($types, ...$params);
            }
            $stmt->execute();
            $result = $stmt->get_result();
            
            while ($row = $result->fetch_assoc()) {
                // Match Staff Logic exactly based on ao.status
                if ($row['status'] == 3) { 
                    $total_completed++;
                    
                    if (!empty($row['time_started']) && !empty($row['time_completed'])) {
                        $start = strtotime($row['time_started']);
                        $end = strtotime($row['time_completed']);
                        if ($end > $start) {
                            $total_process_seconds += ($end - $start);
                            $process_count++;
                        }
                    }
                } elseif ($row['status'] == 4) {
                    $total_noshow++;
                }

                $time_for_peak = !empty($row['time_queued']) ? $row['time_queued'] : $row['appointment_time'];
                if (!empty($time_for_peak)) {
                    $hour = date('h A', strtotime($time_for_peak));
                    if (!isset($hour_counts[$hour])) $hour_counts[$hour] = 0;
                    $hour_counts[$hour]++;
                }
                
                $records[] = $row;
            }
            $stmt->close();
        }

        $avg_process_display = "N/A";
        if ($process_count > 0) {
            $avg_seconds = $total_process_seconds / $process_count;
            $avg_minutes = floor($avg_seconds / 60);
            $avg_process_display = ($avg_minutes > 0) ? $avg_minutes . " min" : "< 1 min";
        }
        
        $peak_hour = "N/A";
        if (!empty($hour_counts)) {
            arsort($hour_counts); 
            $peak_hour = key($hour_counts); 
        }

        return [
            'records' => $records,
            'metrics' => [
                'total_completed' => $total_completed,
                'total_noshow' => $total_noshow,
                'avg_process' => $avg_process_display,
                'peak_hour' => $peak_hour
            ]
        ];
    }
    
    public function getStudentName($student_id) {
        // Adjust 'students', 'fname', and 'lname' to match your actual database!
        $query = "SELECT CONCAT(fname, ' ', lname) AS full_name FROM accounts WHERE id = ?";
        
        if ($stmt = $this->conn->prepare($query)) {
            $stmt->bind_param('s', $student_id);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($row = $result->fetch_assoc()) {
                return $row['full_name'];
            }
            $stmt->close();
        }
        
        // Fallback if the student isn't found in the database
        return "Student ID: " . htmlspecialchars($student_id); 
    }
    
    public function getGraphData($office_id = null, $start_date = null, $end_date = null, $status = null) {
        $this->autoCancelExpiredAppointments($office_id);
        $where_clauses = [];
        $params = [];
        $types = "";

        // Graphs show weekday service visits; weekend history remains in records reports.
        $query = "SELECT a.appointment_date, 
                         COALESCE(ao.time_queued, a.appointment_time) as active_time 
                  FROM appointments a
                  JOIN appointment_offices ao ON a.tracking_no = ao.tracking_no
                  WHERE WEEKDAY(a.appointment_date) BETWEEN 0 AND 4";

        if (!empty($office_id)) {
            $where_clauses[] = "ao.office_id = ?";
            $params[] = $office_id;
            $types .= "i";
        }
        if (!empty($start_date)) {
            $where_clauses[] = "a.appointment_date >= ?";
            $params[] = $start_date;
            $types .= "s";
        }
        if (!empty($end_date)) {
            $where_clauses[] = "a.appointment_date <= ?";
            $params[] = $end_date;
            $types .= "s";
        }
        if ($status !== null && $status !== '') {
            $where_clauses[] = "ao.status = ?";
            $params[] = (int)$status;
            $types .= "i";
        }

        if (count($where_clauses) > 0) {
            $query .= " AND " . implode(" AND ", $where_clauses);
        }

        $days_count = [
            'Monday' => 0, 'Tuesday' => 0, 'Wednesday' => 0, 
            'Thursday' => 0, 'Friday' => 0
        ];
        
        // Initialize hours from 8 AM to 5 PM
        $hours_count = [];
        $start_hour = strtotime('08:00 AM');
        $end_hour = strtotime('05:00 PM');
        for ($i = $start_hour; $i <= $end_hour; $i += 3600) {
            $hours_count[date('h A', $i)] = 0;
        }

        if ($stmt = $this->conn->prepare($query)) {
            if ($types) {
                $stmt->bind_param($types, ...$params);
            }
            $stmt->execute();
            $result = $stmt->get_result();
            
            while ($row = $result->fetch_assoc()) {
                // 1. Group by Day of Week
                $day_name = date('l', strtotime($row['appointment_date']));
                if (isset($days_count[$day_name])) {
                    $days_count[$day_name]++;
                }

                // 2. Group by Hour
                if (!empty($row['active_time'])) {
                    $hour_name = date('h A', strtotime($row['active_time']));
                    // If the hour falls outside 8AM-5PM, we can still dynamically add it, or restrict it.
                    // We will dynamically add it just in case they arrived early/late.
                    if (!isset($hours_count[$hour_name])) {
                        $hours_count[$hour_name] = 0; 
                    }
                    $hours_count[$hour_name]++;
                }
            }
            $stmt->close();
        }

        // We want to sort the hours chronologically before returning
        uksort($hours_count, function($a, $b) {
            return strtotime($a) - strtotime($b);
        });

        return [
            'days_labels' => array_keys($days_count),
            'days_data' => array_values($days_count),
            'hours_labels' => array_keys($hours_count),
            'hours_data' => array_values($hours_count)
        ];
    }
    
    public function getPendingSMSAppointments() {
        $data = [];
        $scheduled = SmartServeAppointmentRules::SCHEDULED_AT_SQL;
        $minutes = SmartServeAppointmentRules::SMS_REMINDER_MINUTES;
        $query = "SELECT a.id, a.tracking_no, a.appointment_time, acc.fname, acc.contact 
                  FROM appointments a 
                  JOIN accounts acc ON a.student_id = acc.id 
                  WHERE a.appointment_date = CURDATE() 
                  AND (a.sms_notif IS NULL OR a.sms_notif = 0) 
                  AND a.status=0 AND a.is_walkin=0 AND acc.status=1
                  AND (a.sms_claimed_at IS NULL OR a.sms_claimed_at < DATE_SUB(NOW(),INTERVAL 2 MINUTE))
                  AND $scheduled > NOW() AND $scheduled <= DATE_ADD(NOW(), INTERVAL $minutes MINUTE)
                  AND EXISTS (SELECT 1 FROM appointment_offices ao WHERE ao.tracking_no=a.tracking_no AND ao.status=0)";
                  
        if ($stmt = $this->conn->prepare($query)) {
            $stmt->execute();
            $result = $stmt->get_result();
            while ($row = $result->fetch_assoc()) {
                $data[] = $row;
            }
            $stmt->close();
        }
        return $data;
    }

    // Update the database so we don't spam them with texts every minute
    public function markSMSSent($appointment_id) {
        $query = "UPDATE appointments SET sms_notif = 1, sms_claimed_at=NULL WHERE id = ?";
        if ($stmt = $this->conn->prepare($query)) {
            $stmt->bind_param('i', $appointment_id);
            $stmt->execute();
            $stmt->close();
        }
    }

    public function runSmsReminders($transport = null) {
        require_once __DIR__ . '/sms.php';
        $transport = $transport ?: 'sendTextbeeSMS';
        $result = ['sent'=>0,'failed'=>0];
        foreach ($this->getPendingSMSAppointments() as $appt) {
            $stmt=$this->conn->prepare('UPDATE appointments SET sms_claimed_at=NOW() WHERE id=? AND status=0 AND COALESCE(sms_notif,0)=0 AND (sms_claimed_at IS NULL OR sms_claimed_at < DATE_SUB(NOW(),INTERVAL 2 MINUTE))');
            $stmt->bind_param('i',$appt['id']); $stmt->execute(); $claimed=$stmt->affected_rows===1; $stmt->close();
            if (!$claimed) continue;
            $number=normalizePhilippineMobileNumber($appt['contact'] ?? '');
            $grace=SmartServeAppointmentRules::ARRIVAL_GRACE_MINUTES;
            $time=date('h:i A',strtotime($appt['appointment_time']));
            $message="SmartServe: Hi {$appt['fname']}, your appointment {$appt['tracking_no']} is in 10 minutes at $time. Scan your QR code at the campus kiosk to check in. If you have not scanned within $grace minutes after your scheduled time, your booking will be cancelled.";
            try { $ok=$number && $transport($number,$message); } catch (Throwable $e) { $ok=false; }
            if ($ok) { $this->markSMSSent($appt['id']); $result['sent']++; }
            else { $stmt=$this->conn->prepare('UPDATE appointments SET sms_claimed_at=NULL WHERE id=?'); $stmt->bind_param('i',$appt['id']); $stmt->execute(); $stmt->close(); $result['failed']++; }
        }
        return $result;
    }
    
    // Check if email exists for Password Reset
    public function checkEmailExists($email) {
        $query = "SELECT id FROM accounts WHERE uname = ?";
        if ($stmt = $this->conn->prepare($query)) {
            $stmt->bind_param('s', $email);
            $stmt->execute();
            $stmt->store_result();
            $exists = $stmt->num_rows > 0;
            $stmt->close();
            return $exists;
        }
        return false;
    }

    // Update the password
    public function updatePassword($email, $new_password) {
        $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
        $query = "UPDATE accounts SET pword = ? WHERE uname = ?";
        if ($stmt = $this->conn->prepare($query)) {
            $stmt->bind_param('ss', $hashed_password, $email);
            $success = $stmt->execute();
            $changed = $stmt->affected_rows === 1;
            $stmt->close();
            if ($success && $changed) {
                $account = $this->displayBasicProfile($email);
                if (!empty($account[0]['id'])) $this->emailEvent('account', $account[0]['id'], 'password');
            }
            return $success;
        }
        return false;
    }

    public function displayFaqs($status = null) {
        $data = [];
        $query = "SELECT faq_id, question, answer, status FROM faqs";

        if ($status !== null) {
            $query .= " WHERE status = ?";
        }

        $query .= " ORDER BY faq_id ASC";

        if ($stmt = $this->conn->prepare($query)) {
            if ($status !== null) {
                $stmt->bind_param('i', $status);
            }
            $stmt->execute();
            $result = $stmt->get_result();
            while ($row = $result->fetch_assoc()) {
                $data[] = $row;
            }
            $stmt->close();
        }

        return $data;
    }

    public function addFaq($question, $answer) {
        $query = "INSERT INTO faqs (question, answer, status) VALUES (?, ?, 1)";
        if ($stmt = $this->conn->prepare($query)) {
            $stmt->bind_param('ss', $question, $answer);
            $result = $stmt->execute();
            $stmt->close();
            return $result;
        }
        return false;
    }

    public function editFaq($faq_id, $question, $answer) {
        $query = "UPDATE faqs SET question = ?, answer = ? WHERE faq_id = ?";
        if ($stmt = $this->conn->prepare($query)) {
            $stmt->bind_param('ssi', $question, $answer, $faq_id);
            $result = $stmt->execute();
            $stmt->close();
            return $result;
        }
        return false;
    }

    public function deleteFaq($faq_id) {
        $query = "UPDATE faqs SET status = 0 WHERE faq_id = ?";
        if ($stmt = $this->conn->prepare($query)) {
            $stmt->bind_param('i', $faq_id);
            $result = $stmt->execute();
            $stmt->close();
            return $result;
        }
        return false;
    }
    
    

    public function website_details(){
        $data = null;
        $query = "SELECT * FROM web_details ORDER BY web_id DESC LIMIT 1";
        if ($sql = $this->conn->query($query)) {
            while ($row = mysqli_fetch_assoc($sql)) {
                $data[] = $row;
            }
        }
        return $data;
    }

    public function getPendingSMSAppointmentsByOffice($office_id) {
        $data = [];
        $query = "SELECT DISTINCT acc.id, acc.fname, acc.lname, acc.contact
                  FROM appointments a
                  JOIN appointment_offices ao ON a.tracking_no = ao.tracking_no
                  JOIN accounts acc ON a.student_id = acc.id
                  WHERE ao.office_id = ?
                  AND a.appointment_date = CURDATE()
                  AND a.status = 0
                  AND ao.status = 0
                  AND (a.sms_notif IS NULL OR a.sms_notif = 0)";

        if ($stmt = $this->conn->prepare($query)) {
            $stmt->bind_param('i', $office_id);
            $stmt->execute();
            $result = $stmt->get_result();
            while ($row = $result->fetch_assoc()) {
                $data[] = $row;
            }
            $stmt->close();
        }

        return $data;
    }

    public function getAllPendingSMSAppointments() {
        $data = [];
        $query = "SELECT DISTINCT acc.id, acc.fname, acc.lname, acc.contact
                  FROM appointments a
                  JOIN accounts acc ON a.student_id = acc.id
                  WHERE a.student_id IS NOT NULL
                  AND a.status = 0
                  AND (a.sms_notif IS NULL OR a.sms_notif = 0)";

        if ($stmt = $this->conn->prepare($query)) {
            $stmt->execute();
            $result = $stmt->get_result();
            while ($row = $result->fetch_assoc()) {
                $data[] = $row;
            }
            $stmt->close();
        }

        return $data;
    }

    public function createBlockedSchedule($office_id, $block_date, $start_time, $end_time, $block_type, $reason, $note, $created_by, $target_purpose = null) {
        if ($block_type === 'time_range' && !SmartServeValidation::blockTimeRange($start_time, $end_time)) return 0;
        $query = "INSERT INTO blocked_schedules (office_id, block_date, start_time, end_time, block_type, target_purpose, reason, note, status, created_by, date_created)
                  VALUES (?, ?, ?, ?, ?, ?, ?, ?, 1, ?, NOW())";
        if ($stmt = $this->conn->prepare($query)) {
            $stmt->bind_param('isssssssi', $office_id, $block_date, $start_time, $end_time, $block_type, $target_purpose, $reason, $note, $created_by);
            $ok = $stmt->execute();
            $id = $this->conn->insert_id;
            $stmt->close();
            return $ok ? $id : 0;
        }
        return 0;
    }

    public function getBlockedSchedulesByOffice($office_id) {
        $data = [];
        $query = "SELECT bs.*, 
                         SUM(CASE WHEN bsa.action_status IN ('pending','notified') THEN 1 ELSE 0 END) AS waiting_count,
                         SUM(CASE WHEN bsa.action_status = 'reschedule_requested' THEN 1 ELSE 0 END) AS requested_count,
                         SUM(CASE WHEN bsa.action_status = 'rescheduled' THEN 1 ELSE 0 END) AS rescheduled_count,
                         SUM(CASE WHEN bsa.action_status = 'cancelled' THEN 1 ELSE 0 END) AS cancelled_count
                  FROM blocked_schedules bs
                  LEFT JOIN blocked_schedule_appointments bsa ON bs.id = bsa.block_id
                  WHERE bs.office_id = ?
                  GROUP BY bs.id
                  ORDER BY bs.block_date DESC, bs.start_time DESC, bs.id DESC";

        if ($stmt = $this->conn->prepare($query)) {
            $stmt->bind_param('i', $office_id);
            $stmt->execute();
            $result = $stmt->get_result();
            while ($row = $result->fetch_assoc()) {
                $data[] = $row;
            }
            $stmt->close();
        }
        return $data;
    }

    public function getPendingAppointmentsForBlock($office_id, $block_date, $start_time = null, $end_time = null, $block_type = 'full_day', $target_purpose = null) {
        $data = [];
        $query = "SELECT a.id AS appointment_id, a.tracking_no, a.student_id, a.appointment_date, a.appointment_time,
                         acc.fname, acc.lname, acc.contact
                  FROM appointments a
                  JOIN appointment_offices ao ON a.tracking_no = ao.tracking_no
                  LEFT JOIN accounts acc ON a.student_id = acc.id
                  WHERE ao.office_id = ?
                  AND a.appointment_date = ?
                  AND a.status = 0
                  AND ao.status IN (0, 1)";

        if ($block_type === 'time_range') {
            $query .= " AND TIME(COALESCE(
                            STR_TO_DATE(a.appointment_time, '%h:%i %p'),
                            STR_TO_DATE(a.appointment_time, '%H:%i:%s'),
                            STR_TO_DATE(a.appointment_time, '%H:%i')
                        )) BETWEEN ? AND ?";
        } elseif ($block_type === 'specific_purpose') {
            $query .= " AND LOWER(TRIM(a.notes)) = LOWER(TRIM(?))";
        }

        if ($stmt = $this->conn->prepare($query)) {
            if ($block_type === 'full_day') {
                $stmt->bind_param('is', $office_id, $block_date);
            } elseif ($block_type === 'time_range') {
                $stmt->bind_param('isss', $office_id, $block_date, $start_time, $end_time);
            } else {
                $target_purpose = trim((string)$target_purpose);
                $stmt->bind_param('iss', $office_id, $block_date, $target_purpose);
            }
            $stmt->execute();
            $result = $stmt->get_result();
            while ($row = $result->fetch_assoc()) {
                $data[] = $row;
            }
            $stmt->close();
        }
        return $data;
    }

    public function addBlockedScheduleAppointment($block_id, $appointment_id, $tracking_no, $student_id, $office_id, $original_date, $original_time, $action_status = 'notified') {
        $query = "INSERT INTO blocked_schedule_appointments
                    (block_id, appointment_id, tracking_no, student_id, office_id, original_date, original_time, action_status, notified_at, date_created)
                  VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())";
        if ($stmt = $this->conn->prepare($query)) {
            $stmt->bind_param('iisiisss', $block_id, $appointment_id, $tracking_no, $student_id, $office_id, $original_date, $original_time, $action_status);
            $ok = $stmt->execute();
            $stmt->close();
            if ($ok) $this->emailEvent('blocked', $block_id, $tracking_no, $office_id);
            return $ok;
        }
        return false;
    }

    public function markBlockScheduleSmsSent($block_id) {
        $query = "UPDATE blocked_schedules SET sms_sent = 1, sms_sent_at = NOW() WHERE id = ?";
        if ($stmt = $this->conn->prepare($query)) {
            $stmt->bind_param('i', $block_id);
            $ok = $stmt->execute();
            $stmt->close();
            return $ok;
        }
        return false;
    }

    public function getSlotBlockInfo($office_id, $date, $time) {
        $data = null;
        $query = "SELECT bs.id, bs.reason, bs.note, bs.block_type, bs.start_time, bs.end_time, o.office_name
                  FROM blocked_schedules bs
                  JOIN offices o ON bs.office_id = o.id
                  WHERE bs.office_id = ?
                  AND bs.block_date = ?
                  AND bs.status = 1
                  AND (
                      bs.block_type = 'full_day'
                      OR TIME(COALESCE(
                            STR_TO_DATE(?, '%h:%i %p'),
                            STR_TO_DATE(?, '%H:%i:%s'),
                            STR_TO_DATE(?, '%H:%i')
                         )) BETWEEN bs.start_time AND bs.end_time
                  )
                  ORDER BY bs.id DESC
                  LIMIT 1";

        if ($stmt = $this->conn->prepare($query)) {
            $stmt->bind_param('issss', $office_id, $date, $time, $time, $time);
            $stmt->execute();
            $result = $stmt->get_result();
            if ($row = $result->fetch_assoc()) {
                $data = $row;
            }
            $stmt->close();
        }
        return $data;
    }

    public function getPurposeBlockInfo($office_id, $date, $purpose) {
        $data = null;
        $query = "SELECT bs.id, bs.reason, bs.note, bs.block_type, bs.target_purpose, o.office_name
                  FROM blocked_schedules bs
                  JOIN offices o ON bs.office_id = o.id
                  WHERE bs.office_id = ?
                  AND bs.block_date = ?
                  AND bs.status = 1
                  AND bs.block_type = 'specific_purpose'
                  AND LOWER(TRIM(bs.target_purpose)) = LOWER(TRIM(?))
                  ORDER BY bs.id DESC
                  LIMIT 1";

        if ($stmt = $this->conn->prepare($query)) {
            $stmt->bind_param('iss', $office_id, $date, $purpose);
            $stmt->execute();
            $result = $stmt->get_result();
            if ($row = $result->fetch_assoc()) {
                $data = $row;
            }
            $stmt->close();
        }
        return $data;
    }

    public function getStudentBlockedAppointments($student_id) {
        $data = [];
        $query = "SELECT bsa.id, bsa.block_id, bsa.appointment_id, bsa.tracking_no, bsa.original_date, bsa.original_time,
                         bsa.action_status, bsa.reschedule_date_choice, bsa.reschedule_time_choice,
                         bs.block_date, bs.block_type, bs.start_time, bs.end_time, bs.reason, bs.note,
                         o.office_name
                  FROM blocked_schedule_appointments bsa
                  JOIN blocked_schedules bs ON bsa.block_id = bs.id
                  JOIN offices o ON bsa.office_id = o.id
                  JOIN appointments a ON bsa.appointment_id = a.id
                  JOIN appointment_offices ao ON ao.tracking_no=a.tracking_no AND ao.office_id=bsa.office_id AND ao.status IN (0,1)
                  WHERE bsa.student_id = ?
                  AND bs.status = 1
                  AND a.status = 0
                  AND bsa.action_status IN ('pending','notified','reschedule_requested')
                  ORDER BY bs.block_date ASC, bsa.id ASC";

        if ($stmt = $this->conn->prepare($query)) {
            $stmt->bind_param('i', $student_id);
            $stmt->execute();
            $result = $stmt->get_result();
            while ($row = $result->fetch_assoc()) {
                $data[] = $row;
            }
            $stmt->close();
        }
        return $data;
    }

    public function getBlockChoiceSummary($block_id) {
        $summary = ['cancelled' => 0, 'choices' => []];

        $cancel_query = "SELECT COUNT(*) AS total FROM blocked_schedule_appointments WHERE block_id = ? AND action_status = 'cancelled'";
        if ($stmt = $this->conn->prepare($cancel_query)) {
            $stmt->bind_param('i', $block_id);
            $stmt->execute();
            $result = $stmt->get_result();
            if ($row = $result->fetch_assoc()) {
                $summary['cancelled'] = (int)$row['total'];
            }
            $stmt->close();
        }

        $choice_query = "SELECT reschedule_date_choice, reschedule_time_choice, COUNT(*) AS total
                         FROM blocked_schedule_appointments
                         WHERE block_id = ?
                         AND action_status IN ('reschedule_requested','rescheduled')
                         AND reschedule_date_choice IS NOT NULL
                         AND reschedule_time_choice IS NOT NULL
                         GROUP BY reschedule_date_choice, reschedule_time_choice
                         ORDER BY reschedule_date_choice ASC, reschedule_time_choice ASC";

        if ($stmt = $this->conn->prepare($choice_query)) {
            $stmt->bind_param('i', $block_id);
            $stmt->execute();
            $result = $stmt->get_result();
            while ($row = $result->fetch_assoc()) {
                $summary['choices'][] = $row;
            }
            $stmt->close();
        }

        return $summary;
    }

    public function submitBlockedAppointmentResponse($blocked_row_id, $student_id, $action, $new_date = null, $new_time = null) {
        if (!in_array($action, ['cancel', 'reschedule'], true)) return ['ok'=>false, 'msg'=>'Invalid action.'];
        if ($action === 'reschedule' && (!SmartServeValidation::bookingDate($new_date) || !in_array($new_time, SmartServeValidation::SLOTS, true))) {
            return ['ok'=>false, 'msg'=>'Please select a weekday (Monday to Friday) within the next seven days and an available time.'];
        }
        try {
            $this->conn->begin_transaction();
            // Match online booking's account lock to serialize duplicate requests.
            $lock = $this->conn->prepare('SELECT id FROM accounts WHERE id=? AND access=3 AND status=1 FOR UPDATE');
            $lock->bind_param('i', $student_id); $lock->execute();
            $studentExists = $lock->get_result()->num_rows === 1; $lock->close();
            if (!$studentExists) { $this->conn->rollback(); return ['ok'=>false, 'msg'=>'Please sign in with an active student account.']; }
            $stmt = $this->conn->prepare("SELECT bsa.*, a.notes AS appointment_notes, ao.id AS visit_id
                FROM blocked_schedule_appointments bsa
                JOIN blocked_schedules bs ON bs.id=bsa.block_id AND bs.status=1
                JOIN appointments a ON a.id=bsa.appointment_id AND a.student_id=bsa.student_id
                JOIN appointment_offices ao ON ao.tracking_no=a.tracking_no AND ao.office_id=bsa.office_id
                WHERE bsa.id=? AND bsa.student_id=? AND a.status=0 AND ao.status IN (0,1)
                AND bsa.action_status IN ('pending','notified','reschedule_requested') LIMIT 1 FOR UPDATE");
            $stmt->bind_param('ii', $blocked_row_id, $student_id); $stmt->execute();
            $row = $stmt->get_result()->fetch_assoc(); $stmt->close();
            if (!$row) { $this->conn->rollback(); return ['ok'=>false, 'msg'=>'This service update was already handled or the service is no longer awaiting a response.']; }

            $replacementId = null;
            $tracking = $row['tracking_no'];
            if ($action === 'reschedule') {
                $stmt = $this->conn->prepare("SELECT TIMESTAMP(?, STR_TO_DATE(?, '%h:%i %p')) > DATE_ADD(NOW(), INTERVAL 30 MINUTE) AS available");
                $stmt->bind_param('ss', $new_date, $new_time); $stmt->execute();
                $futureSlot = (bool)$stmt->get_result()->fetch_row()[0]; $stmt->close();
                $officeId = (int)$row['office_id'];
                $lock = $this->conn->prepare('SELECT id FROM offices WHERE id=? AND status=1 FOR UPDATE');
                $lock->bind_param('i', $officeId); $lock->execute();
                $activeOffice = $lock->get_result()->num_rows === 1; $lock->close();
                if (!$futureSlot || !$activeOffice || $this->getSlotBlockInfo($officeId, $new_date, $new_time)
                    || $this->getPurposeBlockInfo($officeId, $new_date, $row['appointment_notes'])
                    || $this->getBookedSlots($officeId, $new_date, $new_time) >= 5
                    || $this->checkExistingBooking($student_id, $new_date, [$officeId], $row['tracking_no'])) {
                    $this->conn->rollback();
                    return ['ok'=>false, 'msg'=>'This service is blocked, full, too close to its start time, or already booked by you on that date. Please choose another schedule.'];
                }
                $tracking = 'ONL-' . date('Ymd') . '-' . strtoupper(bin2hex(random_bytes(6)));
                $stmt = $this->conn->prepare("INSERT INTO appointments
                    (tracking_no,student_id,appointment_date,appointment_time,notes,is_priority,priority,status,is_walkin,rescheduled_from_id,sms_notif)
                    SELECT ?,student_id,?,?,notes,is_priority,priority,0,0,id,0 FROM appointments WHERE id=? AND student_id=?");
                $stmt->bind_param('sssii', $tracking, $new_date, $new_time, $row['appointment_id'], $student_id);
                $stmt->execute(); $replacementId = $this->conn->insert_id; $stmt->close();
                $stmt = $this->conn->prepare("SELECT COUNT(*)+1 FROM appointment_offices ao JOIN appointments a ON a.tracking_no=ao.tracking_no WHERE ao.office_id=? AND a.appointment_date=?");
                $stmt->bind_param('is', $officeId, $new_date); $stmt->execute();
                $queue = 'ONL-' . str_pad($stmt->get_result()->fetch_row()[0], 3, '0', STR_PAD_LEFT); $stmt->close();
                $stmt = $this->conn->prepare('INSERT INTO appointment_offices(tracking_no,office_id,status,queue_number) VALUES (?,?,0,?)');
                $stmt->bind_param('sis', $tracking, $officeId, $queue); $stmt->execute(); $stmt->close();
            }
            $note = $action === 'reschedule' ? 'Rescheduled to ' . $tracking . ' after office schedule block.' : 'Cancelled by student after blocked service notice.';
            $stmt = $this->conn->prepare('UPDATE appointment_offices SET status=4,office_notes=? WHERE id=? AND status IN (0,1)');
            $stmt->bind_param('si', $note, $row['visit_id']); $stmt->execute();
            if ($stmt->affected_rows !== 1) throw new RuntimeException('Service was already updated.');
            $stmt->close();
            $resolved = $action === 'reschedule' ? 'rescheduled' : 'cancelled';
            // Resolve overlapping notices for this visit together; other services stay untouched.
            $stmt = $this->conn->prepare("UPDATE blocked_schedule_appointments SET action_status=?,response_at=NOW(),new_appointment_id=?,reschedule_date_choice=?,reschedule_time_choice=?
                WHERE appointment_id=? AND office_id=? AND student_id=? AND action_status IN ('pending','notified','reschedule_requested')");
            $stmt->bind_param('sissiii', $resolved, $replacementId, $new_date, $new_time, $row['appointment_id'], $row['office_id'], $student_id);
            $stmt->execute(); $stmt->close();
            $this->syncAppointmentCompletion($row['tracking_no']);
            if ($action === 'reschedule') $this->emailEvent('appointment', $tracking, 'rescheduled', $row['office_id'], ['Original booking'=>$row['tracking_no']], 'rescheduled:' . $blocked_row_id);
            else $this->emailEvent('appointment', $tracking, 'cancelled', $row['office_id']);
            $this->conn->commit();
            return ['ok'=>true, 'tracking_no'=>$tracking, 'msg'=>$action === 'reschedule'
                ? 'This service has a new booking and QR code. Your other services keep their original schedule and progress.'
                : 'The blocked service has been cancelled. Your other services are unchanged.'];
        } catch (Throwable $e) {
            $this->conn->rollback();
            error_log('SmartServe: blocked service response failed (' . get_class($e) . ').');
            return ['ok'=>false, 'msg'=>'Unable to update this service. Please try again.'];
        }
    }

} // <--- END OF CLASS MODEL

// Helper function placed safely outside the class
if (!function_exists('timeAgo')) {
    function timeAgo($datetime) {
        $timestamp = is_numeric($datetime) ? $datetime : strtotime($datetime);
        $diff = time() - $timestamp;

        if ($diff < 60) {
            return "a few seconds ago";
        } elseif ($diff < 3600) {
            $minutes = floor($diff / 60);
            return $minutes . " minute" . ($minutes > 1 ? "s" : "") . " ago";
        } elseif ($diff < 86400) {
            $hours = floor($diff / 3600);
            return $hours . " hour" . ($hours > 1 ? "s" : "") . " ago";
        } elseif ($diff < 2592000) { // 30 days
            $days = floor($diff / 86400);
            return $days . " day" . ($days > 1 ? "s" : "") . " ago";
        } elseif ($diff < 31536000) { // 12 months
            $months = floor($diff / 2592000);
            return $months . " month" . ($months > 1 ? "s" : "") . " ago";
        } else {
            $years = floor($diff / 31536000);
            return $years . " year" . ($years > 1 ? "s" : "") . " ago";
        }
    }
}

?>
