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
		<link rel="stylesheet" type="text/css" href="../dashboard/assets/css/assets.css">
		<link rel="stylesheet" type="text/css" href="../dashboard/assets/vendors/calendar/fullcalendar.css">
		<link rel="stylesheet" type="text/css" href="../dashboard/assets/css/typography.css">
		<link rel="stylesheet" type="text/css" href="../dashboard/assets/css/shortcodes/shortcodes.css">
		<link rel="stylesheet" type="text/css" href="../dashboard/assets/css/style.css">
		<link rel="stylesheet" type="text/css" href="../dashboard/assets/css/dashboard.css">
	</head>
	<style type="text/css">
		.btn.dropdown-toggle.btn-default:hover {
			color: #000!important;
		}

		.btn.dropdown-toggle.btn-default:focus {
			color: #000!important;
		}


		.widget-card .icon {
			position: absolute;
			top: auto;
			bottom: -20px;
			right: 5px;
			z-index: 0;
			font-size: 65px;
			color: rgba(0, 0, 0, 0.15);
		}
		:root {
            --accent-gold: #005141;
            --sidebar-bg: #D9D9D9;
            --sidebar-hover: #C4C4C4;
        }
		.welcome-section { border-bottom: 3px solid #eee; padding-bottom: 20px; margin-bottom: 30px; }
        .accent-text { color: var(--accent-gold); text-transform: uppercase; }
        .about-title { color: var(--accent-gold); font-weight: bold; margin-bottom: 20px; }
        .content-body { line-height: 1.6; text-align: justify; font-size: 0.95rem; }
	</style>
		<?php
				$page = 'my-profile';
				include 'main/admin-nav.php'; 
		?>
		<main class="ttr-wrapper" style="background-color: #FCFCFC;">
			<div class="container-fluid">
				<div class="welcome-section">
					<h2 class="fw-bold" style="font-size: 40px;">Welcome, <span class="accent-text">ADMINISTRATOR</span></h2>
					<p class="" style="margin-top: -15px">Welcome to your profile overview</p>
				</div>
				<div class="row justify-content-center">
				    <div class="col-lg-12 col-md-12">
				        <div class="new-user-list">
				            <div class="row align-items-center">
				                <!-- Profile Image -->
				                <div class="col-md-3 col-sm-12 text-center mb-3 mb-md-0">
				                    <img src="../assets/img/profile-pictures/<?php echo htmlspecialchars($account_photo); ?>.jpg" 
				                         alt="User" 
				                         class="img-fluid rounded-circle shadow"
				                         style="width: 200px; height: 200px; object-fit: cover;border: 3px solid #D3D3D3;">
				                </div>

				                <!-- Profile Details -->
				                <div class="col-md-9 col-sm-12">
				                    <h3 class="fw-bold mb-1">
				                        <?php echo htmlspecialchars($account_fname); ?> <?php echo htmlspecialchars($account_lname); ?>
				                    </h3>
				                    <p class="text-muted mb-2"><?php echo htmlspecialchars($account_position); ?></p>
				                    <hr class="mt-2 mb-3">
				                    <p class="mb-1"><strong>Address:</strong> <?php echo htmlspecialchars($account_address); ?></p>
				                    <p class="mb-1"><strong>Email:</strong> <?php echo htmlspecialchars($account_uname); ?></p>
				                    <p class="mb-1"><strong>Contact:</strong> <?php echo htmlspecialchars($account_contact); ?></p>
				                    <p class="mb-1"><strong>Status:</strong> <span class="badge badge-success">Active</span></p>
				                </div>
				            </div>
				        </div>

				        <hr>

				        <!-- Action Buttons -->
				        <div class="d-flex flex-wrap justify-content-start">
                
							<a href="update-photo" class="btn button-md radius-xl shadow-sm mb-2 mr-2" style="background-color: #ffb21e; color: white; border: none;">
								<i class="ti-camera"></i> <span>UPDATE PHOTO</span>
							</a>
							
							<a href="update-profile" class="btn button-md radius-xl shadow-sm mb-2 mr-2" style="background-color: #28a745; color: white; border: none;">
								<i class="ti-marker-alt"></i> <span>UPDATE MY PROFILE</span>
							</a>
							
							<a href="update-password" class="btn button-md radius-xl shadow-sm mb-2" style="background-color: #007bff; color: white; border: none;">
								<i class="ti-lock"></i> <span>UPDATE PASSWORD</span>
							</a>
							
						</div>

				        <div class="py-3"></div>
				    </div>
				</div>

			</div>
		</main>
		<div class="ttr-overlay"></div>
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
	</body>
</html>