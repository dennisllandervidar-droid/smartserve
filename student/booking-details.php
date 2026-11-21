<?php
    ob_start();
    include('../global/model.php');
    $model = new Model();
    $faqs = $model->displayFaqs(1);
    include('main/department.php');

    if ((int)($_SESSION['access'] ?? 0) !== 3) {
        http_response_code(403);
        exit;
    }

    // Verify User is logged in and ID is passed
    if (!isset($_SESSION['sess']) || !isset($_GET['id'])) {
        echo "<script>window.location.href='appointments';</script>";
        exit;
    }

    $tracking_no = $_GET['id'];
    $student_id = $_SESSION['sess'];

    // --- HANDLE CANCELLATION REQUEST ---
    if (isset($_POST['cancel_appointment'])) {
        $model->autoCancelExpiredAppointments();
        if ($model->cancelAppointment($tracking_no, $student_id)) {
            echo "<script>window.location.href='booking-details?id=$tracking_no';</script>";
            exit;
        } else {
            smartserve_notify('Error: Unable to cancel this appointment. It may have already been processed.', 'danger');
        }
    }

    // Fetch the details
    $booking = $model->getAppointmentDetails($tracking_no, $student_id);
    $appointment_version = hash('sha256', json_encode($booking));

    // Only return the current student's version; never cache authenticated status checks.
    if (isset($_GET['appointment_status'])) {
        ob_clean();
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store, private');
        http_response_code(empty($booking) ? 404 : 200);
        echo json_encode(empty($booking) ? ['error' => 'Appointment not found.'] : ['version' => $appointment_version]);
        exit;
    }

    // If booking doesn't exist or doesn't belong to this student, kick them out
    if (empty($booking)) {
        smartserve_notify('Invalid or missing appointment record.', 'danger', 'appointments');
        exit;
    }

    require_once __DIR__ . '/partials/service-progress.php';
    $serviceSummary = smartserve_student_service_summary($booking);

    // Set Status Badge formatting
    $status_badge = '<span class="ss-ticket-status ss-ticket-' . $serviceSummary['bucket'] . '">' . $serviceSummary['label'] . '</span>';

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
    <title><?php echo $web_name; ?> | Booking Details</title>

    <link rel="stylesheet" type="text/css" href="../dashboard/assets/css/assets.css">
    <link rel="stylesheet" type="text/css" href="../dashboard/assets/css/typography.css">
    <link rel="stylesheet" type="text/css" href="../dashboard/assets/css/style.css">
    <link rel="stylesheet" type="text/css" href="../dashboard/assets/css/dashboard.css">
    
    <style>
        :root {
            --primary-green: <?php echo $primary_color; ?>;
            --soft-green: #eef7f1;
            --text-dark: #2d3436;
            --danger-red: #dc3545;
        }

        body, .ttr-wrapper { background-color: #f4f7f6 !important; font-family: 'Inter', sans-serif; }

        /* --- Header Banner --- */
        .welcome-banner {
            background: #ffffff;
            border-radius: 20px;
            padding: 30px 40px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.03);
            border-left: 6px solid var(--primary-green);
            margin-bottom: 30px;
        }
        .welcome-banner h3 { margin: 0; font-size: 1.8rem; font-weight: 800; color: var(--text-dark); letter-spacing: -0.5px; }
        .welcome-banner p { margin: 5px 0 0 0; color: #636e72; font-size: 1.05rem; }

        /* --- Ticket Layout --- */
        .ticket-card {
            background: #fff;
            border-radius: 20px;
            box-shadow: 0 15px 40px rgba(0,0,0,0.08);
            overflow: hidden;
            border: 1px solid rgba(0,0,0,0.05);
            display: flex;
            flex-wrap: wrap;
            position: relative;
        }

        /* Left Side: QR Code Area */
        .ticket-left {
            background: linear-gradient(135deg, var(--soft-green) 0%, #ffffff 100%);
            padding: 70px 30px 50px 30px; /* Added extra top padding to clear the back button */
            text-align: center;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            border-right: 2px dashed #d1e8da;
            flex: 1 1 300px;
            position: relative;
        }

        /* Custom Anchored Back Button */
        .btn-back-nav {
            position: absolute;
            top: 20px;
            left: 20px;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            color: #636e72;
            font-weight: 600;
            text-decoration: none;
            transition: 0.3s;
            background: #fff;
            padding: 8px 16px;
            border-radius: 30px;
            box-shadow: 0 4px 10px rgba(0,0,0,0.05);
            border: 1px solid #eee;
            font-size: 0.85rem;
            z-index: 10;
        }
        .btn-back-nav:hover {
            color: var(--primary-green);
            transform: translateX(-4px);
            box-shadow: 0 4px 15px rgba(0,0,0,0.08);
            text-decoration: none;
        }

        /* Creating the perforated ticket edge effect */
        .ticket-left::before, .ticket-left::after {
            content: '';
            position: absolute;
            right: -15px;
            width: 30px;
            height: 30px;
            background: #f4f7f6; /* Match body background */
            border-radius: 50%;
            z-index: 1;
        }
        .ticket-left::before { top: -15px; }
        .ticket-left::after { bottom: -15px; }

        .qr-wrapper {
            background: #fff;
            padding: 15px;
            border-radius: 15px;
            box-shadow: 0 10px 25px rgba(0,0,0,0.08);
            margin-bottom: 20px;
            display: inline-block;
            /* If cancelled, dim the QR code */
            <?php echo (!$serviceSummary['active']) ? 'opacity: 0.4; filter: grayscale(100%);' : ''; ?>
        }

        .qr-wrapper img {
            display: block;
            width: 180px;
            height: 180px;
        }

        .tracking-number {
            font-size: 2rem;
            font-weight: 900;
            color: var(--primary-green);
            letter-spacing: 1px;
            margin: 0;
            line-height: 1;
        }

        .priority-pill {
            background: #ffebee;
            color: var(--danger-red);
            border: 1px solid #ffcdd2;
            padding: 5px 15px;
            border-radius: 20px;
            font-weight: 700;
            font-size: 0.8rem;
            text-transform: uppercase;
            letter-spacing: 1px;
            display: inline-flex;
            align-items: center;
            gap: 5px;
            margin-top: 15px;
        }

        /* Right Side: Details Area */
        .ticket-right {
            padding: 40px 50px;
            flex: 2 1 450px;
            background: #fff;
            display: flex;
            flex-direction: column;
        }

        .detail-group { margin-bottom: 25px; }
        
        .detail-label {
            font-size: 0.8rem;
            text-transform: uppercase;
            font-weight: 700;
            color: #a0aab2;
            letter-spacing: 0.5px;
            display: block;
            margin-bottom: 8px;
        }

        .detail-value {
            font-size: 1.1rem;
            font-weight: 700;
            color: var(--text-dark);
        }

        .notes-box {
            background: #f8f9fa; 
            border-radius: 15px; 
            border: 1px solid #f1f1f1;
            padding: 20px;
            color: #4a5568; 
            font-size: 0.95rem; 
            line-height: 1.6;
        }

        /* Action Buttons */
        .btn-cancel {
            border: 2px solid #f8d7da;
            color: var(--danger-red);
            background: #fdf2f2;
            padding: 10px 25px;
            border-radius: 30px;
            font-weight: 700;
            transition: 0.3s;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }
        .btn-cancel:hover { background: var(--danger-red); color: #fff; border-color: var(--danger-red); transform: translateY(-2px); }

        @media print {
            body * { visibility: hidden; }
            .ticket-card, .ticket-card * { visibility: visible; }
            .ticket-card { position: absolute; left: 0; top: 0; width: 100%; box-shadow: none; border: 1px solid #ddd; }
            .btn-cancel, .welcome-banner, .db-breadcrumb, .ttr-sidebar, .alert, .btn-back-nav { display: none !important; }
        }
    </style>
</head>

<body class="ttr-opened-sidebar ttr-pinned-sidebar">
    <?php
        $page = 'appointments';
        $secondnav = 'history'; 
        include 'main/admin-nav.php'; 
    ?>

    <main class="ttr-wrapper" data-ss-appointment-version="<?php echo $appointment_version; ?>">
        <div class="container-fluid">
            
            <div class="db-breadcrumb">
                <h4 class="breadcrumb-title"><i class="ti ti-calendar"></i> Appointments</h4>
                <ul class="db-breadcrumb-list">
                    <li>Booking Details</li>
                </ul>
            </div>
            
            <div class="row justify-content-center">
                <div class="col-lg-12 m-b30">

                    <?php if($booking['status'] == 0 && isset($_SERVER['HTTP_REFERER']) && strpos($_SERVER['HTTP_REFERER'], 'booking') !== false): ?>
                    <div class="alert alert-success alert-dismissible fade show mb-4" role="alert" style="border-radius: 15px; border:none; background:#d4edda; color:#155724; padding: 20px; box-shadow: 0 5px 15px rgba(0,0,0,0.05);">
                        <div class="d-flex align-items-center">
                            <i class="ti-check-box mr-3" style="font-size: 1.5rem;"></i>
                            <div>
                                <strong class="d-block mb-1">Appointment Request Submitted!</strong>
                                <span>Scan your QR at each office to check in for that service.</span>
                            </div>
                        </div>
                        <button type="button" class="close mt-1" data-dismiss="alert" aria-label="Close">
                            <span aria-hidden="true">&times;</span>
                        </button>
                    </div>
                    <?php endif; ?>

                    <?php if($serviceSummary['bucket'] === 'cancelled'): ?>
                    <div class="alert alert-danger mb-4" role="alert" style="border-radius: 15px; border:none; background:#f8d7da; color:#721c24; padding: 20px;">
                        <div class="d-flex align-items-center">
                            <i class="ti-na mr-3" style="font-size: 1.5rem;"></i>
                            <div>
                                <strong class="d-block mb-1">Appointment Cancelled</strong>
                                <span>This appointment request is no longer valid.</span>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>

                    <div class="ticket-card">
                        
                        <div class="ticket-left">
                            <a href="history" class="btn-back-nav">
                                <i class="ti-arrow-left"></i> Back to History
                            </a>

                            <div class="qr-wrapper">
                                <img src="https://api.qrserver.com/v1/create-qr-code/?size=200x200&data=<?php echo urlencode($booking['tracking_no']); ?>" alt="QR Code">
                            </div>
                            <h4 class="tracking-number">
                                <?php echo htmlspecialchars($booking['tracking_no']); ?>
                            </h4>
                            <p class="text-muted mt-2 mb-0" style="font-size: 0.9rem; font-weight: 500;">
                                <?php echo !$serviceSummary['active'] ? 'Closed ticket — see each service below' : 'Scan separately for each service'; ?>
                            </p>
                            
                            <?php if (isset($booking['is_priority']) && $booking['is_priority'] == 1): ?>
                                <div class="priority-pill">
                                    <i class="ti-star"></i> <?php echo htmlspecialchars($booking['priority'] ?? 'Priority'); ?>
                                </div>
                            <?php endif; ?>
                        </div>

                        <div class="ticket-right">
                            <div class="d-flex justify-content-between align-items-center mb-4 pb-3" style="border-bottom: 2px solid #f8f9fa;">
                                <h3 class="m-0 fw-bold" style="color: var(--text-dark);">Appointment Details</h3>
                                <?php echo $status_badge; ?>
                            </div>

                            <?php if (!empty($booking['rescheduled_from_tracking_no'])): ?>
                            <div class="ss-service-origin">This is a new ticket for your rescheduled service. Use this QR for the new schedule. <a href="booking-details?id=<?php echo rawurlencode($booking['rescheduled_from_tracking_no']); ?>">View original booking</a></div>
                            <?php endif; ?>
                            <div class="row flex-grow-1">
                                <div class="col-md-12 detail-group">
                                    <span class="detail-label">Progress by Service</span>
                                    <?php smartserve_render_service_progress($booking); ?>
                                </div>

                                <div class="col-md-6 detail-group">
                                    <span class="detail-label"><i class="ti-calendar"></i> Appointment Date</span>
                                    <span class="detail-value" style="<?php echo ($booking['status'] == 3) ? 'text-decoration: line-through; color: #a0aec0;' : ''; ?>">
                                        <?php echo date('l, F d, Y', strtotime($booking['appointment_date'])); ?>
                                    </span>
                                </div>
                                
                                <div class="col-md-6 detail-group">
                                    <span class="detail-label"><i class="ti-time"></i> Preferred Time Slot</span>
                                    <span class="detail-value" style="<?php echo ($booking['status'] == 3) ? 'text-decoration: line-through; color: #a0aec0;' : ''; ?>">
                                        <?php echo htmlspecialchars($booking['appointment_time']); ?>
                                    </span>
                                </div>

                                <div class="col-md-12 detail-group">
                                    <span class="detail-label">Purpose / Notes</span>
                                    <div class="notes-box">
                                        <?php echo nl2br(htmlspecialchars($booking['notes'])); ?>
                                    </div>
                                </div>
                                
                                <div class="col-md-12 mt-4 pt-4 border-top d-flex justify-content-between align-items-center flex-wrap gap-3">
                                    <small class="text-muted font-weight-bold">Requested on: <?php echo date('M d, Y h:i A', strtotime($booking['date_created'])); ?></small>
                                    
                                    <div class="action-buttons">
                                        <?php if($serviceSummary['active'] > 0 && $serviceSummary['serving'] === 0): ?>
                                            <form method="POST" class="m-0" data-ss-confirm="Cancel all remaining services on this ticket? Completed services will remain in your history. This action cannot be undone.">
                                                <button type="submit" name="cancel_appointment" class="btn-cancel">
                                                    <i class="ti-close"></i> Cancel Remaining Services
                                                </button>
                                            </form>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                
                                <div class="col-md-12 mt-4 pt-4 border-top d-flex justify-content-between align-items-center flex-wrap gap-3">
                                    <small class="text-muted"><i>Check in separately at each office within <?php echo SmartServeAppointmentRules::ARRIVAL_GRACE_MINUTES; ?> minutes of the scheduled time. The deadline applies to each service. A blocked service awaiting your response is held for rescheduling; a rescheduled service has its own new ticket and deadline.</i></small>
                                </div>
                                
                            </div>
                        </div>

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
    <script src="../assets/js/appointment-live.js?v=<?php echo filemtime(__DIR__ . '/../assets/js/appointment-live.js'); ?>"></script>
</body>
</html>
