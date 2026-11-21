<?php
    ob_start();
    require_once __DIR__ . '/global/kiosk-settings.php';
    try {
        $kioskHours = smartserve_kiosk_hours(smartserve_load_kiosk_config());
    } catch (Throwable $e) {
        error_log('SmartServe kiosk configuration is invalid: ' . $e->getMessage());
        $kioskHours = ['is_open'=>false, 'testing_enabled'=>false, 'hours_label'=>'Temporarily unavailable', 'timezone'=>'Asia/Manila'];
    }
    $is_business_hours = $kioskHours['is_open'];

    // Enforce the same schedule for direct requests and a kiosk page left open.
    if (!$is_business_hours && in_array($_POST['action'] ?? '', ['verify_qr', 'submit_walkin', 'check_purpose_block'], true)) {
        while (ob_get_level()) ob_end_clean();
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store');
        $closedMessage = 'The kiosk is currently closed. Kiosk hours: ' . $kioskHours['hours_label'] . '.';
        echo json_encode(['status'=>'error', 'msg'=>$closedMessage, 'errors'=>['kiosk'=>$closedMessage], 'blocked'=>true, 'message'=>$closedMessage]);
        exit;
    }

    include('global/model.php');
    $model = new Model();
    session_start();

    date_default_timezone_set($kioskHours['timezone']);
    $today_date = date('Y-m-d');
    $current_time_display = date('h:i A');

    // Fail-safe wrapper: if block schedule query throws, kiosk still loads.
    $safeGetSlotBlockInfo = function($office_id) use ($model, $today_date, $current_time_display) {
        try {
            return $model->getSlotBlockInfo((int)$office_id, $today_date, $current_time_display);
        } catch (Throwable $e) {
            error_log('Kiosk block lookup failed: ' . $e->getMessage());
            return null;
        }
    };

    // --------------------------------------------------------
    // AJAX HANDLER: VERIFY QR CODE
    // --------------------------------------------------------
    if (isset($_POST['action']) && $_POST['action'] == 'verify_qr') {
        while (ob_get_level()) ob_end_clean(); 
        header('Content-Type: application/json');
        
        $tracking_no = trim($_POST['tracking_no']);
        $office_id = (int)$_POST['office_id'];
        
        // Check if office is blocked
        $block = $safeGetSlotBlockInfo($office_id);
        if (!empty($block)) {
            echo json_encode([
                'status' => 'error',
                'msg' => 'This office is currently blocked: ' . ($block['reason'] ?? 'Unavailable') . 
                         (!empty($block['note']) ? ' (' . $block['note'] . ')' : '')
            ]);
            exit;
        }
        
        $result = $model->verifyQRCode($tracking_no, $office_id);
        
        echo json_encode($result);
        exit;
    }

    if (isset($_POST['action']) && $_POST['action'] == 'check_purpose_block') {
        while (ob_get_level()) ob_end_clean();
        header('Content-Type: application/json');

        $office_id = (int)($_POST['office_id'] ?? 0);
        $purpose = trim($_POST['purpose'] ?? '');

        if (empty($office_id) || empty($purpose) || $purpose === 'Others') {
            echo json_encode(['blocked' => false]);
            exit;
        }

        $block = $model->getPurposeBlockInfo($office_id, $today_date, $purpose);
        if (!empty($block)) {
            $message = trim(($block['reason'] ?? 'This purpose is unavailable today.'));
            if (!empty($block['note'])) {
                $message .= ', ' . trim($block['note']);
            }
            echo json_encode(['blocked' => true, 'message' => $message]);
        } else {
            echo json_encode(['blocked' => false]);
        }
        exit;
    }
        

    // --------------------------------------------------------
    // AJAX HANDLER: GUEST WALK-IN
    // --------------------------------------------------------
    if (isset($_POST['action']) && $_POST['action'] == 'submit_walkin') {
        while (ob_get_level()) ob_end_clean();
        header('Content-Type: application/json');
        
        $guest_name = trim($_POST['guest_name']);
        $office_id = (int)$_POST['office_id'];
        $priority = trim($_POST['priority'] ?? 'N/A');
        $valid_priorities = ['N/A', 'PWD', 'Senior Citizen', 'Pregnant'];
        if (!in_array($priority, $valid_priorities, true)) {
            $priority = 'N/A';
        }
        $is_priority = ($priority === 'N/A') ? 0 : 1;

        $purpose_option = trim($_POST['purpose_option'] ?? '');
        $custom_purpose = trim($_POST['custom_purpose'] ?? '');
        $notes = ($purpose_option === 'Others') ? $custom_purpose : $purpose_option;

        $purpose_block = null;
        if (!empty($notes) && $notes !== 'Others') {
            $purpose_block = $model->getPurposeBlockInfo($office_id, $today_date, $notes);
        }
        
        if (!SmartServeValidation::name($guest_name, true, 100)) {
            echo json_encode(['status' => 'error', 'msg' => 'Invalid name format. Only letters, spaces, and hyphens are allowed.']);
            exit;
        }

        if(empty($guest_name) || empty($office_id)) {
            echo json_encode(['status' => 'error', 'msg' => 'Missing required fields.']);
            exit;
        }

        if (empty($notes)) {
            echo json_encode(['status' => 'error', 'msg' => 'Please select a purpose. If you chose Others, type your purpose.']);
            exit;
        }

        if (!empty($purpose_block)) {
            $message = trim(($purpose_block['reason'] ?? 'This purpose is unavailable today.'));
            if (!empty($purpose_block['note'])) {
                $message .= ', ' . trim($purpose_block['note']);
            }
            echo json_encode(['status' => 'error', 'msg' => $message]);
            exit;
        }

        // Check if office is blocked
        $block = $safeGetSlotBlockInfo($office_id);
        if (!empty($block)) {
            echo json_encode([
                'status' => 'error',
                'msg' => 'This office is currently blocked: ' . ($block['reason'] ?? 'Unavailable') . 
                         (!empty($block['note']) ? ' (' . $block['note'] . ')' : '')
            ]);
            exit;
        }

        $result = $model->addWalkInAppointment(NULL, $guest_name, $office_id, $notes, $is_priority, $priority);
        
        if ($result['status'] == 'success') {
            echo json_encode([
                'status' => 'success', 
                'queue_number' => $result['queue_number'],
                'msg' => 'Walk-in Registered!'
            ]);
        } else {
            echo json_encode(['status' => 'error', 'msg' => $result['msg']]);
        }
        exit;
    }

    // --- Fetch Website Details & Offices ---
    $rows = $model->website_details();
    $web_name = "De La Salle Araneta University";
    $primary_color = "#005141"; 

    if (!empty($rows)) {
        foreach ($rows as $row) {
            $web_name = $row['web_name'];
            $primary_color = $row['primary_color'];
        }
    }
    
    $offices = $model->getOffices();
    
    // Build block map for all offices
    $office_block_map = [];
    if (!empty($offices)) {
        foreach ($offices as $office) {
            $oid = (int)$office['id'];
            $block = $safeGetSlotBlockInfo($oid);
            $office_block_map[$oid] = [
                'blocked' => !empty($block),
                'reason' => !empty($block) ? ($block['reason'] ?? 'Unavailable') : '',
                'note' => !empty($block) ? ($block['note'] ?? '') : ''
            ];
        }
    }
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <?php require_once __DIR__ . '/global/form-assets.php'; ?>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no">
    <title>SmartServe Kiosk</title>
    <link rel="icon" href="assets/img/icon.png" type="image/x-icon" />
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" type="text/css" href="dashboard/assets/css/typography.css">
    
    <script src="https://unpkg.com/lucide@latest"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="https://unpkg.com/html5-qrcode" type="text/javascript"></script>

    <style>
        :root {
            --primary: <?php echo $primary_color; ?>; 
            --bg-dark-green: #005141; 
            --bg-darker-green: #003d31;
            --text-dark: #2d3436;
            --text-muted: #636e72;
            --surface: #ffffff;
            --danger-red: #dc3545;
        }

        * { box-sizing: border-box; }

        body {
            font-family: 'Inter', 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, var(--bg-dark-green) 0%, var(--bg-darker-green) 100%);
            color: #ffffff;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            overflow-x: hidden;
            margin: 0;
        }

        .main-wrapper {
            flex: 1 0 auto; 
            display: flex;
            flex-direction: column;
            align-items: center;
            padding-bottom: 60px;
            width: 100%;
        }

        .modern-header {
            padding: 8vh 20px 5vh;
            text-align: center;
            width: 100%;
        }
        .modern-header img {
            width: 100px;
            filter: brightness(0) invert(1);
            margin-bottom: 10px;
        }
        .modern-header h1 {
            font-weight: 900;
            color: #ffffff; 
            font-size: clamp(2rem, 5vw, 3rem);
            letter-spacing: -0.5px;
            margin-bottom: 10px;
        }
        .modern-header p {
            color: rgba(255, 255, 255, 0.8); 
            font-size: 1.1rem;
            font-weight: 500;
        }

        .directory-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 350px)); 
            justify-content: center;
            gap: 30px;
            width: 100%;
            max-width: 1200px;
            padding: 0 20px;
        }

        .office-card {
            background: var(--surface);
            border-radius: 20px;
            padding: 35px 30px;
            text-decoration: none !important;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            border: 1px solid rgba(255,255,255,0.1);
            box-shadow: 0 15px 35px rgba(0,0,0,0.25);
            transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
            position: relative;
            overflow: hidden;
            min-height: 180px;
            cursor: pointer;
            text-align: left;
        }

        .office-card::before {
            content: '';
            position: absolute;
            top: 0; left: 0; right: 0; 
            height: 5px;
            background: var(--primary);
            transform: scaleX(0);
            transform-origin: left;
            transition: transform 0.4s ease;
        }

        .office-card:hover {
            transform: translateY(-8px);
            box-shadow: 0 25px 50px rgba(0,0,0,0.4);
        }
        .office-card:hover::before { transform: scaleX(1); }

        .office-meta {
            color: var(--text-muted);
            font-size: 0.8rem;
            text-transform: uppercase;
            letter-spacing: 1.5px;
            font-weight: 700;
            margin-bottom: 5px;
        }
        .office-name {
            font-size: 1.5rem;
            font-weight: 800;
            color: var(--text-dark);
            line-height: 1.3;
            margin-bottom: 8px;
        }

        .card-action {
            margin-top: 30px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            color: var(--bg-dark-green);
            font-weight: 800;
            font-size: 0.9rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .card-action i {
            transition: transform 0.3s ease;
        }
        .office-card:hover .card-action i { transform: translateX(6px); }

        #action-area { 
            display: none; 
            width: 100%; 
            max-width: 1100px; 
            padding: 0 20px; 
            margin: 0 auto; 
        }
        
        .action-container {
            background: transparent;
            border-radius: 20px;
            color: var(--text-dark);
        }

        .panel-card {
            background: var(--surface);
            border-radius: 25px;
            padding: 40px 30px;
            height: 100%;
            box-shadow: 0 20px 40px rgba(0,0,0,0.2);
            border-top: 5px solid var(--primary);
            display: flex;
            flex-direction: column;
        }

        .action-title { 
            font-weight: 800; 
            color: var(--primary); 
            margin-bottom: 10px; 
            font-size: 1.6rem; 
            display: flex; 
            align-items: center; 
            gap: 12px; 
            font-family: 'Inter', 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif !important;
        }

        .destination-title {
            font-family: 'Inter', 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif !important;
            font-weight: 800;
            letter-spacing: 0.2px;
        }
        
        #reader { border: 2px dashed #b2d4c8 !important; border-radius: 20px; overflow: hidden; background: #f8fcf9; padding: 10px; }
        #reader img { display: none; } 
        #reader button { background: var(--primary); color: white; border: none; padding: 10px 20px; border-radius: 10px; font-weight: 600; margin-top: 10px; cursor: pointer; transition: 0.3s; }
        #reader button:hover { background: var(--bg-darker-green); }
        #reader span a { display: none; }

        .custom-input { 
            border-radius: 12px; border: 2px solid #e2e8f0; padding: 15px 20px; 
            width: 100%; outline: none; transition: 0.3s; background: #f8fafc;
            color: #334155; font-size: 1.1rem; font-weight: 500;
        }
        .custom-input:focus { border-color: var(--primary); background: #fff; box-shadow: 0 0 0 4px rgba(0, 81, 65, 0.1); }
        .custom-input.is-invalid { border-color: var(--danger-red); box-shadow: 0 0 0 4px rgba(220, 53, 69, 0.1); }
        
        .name-error-msg { color: var(--danger-red); font-size: 0.85rem; font-weight: 600; display: none; margin-top: 5px; }

        .btn-submit {
            background: var(--primary);
            color: white; border: none; padding: 16px; border-radius: 15px;
            font-weight: 700; font-size: 1.1rem; width: 100%; transition: 0.3s;
            margin-top: 20px; box-shadow: 0 5px 15px rgba(0, 81, 65, 0.2);
        }
        .btn-submit:hover { transform: translateY(-2px); background: var(--bg-darker-green); box-shadow: 0 8px 20px rgba(0, 81, 65, 0.3); }
        .btn-submit:disabled { opacity: 0.6; cursor: not-allowed; transform: none; }

        .btn-back {
            background: rgba(255,255,255,0.1); color: #fff; border: 1px solid rgba(255,255,255,0.2);
            padding: 10px 20px; border-radius: 30px; font-weight: 600; text-decoration: none;
            display: inline-flex; align-items: center; gap: 8px; transition: 0.3s; cursor: pointer;
        }
        .btn-back:hover { background: rgba(255,255,255,0.2); color: #fff; }

        .disclaimer-box {
            background: #fff0f0;
            border-left: 4px solid var(--danger-red);
            border-radius: 8px;
            padding: 10px 15px;
            margin-top: 15px;
        }
        
        .offline-box {
            background: rgba(255,255,255,0.1);
            border: 1px solid rgba(255,255,255,0.2);
            border-radius: 20px;
            padding: 50px 30px;
            text-align: center;
            max-width: 600px;
            margin: 0 auto;
            backdrop-filter: blur(10px);
        }

        .modern-footer {
            flex-shrink: 0; text-align: center; padding: 20px;
            color: rgba(255, 255, 255, 0.5); font-size: 0.85rem;
            border-top: 1px solid rgba(255, 255, 255, 0.05); width: 100%;
        }
    </style>
</head>
<body>

    <div class="main-wrapper">
        
        <header class="modern-header">
            <img src="assets/img/icon.png" alt="Logo" onerror="this.style.display='none'"><br>
            <span style="font-size: 45px;font-weight: 950;">SmartServe Kiosk</span>
            <?php if ($kioskHours['testing_enabled']): ?>
                <p style="color:#ffe39a; font-weight:600;">After-hours testing is enabled</p>
            <?php endif; ?>
            <?php if($is_business_hours): ?>
                <p id="header-subtitle">Select your destination department to begin</p>
            <?php endif; ?>
        </header>

        <?php if($is_business_hours): ?>
            <div id="selection-area" class="directory-grid">
                <?php
                    if (!empty($offices)) {
                        foreach ($offices as $off) {
                ?>
                <div class="office-card" onclick="selectOffice(<?php echo $off['id']; ?>, '<?php echo addslashes(htmlspecialchars($off['office_name'])); ?>')">
                    <div>
                        <div class="office-meta">Check-in Destination</div>
                        <div class="office-name"><?php echo htmlspecialchars($off['office_name']); ?></div>
                    </div>
                    <div class="card-action">
                        <span>Select Office</span>
                        <i data-lucide="arrow-right" style="width: 20px; height: 20px;"></i>
                    </div>
                </div>
                <?php
                        }
                    } else {
                        echo '<div style="grid-column: 1 / -1; text-align: center; padding: 40px; color: rgba(255,255,255,0.7);">
                                <h4 style="font-weight: 700; color: #ffffff;">No active departments found</h4>
                              </div>';
                    }
                ?>
            </div>

            <div id="action-area">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <button class="btn-back" onclick="goBack()"><i data-lucide="arrow-left" style="width:16px;height:16px;"></i> Back to Offices</button>
                    <h4 class="m-0 fw-bold text-white text-end destination-title" style="opacity: 0.9;">Destination: <span id="display-office-name" style="color: #ffffff;"></span></h4>
                </div>

                <div id="blockWarning" class="disclaimer-box" style="display: none; margin-bottom: 20px;">
                    <small style="color: var(--danger-red); font-weight: 700; display: flex; align-items: center; gap: 6px; line-height: 1.4;">
                        <i data-lucide="alert-circle" style="width: 16px; height: 16px; flex-shrink: 0;"></i> 
                        <span id="blockWarningText"></span>
                    </small>
                </div>

                <div class="action-container">
                    <div class="row g-4">
                        
                        <div class="col-lg-6">
                            <div class="panel-card text-center">
                                <h4 class="action-title justify-content-center"><i data-lucide="qr-code"></i> Check-in Appointment</h4>
                                <p class="text-muted mb-4" style="font-size: 0.95rem;">Show your digital ticket QR code to the camera below to verify your schedule.</p>
                                
                                <div id="reader" style="width: 100%; max-width: 320px; margin: 0 auto;"></div>
                            </div>
                        </div>

                        <div class="col-lg-6">
                            <div class="panel-card">
                                <h4 class="action-title"><i data-lucide="user-plus"></i> Walk-in Registration</h4>
                                <p class="text-muted mb-4" style="font-size: 0.95rem;">No appointment? Register as a guest to generate an immediate queue number.</p>
                                
                                <form id="kioskWalkinForm" class="mt-auto">
                                    <input type="hidden" id="selected_office_id" name="office_id">
                                    <input type="hidden" name="action" value="submit_walkin">
                                    
                                    <label class="fw-bold mb-2" style="color: var(--text-dark); font-size: 0.9rem; text-transform: uppercase; letter-spacing: 0.5px;">Guest Full Name</label>
                                    <input maxlength="100" title="Letters, spaces, and hyphens only." pattern="[\p{L}\p{M} \-]+" type="text" name="guest_name" id="guest_name_input" class="custom-input mb-3" placeholder="e.g., Juan Dela Cruz" required autocomplete="off">
                                    <div class="name-error-msg" id="name_error_msg">Only letters, spaces, and hyphens are allowed.</div>
                                    
                                    <label class="fw-bold mb-2 mt-2" style="color: var(--text-dark); font-size: 0.9rem; text-transform: uppercase; letter-spacing: 0.5px;">Purpose</label>
                                    <select name="purpose_option" id="purpose_option" class="custom-input mb-2" required>
                                        <option value="">Select a service</option>
                                        <option value="Others">Others</option>
                                    </select>
                                    <small id="purpose_helper" style="color: #64748b; font-size: 0.82rem; display: block; margin-bottom: 10px;">Service list will change based on your destination.</small>

                                    <div id="purpose-block-alert" style="display:none; margin-bottom: 12px;" class="disclaimer-box"></div>

                                    <div id="custom-purpose-wrap" style="display:none; margin-bottom: 12px;">
                                        <textarea name="custom_purpose" id="custom_purpose" class="custom-input" placeholder="Type your specific purpose..." rows="2" style="resize: none;"></textarea>
                                    </div>
                                    
                                    <label class="fw-bold mb-2 mt-2" style="color: var(--text-dark); font-size: 0.9rem; text-transform: uppercase; letter-spacing: 0.5px;">Priority Status</label>
                                    <div class="d-flex flex-column gap-2">
                                        <label style="cursor: pointer; display: flex; align-items: center; gap: 8px; font-weight: 600; color: #4a5568;">
                                            <input type="radio" name="priority" value="N/A" checked style="width: 18px; height: 18px; accent-color: var(--primary);"> N/A
                                        </label>
                                        <label style="cursor: pointer; display: flex; align-items: center; gap: 8px; font-weight: 600; color: #4a5568;">
                                            <input type="radio" name="priority" value="PWD" style="width: 18px; height: 18px; accent-color: var(--primary);"> PWD
                                        </label>
                                        <label style="cursor: pointer; display: flex; align-items: center; gap: 8px; font-weight: 600; color: #4a5568;">
                                            <input type="radio" name="priority" value="Senior Citizen" style="width: 18px; height: 18px; accent-color: var(--primary);"> Senior Citizen
                                        </label>
                                        <label style="cursor: pointer; display: flex; align-items: center; gap: 8px; font-weight: 600; color: #4a5568;">
                                            <input type="radio" name="priority" value="Pregnant" style="width: 18px; height: 18px; accent-color: var(--primary);"> Pregnant
                                        </label>
                                    </div>

                                    <div class="disclaimer-box">
                                        <small style="color: var(--danger-red); font-weight: 700; display: flex; align-items: center; gap: 6px; line-height: 1.2;">
                                            <i data-lucide="alert-triangle" style="width: 16px; height: 16px; flex-shrink: 0;"></i> 
                                            Note: Priority students must present valid ID (PWD/Senior) at the window.
                                        </small>
                                    </div>
                                    
                                    <button type="submit" class="btn-submit" id="btn-walkin">Get Queue Number</button>
                                </form>
                            </div>
                        </div>

                    </div>
                </div>
            </div>

        <?php else: ?>
            <div class="offline-box mt-5">
                <i data-lucide="moon" style="width: 60px; height: 60px; color: #a0aec0; margin-bottom: 20px;"></i>
                <h2 style="font-weight: 900; margin-bottom: 10px;color: white;">Kiosk Offline</h2>
                <p style="font-size: 1.1rem; color: rgba(255,255,255,0.8); line-height: 1.6;">
                    The SmartServe checking kiosk is currently closed. 
                </p>
                <div style="background: rgba(0,0,0,0.3); border-radius: 10px; padding: 15px; margin-top: 20px;">
                    <p style="margin: 0; font-weight: 600; color: #eef7f1;">
                        <i data-lucide="clock" style="width: 18px; height: 18px; vertical-align: text-bottom; margin-right: 5px;"></i>
                        Queue services are available from<br><span style="font-size: 1.3rem; color: #fff;"><?php echo htmlspecialchars($kioskHours['hours_label'], ENT_QUOTES, 'UTF-8'); ?></span>
                    </p>
                </div>
            </div>
        <?php endif; ?>
        <div style="margin-top: 35px;">
                <a href="index" style="display: inline-flex; align-items: center; gap: 8px; padding: 10px 24px; background: rgba(255,255,255,0.05); color: #ffffff; text-decoration: none; border-radius: 30px; border: 1px solid rgba(255,255,255,0.15); transition: all 0.3s ease; font-size: 0.95rem;" 
                   onmouseover="this.style.background='rgba(255,255,255,0.15)'; this.style.transform='translateX(-3px)';" 
                   onmouseout="this.style.background='rgba(255,255,255,0.05)'; this.style.transform='translateX(0)';">
                    <i data-lucide="arrow-left" style="width: 18px; height: 18px;"></i>
                    <span style="font-weight: 600;">Back to Home</span>
                </a>
            </div>

    </div>

    <footer class="modern-footer">
        &copy; <?php echo date('Y'); ?> SmartServe. All rights reserved.
    </footer>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script>
        lucide.createIcons();
        
        <?php if($is_business_hours): ?>
        let html5QrcodeScanner = null;
        let isProcessing = false;
        const officeBlockMap = <?php echo json_encode($office_block_map); ?>;
        const purposeSelect = $('#purpose_option');
        const customPurposeWrap = $('#custom-purpose-wrap');
        const customPurposeInput = $('#custom_purpose');
        const purposeBlockAlert = $('#purpose-block-alert');
        const officePurposeMap = {
            1: [
                'Medical Checkup',
                'Dental',
                'Injury/Wound Care'
            ],
            2: [
                'Payment'
            ],
            3: [
                'Valid ID',
                'Teams/Tech Concern'
            ]
        };

        $('#guest_name_input').on('input', function() {
            var validName = SmartServe.rules.name;
            var val = $(this).val();
            
            if (val.length > 0 && !validName(val, true, 100)) {
                $(this).addClass('is-invalid');
                $('#name_error_msg').slideDown(200);
                $('#btn-walkin').prop('disabled', true);
            } else {
                $(this).removeClass('is-invalid');
                $('#name_error_msg').slideUp(200);
                $('#btn-walkin').prop('disabled', false);
            }
        });

        function selectOffice(id, name) {
            $('#selection-area').hide();
            $('#header-subtitle').hide();
            $('#action-area').fadeIn();
            
            $('#selected_office_id').val(id);
            $('#display-office-name').text(name);
            updatePurposeOptions(Number(id));
            applyBlockState(id);
            
            if($('#manual-test-box').length === 0) {
                $('#reader').after(`
                    <div id="manual-test-box" style="margin-top: 15px; padding: 15px; background: #f8fafc; border-radius: 12px; border: 2px dashed #cbd5e1; text-align: left;">
                        <label style="font-size: 0.8rem; font-weight: 800; color: #64748b; display: block; margin-bottom: 8px; text-transform: uppercase;">Manual Check-in</label>
                        <div style="display: flex; gap: 10px;">
                            <input type="text" id="manual-tracking-input" placeholder="e.g. APT-2026..." class="custom-input" style="padding: 10px; font-size: 0.95rem; flex-grow: 1;">
                            <button type="button" id="manual-test-btn" style="background: var(--primary); color: white; border: none; padding: 10px 20px; border-radius: 10px; cursor: pointer; font-weight: 700; white-space: nowrap;">Scan</button>
                        </div>
                    </div>
                `);
            }
            
            startScanner();
        }

        function applyBlockState(officeId) {
            const blockInfo = officeBlockMap[officeId];
            const blockWarning = $('#blockWarning');
            const blockWarningText = $('#blockWarningText');
            const walkinBtn = $('#btn-walkin');
            const checkInPanel = $('#reader').closest('.panel-card');
            const walkinPanel = $('#kioskWalkinForm').closest('.panel-card');

            if (!blockInfo || !blockInfo.blocked) {
                blockWarning.slideUp(200);
                walkinBtn.prop('disabled', false);
                checkInPanel.css('opacity', '1').css('pointer-events', 'auto');
                walkinPanel.css('opacity', '1').css('pointer-events', 'auto');
            } else {
                const reason = blockInfo.reason ? blockInfo.reason : 'Unavailable';
                const note = blockInfo.note ? ' (' + blockInfo.note + ')' : '';
                blockWarningText.text('Office is blocked: ' + reason);
                blockWarning.slideDown(200);
                walkinBtn.prop('disabled', true);
                checkInPanel.css('opacity', '0.6').css('pointer-events', 'none');
                walkinPanel.css('opacity', '0.6').css('pointer-events', 'none');
            }
            lucide.createIcons();
        }

        $(document).on('click', '#manual-test-btn', function(e) {
            e.preventDefault();
            let trackingNo = $('#manual-tracking-input').val().trim();
            
            if(trackingNo !== '') {
                isProcessing = false; 
                onScanSuccess(trackingNo, null);
            } else {
                Swal.fire('Notice', 'Please enter a Tracking Number in the debug box first!', 'warning');
            }
        });

        function goBack() {
            stopScanner();
            $('#action-area').hide();
            $('#selection-area').fadeIn();
            $('#header-subtitle').fadeIn();
            
            $('#kioskWalkinForm')[0].reset();
            $('#guest_name_input').removeClass('is-invalid');
            $('#name_error_msg').hide();
            $('#btn-walkin').prop('disabled', false);
            updatePurposeOptions(null);
            customPurposeWrap.hide();
            customPurposeInput.prop('required', false).val('');
            purposeBlockAlert.hide().empty();
            
            $('#manual-tracking-input').val('');
            $('#blockWarning').slideUp(200);
            $('.panel-card').css('opacity', '1').css('pointer-events', 'auto');
            isProcessing = false;
        }

        function showPurposeBlock(message) {
            purposeBlockAlert.html('<small style="color: var(--danger-red); font-weight: 700; display: flex; align-items: center; gap: 6px; line-height: 1.4;"><i data-lucide="alert-triangle" style="width: 16px; height: 16px; flex-shrink: 0;"></i><span>' + message + '</span></small>').show();
            lucide.createIcons();
            $('#btn-walkin').prop('disabled', true);
        }

        function clearPurposeBlock() {
            purposeBlockAlert.hide().empty();
            if (!$('#blockWarning').is(':visible')) {
                $('#btn-walkin').prop('disabled', false);
            }
        }

        function checkPurposeBlock() {
            const officeId = $('#selected_office_id').val();
            const purpose = purposeSelect.val();

            if (!officeId || !purpose || purpose === 'Others') {
                clearPurposeBlock();
                return;
            }

            $.ajax({
                url: window.location.href.split('?')[0],
                type: 'POST',
                dataType: 'json',
                data: {
                    action: 'check_purpose_block',
                    office_id: officeId,
                    purpose: purpose
                },
                success: function(res) {
                    if (res && res.blocked) {
                        showPurposeBlock(res.message || 'This purpose is unavailable today.');
                    } else {
                        clearPurposeBlock();
                    }
                },
                error: function() {
                    clearPurposeBlock();
                }
            });
        }

        function toggleCustomPurposeField() {
            if (purposeSelect.val() === 'Others') {
                customPurposeWrap.slideDown(120);
                customPurposeInput.prop('required', true);
            } else {
                customPurposeWrap.slideUp(120);
                customPurposeInput.prop('required', false).val('');
            }
        }

        function updatePurposeOptions(officeId) {
            const previousValue = purposeSelect.val();
            const options = officePurposeMap[officeId] || [];

            purposeSelect.empty();
            purposeSelect.append('<option value="">Select a service</option>');

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

        function startScanner() {
            if(html5QrcodeScanner === null) {
                html5QrcodeScanner = new Html5QrcodeScanner(
                    "reader", 
                    { fps: 10, qrbox: {width: 250, height: 250}, aspectRatio: 1.0 }, 
                    false
                );
            }
            html5QrcodeScanner.render(onScanSuccess, onScanFailure);
        }

        function stopScanner() {
            if(html5QrcodeScanner !== null) {
                html5QrcodeScanner.clear().catch(error => {
                    console.error("Failed to clear scanner.");
                });
            }
        }

        function onScanSuccess(decodedText, decodedResult) {
            if (isProcessing) return; 
            isProcessing = true;
            
            try {
                if(html5QrcodeScanner && html5QrcodeScanner.getState() === 2) {
                    html5QrcodeScanner.pause();
                }
            } catch(e) {}
            
            let officeId = $('#selected_office_id').val();

            Swal.fire({
                title: 'Verifying...',
                text: 'Checking your appointment status.',
                allowOutsideClick: false,
                didOpen: () => { Swal.showLoading(); }
            });

            $.ajax({
                url: window.location.href.split('?')[0], 
                type: 'POST',
                data: {
                    action: 'verify_qr',
                    tracking_no: decodedText,
                    office_id: officeId
                },
                dataType: 'json',
                success: function(res) {
                    if (res.status === 'success') {
                        showSuccessModal(res.queue_number, res.msg);
                    } else {
                        showErrorModal(res.msg);
                    }
                },
                error: function(xhr, status, error) {
                    let errText = xhr.responseText ? xhr.responseText.substring(0, 150) : error;
                    showErrorModal('System Error: ' + errText);
                    console.error("Full Error:", xhr.responseText);
                }
            });
        }

        function onScanFailure(error) {}

        $('#kioskWalkinForm').on('submit', function(e) {
            e.preventDefault();
            
            var validName = SmartServe.rules.name;
            if (!validName($('#guest_name_input').val(), true, 100)) {
                return false;
            }

            if (isProcessing) return;
            isProcessing = true;
            
            let btn = $('#btn-walkin');
            btn.text('Processing...').prop('disabled', true);

            $.ajax({
                url: window.location.href.split('?')[0],
                type: 'POST',
                data: $(this).serialize(),
                dataType: 'json',
                success: function(res) {
                    btn.text('Get Queue Number').prop('disabled', false);
                    if (res.status === 'success') {
                        $('#kioskWalkinForm')[0].reset();
                        showSuccessModal(res.queue_number, res.msg);
                    } else {
                        showErrorModal(res.msg);
                    }
                },
                error: function(xhr, status, error) {
                    btn.text('Get Queue Number').prop('disabled', false);
                    let errText = xhr.responseText ? xhr.responseText.substring(0, 150) : error;
                    showErrorModal('System Error: ' + errText);
                    console.error("Full Error:", xhr.responseText);
                }
            });
        });

        function showSuccessModal(queueNum, message) {
            Swal.fire({
                title: message,
                html: `
                    <p style="color: #636e72; font-size: 1.1rem; margin-bottom: 20px;">Please wait to be called at the window.</p>
                    <div style="background: #eef7f1; border: 2px dashed #005141; border-radius: 20px; padding: 30px; margin: 0 auto; max-width: 80%;">
                        <h1 style="font-size: 5rem; font-weight: 900; color: #005141; margin: 0; line-height: 1;">${queueNum}</h1>
                    </div>
                `,
                icon: 'success',
                confirmButtonText: 'Done',
                confirmButtonColor: '#005141',
                allowOutsideClick: false
            }).then(() => {
                isProcessing = false;
                goBack(); 
            });
        }

        function showErrorModal(message) {
            Swal.fire({
                title: 'Error',
                text: message,
                icon: 'error',
                confirmButtonText: 'Try Again',
                confirmButtonColor: '#dc3545'
            }).then(() => {
                isProcessing = false;
                try {
                    if(html5QrcodeScanner && html5QrcodeScanner.getState() === 2) { 
                        html5QrcodeScanner.resume();
                    }
                } catch(e) {}
            });
        }

        purposeSelect.on('change', toggleCustomPurposeField);
        purposeSelect.on('change', checkPurposeBlock);
        updatePurposeOptions(null);
        <?php endif; ?>
    </script>
</body>
</html>
