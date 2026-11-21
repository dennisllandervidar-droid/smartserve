<!DOCTYPE html>
<html lang="en">
<head>
    <?php require_once __DIR__ . '/../global/form-assets.php'; ?>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Archived Website | V-Tech Solutions</title>
    
    <!-- Website Icon in Browser Tab -->
    <link rel="icon" href="icon.png" type="image/png">

    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600;700&display=swap');

        :root {
            --bg-deep: #050511;
            --bg-accent: #0f172a;
            --card-bg: rgba(16, 33, 54, 0.4);
            --card-border: rgba(94, 176, 239, 0.3);
            --text-main: #ffffff;
            --text-muted: #94a3b8;
            --accent-cyan: #38bdf8;
            --accent-blue: #3b82f6;
            --btn-glow: rgba(56, 189, 248, 0.5);
        }

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            font-family: 'Inter', sans-serif;
            background-color: var(--bg-deep);
            color: var(--text-main);
            display: flex;
            flex-direction: column;
            min-height: 100vh;
            overflow-x: hidden;
            overflow-y: auto; /* Allows scrolling on very small screens so content isn't cut off */
            position: relative;
        }

        /* Animated Background Elements */
        .bg-animation {
            position: fixed; /* Changed to fixed so it stays in place if scrolling happens */
            top: 0;
            left: 0;
            width: 100vw;
            height: 100vh;
            z-index: 0;
            overflow: hidden;
            background: 
                radial-gradient(circle at 15% 50%, rgba(59, 130, 246, 0.15) 0%, transparent 50%),
                radial-gradient(circle at 85% 30%, rgba(139, 92, 246, 0.15) 0%, transparent 50%);
        }

        .grid-overlay {
            position: absolute;
            width: 200%;
            height: 200%;
            top: -50%;
            left: -50%;
            background-image: 
                linear-gradient(to right, rgba(255,255,255,0.03) 1px, transparent 1px),
                linear-gradient(to bottom, rgba(255,255,255,0.03) 1px, transparent 1px);
            background-size: 40px 40px;
            transform: perspective(500px) rotateX(60deg) translateY(-100px) translateZ(-200px);
            animation: gridMove 20s linear infinite;
        }

        @keyframes gridMove {
            0% { transform: perspective(500px) rotateX(60deg) translateY(0) translateZ(-200px); }
            100% { transform: perspective(500px) rotateX(60deg) translateY(40px) translateZ(-200px); }
        }

        .container {
            position: relative;
            z-index: 10;
            max-width: 700px;
            width: 90%;
            margin: auto; /* Safely centers the container vertically and horizontally */
            padding: 60px 0; /* Ensures space at the top and bottom */
            display: flex;
            flex-direction: column;
            align-items: center;
            text-align: center;
        }

        /* Header & Logo */
        .header {
            margin-bottom: 20px;
            animation: float 6s ease-in-out infinite;
        }

        .logo-placeholder {
            max-width: 160px;
            height: auto;
            object-fit: contain;
            filter: brightness(0) invert(1) drop-shadow(0 0 10px rgba(255, 255, 255, 0.3));
        }

        @keyframes float {
            0% { transform: translateY(0px); }
            50% { transform: translateY(-8px); }
            100% { transform: translateY(0px); }
        }

        /* Glassmorphism Card */
        .archived-card {
            background: var(--card-bg);
            backdrop-filter: blur(16px);
            -webkit-backdrop-filter: blur(16px);
            border-radius: 20px;
            padding: 50px 40px;
            border: 1px solid var(--card-border);
            box-shadow: 
                0 20px 50px rgba(0,0,0,0.5),
                inset 0 0 20px rgba(56, 189, 248, 0.05),
                0 0 30px rgba(59, 130, 246, 0.2);
            animation: fadeUp 1s cubic-bezier(0.16, 1, 0.3, 1) forwards;
            opacity: 0;
            transform: translateY(30px);
        }

        @keyframes fadeUp {
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .archived-card h1 {
            font-size: 32px;
            font-weight: 700;
            margin-bottom: 20px;
            letter-spacing: 1.5px;
            text-transform: uppercase;
            background: linear-gradient(135deg, #ffffff 0%, #a5b4fc 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            text-shadow: 0 0 30px rgba(255,255,255,0.1);
        }

        .archived-card p {
            font-size: 16px;
            line-height: 1.8;
            color: var(--text-muted);
            margin-bottom: 40px;
            font-weight: 300;
        }

        .archived-card p a {
            color: var(--accent-cyan);
            text-decoration: none;
            font-weight: 400;
            transition: all 0.3s ease;
        }

        .archived-card p a:hover {
            color: #ffffff;
            text-shadow: 0 0 8px var(--btn-glow);
        }

        /* Buttons and Links */
        .btn-primary {
            display: inline-block;
            background: linear-gradient(90deg, rgba(56, 189, 248, 0.1), rgba(59, 130, 246, 0.1));
            color: #ffffff;
            padding: 16px 32px;
            border-radius: 30px;
            text-decoration: none;
            font-weight: 600;
            font-size: 15px;
            letter-spacing: 0.5px;
            margin-bottom: 25px;
            border: 1px solid var(--accent-cyan);
            box-shadow: 0 0 15px var(--btn-glow), inset 0 0 10px rgba(56, 189, 248, 0.2);
            transition: all 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
            position: relative;
            overflow: hidden;
            text-transform: uppercase;
        }

        .btn-primary::before {
            content: '';
            position: absolute;
            top: 0; left: -100%;
            width: 100%; height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255,255,255,0.2), transparent);
            transition: all 0.5s ease;
        }

        .btn-primary:hover {
            background: rgba(56, 189, 248, 0.2);
            box-shadow: 0 0 25px var(--btn-glow), inset 0 0 15px rgba(56, 189, 248, 0.4);
            transform: translateY(-2px) scale(1.02);
        }

        .btn-primary:hover::before {
            left: 100%;
        }

        /* Footer */
        .footer {
            margin-top: 40px;
            font-size: 13px;
            color: #64748b;
            line-height: 1.6;
            animation: fadeUp 1s 0.3s cubic-bezier(0.16, 1, 0.3, 1) forwards;
            opacity: 0;
        }

        .footer a {
            color: var(--accent-cyan);
            text-decoration: none;
            transition: color 0.3s ease;
        }

        .footer a:hover {
            color: #ffffff;
        }

        /* Glowing Orbs */
        .orb {
            position: fixed; /* Changed to fixed so they don't scroll weirdly */
            border-radius: 50%;
            filter: blur(60px);
            z-index: 1;
            opacity: 0.6;
            animation: pulseGlow 8s infinite alternate;
        }

        .orb-1 { width: 300px; height: 300px; background: rgba(56, 189, 248, 0.15); top: -100px; left: -100px; }
        .orb-2 { width: 400px; height: 400px; background: rgba(139, 92, 246, 0.15); bottom: -150px; right: -100px; animation-delay: -4s; }

        @keyframes pulseGlow {
            0% { transform: scale(1); opacity: 0.4; }
            100% { transform: scale(1.2); opacity: 0.7; }
        }

        /* Mobile responsiveness */
        @media (max-width: 600px) {
            .container {
                padding-top: 60px; /* Forces distance from the top of the screen */
                padding-bottom: 40px;
            }
            .header {
                margin-bottom: 16px;
            }
            .logo-placeholder {
                max-width: 120px;
                filter: brightness(0) invert(1) drop-shadow(0 0 3px rgba(255, 255, 255, 0.1));
            }
            .archived-card {
                padding: 40px 25px;
                border-radius: 16px;
            }
            .archived-card h1 {
                font-size: 24px;
            }
            .archived-card p {
                font-size: 14px;
                margin-bottom: 30px;
            }
            .btn-primary {
                padding: 14px 24px;
                font-size: 13px;
                width: 100%;
            }
        }
    </style>
