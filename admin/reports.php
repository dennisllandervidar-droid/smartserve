<?php
    ob_start();
    include('../global/model.php');
    $model = new Model();


    // Handle Filter Inputs
    $filter_office = isset($_GET['office_id']) && $_GET['office_id'] !== '' ? (int)$_GET['office_id'] : null;
    $filter_start = isset($_GET['start_date']) && $_GET['start_date'] !== '' ? $_GET['start_date'] : date('Y-m-d');
    $filter_end = isset($_GET['end_date']) && $_GET['end_date'] !== '' ? $_GET['end_date'] : date('Y-m-d');
    $filter_status = isset($_GET['status']) && $_GET['status'] !== '' ? (int)$_GET['status'] : null;

    // Fetch Report Data
    $report_data = $model->getAdminReports($filter_office, $filter_start, $filter_end, $filter_status);
    $records = $report_data['records'];
    $metrics = $report_data['metrics'];

    include('main/department.php');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <?php require_once __DIR__ . '/../global/form-assets.php'; ?>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="icon" href="../assets/img/<?php echo $web_icon; ?>.png" type="image/x-icon" />
    <title><?php echo $web_name; ?> | Admin Reports</title>

    <link rel="stylesheet" type="text/css" href="../dashboard/assets/css/dataTables.bootstrap4.min.css">
    <link rel="stylesheet" type="text/css" href="../dashboard/assets/css/assets.css">
    <link rel="stylesheet" type="text/css" href="../dashboard/assets/css/typography.css">
    <link rel="stylesheet" type="text/css" href="../dashboard/assets/css/style.css">
    <link rel="stylesheet" type="text/css" href="../dashboard/assets/css/dashboard.css">
    
    <style type="text/css">
        :root { --primary-green: <?php echo $primary_color; ?>; --soft-green: #eef7f1; }
        
        .filter-wrapper { background: #fff; border-radius: 15px; padding: 20px; border: 1px solid #eee; margin-bottom: 25px; box-shadow: 0 4px 15px rgba(0,0,0,0.02); }
        .filter-wrapper form { display: flex; gap: 15px; align-items: flex-end; flex-wrap: wrap; }
        .filter-group { flex: 1; min-width: 200px; }
        .filter-group label { font-size: 0.85rem; font-weight: 700; color: #495057; margin-bottom: 5px; display: block; }
        .custom-input { border-radius: 10px; border: 2px solid #eee; padding: 10px 15px; width: 100%; outline: none; font-weight: 500; color: #4a5568; }
        .custom-input:focus { border-color: var(--primary-green); }
        .btn-filter { background: var(--primary-green); color: white; border: none; padding: 10px 25px; border-radius: 10px; font-weight: 600; height: 44px; transition: 0.3s; cursor: pointer; }
        
        .history-stat { background: #fff; border-radius: 15px; padding: 20px; border: 1px solid #eee; display: flex; align-items: center; gap: 15px; box-shadow: 0 4px 15px rgba(0,0,0,0.02); }
        .stat-icon { width: 45px; height: 45px; border-radius: 10px; display: flex; align-items: center; justify-content: center; font-size: 20px; }
        .stat-info h3 { margin: 0; font-weight: 800; font-size: 1.4rem; color: #2d3436; }
        .stat-info p { margin: 0; color: #8795a1; font-size: 0.75rem; text-transform: uppercase; font-weight: 700; letter-spacing: 0.5px; }

        .history-card { background: #fff; border-radius: 20px; padding: 25px; box-shadow: 0 10px 30px rgba(0,0,0,0.03); border: 1px solid rgba(0,0,0,0.05); }
        .table thead th { border-bottom: 2px solid var(--soft-green); color: #6c757d; text-transform: uppercase; font-size: 0.75rem; letter-spacing: 0.5px; padding-bottom: 15px; border-top: none; }
        .table tbody td { vertical-align: middle; padding: 15px 10px; border-bottom: 1px solid #f8f9fa; font-size: 0.95rem; }
        
        .badge-type { padding: 4px 10px; border-radius: 6px; font-size: 0.75rem; font-weight: 700; }
        .type-online { background: #e2e8f0; color: #4a5568; }
        .type-walkin { background: #eef7f1; color: var(--primary-green); border: 1px solid #d1e8da; }

        /* Exact status dots from Staff Side */
        .status-dot { width: 10px; height: 10px; border-radius: 50%; display: inline-block; margin-right: 6px; }
        .dot-completed { background: #28a745; box-shadow: 0 0 5px rgba(40,167,69,0.4); }
        .dot-noshow { background: #dc3545; box-shadow: 0 0 5px rgba(220,53,69,0.4); }
        .dot-serving { background: #0d6efd; box-shadow: 0 0 5px rgba(13,110,253,0.4); }
        .dot-pending { background: #ffc107; }

        .notes-box { background: #f8f9fa; padding: 10px; border-radius: 8px; border: 1px solid #eee; font-size: 0.85rem; color: #4a5568; margin-top: 5px; }
    </style>
</head>

<body class="ttr-opened-sidebar ttr-pinned-sidebar" style="background-color: #FCFCFC;">
    <?php
        $page = 'reports';
        $secondnav = 'records';
        include 'main/admin-nav.php'; 
    ?>

    <main class="ttr-wrapper" style="background-color: #FCFCFC;">
        <div class="container-fluid">
            <div class="db-breadcrumb">
                <h4 class="breadcrumb-title"><i class="ti ti-bar-chart"></i> Reports</h4>
                <ul class="db-breadcrumb-list"><li>Service Visit Records</li></ul>
            </div>
            <p class="text-muted">Each record and count represents one service visit. A booking with a completed service and a cancelled service appears under each service's own status.</p>
            
            <div class="row">
                <div class="col-12">
                    <div class="filter-wrapper">
                        <form method="GET" action="">
                            <div class="filter-group">
                                <label>Select Office</label>
                                <select name="office_id" class="custom-input">
                                    <option value="">All Offices</option>
                                    <?php
                                        $offices = $model->getOffices();
                                        foreach ($offices as $off) {
                                            $selected = ($filter_office == $off['id']) ? 'selected' : '';
                                            echo "<option value='{$off['id']}' {$selected}>".htmlspecialchars($off['office_name'])."</option>";
                                        }
                                    ?>
                                </select>
                            </div>
                            <div class="filter-group">
                                <label>Date From</label>
                                <input type="date" name="start_date" class="custom-input" value="<?php echo htmlspecialchars($filter_start); ?>">
                            </div>
                            <div class="filter-group">
                                <label>Date To</label>
                                <input type="date" name="end_date" class="custom-input" value="<?php echo htmlspecialchars($filter_end); ?>">
                            </div>
                            <div class="filter-group">
                                <label>Status</label>
                                <select name="status" class="custom-input">
                                    <option value="" <?php echo ($filter_status === null) ? 'selected' : ''; ?>>All Status</option>
                                    <option value="0" <?php echo ($filter_status === 0) ? 'selected' : ''; ?>>Pending arrival</option>
                                    <option value="1" <?php echo ($filter_status === 1) ? 'selected' : ''; ?>>Queued</option>
                                    <option value="2" <?php echo ($filter_status === 2) ? 'selected' : ''; ?>>Serving</option>
                                    <option value="3" <?php echo ($filter_status === 3) ? 'selected' : ''; ?>>Completed</option>
                                    <option value="4" <?php echo ($filter_status === 4) ? 'selected' : ''; ?>>Cancelled / No-show / Rescheduled</option>
                                </select>
                            </div>
                            <div>
                                <button type="submit" class="btn-filter"><i class="ti-search"></i> Generate</button>
                                <a href="reports" class="btn btn-secondary radius-xl outline" style="height: 44px; margin-left: 10px; line-height: 30px;">Reset</a>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            <div class="row mb-4">
                <div class="col-md-3">
                    <div class="history-stat">
                        <div class="stat-icon" style="background: var(--soft-green); color: var(--primary-green);"><i class="ti-check-box"></i></div>
                        <div class="stat-info"><h3><?php echo $metrics['total_completed']; ?></h3><p>Completed service visits</p></div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="history-stat">
                        <div class="stat-icon" style="background: #fdf2f2; color: #dc3545;"><i class="ti-na"></i></div>
                        <div class="stat-info"><h3><?php echo $metrics['total_noshow']; ?></h3><p>Cancelled / No-show / Rescheduled</p></div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="history-stat">
                        <div class="stat-icon" style="background: #fff3cd; color: #856404;"><i class="ti-timer"></i></div>
                        <div class="stat-info"><h3><?php echo $metrics['avg_process']; ?></h3><p>Avg. Process Time</p></div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="history-stat">
                        <div class="stat-icon" style="background: #e2e8f0; color: #4a5568;"><i class="ti-bar-chart"></i></div>
                        <div class="stat-info"><h3><?php echo $metrics['peak_hour']; ?></h3><p>Busiest Time</p></div>
                    </div>
                </div>
            </div>

            <div class="row">
                <div class="col-12 m-b30">
                    <div class="history-card">
                        <h5 class="fw-bold mb-4">Service Visit Records</h5>
                        
                        <div class="table-responsive">
                            <table id="table" class="table">
                                <thead>
                                    <tr>
                                        <th>Queue / Type</th>
                                        <th>Client Details</th>
                                        <th>Office</th>
                                        <th>Status</th>
                                        <th>Timestamps</th>
                                        <th>Office Notes</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php 
                                        foreach ($records as $row): 
                                            // Exact Type Badge Match
                                            $is_walkin = (isset($row['queue_number']) && substr($row['queue_number'], 0, 1) === 'W') ? true : false;
                                            $typeBadge = $is_walkin 
                                                ? '<span class="badge-type type-walkin"><i class="ti-direction"></i> Walk-in</span>'
                                                : '<span class="badge-type type-online"><i class="ti-world"></i> Online</span>';
                                                
                                            $queueNo = $row['queue_number'] ? '<strong class="text-dark" style="font-size: 1.1rem;">'.$row['queue_number'].'</strong><br>' : '';
                                            $windowDisplay = !empty($row['served_at_window']) ? '<div class="mt-2"><small class="text-muted"><i class="ti-layout-grid2"></i> <strong>' . htmlspecialchars($row['served_at_window']) . '</strong></small></div>' : '';

                                            // Exact Name Match (Removed Course/Guest Walk-in text)
                                            $priority_badge = (!empty($row['priority']) && (int)$row['is_priority'] == 1) ? '<br><span class="badge-soft-danger mt-1" style="display:inline-block; padding: 3px 8px; font-size:0.7rem; background:#ffe2e5; color:#e63c3c;"><i class="ti-star"></i> ' . htmlspecialchars($row['priority']) . '</span>' : '';
                                            $display_name = !empty($row['guest_name']) ? htmlspecialchars($row['guest_name']) : htmlspecialchars($row['fname'].' '.$row['lname']);

                                            // Exact Status Display Match
                                            $statusDisplay = '';
                                            if ($row['status'] == 3) $statusDisplay = '<span class="status-dot dot-completed"></span> Completed';
                                            elseif ($row['status'] == 4) $statusDisplay = '<span class="status-dot dot-noshow"></span> ' . (!empty($row['rescheduled_tracking_no']) ? 'Rescheduled' : 'Cancelled / No-show');
                                            elseif ($row['status'] == 2) $statusDisplay = '<span class="status-dot dot-serving"></span> Serving';
                                            elseif ($row['status'] == 1) $statusDisplay = '<span class="status-dot dot-pending"></span> Queued';
                                            else $statusDisplay = '<span class="status-dot dot-pending"></span> Pending arrival';

                                            // Exact Timestamps formatting
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
                                            <strong style="color: var(--primary-green); font-size: 1.05rem;"><?php echo $display_name; ?></strong><br>
                                            <span class="text-muted" style="font-size: 0.75rem;">Trk: <?php echo htmlspecialchars($row['tracking_no']); ?></span>
                                            <?php echo $priority_badge; ?>
                                            <?php if (!empty($row['has_pwd_id'])): ?><br><a class="text-success" href="../pwd-id.php?id=<?php echo (int)$row['student_id']; ?>" target="_blank" rel="noopener"><i class="ti-id-badge"></i> View PWD ID</a><?php endif; ?>
                                        </td>
                                        <td>
                                            <strong class="text-dark"><?php echo htmlspecialchars($row['office_name']); ?></strong>
                                        </td>
                                        <td><strong><?php echo $statusDisplay; ?></strong></td>
                                        <td>
                                            <small class="text-muted d-block"><i class="ti-alarm-clock"></i> Sched: <strong><?php echo $schedTime; ?></strong></small>
                                            <small class="text-muted d-block"><i class="ti-arrow-down"></i> Queued: <strong><?php echo $timeQueued; ?></strong></small>
                                            <small class="text-muted d-block"><i class="ti-check"></i> Done: <strong><?php echo $timeCompleted; ?></strong></small>
                                        </td>
                                        <td style="max-width: 250px;">
                                            <?php if (!empty($row['rescheduled_tracking_no'])): ?>
                                                <p class="small mb-2">Replacement booking: <strong><?php echo htmlspecialchars($row['rescheduled_tracking_no'], ENT_QUOTES, 'UTF-8'); ?></strong></p>
                                            <?php endif; ?>
                                            <?php if (!empty($row['office_notes'])): ?>
                                                <div class="notes-box"><?php echo nl2br(htmlspecialchars($row['office_notes'])); ?></div>
                                            <?php else: ?>
                                                <span class="text-muted" style="font-size: 0.8rem; font-style: italic;">No notes added.</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
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
    <script src="../dashboard/assets/js/jquery.dataTables.min.js"></script>
    <script src="../dashboard/assets/js/dataTables.bootstrap4.min.js"></script>
    
    <script>
        $(document).ready(function() { 
            $('#table').DataTable({
                "order": [], // Let the SQL query handle the initial sort
                "language": {
                    "emptyTable": "No appointment records found for this filter combination."
                }
            }); 
        });
    </script>
</body>
</html>
