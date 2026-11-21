<?php
	include('../global/model.php');

	$model = new Model();
	include('main/department.php');


	if (isset($_POST['save_pass'])) {
	    $cpass = $_POST['cpass'] ?? '';
	    $npass = $_POST['npass'] ?? '';
	    $confirm = $_POST['rpass'] ?? '';

	    // Shared validation checks password strength and confirmation.


	    $model = new Model();
	    $model->updateAccountPassword($_SESSION['sess'], $cpass, $npass); 
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
		<link rel="stylesheet" type="text/css" href="../dashboard/assets/css/assets.css">
		<link rel="stylesheet" type="text/css" href="../dashboard/assets/vendors/calendar/fullcalendar.css">
		<link rel="stylesheet" type="text/css" href="../dashboard/assets/css/typography.css">
		<link rel="stylesheet" type="text/css" href="../dashboard/assets/css/shortcodes/shortcodes.css">
		<link rel="stylesheet" type="text/css" href="../dashboard/assets/css/style.css">
		<link rel="stylesheet" type="text/css" href="../dashboard/assets/css/dashboard.css">
	</head>
	<style type="text/css">
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
				<div class="row">
					<div class="col-lg-12 m-b30">
							<div class="left">
								<h2 class="m-b10 title-head">Change Password</h2>
							</div>

								<form class="edit-profile" method="POST">
									<div class="">
										<div class="form-group row">
											<label class="col-sm-2 col-form-label">Current Password</label>
											<div class="col-sm-7">
												<input maxlength="72" class="form-control" type="password" name="cpass" minlength="5" required>
											</div>
										</div>
										<div class="form-group row">
											<label class="col-sm-2 col-form-label">New Password</label>
											<div class="col-sm-7">
												<input minlength="8" maxlength="72" class="form-control" type="password" name="npass" id="password" required>
											</div>
										</div>
										<div class="form-group row">
											<label class="col-sm-2 col-form-label">Confirm Password</label>
											<div class="col-sm-7">
												<input minlength="8" maxlength="72" class="form-control" type="password" name="rpass" id="confirm_password" required>
											</div>
										</div>
									</div>
									<div class="row">
										<div class="col-sm-2">
											</div>
										<div class="col-sm-7">
											<label class="col-form-label" id="message"></label><br>
											
											<div class="d-flex flex-wrap">
												<button name="save_pass" type="submit" class="btn button-md radius-xl shadow-sm mb-2 mr-2" style="background-color: #007bff; color: white; border: none;">
													<i class="ti-lock"></i> <span>SAVE PASSWORD</span>
												</button>

												<a href="my-profile" class="btn button-md radius-xl shadow-sm mb-2" style="background-color: #6c757d; color: white; border: none;">
													<i class="ti-arrow-left"></i> <span>CANCEL</span>
												</a>
											</div>
										</div>
									</div>
								</form>
								

						
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
		<script src="../dashboard/assets/js/jquery.dataTables.min.js"></script>
		<script src="../dashboard/assets/js/dataTables.bootstrap4.min.js"></script>
		<script type="text/javascript">
			var password = document.getElementById("password"), confirm_password = document.getElementById("confirm_password");

			function validatePassword() {
				if(password.value != confirm_password.value) {
					confirm_password.setCustomValidity("Passwords Don't Match");
				} 

				else {
					confirm_password.setCustomValidity('');
				}
			}

			password.onchange = validatePassword;
			confirm_password.onkeyup = validatePassword;
		</script>
	</body>

</html>