</head>
<body>
    <!-- Animated Background -->
    <div class="bg-animation">
        <div class="grid-overlay"></div>
    </div>
    
    <div class="orb orb-1"></div>
    <div class="orb orb-2"></div>

    <div class="container">
        <!-- Logo Header -->
        <header class="header">
            <!-- Filter makes any image clear HD white -->
            <img src="icon.png" alt="V-Tech Solutions Logo" class="logo-placeholder">
        </header>

        <!-- Main Content Card (Glassmorphism) -->
        <main class="archived-card">
            <h1>This Website is Archived</h1>
            <p>
                The website you are looking for has been decommissioned<br>
                or archived. It is no longer publicly available on this URL.<br>
                For project data, documentation, or further details,<br>
                please contact the <a href="https://www.facebook.com/vtech.web.solutions" target="_blank">V-Tech Solutions team</a>.
            </p>
            <a href="https://www.facebook.com/vtech.web.solutions" target="_blank" class="btn-primary">Contact V-Tech Solutions Support</a>
        </main>

        <!-- Footer -->
        <footer class="footer">
            <p>
                &copy; 2026 V-Tech Solutions. All rights reserved.<br>
                Platform archived by <a href="https://www.facebook.com/vtech.web.solutions" target="_blank">V-Tech Solutions</a>.
            </p>
        </footer>
    </div>
</body>
</html>