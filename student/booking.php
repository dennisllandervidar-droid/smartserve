<?php
    ob_start();
    include('../global/model.php');
    $model = new Model();
    include('main/department.php');

    // Set correct timezone to Philippine Time
    date_default_timezone_set('Asia/Manila');

    $student_id = $_SESSION['sess'] ?? null;

    if (isset($_POST['submit_block_response']) && !empty($student_id)) {
        $blocked_row_id = isset($_POST['blocked_row_id']) ? (int)$_POST['blocked_row_id'] : 0;
        $response_action = $_POST['response_action'] ?? '';
        $new_date = $_POST['reschedule_date'] ?? null;
        $new_time = $_POST['reschedule_time'] ?? null;

        $response = $model->submitBlockedAppointmentResponse($blocked_row_id, (int)$student_id, $response_action, $new_date, $new_time);
        if (!empty($response['ok'])) {
            if ($response_action === 'reschedule' && !empty($response['tracking_no'])) {
                header('Location: booking-details?id=' . rawurlencode($response['tracking_no']));
                exit;
            }
            $success_message = $response['msg'];
        } else {
            $error_message = $response['msg'] ?? 'Unable to process your response.';
        }
    }

    $student_blocked_rows = !empty($student_id) ? $model->getStudentBlockedAppointments((int)$student_id) : [];
    $faqs = $model->displayFaqs(1);
    $block_choice_cache = [];
    foreach ($student_blocked_rows as $row) {
        $bid = (int)$row['block_id'];
        if (!isset($block_choice_cache[$bid])) {
            $block_choice_cache[$bid] = $model->getBlockChoiceSummary($bid);
        }
    }

    if (isset($_POST['fetch_slots']) || isset($_POST['fetch_calendar'])) {
        ob_clean(); header('Content-Type: application/json');
        $officeIds = $_POST['office_ids'] ?? [];
        $date = $_POST['date'] ?? '';
        $validDate = isset($_POST['fetch_calendar']) ? SmartServeValidation::bookingDateRange($date) : SmartServeValidation::bookingDate($date);
        if (!is_array($officeIds) || !$officeIds || !$validDate) { echo json_encode(['status'=>'error','msg'=>'Please select an office and a weekday within the next seven days.']); exit; }
        $activeIds = array_map('intval', array_column($model->getOffices(), 'id'));
        foreach ($officeIds as $id) if (!in_array((int)$id,$activeIds,true)) { echo json_encode(['status'=>'error','msg'=>'Please select an active office.']); exit; }
        if (isset($_POST['fetch_slots'])) echo json_encode($model->scheduleAvailability($officeIds,$date));
        else {
            $days=[];
            for ($offset=0;$offset<=7;$offset++) {
                $day=date('Y-m-d',strtotime("+$offset days")); $slots=$model->scheduleAvailability($officeIds,$day);
                $available = count(array_filter($slots,fn($slot)=>$slot['available']>0))>0;
                $days[$day]=['available'=>$available,'note'=>!SmartServeValidation::weekdayDate($day)?'Weekdays only':($available?'Available times':'Blocked or fully reserved')];
            }
            echo json_encode($days);
        }
        exit;
    }

    if (isset($_POST['check_purpose_block'])) {
        ob_clean();
        header('Content-Type: application/json');

        $office_ids = $_POST['office_ids'] ?? [];
        $date = $_POST['date'] ?? '';
        $purpose = trim($_POST['purpose'] ?? '');

        if (empty($office_ids) || empty($date) || empty($purpose) || $purpose === 'Others') {
            echo json_encode(['blocked' => false]);
            exit;
        }

        foreach ($office_ids as $oid) {
            $block = $model->getPurposeBlockInfo((int)$oid, $date, $purpose);
            if (!empty($block)) {
                $message = trim(($block['reason'] ?? 'This purpose is unavailable today.'));
                if (!empty($block['note'])) {
                    $message .= ', ' . trim($block['note']);
                }

                echo json_encode([
                    'blocked' => true,
                    'office_name' => $block['office_name'] ?? 'Office',
                    'message' => $message
                ]);
                exit;
            }
        }

        echo json_encode(['blocked' => false]);
        exit;
    }

    // FORM SUBMISSION
    if (isset($_POST['submit_appointment'])) {
        $office_ids = $_POST['office_ids'] ?? [];
        $date       = $_POST['appointment_date'];
        $time       = $_POST['appointment_time'];
        $purpose_option = trim($_POST['purpose_option'] ?? '');
        $custom_purpose = trim($_POST['custom_purpose'] ?? '');
        $notes      = ($purpose_option === 'Others') ? $custom_purpose : $purpose_option;
        $priority = $model->studentPriority($student_id);
        $is_priority = ($priority === 'N/A') ? 0 : 1;
        $purpose_block_message = '';
        if (!empty($office_ids) && !empty($date) && !empty($notes) && $notes !== 'Others') {
            foreach ($office_ids as $oid) {
                $purpose_block = $model->getPurposeBlockInfo((int)$oid, $date, $notes);
                if (!empty($purpose_block)) {
                    $purpose_block_message = trim(($purpose_block['reason'] ?? 'This purpose is unavailable today.'));
                    if (!empty($purpose_block['note'])) {
                        $purpose_block_message .= ', ' . trim($purpose_block['note']);
                    }
                    break;
                }
            }
        }
        
        if(empty($office_ids)) {
            $error_message = 'Please select at least one office.';
        } elseif (empty($notes)) {
            $error_message = 'Please select a purpose of visit. If you choose Others, please type your purpose.';
        } elseif (!empty($purpose_block_message)) {
            $error_message = $purpose_block_message;
        } else {
            
            $model->autoCancelExpiredAppointments();
            if ($model->checkExistingBooking($student_id, $date, $office_ids)) {
                $error_message = "You already have an active appointment for one of the selected services on " . date('M d, Y', strtotime($date)) . ". You can book a different service, or finish or cancel the existing appointment first.";
            } else {
                $tracking_no = 'ONL-' . date('Ymd') . '-' . strtoupper(substr(uniqid(), -5));
                
                // Server-side guard: same-day slot closes 30 minutes before schedule
                $is_slot_open = true;
                if ($date === date('Y-m-d')) {
                    $slot_time_str = strtotime($time);
                    $slot_cutoff_str = strtotime('-30 minutes', $slot_time_str);
                    if (time() >= $slot_cutoff_str) {
                        $is_slot_open = false;
                    }
                }

                if ($time === '12:00 PM') {
                    $is_slot_open = false;
                }

                $block_message = '';
                if ($is_slot_open) {
                    foreach ($office_ids as $oid) {
                        $slot_block = $model->getSlotBlockInfo((int)$oid, $date, $time);
                        if (!empty($slot_block)) {
                            $is_slot_open = false;
                            $block_message = 'This schedule is blocked by ' . $slot_block['office_name'] . ': ' . $slot_block['reason'];
                            if (!empty($slot_block['note'])) {
                                $block_message .= ' (' . $slot_block['note'] . ')';
                            }
                            break;
                        }
                    }
                }

                if (!$is_slot_open) {
                    $error_message = !empty($block_message)
                        ? $block_message
                        : (($time === '12:00 PM')
                        ? 'This time slot is unavailable for Lunch break.'
                        : 'This time slot is no longer available. Booking closes 30 minutes before the selected time.');
                } else {
                    // Double check bottleneck availability
                    $min_available = 5;
                    foreach ($office_ids as $oid) {
                        $booked = $model->getBookedSlots($oid, $date, $time);
                        if ((5 - $booked) < $min_available) {
                            $min_available = (5 - $booked);
                        }
                    }

                    if ($min_available > 0) {
                    // Pass $is_priority to the model function
                        if ($model->addAppointment($tracking_no, $student_id, $office_ids, $date, $time, $notes, $is_priority, $priority)) {
                            echo "<script>window.location.href='booking-details?id=$tracking_no';</script>";
                            exit;
                        }
                        $error_message = 'Unable to save your appointment. Please check your selected offices and try again.';
                    } else {
                        $error_message = 'That time slot is fully booked for one or more selected offices.';
                    }
                }
            }
        }
    }

    $min_date = date('Y-m-d');
    $max_date = date('Y-m-d', strtotime('+7 days'));
    $reschedule_time_options = ['07:00 AM', '08:00 AM', '09:00 AM', '10:00 AM', '11:00 AM', '01:00 PM', '02:00 PM', '03:00 PM', '04:00 PM'];


