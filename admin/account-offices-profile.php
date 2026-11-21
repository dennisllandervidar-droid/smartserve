<?php
	include('../global/model.php');
	$model = new Model();
	include('main/department.php');
?>
<!DOCTYPE html>
<html lang="en">
	<head>
    <?php require_once __DIR__ . '/../global/form-assets.php'; ?>
		<meta charset="utf-8">
		<meta http-equiv="X-UA-Compatible" content="IE=edge">
		<meta name="keywords" content="" />
		<meta name="author" content="" />
		<meta name="robots" content="" />
		<meta name="description" content="" />
		<meta property="og:title" content="" />
		<meta property="og:description" content="" />
		<meta property="og:image" content="" />
		<meta name="format-detection" content="telephone=no">
		<link rel="icon" href="../assets/img/<?php echo $web_icon; ?>.png" type="image/x-icon" />
		<link rel="shortcut icon" type="image/x-icon" href="../assets/img/<?php echo $web_icon; ?>.png" />

		<title><?php echo $web_name; ?></title>
		
		<meta name="viewport" content="width=device-width, initial-scale=1">
		
		<link rel="stylesheet" type="text/css" href="../dashboard/assets/css/dataTables.bootstrap4.min.css">
		<link rel="stylesheet" type="text/css" href="../dashboard/assets/css/assets.css">
		<link rel="stylesheet" type="text/css" href="../dashboard/assets/vendors/calendar/fullcalendar.css">
		<link rel="stylesheet" type="text/css" href="../dashboard/assets/css/typography.css">
		<link rel="stylesheet" type="text/css" href="../dashboard/assets/css/shortcodes/shortcodes.css">
		<link rel="stylesheet" type="text/css" href="../dashboard/assets/css/style.css">
		<link rel="stylesheet" type="text/css" href="../dashboard/assets/css/dashboard.css">
		
		<style type="text/css">
			/* --- Cleaned up and Modernized CSS --- */
			:root {
				--primary-green: <?php echo $primary_color; ?>;
				--soft-green: #eef7f1;
				--surface-soft: #f6f9f7;
			}

			.modern-profile-card {
				background: #ffffff;
				border-radius: 20px;
				box-shadow: 0 10px 30px rgba(0,0,0,0.03);
				border: 1px solid rgba(0,0,0,0.05);
				overflow: hidden;
				margin-bottom: 30px;
			}

			/* Header Section */
			.profile-header {
				padding: 40px;
				border-bottom: 1px solid #f0f0f0;
				display: flex;
				align-items: center;
				gap: 30px;
				background: #ffffff;
			}

			.profile-avatar {
				width: 140px;
				height: 140px;
				object-fit: cover;
				border-radius: 50%;
				border: 4px solid var(--soft-green);
				box-shadow: 0 8px 20px rgba(0,0,0,0.06);
				flex-shrink: 0;
			}

			.profile-info h3 {
				font-weight: 700;
				color: #2d3436;
				margin-bottom: 8px;
				font-size: 1.8rem;
			}

			.profile-badge {
				background: var(--soft-green);
				color: var(--primary-green);
				padding: 6px 16px;
				border-radius: 20px;
				font-size: 0.85rem;
				font-weight: 600;
				display: inline-block;
				text-transform: uppercase;
				letter-spacing: 0.5px;
				margin-bottom: 15px;
			}

			/* Info Grid Section */
			.info-grid {
                display: grid;
                grid-template-columns: repeat(2, 1fr); /* Forces exactly 2 columns (like col-6) */
                gap: 20px;
                padding: 40px;
                background: #fafbfc;
            }
            
            /* Make sure it stacks on mobile (like col-12) */
            @media (max-width: 768px) {
                .info-grid {
                    grid-template-columns: 1fr;
                    padding: 20px;
                }
            }

			.info-item {
				background: #ffffff;
				padding: 20px;
				border-radius: 15px;
				border: 1px solid #f0f0f0;
				display: flex;
				align-items: flex-start;
				gap: 15px;
				transition: transform 0.2s ease, box-shadow 0.2s ease;
			}

			.info-item:hover {
				transform: translateY(-3px);
				box-shadow: 0 8px 20px rgba(0,0,0,0.04);
				border-color: var(--soft-green);
			}

			.info-icon {
				width: 45px;
				height: 45px;
				background: var(--soft-green);
				color: var(--primary-green);
				border-radius: 12px;
				display: flex;
				align-items: center;
				justify-content: center;
				font-size: 1.2rem;
				flex-shrink: 0;
			}

			.info-content {
				flex-grow: 1;
				overflow: hidden;
			}

			.info-content label {
				display: block;
				font-size: 0.75rem;
				color: #8795a1;
				text-transform: uppercase;
				font-weight: 700;
				margin-bottom: 4px;
				letter-spacing: 0.5px;
			}

			.info-content span {
				color: #2d3436;
				font-weight: 500;
				font-size: 1rem;
				display: block;
				word-break: break-word;
				line-height: 1.4;
			}

			.info-item-single {
				grid-column: 1 / -1;
				display: block;
			}

			.info-rows {
				display: grid;
				grid-template-columns: repeat(2, minmax(0, 1fr));
				gap: 12px 20px;
			}

			.info-row {
				border-bottom: 1px dashed #e8efeb;
				padding-bottom: 8px;
			}

			.info-row label {
				display: block;
				font-size: 0.72rem;
				font-weight: 700;
				text-transform: uppercase;
				letter-spacing: 0.5px;
				color: #83929a;
				margin-bottom: 3px;
			}

			.info-row span {
				display: block;
				font-size: 0.98rem;
				font-weight: 600;
				color: #2d3436;
				word-break: break-word;
			}

			.report-shell {
				background: #ffffff;
				border-radius: 20px;
				padding: 24px;
				border: 1px solid rgba(0,0,0,0.05);
				box-shadow: 0 10px 30px rgba(0,0,0,0.03);
			}

			.report-header {
				display: flex;
				justify-content: space-between;
				align-items: center;
				gap: 12px;
				margin-bottom: 18px;
				flex-wrap: wrap;
			}

			.report-title {
				margin: 0;
				font-size: 1.25rem;
				font-weight: 800;
				color: #2d3436;
			}

			.report-subtitle {
				margin: 0;
				font-size: 0.88rem;
				color: #708090;
			}

			.filter-wrapper {
				background: var(--surface-soft);
				border-radius: 14px;
				padding: 16px;
				border: 1px solid #e8efeb;
				margin-bottom: 18px;
			}

			.filter-wrapper form {
				display: flex;
				gap: 12px;
				align-items: flex-end;
				flex-wrap: wrap;
			}

			.filter-group {
				min-width: 180px;
				flex: 1;
			}

			.filter-group label {
				display: block;
				font-size: 0.78rem;
				font-weight: 700;
				color: #54606b;
				margin-bottom: 6px;
				text-transform: uppercase;
				letter-spacing: 0.4px;
			}

			.custom-input {
				border-radius: 10px;
				border: 2px solid #e5ece8;
				padding: 10px 12px;
				width: 100%;
				height: 46px;
				outline: none;
				font-weight: 500;
				color: #2d3436;
				background: #ffffff;
			}

			.custom-input:focus {
				border-color: var(--primary-green);
			}

			.status-select {
				border: none;
				color: #2d3436 !important;
				background-color: #ffffff !important;
				appearance: auto;
			}

			.status-select:focus {
				border: none;
				box-shadow: 0 0 0 2px rgba(0, 81, 65, 0.15);
				color: #2d3436 !important;
			}

			.status-select option {
				color: #2d3436 !important;
				background: #ffffff !important;
			}

			.status-select option:hover,
			.status-select option:checked,
			.status-select option:focus {
				color: #2d3436 !important;
				background: #eef7f1 !important;
			}

			/* Bootstrap-select fallback styles (if select is transformed by theme scripts) */
			.bootstrap-select.status-select,
			.bootstrap-select.status-select > .dropdown-toggle {
				height: 46px;
			}

			.bootstrap-select.status-select > .dropdown-toggle {
				border: none !important;
				background: #ffffff !important;
				color: #2d3436 !important;
				padding: 0 12px !important;
				display: flex;
				align-items: center;
			}

			.bootstrap-select.status-select > .dropdown-toggle .filter-option,
			.bootstrap-select.status-select > .dropdown-toggle .filter-option-inner,
			.bootstrap-select.status-select > .dropdown-toggle .filter-option-inner-inner {
				display: flex;
				align-items: center;
				height: 100%;
				line-height: 1;
			}

			.bootstrap-select.status-select > .dropdown-toggle .bs-caret,
			.bootstrap-select.status-select > .dropdown-toggle .caret {
				display: flex;
				align-items: center;
				height: 100%;
			}

			.bootstrap-select .dropdown-menu li a,
			.bootstrap-select .dropdown-menu li a span.text,
			.bootstrap-select .dropdown-menu li.selected a,
			.bootstrap-select .dropdown-menu li.active a {
				color: #2d3436 !important;
			}

			.bootstrap-select .dropdown-menu li a:hover,
			.bootstrap-select .dropdown-menu li.selected a:hover,
			.bootstrap-select .dropdown-menu li.active a:hover {
				color: #2d3436 !important;
				background: #eef7f1 !important;
			}

			.btn-filter {
				background: var(--primary-green);
				color: #ffffff;
				border: none;
				padding: 10px 20px;
				border-radius: 10px;
				font-weight: 700;
				height: 44px;
				cursor: pointer;
			}

			.report-metrics {
				display: grid;
				grid-template-columns: repeat(4, minmax(0, 1fr));
				gap: 12px;
				margin-bottom: 18px;
			}

			.metric-card {
				background: #ffffff;
				border: 1px solid #edf2ef;
				border-radius: 12px;
				padding: 14px;
			}

			.metric-card h3 {
				margin: 0;
				font-size: 1.35rem;
				font-weight: 800;
				color: #21302d;
			}

			.metric-card p {
				margin: 2px 0 0 0;
				font-size: 0.75rem;
				font-weight: 700;
				color: #6f7e79;
				text-transform: uppercase;
				letter-spacing: 0.5px;
			}

			.badge-type { padding: 4px 10px; border-radius: 6px; font-size: 0.75rem; font-weight: 700; }
			.type-online { background: #e2e8f0; color: #4a5568; }
			.type-walkin { background: #eef7f1; color: var(--primary-green); border: 1px solid #d1e8da; }

			.status-dot { width: 10px; height: 10px; border-radius: 50%; display: inline-block; margin-right: 6px; }
			.dot-completed { background: #28a745; box-shadow: 0 0 5px rgba(40,167,69,0.4); }
			.dot-noshow { background: #dc3545; box-shadow: 0 0 5px rgba(220,53,69,0.4); }
			.dot-serving { background: #0d6efd; box-shadow: 0 0 5px rgba(13,110,253,0.4); }
			.dot-pending { background: #ffc107; }

			.notes-box {
				background: #f8f9fa;
				padding: 8px;
				border-radius: 8px;
				border: 1px solid #edf1ef;
				font-size: 0.82rem;
				color: #4a5568;
			}

			/* Responsive */
			@media (max-width: 768px) {
				.profile-header {
					flex-direction: column;
					text-align: center;
					padding: 30px 20px;
				}
				.info-grid {
					padding: 20px;
				}
				.report-metrics {
					grid-template-columns: repeat(2, minmax(0, 1fr));
				}
				.info-rows {
					grid-template-columns: 1fr;
				}
			}
		</style>
	</head>

	<body class="ttr-opened-sidebar ttr-pinned-sidebar" style="background-color: #FCFCFC;">
		<?php
			$page = 'accounts';
			$secondnav = 'offices';
			include 'main/admin-nav.php'; 

			$account_id = isset($_GET['id']) ? $_GET['id'] : '';

			$view_department = $model->displayOfficeAccountDetails($account_id);
			if (!empty($view_department)) {
			    $view_account_fname        = $view_department['fname'] ?? '';
			    $view_account_mname        = $view_department['mname'] ?? '';
			    $view_account_lname        = $view_department['lname'] ?? '';
			    $view_account_position     = $view_department['position'] ?? 'Office';
			    $view_account_uname        = $view_department['uname'] ?? 'Not provided';
			    $view_account_contact      = $view_department['contact'] ?? 'Not provided';
			    $view_account_address      = $view_department['address'] ?? 'Not provided';
			    $view_account_photo        = $view_department['photo'] ?? '';
			    $view_account_access       = $view_department['access'] ?? '';
			    $view_account_date_created = $view_department['date_created'] ?? '';
			    $view_account_status       = $view_department['status'] ?? '';
                
                // Fetch Office ID and Window
			    $office_id                 = $view_department['course'] ?? '';
                $window_name               = $view_department['year'] ?? 'Not assigned';

                // Map the office ID back to the readable Office Name
                $office_name = 'Unknown Office';
                $all_offices = $model->displayOffices();
                if (!empty($all_offices)) {
                    foreach ($all_offices as $off) {
                        if ($off['id'] == $office_id) {
                            $office_name = $off['office_name'];
                            break;
                        }
                    }
                }

				$filter_start = isset($_GET['start_date']) && $_GET['start_date'] !== '' ? $_GET['start_date'] : date('Y-m-d');
				$filter_end = isset($_GET['end_date']) && $_GET['end_date'] !== '' ? $_GET['end_date'] : date('Y-m-d');
				$filter_status = isset($_GET['status']) && $_GET['status'] !== '' ? (int)$_GET['status'] : null;

				$office_reports = ['records' => [], 'metrics' => [
					'total_completed' => 0,
					'total_noshow' => 0,
					'avg_process' => 'N/A',
					'peak_hour' => 'N/A'
				]];

				if (!empty($office_id)) {
					$office_reports = $model->getAdminReports((int)$office_id, $filter_start, $filter_end, $filter_status);
				}
			}
			else {
				echo "<script>window.open('index', '_self');</script>";
			}
		?>
		
		<main class="ttr-wrapper" style="background-color: #FCFCFC;">
			<div class="container-fluid">
				<div class="db-breadcrumb">
					<h4 class="breadcrumb-title"><i class="ti ti-user"></i> Accounts</h4>
					<ul class="db-breadcrumb-list">
						<li>Staff Profile</li>
					</ul>
				</div>
				
				<div class="row justify-content-center">
				    <div class="col-lg-12 col-md-12">
						
						<div class="modern-profile-card">
							
							<div class="profile-header">
								<img src="../assets/img/profile-pictures/<?php echo htmlspecialchars($view_account_photo ?: 'default'); ?>.jpg" 
									 alt="User Avatar" 
									 class="profile-avatar">
								
								<div class="profile-info">
									<div class="profile-badge"><?php echo htmlspecialchars($view_account_position); ?> Account</div>
									<h3><?php echo htmlspecialchars($view_account_fname . ' ' . $view_account_lname); ?></h3>
									<p class="text-muted m-0" style="font-size: 0.95rem;">
										Designated Office: <?php echo htmlspecialchars($office_name); ?><br>
										Assigned Window: <?php echo htmlspecialchars($window_name); ?>
									</p>
								</div>
							</div>
						</div>

						<div class="report-shell">
							<div class="report-header">
								<div>
									<h5 class="report-title"><i class="ti-bar-chart"></i> <?php echo htmlspecialchars($office_name); ?> Reports</h5>
								</div>
							</div>

							<div class="filter-wrapper">
								<form method="GET" action="">
									<input type="hidden" name="id" value="<?php echo htmlspecialchars($account_id); ?>">
									<div class="filter-group">
										<label>Date From</label>
										<input type="date" name="start_date" class="custom-input" value="<?php echo htmlspecialchars($filter_start); ?>">
									</div>
									<div class="filter-group">
										<label>Date To</label>
										<input type="date" name="end_date" class="custom-input" value="<?php echo htmlspecialchars($filter_end); ?>">
									</div>
									<div class="filter-group" style="margin-top: -115px!important;">
										<label>Status</label>
										<select name="status" class="custom-input status-select" style="margin-top: -115px!important;">
											<option value="" <?php echo ($filter_status === null) ? 'selected' : ''; ?>>All Status</option>
											<option value="1" <?php echo ($filter_status === 1) ? 'selected' : ''; ?>>Pending</option>
											<option value="2" <?php echo ($filter_status === 2) ? 'selected' : ''; ?>>Serving</option>
											<option value="3" <?php echo ($filter_status === 3) ? 'selected' : ''; ?>>Completed</option>
											<option value="4" <?php echo ($filter_status === 4) ? 'selected' : ''; ?>>No Show</option>
										</select>
									</div>
									<div>
										<button type="submit" class="btn-filter"><i class="ti-search"></i> Apply Filter</button>
									</div>
								</form>
							</div>

							<div class="report-metrics">
								<div class="metric-card"><h3><?php echo (int)$office_reports['metrics']['total_completed']; ?></h3><p>Completed</p></div>
								<div class="metric-card"><h3><?php echo (int)$office_reports['metrics']['total_noshow']; ?></h3><p>No Shows</p></div>
								<div class="metric-card"><h3><?php echo htmlspecialchars($office_reports['metrics']['avg_process']); ?></h3><p>Avg Process Time</p></div>
								<div class="metric-card"><h3><?php echo htmlspecialchars($office_reports['metrics']['peak_hour']); ?></h3><p>Peak Hour</p></div>
							</div>

							<div class="table-responsive">
								<table id="officeReportsTable" class="table table-hover">
									<thead>
										<tr>
											<th>Queue / Type</th>
											<th>Client</th>
											<th>Status</th>
											<th>Timestamps</th>
											<th>Office Notes</th>
										</tr>
									</thead>
									<tbody>
										<?php foreach (($office_reports['records'] ?? []) as $row): ?>
											<?php
												$is_walkin = (isset($row['queue_number']) && substr($row['queue_number'], 0, 1) === 'W');
												$typeBadge = $is_walkin
													? '<span class="badge-type type-walkin"><i class="ti-direction"></i> Walk-in</span>'
													: '<span class="badge-type type-online"><i class="ti-world"></i> Online</span>';
												$display_name = !empty($row['guest_name']) ? htmlspecialchars($row['guest_name']) : htmlspecialchars(($row['fname'] ?? '') . ' ' . ($row['lname'] ?? ''));
												$statusDisplay = '';
												if ((int)$row['status'] === 3) {
													$statusDisplay = '<span class="status-dot dot-completed"></span> Completed';
												} elseif ((int)$row['status'] === 4) {
													$statusDisplay = '<span class="status-dot dot-noshow"></span> No Show';
												} elseif ((int)$row['status'] === 2) {
													$statusDisplay = '<span class="status-dot dot-serving"></span> Serving';
												} else {
													$statusDisplay = '<span class="status-dot dot-pending"></span> Pending';
												}
												$schedTime = !empty($row['appointment_time']) ? date('h:i A', strtotime($row['appointment_time'])) : '--:--';
												$timeQueued = !empty($row['time_queued']) ? date('h:i A', strtotime($row['time_queued'])) : '--:--';
												$timeCompleted = !empty($row['time_completed']) ? date('h:i A', strtotime($row['time_completed'])) : '--:--';
											?>
											<tr>
												<td>
													<strong class="text-dark"><?php echo htmlspecialchars($row['queue_number'] ?? 'N/A'); ?></strong><br>
													<?php echo $typeBadge; ?>
												</td>
												<td>
													<strong style="color: var(--primary-green);"><?php echo $display_name; ?></strong><br>
													<small class="text-muted">Trk: <?php echo htmlspecialchars($row['tracking_no'] ?? ''); ?></small>
												</td>
												<td><strong><?php echo $statusDisplay; ?></strong></td>
												<td>
													<small class="text-muted d-block"><i class="ti-alarm-clock"></i> Sched: <strong><?php echo $schedTime; ?></strong></small>
													<small class="text-muted d-block"><i class="ti-arrow-down"></i> Queued: <strong><?php echo $timeQueued; ?></strong></small>
													<small class="text-muted d-block"><i class="ti-check"></i> Done: <strong><?php echo $timeCompleted; ?></strong></small>
												</td>
												<td style="max-width: 260px;">
													<?php if (!empty($row['office_notes'])): ?>
														<div class="notes-box"><?php echo nl2br(htmlspecialchars($row['office_notes'])); ?></div>
													<?php else: ?>
														<span class="text-muted" style="font-size: 0.8rem; font-style: italic;">No notes added.</span>
													<?php endif; ?>
												</td>
											</tr>
										<?php endforeach; ?>
									</tbody>
								</table>
							</div>
						</div>
					</div>
				</div>
			</div>
		</main>
		<div class="ttr-overlay"></div>
		
		<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
		<script src="../dashboard/assets/js/jquery.min.js"></script>
		<script src="../dashboard/assets/vendors/bootstrap/js/popper.min.js"></script>
		<script src="../dashboard/assets/vendors/bootstrap/js/bootstrap.min.js"></script>
		<script src="../dashboard/assets/vendors/bootstrap-select/bootstrap-select.min.js"></script>
		<script src="../dashboard/assets/vendors/bootstrap-touchspin/jquery.bootstrap-touchspin.js"></script>
		<script src="../dashboard/assets/vendors/magnific-popup/magnific-popup.js"></script>
		<script src="../dashboard/assets/vendors/counter/waypoints-min.js"></script>
		<script src="../dashboard/assets/vendors/counter/counterup.min.js"></script>
		<script src="../dashboard/assets/vendors/imagesloaded/imagesloaded.js"></script>
		<script src="../dashboard/assets/vendors/masonry/masonry.js"></script>
		<script src="../dashboard/assets/vendors/masonry/filter.js"></script>
		<script src="../dashboard/assets/vendors/owl-carousel/owl.carousel.js"></script>
		<script src='../dashboard/assets/vendors/scroll/scrollbar.min.js'></script>
		<script src="../dashboard/assets/js/functions.js"></script>
		<script src="../dashboard/assets/vendors/chart/chart.min.js"></script>
		<script src="../dashboard/assets/js/admin.js"></script>
		<script src='../dashboard/assets/vendors/calendar/moment.min.js'></script>
		<script src='../dashboard/assets/vendors/calendar/fullcalendar.js'></script>
		<script src="../dashboard/assets/js/jquery.dataTables.min.js"></script>
		<script src="../dashboard/assets/js/dataTables.bootstrap4.min.js"></script>
		<script>
			$(document).ready(function () {
				$('#officeReportsTable').DataTable({
					"order": [],
					"pageLength": 10,
					"language": {
						"emptyTable": "No appointment records found for this office and date range."
					}
				});
			});
		</script>
	</body>
</html>