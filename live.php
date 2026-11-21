<?php
    ob_start();
    include('global/model.php'); 
    $model = new Model();
    session_start();

    $office_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
    
    if ($office_id === 0) {
        echo "<script>window.location.href='live-home';</script>";
        exit;
    }

    $today_date = date('Y-m-d');
    $current_time_display = date('h:i A');
    $current_time_24 = date('H:i:s');
    $is_lunch_break_now = ($current_time_24 >= '12:00:00' && $current_time_24 < '13:00:00');
    $active_block_now = $model->getSlotBlockInfo($office_id, $today_date, $current_time_display);

    // --- AJAX ENDPOINT FOR REAL-TIME POLLING ---
    if (isset($_GET['ajax']) && $_GET['ajax'] == 1) {
        header('Content-Type: application/json');

        $today_date = date('Y-m-d');
        $current_time_display = date('h:i A');
        $current_time_24 = date('H:i:s');
        $is_lunch_break_now = ($current_time_24 >= '12:00:00' && $current_time_24 < '13:00:00');
        $active_block_now = $model->getSlotBlockInfo($office_id, $today_date, $current_time_display);
        
        $today_queue = $model->getTodayQueue($office_id);
        $serving = []; 
        $waiting = [];
        
        if (!empty($today_queue)) {
            foreach ($today_queue as $q) {
                // 2 = Currently Serving
                if ($q['status'] == 2 && !empty($q['served_at_window'])) {
                    $serving[$q['served_at_window']][] = $q; 
                } 
                // 1 = Waiting in Line
                elseif ($q['status'] == 1) {
                    $waiting[] = $q;
                }
            }
        }
        
        ob_clean(); 
        echo json_encode([
            'serving' => $serving,
            'waiting' => $waiting,
            'blocked_now' => !empty($active_block_now),
            'block_reason' => !empty($active_block_now) ? ($active_block_now['reason'] ?? 'Unavailable') : '',
            'block_note' => !empty($active_block_now) ? ($active_block_now['note'] ?? '') : '',
            'lunch_break_now' => $is_lunch_break_now
        ]);
        exit;
    }

    // --- NORMAL PAGE LOAD ---
    $offices = $model->displayOffices();
    $office_name = "Unknown Office";
    if (!empty($offices)) {
        foreach ($offices as $off) {
            if ($off['id'] == $office_id) {
                $office_name = $off['office_name'];
                break;
            }
        }
    }

    // Fetch Active Windows for this specific Office
    $active_windows = $model->getAccountsByOffice($office_id);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <?php require_once __DIR__ . '/global/form-assets.php'; ?>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?php echo htmlspecialchars($office_name); ?> | SmartServe Live Queue</title>
    <link rel="icon" href="assets/img/icon.png" type="image/x-icon" />

    <link rel="stylesheet" type="text/css" href="dashboard/assets/css/typography.css">
    
    <style>
        :root { 
            --bg-dark-green: #005141; 
            --bg-darker-green: #003d31;
            --accent-light: #eef7f1;
            --surface: #ffffff;
            --text-dark: #2d3436;
        }
        
        * { box-sizing: border-box; }

        body {
            background: linear-gradient(135deg, var(--bg-dark-green) 0%, var(--bg-darker-green) 100%);
            font-family: 'Inter', 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            min-height: 100vh;
            margin: 0;
            display: flex;
            flex-direction: column;
            overflow: hidden; 
            cursor: pointer; 
        }

        /* --- Header --- */
        .tv-header {
            padding: 25px 40px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-bottom: 1px solid rgba(255,255,255,0.1);
            background: rgba(0,0,0,0.1);
        }

        .header-left h1 { 
            margin: 0; 
            color: #ffffff; 
            font-size: 2.5rem; 
            font-weight: 900; 
            letter-spacing: 1px; 
            text-transform: uppercase; 
            font-family: 'Inter', 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        .header-left p { margin: 5px 0 0 0; color: var(--accent-light); font-size: 1.2rem; font-weight: 600; letter-spacing: 2px; text-transform: uppercase; }
        .header-right { text-align: right; color: #ffffff; }
        .time-display { font-size: 3rem; font-weight: 800; line-height: 1; margin-bottom: 5px; }
        .date-display { font-size: 1.2rem; color: rgba(255,255,255,0.7); font-weight: 600; text-transform: uppercase; }

        /* --- Main Layout Grid --- */
        .tv-container {
            display: flex;
            flex: 1;
            padding: 30px;
            gap: 30px;
            height: calc(100vh - 120px);
        }

        /* --- Left Side: Active Windows (Now Serving) --- */
        .windows-section {
            flex: 2;
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(350px, 1fr));
            gap: 25px;
            align-content: start;
        }

        .window-card {
            background: var(--surface);
            border-radius: 20px;
            padding: 30px;
            text-align: center;
            box-shadow: 0 15px 35px rgba(0,0,0,0.3);
            border: 4px solid transparent;
            transition: all 0.3s ease;
            display: flex;
            flex-direction: column;
            justify-content: center;
            min-height: 300px;
        }

        .window-card.disabled-window {
            opacity: 0.6;
            filter: grayscale(0.25);
            border-color: #f3caca;
        }

        .window-card.flash { animation: pulse-border 2s ease-out; }

        @keyframes pulse-border {
            0% { border-color: var(--surface); transform: scale(1.02); box-shadow: 0 0 50px rgba(255, 255, 255, 0.7); }
            100% { border-color: transparent; transform: scale(1); box-shadow: 0 15px 35px rgba(0,0,0,0.3); }
        }

        .window-name { font-size: 1.8rem; color: var(--text-dark); font-weight: 800; text-transform: uppercase; letter-spacing: 1px; margin-bottom: 10px; }
        .staff-label { font-size: 1rem; color: #636e72; font-weight: 600; margin-bottom: 20px; text-transform: uppercase; }
        .now-serving-label { background: #eef7f1; color: var(--bg-dark-green); display: inline-block; padding: 8px 20px; border-radius: 30px; font-weight: 800; font-size: 1.1rem; margin: 0 auto 15px; letter-spacing: 1px; }

        /* --- Dynamic Queue Numbers --- */
        .queue-number-wrapper {
            display: flex;
            flex-wrap: wrap;
            justify-content: center;
            align-items: center;
            gap: 10px;
            margin-top: 10px;
            min-height: 120px; 
        }

        .q-num-box {
            font-weight: 900;
            color: var(--bg-dark-green);
            line-height: 1;
            letter-spacing: -2px;
            font-size: clamp(2rem, 8vw, 5.5rem); 
            background: var(--accent-light);
            padding: 5px 15px;
            border-radius: 12px;
            border: 2px solid rgba(0, 81, 65, 0.1);
        }

        .q-num-box.small-text { font-size: clamp(1.5rem, 5vw, 3rem); padding: 5px 10px; }

        /* --- Right Side: Waiting List --- */
        .waiting-section { flex: 1; background: rgba(0,0,0,0.2); border-radius: 20px; border: 1px solid rgba(255,255,255,0.1); display: flex; flex-direction: column; overflow: hidden; }
        .waiting-header { background: var(--surface); color: var(--bg-dark-green); padding: 20px; text-align: center; font-size: 1.5rem; font-weight: 900; text-transform: uppercase; letter-spacing: 1px; }
        .waiting-list { padding: 20px; overflow-y: hidden; display: flex; flex-direction: column; gap: 15px; }
        .waiting-item { background: rgba(255,255,255,0.1); border-left: 5px solid var(--accent-light); padding: 20px; border-radius: 10px; display: flex; justify-content: space-between; align-items: center; }
        .waiting-item .w-left { display: flex; flex-direction: column; align-items: flex-start; gap: 10px; }
        .waiting-item .w-num { font-size: 2.2rem; font-weight: 800; color: #ffffff; display: flex; align-items: center; justify-content: center; min-width: 140px; text-align: center; white-space: nowrap; }
        .waiting-item .w-type { font-size: 2.2rem; color: var(--accent-light); font-weight: 700; text-transform: uppercase; line-height: 1; }
        .priority-live-badge { display: inline-flex; align-items: center; gap: 6px; padding: 4px 10px; border-radius: 4px; background: #ffe2e5; color: #e63c3c; font-size: 0.85rem; font-weight: 700; letter-spacing: 0.3px; }
        .priority-star { color: #ff6b6b; margin-left: 10px; font-size: 1.5rem; }

        .status-banner {
            border-radius: 14px;
            padding: 12px 18px;
            font-weight: 700;
            letter-spacing: 0.3px;
        }

        .status-banner.blocked {
            background: #ffe8e8;
            color: #9d1c1c;
            border: 1px solid #f6c6c6;
        }

        .status-banner.lunch {
            background: #fff4d9;
            color: #8b6400;
            border: 1px solid #f0dfb3;
            margin-top: 10px;
        }

        /* --- Audio Unlock Banner --- */
        #audioBanner { position: fixed; bottom: 10px; left: 50%; transform: translateX(-50%); background: rgba(0,0,0,0.6); color: rgba(255,255,255,0.6); padding: 8px 20px; border-radius: 20px; font-size: 0.9rem; pointer-events: none; transition: opacity 0.5s; }
    </style>
</head>
<body>

    <audio id="chimeSound" preload="auto">
        <source src="assets/audio/chime.mp3" type="audio/mpeg">
    </audio>

    <div class="tv-header">
        <div class="header-left">
            <h1><?php echo htmlspecialchars($office_name); ?></h1>
            <p>SmartServe</p>
        </div>
        <div class="header-right">
            <div class="time-display" id="clock">00:00:00 AM</div>
            <div class="date-display" id="date">Loading...</div>
        </div>
    </div>

    <div class="tv-container">
        
        <div class="windows-section">
            <div class="status-banner blocked" id="officeBlockBanner" style="grid-column: 1 / -1; margin: 0 0 10px 0; display: <?php echo !empty($active_block_now) ? 'block' : 'none'; ?>;">
                Office temporarily unavailable: <?php echo !empty($active_block_now) ? htmlspecialchars($active_block_now['reason']) : ''; ?>
                <?php if (!empty($active_block_now['note'])): ?>
                    | <?php echo htmlspecialchars($active_block_now['note']); ?>
                <?php endif; ?>
            </div>
            <div class="status-banner lunch" id="lunchBreakBanner" style="grid-column: 1 / -1; margin: 0 0 12px 0; display: <?php echo $is_lunch_break_now ? 'block' : 'none'; ?>;">
                Lunch Break (12:00 PM - 1:00 PM): all windows are on lunch break.
            </div>
            <?php
                if (!empty($active_windows)) {
                    foreach ($active_windows as $win) {
                        $win_name = htmlspecialchars($win['year']); 
                        $staff_name = htmlspecialchars($win['fname'] . ' ' . $win['lname']);
                        $hash_id = md5($win_name);
            ?>
            <div class="window-card <?php echo !empty($active_block_now) ? 'disabled-window' : ''; ?>" id="window-card-<?php echo $hash_id; ?>" data-window="<?php echo $win_name; ?>">
                <div class="window-name"><?php echo $win_name; ?></div>
                <div class="staff-label">Assisted by <?php echo $staff_name; ?></div>
                <div class="now-serving-label">NOW SERVING</div>
                <div class="queue-number-wrapper" id="qnum-wrapper-<?php echo $hash_id; ?>">
                    <div class="q-num-box" style="opacity:0.2;">----</div>
                </div>
            </div>
            <?php
                    }
                } else {
                    echo '<h2 style="color:white; width:100%; text-align:center; margin-top:100px;">No active windows available.</h2>';
                }
            ?>
        </div>

        <div class="waiting-section">
            <div class="waiting-header">Next in Line</div>
            <div class="waiting-list" id="waitingListContainer">
            </div>
            <div style="padding: 20px; text-align: center; border-top: 1px solid rgba(255,255,255,0.05); margin-top: auto; background: rgba(0,0,0,0.1);">
                <a href="live-home" style="display: inline-flex; align-items: center; gap: 8px; padding: 10px 24px; background: rgba(255,255,255,0.05); color: #ffffff; text-decoration: none; border-radius: 30px; border: 1px solid rgba(255,255,255,0.15); transition: all 0.3s ease; font-size: 0.95rem;" 
                   onmouseover="this.style.background='rgba(255,255,255,0.15)'; this.style.transform='translateX(-3px)';" 
                   onmouseout="this.style.background='rgba(255,255,255,0.05)'; this.style.transform='translateX(0)';">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="m12 19-7-7 7-7"/>
                        <path d="M19 12H5"/>
                    </svg>
                    <span style="font-weight: 600;">Back to Directory</span>
                </a>
            </div>
        </div>

    </div>

    <div id="audioBanner">Tap anywhere to enable audio chimes</div>

    <script src="dashboard/assets/js/jquery.min.js"></script>
    <script>
        // --- 1. Real-time Clock ---
        function updateClock() {
            const now = new Date();
            let hours = now.getHours();
            let minutes = now.getMinutes();
            let seconds = now.getSeconds();
            let ampm = hours >= 12 ? 'PM' : 'AM';
            
            hours = hours % 12;
            hours = hours ? hours : 12; 
            minutes = minutes < 10 ? '0' + minutes : minutes;
            seconds = seconds < 10 ? '0' + seconds : seconds;
            
            document.getElementById('clock').innerHTML = hours + ':' + minutes + ':' + seconds + ' ' + ampm;
            
            const options = { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' };
            document.getElementById('date').innerHTML = now.toLocaleDateString('en-US', options);
        }
        setInterval(updateClock, 1000);
        updateClock();

        // --- 2. Audio Unlock Logic ---
        let audioEnabled = false;
        const chime = document.getElementById('chimeSound');
        
        document.body.addEventListener('click', function() {
            if (!audioEnabled) {
                chime.play().then(() => {
                    chime.pause();
                    chime.currentTime = 0;
                    audioEnabled = true;
                    document.getElementById('audioBanner').style.opacity = '0';
                }).catch(err => console.log("Audio unlock failed:", err));
            }
        });

        // --- 3. AJAX Polling Engine ---
        let currentServingState = {};

        function fetchLiveQueue() {
            $.ajax({
                url: window.location.href + (window.location.href.includes('?') ? '&' : '?') + 'ajax=1',
                type: 'GET',
                dataType: 'json',
                success: function(data) {
                    const isBlockedNow = !!data.blocked_now;
                    const isLunchBreakNow = !!data.lunch_break_now;

                    const blockBanner = $('#officeBlockBanner');
                    const lunchBanner = $('#lunchBreakBanner');

                    if (isBlockedNow) {
                        const reason = data.block_reason ? data.block_reason : 'Unavailable';
                        const note = data.block_note ? (' | ' + data.block_note) : '';
                        blockBanner.text('Office temporarily unavailable: ' + reason).show();
                    } else {
                        blockBanner.hide();
                    }

                    if (isLunchBreakNow) {
                        lunchBanner.show();
                    } else {
                        lunchBanner.hide();
                    }

                    $('.window-card').toggleClass('disabled-window', isBlockedNow);
                    
                    // A. Update Active Windows (Supports Multiple Students)
                    $('.window-card').each(function() {
                        let winName = $(this).data('window');
                        let hashId = $(this).attr('id').replace('window-card-', '');
                        let qWrapper = $('#qnum-wrapper-' + hashId);
                        
                        if (data.serving && data.serving[winName] && data.serving[winName].length > 0) {
                            let students = data.serving[winName];
                            
                            // Create a signature to detect if the group of students has changed
                            let stateKey = students.map(s => s.queue_number).join('|');
                            
                            if (currentServingState[winName] !== stateKey) {
                                let html = '';
                                let sizeClass = (students.length > 3) ? 'small-text' : '';
                                
                                students.forEach(function(student) {
                                    html += `<div class="q-num-box ${sizeClass}">${student.queue_number}</div>`;
                                });

                                qWrapper.html(html);
                                currentServingState[winName] = stateKey;
                                
                                // Flash animation & play chime
                                $(this).removeClass('flash');
                                void this.offsetWidth; 
                                $(this).addClass('flash');
                                
                                if (audioEnabled) {
                                    chime.currentTime = 0;
                                    chime.play().catch(e => console.log(e));
                                }
                            }
                        } else {
                            // Reset to empty state
                            if (currentServingState[winName] !== null) {
                                qWrapper.html('<div class="q-num-box" style="opacity:0.2;">----</div>');
                                currentServingState[winName] = null;
                            }
                        }
                    });

                    // B. Update Waiting List (Max 6)
                   // B. Update Waiting List (Max 6)
                    let waitingHtml = '';
                    if (data.waiting && data.waiting.length > 0) {
                        let nextInLine = data.waiting.slice(0, 6);
                        nextInLine.forEach(function(w) {
                            let isPriority = (w.is_priority == 1) ? '<span class="priority-live-badge">★ ' + String(w.priority || 'Priority') + '</span>' : '';
                            
                            // STRICT CHECK: If is_walkin is 1, OR if they have a guest_name, it is definitely a Walk-in!
                            let isWalkinFlag = (w.is_walkin !== null && w.is_walkin !== undefined) ? String(w.is_walkin) : '0';
                            let hasGuestName = (w.guest_name && w.guest_name.trim() !== '');
                            let typeText = (isWalkinFlag === '1' || hasGuestName) ? 'Walk-in' : 'Online';
                            
                            waitingHtml += `
                                <div class="waiting-item">
                                    <div class="w-left">
                                        <div class="w-type">${typeText}</div>
                                        ${isPriority}
                                    </div>
                                    <div class="w-num">${w.queue_number}</div>
                                </div>
                            `;
                        });
                    } else {
                        waitingHtml = '<div style="text-align:center; color:rgba(255,255,255,0.5); padding: 40px 0;">Queue is empty</div>';
                    }
                    $('#waitingListContainer').html(waitingHtml);
                },
                error: function() {
                    console.log("Polling error...");
                }
            });
        }

        // Poll every 3 seconds
        setInterval(fetchLiveQueue, 3000);
        fetchLiveQueue();
        
    </script>
</body>
</html>