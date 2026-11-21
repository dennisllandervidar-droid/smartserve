<?php
    ob_start();
    include('global/model.php'); // Corrected path for root directory
    $model = new Model();
    session_start();

    // Fetch all offices
    $offices = $model->displayOffices();
    
    // Fallback color if your global settings aren't loaded here
    $theme_color = isset($primary_color) ? $primary_color : '#FFB800'; 
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <?php require_once __DIR__ . '/global/form-assets.php'; ?>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>SmartServe Live Queue</title>
    <link rel="icon" href="assets/img/icon.png" type="image/x-icon" />

    <link rel="stylesheet" type="text/css" href="dashboard/assets/css/assets.css">
    <link rel="stylesheet" type="text/css" href="dashboard/assets/css/typography.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/themify-icons@1.0.0/css/themify-icons.css">
    
    <style>
        :root { 
            --primary: <?php echo $theme_color; ?>; 
            --bg-dark-green: #005141; 
            --bg-darker-green: #003d31;
            --text-dark: #2d3436;
            --text-muted: #636e72;
            --surface: #ffffff;
        }
        
        * {
            box-sizing: border-box;
        }

        body {
            /* Rich gradient using your custom green */
            background: linear-gradient(135deg, var(--bg-dark-green) 0%, var(--bg-darker-green) 100%);
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            min-height: 100vh;
            margin: 0;
            display: flex;
            flex-direction: column;
        }

        /* Forces the main content to take up available space, pushing the footer down */
        .main-wrapper {
            flex: 1 0 auto; 
            display: flex;
            flex-direction: column;
            align-items: center;
            padding-bottom: 60px;
        }

        /* Modern Header */
        .modern-header {
            padding: 8vh 20px 5vh;
            text-align: center;
            width: 100%;
        }

        .modern-header h1 {
            font-weight: 900;
            color: #ffffff; 
            font-size: clamp(2rem, 5vw, 3rem); /* Responsive font size */
            letter-spacing: -0.5px;
            margin-bottom: 10px;
        }

        .modern-header p {
            color: rgba(255, 255, 255, 0.8); 
            font-size: 1.1rem;
            font-weight: 500;
        }

        /* Ultra-Responsive CSS Grid */
        .directory-grid {
            display: grid;
            /* Auto-fit ensures it wraps beautifully, minmax 280px prevents mobile overflow */
            grid-template-columns: repeat(auto-fit, minmax(280px, 350px)); 
            justify-content: center;
            gap: 30px;
            width: 100%;
            max-width: 1200px;
            padding: 0 20px;
        }

        /* Sleek Card Design */
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
        }

        /* Animated Top Border Line */
        .office-card::before {
            content: '';
            position: absolute;
            top: 0; left: 0; right: 0; 
            height: 5px;
            background: #005141;
            transform: scaleX(0);
            transform-origin: left;
            transition: transform 0.4s ease;
        }

        .office-card:hover {
            transform: translateY(-8px);
            box-shadow: 0 25px 50px rgba(0,0,0,0.4);
        }

        .office-card:hover::before {
            transform: scaleX(1);
        }

        /* Typography inside Card */
        .office-name {
            font-size: 1.5rem;
            font-weight: 800;
            color: var(--text-dark);
            line-height: 1.3;
            margin-bottom: 8px;
        }

        .office-meta {
            color: var(--text-muted);
            font-size: 0.8rem;
            text-transform: uppercase;
            letter-spacing: 1.5px;
            font-weight: 700;
            margin-bottom: 5px;
        }

        /* Action Row at Bottom */
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
            font-size: 1.2rem;
            transition: transform 0.3s ease;
        }

        .office-card:hover .card-action i {
            transform: translateX(6px);
        }

        /* Sticky Footer */
        .modern-footer {
            flex-shrink: 0;
            text-align: center;
            padding: 20px;
            color: rgba(255, 255, 255, 0.5);
            font-size: 0.85rem;
            border-top: 1px solid rgba(255, 255, 255, 0.05);
            width: 100%;
        }
    </style>
</head>
<body>

    <div class="main-wrapper">
        <header class="modern-header">
            <img src="assets/img/icon.png" alt="Logo" class="header-logo" style="filter: brightness(0) invert(1);width: 100px;" onerror="this.style.display='none'">
         <br>
            <span style="font-size: 45px;font-weight: 950;color: white;">SmartServe Live Display</span>
            <p>Select a department to launch its queue monitor</p>
        </header>

        <div class="directory-grid">
            <?php
                if (!empty($offices)) {
                    foreach ($offices as $off) {
                        if ($off['status'] == 1) {
            ?>
            <a href="live?id=<?php echo htmlspecialchars($off['id']); ?>" class="office-card" target="_blank">
                <div>
                    <div class="office-meta">Public Monitor</div>
                    <div class="office-name"><?php echo htmlspecialchars($off['office_name']); ?></div>
                </div>
                
                <div class="card-action">
                    <span>Launch Display</span>
                    <i class="ti-arrow-right"></i>
                </div>
            </a>
            <?php
                        }
                    }
                } else {
                    echo '<div style="grid-column: 1 / -1; text-align: center; padding: 40px; color: rgba(255,255,255,0.7);">
                            <i class="ti-info-alt" style="font-size: 3rem; margin-bottom: 15px; display: block; color: #ffffff;"></i>
                            <h4 style="font-weight: 700; color: #ffffff;">No active departments found</h4>
                            <p>Please add a service in the Admin Panel to display it here.</p>
                          </div>';
                }
            ?>
        </div>
         <div style="margin-top: 35px;">
                <a href="index" style="display: inline-flex; align-items: center; gap: 8px; padding: 10px 24px; background: rgba(255,255,255,0.05); color: #ffffff; text-decoration: none; border-radius: 30px; border: 1px solid rgba(255,255,255,0.15); transition: all 0.3s ease; font-size: 0.95rem;" 
                   onmouseover="this.style.background='rgba(255,255,255,0.15)'; this.style.transform='translateX(-3px)';" 
                   onmouseout="this.style.background='rgba(255,255,255,0.05)'; this.style.transform='translateX(0)';">
                    <i class="ti-arrow-left"></i>
                    <span style="font-weight: 600;">Back to Home</span>
                </a>
            </div>
    </div>

    <footer class="modern-footer">
        &copy; <?php echo date('Y'); ?> SmartServe. All rights reserved.
    </footer>

    <script src="dashboard/assets/js/jquery.min.js"></script>
    <script src="dashboard/assets/vendors/bootstrap/js/bootstrap.min.js"></script>
</body>
</html>