<?php
    ob_start();
    include('../global/model.php');
    $model = new Model();

    include('main/department.php');
    $account_id = $_SESSION['sess'];

    // --- FETCH STAFF'S ACTUAL OFFICE ID ---
    $staff_details = $model->displayAccountDetails($account_id);
    $office_id = null;
    if (!empty($staff_details)) {
        foreach ($staff_details as $dep) {
            $office_id = $dep['course'] ?? null; // 'course' holds the actual Office ID
            break;
        }
    }

    // --- Date Navigation Logic ---
    $active_date = isset($_GET['date']) ? date('Y-m-d', strtotime($_GET['date'])) : date('Y-m-d');
    
    $prev_date = date('Y-m-d', strtotime($active_date . ' -1 day'));
    $next_date = date('Y-m-d', strtotime($active_date . ' +1 day'));
    
    $display_date = date('l, F d, Y', strtotime($active_date));
    $is_today = ($active_date == date('Y-m-d'));

    // --- Fetch Data ---
    // This naturally pulls ALL windows because it filters by the Office ID, not the Window ID.
    $history_data = $model->getOfficeHistoryByDate($office_id, $active_date);

    // --- Calculate Daily Metrics ---
    $total_records = is_array($history_data) ? count($history_data) : 0;
    $total_completed = 0;
    $total_noshow = 0;
    $total_walkins = 0;
    
    $total_processing_seconds = 0;
    $valid_times_count = 0;

    if ($total_records > 0) {
        foreach ($history_data as $row) {
            if ($row['status'] == 3) $total_completed++;
            if ($row['status'] == 4) $total_noshow++;
            
            // Check if it's a walk-in by looking at the queue number prefix (W-)
            if (isset($row['queue_number']) && substr($row['queue_number'], 0, 1) === 'W') {
                $total_walkins++;
            }

            // Calculate Processing Time
            if ($row['status'] == 3 && !empty($row['time_started']) && !empty($row['time_completed'])) {
                $start = strtotime($row['time_started']);
                $end = strtotime($row['time_completed']);
                if ($end > $start) {
                    $total_processing_seconds += ($end - $start);
                    $valid_times_count++;
                }
            }
        }
    }

    // Average Processing Time formatting
    $avg_time_display = "N/A";
    if ($valid_times_count > 0) {
        $avg_seconds = $total_processing_seconds / $valid_times_count;
        $avg_minutes = floor($avg_seconds / 60);
        $avg_time_display = ($avg_minutes > 0) ? $avg_minutes . " min" : "< 1 min";
    }

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <?php require_once __DIR__ . '/../global/form-assets.php'; ?>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="icon" href="../assets/img/<?php echo $web_icon; ?>.png" type="image/x-icon" />
    <title><?php echo $web_name; ?> | Office History</title>
    
    <link rel="stylesheet" type="text/css" href="../dashboard/assets/css/assets.css">
    <link rel="stylesheet" type="text/css" href="../dashboard/assets/css/typography.css">
    <link rel="stylesheet" type="text/css" href="../dashboard/assets/css/style.css">
    <link rel="stylesheet" type="text/css" href="../dashboard/assets/css/dashboard.css">
    
    <style>
        :root { --primary-green: <?php echo $primary_color; ?>; --soft-green: #eef7f1; }
        
        .date-navigator {
            background: #fff; border-radius: 15px; padding: 15px 25px; 
            box-shadow: 0 5px 15px rgba(0,0,0,0.03); border: 1px solid rgba(0,0,0,0.05);
            display: flex; justify-content: space-between; align-items: center; margin-bottom: 25px;
        }
        .btn-nav {
            background: #f8f9fa; border: 1px solid #eee; color: #4a5568; 
            padding: 8px 20px; border-radius: 10px; font-weight: 600; transition: 0.3s;
            display: inline-flex; align-items: center; gap: 8px; text-decoration: none !important;
        }
        .btn-nav:hover { background: var(--primary-green); color: #fff; border-color: var(--primary-green); }
        .date-display { text-align: center; }
        .date-display h4 { margin: 0; font-weight: 800; color: #2d3436; font-size: 1.4rem; }
        .date-display input[type="date"] {
            border: none; background: transparent; color: #6c757d; font-weight: 600; 
            font-size: 0.9rem; cursor: pointer; outline: none; margin-top: 5px;
        }

        .history-stat { 
            background: #fff; border-radius: 15px; padding: 20px; border: 1px solid #eee; 
            display: flex; align-items: center; gap: 15px; box-shadow: 0 4px 15px rgba(0,0,0,0.02); 
        }
        .stat-icon { width: 45px; height: 45px; border-radius: 10px; display: flex; align-items: center; justify-content: center; font-size: 20px; }
        .stat-info h3 { margin: 0; font-weight: 800; font-size: 1.4rem; color: #2d3436; }
        .stat-info p { margin: 0; color: #8795a1; font-size: 0.75rem; text-transform: uppercase; font-weight: 700; letter-spacing: 0.5px; }

        .history-card { background: #fff; border-radius: 20px; padding: 25px; box-shadow: 0 10px 30px rgba(0,0,0,0.03); border: 1px solid rgba(0,0,0,0.05); }
        .table thead th { border-bottom: 2px solid var(--soft-green); color: #6c757d; text-transform: uppercase; font-size: 0.75rem; letter-spacing: 0.5px; padding-bottom: 15px; border-top: none; }
        .table tbody td { vertical-align: middle; padding: 15px 10px; border-bottom: 1px solid #f8f9fa; font-size: 0.95rem; }
        
        .badge-type { padding: 4px 10px; border-radius: 6px; font-size: 0.75rem; font-weight: 700; }
        .type-online { background: #e2e8f0; color: #4a5568; }
        .type-walkin { background: #eef7f1; color: var(--primary-green); border: 1px solid #d1e8da; }

        .status-dot { width: 10px; height: 10px; border-radius: 50%; display: inline-block; margin-right: 6px; }
        .dot-completed { background: #28a745; box-shadow: 0 0 5px rgba(40,167,69,0.4); }
        .dot-noshow { background: #dc3545; box-shadow: 0 0 5px rgba(220,53,69,0.4); }
        .dot-pending { background: #ffc107; }

        .notes-box { background: #f8f9fa; padding: 10px; border-radius: 8px; border: 1px solid #eee; font-size: 0.85rem; color: #4a5568; margin-top: 5px; }
    </style>
</head>
<body class="ttr-opened-sidebar ttr-pinned-sidebar" style="background-color: #FCFCFC;">
    <?php 
        $page = 'appointments'; 
        $secondnav = 'history';
        include 'main/admin-nav.php'; 
    ?>

    <main class="ttr-wrapper" style="background-color: #FCFCFC;">
        <div class="container-fluid">
            <div class="db-breadcrumb">
                <h4 class="breadcrumb-title"><i class="ti ti-calendar"></i> Appointments</h4>
                <ul class="db-breadcrumb-list"><li>Appointment Records</li></ul>
            </div>
            
            <div class="row">
                <div class="col-12">
                    <div class="date-navigator">
                        <a href="?date=<?php echo $prev_date; ?>" class="btn-nav"><i class="ti-angle-left"></i> Previous Day</a>
                        
                        <div class="date-display">
                            <h4><?php echo $is_today ? "Today" : $display_date; ?></h4>
                            <input type="date" id="datePicker" value="<?php echo $active_date; ?>" onchange="window.location.href='?date='+this.value">
                        </div>

                        <a href="?date=<?php echo $next_date; ?>" class="btn-nav">Next Day <i class="ti-angle-right"></i></a>
                    </div>
                </div>
            </div>

            <div class="row mb-4">
                <div class="col-md-4">
                    <div class="history-stat">
                        <div class="stat-icon" style="background: var(--soft-green); color: var(--primary-green);"><i class="ti-check-box"></i></div>
                        <div class="stat-info"><h3><?php echo $total_completed; ?></h3><p>Completed</p></div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="history-stat">
                        <div class="stat-icon" style="background: #fdf2f2; color: #dc3545;"><i class="ti-na"></i></div>
                        <div class="stat-info"><h3><?php echo $total_noshow; ?></h3><p>Cancelled / No-show / Rescheduled</p></div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="history-stat">
                        <div class="stat-icon" style="background: #fff3cd; color: #856404;"><i class="ti-timer"></i></div>
                        <div class="stat-info"><h3><?php echo $avg_time_display; ?></h3><p>Avg. Process Time</p></div>
                    </div>
                </div>
            </div>

            <div class="row">
                <div class="col-12">
                    <div class="history-card">
                        <h5 class="fw-bold mb-4">Detailed Audit Log</h5>
                        
                        <div class="table-responsive">
                            <table class="table">
                                <thead>
                                    <tr>
                                        <th>Queue / Type</th>
                                        <th>Student Details</th>
                                        <th>Status</th>
                                        <th>Timestamps</th>
                                        <th>Office Notes</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php
                                        if (empty($history_data)) {
                                            echo '<tr><td colspan="5" class="text-center py-5 text-muted">
                                                    <i class="ti-calendar text-light mb-2" style="font-size: 30px;"></i><br>
                                                    No appointments recorded for this date.
                                                  </td></tr>';
                                        } else {
                                            foreach ($history_data as $row) {
                                                
                                                // Type Badge (Checks if Queue No starts with W)
                                                $is_walkin = (isset($row['queue_number']) && substr($row['queue_number'], 0, 1) === 'W') ? true : false;
                                                $typeBadge = $is_walkin 
                                                    ? '<span class="badge-type type-walkin"><i class="ti-direction"></i> Walk-in</span>'
                                                    : '<span class="badge-type type-online"><i class="ti-world"></i> Online</span>';
                                                
                                                // Queue No & Served At Window
                                                $queueNo = $row['queue_number'] ? '<strong class="text-dark" style="font-size: 1.1rem;">'.$row['queue_number'].'</strong><br>' : '';
                                                $windowDisplay = !empty($row['served_at_window']) ? '<div class="mt-2"><small class="text-muted"><i class="ti-layout-grid2"></i> Served at: <strong>' . htmlspecialchars($row['served_at_window']) . '</strong></small></div>' : '';

                                                // --- NAME & PRIORITY LOGIC ---
                                                $priority_badge = (!empty($row['priority']) && (int)$row['is_priority'] == 1) ? '<br><span class="badge-soft-danger mt-1" style="display:inline-block; padding: 3px 8px; font-size:0.7rem; background:#ffe2e5; color:#e63c3c;"><i class="ti-star"></i> ' . htmlspecialchars($row['priority']) . '</span>' : '';

                                                if (!empty($row['guest_name'])) {
                                                    $display_name = htmlspecialchars($row['guest_name']);
                                                    $course_display = 'Guest Walk-in';
                                                } else {
                                                    $display_name = htmlspecialchars($row['fname'].' '.$row['lname']);
                                                    if (!empty($row['has_pwd_id'])) $display_name .= '<br><a class="text-success" href="../pwd-id.php?id=' . (int)$row['student_id'] . '" target="_blank" rel="noopener"><i class="ti-id-badge"></i> View PWD ID</a>';
                                                    $course_display = htmlspecialchars($row['course']);
                                                }

                                                // Status
                                                $statusDisplay = '';
                                                if ($row['status'] == 3) $statusDisplay = '<span class="status-dot dot-completed"></span> Completed';
                                                elseif ($row['status'] == 4) $statusDisplay = '<span class="status-dot dot-noshow"></span> Cancelled / No-show / Rescheduled';
                                                elseif ($row['status'] == 2) $statusDisplay = '<span class="status-dot dot-pending"></span> Serving';
                                                elseif ($row['status'] == 1) $statusDisplay = '<span class="status-dot dot-pending"></span> Queued';
                                                else $statusDisplay = '<span class="status-dot dot-pending"></span> Pending arrival';

                                                // Timestamps formatting (12:27 AM format)
                                                $timeQueued = $row['time_queued'] ? date('h:i A', strtotime($row['time_queued'])) : '--:--';
                                                $timeCompleted = $row['time_completed'] ? date('h:i A', strtotime($row['time_completed'])) : '--:--';
                                                $schedTime = $row['appointment_time'] ? date('h:i A', strtotime($row['appointment_time'])) : '--:--';
                                    ?>
                                    <tr>
                                        <td>
                                            <?php echo $queueNo; ?>
                                            <?php echo $typeBadge; ?>
                                            <?php echo $windowDisplay; ?>
                                        </td>
                                        <td>
                                            <strong style="color: var(--primary-green);"><?php echo $display_name; ?></strong><br>
                                            <span class="text-muted" style="font-size: 0.8rem;"><?php echo $course_display; ?></span><br>
                                            <span class="text-muted" style="font-size: 0.75rem;"><?php echo htmlspecialchars($row['tracking_no']); ?></span>
                                            <?php echo $priority_badge; ?>
                                        </td>
                                        <td><strong><?php echo $statusDisplay; ?></strong></td>
                                        <td>
                                            <small class="text-muted d-block"><i class="ti-alarm-clock"></i> Sched: <strong><?php echo $schedTime; ?></strong></small>
                                            <small class="text-muted d-block"><i class="ti-arrow-down"></i> Queued: <strong><?php echo $timeQueued; ?></strong></small>
                                            <small class="text-muted d-block"><i class="ti-check"></i> Done: <strong><?php echo $timeCompleted; ?></strong></small>
                                        </td>
                                        <td style="max-width: 250px;">
                                            <?php if (!empty($row['office_notes'])): ?>
                                                <div class="notes-box"><?php echo nl2br(htmlspecialchars($row['office_notes'])); ?></div>
                                            <?php else: ?>
                                                <span class="text-muted" style="font-size: 0.8rem; font-style: italic;">No notes added.</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                    <?php 
                                            }
                                        } 
                                    ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

        </div>
    </main>
    <div class="ttr-overlay"></div>

    <script src="../dashboard/assets/js/jquery.min.js"></script>
    <script src="../dashboard/assets/vendors/bootstrap/js/bootstrap.min.js"></script>
    <script src="../dashboard/assets/js/admin.js"></script>
</body>
</html>
