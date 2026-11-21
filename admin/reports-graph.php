<?php
    ob_start();
    include('../global/model.php');
    $model = new Model();


    // Handle Filter Inputs
    $filter_office = isset($_GET['office_id']) && $_GET['office_id'] !== '' ? (int)$_GET['office_id'] : null;
    $filter_status = isset($_GET['status']) && $_GET['status'] !== '' ? (int)$_GET['status'] : null;
    
    // Default to today when date filters are not provided
    $filter_start = isset($_GET['start_date']) && $_GET['start_date'] !== '' ? $_GET['start_date'] : date('Y-m-d');
    $filter_end = isset($_GET['end_date']) && $_GET['end_date'] !== '' ? $_GET['end_date'] : date('Y-m-d');

    // Fetch the Graph Data
    $graph_data = $model->getGraphData($filter_office, $filter_start, $filter_end, $filter_status);
    
    // Convert arrays to JSON so Javascript can read them for the charts
    $days_labels_json = json_encode($graph_data['days_labels']);
    $days_data_json = json_encode($graph_data['days_data']);
    $hours_labels_json = json_encode($graph_data['hours_labels']);
    $hours_data_json = json_encode($graph_data['hours_data']);

    include('main/department.php');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <?php require_once __DIR__ . '/../global/form-assets.php'; ?>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="icon" href="../assets/img/<?php echo $web_icon; ?>.png" type="image/x-icon" />
    <title><?php echo $web_name; ?> | Peak Hours Graph</title>

    <link rel="stylesheet" type="text/css" href="../dashboard/assets/css/assets.css">
    <link rel="stylesheet" type="text/css" href="../dashboard/assets/css/typography.css">
    <link rel="stylesheet" type="text/css" href="../dashboard/assets/css/style.css">
    <link rel="stylesheet" type="text/css" href="../dashboard/assets/css/dashboard.css">
    
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

    <style type="text/css">
        :root { --primary-green: <?php echo $primary_color; ?>; --soft-green: #eef7f1; }
        
        .filter-wrapper { background: #fff; border-radius: 15px; padding: 20px; border: 1px solid #eee; margin-bottom: 25px; box-shadow: 0 4px 15px rgba(0,0,0,0.02); }
        .filter-wrapper form { display: flex; gap: 15px; align-items: flex-end; flex-wrap: wrap; }
        .filter-group { flex: 1; min-width: 200px; }
        .filter-group label { font-size: 0.85rem; font-weight: 700; color: #495057; margin-bottom: 5px; display: block; }
        .custom-input { border-radius: 10px; border: 2px solid #eee; padding: 10px 15px; width: 100%; outline: none; font-weight: 500; color: #4a5568; }
        .custom-input:focus { border-color: var(--primary-green); }
        .btn-filter { background: var(--primary-green); color: white; border: none; padding: 10px 25px; border-radius: 10px; font-weight: 600; height: 44px; transition: 0.3s; cursor: pointer; }
        
        .chart-card { background: #fff; border-radius: 20px; padding: 30px; box-shadow: 0 10px 30px rgba(0,0,0,0.03); border: 1px solid rgba(0,0,0,0.05); margin-bottom: 30px; }
        .chart-header { display: flex; align-items: center; gap: 15px; margin-bottom: 25px; border-bottom: 1px solid #f8f9fa; padding-bottom: 15px; }
        .chart-icon { width: 45px; height: 45px; border-radius: 10px; display: flex; align-items: center; justify-content: center; background: var(--soft-green); color: var(--primary-green); font-size: 20px; }
        .chart-header h4 { margin: 0; font-weight: 800; color: #2d3436; font-size: 1.3rem; }
        .chart-header p { margin: 0; color: #8795a1; font-size: 0.85rem; font-weight: 500; }
        
        .canvas-container { position: relative; height: 350px; width: 100%; }
    </style>
</head>

<body class="ttr-opened-sidebar ttr-pinned-sidebar" style="background-color: #FCFCFC;">
    <?php
        $page = 'reports';
        $secondnav = 'graph';
        include 'main/admin-nav.php'; 
    ?>

    <main class="ttr-wrapper" style="background-color: #FCFCFC;">
        <div class="container-fluid">
            <div class="db-breadcrumb">
                <h4 class="breadcrumb-title"><i class="ti ti-bar-chart"></i> Reports</h4>
                <ul class="db-breadcrumb-list"><li>Peak Hours Graph</li></ul>
            </div>
            <p class="text-muted">Graphs include Monday–Friday service visits within the selected dates. Counts use each service's own status; weekend records remain available in the records report.</p>
            
            <div class="row">
                <div class="col-12">
                    <div class="filter-wrapper">
                        <form method="GET" action="">
                            <div class="filter-group">
                                <label>Select Office</label>
                                <select name="office_id" class="custom-input">
                                    <option value="">All Offices</option>
                                    <?php
                                        $offices = $model->getOffices();
                                        foreach ($offices as $off) {
                                            $selected = ($filter_office == $off['id']) ? 'selected' : '';
                                            echo "<option value='{$off['id']}' {$selected}>".htmlspecialchars($off['office_name'])."</option>";
                                        }
                                    ?>
                                </select>
                            </div>
                            <div class="filter-group">
                                <label>Date From</label>
                                <input type="date" name="start_date" class="custom-input" value="<?php echo htmlspecialchars($filter_start); ?>">
                            </div>
                            <div class="filter-group">
                                <label>Date To</label>
                                <input type="date" name="end_date" class="custom-input" value="<?php echo htmlspecialchars($filter_end); ?>">
                            </div>
                            <div class="filter-group">
                                <label>Status</label>
                                <select name="status" class="custom-input">
                                    <option value="" <?php echo ($filter_status === null) ? 'selected' : ''; ?>>All Status</option>
                                    <option value="0" <?php echo ($filter_status === 0) ? 'selected' : ''; ?>>Pending arrival</option>
                                    <option value="1" <?php echo ($filter_status === 1) ? 'selected' : ''; ?>>Queued</option>
                                    <option value="2" <?php echo ($filter_status === 2) ? 'selected' : ''; ?>>Serving</option>
                                    <option value="3" <?php echo ($filter_status === 3) ? 'selected' : ''; ?>>Completed</option>
                                    <option value="4" <?php echo ($filter_status === 4) ? 'selected' : ''; ?>>Cancelled / No-show / Rescheduled</option>
                                </select>
                            </div>
                            <div>
                                <button type="submit" class="btn-filter"><i class="ti-search"></i> Generate</button>
                                <a href="reports-graph" class="btn btn-secondary radius-xl outline" style="height: 44px; margin-left: 10px; line-height: 30px;">Reset</a>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            <div class="row">
                <div class="col-lg-12">
                    <div class="chart-card">
                        <div class="chart-header">
                            <div class="chart-icon"><i class="ti-calendar"></i></div>
                            <div>
                                <h4>Weekly Traffic Volume</h4>
                                <p>Weekday service visits matching the selected filters, grouped by appointment day.</p>
                            </div>
                        </div>
                        <div class="canvas-container">
                            <canvas id="daysChart"></canvas>
                        </div>
                    </div>
                </div>

                <div class="col-lg-12">
                    <div class="chart-card">
                        <div class="chart-header">
                            <div class="chart-icon" style="background: #fff3cd; color: #856404;"><i class="ti-time"></i></div>
                            <div>
                                <h4>Peak Hours Analysis</h4>
                                <p>Matching service visits by check-in hour, or scheduled hour when no check-in was recorded.</p>
                            </div>
                        </div>
                        <div class="canvas-container">
                            <canvas id="hoursChart"></canvas>
                        </div>
                    </div>
                </div>
            </div>

        </div>
    </main>

    <div class="ttr-overlay"></div>

    <script src="../dashboard/assets/js/jquery.min.js"></script>
    <script src="../dashboard/assets/vendors/bootstrap/js/bootstrap.min.js"></script>
    <script src="../dashboard/assets/js/admin.js"></script>
    
    <script>
        // Keep low-volume charts readable without capping larger counts.
        function serviceVisitAxis(data) {
            return {
                title: { display: true, text: 'Number of service visits', color: '#4a5568', font: { weight: '600' } },
                beginAtZero: true,
                suggestedMax: Math.max(5, ...data),
                ticks: { precision: 0, maxTicksLimit: 8, color: '#8795a1', font: { weight: '600' } },
                grid: { borderDash: [4, 4], color: '#f1f3f5' }
            };
        }
        // ---------------------------------------------------------
        // CHART 1: DAYS OF THE WEEK
        // ---------------------------------------------------------
        const ctxDays = document.getElementById('daysChart').getContext('2d');
        
        // Dynamic PHP Data injected here
        const daysLabels = <?php echo $days_labels_json; ?>;
        const daysData = <?php echo $days_data_json; ?>;
        
        new Chart(ctxDays, {
            type: 'bar',
            data: {
                labels: daysLabels,
                datasets: [{
                    label: 'Service Visits',
                    data: daysData,
                    backgroundColor: 'rgba(0, 81, 65, 0.8)', // Primary Color
                    borderColor: 'rgba(0, 81, 65, 1)',
                    borderWidth: 1,
                    borderRadius: 6,
                    barPercentage: 0.6
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        backgroundColor: '#2d3436',
                        padding: 12,
                        titleFont: { size: 14, family: 'Arial' },
                        bodyFont: { size: 14, weight: 'bold', family: 'Arial' }
                    }
                },
                scales: {
                    y: serviceVisitAxis(daysData),
                    x: {
                        title: { display: true, text: 'Day of the week', color: '#4a5568', font: { weight: '600' } },
                        ticks: { color: '#4a5568', font: { weight: 'bold' } },
                        grid: { display: false }
                    }
                }
            }
        });

        // ---------------------------------------------------------
        // CHART 2: HOURLY PEAK TIMES
        // ---------------------------------------------------------
        const ctxHours = document.getElementById('hoursChart').getContext('2d');
        
        // Dynamic PHP Data injected here
        const hoursLabels = <?php echo $hours_labels_json; ?>;
        const hoursData = <?php echo $hours_data_json; ?>;
        
        new Chart(ctxHours, {
            type: 'line', // Line chart is better for showing time trends!
            data: {
                labels: hoursLabels,
                datasets: [{
                    label: 'Service Visits',
                    data: hoursData,
                    backgroundColor: 'rgba(255, 193, 7, 0.2)', // Yellow tint for fill
                    borderColor: 'rgba(255, 193, 7, 1)', // Solid Yellow line
                    borderWidth: 3,
                    pointBackgroundColor: '#fff',
                    pointBorderColor: 'rgba(255, 193, 7, 1)',
                    pointBorderWidth: 2,
                    pointRadius: 5,
                    pointHoverRadius: 7,
                    fill: true,
                    tension: 0.3 // Adds a smooth curve to the line
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        backgroundColor: '#2d3436',
                        padding: 12,
                        titleFont: { size: 14, family: 'Arial' },
                        bodyFont: { size: 14, weight: 'bold', family: 'Arial' }
                    }
                },
                scales: {
                    y: serviceVisitAxis(hoursData),
                    x: {
                        ticks: { color: '#4a5568', font: { weight: 'bold' } },
                        grid: { display: false }
                    }
                }
            }
        });
    </script>
</body>
</html>
