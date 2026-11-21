<?php

    session_start();

    include('global/model.php');



    $model = new Model();

    $rows = $model->website_details();



    // Default values

    $web_name = "De La Salle Araneta University";

    $primary_color = "#005141"; // Base dark green

    $secondary_color = "#ffffff"; // Pure white accent

    $faqs = $model->displayFaqs(1);



    if (!empty($rows)) {

        foreach ($rows as $row) {

            $web_name = $row['web_name'];

            $primary_color = $row['primary_color'];

            $secondary_color = $row['secondary_color'];

        }

    }



    // Helper function for Facebook-style dates

    if (!function_exists('timeAgo')) {

        function timeAgo($datetime) {

            $timestamp = is_numeric($datetime) ? $datetime : strtotime($datetime);

            $diff = time() - $timestamp;

            if ($diff < 60) return "a few seconds ago";

            elseif ($diff < 3600) return floor($diff / 60) . " mins ago";

            elseif ($diff < 86400) return floor($diff / 3600) . " hours ago";

            elseif ($diff < 2592000) return floor($diff / 86400) . " days ago";

            elseif ($diff < 31536000) return floor($diff / 2592000) . " months ago";

            else return floor($diff / 31536000) . " years ago";

        }

    }

?>

<!DOCTYPE html>

<html lang="en">

