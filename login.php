<?php

	session_start();
	include('global/model.php');

	$model = new Model();
    $loginUsername = $_SESSION['login_attempt_username'] ?? '';
    $loginState = $model->getLoginAttemptStatus($loginUsername);

    if (isset($_GET['login_status'])) {
        header('Content-Type: application/json');
        header('Cache-Control: no-store');
        echo json_encode($loginState);
        exit;
    }

    if (isset($_POST['submit'])) {
        if ($loginState['locked']) {
            $_SESSION['login_error'] = 'All 5 login attempts have been used. Please wait for the five-minute cooldown to finish.';
        } else {
            $_SESSION['login_attempt_username'] = trim($_POST['username']);
            $_SESSION['login_error'] = $model->signIn($_POST['username'], $_POST['password']);
        }
        header('Location: login', true, 303);
        exit;
    }
    $msg = $_SESSION['login_error'] ?? '';
    unset($_SESSION['login_error']);
    header('Cache-Control: no-store');
    $rows = $model->website_details();

    if (!empty($rows)) {
        foreach ($rows as $row) {
        	$web_name = $row['web_name'];
        	$web_code = strtoupper($row['web_code']);
            $web_header = $row['web_header'];
            $primary_color = $row['primary_color'];
            $secondary_color = $row['secondary_color'];
            $web_icon = $row['web_icon'];
       	}
    }

    $faqs = $model->displayFaqs(1);

    if ($msg) smartserve_notify($msg, 'danger');

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <?php require_once __DIR__ . '/global/form-assets.php'; ?>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $web_name; ?> | Appointment System</title>
    <link href="assets/img/icon.png" rel="icon">
    <link href="assets/img/icon.png" rel="apple-touch-icon">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://unpkg.com/lucide@latest"></script>

    <style>
        :root {
            --primary-green: <?php echo $primary_color; ?>;
            --accent-green: <?php echo $secondary_color; ?>;
            --bg-dark-green: #005141;
            --bg-darker-green: #002d24;
        }

        body {
            font-family: 'Inter', sans-serif;
            background-color: var(--bg-dark-green);
            background-image:
                radial-gradient(circle at 0% 0%, rgba(20, 164, 77, 0.15) 0%, transparent 50%),
                radial-gradient(circle at 100% 100%, rgba(0, 45, 36, 0.8) 0%, transparent 50%),
                radial-gradient(circle at 50% 50%, rgba(255, 255, 255, 0.03) 0%, transparent 60%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
            background-attachment: fixed;
        }

        .reg-card {
            background: linear-gradient(145deg, rgba(255,255,255,0.1), rgba(255,255,255,0.03));
            border-radius: 30px;
            border: 1px solid rgba(255,255,255,0.18);
            box-shadow: 0 20px 40px rgba(0,0,0,0.25);
            backdrop-filter: blur(10px);
            width: 100%;
            max-width: 550px;
            overflow: hidden;
            animation: fadeIn 0.6s ease-out;
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .reg-header {
            background: linear-gradient(135deg, rgba(0,81,65,0.9), rgba(0,45,36,0.95));
            padding: 40px;
            text-align: center;
            color: white;
        }

        .reg-body { padding: 40px; }

        .form-label {
            font-weight: 600;
            font-size: 0.85rem;
            color: rgba(255,255,255,0.85);
            margin-bottom: 8px;
        }

        .form-control {
            border-radius: 12px;
            padding: 12px 15px;
            border: 1px solid rgba(255,255,255,0.2);
            background: rgba(72, 132, 113, 0.68);
            color: #ffffff;
            transition: 0.3s;
        }

        .form-control::placeholder {
            color: rgba(255,255,255,0.5);
        }

        .form-control:focus {
            border-color: rgba(255,255,255,0.45);
            box-shadow: 0 0 0 4px rgba(255, 255, 255, 0.08);
            background: rgba(72, 132, 113, 0.68);
            color: #ffffff;
            outline: none;
        }

        .form-control:active,
        .form-control.is-valid,
        .form-control.is-invalid {
            background: rgba(72, 132, 113, 0.68) !important;
            color: #ffffff !important;
        }

        input:-webkit-autofill,
        input:-webkit-autofill:hover,
        input:-webkit-autofill:focus,
        textarea:-webkit-autofill,
        select:-webkit-autofill {
            -webkit-text-fill-color: #ffffff !important;
            -webkit-box-shadow: 0 0 0px 1000px rgba(72, 132, 113, 0.68) inset !important;
            box-shadow: 0 0 0px 1000px rgba(72, 132, 113, 0.68) inset !important;
            transition: background-color 9999s ease-in-out 0s;
        }

        .btn-register {
            background: #ffffff;
            color: var(--bg-darker-green);
            border: none;
            border-radius: 12px;
            padding: 14px;
            font-weight: 700;
            width: 100%;
            transition: 0.3s;
            margin-top: 10px;
        }

        .btn-register:hover {
            background: #f0f4f3 !important;
            color: var(--bg-darker-green);
            transform: translateY(-2px);
            box-shadow: 0 10px 20px rgba(0, 0, 0, 0.22);
        }

        .btn-register:disabled,
        .btn-register:disabled:hover {
            background: #ffffff;
            color: var(--bg-darker-green);
            opacity: 0.6;
            cursor: not-allowed;
            transform: none;
            box-shadow: none;
        }

        .reg-body .login-attempt-status {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            margin: 12px 0 0;
            color: #ffaaa5 !important;
            font-size: 0.8rem;
            font-weight: 600;
            line-height: 1.6;
        }

        .login-attempt-status[hidden] { display: none; }
        .login-attempt-status svg { width: 16px; height: 16px; flex-shrink: 0; }

        .back-link {
            color: #ffffff;
            text-decoration: none;
            font-weight: 600;
            font-size: 0.9rem;
        }

        .reg-body,
        .reg-body p,
        .reg-body a,
        .reg-body small,
        .reg-body .text-muted,
        .reg-body .small {
            color: rgba(255, 255, 255, 0.88) !important;
        }

        .toggle-password {
            color: rgba(255, 255, 255, 0.75) !important;
        }

        .alert {
            color: #ffffff;
            border: 1px solid rgba(255,255,255,0.2);
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
        }

        .help-widget-trigger:hover,
        .help-widget-trigger:focus,
        .help-widget-trigger:active {
            color: #ffffff !important;
        }

        .help-widget-trigger:hover i,
        .help-widget-trigger:focus i,
        .help-widget-trigger:active i {
            color: #ffffff !important;
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
        }

        .help-widget-panel .accordion-button::after {
            filter: invert(1) brightness(1.5);
        }

        .help-widget-panel .accordion-body {
            color: rgba(255, 255, 255, 0.86);
            line-height: 1.65;
        }
        .position-relative .form-control.is-valid,
        .position-relative .form-control.is-invalid {
            /* Pushes the red/green checkmark to the left of the eye icon */
            background-position: right 45px center;
            /* Prevents the password text from typing underneath both icons */
            padding-right: 75px !important; 
        }

        @media (max-width: 768px) {
            .reg-header,
            .reg-body {
                padding: 28px;
            }

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

    <div class="reg-card">
        <div class="reg-header">
            <img src="assets/img/icon.png" alt="Logo" height="60" class="mb-3" style="filter: brightness(0) invert(1);">
            <h3 class="fw-bold m-0">Login Account</h3>
            <p class="opacity-75 small mt-2">Schedule your visits for admissions, registrar services, and departmental inquiries with ease.</p>
        </div>

        <div class="reg-body">
            <form action="" method="POST" id="login-form" data-login-state="<?php echo htmlspecialchars(json_encode($loginState), ENT_QUOTES); ?>">
                <div class="row g-3">
                    <div class="col-12">
                        <label class="form-label">Email Address</label>
                        <input pattern="[^@\s]+@[A-Za-z0-9](?:[A-Za-z0-9\-]*[A-Za-z0-9])?(?:\.[A-Za-z0-9](?:[A-Za-z0-9\-]*[A-Za-z0-9])?)*\.[A-Za-z]{2,63}" maxlength="50" name="username" type="email" class="form-control" placeholder="" value="<?php echo htmlspecialchars($loginUsername, ENT_QUOTES); ?>" required>
                    </div>
                    
                    <div class="col-md-12">
                        <div class="d-flex justify-content-between align-items-center mb-1">
                            <label class="form-label m-0">Password</label>
                            <a href="forgot-password" class="text-decoration-none small" style="color: rgba(255,255,255,0.9); font-weight: 600;">Forgot Password?</a>
                        </div>
                        <div class="position-relative">
                            <input maxlength="72" type="password" class="form-control" name="password" id="login_password" required style="padding-right: 45px;">
                            <span class="toggle-password position-absolute" data-target="login_password" style="right: 15px; top: 50%; transform: translateY(-50%); cursor: pointer; color: #888;">
                                <i data-lucide="eye" width="20"></i>
                            </span>
                        </div>
                    </div>

                    <div class="col-12 mt-4">
                        <button type="submit" class="btn btn-register" name="submit" id="login-button" aria-describedby="login-attempt-status" <?php echo $loginState['locked'] ? 'disabled' : ''; ?>><span id="login-button-label"><?php echo $loginState['locked'] ? 'Try again in ' . gmdate('i:s', max(0, $loginState['unlock_at'] - $loginState['server_now'])) : 'Login Account'; ?></span></button>
                        <p id="login-attempt-status" class="login-attempt-status" role="status" aria-live="polite" <?php echo $loginState['attempts'] === 0 ? 'hidden' : ''; ?>><i data-lucide="circle-alert"></i><span id="login-attempt-message"><?php echo $loginState['locked'] ? 'All 5 attempts have been used. Please wait before trying again.' : ($loginState['attempts'] > 0 ? $loginState['remaining'] . ' attempt' . ($loginState['remaining'] === 1 ? '' : 's') . ' remaining' : ''); ?></span></p>
                    </div>
                    <div class="col-lg-12 m-b30" align="">
                    <?php
                    ?>
					</div>
                    <div class="col-12 text-center mt-4">
                        <p class="text-muted small">Doesn't have an account? <a href="register" class="back-link">Register here</a></p>
                        <hr class="my-4 opacity-25">
                        <a href="index" class="text-muted text-decoration-none small d-flex align-items-center justify-content-center gap-2">
                            <i data-lucide="arrow-left" size="14"></i> Back to Home
                        </a>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <button class="btn help-widget-trigger" type="button" data-bs-toggle="offcanvas" data-bs-target="#faqsWidgetLogin" aria-controls="faqsWidgetLogin" aria-label="Open FAQs">
        <i data-lucide="circle-help"></i>
        FAQs
    </button>

    <div class="offcanvas offcanvas-end help-widget-panel" tabindex="-1" id="faqsWidgetLogin" aria-labelledby="faqsWidgetLoginLabel">
        <div class="help-widget-head d-flex align-items-start justify-content-between gap-2">
            <div>
                <h5 id="faqsWidgetLoginLabel" class="d-flex align-items-center gap-2 m-0 fw-bold">
                    <i data-lucide="circle-help" size="18"></i>
                    Frequently Asked Questions
                </h5>
                <p class="help-widget-sub">Browse answers to frequently asked questions.</p>
            </div>
            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="offcanvas" aria-label="Close"></button>
        </div>
        <div class="offcanvas-body">
            <div class="accordion" id="loginFaqAccordion">
                <?php if (!empty($faqs)) { ?>
                    <?php foreach ($faqs as $index => $faq) {
                        $faq_number = $index + 1;
                        $heading_id = 'loginFaqHeading' . $faq_number;
                        $collapse_id = 'loginFaqCollapse' . $faq_number;
                        $is_first = ($index === 0);
                    ?>
                        <div class="accordion-item">
                            <h2 class="accordion-header" id="<?php echo $heading_id; ?>">
                                <button class="accordion-button <?php echo $is_first ? '' : 'collapsed'; ?>" type="button" data-bs-toggle="collapse" data-bs-target="#<?php echo $collapse_id; ?>" aria-expanded="<?php echo $is_first ? 'true' : 'false'; ?>" aria-controls="<?php echo $collapse_id; ?>">
                                    <?php echo htmlspecialchars($faq['question']); ?>
                                </button>
                            </h2>
                            <div id="<?php echo $collapse_id; ?>" class="accordion-collapse collapse <?php echo $is_first ? 'show' : ''; ?>" aria-labelledby="<?php echo $heading_id; ?>" data-bs-parent="#loginFaqAccordion">
                                <div class="accordion-body">
                                    <?php echo nl2br(htmlspecialchars($faq['answer'])); ?>
                                </div>
                            </div>
                        </div>
                    <?php } ?>
                <?php } else { ?>
                    <div class="accordion-item">
                        <div class="accordion-body">No FAQs are available at the moment.</div>
                    </div>
                <?php } ?>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script defer src="<?php echo htmlspecialchars(smartserve_asset_url('js/login-cooldown.js') . '?v=' . filemtime(__DIR__ . '/assets/js/login-cooldown.js'), ENT_QUOTES); ?>"></script>
    <script>
        lucide.createIcons();

        // Password Toggle Logic
        document.querySelectorAll('.toggle-password').forEach(btn => {
            btn.addEventListener('click', function() {
                const targetId = this.getAttribute('data-target');
                const input = document.getElementById(targetId);
                const type = input.getAttribute('type') === 'password' ? 'text' : 'password';
                input.setAttribute('type', type);
                
                // Swap icon
                this.innerHTML = `<i data-lucide="${type === 'password' ? 'eye' : 'eye-off'}" width="20"></i>`;
                lucide.createIcons();
            });
        });
    </script>
</body>
</html>