?>
<!DOCTYPE html>
<html lang="en">
<head>
    <?php require_once __DIR__ . '/../global/form-assets.php'; ?>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="icon" href="../assets/img/<?php echo $web_icon; ?>.png" type="image/x-icon" />
    <title><?php echo $web_name; ?> | Request Appointment</title>
    
    <link rel="stylesheet" type="text/css" href="../dashboard/assets/css/dataTables.bootstrap4.min.css">
    <link rel="stylesheet" type="text/css" href="../dashboard/assets/css/assets.css">
    <link rel="stylesheet" type="text/css" href="../dashboard/assets/css/typography.css">
    <link rel="stylesheet" type="text/css" href="../dashboard/assets/css/style.css">
    <link rel="stylesheet" type="text/css" href="../dashboard/assets/css/dashboard.css">
    
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    
    <style>
        :root { --primary-green: <?php echo $primary_color; ?>; --soft-green: #eef7f1; }
        
        .appointment-card { background: #fff; border-radius: 20px; box-shadow: 0 10px 30px rgba(0,0,0,0.05); padding: 40px; border: 1px solid rgba(0,0,0,0.05); }
        .form-label { font-weight: 600; color: #2d3436; margin-bottom: 8px; display: block; }
        
        .custom-input { 
            border-radius: 12px; border: 2px solid #eee; padding: 12px 20px; 
            width: 100%; outline: none; transition: 0.3s; background: #fff;
            color: #495057; font-size: 1rem;
        }
        .custom-input:focus { border-color: var(--primary-green); box-shadow: 0 0 0 4px var(--soft-green); }

        .readonly-box { background: #f8f9fa; border: 2px solid #eee; border-radius: 12px; padding: 12px 20px; color: #6c757d; font-weight: 500; cursor: not-allowed; }

        /* --- Checkbox Cards for Offices --- */
        .office-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(200px, 1fr)); gap: 15px; }
        .office-card-label { cursor: pointer; margin: 0; }
        .office-card-label input[type="checkbox"] { display: none; }
        .office-card {
            border: 2px solid #eee; border-radius: 12px; padding: 15px; background: #fff;
            display: flex; align-items: center; gap: 10px; transition: 0.2s; font-weight: 500; color: #4a5568;
        }
        .office-card i { font-size: 1.2rem; color: #a0aec0; }
        .office-card-label input[type="checkbox"]:checked + .office-card {
            border-color: var(--primary-green); background: var(--soft-green); color: var(--primary-green);
        }
        .office-card-label input[type="checkbox"]:checked + .office-card i { color: var(--primary-green); }

        /* --- Time Slot Grid --- */
        .time-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(130px, 1fr)); gap: 15px; margin-top: 15px; }
        .time-slot { background: #f8f9fa; border: 2px solid #eee; border-radius: 12px; padding: 15px 10px; text-align: center; cursor: pointer; transition: 0.2s; }
        .time-slot.available:hover { border-color: var(--primary-green); background: var(--soft-green); }
        .time-slot.available.selected { background: var(--primary-green); border-color: var(--primary-green); color: white; }
        .time-slot.unavailable { background: #fdf2f2; border-color: #f8d7da; color: #dc3545; cursor: not-allowed; opacity: 0.7; }
        .time-text { font-weight: 700; display: block; }
        .slot-count { font-size: 0.8rem; font-weight: 500; }
        .time-slot.selected .slot-count { color: rgba(255,255,255,0.8); }

        /* --- Reminder Box (Light Red Theme) --- */
        .instructions-box {
            background: #fff2f2;
            border: 1px solid #f3caca;
            border-radius: 20px;
            padding: 30px;
            margin-top: 20px;
            color: #5c1f1f;
            box-shadow: 0 8px 22px rgba(157, 27, 27, 0.08);
        }

        .instructions-box h5 {
            color: #8b1d1d;
            font-weight: 700;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .route-path {
            display: flex;
            align-items: center;
            gap: 15px;
            flex-wrap: wrap;
            margin-top: 25px;
        }

        .route-step {
            display: flex;
            align-items: center;
            gap: 12px;
            background: #fff;
            padding: 10px 18px;
            border-radius: 50px;
            border: 1px solid #f2d4d4;
        }

        .route-step-num {
            background: #ef4444;
            color: #fff;
            width: 32px;
            height: 32px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 800;
            font-size: 0.9rem;
        }

        .step-final .route-step-num { background: #c23c3c; }

        .route-step-text strong {
            display: block;
            font-size: 0.95rem;
            color: #5c1f1f;
            line-height: 1.2;
        }

        .route-step-text span {
            font-size: 0.72rem;
            color: rgba(92, 31, 31, 0.62);
            text-transform: uppercase;
        }

        .route-arrow { color: rgba(92, 31, 31, 0.35); }
        
        .time-warning {
            background: #ffe5e5;
            color: #8b1d1d;
            padding: 12px 16px;
            border-radius: 12px;
            font-weight: 600;
            margin-top: 15px;
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 0.9rem;
            border: 1px solid #f2bcbc;
        }

        .purpose-helper {
            color: #6c757d;
            font-size: 0.85rem;
            margin-top: 8px;
            display: block;
        }

        .reschedule-alert-card { background: #fff7e8; border: 1px solid #ffe2b8; border-radius: 14px; padding: 18px; margin-bottom: 15px; }
        .reschedule-alert-title { color: #9a6700; font-weight: 700; margin-bottom: 6px; }
        .choice-pill { background: #fff; border: 1px solid #f5d9ab; border-radius: 999px; padding: 3px 10px; font-size: 0.75rem; margin-right: 6px; display: inline-block; }

        @media (max-width: 768px) {
            .route-arrow { display: none; }
            .route-step { width: 100%; }
        }
    </style>
<link rel="stylesheet" href="../assets/css/schedule-calendar.css?v=<?php echo filemtime(__DIR__ . '/../assets/css/schedule-calendar.css'); ?>"><script src="../assets/js/schedule-calendar.js?v=<?php echo filemtime(__DIR__ . '/../assets/js/schedule-calendar.js'); ?>" defer></script></head>
<body class="ttr-opened-sidebar ttr-pinned-sidebar" style="background-color: #FCFCFC;">
    <?php $page = 'appointments'; $secondnav = 'booking'; include 'main/admin-nav.php'; ?>

    <main class="ttr-wrapper" style="background-color: #FCFCFC;">
        <div class="container-fluid">
            <div class="db-breadcrumb">
                <h4 class="breadcrumb-title"><i class="ti ti-calendar"></i> Appointments</h4>
                <ul class="db-breadcrumb-list"><li>Request Appointment</li></ul>
            </div>
            
            <div class="row justify-content-center">
                <div class="col-lg-12 m-b30">
                    <div class="appointment-card">
                        
                        <div class="d-flex justify-content-between align-items-center mb-4 border-bottom pb-3">
                            <div>
                                <h3 class="fw-bold m-0" style="color: var(--primary-green);">Book an Appointment</h3>
                                <p class="text-muted m-0">Select one or more offices, then pick your preferred schedule.</p>
                            </div>
                        </div>

                        <?php if(!empty($error_message)): ?>
                        <script>
                            document.addEventListener("DOMContentLoaded", function() {
                                Swal.fire({
                                    title: 'Booking Notice',
                                    text: '<?php echo addslashes($error_message); ?>',
                                    icon: 'info',
                                    confirmButtonColor: 'var(--primary-green)',
                                    confirmButtonText: 'Close'
                                });
                            });
                        </script>
                        <?php endif; ?>

                        <?php if(!empty($success_message)): ?>
                        <script>
                            document.addEventListener("DOMContentLoaded", function() {
                                Swal.fire({
                                    title: 'Update Saved',
                                    text: '<?php echo addslashes($success_message); ?>',
                                    icon: 'success',
                                    confirmButtonColor: 'var(--primary-green)',
                                    confirmButtonText: 'Close'
                                });
                            });
                        </script>
                        <?php endif; ?>

                        <?php if (!empty($student_blocked_rows)): ?>
                        <div class="mb-4">
                            <h5 class="mb-3" style="font-weight: 700; color: #9a6700;"><i class="ti-alert"></i> Appointment Updates Requiring Your Action</h5>
                            <?php foreach ($student_blocked_rows as $brow): ?>
                                <?php $sum = $block_choice_cache[(int)$brow['block_id']] ?? ['cancelled' => 0, 'choices' => []]; ?>
                                <div class="reschedule-alert-card" id="blocked-appointment-<?php echo (int)$brow['id']; ?>">
                                    <div class="reschedule-alert-title">
                                        <?php echo htmlspecialchars($brow['office_name']); ?> blocked your original schedule (<?php echo htmlspecialchars($brow['original_time']); ?> on <?php echo date('M d, Y', strtotime($brow['original_date'])); ?>)
                                    </div>
                                    <p class="mb-2" style="font-size:0.9rem; color:#7a5a20;">
                                        Reason: <strong><?php echo htmlspecialchars($brow['reason']); ?></strong>
                                        <?php if (!empty($brow['note'])): ?>
                                            | Note: <?php echo htmlspecialchars($brow['note']); ?>
                                        <?php endif; ?>
                                    </p>
                                    <p class="mb-2" style="font-size:0.9rem; color:#7a5a20;">Your response applies only to this service. Rescheduling creates a separate ticket and QR for its new schedule. Your other services keep their current schedule and progress.</p>
                                    <div class="mb-2">
                                        <span class="choice-pill">Cancelled: <?php echo (int)$sum['cancelled']; ?></span>
                                        <?php foreach ($sum['choices'] as $opt): ?>
                                            <span class="choice-pill"><?php echo date('M d', strtotime($opt['reschedule_date_choice'])); ?> <?php echo htmlspecialchars($opt['reschedule_time_choice']); ?> (<?php echo (int)$opt['total']; ?>)</span>
                                        <?php endforeach; ?>
                                    </div>

                                    <form method="POST" class="block-response-form mt-3">
                                        <input type="hidden" name="blocked_row_id" value="<?php echo (int)$brow['id']; ?>">
                                        <div class="row">
                                            <div class="col-md-4">
                                                <label class="form-label">Your Action</label>
                                                <select class="custom-input block-action-select" name="response_action" required>
                                                    <option value="reschedule">Reschedule This Service</option>
                                                    <option value="cancel">Cancel This Service</option>
                                                </select>
                                            </div>
                                            <div class="col-md-4 reschedule-input-wrap">
                                                <label class="form-label">New Date</label>
                                                <input type="date" name="reschedule_date" class="custom-input" min="<?php echo $min_date; ?>" max="<?php echo $max_date; ?>" required>
                                            </div>
                                            <div class="col-md-4 reschedule-input-wrap">
                                                <label class="form-label">New Time</label>
                                                <select name="reschedule_time" class="custom-input" required>
                                                    <option value="">Select time</option>
                                                    <?php foreach ($reschedule_time_options as $rtime): ?>
                                                        <option value="<?php echo $rtime; ?>"><?php echo $rtime; ?></option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </div>
                                        </div>
                                        <button type="submit" name="submit_block_response" class="btn green radius-xl mt-3" style="background-color: var(--primary-green);">Submit Response</button>
                                    </form>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        <?php endif; ?>

                        <form method="POST" id="appointmentForm">
                            
                            <div class="form-group m-b25">
                                <label class="form-label">1. Select Offices / Departments</label>
                                <div class="office-grid">
                                    <?php
                                        $offices = $model->getOffices();
                                        foreach ($offices as $off) {
                                            echo '
                                            <label class="office-card-label">
                                                <input type="checkbox" name="office_ids[]" class="office-checkbox" value="'.$off['id'].'">
                                                <div class="office-card">
                                                    <i class="ti-briefcase"></i>
                                                    <div>
                                                        <span class="d-block lh-1">'.htmlspecialchars($off['office_name']).'</span>
                                                    </div>
                                                </div>
                                            </label>';
                                        }
                                    ?>
                                </div>
                            </div>

                            <div class="form-group m-b25">
                                <label class="form-label">2. Priority Type</label>
                                <?php $studentAccount = $model->displayStudentAccountDetails($student_id); $accountPriority = $model->studentPriority($student_id); ?>
                                <p><span class="badge badge-success"><?php echo htmlspecialchars($accountPriority); ?></span></p>
                                <small class="text-muted">Priority comes from your student profile. <?php if (($studentAccount['priority_type'] ?? '') === 'PWD' && ($studentAccount['pwd_status'] ?? '') === 'pending'): ?>Your PWD ID is awaiting admin approval; this booking will use regular priority.<?php endif; ?></small>
                                <small class="text-muted d-block" style="font-weight: 600;">
                                    <i class="ti-info-alt"></i> PWD priority is applied automatically after admin approves the ID in your profile.
                                </small>
                            </div>
                            
                            <div class="form-group m-b25">
                                <label class="form-label">3. Select Date</label>
                                <?php $calendarTarget='appointment_date'; $calendarMode='booking'; require '../global/calendar-fields.php'; ?>
                                <input type="date" name="appointment_date" id="appointment_date" class="custom-input" min="<?php echo $min_date; ?>" max="<?php echo $max_date; ?>" required>
                            </div>

                            <div class="form-group m-b25" id="time-container" style="display: none;">
                                <label class="form-label">4. Select Time Slot</label>
                                <input type="hidden" name="appointment_time" id="selected_time" required>
                                <div class="time-grid" id="slots-wrapper"></div>
                            </div>

                            <div class="form-group m-b30">
                                <label class="form-label">5. Purpose of Visit</label>
                                <select name="purpose_option" id="purpose_option" class="custom-input" required>
                                    <option value="">Select a common service</option>
                                    <option value="Others">Others</option>
                                </select>
                                <small class="purpose-helper">Choose a common service. Select Others if your purpose is not listed.</small>

                                <div id="purpose-block-alert" style="display:none; margin-top: 12px;" class="time-warning"></div>

                                <div id="custom-purpose-wrap" style="display: none; margin-top: 12px;">
                                    <textarea name="custom_purpose" id="custom_purpose" class="custom-input" rows="3" placeholder="Type your specific purpose here..."></textarea>
                                </div>
                                
                                <div class="instructions-box" id="validation-instructions">
                                    <h5><i class="ti-info-alt"></i> Important Reminder for ID Validation</h5>
                                    <p class="m-0" style="font-size: 0.95rem; color: #5c1f1f;">If you are requesting <strong>ID Validation at the ITC</strong>, you must secure the required documents by visiting the offices in this exact order:</p>

                                    <div class="route-path">
                                        <div class="route-step">
                                            <div class="route-step-num">1</div>
                                            <div class="route-step-text"><strong>Cashier</strong><span>Enrollment Receipt</span></div>
                                        </div>
                                        <i class="ti-angle-right route-arrow"></i>
                                        <div class="route-step">
                                            <div class="route-step-num">2</div>
                                            <div class="route-step-text"><strong>Clinic</strong><span>Medical Checkup</span></div>
                                        </div>
                                        <i class="ti-angle-right route-arrow"></i>
                                        <div class="route-step step-final">
                                            <div class="route-step-num">3</div>
                                            <div class="route-step-text"><strong>ITC</strong><span>Final ID Validation</span></div>
                                        </div>
                                    </div>

                                    <div class="time-warning">
                                        <i class="ti-time"></i> Queue transactions are strictly processed until 5:00 PM only.
                                    </div>
                                </div>
                            </div>

                            <div class="form-group border-top pt-4 text-right d-flex justify-content-between align-items-center">
                      
                                <button type="submit" name="submit_appointment" id="submit_btn" class="btn green radius-xl" style="background-color: var(--primary-green); padding: 12px 30px; font-size: 1.1rem; font-weight: 600;">
                                    Confirm Request
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <div class="ttr-overlay"></div>
    <?php include 'main/faqs-widget.php'; ?>

    <script src="../dashboard/assets/js/jquery.min.js"></script>
    <script src="../dashboard/assets/vendors/bootstrap/js/bootstrap.min.js"></script>
    <script src="../dashboard/assets/js/admin.js"></script>
    <script>
        $(document).ready(function() {
            const officeCheckboxes = $('.office-checkbox');
            const dateSelect = $('#appointment_date');
            const timeContainer = $('#time-container');
            const slotsWrapper = $('#slots-wrapper');
            const selectedTimeInput = $('#selected_time');
            const submitBtn = $('#submit_btn');
            const purposeSelect = $('#purpose_option');
            const customPurposeWrap = $('#custom-purpose-wrap');
            const customPurposeInput = $('#custom_purpose');
            const purposeBlockAlert = $('#purpose-block-alert');
            let slotsRequestId = 0;
            let purposeRequestId = 0;
            const officePurposeMap = {
                '1': [
                    'Medical Checkup',
                    'Dental',
                    'Injury/Wound Care'
                ],
                '2': [
                    'Payment'
                ],
                '3': [
                    'Valid ID',
                    'Teams/Tech Concern'
                ]
            };

            function getSelectedOffices() {
                let selected = [];
                $('.office-checkbox:checked').each(function() {
                    selected.push($(this).val());
                });
                return selected;
            }

            function updatePurposeOptions(selectedOfficeIds) {
                const previousValue = purposeSelect.val();
                const options = [];

                selectedOfficeIds.forEach(function(officeId) {
                    if (officePurposeMap[officeId]) {
                        officePurposeMap[officeId].forEach(function(service) {
                            if (!options.includes(service)) {
                                options.push(service);
                            }
                        });
                    }
                });

                purposeSelect.empty();
                purposeSelect.append('<option value="">Select a common service</option>');

                options.forEach(function(service) {
                    purposeSelect.append('<option value="' + service + '">' + service + '</option>');
                });

                purposeSelect.append('<option value="Others">Others</option>');

                if (options.includes(previousValue) || previousValue === 'Others') {
                    purposeSelect.val(previousValue);
                } else {
                    purposeSelect.val('');
                }

                toggleCustomPurposeField();
                checkPurposeBlock();
            }

            function refreshSubmitState() {
                const hasTime = !!selectedTimeInput.val();
                const hasPurposeBlock = purposeBlockAlert.is(':visible');
                submitBtn.prop('disabled', false);
                document.getElementById('appointmentForm').dataset.purposeBlocked = String(hasPurposeBlock);
            }

            function showPurposeBlock(message) {
                purposeBlockAlert.html('<i class="ti-na"></i> ' + message).show();
                refreshSubmitState();
            }

            function clearPurposeBlock() {
                purposeBlockAlert.hide().empty();
                refreshSubmitState();
            }

            function checkPurposeBlock() {
                const requestId = ++purposeRequestId;
                const selected = getSelectedOffices();
                const date = dateSelect.val();
                const purpose = purposeSelect.val();

                if (selected.length === 0 || !hasValidBookingDate() || !purpose || purpose === 'Others') {
                    clearPurposeBlock();
                    return;
                }

                $.ajax({
                    url: window.location.href,
                    type: 'POST',
                    dataType: 'json',
                    data: {
                        check_purpose_block: true,
                        office_ids: selected,
                        date: date,
                        purpose: purpose
                    },
                    success: function(response) {
                        if (requestId !== purposeRequestId) return;
                        if (response && response.blocked) {
                            showPurposeBlock(response.message || 'This purpose is unavailable today.');
                        } else {
                            clearPurposeBlock();
                        }
                    },
                    error: function() {
                        if (requestId !== purposeRequestId) return;
                        clearPurposeBlock();
                    }
                });
            }

            // Enable Date if at least one office is checked
            officeCheckboxes.on('change', function() {
                let selected = getSelectedOffices();
                if(selected.length > 0) {
                    dateSelect.prop('disabled', false);
                    updatePurposeOptions(selected);
                    fetchSlots(selected);
                } else {
                    dateSelect.prop('disabled', false);
                    dateSelect.val('');
                    fetchSlots([]);
                    timeContainer.slideUp();
                    slotsWrapper.empty();
                    selectedTimeInput.val('');
                    submitBtn.prop('disabled', false);
                    updatePurposeOptions([]);
                    clearPurposeBlock();
                }
            });

            function hasValidBookingDate() {
                const input = dateSelect[0];
                const date = input.value;
                return SmartServe.rules.weekdayDate(date) && date >= input.min && date <= input.max;
            }

            // Native date controls emit changes while a manually typed year is incomplete.
            // Clear the old time immediately, but only request availability for a usable date.
            dateSelect.on('input', function() {
                ++slotsRequestId;
                ++purposeRequestId;
                selectedTimeInput.val('');
                slotsWrapper.empty();
                timeContainer.hide();
                document.getElementById('appointmentForm').dataset.slotsLoading = 'false';
                clearPurposeBlock();
            });

            dateSelect.on('change', function() {
                fetchSlots(getSelectedOffices());
                checkPurposeBlock();
            });

            function fetchSlots(selectedOffices) {
                const requestId = ++slotsRequestId;
                const date = dateSelect.val();
                selectedTimeInput.val('');
                if (!hasValidBookingDate() || selectedOffices.length === 0) {
                    timeContainer.hide();
                    slotsWrapper.empty();
                    document.getElementById('appointmentForm').dataset.slotsLoading = 'false';
                    return;
                }

                timeContainer.slideDown();
                selectedTimeInput.val('');
                submitBtn.prop('disabled', false);
                document.getElementById('appointmentForm').dataset.slotsLoading = 'true';
                slotsWrapper.html(`<div class="w-100 text-center py-3" style="grid-column: 1/-1;"><i class="ti-reload fa-spin" style="color: var(--primary-green);"></i> Checking slots for selected offices...</div>`);

                $.ajax({
                    url: window.location.href, 
                    type: 'POST',
                    data: { fetch_slots: true, office_ids: selectedOffices, date: date },
                    dataType: 'json',
                    success: function(response) {
                        if (requestId !== slotsRequestId) return;
                        document.getElementById('appointmentForm').dataset.slotsLoading = 'false';
                        slotsWrapper.empty();
                        if (response.status === 'error') {
                            SmartServe.notify(response.msg, 'danger');
                            return;
                        }

                        function escapeHtml(str) {
                            return String(str)
                                .replace(/&/g, '&amp;')
                                .replace(/</g, '&lt;')
                                .replace(/>/g, '&gt;')
                                .replace(/"/g, '&quot;')
                                .replace(/'/g, '&#39;');
                        }

                        $.each(response, function(time, slotData) {
                            const isLunchBreak = time === '12:00 PM';
                            const available = typeof slotData === 'object' ? Number(slotData.available || 0) : Number(slotData || 0);
                            const label = typeof slotData === 'object' ? (slotData.label || 'Unavailable') : (available > 0 ? `${available} slot(s) left` : 'Unavailable');
                            const note = typeof slotData === 'object' ? (slotData.note || '') : '';
                            const safeNote = escapeHtml(note);
                            const safeLabel = escapeHtml(label);

                            let html = available > 0 && !isLunchBreak
                                ? `<button type="button" class="time-slot available" data-time="${time}"><span class="time-text">${time}</span><span class="slot-count">${safeLabel}</span></button>`
                                : `<button type="button" class="time-slot unavailable" disabled title="${safeNote}"><span class="time-text">${time}</span><span class="slot-count">${isLunchBreak ? 'Lunch break' : safeLabel}</span>${safeNote ? `<small class="d-block mt-1" style="font-size:0.72rem;">${safeNote}</small>` : ''}</button>`;
                            slotsWrapper.append(html);
                        });
                    },
                    error: function() {
                        if (requestId !== slotsRequestId) return;
                        document.getElementById('appointmentForm').dataset.slotsLoading = 'true';
                        slotsWrapper.text('Unable to load available times. Please select the date again.');
                    }
                });
            }

            $(document).on('click', '.time-slot.available', function() {
                $('.time-slot').removeClass('selected');
                $(this).addClass('selected');
                selectedTimeInput.val($(this).data('time'));
                selectedTimeInput[0].dispatchEvent(new Event('change', {bubbles:true}));
                refreshSubmitState();
            });

            function toggleCustomPurposeField() {
                if (purposeSelect.val() === 'Others') {
                    customPurposeWrap.slideDown(120);
                    customPurposeInput.prop('required', true);
                } else {
                    customPurposeWrap.slideUp(120);
                    customPurposeInput.prop('required', false).val('');
                }
                checkPurposeBlock();
            }

            purposeSelect.on('change', toggleCustomPurposeField);
            updatePurposeOptions(getSelectedOffices());
            toggleCustomPurposeField();

            $('.block-response-form').each(function() {
                const form = $(this);
                const actionSelect = form.find('.block-action-select');
                const wraps = form.find('.reschedule-input-wrap');

                function toggleRescheduleFields() {
                    const isCancel = actionSelect.val() === 'cancel';
                    wraps.toggle(!isCancel);
                    wraps.find('input, select').prop('required', !isCancel);
                }

                actionSelect.on('change', toggleRescheduleFields);
                toggleRescheduleFields();
            });
        });
    </script>
</body>
</html>
