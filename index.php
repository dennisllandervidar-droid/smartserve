<?php
    session_start();
    include('global/model.php');

    $model = new Model();
    $rows = $model->website_details();

    // Default values
    $web_name = "De La Salle Araneta University";
    $primary_color = "#005141"; // Base dark green
    $secondary_color = "#ffffff"; // Pure white
    $faqs = $model->displayFaqs(1);

    if (!empty($rows)) {
        foreach ($rows as $row) {
            $web_name = $row['web_name'];
            $primary_color = $row['primary_color']; 
            $secondary_color = $row['secondary_color'];
        }
    }
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <?php require_once __DIR__ . '/global/form-assets.php'; ?>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($web_name); ?> | Appointment System</title>
    <link href="assets/img/icon.png" rel="icon">
    <link href="assets/img/icon.png" rel="apple-touch-icon">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;900&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://unpkg.com/lucide@latest"></script>

    <style>
        :root {
            --bg-dark-green: #005141; 
            --bg-darker-green: #002d24; 
            --accent-white: #ffffff;
            --text-light: #f8f9fa;
        }

        body {
            font-family: 'Inter', sans-serif;
            background-color: var(--bg-dark-green);
            background-image: 
                radial-gradient(circle at 0% 0%, rgba(20, 164, 77, 0.15) 0%, transparent 50%),
                radial-gradient(circle at 100% 100%, rgba(0, 45, 36, 0.8) 0%, transparent 50%),
                radial-gradient(circle at 50% 50%, rgba(255, 255, 255, 0.03) 0%, transparent 60%);
            color: var(--text-light);
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            margin: 0;
            overflow-x: hidden;
            background-attachment: fixed;
        }

        /* --- Navbar --- */
        .navbar-custom {
            background: rgba(0, 61, 49, 0.4);
            backdrop-filter: blur(12px);
            -webkit-backdrop-filter: blur(12px);
            border-bottom: 1px solid rgba(255, 255, 255, 0.08);
            padding: 1rem 0;
        }

        .brand-text {
            border-left: 2px solid rgba(255,255,255,0.5);
            padding-left: 15px;
            margin-left: 15px;
        }

        .brand-text h1 {
            font-size: 1.1rem;
            font-weight: 800;
            margin: 0;
            color: #ffffff;
            letter-spacing: 0.5px;
        }

        .btn-login {
            background: linear-gradient(135deg, rgba(255,255,255,0.1), rgba(255,255,255,0.02));
            color: #ffffff;
            border: 1px solid rgba(255,255,255,0.3);
            border-radius: 30px;
            font-weight: 600;
            padding: 8px 24px;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
        }

        .btn-login:hover { 
            background: var(--accent-white); 
            color: var(--bg-dark-green); 
            border-color: var(--accent-white);
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(0,0,0,0.2);
        }

        /* --- Hero Section --- */
        .hero-section { padding: 70px 0 30px; text-align: center; }
        .sub-badge {
            background: linear-gradient(90deg, rgba(255, 255, 255, 0.1), rgba(255, 255, 255, 0.03));
            color: var(--accent-white);
            padding: 8px 20px;
            border-radius: 50px;
            font-weight: 700;
            font-size: 0.85rem;
            margin-bottom: 1.5rem;
            display: inline-flex;
            align-items: center;
            border: 1px solid rgba(255, 255, 255, 0.15);
            letter-spacing: 1px;
            text-transform: uppercase;
            backdrop-filter: blur(5px);
        }

        .hero-title {
            font-size: clamp(2.5rem, 5vw, 4rem);
            font-weight: 900;
            color: #ffffff;
            letter-spacing: -1px;
            margin-bottom: 1rem;
            line-height: 1.1;
        }

        .hero-title span {
            background: linear-gradient(to right, #ffffff, rgba(255,255,255,0.6));
            background-clip: text;
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        .hero-subtitle {
            color: rgba(255, 255, 255, 0.75);
            font-size: 1.15rem;
            max-width: 600px;
            margin: 0 auto;
            line-height: 1.6;
        }

        /* --- Action Cards --- */
        .action-grid-wrapper { padding: 2rem 0; }
        .action-card {
            background: linear-gradient(145deg, rgba(255, 255, 255, 0.05) 0%, rgba(255, 255, 255, 0.01) 100%);
            border: 1px solid rgba(255, 255, 255, 0.08);
            border-radius: 28px;
            padding: 40px 30px;
            text-align: center;
            text-decoration: none !important;
            display: flex;
            flex-direction: column;
            align-items: center;
            transition: all 0.4s cubic-bezier(0.165, 0.84, 0.44, 1);
            position: relative;
            overflow: hidden;
            height: 100%;
            backdrop-filter: blur(12px);
            -webkit-backdrop-filter: blur(12px);
            box-shadow: 0 15px 35px rgba(0,0,0,0.15);
        }

        .action-card:hover {
            transform: translateY(-10px);
            background: linear-gradient(145deg, rgba(255, 255, 255, 0.08) 0%, rgba(255, 255, 255, 0.02) 100%);
            box-shadow: 0 25px 50px rgba(0,0,0,0.25);
            border-color: rgba(255, 255, 255, 0.15);
        }

        .icon-box {
            width: 70px; height: 70px;
            background: linear-gradient(135deg, rgba(255,255,255,0.15), rgba(255,255,255,0.05)); 
            color: var(--accent-white);
            border-radius: 50%;
            display: flex; align-items: center; justify-content: center;
            margin-bottom: 25px;
            box-shadow: inset 0 2px 10px rgba(255,255,255,0.1);
        }

        .action-card h3 { font-size: 1.4rem; font-weight: 800; color: #ffffff; margin-bottom: 12px; line-height: 1.2; }
        .action-card p { font-size: 0.95rem; color: rgba(255, 255, 255, 0.7); margin: 0; line-height: 1.5; }

        /* --- Guide Section --- */
        .guide-section {
            background: linear-gradient(145deg, rgba(0,0,0,0.3) 0%, rgba(0,0,0,0.1) 100%);
            border-top: 1px solid rgba(255,255,255,0.05);
            border-bottom: 1px solid rgba(255,255,255,0.05);
            padding: 60px 0;
            margin: 30px 0;
        }
        .step-card {
            background: rgba(255, 255, 255, 0.03);
            border: 1px solid rgba(255, 255, 255, 0.05);
            border-radius: 20px;
            padding: 30px;
            height: 100%;
        }
        .step-number {
            width: 35px; height: 35px;
            background: var(--accent-white); color: var(--bg-dark-green);
            border-radius: 50%; display: flex; align-items: center; justify-content: center;
            font-weight: 900; font-size: 1.1rem; margin-bottom: 20px;
        }
        .step-card h4 { font-weight: 700; font-size: 1.1rem; color: #fff; margin-bottom: 10px; }
        .step-card p { color: rgba(255,255,255,0.7); font-size: 0.9rem; line-height: 1.6; margin: 0; }

        /* --- PHOTO MATCHED REMINDERS SECTION --- */
        .instructions-box {
            background: #7b443c; /* Exact Maroon from image */
            border: none;
            border-radius: 20px;
            padding: 35px;
            margin-bottom: 30px;
            color: #fff;
            box-shadow: 0 15px 35px rgba(0,0,0,0.25);
        }

        .instructions-box h5 { 
            color: #ffffff; 
            font-weight: 700; 
            margin-bottom: 24px; 
            display: flex; 
            align-items: center; 
            gap: 12px; 
        }

        .time-warning {
            background: rgba(255, 255, 255, 0.08); /* Transparent white bar */
            color: #ffffff;
            padding: 15px 20px;
            border-radius: 12px;
            display: flex; 
            align-items: center; 
            gap: 14px;
            margin-bottom: 14px;
            border: 1px solid rgba(255, 255, 255, 0.05);
        }

        .time-warning i { color: #ffffff; flex-shrink: 0; }

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
            background: rgba(0, 0, 0, 0.15); /* Darker pill */
            padding: 10px 18px;
            border-radius: 50px; /* Fully rounded pill */
            border: 1px solid rgba(255, 255, 255, 0.1);
        }

        .route-step-num {
            background: #ef4444; /* Red circle */
            color: #fff;
            width: 32px; height: 32px;
            border-radius: 50%;
            display: flex; align-items: center; justify-content: center;
            font-weight: 800; font-size: 0.9rem;
        }

        /* Step 3 is Green in your photo */
        .step-final .route-step-num { background: #22c55e; }

        .route-step-text strong { display: block; font-size: 0.95rem; color: #ffffff; line-height: 1.2; }
        .route-step-text span { font-size: 0.72rem; color: rgba(255, 255, 255, 0.6); text-transform: uppercase; }

        .route-arrow { color: rgba(255, 255, 255, 0.3); }

        .faq-section {
            padding: 20px 0 60px;
        }

        .faq-shell {
            background: linear-gradient(145deg, rgba(255,255,255,0.08), rgba(255,255,255,0.02));
            border: 1px solid rgba(255, 255, 255, 0.12);
            border-radius: 24px;
            padding: 28px;
            box-shadow: 0 20px 45px rgba(0,0,0,0.18);
        }

        .faq-side-card {
            background: rgba(0,0,0,0.2);
            border: 1px solid rgba(255,255,255,0.1);
            border-radius: 16px;
            padding: 22px;
            height: 100%;
        }

        .faq-side-chip {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            background: rgba(255,255,255,0.12);
            border: 1px solid rgba(255,255,255,0.18);
            border-radius: 999px;
            padding: 7px 14px;
            font-size: 0.8rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.8px;
            color: #fff;
            margin-bottom: 16px;
        }

        .faq-side-note {
            background: rgba(255,255,255,0.08);
            border-left: 3px solid rgba(255,255,255,0.4);
            border-radius: 10px;
            padding: 12px 14px;
            color: rgba(255,255,255,0.85);
            font-size: 0.88rem;
            margin-top: 14px;
            margin-bottom: 0;
            line-height: 1.6;
        }

        .faq-section .accordion-item {
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid rgba(255, 255, 255, 0.12);
            margin-bottom: 12px;
            border-radius: 14px !important;
            overflow: hidden;
        }

        .faq-section .accordion-button {
            background: rgba(0, 0, 0, 0.2);
            color: #ffffff;
            font-weight: 600;
            box-shadow: none;
        }

        .faq-section .accordion-button:not(.collapsed) {
            background: rgba(255, 255, 255, 0.1);
            color: #ffffff;
        }

        .faq-section .accordion-body {
            color: rgba(255, 255, 255, 0.85);
            line-height: 1.7;
        }

        .faq-section .accordion-button::after {
            filter: invert(1) brightness(1.5);
        }

        .help-widget-trigger {
            position: fixed;
            right: 20px;
            bottom: 20px;
            z-index: 1080;
            border: 1px solid rgba(255, 255, 255, 0.25);
            background: linear-gradient(135deg, rgba(0, 81, 65, 0.95), rgba(0, 45, 36, 0.95));
            color: #ffffff;
            border-radius: 999px;
            padding: 11px 18px;
            display: inline-flex;
            align-items: center;
            gap: 10px;
            font-weight: 700;
            box-shadow: 0 14px 30px rgba(0, 0, 0, 0.35);
            transition: transform 0.25s ease, box-shadow 0.25s ease;
        }

        .help-widget-trigger:hover {
            transform: translateY(-3px);
            box-shadow: 0 18px 34px rgba(0, 0, 0, 0.42);
            color: #ffffff;
        }

        .help-widget-trigger i {
            width: 18px;
            height: 18px;
        }

        .help-widget-panel {
            width: min(420px, 100vw);
            background: linear-gradient(170deg, #06281f 0%, #03140f 100%);
            color: #ffffff;
            border-left: 1px solid rgba(255, 255, 255, 0.12);
        }

        .help-widget-head {
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
            padding: 1rem 1rem 0.9rem;
        }

        .help-widget-head h5 {
            margin: 0;
            font-weight: 800;
            letter-spacing: 0.3px;
        }

        .help-widget-sub {
            margin: 8px 0 0;
            font-size: 0.9rem;
            color: rgba(255, 255, 255, 0.72);
        }

        .help-widget-panel .accordion-item {
            background: rgba(255, 255, 255, 0.04);
            border: 1px solid rgba(255, 255, 255, 0.1);
            margin-bottom: 10px;
            border-radius: 12px !important;
            overflow: hidden;
        }

        .help-widget-panel .accordion-button {
            background: rgba(0, 0, 0, 0.25);
            color: #ffffff;
            font-weight: 600;
            box-shadow: none;
            line-height: 1.4;
        }

        .help-widget-panel .accordion-button:not(.collapsed) {
            background: rgba(255, 255, 255, 0.1);
            color: #ffffff;
        }

        .help-widget-panel .accordion-button::after {
            filter: invert(1) brightness(1.5);
        }

        .help-widget-panel .accordion-body {
            color: rgba(255, 255, 255, 0.86);
            line-height: 1.65;
            font-size: 0.95rem;
        }

        .reminder-modal-content {
            background: linear-gradient(160deg, #7b443c 0%, #5f322b 100%);
            color: #fff;
            border: 1px solid rgba(255,255,255,0.2);
            border-radius: 18px;
        }

        .btn-reminder-close {
            background: linear-gradient(135deg, rgba(255,255,255,0.95), rgba(240,240,240,0.95));
            color: #5f322b;
            border: 1px solid rgba(255,255,255,0.65);
            border-radius: 999px;
            font-weight: 700;
            padding: 10px 24px;
            box-shadow: 0 10px 20px rgba(0,0,0,0.2);
            transition: all 0.25s ease;
        }

        .btn-reminder-close:hover {
            transform: translateY(-2px);
            box-shadow: 0 14px 24px rgba(0,0,0,0.28);
            color: #4a261f;
            background: #ffffff;
        }

        /* --- Footer --- */
        .main-footer { background: rgba(0, 45, 36, 0.7); padding-top: 50px; margin-top: auto; border-top: 1px solid rgba(255,255,255,0.05); backdrop-filter: blur(10px); }
        .footer-link-hd { color: var(--accent-white); font-weight: 700; text-transform: uppercase; font-size: 0.85rem; margin-bottom: 20px; letter-spacing: 1px; }
        .footer-links { list-style: none; padding: 0; }
        .footer-links a { color: rgba(255,255,255,0.6); text-decoration: none; transition: 0.2s; display: block; margin-bottom: 10px; }
        .footer-links a:hover { color: #ffffff; transform: translateX(5px); }
        .footer-bottom { background: rgba(0,0,0,0.4); padding: 20px 0; font-size: 0.85rem; margin-top: 40px; }

        @media (max-width: 768px) {
            .hero-section { padding: 40px 0 20px; }
            .route-arrow { display: none; }
            .route-step { width: 100%; margin-bottom: 5px; }
            .faq-shell { padding: 18px; }
            .help-widget-trigger {
                right: 14px;
                bottom: 14px;
                padding: 10px 14px;
                font-size: 0.85rem;
            }
            .help-widget-panel {
                width: 100vw;
            }
        }
    </style>
</head>
<body>

    <nav class="navbar navbar-custom sticky-top navbar-expand-lg">
        <div class="container">
            <div class="d-flex align-items-center">
                <img src="assets/img/icon.png" alt="Logo" height="50" style="filter: brightness(0) invert(1);">
                <div class="brand-text">
                    <p class="text-uppercase m-0" style="font-size: 9px; color: rgba(255,255,255,0.6); font-weight: 700; letter-spacing: 1px;">SMARTSERVE</p>
                    <h1><?php echo htmlspecialchars($web_name); ?></h1>
                </div>
            </div>
            <div class="ms-auto">
                <a href="login" class="btn-login">
                    <i data-lucide="log-in" size="16"></i>
                    <span class="d-none d-sm-inline">Login</span>
                </a>
            </div>
        </div>
    </nav>

    <main class="container flex-grow-1">
        <section class="hero-section">
            <span class="sub-badge"><i data-lucide="zap" size="14" class="me-2 mb-1"></i> Digital Campus Services</span>
            <h2 class="hero-title">Appointment <span>Management</span></h2>
            <p class="hero-subtitle">Schedule your visits for admissions, registrar services, and departmental inquiries seamlessly through our digital queue portal.</p>
        </section>

        <div class="action-grid-wrapper">
            <div class="row g-4 justify-content-center"> 
                <div class="col-lg-4 col-md-6">
                    <a href="login" class="action-card">
                        <div class="icon-box"><i data-lucide="calendar-plus" size="32" stroke-width="1.5"></i></div>
                        <h3>Book New<br>Appointment</h3>
                        <p>Secure your slot for campus services in just a few clicks.</p>
                    </a>
                </div>
                <div class="col-lg-4 col-md-12">
                    <a href="live-home" class="action-card">
                        <div class="icon-box"><i data-lucide="tv" size="32" stroke-width="1.5"></i></div>
                        <h3>Live<br>Queues</h3>
                        <p>Check the active calling monitors for all departments.</p>
                    </a>
                </div>
                <div class="col-lg-4 col-md-6">
                    <a href="advisories" class="action-card">
                        <div class="icon-box"><i data-lucide="layout-dashboard" size="32" stroke-width="1.5"></i></div>
                        <h3>See Latest<br>Advisories</h3>
                        <p>Stay informed with real-time updates and easily manage your bookings in one place.</p>
                    </a>
                </div>
            </div>
        </div>
    </main>

    <section class="guide-section">
        <div class="container">
            <div class="text-center mb-5">
                <h3 class="fw-bold text-white mb-2">How to Use SmartServe</h3>
                <p style="color: rgba(255,255,255,0.6);">Follow these simple steps to ensure a smooth transaction.</p>
            </div>
            <div class="row g-4">
                <div class="col-md-3"><div class="step-card"><div class="step-number">1</div><h4>Book Online</h4><p>Log in, select department, and pick a time.</p></div></div>
                <div class="col-md-3"><div class="step-card"><div class="step-number">2</div><h4>Save Ticket</h4><p>Save your digital QR ticket to your phone.</p></div></div>
                <div class="col-md-3"><div class="step-card"><div class="step-number">3</div><h4>Scan at Kiosk</h4><p>Arrive early and scan QR at the kiosk.</p></div></div>
                <div class="col-md-3"><div class="step-card"><div class="step-number">4</div><h4>Wait to be Called</h4><p>Watch monitors and proceed to window.</p></div></div>
            </div>
        </div>
    </section>

    <main class="container">
        <section class="py-4">
            <div class="row justify-content-center">
                <div class="col-lg-12">
                    <div class="instructions-box" id="validation-instructions">
                        <h5><i data-lucide="info" size="22"></i> Important Reminders</h5>
                        
                        <div class="time-warning">
                            <i data-lucide="alert-triangle" size="20"></i> 
                            <span>Requests are automatically canceled if not scanned within <strong><?php echo SmartServeAppointmentRules::ARRIVAL_GRACE_MINUTES; ?> minutes</strong> of your scheduled appointment time.</span>
                        </div>
                        <div class="time-warning">
                            <i data-lucide="clock" size="20"></i> 
                            <span>Queue transactions are strictly processed until <strong>5:00 PM</strong> only.</span>
                        </div>
                        
                        <div class="mt-4 pt-3">
                            <p class="mb-3" style="font-size: 0.95rem; color: rgba(255,255,255,0.95);">
                                If you are requesting <strong>ID Validation at the ITC</strong>, you must secure the required documents by visiting the offices in this exact order:
                            </p>
                            
                            <div class="route-path">
                                <div class="route-step">
                                    <div class="route-step-num">1</div>
                                    <div class="route-step-text"><strong>Cashier</strong><span>Enrollment Receipt</span></div>
                                </div>
                                <i data-lucide="chevron-right" size="18" class="route-arrow"></i>
                                <div class="route-step">
                                    <div class="route-step-num">2</div>
                                    <div class="route-step-text"><strong>Clinic</strong><span>Medical Checkup</span></div>
                                </div>
                                <i data-lucide="chevron-right" size="18" class="route-arrow"></i>
                                <div class="route-step step-final">
                                    <div class="route-step-num">3</div>
                                    <div class="route-step-text"><strong>ITC</strong><span>Final ID Validation</span></div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>

    </main>

    <button class="btn help-widget-trigger" type="button" data-bs-toggle="offcanvas" data-bs-target="#faqsWidget" aria-controls="faqsWidget" aria-label="Open FAQs">
        <i data-lucide="circle-help"></i>
        FAQs
    </button>

    <div class="offcanvas offcanvas-end help-widget-panel" tabindex="-1" id="faqsWidget" aria-labelledby="faqsWidgetLabel">
        <div class="help-widget-head d-flex align-items-start justify-content-between gap-2">
            <div>
                <h5 id="faqsWidgetLabel" class="d-flex align-items-center gap-2">
                    <i data-lucide="circle-help" size="18"></i>
                    Frequently Asked Questions
                </h5>
                <p class="help-widget-sub">Browse answers to frequently asked questions.</p>
            </div>
            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="offcanvas" aria-label="Close"></button>
        </div>
        <div class="offcanvas-body">
            <div class="accordion" id="helpCenterFaqAccordion">
                <?php if (!empty($faqs)) { ?>
                    <?php foreach ($faqs as $index => $faq) {
                        $faq_number = $index + 1;
                        $heading_id = 'helpFaqHeading' . $faq_number;
                        $collapse_id = 'helpFaqCollapse' . $faq_number;
                        $is_first = ($index === 0);
                    ?>
                        <div class="accordion-item">
                            <h2 class="accordion-header" id="<?php echo $heading_id; ?>">
                                <button class="accordion-button <?php echo $is_first ? '' : 'collapsed'; ?>" type="button" data-bs-toggle="collapse" data-bs-target="#<?php echo $collapse_id; ?>" aria-expanded="<?php echo $is_first ? 'true' : 'false'; ?>" aria-controls="<?php echo $collapse_id; ?>">
                                    <?php echo htmlspecialchars($faq['question']); ?>
                                </button>
                            </h2>
                            <div id="<?php echo $collapse_id; ?>" class="accordion-collapse collapse <?php echo $is_first ? 'show' : ''; ?>" aria-labelledby="<?php echo $heading_id; ?>" data-bs-parent="#helpCenterFaqAccordion">
                                <div class="accordion-body">
                                    <?php echo nl2br(htmlspecialchars($faq['answer'])); ?>
                                </div>
                            </div>
                        </div>
                    <?php } ?>
                <?php } else { ?>
                    <div class="accordion-item">
                        <div class="accordion-body">
                            No FAQs are available at the moment.
                        </div>
                    </div>
                <?php } ?>
            </div>
        </div>
    </div>

    <div class="modal fade" id="importantRemindersModal" tabindex="-1" aria-labelledby="importantRemindersModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <div class="modal-content reminder-modal-content">
                <div class="modal-header border-0 pb-0">
                    <h5 class="modal-title d-flex align-items-center gap-2" id="importantRemindersModalLabel">
                        <i data-lucide="bell-ring" size="18"></i>
                        Important Reminders
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body pt-2">
                    <br>
                    <div class="time-warning">
                        <i data-lucide="alert-triangle" size="20"></i>
                        <span>Requests are automatically canceled if not scanned within <strong><?php echo SmartServeAppointmentRules::ARRIVAL_GRACE_MINUTES; ?> minutes</strong> of your scheduled appointment time.</span>
                    </div>
                    <div class="time-warning">
                        <i data-lucide="clock" size="20"></i>
                        <span>Queue transactions are strictly processed until <strong>5:00 PM</strong> only.</span>
                    </div>

                    <div class="mt-4 pt-2">
                        <p class="mb-3" style="font-size: 0.95rem; color: rgba(255,255,255,0.95);">
                            If you are requesting <strong>ID Validation at the ITC</strong>, you must secure the required documents by visiting the offices in this exact order:
                        </p>

                        <div class="route-path mt-0">
                            <div class="route-step">
                                <div class="route-step-num">1</div>
                                <div class="route-step-text"><strong>Cashier</strong><span>Enrollment Receipt</span></div>
                            </div>
                            <i data-lucide="chevron-right" size="18" class="route-arrow"></i>
                            <div class="route-step">
                                <div class="route-step-num">2</div>
                                <div class="route-step-text"><strong>Clinic</strong><span>Medical Checkup</span></div>
                            </div>
                            <i data-lucide="chevron-right" size="18" class="route-arrow"></i>
                            <div class="route-step step-final">
                                <div class="route-step-num">3</div>
                                <div class="route-step-text"><strong>ITC</strong><span>Final ID Validation</span></div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer border-0 pt-0">
                    <button type="button" class="btn btn-outline-secondary" id="hide-homepage-reminders" data-bs-dismiss="modal">Do not show again</button>
                    <button type="button" class="btn btn-reminder-close" data-bs-dismiss="modal">I Understand</button>
                </div>
            </div>
        </div>
    </div>

    <footer class="main-footer">
        <div class="container">
            <div class="row gy-4">
                <div class="col-lg-6">
                    <h4 class="text-white fw-bold mb-3 d-flex align-items-center gap-2">
                        <img src="assets/img/icon.png" height="30" alt="" style="filter: brightness(0) invert(1);">
                        <?php echo htmlspecialchars($web_name); ?>
                    </h4>
                    <p style="color: rgba(255,255,255,0.7); max-width: 400px; font-size: 0.9rem;">
                        Streamlined digital solutions designed to empower and connect the Lasallian community.
                    </p>
                </div>
                <div class="col-lg-3 col-md-6">
                    <h5 class="footer-link-hd">Quick Links</h5>
                    <ul class="footer-links">
                        <li><a href="advisories">Advisories</a></li>
                        <li><a href="live-home">Live Queue Directory</a></li>
                        <li><a href="kiosk">Kiosk</a></li>
                    </ul>
                </div>
                <div class="col-lg-3 col-md-6">
                    <h5 class="footer-link-hd">Connect</h5>
                    <p style="color: rgba(255,255,255,0.7); font-size: 0.85rem;"><i data-lucide="map-pin" size="14" class="me-2"></i> Malabon City, Philippines</p>
                </div>
            </div>
        </div>
        <div class="footer-bottom">
            <div class="container text-center"><p class="mb-0 text-white-50">&copy; <?php echo date('Y'); ?> <?php echo htmlspecialchars($web_name); ?>. All Rights Reserved.</p></div>
        </div>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            lucide.createIcons();

        });
    </script>
    <script src="assets/js/home-reminders.js?v=<?php echo filemtime(__DIR__ . '/assets/js/home-reminders.js'); ?>"></script>
</body>
</html>
