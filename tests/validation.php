<?php
require_once __DIR__ . '/../global/validation.php';
date_default_timezone_set('Asia/Manila');
$checks = 0;
function check($condition, $label) {
    global $checks;
    $checks++;
    if (!$condition) throw new RuntimeException($label);
}
foreach (['Juan', 'María-José', 'Dela Cruz', 'Peña', "Jose\u{0301}"] as $name) check(SmartServeValidation::name($name), 'Valid name: ' . $name);
foreach (['Juan2', 'Juan!', "O'Neil", '   ', '--', "Juan\nCruz", '<script>'] as $name) check(!SmartServeValidation::name($name), 'Reject name: ' . $name);
check(SmartServeValidation::name('', false), 'Optional middle name');
check(!SmartServeValidation::name(str_repeat('ñ', 51)), 'Name character limit');
foreach (['09123456789', '+639123456789'] as $phone) check(SmartServeValidation::phone($phone), 'Valid phone: ' . $phone);
foreach (['0912345678', '091234567890', '+63912345678', '+6391234567890', '08123456789', '639123456789', '+6309123456789', '09 123456789', '09123-456789', '09abcdefghi', '０９１２３４５６７８９'] as $phone) check(!SmartServeValidation::phone($phone), 'Reject phone: ' . $phone);
check(!SmartServeValidation::phone(''), 'Contact number is required');
foreach (['sample@edu.ph', 'a+b@example.com', 'sample@example.co.uk', 'juan@sub.example.ph'] as $email) check(SmartServeValidation::email($email), 'Valid email: ' . $email);
foreach (['sample@edu', 'sample@', 'sample@edu.', 'sample@.edu.ph', 'sample@example.c', 'sample@-edu.ph', 'sample@edu..ph', 'sample @edu.ph', '.sample@edu.ph', 'sample..x@edu.ph'] as $email) check(!SmartServeValidation::email($email), 'Reject email: ' . $email);
check(SmartServeValidation::password('Secure#123'), 'Strong password');
check(!SmartServeValidation::password('Ab#1ñññ'), 'Seven Unicode characters do not meet the eight-character minimum');
check(SmartServeValidation::password('Ab#1ññññ'), 'Eight Unicode characters meet the minimum');
check(SmartServeValidation::password(str_repeat('a', 68) . 'Ab#1'), '72-character ASCII password fits secure storage');
check(!SmartServeValidation::password(str_repeat('ñ', 35) . 'Ab#1'), 'Multibyte password cannot exceed bcrypt storage capacity');
check(!SmartServeValidation::password("Secure#1\0"), 'Null characters cannot reach password hashing');
foreach (['12345678', 'password', 'Secure123', 'Weak#1', 'Secure 123!', str_repeat('a', 70) . '#12'] as $password) check(!SmartServeValidation::password($password), 'Reject weak or long password');
check(SmartServeValidation::text('123-A Rizal St., Brgy. 2', true, 120), 'Address numbers and punctuation');
check(!SmartServeValidation::text('   ', true, 120), 'Whitespace address');
check(!SmartServeValidation::date('2026-02-31'), 'Impossible date');
check(SmartServeValidation::date('2028-02-29'), 'Leap date');
check(SmartServeValidation::dateTime('2026-10-12T13:45'), 'Advisory date/time');
check(!SmartServeValidation::dateTime('2026-02-31T13:45'), 'Impossible advisory date');

