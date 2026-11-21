<?php
ob_start();
include('../global/model.php');
$model = new Model();
include('main/department.php');

date_default_timezone_set('Asia/Manila');

require_once __DIR__ . '/../global/sms.php';



$account_id = $_SESSION['sess'];
$staff_details = $model->displayOfficeAccountDetails2($account_id);
$staff_office_id = null;
$staff_office_name = 'Unknown Office';

if (!empty($staff_details)) {
    foreach ($staff_details as $dep) {
        $staff_office_id = !empty($dep['course']) ? (int) $dep['course'] : null;
        $staff_office_name = !empty($dep['office_name']) ? $dep['office_name'] : $staff_office_name;
        break;
    }
}

$purpose_options_map = [
    1 => ['Medical Checkup', 'Dental', 'Injury/Wound Care'],
    2 => ['Payment'],
    3 => ['Valid ID', 'Teams/Tech Concern']
];
$staff_purpose_options = $purpose_options_map[$staff_office_id] ?? [];
if (isset($_POST['fetch_block_calendar'])) {
    ob_clean(); header('Content-Type: application/json');
    $start = $_POST['calendar_start'] ?? '';
    $end = $_POST['calendar_end'] ?? '';
    if (!$staff_office_id || !SmartServeValidation::date($start) || !SmartServeValidation::date($end) || $end < $start || (strtotime($end)-strtotime($start)) > 31*86400) { echo json_encode(['status'=>'error','msg'=>'Please select a valid calendar month.']); exit; }
    $days=[];
    for ($day=$start; $day<=$end; $day=date('Y-m-d',strtotime($day.' +1 day'))) {
        $slots=$model->scheduleAvailability([$staff_office_id],$day);
        $fullyBlocked=count(array_filter($slots,fn($slot)=>$slot['label']==='Blocked'))===count($slots);
        $days[$day]=['available'=>!$fullyBlocked,'note'=>$fullyBlocked?'Already blocked for the whole day':'Select a date to view its schedule','slots'=>$slots];
    }
    echo json_encode($days); exit;
}

if (isset($_POST['submit_block']) && !empty($staff_office_id)) {
    $block_type = $_POST['block_type'] ?? 'full_day';
    $block_date = $_POST['block_date'] ?? '';
    $start_time = $_POST['start_time'] ?? null;
    $end_time = $_POST['end_time'] ?? null;
    $reason = trim($_POST['reason'] ?? 'Unavailable');
    $note = trim($_POST['note'] ?? '');
    $target_purpose = trim($_POST['target_purpose'] ?? '');

    if ($block_type === 'full_day' || $block_type === 'specific_purpose') {
        $start_time = null;
        $end_time = null;
    }

    if (empty($block_date) || empty($reason)) {
        $error_message = 'Please fill in the required fields.';
    } elseif ($block_type === 'time_range' && !SmartServeValidation::blockTimeRange($start_time, $end_time)) {
        $error_message = 'Choose hourly times from 7 AM to 4 PM. End time must be later than start time.';
    } elseif ($block_type === 'specific_purpose' && empty($target_purpose)) {
        $error_message = 'Please select a purpose to block.';
    } else {
        $block_id = $model->createBlockedSchedule($staff_office_id, $block_date, $start_time, $end_time, $block_type, $reason, $note, $account_id, $target_purpose);

        if ($block_id > 0) {
            $affected = $model->getPendingAppointmentsForBlock($staff_office_id, $block_date, $start_time, $end_time, $block_type, $target_purpose);
            $sent_numbers = [];
            $sms_count = 0;

            foreach ($affected as $row) {
                if (!empty($row['student_id'])) {
                    $model->addBlockedScheduleAppointment(
                        $block_id,
                        $row['appointment_id'],
                        $row['tracking_no'],
                        $row['student_id'],
                        $staff_office_id,
                        $row['appointment_date'],
                        $row['appointment_time'],
                        'notified'
                    );

                    $formatted_number = normalizePhilippineMobileNumber($row['contact'] ?? '');
                    if ($formatted_number !== '' && !isset($sent_numbers[$formatted_number])) {
                        $sms_message = "SMARTSERVE NOTICE\n\nYour appointment in {$staff_office_name} on "
                            . date('M d, Y', strtotime($row['appointment_date'])) . " at {$row['appointment_time']} "
                            . "was affected by: {$reason}.\n"
                            . (!empty($note) ? "Note: {$note}\n" : '')
                            . "Please log in to reschedule or cancel this service only. Other services keep their original schedules.";

                        if (sendTextbeeSMS($formatted_number, $sms_message)) {
                            $sent_numbers[$formatted_number] = true;
                            $sms_count++;
                        }
                        sleep(2);
                    }
                }
            }

            if ($sms_count > 0) $model->markBlockScheduleSmsSent($block_id);
            $success_message = 'Blocked schedule saved. Affected students tagged: ' . count($affected) . '. SMS accepted by gateway: ' . $sms_count . '.';
        } else {
            $error_message = 'Failed to save blocked schedule.';
        }
    }
}