<head>
    <?php require_once __DIR__ . '/global/form-assets.php'; ?>

    <meta charset="UTF-8">

    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title><?php echo htmlspecialchars($web_name); ?> | Advisories</title>

    <link href="assets/img/icon.png" rel="icon">

    <link href="assets/img/icon.png" rel="apple-touch-icon">

    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;900&display=swap" rel="stylesheet">

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">

    <script src="https://unpkg.com/lucide@latest"></script>



    <style>

        :root {
            --bg-dark-green: #005141; 
            --bg-darker-green: #002d24; /* Deepened slightly for contrast */
            --accent-white: #ffffff;
        }



        body {

            font-family: 'Inter', sans-serif;

            background: linear-gradient(135deg, var(--bg-dark-green) 0%, var(--bg-darker-green) 100%);

            color: var(--text-light);

            min-height: 100vh;

            display: flex;

            flex-direction: column;

            margin: 0;

            overflow-x: hidden;

        }



        /* --- Modern Transparent Navbar --- */

        .navbar-custom {

            background: rgba(0, 0, 0, 0.15);

            backdrop-filter: blur(10px);

            border-bottom: 1px solid rgba(255, 255, 255, 0.05);

            padding: 1rem 0;

        }



        .brand-text {

            border-left: 2px solid var(--accent-white);

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

            background: rgba(255,255,255,0.1);

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

        }



        .btn-login:hover { 

            background: var(--accent-white); 

            color: var(--bg-darker-green); 

            border-color: var(--accent-white);

        }



        /* --- Layout Adjustments --- */

        .action-grid-wrapper { padding: 2rem 0; }



        /* --- Glassmorphism Newsfeed/Advisory Cards --- */

        .advisory-card {

            background: rgba(255, 255, 255, 0.03);

            border-radius: 20px;

            border: 1px solid rgba(255, 255, 255, 0.05);

            overflow: hidden;

            box-shadow: 0 10px 30px rgba(0,0,0,0.2);

            transition: all 0.3s ease;

            backdrop-filter: blur(10px);

            margin-bottom: 25px;

        }



        .advisory-card:hover {

            transform: translateY(-5px);

            box-shadow: 0 15px 35px rgba(0,0,0,0.3);

            border-color: rgba(255, 255, 255, 0.2);

        }



        .advisory-img {

            width: 100%;

            height: 100%;

            min-height: 250px;

            object-fit: cover;

            display: block;

            border-right: 1px solid rgba(255,255,255,0.05);

        }



        .img-placeholder {

            background: rgba(0,0,0,0.2);

            min-height: 250px;

        }



        .full-text { display: none; }

        

        .toggle-text { 

            cursor: pointer; 

            text-decoration: none; 

            color: var(--accent-white) !important;

            font-weight: 700;

            display: inline-block;

            margin-top: 5px;

        }

        

        .toggle-text:hover { text-decoration: underline; }



        /* --- Components (Synced with index.php) --- */

        .contact-pill {

            background: rgba(255, 255, 255, 0.03);

            padding: 25px;

            border-radius: 20px;

            border: 1px solid rgba(255, 255, 255, 0.05);

            text-align: center;

            height: 100%;

        }



        .btn-outline-white {

            border: 1px solid rgba(255, 255, 255, 0.5);

            color: var(--accent-white);

            border-radius: 30px;

            font-weight: 600;

            padding: 10px 24px;

            transition: 0.3s;

            text-decoration: none;

            display: block;

            margin-top: 15px;

        }



        .btn-outline-white:hover { 

            background: var(--accent-white); 

            color: var(--bg-darker-green); 

        }

        

        .btn-solid-white {

            background: var(--accent-white); 

            color: var(--bg-darker-green); 

            border-radius: 30px;

            font-weight: 700;

            padding: 10px 24px;

            transition: 0.3s;

            text-decoration: none;

            display: block;

            margin-top: 15px;

        }

        

        .btn-solid-white:hover {

            transform: translateY(-2px);

            box-shadow: 0 5px 15px rgba(255, 255, 255, 0.2);

        }



        /* --- Footer (Synced with index.php) --- */

        .main-footer { 

            background: rgba(0,0,0,0.3); 

            padding-top: 50px; 

            margin-top: auto;

            border-top: 1px solid rgba(255,255,255,0.05);

        }

        .footer-link-hd { color: var(--accent-white); font-weight: 700; text-transform: uppercase; font-size: 0.85rem; margin-bottom: 20px; letter-spacing: 1px; }

        .footer-links { list-style: none; padding: 0; }

        .footer-links a { color: rgba(255,255,255,0.6); text-decoration: none; transition: 0.2s; display: block; margin-bottom: 10px; }

        .footer-links a:hover { color: #ffffff; transform: translateX(5px); }

        

        .footer-bottom { 

            background: rgba(0,0,0,0.5); 

            padding: 20px 0; 

            font-size: 0.85rem; 

            margin-top: 40px; 

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
        }

        .help-widget-panel .accordion-button::after {
            filter: invert(1) brightness(1.5);
        }

        .help-widget-panel .accordion-body {
            color: rgba(255, 255, 255, 0.86);
            line-height: 1.65;
            font-size: 0.95rem;
        }



        @media (max-width: 768px) {

            .brand-text h1 { font-size: 0.95rem; }

            .advisory-img, .img-placeholder { min-height: 200px; height: 200px; border-right: none; border-bottom: 1px solid rgba(255,255,255,0.05); }

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

                        <h1><?php echo htmlspecialchars($web_name); ?></h1>

                    </div>

                </a>

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



        <div class="action-grid-wrapper pb-2 pt-4">
            <a href="index" class="text-decoration-none d-inline-flex align-items-center gap-2 px-4 py-2 rounded-pill" 
               style="background: rgba(255,255,255,0.05); color: #ffffff; border: 1px solid rgba(255,255,255,0.1); transition: all 0.3s ease;" 
               onmouseover="this.style.background='rgba(255,255,255,0.15)'; this.style.transform='translateX(-3px)';" 
               onmouseout="this.style.background='rgba(255,255,255,0.05)'; this.style.transform='translateX(0)';">
                <i data-lucide="arrow-left" size="18"></i>
                <span class="fw-medium">Back to Home</span>
            </a>
        </div>



        <section class="mb-5">

            <div class="d-flex align-items-center justify-content-between mb-4">

                <h3 class="fw-bold m-0 text-white" style="font-size: 2.2rem; letter-spacing: -0.5px;">Campus Advisories</h3>

            </div>



            <div class="row g-4">

                <?php

                    // Fetch active announcements

                    $rows = $model->displayAnnouncements(1, 1);

                    if (!empty($rows)) {

                        // Displaying all announcements for the advisories page

                        foreach ($rows as $row) {

                            $poster = htmlspecialchars($row['fname'] . " " . $row['lname']);

                ?>

                <div class="col-lg-12">

                    <div class="advisory-card">

                        <div class="row g-0 h-100">

                            <div class="col-md-5 img-placeholder d-flex align-items-center justify-content-center">

                                <?php if(!empty($row['image_unique'])): ?>

                                    <img src="assets/img/announcement/<?php echo htmlspecialchars($row['image_unique']); ?>.jpg" class="advisory-img" alt="Advisory Image" onerror="this.style.display='none'">

                                <?php else: ?>

                                    <i data-lucide="image" style="color: rgba(255,255,255,0.2); width: 64px; height: 64px;"></i>

                                <?php endif; ?>

                            </div>

                            

                            <div class="col-md-7">

                                <div class="card-body d-flex flex-column h-100 p-4 p-md-5">

                                    <div class="mb-3 d-flex align-items-center justify-content-between">

                                        <div>

                                            <h6 class="m-0 fw-bold text-white"><?php echo $poster; ?></h6>

                                            <small class="d-flex align-items-center gap-1 mt-1" style="color: rgba(255,255,255,0.5);">

                                                <i data-lucide="calendar" size="14"></i> 

                                                <?php echo timeAgo($row['date']); ?> • <?php echo date('M d, Y', strtotime($row['date'])); ?>

                                            </small>

                                        </div>

                                    </div>

                                    

                                    <h4 class="fw-bold mb-3 text-white"><?php echo htmlspecialchars($row['title']); ?></h4>

                                    

                                    <div class="flex-grow-1 mb-4" style="color: rgba(255,255,255,0.8); font-size: 0.95rem; line-height: 1.6;">

                                        <?php

                                            $details = $row['details'];

                                            if (strlen($details) > 180) {

                                                echo '<span class="short-text">'.nl2br(htmlspecialchars(substr($details, 0, 180))).'...</span>';

                                                echo '<span class="full-text">'.nl2br(htmlspecialchars($details)).'</span>';

                                                echo ' <a href="javascript:void(0);" onclick="toggleDetails(this)" class="toggle-text">See More</a>';

                                            } else {

                                                echo nl2br(htmlspecialchars($details));

                                            }

                                        ?>

                                    </div>

                                    

                                    <div class="pt-3" style="border-top: 1px solid rgba(255,255,255,0.1);">

                                        <span class="badge" style="background-color: rgba(255,255,255,0.1); border: 1px solid rgba(255,255,255,0.2); color: #ffffff; padding: 8px 15px; font-weight: 600; border-radius: 30px; letter-spacing: 0.5px;">

                                            <?php echo htmlspecialchars($row['course']); ?> Advisory

                                        </span>

                                    </div>

                                </div>

                            </div>

                        </div>

                    </div>

                </div>

                <?php 

                        }

                    } else {

                        echo '<div class="col-12 text-center py-5" style="color: rgba(255,255,255,0.6);">

                                <i data-lucide="bell-off" size="48" style="opacity: 0.5; margin-bottom: 15px;"></i>

                                <h4>No active advisories</h4>

                                <p class="mb-0">There are no recent announcements at this time.</p>

                              </div>';

                    }

                ?>

            </div>

        </section>



        <!--<div class="row g-4 mb-5">-->

        <!--    <div class="col-md-6">-->

        <!--        <div class="contact-pill">-->

        <!--            <h5 class="fw-bold text-white mb-2">Technical Support</h5>-->

        <!--            <p style="color: rgba(255,255,255,0.7); font-size: 0.9rem;">Encountering issues with the portal?</p>-->

        <!--            <a href="#" class="btn-outline-white">Contact IT Helpdesk</a>-->

        <!--        </div>-->

        <!--    </div>-->

        <!--    <div class="col-md-6">-->

        <!--        <div class="contact-pill">-->

        <!--            <h5 class="fw-bold text-white mb-2">General Inquiries</h5>-->

        <!--            <p style="color: rgba(255,255,255,0.7); font-size: 0.9rem;">Questions about university requirements?</p>-->

        <!--            <a href="#" class="btn-solid-white">Message Support</a>-->

        <!--        </div>-->

        <!--    </div>-->

        <!--</div>-->

    </main>

    <button class="btn help-widget-trigger" type="button" data-bs-toggle="offcanvas" data-bs-target="#faqsWidgetAdvisories" aria-controls="faqsWidgetAdvisories" aria-label="Open FAQs">
        <i data-lucide="circle-help"></i>
        FAQs
    </button>

    <div class="offcanvas offcanvas-end help-widget-panel" tabindex="-1" id="faqsWidgetAdvisories" aria-labelledby="faqsWidgetAdvisoriesLabel">
        <div class="help-widget-head d-flex align-items-start justify-content-between gap-2">
            <div>
                <h5 id="faqsWidgetAdvisoriesLabel" class="d-flex align-items-center gap-2">
                    <i data-lucide="circle-help" size="18"></i>
                    Frequently Asked Questions
                </h5>
                <p class="help-widget-sub">Browse answers to frequently asked questions.</p>
            </div>
            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="offcanvas" aria-label="Close"></button>
        </div>
        <div class="offcanvas-body">
            <div class="accordion" id="advisoriesFaqAccordion">
                <?php if (!empty($faqs)) { ?>
                    <?php foreach ($faqs as $index => $faq) {
                        $faq_number = $index + 1;
                        $heading_id = 'advisoriesFaqHeading' . $faq_number;
                        $collapse_id = 'advisoriesFaqCollapse' . $faq_number;
                        $is_first = ($index === 0);
                    ?>
                        <div class="accordion-item">
                            <h2 class="accordion-header" id="<?php echo $heading_id; ?>">
                                <button class="accordion-button <?php echo $is_first ? '' : 'collapsed'; ?>" type="button" data-bs-toggle="collapse" data-bs-target="#<?php echo $collapse_id; ?>" aria-expanded="<?php echo $is_first ? 'true' : 'false'; ?>" aria-controls="<?php echo $collapse_id; ?>">
                                    <?php echo htmlspecialchars($faq['question']); ?>
                                </button>
                            </h2>
                            <div id="<?php echo $collapse_id; ?>" class="accordion-collapse collapse <?php echo $is_first ? 'show' : ''; ?>" aria-labelledby="<?php echo $heading_id; ?>" data-bs-parent="#advisoriesFaqAccordion">
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

                    <p style="color: rgba(255,255,255,0.7); max-width: 400px; font-size: 0.9rem; line-height: 1.6;">

                        Streamlined digital solutions designed to empower and connect the Lasallian community.

                    </p>

                </div>

                <div class="col-lg-3 col-md-6">

                    <h5 class="footer-link-hd">Quick Links</h5>

                    <ul class="footer-links">
                        <li><a href="advisories" target="_blank">Advisories</a></li>
                        <li><a href="live-home">Live Queue Directory</a></li>
                        <li><a href="kiosk">Kiosk</a></li>
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

                <p class="mb-0" style="color: rgba(255,255,255,0.5);">&copy; <?php echo date('Y'); ?> <?php echo htmlspecialchars($web_name); ?>. All Rights Reserved.</p>

                <div class="mt-2 mt-md-0">

                    <a href="#" class="text-decoration-none me-3" style="color: rgba(255,255,255,0.5);">Privacy Policy</a>

                    <a href="#" class="text-decoration-none" style="color: rgba(255,255,255,0.5);">Terms of Use</a>

                </div>

            </div>

        </div>

    </footer>



    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

    <script>

        // Initialize Icons

        lucide.createIcons();



        // Vanilla JS Toggle for "See More"

        function toggleDetails(element) {

            const cardBody = element.closest('.card-body');

            const shortText = cardBody.querySelector('.short-text');

            const fullText = cardBody.querySelector('.full-text');



            if (fullText.style.display === 'none' || fullText.style.display === '') {

                shortText.style.display = 'none';

                fullText.style.display = 'inline';

                element.innerText = 'See Less';

            } else {

                shortText.style.display = 'inline';

                fullText.style.display = 'none';

                element.innerText = 'See More';

            }

        }

    </script>

</body>

</html>