<?php
    ob_start();
    include('../global/model.php');
    if (!isset($model) || !($model instanceof Model)) {
        $model = new Model();
    }
    $faqs = $model->displayFaqs(1);
        include('main/department.php');

    if ((int)($_SESSION['access'] ?? 0) !== 3) {
        http_response_code(403);
        exit;
    }

    // Verify User is logged in
    if (!isset($_SESSION['sess'])) {
        echo "<script>window.location.href='index';</script>";
        exit;
    }

    $student_id = $_SESSION['sess'];

    $rows = $model->website_details();
    $web_name = "De La Salle Araneta University";
    $primary_color = "#087830"; 
    $secondary_color = "#14a44d";

    if (!empty($rows)) {
        foreach ($rows as $row) {
            $web_name = $row['web_name'];
            $primary_color = $row['primary_color'];
            $secondary_color = $row['secondary_color'];
        }
    }

    // Helper function for relative time
    if (!function_exists('timeAgo')) {
        function timeAgo($datetime) {
            $timestamp = is_numeric($datetime) ? $datetime : strtotime($datetime);
            $diff = time() - $timestamp;
            if ($diff < 60) return "Just now";
            elseif ($diff < 3600) return floor($diff / 60) . " mins ago";
            elseif ($diff < 86400) return floor($diff / 3600) . " hours ago";
            elseif ($diff < 2592000) return floor($diff / 86400) . " days ago";
            else return date('M d, Y', $timestamp);
        }
    }

    require_once __DIR__ . '/partials/service-progress.php';

    // Fetch Appointments & Calculate Counts
    $appointments = $model->getStudentAppointments($student_id);
    $student_blocked_rows = $model->getStudentBlockedAppointments((int)$student_id);
    $blockedTrackingNumbers = array_fill_keys(array_column($student_blocked_rows, 'tracking_no'), true);
    $appointment_version = hash('sha256', json_encode([$appointments, $student_blocked_rows]));
    if (isset($_GET['appointment_status'])) {
        ob_clean();
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store, private');
        echo json_encode(['version' => $appointment_version]);
        exit;
    }
    
    $countAll = count($appointments);
    $filterLabels = ['pending'=>'Pending','progress'=>'In Progress','completed'=>'Completed','partial'=>'Partially completed','rescheduled'=>'Rescheduled','cancelled'=>'Cancelled'];
    $filterCounts = array_fill_keys(array_keys($filterLabels), 0);
    foreach ($appointments as $appt) {
        $filterCounts[smartserve_student_service_summary($appt)['bucket']]++;
    }
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <?php require_once __DIR__ . '/../global/form-assets.php'; ?>
    <link rel="stylesheet" href="../assets/css/appointment-services.css?v=<?php echo filemtime(__DIR__ . '/../assets/css/appointment-services.css'); ?>">
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="icon" href="../assets/img/<?php echo $web_icon; ?>.png" type="image/x-icon" />
    <title><?php echo $web_name; ?> | Appointment History</title>

    <link rel="stylesheet" type="text/css" href="../dashboard/assets/css/assets.css">
    <link rel="stylesheet" type="text/css" href="../dashboard/assets/css/typography.css">
    <link rel="stylesheet" type="text/css" href="../dashboard/assets/css/style.css">
    <link rel="stylesheet" type="text/css" href="../dashboard/assets/css/dashboard.css">
    
    <style>
        :root {
            --primary-green: <?php echo $primary_color; ?>;
            --soft-green: #eef7f1;
        }

        /* --- Filter Pills --- */
        .filter-container {
            display: flex;
            gap: 12px;
            overflow-x: auto;
            padding-bottom: 10px;
            margin-bottom: 20px;
            scrollbar-width: none; /* Firefox */
        }
        .filter-container::-webkit-scrollbar { display: none; } /* Chrome */

        .filter-pill {
            background: #fff;
            border: 1px solid #e1e5eb;
            color: #6c757d;
            padding: 10px 22px;
            border-radius: 30px;
            font-weight: 600;
            font-size: 0.95rem;
            cursor: pointer;
            transition: all 0.3s ease;
            white-space: nowrap;
            display: flex;
            align-items: center;
            gap: 8px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.02);
        }

        .filter-pill:hover {
            border-color: var(--primary-green);
            color: var(--primary-green);
        }

        .filter-pill.active {
            background: var(--primary-green);
            color: #fff;
            border-color: var(--primary-green);
            box-shadow: 0 5px 15px rgba(8, 120, 48, 0.2);
        }

        .badge-count {
            background: #f0f2f5;
            color: #4a5568;
            padding: 2px 8px;
            border-radius: 12px;
            font-size: 0.8rem;
            font-weight: 700;
            transition: 0.3s;
        }

        .filter-pill.active .badge-count {
            background: rgba(255,255,255,0.2);
            color: #fff;
        }

        /* --- History Cards --- */
        .history-card {
            background: #fff;
            border-radius: 16px;
            padding: 25px;
            border: 1px solid #eee;
            box-shadow: 0 5px 15px rgba(0,0,0,0.02);
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 15px;
        }

        .history-card:hover {
            box-shadow: 0 10px 25px rgba(0,0,0,0.06);
            transform: translateY(-3px);
            border-color: var(--soft-green);
        }

        .card-icon {
            width: 50px;
            height: 50px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 20px;
            flex-shrink: 0;
        }
        
        .icon-pending { background: #fff3cd; color: #856404; }
        .icon-approved { background: #d4edda; color: #155724; }
        .icon-completed { background: #cce5ff; color: #004085; }
        .icon-cancelled { background: #f8d7da; color: #721c24; }

        .appt-details { flex-grow: 1; margin: 0 20px; }
        .appt-title { font-weight: 700; color: #2d3436; font-size: 1.1rem; margin-bottom: 4px; }
        .appt-meta { color: #6c757d; font-size: 0.9rem; }
        
        .btn-view-ticket {
            border: 2px solid var(--primary-green);
            color: var(--primary-green);
            background: transparent;
            padding: 8px 20px;
            border-radius: 10px;
            font-weight: 600;
            transition: 0.3s;
            text-decoration: none;
            white-space: nowrap;
        }
        .btn-view-ticket:hover {
            background: var(--primary-green);
            color: #fff;
            text-decoration: none;
        }

        @media (max-width: 768px) {
            .history-card { flex-direction: column; align-items: flex-start; gap: 15px; }
            .appt-details { margin: 0; }
            .btn-view-ticket { width: 100%; text-align: center; }
        }
    </style>
</head>

<body class="ttr-opened-sidebar ttr-pinned-sidebar" style="background-color: #FCFCFC;">
    <?php
        $page = 'appointments';
        $secondnav = 'history'; 
        include 'main/admin-nav.php'; 
    ?>

    <main class="ttr-wrapper" style="background-color: #FCFCFC;" data-ss-appointment-version="<?php echo $appointment_version; ?>">
        <div class="container-fluid">
            <div class="db-breadcrumb">
                <h4 class="breadcrumb-title"><i class="ti ti-calendar"></i> Appointments</h4>
                <ul class="db-breadcrumb-list">
                    <li>History</li>
                </ul>
            </div>
            
            <div class="row justify-content-center">
                <div class="col-lg-12 m-b30">
                    <?php require __DIR__ . '/partials/blocked-appointment-notices.php'; ?>
                    
                    <div class="filter-container">
                        <div class="filter-pill active" data-filter="all">
                            All <span class="badge-count"><?php echo $countAll; ?></span>
                        </div>
                        <?php foreach ($filterLabels as $filterKey => $filterLabel): ?>
                        <div class="filter-pill" data-filter="<?php echo $filterKey; ?>">
                            <?php echo $filterLabel; ?> <span class="badge-count"><?php echo $filterCounts[$filterKey]; ?></span>
                        </div>
                        <?php endforeach; ?>
                    </div>

                    <div id="appointment-list">
                        <?php
                            if (empty($appointments)) {
                                echo '<div class="text-center py-5 text-muted">
                                        <i class="ti-calendar" style="font-size: 40px; opacity: 0.5;"></i>
                                        <h5 class="mt-3">No appointments found.</h5>
                                        <p>You haven\'t made any appointment requests yet.</p>
                                      </div>';
                            } else {
                                foreach ($appointments as $appt) {
                                    $summary = smartserve_student_service_summary($appt);
                                    $status = $summary['bucket'];
                                    $statusText = $summary['label'];
                                    $iconClass = 'ss-ticket-' . $status;
                                    $iconHtml = '<i class="ti-calendar"></i>';
                        ?>
                        
                        <div class="history-card appt-item" data-status="<?php echo $status; ?>">
                            <div class="card-icon <?php echo $iconClass; ?>">
                                <?php echo $iconHtml; ?>
                            </div>
                            
                            <div class="appt-details">
                                <div class="appt-title">
                                    <?php 
                                        // Display office names directly using the new GROUP_CONCAT from getStudentAppointments
                                        echo htmlspecialchars($appt['office_names'] ?? 'N/A', ENT_QUOTES, 'UTF-8');
                                    ?> 
                                    <span style="font-size: 0.9rem; font-weight: normal; color: #a0aec0;">(#<?php echo htmlspecialchars($appt['tracking_no']); ?>)</span>
                                </div>
                                <div class="appt-meta">
                                    <i class="ti-calendar"></i> <?php echo date('M d, Y', strtotime($appt['appointment_date'])); ?> &nbsp;&bull;&nbsp; 
                                    <i class="ti-time"></i> <?php echo htmlspecialchars($appt['appointment_time']); ?>
                                </div>
                                <div class="appt-meta mt-1" style="font-size: 0.8rem;">
                                    <strong>Status:</strong> <?php echo $statusText; ?> &nbsp;|&nbsp; 
                                    <em>Requested <?php echo timeAgo($appt['date_created']); ?></em>
                                </div>
                                <?php smartserve_render_service_progress($appt, true); ?>
                                <?php if (isset($blockedTrackingNumbers[$appt['tracking_no']])): ?>
                                <div class="ss-blocked-status">Schedule blocked &mdash; action required</div>
                                <?php endif; ?>
                            </div>

                            <div>
                                <a href="booking-details?id=<?php echo rawurlencode($appt['tracking_no']); ?>" class="btn-view-ticket">
                                    <i class="ti-search"></i> View Details
                                </a>
                            </div>
                        </div>

                        <?php 
                                }
                            } 
                        ?>
                    </div>

                    <div id="no-results" class="text-center py-5 text-muted" style="display: none;">
                        <i class="ti-search" style="font-size: 40px; opacity: 0.5;"></i>
                        <h5 class="mt-3">No records found.</h5>
                        <p>There are no appointments matching this filter.</p>
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
            // Vanilla JS / jQuery to handle smooth filtering
            $('.filter-pill').on('click', function() {
                // Update active state
                $('.filter-pill').removeClass('active');
                $(this).addClass('active');

                var filterValue = $(this).data('filter');
                // Keep the selected filter when a changed appointment refreshes this page.
                var filterUrl = new URL(window.location.href);
                if (filterValue === 'all') filterUrl.searchParams.delete('view');
                else filterUrl.searchParams.set('view', String(filterValue));
                filterUrl.searchParams.delete('status');
                window.history.replaceState(null, '', filterUrl);
                var visibleCount = 0;

                // Show/Hide logic
                if (filterValue === 'all') {
                    $('.appt-item').fadeIn(200);
                    visibleCount = $('.appt-item').length;
                } else {
                    $('.appt-item').hide(); // Instantly hide all
                    var $matchingItems = $('.appt-item[data-status="' + filterValue + '"]');
                    $matchingItems.fadeIn(200); // Fade in matches
                    visibleCount = $matchingItems.length;
                }

                // Show "No Results" message if empty
                if (visibleCount === 0 && $('.appt-item').length > 0) {
                    $('#no-results').fadeIn(200);
                } else {
                    $('#no-results').hide();
                }
            });
            var filterParams = new URL(window.location.href).searchParams;
            var selectedFilter = filterParams.get('view') || ({'0':'pending','1':'progress','2':'completed','3':'cancelled'}[filterParams.get('status')]);
            if (['pending','progress','completed','partial','rescheduled','cancelled'].indexOf(selectedFilter) !== -1) {
                $('.filter-pill[data-filter="' + selectedFilter + '"]').trigger('click');
            }
        });
    </script>
    <script src="../assets/js/appointment-live.js?v=<?php echo filemtime(__DIR__ . '/../assets/js/appointment-live.js'); ?>"></script>
</body>
</html>