$blocked_rows = !empty($staff_office_id) ? $model->getBlockedSchedulesByOffice($staff_office_id) : [];
$choice_summary_map = [];
foreach ($blocked_rows as $brow) {
    $choice_summary_map[$brow['id']] = $model->getBlockChoiceSummary($brow['id']);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <?php require_once __DIR__ . '/../global/form-assets.php'; ?>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="icon" href="../assets/img/<?php echo $web_icon; ?>.png" type="image/x-icon" />
    <title><?php echo $web_name; ?> | Block Schedules</title>

    <link rel="stylesheet" type="text/css" href="../dashboard/assets/css/assets.css">
    <link rel="stylesheet" type="text/css" href="../dashboard/assets/css/typography.css">
    <link rel="stylesheet" type="text/css" href="../dashboard/assets/css/style.css">
    <link rel="stylesheet" type="text/css" href="../dashboard/assets/css/dashboard.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <style>
        :root { --primary-green: <?php echo $primary_color; ?>; --soft-green: #eef7f1; }
        .card-box { background: #fff; border-radius: 16px; border: 1px solid #eef1f4; box-shadow: 0 6px 24px rgba(0,0,0,0.04); }
        .form-title { font-weight: 700; color: #1f2937; }
        .summary-pill { border-radius: 999px; padding: 5px 12px; font-size: 0.75rem; font-weight: 700; display: inline-block; margin-right: 6px; }
        .pill-waiting { background: #fff4d9; color: #9a6700; }
        .pill-resched { background: #e6f2ff; color: #0b5ed7; }
        .pill-cancel { background: #ffe3e3; color: #c92a2a; }
        .slot-count-list { margin: 0; padding-left: 18px; }
    </style>
<link rel="stylesheet" href="../assets/css/schedule-calendar.css"><script src="../assets/js/schedule-calendar.js" defer></script></head>
<body class="ttr-opened-sidebar ttr-pinned-sidebar" style="background:#FCFCFC;">
<?php
$page = 'appointments';
$secondnav = 'blocked-schedules';
include 'main/admin-nav.php';
?>

<main class="ttr-wrapper" style="background:#FCFCFC;">
    <div class="container-fluid">
        <div class="db-breadcrumb d-flex justify-content-between align-items-center">
            <div>
                <h4 class="breadcrumb-title"><i class="ti ti-na"></i> Blocked Schedules</h4>
                <ul class="db-breadcrumb-list">
                    <li>Office: <strong style="color: var(--primary-green)"><?php echo htmlspecialchars($staff_office_name); ?></strong></li>
                </ul>
            </div>
            <a href="queue" class="btn btn-secondary radius-xl"><i class="ti-arrow-left"></i> Back to Queue</a>
        </div>

        <?php if (!empty($error_message)): ?>
            <script>
                document.addEventListener('DOMContentLoaded', function () {
                    Swal.fire({ icon: 'error', title: 'Unable to Save', text: '<?php echo addslashes($error_message); ?>' });
                });
            </script>
        <?php endif; ?>

        <?php if (!empty($success_message)): ?>
            <script>
                document.addEventListener('DOMContentLoaded', function () {
                    Swal.fire({ icon: 'success', title: 'Saved', text: '<?php echo addslashes($success_message); ?>' });
                });
            </script>
        <?php endif; ?>

        <div class="row">
            <div class="col-lg-5 m-b30">
                <div class="card-box p-4">
                    <h5 class="form-title m-b20">Add Blocked Schedule</h5>
                    <form method="POST">
                        <div class="form-group">
                            <label>Block Type</label>
                            <select name="block_type" id="block_type" class="form-control" required>
                                <option value="full_day">Full Day</option>
                                <option value="time_range">Time Range</option>
                                <option value="specific_purpose">Specific Purpose</option>
                            </select>
                        </div>
                        <div class="form-group" id="purpose_wrap" style="display:none;">
                            <label>Specific Purpose</label>
                            <select name="target_purpose" id="target_purpose" class="form-control">
                                <option value="">Select purpose</option>
                                <?php foreach ($staff_purpose_options as $purpose_option): ?>
                                    <option value="<?php echo htmlspecialchars($purpose_option); ?>"><?php echo htmlspecialchars($purpose_option); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Date</label>
                            <?php $calendarTarget='block_date'; $calendarMode='block'; require '../global/calendar-fields.php'; ?>
                            <input type="date" name="block_date" id="block_date" class="form-control" min="<?php echo date('Y-m-d'); ?>" required>
                        </div>
                        <div class="row" id="time_range_wrap" style="display:none;">
                            <?php require __DIR__ . '/main/block-time-fields.php'; ?>
                        </div>
                        <div class="form-group">
                            <label>Reason</label>
                            <input type="text" name="reason" class="form-control" placeholder="Doctor unavailable" required>
                        </div>
                        <div class="form-group">
                            <label>Note (Shown to students)</label>
                            <textarea name="note" class="form-control" rows="3" placeholder="Please select another date/time."></textarea>
                        </div>
                        <button type="submit" name="submit_block" class="btn green radius-xl" style="background: var(--primary-green);">Save Block & Notify</button>
                    </form>
                </div>
            </div>

            <div class="col-lg-7 m-b30">
                <div class="card-box p-4">
                    <h5 class="form-title m-b20">Recent Blocks</h5>
                    <?php if (empty($blocked_rows)): ?>
                        <p class="text-muted m-0">No blocked schedules yet.</p>
                    <?php else: ?>
                        <?php foreach ($blocked_rows as $row): ?>
                            <?php $sum = $choice_summary_map[$row['id']]; ?>
                            <div class="border rounded p-3 m-b15" style="border-color:#eef1f4 !important;">
                                <div class="d-flex justify-content-between align-items-start">
                                    <div>
                                        <h6 class="m-0"><?php echo htmlspecialchars($row['reason']); ?></h6>
                                        <small class="text-muted">
                                            <?php echo date('M d, Y', strtotime($row['block_date'])); ?>
                                            <?php if (!empty($row['start_time']) && !empty($row['end_time'])): ?>
                                                • <?php echo date('h:i A', strtotime($row['start_time'])); ?> - <?php echo date('h:i A', strtotime($row['end_time'])); ?>
                                            <?php else: ?>
                                                • Whole day
                                            <?php endif; ?>
                                        </small>
                                        <?php if (!empty($row['note'])): ?>
                                            <p class="m-0 mt-2" style="font-size:0.9rem;"><?php echo htmlspecialchars($row['note']); ?></p>
                                        <?php endif; ?>
                                    </div>
                                    <div>
                                        <span class="summary-pill pill-waiting">Waiting: <?php echo (int)$row['waiting_count']; ?></span>
                                        <span class="summary-pill pill-resched">Rescheduled: <?php echo (int)$row['rescheduled_count']; ?></span>
                                        <span class="summary-pill pill-cancel">Cancelled: <?php echo (int)$row['cancelled_count']; ?></span>
                                    </div>
                                </div>

                                <?php if (!empty($sum['choices'])): ?>
                                    <div class="mt-2">
                                        <small class="text-muted d-block">Preferred reschedule options chosen by students:</small>
                                        <ul class="slot-count-list">
                                            <?php foreach ($sum['choices'] as $opt): ?>
                                                <li><?php echo date('M d, Y', strtotime($opt['reschedule_date_choice'])); ?> at <?php echo htmlspecialchars($opt['reschedule_time_choice']); ?> - <?php echo (int)$opt['total']; ?> student(s)</li>
                                            <?php endforeach; ?>
                                        </ul>
                                    </div>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</main>

<script src="../dashboard/assets/js/jquery.min.js"></script>
<script src="../dashboard/assets/vendors/bootstrap/js/bootstrap.min.js"></script>
<script src="../dashboard/assets/js/admin.js"></script>
<script src="../assets/js/block-schedules.js"></script>
</body>
</html>
