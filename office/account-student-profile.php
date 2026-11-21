<?php
	include('../global/model.php');
	$model = new Model();
	include('main/department.php');
    require_once __DIR__ . '/../global/priority.php';
    $_SESSION['pwd_csrf'] ??= bin2hex(random_bytes(32));
    if (isset($_POST['pwd_decision'])) {
        if ((int)$_SESSION['access'] !== 1 || !hash_equals($_SESSION['pwd_csrf'], $_POST['csrf_token'] ?? '') || !SmartServeValidation::id($_POST['pwd_account_id'] ?? '')) { http_response_code(403); exit('Access denied.'); }
        $ok = $model->reviewPwdId((int)$_POST['pwd_account_id'], $_POST['pwd_decision'], (int)$_SESSION['sess']);
        smartserve_notify($ok ? 'PWD ID review saved.' : 'This ID is no longer pending review.', $ok ? 'success' : 'warning');
    }

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
			}
		</style>
	</head>

	<body class="ttr-opened-sidebar ttr-pinned-sidebar" style="background-color: #FCFCFC;">
		<?php
			$page = 'accounts';
			$secondnav = 'students';
			include 'main/admin-nav.php'; 

			$account_id = isset($_GET['id']) ? $_GET['id'] : '';

			$view_department = $model->displayStudentAccountDetails($account_id);
			if (!empty($view_department)) {
			    $view_account_fname        = $view_department['fname'] ?? '';
			    $view_account_mname        = $view_department['mname'] ?? '';
			    $view_account_lname        = $view_department['lname'] ?? '';
			    $view_account_position     = $view_department['position'] ?? 'Student';
			    $view_account_uname        = $view_department['uname'] ?? 'Not provided';
			    $view_account_contact      = $view_department['contact'] ?? 'Not provided';
			    $view_account_address      = $view_department['address'] ?? 'Not provided';
			    $view_account_photo        = $view_department['photo'] ?? '';
			    $view_account_access       = $view_department['access'] ?? '';
			    $view_account_date_created = $view_department['date_created'] ?? '';
			    $view_account_status       = $view_department['status'] ?? '';
			    $course                    = $view_department['course'] ?? 'N/A';
			    $year                      = $view_department['year'] ?? 'N/A';
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
						<li>Student Profile</li>
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
										Member since <?php echo date('F Y', strtotime($view_account_date_created ?: 'now')); ?>
									</p>
								</div>
							</div>

							<?php smartserve_pwd_card($view_department, false); ?>
                            <div class="info-grid">
								
								<div class="info-item">
									<div class="info-icon"><i class="ti-calendar"></i></div>
									<div class="info-content">
										<label>Year Level</label>
										<span><?php echo htmlspecialchars($year); ?></span>
									</div>
								</div>
								
								<div class="info-item">
									<div class="info-icon"><i class="ti-book"></i></div>
									<div class="info-content">
										<label>Course Program</label>
										<span><?php echo htmlspecialchars($course); ?></span>
									</div>
								</div>
								
								<div class="info-item">
									<div class="info-icon"><i class="ti-email"></i></div>
									<div class="info-content">
										<label>Email Address</label>
										<span><?php echo htmlspecialchars($view_account_uname); ?></span>
									</div>
								</div>

								<div class="info-item">
									<div class="info-icon"><i class="ti-mobile"></i></div>
									<div class="info-content">
										<label>Contact Number</label>
										<span><?php echo htmlspecialchars($view_account_contact); ?></span>
									</div>
								</div>

								

								<div class="info-item" style="grid-column: 1 / -1;">
									<div class="info-icon"><i class="ti-location-pin"></i></div>
									<div class="info-content">
										<label>Address</label>
										<span><?php echo htmlspecialchars($view_account_address); ?></span>
									</div>
								</div>

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
	</body>
</html>