$registration = ['submit_verify' => '', 'fname' => 'Juan-Peña', 'lname' => 'Dela Cruz', 'uname' => 'juan@example.ph', 'contact' => '+639123456789', 'address' => '123-A Rizal St.', 'password' => 'Secure#123', 'confirm_password' => 'Secure#123', 'year' => '1st Year', 'course' => 'BS Computer Science'];
check(SmartServeValidation::request($registration, '/smartserve/register.php') === [], 'Complete valid registration');
foreach (['fname', 'lname', 'uname', 'contact', 'address', 'password', 'confirm_password', 'year', 'course'] as $field) {
    $missing = $registration;
    unset($missing[$field]);
    check(isset(SmartServeValidation::request($missing, '/smartserve/register.php')[$field]), 'Missing registration ' . $field);
}
foreach (['fname' => ['Juan'], 'uname' => 'sample@edu', 'contact' => '091234567890', 'year' => '7th Year', 'course' => 'Invented Program', 'confirm_password' => 'Other#123'] as $field => $value) {
    check(isset(SmartServeValidation::request(array_replace($registration, [$field => $value]), '/smartserve/register.php')[$field]), 'Invalid registration ' . $field);
}
foreach (['5th Year', '6th Year'] as $year) {
    check(SmartServeValidation::request(array_replace($registration, ['year' => $year]), '/smartserve/register.php') === [], 'Registration accepts ' . $year);
}
$missingRegistration = SmartServeValidation::request(['submit_verify' => ''], '/smartserve/register.php');
check($missingRegistration['fname'] === 'First Name is required.' && $missingRegistration['lname'] === 'Last Name is required.', 'Required names identify the field');
check($missingRegistration['uname'] === 'Email Address is required.' && $missingRegistration['contact'] === 'Phone Number is required.', 'Required email and phone identify the field');
check($missingRegistration['password'] === 'Password is required.' && $missingRegistration['confirm_password'] === 'Confirm Password is required.', 'Required password errors are preserved');
$missingLogin = SmartServeValidation::request(['submit' => ''], '/smartserve/login.php');
check($missingLogin['username'] === 'Email Address is required.' && $missingLogin['password'] === 'Password is required.', 'Required login errors identify both fields');
$staff = ['add_user' => '', 'fname' => 'Juan', 'mname' => '', 'lname' => 'Cruz', 'uname' => 'staff@example.ph', 'contact' => '09123456789', 'address' => '123-A Street', 'pword' => 'Secure#123', 'office' => '1', 'window' => 'Window 2'];
check(SmartServeValidation::request($staff, '/smartserve/admin/account-offices.php') === [], 'Complete staff creation');
check(isset(SmartServeValidation::request(array_replace($staff, ['office' => 'abc']), '/smartserve/admin/account-offices.php')['office']), 'Invalid staff office');
check(isset(SmartServeValidation::request(array_replace($staff, ['fname' => str_repeat('a', 31)]), '/smartserve/admin/account-offices.php')['fname']), 'Staff name length');
check(isset(SmartServeValidation::request($staff, '/smartserve/student/account-offices.php')['uname']), 'Existing institutional restriction');
foreach (['0', '1'] as $status) {
    check(SmartServeValidation::request(['update_status' => '', 'status_id' => '1', 'status_val' => $status], '/smartserve/admin/office-management.php') === [], 'Archive/restore office status ' . $status);
}
foreach (['2', '-1', 'invalid', ['0']] as $status) {
    check(isset(SmartServeValidation::request(['update_status' => '', 'status_id' => '1', 'status_val' => $status], '/smartserve/admin/office-management.php')['status_val']), 'Reject invalid office status');
}
foreach (['archive', 'restore'] as $action) {
    foreach (['account-students', 'account-offices', 'account-admins'] as $page) {
        check(SmartServeValidation::request([$action => '', 'approve_hidden' => '1'], '/smartserve/admin/' . $page . '.php') === [], 'Account ' . $action . ' on ' . $page);
    }
}
check(isset(SmartServeValidation::request(['save_pass' => '', 'cpass' => 'old-password', 'npass' => 'Secure#123', 'rpass' => 'Mismatch#123'], '/smartserve/student/update-password.php')['rpass']), 'Server confirmation uses actual rpass field');
$passwordErrors = SmartServeValidation::request(['save_pass' => '', 'cpass' => 'old-password', 'npass' => 'Ab#1ñññ', 'rpass' => 'Ab#1ñññ'], '/smartserve/student/update-password.php');
check(str_contains($passwordErrors['npass'], 'characters') && !str_contains($passwordErrors['npass'], 'bytes'), 'Password requirement uses characters');
$passwordErrors = SmartServeValidation::request(['save_pass' => '', 'cpass' => str_repeat('a', 73), 'npass' => 'Secure#123', 'rpass' => 'Secure#123'], '/smartserve/student/update-password.php');
check(isset($passwordErrors['cpass']) && !isset($passwordErrors['password']), 'Current password errors identify the actual field');
check(SmartServeValidation::fieldLabel('rpass') === 'Confirm Password', 'Confirmation label is consistent');
check(SmartServeValidation::request(['save_profile' => '', 'new_fname' => 'María-José', 'new_mname' => '', 'new_lname' => 'Peña', 'new_contact' => '09123456789', 'new_address' => '12-B Manila'], '/smartserve/office/update-profile.php') === [], 'Profile optional middle name and numeric address');
check(isset(SmartServeValidation::request(['submit_walkin' => '', 'student_id' => '', 'manual_name_input' => 'Guest42', 'notes' => 'Payment'], '/smartserve/office/walk-in.php')['manual_name_input']), 'Invalid walk-in guest');
check(SmartServeValidation::request(['submit_walkin' => '', 'student_id' => '1', 'manual_name_input' => 'Existing Person Jr.', 'notes' => 'Payment'], '/smartserve/office/walk-in.php') === [], 'Existing student selection bypasses guest-name rule');
$block = ['submit_block' => '', 'block_type' => 'specific_purpose', 'block_date' => date('Y-m-d'), 'reason' => 'Unavailable', 'target_purpose' => 'Payment', 'start_time' => '', 'end_time' => ''];
check(SmartServeValidation::request($block, '/smartserve/office/block-schedules.php') === [], 'Purpose-only block does not require times');
$range = array_replace($block, ['block_type' => 'time_range', 'start_time' => '13:00', 'end_time' => '12:00']);
check(isset(SmartServeValidation::request($range, '/smartserve/office/block-schedules.php')['end_time']), 'Reversed time range');
foreach (SmartServeValidation::BLOCK_HOURS as $start) {
    foreach (SmartServeValidation::BLOCK_HOURS as $end) {
        $range = array_replace($block, ['block_type' => 'time_range', 'start_time' => $start, 'end_time' => $end]);
        check((SmartServeValidation::request($range, '/smartserve/office/block-schedules.php') === []) === ($start < $end), 'Hourly range ' . $start . ' to ' . $end);
    }
}
foreach (['06:00', '17:00', '07:30', '16:01', '07:00:00', '7:00', '', '10:00 AM', ['07:00']] as $invalidHour) {
    check(!SmartServeValidation::blockHour($invalidHour), 'Reject unsupported block hour');
    $range = array_replace($block, ['block_type' => 'time_range', 'start_time' => $invalidHour, 'end_time' => '16:00']);
    check(isset(SmartServeValidation::request($range, '/smartserve/office/block-schedules.php')['start_time']), 'Reject tampered start time');
    $range = array_replace($block, ['block_type' => 'time_range', 'start_time' => '07:00', 'end_time' => $invalidHour]);
    check(isset(SmartServeValidation::request($range, '/smartserve/office/block-schedules.php')['end_time']), 'Reject tampered end time');
}
$bookingDay = date('Y-m-d');
while (!SmartServeValidation::weekdayDate($bookingDay)) $bookingDay = date('Y-m-d', strtotime($bookingDay . ' +1 day'));
$booking = ['submit_appointment' => '', 'office_ids' => ['1', '2'], 'appointment_date' => $bookingDay, 'appointment_time' => '09:00 AM', 'purpose_option' => 'Payment', 'priority' => 'No'];
for ($offset=0; $offset<=7; $offset++) {
    $day = date('Y-m-d', strtotime("+$offset days"));
    $weekday = (int)date('N', strtotime($day)) <= 5;
    check(SmartServeValidation::bookingDate($day) === $weekday, 'Booking rejects weekend and permits weekday within range: ' . $day);
    check(SmartServeValidation::bookingDateRange($day), 'Calendar may load its range even when anchored on weekend: ' . $day);
    if (!$weekday) {
        check(isset(SmartServeValidation::request(array_replace($booking,['appointment_date'=>$day]), '/student/booking.php')['appointment_date']), 'Direct weekend booking rejected');
        check(isset(SmartServeValidation::request(['submit_block_response'=>'','blocked_row_id'=>'1','response_action'=>'reschedule','reschedule_date'=>$day,'reschedule_time'=>'09:00 AM'], '/student/booking.php')['reschedule_date']), 'Direct weekend reschedule rejected');
        check(isset(SmartServeValidation::request(['fetch_slots'=>'1','date'=>$day], '/student/booking.php')['date']), 'Weekend slot request rejected');
        check(SmartServeValidation::request(['fetch_calendar'=>'1','date'=>$day], '/student/booking.php') === [], 'Weekend calendar anchor can load weekday choices');
    }
}
check(SmartServeValidation::request($booking, '/smartserve/student/booking.php') === [], 'Complete booking');
foreach (['office_ids' => '1', 'appointment_date' => '2000-01-01', 'appointment_time' => '12:00 PM', 'priority' => 'fake'] as $field => $value) check(isset(SmartServeValidation::request(array_replace($booking, [$field => $value]), '/smartserve/student/booking.php')[$field]), 'Invalid booking ' . $field);
check(isset(SmartServeValidation::request(array_replace($booking, ['office_ids' => ['1', '1']]), '/smartserve/student/booking.php')['office_ids']), 'Duplicate office choices');
check(isset(SmartServeValidation::request(array_replace($booking, ['office_ids' => ['1'], 'purpose_option' => 'Payment']), '/smartserve/student/booking.php')['purpose_option']), 'Purpose must belong to the selected office');
check(isset(SmartServeValidation::request(array_replace($booking, ['purpose_option' => 'Others', 'custom_purpose' => '  ']), '/smartserve/student/booking.php')['custom_purpose']), 'Other purpose requires text');
check(SmartServeValidation::request(['submit_block_response' => '', 'blocked_row_id' => '1', 'response_action' => 'cancel', 'reschedule_date' => '2000-01-01', 'reschedule_time' => ''], '/smartserve/student/booking.php') === [], 'Cancellation ignores stale rescheduling values');
check(isset(SmartServeValidation::request(['start_date' => '2026-10-12', 'end_date' => '2026-10-11'], '/smartserve/admin/reports.php')['end_date']), 'Reversed report dates');
check(SmartServeValidation::image(null, true) !== null, 'Required image');
check(SmartServeValidation::image(['error' => UPLOAD_ERR_NO_FILE, 'size' => 0, 'tmp_name' => '']) === null, 'Optional image');
check(SmartServeValidation::image(['error' => UPLOAD_ERR_OK, 'size' => 3 * 1024 * 1024, 'tmp_name' => '']) !== null, 'Image size limit');
echo "Passed $checks server validation checks.\n";
