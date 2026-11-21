<?php
    session_start();
    include('global/model.php');

    $model = new Model();
    $rows = $model->website_details();

    // Default values
    $web_name = "De La Salle Araneta University";
    $primary_color = "#005141"; // Base dark green
    $secondary_color = "#ffffff"; // Changed to pure white

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

        /* --- Modern Transparent Navbar --- */
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
        .hero-section { 
            padding: 70px 0 30px; 
            text-align: center; 
        }

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

        /* --- Glassmorphism Action Cards --- */
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

        .action-card::before {
            content: '';
            position: absolute;
            top: 0; left: 0; right: 0; 
            height: 1px;
            background: linear-gradient(90deg, transparent, rgba(255,255,255,0.5), transparent);
            opacity: 0.5;
            transition: opacity 0.4s ease;
        }

        .action-card:hover {
            transform: translateY(-10px);
            background: linear-gradient(145deg, rgba(255, 255, 255, 0.08) 0%, rgba(255, 255, 255, 0.02) 100%);
            box-shadow: 0 25px 50px rgba(0,0,0,0.25);
            border-color: rgba(255, 255, 255, 0.15);
        }

        .icon-box {
            width: 70px;
            height: 70px;
            background: linear-gradient(135deg, rgba(255,255,255,0.15), rgba(255,255,255,0.05)); 
            color: var(--accent-white);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 25px;
            transition: 0.4s ease;
            box-shadow: inset 0 2px 10px rgba(255,255,255,0.1);
        }

        .action-card h3 { 
            font-size: 1.4rem; font-weight: 800; color: #ffffff; 
            margin-bottom: 12px; line-height: 1.2;
        }
        .action-card p { font-size: 0.95rem; color: rgba(255, 255, 255, 0.7); margin: 0; line-height: 1.5; }

        /* --- Reminders Box --- */
        .instructions-box {
            background: rgba(255, 193, 7, 0.1);
            border-left: 5px solid #ffc107;
            border-radius: 12px;
            padding: 25px;
            margin-bottom: 30px;
            color: #fff;
            backdrop-filter: blur(5px);
        }
        .instructions-box h5 { color: #ffc107; font-weight: 800; margin-bottom: 15px; display: flex; align-items: center; gap: 10px; }
        .instructions-list { margin: 15px 0; padding-left: 20px; font-weight: 500; }
        .instructions-list li { margin-bottom: 8px; color: rgba(255,255,255,0.9); }
        
        .time-warning {
            background: rgba(220, 53, 69, 0.15);
            color: #ff8787;
            padding: 12px 15px;
            border-radius: 8px;
            font-weight: 700;
            display: flex; align-items: center; gap: 8px;
            border: 1px solid rgba(220, 53, 69, 0.3);
            margin-bottom: 10px;
        }

        /* --- Help / Guide Section --- */
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

        /* --- Footer --- */
        .main-footer { 
            background: rgba(0, 45, 36, 0.7); 
            padding-top: 50px; 
            margin-top: auto;
            border-top: 1px solid rgba(255,255,255,0.05);
            backdrop-filter: blur(10px);
        }
        .footer-link-hd { color: var(--accent-white); font-weight: 700; text-transform: uppercase; font-size: 0.85rem; margin-bottom: 20px; letter-spacing: 1px; }
        .footer-links { list-style: none; padding: 0; }
        .footer-links a { color: rgba(255,255,255,0.6); text-decoration: none; transition: 0.2s; display: block; margin-bottom: 10px; }
        .footer-links a:hover { color: #ffffff; transform: translateX(5px); }
        
        .footer-bottom { 
            background: rgba(0,0,0,0.4); 
            padding: 20px 0; 
            font-size: 0.85rem; 
            margin-top: 40px; 
        }

        @media (max-width: 768px) {
            .brand-text h1 { font-size: 0.95rem; }
            .hero-section { padding: 40px 0 20px; }
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
                <div class="col-lg-4 col-md-6">
                    <a href="login" class="action-card">
                        <div class="icon-box"><i data-lucide="layout-dashboard" size="32" stroke-width="1.5"></i></div>
                        <h3>Check Status<br>& Manage</h3>
                        <p>View, reschedule, or cancel your existing appointments.</p>
                    </a>
                </div>
                <div class="col-lg-4 col-md-12">
                    <a href="live-home" class="action-card">
                        <div class="icon-box"><i data-lucide="tv" size="32" stroke-width="1.5"></i></div>
                        <h3>Live<br>Queues</h3>
                        <p>Check the active calling monitors for all departments.</p>
                    </a>
                </div>
            </div>
        </div>

        <section class="py-4">
            <div class="row">
                <div class="col-lg-12">
                    <div class="instructions-box" id="validation-instructions">
                        <h5><i data-lucide="info" size="24"></i> Important Reminder for ID Validation</h5>
                        <p class="m-0" style="font-size: 0.95rem; color: rgba(255,255,255,0.9);">If you are requesting <strong>ID Validation at the ITC</strong>, you must secure the required documents by visiting the offices in this exact order:</p>
                        <ol class="instructions-list">
                            <li><strong>1st: Cashier</strong> (Secure your Enrollment Receipt)</li>
                            <li><strong>2nd: Clinic</strong> (For your Medical Checkup)</li>
                            <li><strong>3rd: ITC</strong> (For final ID Validation)</li>
                        </ol>

                        <div class="time-warning mt-4">
                            <i data-lucide="clock" size="18"></i> Queue transactions are strictly processed until 5:00 PM only.
                        </div>
                        <div class="time-warning" style="background: rgba(255, 193, 7, 0.1); color: #ffc107; border-color: rgba(255, 193, 7, 0.3);">
                            <i data-lucide="alert-triangle" size="18"></i> Requests are automatically canceled if not scanned within <?php echo SmartServeAppointmentRules::ARRIVAL_GRACE_MINUTES; ?> minutes of your scheduled appointment time.
                        </div>
                    </div>
                </div>
            </div>
        </section>
    </main>

    <section class="guide-section">
        <div class="container">
            <div class="text-center mb-5">
                <h3 class="fw-bold text-white mb-2">How to Use SmartServe</h3>
                <p style="color: rgba(255,255,255,0.6);">Follow these simple steps to ensure a smooth transaction.</p>
            </div>
            
            <div class="row g-4">
                <div class="col-md-3">
                    <div class="step-card">
                        <div class="step-number">1</div>
                        <h4>Book Online</h4>
                        <p>Log in to your account, select your desired department, and pick an available date and time slot.</p>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="step-card">
                        <div class="step-number">2</div>
                        <h4>Save your Ticket</h4>
                        <p>Once booked, a digital QR ticket is generated. Save this to your phone or keep it accessible.</p>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="step-card">
                        <div class="step-number">3</div>
                        <h4>Scan at Kiosk</h4>
                        <p>Arrive at the campus kiosk up to 2 hours early. Scan your QR code to check in and receive your physical Queue Number.</p>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="step-card">
                        <div class="step-number">4</div>
                        <h4>Wait to be Called</h4>
                        <p>Watch the Live Monitor screens. Proceed to the designated window once your Queue Number flashes.</p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <footer class="main-footer">
        <div class="container">
            <div class="row gy-4">
                <div class="col-lg-6">
                    <h4 class="text-white fw-bold mb-3 d-flex align-items-center gap-2">
                        <img src="assets/img/icon.png" height="30" alt="" style="filter: brightness(0) invert(1);">
                        <?php echo htmlspecialchars($web_name); ?>
                    </h4>
                    <p style="color: rgba(255,255,255,0.7); max-width: 400px; font-size: 0.9rem; line-height: 1.6;">
                        Streamlined digital solutions designed to empower and connect the Lasallian community.
                    </p>
                </div>
                <div class="col-lg-3 col-md-6">
                    <h5 class="footer-link-hd">Quick Links</h5>
                    <ul class="footer-links">
                        <li><a href="https://www.dlsu.edu.ph/about/" target="_blank">About Us</a></li>
                        <li><a href="live-home">Live Queue Directory</a></li>
                    </ul>
                </div>
                <div class="col-lg-3 col-md-6">
                    <h5 class="footer-link-hd">Connect</h5>
                    <p style="color: rgba(255,255,255,0.7); font-size: 0.9rem; margin-bottom: 10px;">
                        <i data-lucide="map-pin" size="14" class="me-2"></i> Malabon City, Philippines
                    </p>
                    <p style="color: rgba(255,255,255,0.7); font-size: 0.9rem;">
                        <i data-lucide="phone" size="14" class="me-2"></i> +63 (2) 8330 9129
                    </p>
                </div>
            </div>
        </div>
        <div class="footer-bottom">
            <div class="container d-md-flex justify-content-between text-center">
                <p class="mb-0" style="color: rgba(255,255,255,0.6);">&copy; <?php echo date('Y'); ?> <?php echo htmlspecialchars($web_name); ?>. All Rights Reserved.</p>
                <div class="mt-2 mt-md-0">
                    <a href="#" class="text-decoration-none me-3" style="color: rgba(255,255,255,0.6);">Privacy Policy</a>
                    <a href="#" class="text-decoration-none" style="color: rgba(255,255,255,0.6);">Terms of Use</a>
                </div>
            </div>
        </div>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>lucide.createIcons();</script>
</body>
</html>
