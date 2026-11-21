<?php
    session_start();
    include('global/model.php');

    $model = new Model();
    $rows = $model->website_details();

    // Default values
    $web_name = "De La Salle Araneta University";
    $primary_color = "#087830"; 
    $secondary_color = "#14a44d";
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
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link href="assets/img/icon.png" rel="icon">
    <link href="assets/img/icon.png" rel="apple-touch-icon">

    <title><?php echo $web_name; ?> | Account Archived</title>
    
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://unpkg.com/lucide@latest"></script>

    <style type="text/css">
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

        /* --- Layout & Header --- */
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
        }

        .btn-green-outline {
            background: linear-gradient(135deg, rgba(255,255,255,0.1), rgba(255,255,255,0.02));
            border: 1px solid rgba(255,255,255,0.3);
            color: #ffffff;
            border-radius: 30px;
            font-weight: 600;
            padding: 8px 24px;
            transition: 0.3s;
            text-decoration: none;
        }

        .btn-green-outline:hover { 
            background: #ffffff;
            color: var(--bg-darker-green);
        }

        /* --- Archived Notice Card --- */
        .archived-card {
            background: linear-gradient(145deg, rgba(255,255,255,0.08), rgba(255,255,255,0.02));
            border-radius: 20px;
            box-shadow: 0 15px 35px rgba(0,0,0,0.25);
            padding: 50px 40px;
            border: 1px solid rgba(255, 255, 255, 0.12);
            max-width: 600px;
            width: 100%;
            margin: auto;
            text-align: center;
        }

        .icon-wrapper {
            width: 80px;
            height: 80px;
            background: rgba(220, 53, 69, 0.2);
            color: #ffc6ce;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 25px;
        }

        /* --- Footer --- */
        .main-footer { background: rgba(0, 45, 36, 0.7); color: rgba(255,255,255,0.9); padding-top: 60px; margin-top: auto; border-top: 1px solid rgba(255,255,255,0.05); }
        .footer-link-hd { color: white; font-weight: 600; text-transform: uppercase; font-size: 0.85rem; margin-bottom: 20px; }
        .footer-links { list-style: none; padding: 0; }
        .footer-links a { color: rgba(255,255,255,0.7); text-decoration: none; transition: 0.2s; }
        .footer-links a:hover { color: white; padding-left: 5px; }
        .footer-bottom { background: rgba(0,0,0,0.4); padding: 25px 0; font-size: 0.9rem; margin-top: 50px; }

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
            .brand-text h1 { font-size: 0.95rem; }
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
                <a href="index" class="d-flex align-items-center text-decoration-none">
                <img src="assets/img/icon.png" alt="Logo" height="50" style="filter: brightness(0) invert(1);">
                    <div class="brand-text">
                    <p class="text-uppercase m-0" style="font-size: 9px; color: rgba(255,255,255,0.6); font-weight: 700; letter-spacing: 1px;">SMARTSERVE</p>
                        <h1><?php echo $web_name; ?></h1>
                    </div>
                </a>
            </div>
            
            <div class="ms-auto">
                <a href="login" class="btn btn-green-outline d-flex align-items-center gap-2 py-2 px-4">
                    <i data-lucide="log-in" size="18"></i>
                    <span class="d-none d-sm-inline">Login</span>
                </a>
            </div>
        </div>
    </nav>

    <main class="container d-flex flex-grow-1 align-items-center justify-content-center my-5">
        <div class="archived-card">
            <div class="icon-wrapper" style="background-color: white!important;">
                <i data-lucide="lock" size="40" style="color: #ba574a;"></i>
            </div>
            <h2 class="fw-bold mb-3" style="color: #ffffff;">Account Archived</h2>
            <p class="mb-4" style="font-size: 1.05rem; line-height: 1.6; color: rgba(255,255,255,0.75);">
                Your account is currently archived and access to the system has been restricted. Please contact the school administrator for more information or to request account restoration.
            </p>
            
            <div class="d-grid gap-3 d-sm-flex justify-content-sm-center">
  
                <a href="index" class="btn btn-green-outline d-flex align-items-center justify-content-center gap-2">
                    <i data-lucide="arrow-left" size="18"></i> Return to Homepage
                </a>
            </div>
        </div>
    </main>

    <button class="btn help-widget-trigger" type="button" data-bs-toggle="offcanvas" data-bs-target="#faqsWidgetArchived" aria-controls="faqsWidgetArchived" aria-label="Open FAQs">
        <i data-lucide="circle-help"></i>
        FAQs
    </button>

    <div class="offcanvas offcanvas-end help-widget-panel" tabindex="-1" id="faqsWidgetArchived" aria-labelledby="faqsWidgetArchivedLabel">
        <div class="help-widget-head d-flex align-items-start justify-content-between gap-2">
            <div>
                <h5 id="faqsWidgetArchivedLabel" class="d-flex align-items-center gap-2 m-0 fw-bold">
                    <i data-lucide="circle-help" size="18"></i>
                    Frequently Asked Questions
                </h5>
                <p class="help-widget-sub">Browse answers to frequently asked questions.</p>
            </div>
            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="offcanvas" aria-label="Close"></button>
        </div>
        <div class="offcanvas-body">
            <div class="accordion" id="archivedFaqAccordion">
                <?php if (!empty($faqs)) { ?>
                    <?php foreach ($faqs as $index => $faq) {
                        $faq_number = $index + 1;
                        $heading_id = 'archivedFaqHeading' . $faq_number;
                        $collapse_id = 'archivedFaqCollapse' . $faq_number;
                        $is_first = ($index === 0);
                    ?>
                        <div class="accordion-item">
                            <h2 class="accordion-header" id="<?php echo $heading_id; ?>">
                                <button class="accordion-button <?php echo $is_first ? '' : 'collapsed'; ?>" type="button" data-bs-toggle="collapse" data-bs-target="#<?php echo $collapse_id; ?>" aria-expanded="<?php echo $is_first ? 'true' : 'false'; ?>" aria-controls="<?php echo $collapse_id; ?>">
                                    <?php echo htmlspecialchars($faq['question']); ?>
                                </button>
                            </h2>
                            <div id="<?php echo $collapse_id; ?>" class="accordion-collapse collapse <?php echo $is_first ? 'show' : ''; ?>" aria-labelledby="<?php echo $heading_id; ?>" data-bs-parent="#archivedFaqAccordion">
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
        // Initialize Icons
        lucide.createIcons();
    </script>
</body>
</html>