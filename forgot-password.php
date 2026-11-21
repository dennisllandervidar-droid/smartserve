<?php
    session_start();
    include('global/model.php');
    
    $model = new Model();
    $rows = $model->website_details();

    if (!empty($rows)) {
        foreach ($rows as $row) {
            $web_name = $row['web_name'];
            $primary_color = $row['primary_color'];
            $secondary_color = $row['secondary_color'];
        }
    }

    $faqs = $model->displayFaqs(1);

    $msg = '';
    $msg_type = '';

    // Step Management
    $step = isset($_SESSION['reset_step']) ? $_SESSION['reset_step'] : 1;

    // --- STEP 1: SEND OTP ---
    if (isset($_POST['send_otp'])) {
        $email = $_POST['email'] ?? '';
        
        if ($model->checkEmailExists($email)) {
            // Generate 6-digit OTP
            $otp = sprintf("%06d", random_int(0, 999999));
            $account = $model->displayBasicProfile($email)[0] ?? [];
            $message = [
                'title' => 'Reset your SmartServe password',
                'name' => trim(($account['fname'] ?? '') . ' ' . ($account['lname'] ?? '')),
                'intro' => 'We received a request to reset the password for your SmartServe account.',
                'highlight_label' => 'Password reset verification code',
                'highlight' => $otp,
                'paragraphs' => ['This code expires in 10 minutes. Enter it on the password recovery page. Never share this code with anyone.', 'If you did not request a password reset, you can safely ignore this email.'],
            ];
            try {
                smartserve_send_email($email, 'Password reset verification code | SmartServe', SmartServeEmailTemplate::render($message), SmartServeEmailTemplate::text($message));
                $_SESSION['reset_email'] = $email;
                $_SESSION['reset_otp'] = $otp;
                $_SESSION['reset_otp_expires'] = time() + 600;
                $_SESSION['reset_step'] = 2;
                header("Location: forgot-password");
                exit;
            } catch (Throwable $e) {
                unset($_SESSION['reset_email'], $_SESSION['reset_otp'], $_SESSION['reset_otp_expires']);
                $_SESSION['reset_step'] = 1;
                error_log('SmartServe password recovery email failed (' . get_class($e) . ').');
                $msg = 'We could not send your verification code. Please try again in a moment.';
                $msg_type = 'danger';
            }
        } else {
            $msg = "We couldn't find an account associated with that email.";
            $msg_type = 'danger';
        }
    }

    // --- STEP 2: VERIFY OTP ---
    if (isset($_POST['verify_otp'])) {
        $entered_otp = $_POST['otp'] ?? '';
        
        if (($_SESSION['reset_step'] ?? 1) === 2 && isset($_SESSION['reset_otp']) && ($_SESSION['reset_otp_expires'] ?? 0) >= time() && hash_equals($_SESSION['reset_otp'], $entered_otp)) {
            $_SESSION['reset_step'] = 3;
            header("Location: forgot-password");
            exit;
        } else {
            $msg = "Incorrect or expired verification code. Please try again or request a new code.";
            $msg_type = 'danger';
        }
    }

    // --- STEP 3: RESET PASSWORD ---
    if (isset($_POST['reset_password'])) {
        $new_pass = $_POST['new_password'];
        $confirm_pass = $_POST['confirm_password'];

        if (($_SESSION['reset_step'] ?? 1) !== 3 || empty($_SESSION['reset_email'])) {
            $msg = 'Please verify your recovery code before resetting your password.';
            $msg_type = 'danger';
        } elseif ($new_pass === $confirm_pass) {
            if ($model->updatePassword($_SESSION['reset_email'], $new_pass)) {
                // Clear sessions so they don't get stuck on the reset page
                unset($_SESSION['reset_step']);
                unset($_SESSION['reset_email']);
                unset($_SESSION['reset_otp'], $_SESSION['reset_otp_expires']);
                
                smartserve_notify('Password successfully reset! You can now login.', 'success', 'login');
                exit;
            } else {
                $msg = "System error updating password. Please contact support.";
                $msg_type = 'danger';
            }
        } else {
            $msg = "Passwords do not match!";
            $msg_type = 'danger';
        }
    }

    // Cancel / Restart Process
    if (isset($_GET['cancel'])) {
        unset($_SESSION['reset_step']);
        unset($_SESSION['reset_email']);
        unset($_SESSION['reset_otp'], $_SESSION['reset_otp_expires']);
        header("Location: forgot-password");
        exit;
    }
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <?php require_once __DIR__ . '/global/form-assets.php'; ?>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $web_name; ?> | Forgot Password</title>
    <link href="assets/img/icon.png" rel="icon">
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
            max-width: 500px;
            overflow: hidden;
            animation: fadeIn 0.5s ease-out;
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(10px); }
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

        .form-control:focus,
        .form-control:active,
        .form-control.is-valid,
        .form-control.is-invalid {
            border-color: rgba(255,255,255,0.45);
            box-shadow: 0 0 0 4px rgba(255, 255, 255, 0.08);
            background: rgba(72, 132, 113, 0.68) !important;
            color: #ffffff !important;
            outline: none;
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

        .reg-body,
        .reg-body p,
        .reg-body a,
        .reg-body small,
        .reg-body .text-muted,
        .reg-body .small {
            color: rgba(255, 255, 255, 0.88) !important;
        }

        .alert {
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
            <h3 class="fw-bold m-0">Password Recovery</h3>
            <p class="opacity-75 small mt-2">
                <?php 
                    if ($step == 1) echo "Enter your registered email address.";
                    if ($step == 2) echo "Enter the 6-digit code sent to " . htmlspecialchars($_SESSION['reset_email']);
                    if ($step == 3) echo "Create a strong new password.";
                ?>
            </p>
        </div>

        <div class="reg-body">
            <?php if (!empty($msg)): ?>
                <?php smartserve_notify($msg, $msg_type); ?>
            <?php endif; ?>

            <form action="" method="POST">
                <div class="row g-3">
                    
                    <?php if ($step == 1): ?>
                    <div class="col-12">
                        <label class="form-label">Email Address</label>
                        <input pattern="[^@\s]+@[A-Za-z0-9](?:[A-Za-z0-9\-]*[A-Za-z0-9])?(?:\.[A-Za-z0-9](?:[A-Za-z0-9\-]*[A-Za-z0-9])?)*\.[A-Za-z]{2,63}" maxlength="50" name="email" type="email" class="form-control" placeholder="Enter your email address" required>
                    </div>
                    <div class="col-12 mt-4">
                        <button type="submit" class="btn btn-register" name="send_otp">Send Recovery OTP</button>
                    </div>
                    <?php endif; ?>

                    <?php if ($step == 2): ?>
                    <div class="col-12 text-center">
                        <label class="form-label">6-Digit OTP Code</label>
                        <input inputmode="numeric" pattern="[0-9]{6}" name="otp" type="text" class="form-control text-center fw-bold fs-4" maxlength="6" placeholder="000000" required autocomplete="off" style="letter-spacing: 5px;">
                    </div>
                    <div class="col-12 mt-4">
                        <button type="submit" class="btn btn-register" name="verify_otp">Verify Code</button>
                    </div>
                    <?php endif; ?>

                    <?php if ($step == 3): ?>
                    <div class="col-12">
                        <label class="form-label">New Password</label>
                        <input minlength="8" maxlength="72" name="new_password" type="password" class="form-control" required>
                    </div>
                    <div class="col-12">
                        <label class="form-label">Confirm New Password</label>
                        <input minlength="8" maxlength="72" name="confirm_password" type="password" class="form-control" required>
                    </div>
                    <div class="col-12 mt-4">
                        <button type="submit" class="btn btn-register" name="reset_password">Save New Password</button>
                    </div>
                    <?php endif; ?>

                    <div class="col-12 text-center mt-4">
                        <hr class="my-4 opacity-25">
                        <a href="<?php echo ($step > 1) ? '?cancel=true' : 'login'; ?>" class="text-muted text-decoration-none small d-flex align-items-center justify-content-center gap-2">
                            <i data-lucide="arrow-left" size="14"></i> <?php echo ($step > 1) ? 'Start Over' : 'Back to Login'; ?>
                        </a>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <button class="btn help-widget-trigger" type="button" data-bs-toggle="offcanvas" data-bs-target="#faqsWidgetForgot" aria-controls="faqsWidgetForgot" aria-label="Open FAQs">
        <i data-lucide="circle-help"></i>
        FAQs
    </button>

    <div class="offcanvas offcanvas-end help-widget-panel" tabindex="-1" id="faqsWidgetForgot" aria-labelledby="faqsWidgetForgotLabel">
        <div class="help-widget-head d-flex align-items-start justify-content-between gap-2">
            <div>
                <h5 id="faqsWidgetForgotLabel" class="d-flex align-items-center gap-2 m-0 fw-bold">
                    <i data-lucide="circle-help" size="18"></i>
                    Frequently Asked Questions
                </h5>
                <p class="help-widget-sub">Browse answers to frequently asked questions.</p>
            </div>
            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="offcanvas" aria-label="Close"></button>
        </div>
        <div class="offcanvas-body">
            <div class="accordion" id="forgotFaqAccordion">
                <?php if (!empty($faqs)) { ?>
                    <?php foreach ($faqs as $index => $faq) {
                        $faq_number = $index + 1;
                        $heading_id = 'forgotFaqHeading' . $faq_number;
                        $collapse_id = 'forgotFaqCollapse' . $faq_number;
                        $is_first = ($index === 0);
                    ?>
                        <div class="accordion-item">
                            <h2 class="accordion-header" id="<?php echo $heading_id; ?>">
                                <button class="accordion-button <?php echo $is_first ? '' : 'collapsed'; ?>" type="button" data-bs-toggle="collapse" data-bs-target="#<?php echo $collapse_id; ?>" aria-expanded="<?php echo $is_first ? 'true' : 'false'; ?>" aria-controls="<?php echo $collapse_id; ?>">
                                    <?php echo htmlspecialchars($faq['question']); ?>
                                </button>
                            </h2>
                            <div id="<?php echo $collapse_id; ?>" class="accordion-collapse collapse <?php echo $is_first ? 'show' : ''; ?>" aria-labelledby="<?php echo $heading_id; ?>" data-bs-parent="#forgotFaqAccordion">
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
    <script>lucide.createIcons();</script>
</body>
</html>