<?php
    session_start();
    include('global/model.php');

    $model = new Model();
    $rows = $model->website_details();

    $web_name = "De La Salle Araneta University";
    $primary_color = "#087830"; 
    $secondary_color = "#14a44d";
    $faqs = $model->displayFaqs(1);

    if (!empty($rows)) {
        foreach ($rows as $row) {
            $web_name = trim($row['web_name'] ?? '') ?: 'SmartServe';
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
            color: rgba(255,255,255,0.55);
        }

        select.form-control,
        select.form-control option,
        select.form-control optgroup {
            color: #ffffff;
        }

        select.form-control option,
        select.form-control optgroup {
            color: #10201b;
        }

        .form-control:focus {
            border-color: rgba(255,255,255,0.45);
            box-shadow: 0 0 0 4px rgba(255, 255, 255, 0.08);
            background: rgba(72, 132, 113, 0.68);
            color: #ffffff;
            outline: none;
        }

        .form-control.pwd-id-input {
            padding: 8px;
            min-height: 58px;
            border-style: dashed;
            border-color: rgba(255,255,255,0.35);
            border-radius: 14px;
            background: rgba(255,255,255,0.07);
            font-size: 0.8rem;
            line-height: 1.5;
            cursor: pointer;
        }

        .form-control.pwd-id-input::file-selector-button {
            margin: 0 12px 0 0;
            padding: 10px 14px 10px 38px;
            border: 1px solid rgba(255,255,255,0.4);
            border-radius: 9px;
            background: #e6f3ed url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='18' height='18' viewBox='0 0 24 24' fill='none' stroke='%23005141' stroke-width='1.8' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpath d='M12 16V4m-4 4 4-4 4 4M4 15v5h16v-5'/%3E%3C/svg%3E") no-repeat 12px center;
            color: #005141;
            font-family: inherit;
            font-size: 0.8rem;
            font-weight: 600;
            cursor: pointer;
            transition: background-color .18s;
        }

        .form-control.pwd-id-input:hover:not(:disabled)::file-selector-button {
            background-color: #d1eadd;
        }

        .form-control.pwd-id-input:focus-visible {
            border-color: #b2ddca;
            outline: 2px solid rgba(178,221,202,0.5);
            outline-offset: 3px;
        }

        .pwd-upload-help {
            display: block;
            margin-top: 10px;
            font-size: 0.78rem;
            line-height: 1.7;
        }

        .pwd-upload-help strong {
            display: block;
            margin-bottom: 4px;
            font-size: 0.75rem;
            font-weight: 600;
            color: #c1e5d5;
        }

        .form-control.is-invalid {
            border-color: #dc3545;
            background-color: rgba(72, 132, 113, 0.68) !important;
            color: #ffffff !important;
            background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 12 12' width='12' height='12' fill='none' stroke='%23dc3545'%3e%3ccircle cx='6' cy='6' r='4.5'/%3e%3cpath stroke-linejoin='round' d='M5.8 3.6h.4L6 6.5z'/%3e%3ccircle cx='6' cy='8.2' r='.6' fill='%23dc3545' stroke='none'/%3e%3c/svg%3e");
            background-repeat: no-repeat;
            background-position: right calc(0.375em + 0.1875rem) center;
            background-size: calc(0.75em + 0.375rem) calc(0.75em + 0.375rem);
        }
        
        .form-control.is-valid {
            border-color: #198754;
            background-color: rgba(72, 132, 113, 0.68) !important;
            color: #ffffff !important;
            background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 8 8'%3e%3cpath fill='%23198754' d='M2.3 6.73L.6 4.53c-.4-1.04.46-1.4 1.1-.8l1.1 1.4 3.4-3.8c.6-.63 1.6-.27 1.2.7l-4 4.6c-.43.5-.8.4-1.1.1z'/%3e%3c/svg%3e");
            background-repeat: no-repeat;
            background-position: right calc(0.375em + 0.1875rem) center;
            background-size: calc(0.75em + 0.375rem) calc(0.75em + 0.375rem);
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

        .invalid-feedback {
            font-size: 0.8rem;
            color: #ff8f8f;
            font-weight: 600;
            display: none;
            margin-top: 5px;
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
            background: #f0f4f3;
            transform: translateY(-2px);
            color: var(--bg-darker-green);
            box-shadow: 0 10px 20px rgba(0, 0, 0, 0.22);
        }

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
            <h3 class="fw-bold m-0">Create Account</h3>
            <p class="opacity-75 small mt-2">Create your SmartServe account</p>
        </div>

        <div class="reg-body">
            <form enctype="multipart/form-data" action="" method="POST" id="registerForm">
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label">First Name</label>
                        <input maxlength="50" title="Letters, spaces, and hyphens only." pattern="[\p{L}\p{M} \-]+" type="text" class="form-control" name="fname" id="fname" placeholder="Enter First Name" required>
                        <div class="invalid-feedback">Letters, spaces, and hyphens only.</div>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Last Name</label>
                        <input maxlength="50" title="Letters, spaces, and hyphens only." pattern="[\p{L}\p{M} \-]+" type="text" class="form-control" name="lname" id="lname" placeholder="Enter Last Name" required>
                        <div class="invalid-feedback">Letters, spaces, and hyphens only.</div>
                    </div>
                    
                    <div class="col-12"><label class="form-label" for="mname">Middle Name (optional)</label><input name="mname" id="mname" type="text" class="form-control" maxlength="50" placeholder="Middle name, if any"></div>
                    <div class="form-group col-6">
                        <label class="form-label">Year</label>
                        <select class="form-control" name="year" id="year" required>
                            <option value="" disabled selected>Select Year</option>
                            <?php foreach (SmartServeValidation::YEARS as $yearOption): ?>
                            <option value="<?php echo $yearOption; ?>"><?php echo $yearOption; ?></option>
                            <?php endforeach; ?>
                        </select>
                        <div class="invalid-feedback">Please select a year.</div>
                    </div>
                    
                    <div class="form-group col-6">
                        <label class="form-label">Degree Program</label>
                        <select class="form-control" name="course" id="course" required>
                            <option value="" disabled selected>Select Course</option>
                            <optgroup label="VetMed & Agriculture">
                                <option value="Doctor of Veterinary Medicine">Doctor of Veterinary Medicine</option>
                                <option value="BS Agriculture">BS Agriculture</option>
                                <option value="BS Food Technology">BS Food Technology</option>
                            </optgroup>
                            <optgroup label="Business & Accountancy">
                                <option value="BS Accountancy">BS Accountancy</option>
                                <option value="BSBA Financial Management">BSBA Financial Management</option>
                                <option value="BSBA Human Resource Management">BSBA Human Resource Management</option>
                                <option value="BSBA Marketing Management">BSBA Marketing Management</option>
                                <option value="BS Hotel & Restaurant Management">BS Hotel & Restaurant Management</option>
                                <option value="BS Tourism Management">BS Tourism Management</option>
                            </optgroup>
                            <optgroup label="Arts, Sciences & Technology">
                                <option value="AB Psychology">AB Psychology</option>
                                <option value="BS Computer Science">BS Computer Science</option>
                                <option value="BS Computer Engineering">BS Computer Engineering</option>
                            </optgroup>
                            <optgroup label="Education">
                                <option value="Bachelor of Elementary Education">Bachelor of Elementary Education</option>
                                <option value="Bachelor of Secondary Education">Bachelor of Secondary Education</option>
                                <option value="Bachelor of Early Childhood Education">Bachelor of Early Childhood Education</option>
                            </optgroup>
                        </select>
                        <div class="invalid-feedback">Please select a program.</div>
                    </div>

                    <div class="col-12">
                        <label class="form-label">Phone Number</label>
                        <input title="Use 09XXXXXXXXX (11 characters) or +639XXXXXXXXX (13 characters)." pattern="(?:09[0-9]{9}|\+639[0-9]{9})" maxlength="13" type="tel" class="form-control" name="contact" id="contact" placeholder="09XXXXXXXXX" required>
                        <div class="invalid-feedback">Use 09XXXXXXXXX or +639XXXXXXXXX.</div>
                    </div>

                    <div class="col-md-12">
                        <label class="form-label">Address</label>
                        <input maxlength="120" type="text" class="form-control" name="address" id="address" placeholder="City, Province" required>
                        <div class="invalid-feedback">Address is required.</div>
                    </div>

                    <div class="col-12">
                        <label class="form-label">Email Address</label>
                        <input pattern="[^@\s]+@[A-Za-z0-9](?:[A-Za-z0-9\-]*[A-Za-z0-9])?(?:\.[A-Za-z0-9](?:[A-Za-z0-9\-]*[A-Za-z0-9])?)*\.[A-Za-z]{2,63}" maxlength="50" type="email" class="form-control" name="uname" id="uname" placeholder="email@example.com" required>
                        <div class="invalid-feedback">Please enter a valid email.</div>
                    </div>

                    <div class="col-md-6">
                        <label class="form-label">Password</label>
                        <div class="position-relative">
                            <input minlength="8" maxlength="72" type="password" class="form-control" name="password" id="password" placeholder="••••••••" pattern="(?=.*[A-Za-z])(?=.*\d)(?=.*[@$!%*#?&])[A-Za-z\d@$!%*#?&]{8,}" required style="padding-right: 40px;">
                            <span class="toggle-password position-absolute" data-target="password" style="right: 15px; top: 50%; transform: translateY(-50%); cursor: pointer; color: #888;">
                                <i data-lucide="eye" width="20"></i>
                            </span>
                        </div>
                        <div class="invalid-feedback">Min 8 chars, alphanumeric & special char.</div>
                    </div>
                    
                    <div class="col-md-6">
                        <label class="form-label">Confirm Password</label>
                        <div class="position-relative">
                            <input minlength="8" maxlength="72" type="password" class="form-control" name="confirm_password" id="confirm_password" placeholder="••••••••" required style="padding-right: 40px;">
                            <span class="toggle-password position-absolute" data-target="confirm_password" style="right: 15px; top: 50%; transform: translateY(-50%); cursor: pointer; color: #888;">
                                <i data-lucide="eye" width="20"></i>
                            </span>
                        </div>
                        <div class="invalid-feedback">Passwords do not match.</div>
                    </div>

                    <div class="col-12">
                        <label class="form-label" for="account_priority">Priority Type</label>
                        <select name="account_priority" id="account_priority" class="form-control">
                            <option value="N/A">N/A</option><option value="PWD">PWD</option><option value="Senior Citizen">Senior Citizen</option><option value="Pregnant">Pregnant</option>
                        </select>
                    </div>
                    <div class="col-12" id="pwd-id-wrap" hidden>
                        <label class="form-label" for="pwd_id">PWD ID Photo</label>
                        <input type="file" name="pwd_id" id="pwd_id" class="form-control pwd-id-input" accept="image/jpeg,image/png" aria-describedby="pwd-upload-help" disabled>
                        <small class="pwd-upload-help" id="pwd-upload-help"><strong>JPG or PNG &middot; Maximum 2 MB</strong>Upload a clear photo of your PWD ID. Admin will review it before enabling priority. You may book as a regular student while waiting.</small>
                    </div>
                    <script src="assets/js/priority.js?v=<?php echo filemtime(__DIR__ . '/assets/js/priority.js'); ?>" defer></script>
                    <div class="col-12 mt-4">
                        <button type="submit" name="submit_verify" class="btn btn-register">Create Account</button>
                    </div>
                    
                    <div class="col-lg-12 m-b30" align="">
                    <?php
                        if (isset($_POST['submit_verify'])) {
                            
                            $email   = strtolower(trim($_POST['uname'] ?? '')); 
                            $fname   = trim($_POST['fname'] ?? '');
                            $lname   = trim($_POST['lname'] ?? '');
                            $contact = $_POST['contact'] ?? '';
                            $address = ucwords(strtolower(preg_replace('/\s+/', ' ', trim($_POST['address'] ?? ''))));
                            $pword   = $_POST['password'] ?? '';
                            $cpword  = $_POST['confirm_password'] ?? '';
                            $year    = $_POST['year'] ?? '';
                            $course  = $_POST['course'] ?? '';

                            // Shared request validation checks all fields before this handler.

                            // 3. DATABASE INSERTION
                            $model = new Model();
                            if ($model->checkPhoneExists($contact)) {
                                smartserve_notify('Phone number is already registered.', 'danger', 'back'); exit;
                            }
                            $status = $model->fetchEmailID($email);

                            if ($status) {
                                smartserve_notify('This email is already registered. Please use a different one.', 'danger', 'back');
                                exit();
                            } 
                            else {
                                $fname = ucwords(strtolower($fname));
                                $lname = ucwords(strtolower($lname));
                                $hashed_pword = password_hash($pword, PASSWORD_DEFAULT);

                                require_once __DIR__ . '/global/priority.php';
                                $priorityType = $_POST['account_priority'] ?? 'N/A';
                                $idPath = null;
                                try {
                                    if ($priorityType === 'PWD') $idPath = smartserve_store_pwd_id($_FILES['pwd_id'] ?? null);
                                } catch (Throwable $e) {
                                    smartserve_notify('Unable to upload the PWD ID. Use a clear JPG or PNG up to 2 MB and try again.', 'danger', 'back'); exit;
                                }
                                $model->addRegAccount($fname, trim($_POST['mname'] ?? ''), $lname, $email, $hashed_pword, $contact, $address, "Student", "default", 3, $year, $course, 1, $priorityType, $idPath);
                            }
                        }
                    ?>
                    </div>

                    <div class="col-12 text-center mt-4">
                        <p class="text-muted small">Already have an account? <a href="login" class="back-link">Login here</a></p>
                        <hr class="my-4 opacity-25">
                        <a href="index" class="text-muted text-decoration-none small d-flex align-items-center justify-content-center gap-2">
                            <i data-lucide="arrow-left" size="14"></i> Back to Home
                        </a>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <button class="btn help-widget-trigger" type="button" data-bs-toggle="offcanvas" data-bs-target="#faqsWidgetRegister" aria-controls="faqsWidgetRegister" aria-label="Open FAQs">
        <i data-lucide="circle-help"></i>
        FAQs
    </button>

    <div class="offcanvas offcanvas-end help-widget-panel" tabindex="-1" id="faqsWidgetRegister" aria-labelledby="faqsWidgetRegisterLabel">
        <div class="help-widget-head d-flex align-items-start justify-content-between gap-2">
            <div>
                <h5 id="faqsWidgetRegisterLabel" class="d-flex align-items-center gap-2 m-0 fw-bold">
                    <i data-lucide="circle-help" size="18"></i>
                    Frequently Asked Questions
                </h5>
                <p class="help-widget-sub">Browse answers to frequently asked questions.</p>
            </div>
            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="offcanvas" aria-label="Close"></button>
        </div>
        <div class="offcanvas-body">
            <div class="accordion" id="registerFaqAccordion">
                <?php if (!empty($faqs)) { ?>
                    <?php foreach ($faqs as $index => $faq) {
                        $faq_number = $index + 1;
                        $heading_id = 'registerFaqHeading' . $faq_number;
                        $collapse_id = 'registerFaqCollapse' . $faq_number;
                        $is_first = ($index === 0);
                    ?>
                        <div class="accordion-item">
                            <h2 class="accordion-header" id="<?php echo $heading_id; ?>">
                                <button class="accordion-button <?php echo $is_first ? '' : 'collapsed'; ?>" type="button" data-bs-toggle="collapse" data-bs-target="#<?php echo $collapse_id; ?>" aria-expanded="<?php echo $is_first ? 'true' : 'false'; ?>" aria-controls="<?php echo $collapse_id; ?>">
                                    <?php echo htmlspecialchars($faq['question']); ?>
                                </button>
                            </h2>
                            <div id="<?php echo $collapse_id; ?>" class="accordion-collapse collapse <?php echo $is_first ? 'show' : ''; ?>" aria-labelledby="<?php echo $heading_id; ?>" data-bs-parent="#registerFaqAccordion">
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
