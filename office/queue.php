<?php
    ob_start();
    include('../global/model.php');
    $model = new Model();

    include('main/department.php');
    $account_id = $_SESSION['sess']; 

    // --- FETCH STAFF IDENTITY, OFFICE, AND WINDOW ---
    $staff_details = $model->displayOfficeAccountDetails2($account_id);
    $staff_window = 'Unknown Window';
    $staff_office_id = null;
    $display_office_name = "Unknown Office";

    if (!empty($staff_details)) {
        foreach ($staff_details as $dep) {
            $staff_office_id = $dep['course'] ?? null; 
            $staff_window = $dep['year'] ?? 'Unknown Window'; 
            break;
        }
    }

    // --- AUTO-CANCEL EXPIRED ONLINE APPOINTMENTS ---
    // Cancel online bookings with no check-in after the 10-minute arrival grace period.
    if ($staff_office_id) {
        $model->autoCancelExpiredAppointments($staff_office_id);
    }

    // --- AJAX HANDLERS ---
    if (isset($_POST['action'])) {
        ob_clean();
        header('Content-Type: application/json');
        
        $action = $_POST['action'];

        if ($action === 'refresh_queue') {
            $queue = $model->getTodayQueue($staff_office_id);
            echo json_encode(['status' => 'success', 'version' => hash('sha256', json_encode($queue))]);
            exit;
        }
        
        if ($action == 'scan_qr') {
            $tracking_no = trim($_POST['tracking_no']);
            $result = $model->verifyAndQueueQR($tracking_no, $staff_office_id);
            echo json_encode($result);
            exit;
        }
        
        if ($action == 'start_serving') {
            $ao_id = $_POST['ao_id'];
            // Validator removed: Multiple students can now be served at the same window simultaneously
            $success = $model->updateQueueStatus($ao_id, 2, "", $staff_window);
            echo json_encode(['status' => $success ? 'success' : 'error', 'msg' => '']);
            exit;
        }

        if ($action == 'complete_serving') {
            $ao_id = $_POST['ao_id'];
            $notes = trim($_POST['office_notes']);
            $success = $model->updateQueueStatus($ao_id, 3, $notes, NULL);
            echo json_encode(['status' => $success ? 'success' : 'error']);
            exit;
        }

        if ($action == 'no_show') {
            $ao_id = $_POST['ao_id'];
            // Marking as No Show (Status 4)
            $success = $model->updateQueueStatus($ao_id, 4, "Staff marked as No Show.", NULL);
            echo json_encode(['status' => $success ? 'success' : 'error']);
            exit;
        }
    }

    // Fetch Today's Data
    $today_queue = $model->getTodayQueue($staff_office_id);
    $queue_version = hash('sha256', json_encode($today_queue));
    
    $all_count = is_array($today_queue) ? count($today_queue) : 0;
    $waiting = 0; $serving = 0; $completed = 0;
    
    if($all_count > 0) {
        foreach ($today_queue as $q) {
            // Group both Expected Arrival (0) and Waiting (1) into the Waiting count
            if ($q['status'] == 0 || $q['status'] == 1) $waiting++;
            if ($q['status'] == 2) $serving++;
            if ($q['status'] == 3) $completed++;
        }
    }

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <?php require_once __DIR__ . '/../global/form-assets.php'; ?>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="icon" href="../assets/img/<?php echo $web_icon; ?>.png" type="image/x-icon" />
    <title><?php echo $web_name; ?> | Live Queue Dashboard</title>

    <link rel="stylesheet" type="text/css" href="../dashboard/assets/css/assets.css">
    <link rel="stylesheet" type="text/css" href="../dashboard/assets/css/typography.css">
    <link rel="stylesheet" type="text/css" href="../dashboard/assets/css/style.css">
    <link rel="stylesheet" type="text/css" href="../dashboard/assets/css/dashboard.css">
    
    <script src="https://unpkg.com/html5-qrcode"></script>

    <style>
        :root { --primary-green: <?php echo $primary_color; ?>; --soft-green: #eef7f1; }
        .stat-card { background: #fff; border-radius: 15px; padding: 20px; border: 2px solid transparent; display: flex; align-items: center; gap: 15px; box-shadow: 0 4px 15px rgba(0,0,0,0.02); cursor: pointer; transition: all 0.3s ease; }
        .stat-card:hover { transform: translateY(-3px); box-shadow: 0 8px 20px rgba(0,0,0,0.06); border-color: #eee; }
        .stat-card.active-filter { border-color: var(--primary-green); background: var(--soft-green); }
        .stat-card.active-filter .stat-icon { background: var(--primary-green) !important; color: #fff !important; }
        .stat-icon { width: 50px; height: 50px; border-radius: 12px; display: flex; align-items: center; justify-content: center; font-size: 24px; transition: 0.3s; }
        .stat-info h3 { margin: 0; font-weight: 700; font-size: 1.5rem; }
        .stat-info p { margin: 0; color: #6c757d; font-size: 0.85rem; text-transform: uppercase; font-weight: 600; letter-spacing: 0.5px; }
        .queue-table-wrapper { background: #fff; border-radius: 20px; padding: 25px; box-shadow: 0 10px 30px rgba(0,0,0,0.03); border: 1px solid rgba(0,0,0,0.05); margin-top: 20px; }
        .table thead th { border-bottom: 2px solid var(--soft-green); color: #6c757d; text-transform: uppercase; font-size: 0.8rem; letter-spacing: 0.5px; padding-bottom: 15px; border-top: none; }
        .table tbody td { vertical-align: middle; padding: 15px 10px; border-bottom: 1px solid #f8f9fa; }
        .badge-soft-warning { background: #fff3cd; color: #856404; padding: 6px 12px; border-radius: 20px; font-weight: 600; font-size: 0.8rem; }
        .badge-soft-success { background: #d4edda; color: #155724; padding: 6px 12px; border-radius: 20px; font-weight: 600; font-size: 0.8rem; }
        .badge-soft-primary { background: #cce5ff; color: #004085; padding: 6px 12px; border-radius: 20px; font-weight: 600; font-size: 0.8rem; }
        .badge-soft-danger { background: #f8d7da; color: #721c24; padding: 6px 12px; border-radius: 20px; font-weight: 600; font-size: 0.8rem; }
        .badge-soft-dark { background: #e2e3e5; color: #383d41; padding: 6px 12px; border-radius: 20px; font-weight: 600; font-size: 0.8rem; }
        .btn-action { width: 100%; padding: 6px 15px; border-radius: 8px; font-weight: 600; font-size: 0.85rem; border: none; cursor: pointer; transition: 0.2s; text-align: center; margin-bottom: 3px; }
        .btn-start { background: var(--soft-green); color: var(--primary-green); }
        .btn-start:hover { background: var(--primary-green); color: #fff; }
        .btn-complete { background: #cce5ff; color: #004085; }
        .btn-complete:hover { background: #004085; color: #fff; }
        .btn-noshow { background: #fdf2f2; color: #dc3545; }
        .btn-noshow:hover { background: #dc3545; color: #fff; }
        #reader { width: 100%; max-width: 500px; margin: 0 auto; border-radius: 15px; overflow: hidden; border: 2px solid var(--primary-green); }
    </style>
</head>

<body class="ttr-opened-sidebar ttr-pinned-sidebar" style="background-color: #FCFCFC;">
    <?php
        $page = 'appointments';
        $secondnav = 'queuing';
        include 'main/admin-nav.php'; 
    ?>

    <main class="ttr-wrapper" style="background-color: #FCFCFC;">
        <div class="container-fluid">
            <div class="db-breadcrumb d-flex justify-content-between align-items-center">
                <div>
                    <h4 class="breadcrumb-title"><i class="ti ti-calendar"></i> <?php echo htmlspecialchars($account_course); ?> Service</h4>
                    <ul class="db-breadcrumb-list">
                        <li>Operating: <strong style="color: var(--primary-green);"><?php echo htmlspecialchars($staff_window); ?></strong></li>
                    </ul>
                </div>
                <div>
                    <a href="block-schedules" class="btn green radius-xl" style="background-color: var(--primary-green);">
                        <i class="ti-na"></i> Block Day/Time
                    </a>
                </div>
            </div>
            
            <p class="text-muted mb-0" style="font-size:0.85rem;">Online bookings are automatically cancelled if the student has not checked in within <?php echo SmartServeAppointmentRules::ARRIVAL_GRACE_MINUTES; ?> minutes of the scheduled time.</p>
            <div class="row mb-4 mt-4">
                <div class="col-md-3">
                    <div class="stat-card active-filter filter-widget" data-filter="1">
                        <div class="stat-icon" style="background: var(--soft-green); color: var(--primary-green);"><i class="ti-user"></i></div>
                        <div class="stat-info"><h3><?php echo $waiting; ?></h3><p>Waiting</p></div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="stat-card filter-widget" data-filter="2">
                        <div class="stat-icon" style="background: #e2e8f0; color: #4a5568;"><i class="ti-headphone-alt"></i></div>
                        <div class="stat-info"><h3><?php echo $serving; ?></h3><p>Serving</p></div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="stat-card filter-widget" data-filter="3">
                        <div class="stat-icon" style="background: #d4edda; color: #155724;"><i class="ti-check-box"></i></div>
                        <div class="stat-info"><h3><?php echo $completed; ?></h3><p>Completed</p></div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="stat-card filter-widget" data-filter="all">
                        <div class="stat-icon" style="background: #e1e5eb; color: #495057;"><i class="ti-layers"></i></div>
                        <div class="stat-info"><h3><?php echo $all_count; ?></h3><p>All Today</p></div>
                    </div>
                </div>
            </div>

            <div class="queue-table-wrapper">
                <h5 class="fw-bold mb-4" id="table-title">Waiting Appointments</h5>
                <div class="table-responsive">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Queue No.</th>
                                <th>Name</th>
                                <th>Schedule</th>
                                <th>Status & Location</th>
                                <th class="text-right" style="width: 150px;">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                                if(empty($today_queue)) {
                                    echo '<tr><td colspan="5" class="text-center py-4 text-muted">No appointments today.</td></tr>';
                                } else {
                                    foreach($today_queue as $q) {
                                        $ao_id = $q['id'];
                                        $queue_no = $q['queue_number'] ? $q['queue_number'] : '---';
                                        $priority_badge = ($q['is_priority'] == 1) ? '<br><span class="badge-soft-danger mt-1" style="display:inline-block; padding: 3px 8px; font-size:0.7rem; background:#ffe2e5; color:#e63c3c;"><i class="ti-star"></i> ' . htmlspecialchars($q['priority'] ?? 'Priority') . '</span>' : '';

                                        if (!empty($q['guest_name'])) {
                                            $display_name = htmlspecialchars($q['guest_name']);
                                        } else {
                                            $display_name = htmlspecialchars($q['fname'].' '.$q['lname']);
                                                    if (!empty($q['has_pwd_id'])) $display_name .= '<br><a class="text-success" href="../pwd-id.php?id=' . (int)$q['student_id'] . '" target="_blank" rel="noopener"><i class="ti-id-badge"></i> View PWD ID</a>';
                                        }

                                        // --- FIX: Read is_walkin from the database ---
                                        $isWalkinFlag = isset($q['is_walkin']) ? strtolower((string)$q['is_walkin']) : '';
                                        $typeText = ($isWalkinFlag === '1' || $isWalkinFlag === 'walk-in' || !empty($q['guest_name'])) ? 'Walk-in' : 'Online';
                                        
                                        $name_subtext = '<small class="text-muted"><i class="ti-user"></i> ' . $typeText . '</small>';

                                        // --- NEW: Add the specific notes/purpose ---
                                        if (!empty($q['notes'])) {
                                            $name_subtext .= '<br><small class="text-muted d-block mt-1" style="font-size: 0.75rem; line-height: 1.2;"><b>Notes:</b> ' . htmlspecialchars($q['notes']) . '</small>';
                                        }
                                        
                                        $formatted_time = date('h:i A', strtotime($q['appointment_time']));
                                        $serving_window = isset($q['served_at_window']) ? $q['served_at_window'] : '';
                                        
                                        $statusHtml = '';
                                        $actionBtn = '';

                                        if($q['status'] == 0) {
                                            $statusHtml = '<span class="badge-soft-warning">Expected Arrival</span>';
                                            $actionBtn = '<button class="btn-action btn-noshow" onclick="markNoShow('.$ao_id.')">Mark No Show</button>';
                                        } 
                                        elseif($q['status'] == 1) {
                                            $statusHtml = '<span class="badge-soft-success">Waiting</span>';
                                            $actionBtn = '
                                                <div class="d-flex flex-column">
                                                    <button class="btn-action btn-start" onclick="startServing('.$ao_id.')">Call Next</button>
                                                    <button class="btn-action btn-noshow" style="font-size: 0.75rem; padding: 4px;" onclick="markNoShow('.$ao_id.')">No Show</button>
                                                </div>';
                                        } 
                                        elseif($q['status'] == 2) {
                                            if ($serving_window == $staff_window) {
                                                $statusHtml = '<span class="badge-soft-primary">Serving at ' . htmlspecialchars($serving_window) . '</span>';
                                                $actionBtn = '
                                                    <div class="d-flex flex-column">
                                                        <button class="btn-action btn-complete" onclick="openCompleteModal('.$ao_id.')">Complete</button>
                                                        <button class="btn-action btn-noshow" style="font-size: 0.75rem; padding: 4px;" onclick="markNoShow('.$ao_id.')">No Show</button>
                                                    </div>';
                                            } else {
                                                $statusHtml = '<span class="badge-soft-dark">Serving at ' . htmlspecialchars($serving_window) . '</span>';
                                                $actionBtn = '<span class="text-muted d-block text-center" style="font-size:0.8rem;">Locked to ' . htmlspecialchars($serving_window) . '</span>';
                                            }
                                        } 
                                        elseif($q['status'] == 3) {
                                            $statusHtml = '<span class="badge-soft-success" style="background:#eef2f7; color:#6c757d;">Completed</span>';
                                            $actionBtn = '<span class="text-muted d-block text-center"><i class="ti-check"></i> Done</span>';
                                        } 
                                        elseif($q['status'] == 4) {
                                            $isAuto = str_starts_with($q['office_notes'] ?? '', 'System: Auto-cancel');
                                            $statusHtml = $isAuto ? '<span class="badge-soft-danger">Cancelled</span>' : '<span class="badge-soft-danger">No Show</span>';
                                            $actionBtn = '<span class="text-muted d-block text-center">---</span>';
                                        }
                            ?>
                            <tr class="queue-row" data-status="<?php echo $q['status']; ?>">
                                <td><span style="font-size: 1.1rem; color: var(--primary-green);"><b><?php echo $queue_no; ?></b></span><?php echo $priority_badge; ?></td>
                                <td><strong><?php echo $display_name; ?></strong><br><?php echo $name_subtext; ?></td>
                                <td><?php echo $formatted_time; ?></td>
                                <td><?php echo $statusHtml; ?></td>
                                <td class="text-right"><?php echo $actionBtn; ?></td>
                            </tr>
                            <?php }} ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </main>

    <div class="modal fade" id="qrModal" tabindex="-1" role="dialog" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered" role="document">
            <div class="modal-content" style="border-radius: 20px;">
                <div class="modal-header border-0 pb-0">
                    <h5 class="modal-title fw-bold">Scan QR Code</h5>
                    <button type="button" class="close" data-dismiss="modal" onclick="stopScanner()">&times;</button>
                </div>
                <div class="modal-body text-center pt-2">
                    <p class="text-muted mb-4">Position QR code within frame.</p>
                    <div id="reader"></div>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="completeModal" tabindex="-1" role="dialog" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered" role="document">
            <div class="modal-content" style="border-radius: 20px;">
                <div class="modal-header border-0 pb-0">
                    <h5 class="modal-title fw-bold">Complete Transaction</h5>
                    <button type="button" class="close" data-dismiss="modal">&times;</button>
                </div>
                <div class="modal-body">
                    <input type="hidden" id="complete_ao_id">
                    <div class="form-group">
                        <label class="form-label" style="font-weight: 600;">Office Notes</label>
                        <textarea id="office_notes" name="office_notes" maxlength="1000" class="form-control" rows="4" placeholder="Brief summary..." style="border-radius: 10px;"></textarea>
                    </div>
                </div>
                <div class="modal-footer border-0">
                    <button type="button" class="btn btn-secondary radius-xl outline" data-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-primary radius-xl" style="background: var(--primary-green); border:none;" onclick="submitComplete()">Save & Complete</button>
                </div>
            </div>
        </div>
    </div>

    <script src="../dashboard/assets/js/jquery.min.js"></script>
    <script src="../dashboard/assets/vendors/bootstrap/js/bootstrap.min.js"></script>
    <script src="../dashboard/assets/js/admin.js"></script>

    <script>
        $(document).ready(function() {
            const queueVersion = <?php echo json_encode($queue_version); ?>;
            let refreshing = false;
            // Check the server regularly while keeping open scans/transaction notes intact.
            function refreshQueue() {
                if (refreshing) return;
                refreshing = true;
                $.ajax({url: window.location.href, method: 'POST', dataType: 'json', data: {action: 'refresh_queue'}, timeout: 15000})
                    .done(function(res) {
                        if (res.status !== 'success' || res.version === queueVersion || $('.modal.show, .ss-modal-backdrop').length) return;
                        sessionStorage.setItem('activeQueueTab', String($('.filter-widget.active-filter').data('filter') || '1'));
                        location.reload();
                    })
                    .always(function() { refreshing = false; });
            }
            setInterval(refreshQueue, 15000);
            let targetTab = sessionStorage.getItem('activeQueueTab') || '1'; 
            sessionStorage.removeItem('activeQueueTab'); 
            setTimeout(function() { $('.filter-widget[data-filter="' + targetTab + '"]').trigger('click'); }, 50);

            $('.filter-widget').on('click', function() {
                $('.filter-widget').removeClass('active-filter');
                $(this).addClass('active-filter');
                let targetStatus = String($(this).data('filter'));
                $('#table-title').text($(this).find('p').text() + ' Appointments');
                
                // JavaScript Filter update: Target Status 1 (Waiting) now shows both Status 0 and Status 1
                if (targetStatus === 'all') { 
                    $('.queue-row').fadeIn(250); 
                } else if (targetStatus === '1') {
                    $('.queue-row').hide();
                    $('.queue-row[data-status="0"], .queue-row[data-status="1"]').fadeIn(250);
                } else { 
                    $('.queue-row').hide(); 
                    $('.queue-row[data-status="' + targetStatus + '"]').fadeIn(250); 
                }
            });
        });

        let html5QrcodeScanner = null;
        $('#qrModal').on('shown.bs.modal', function () {
            html5QrcodeScanner = new Html5QrcodeScanner("reader", { fps: 10, qrbox: {width: 250, height: 250} }, false);
            html5QrcodeScanner.render(onScanSuccess, onScanFailure);
        });
        $('#qrModal').on('hidden.bs.modal', function () { if(html5QrcodeScanner) html5QrcodeScanner.clear(); });

        function onScanSuccess(decodedText) {
            html5QrcodeScanner.pause();
            $.post(window.location.href, { action: 'scan_qr', tracking_no: decodedText }, function(res) {
                SmartServe.notify(res.msg || 'Unable to scan this code.', res.status === 'success' ? 'success' : 'danger').then(function() {
                    if(res.status === 'success') { sessionStorage.setItem('activeQueueTab', '1'); location.reload(); }
                    else { html5QrcodeScanner.resume(); }
                });
            });
        }
        function onScanFailure(error) {}
        function stopScanner() { if(html5QrcodeScanner) html5QrcodeScanner.clear(); }

        async function startServing(ao_id) {
            if(await SmartServe.confirm('Call this student to your window?')) {
                $.post(window.location.href, { action: 'start_serving', ao_id: ao_id }, function(res) {
                    if(res.status === 'success') { sessionStorage.setItem('activeQueueTab', '2'); location.reload(); }
                    else { SmartServe.notify(res.msg || 'Unable to start serving this student.', 'danger'); }
                });
            }
        }

        async function markNoShow(ao_id) {
            if(await SmartServe.confirm('Are you sure you want to mark this as No Show?')) {
                $.post(window.location.href, { action: 'no_show', ao_id: ao_id }, function(res) {
                    if(res.status === 'success') { sessionStorage.setItem('activeQueueTab', 'all'); location.reload(); }
                    else { SmartServe.notify(res.msg || 'Unable to mark this student as No Show.', 'danger'); }
                });
            }
        }

        function openCompleteModal(ao_id) {
            $('#complete_ao_id').val(ao_id);
            $('#office_notes').val('');
            $('#completeModal').modal('show');
        }

        function submitComplete() {
            $.post(window.location.href, { action: 'complete_serving', ao_id: $('#complete_ao_id').val(), office_notes: $('#office_notes').val() }, function(res) {
                if(res.status === 'success') { sessionStorage.setItem('activeQueueTab', '3'); location.reload(); }
                else { SmartServe.notify(res.msg || 'Unable to complete this appointment.', 'danger'); }
            });
        }
    </script>
</body>
</html>
