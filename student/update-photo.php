<?php
	include('../global/model.php');

	$model = new Model();
	$faqs = $model->displayFaqs(1);
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
					<h2 class="fw-bold" style="font-size: 40px;">Welcome, <span class="accent-text">STUDENT ACCESS</span></h2>
					<p class="" style="margin-top: -15px">Welcome to your profile overview</p>
				</div>

				<div class="row justify-content-center">
				    <div class="col-lg-12 col-md-12">
				        <div class="left">
				            <h2 class="m-b10 title-head" style="margin-bottom: 20px;">Update Photo</h2>
				        </div>

						<form class="contact-bx" method="POST" enctype="multipart/form-data">

									<div class="new-user-list">
										<div class="row align-items-center">
							                <!-- Profile Image -->
							                <div class="col-md-3 col-sm-12 text-center mb-3 mb-md-0">
							                    <img id="display-img-head" src="../assets/img/profile-pictures/<?php echo htmlspecialchars($account_photo); ?>.jpg" 
							                         alt="User" 
							                         class="img-fluid rounded-circle shadow"
							                         style="width: 200px; height: 200px; object-fit: cover;border: 3px solid #D3D3D3;">
							                    <br>
							                    <label class="col-form-label">Select New Photo<br>File Type: PNG or JPG</label><br>
												<input accept="image/jpeg,image/png" class="" type="file" name="image" style="border: 0px; padding: 0px;" onchange="readURL(this, 'head')" required>
							                </div>
										</div>
									</div>
									<hr>
									

									<button name="submit" type="submit" value="Submit" class="btn button-md radius-xl shadow-sm mb-2" style="background-color: #ffb21e; color: white; border: none;">
										<i class="ti-camera"></i> <span>SAVE PROFILE PHOTO</span>
									</button>
									
									<a href="javascript:history.back()" class="btn button-md radius-xl shadow-sm mb-2 mr-2" style="background-color: #6c757d; color: white; border: none;">
										<i class="ti-arrow-left"></i> <span>BACK</span>
									</a>
							

						</form>

<?php
if (isset($_POST['submit'])) {
    if (!isset($_FILES['image']) || $_FILES['image']['error'] == UPLOAD_ERR_NO_FILE) {
        smartserve_notify('No file uploaded.', 'danger', 'update-photo');
        exit;
    }

    $fileTmpPath = $_FILES['image']['tmp_name'];
    $fileSize    = $_FILES['image']['size'];
    $fileError   = $_FILES['image']['error'];

    // Allow only certain MIME types
    $allowedTypes = ['image/jpeg', 'image/png'];
    $detectedType = mime_content_type($fileTmpPath);

    // Max size (2MB)
    $maxSize = 2 * 1024 * 1024;

    if ($fileError !== UPLOAD_ERR_OK) {
        smartserve_notify('Upload error. Try again.', 'danger', 'update-photo');
        exit;
    }

    if (!in_array($detectedType, $allowedTypes)) {
        smartserve_notify('Invalid file type. Only JPG and PNG is allowed.', 'danger', 'update-photo');
        exit;
    }

    if ($fileSize > $maxSize) {
        smartserve_notify('File too large. Max 2MB allowed.', 'danger', 'update-photo');
        exit;
    }

    // Confirm it’s an actual image
    if (!getimagesize($fileTmpPath)) {
        smartserve_notify('File is not a valid image.', 'danger', 'update-photo');
        exit;
    }

    // Save with unique name
    $path        = '../assets/img/profile-pictures/';
    $unique      = time() . '_' . bin2hex(random_bytes(5));
    $extension   = ".jpg"; // always save as jpg
    $destination = $path . $unique . $extension;

    // 🔹 Delete old profile photo (skip if it's "default")
    if (!empty($account_photo) && $account_photo !== 'default') {
        $oldFile = $path . $account_photo . ".jpg";
        if (file_exists($oldFile)) {
            unlink($oldFile);
        }
    }

    if (move_uploaded_file($fileTmpPath, $destination)) {
        // Update DB with new filename (without extension)
        $model->updateAccountPhoto($unique, $_SESSION['sess']);
        echo "<script>window.open('my-profile', '_self');</script>";
    } else {
        smartserve_notify('Error saving file.', 'danger', 'update-photo');
    }
}
?>



					</div>
				</div>
			</div>
		</main>
		<div class="ttr-overlay"></div>
		<?php include 'main/faqs-widget.php'; ?>
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
		<script>
			function readURL(input, id) {
				if (input.files && input.files[0]) {
					var reader = new FileReader();

					reader.onload = function (e) {
						$('#display-img-' + id).attr('src', e.target.result);
					}

					reader.readAsDataURL(input.files[0]);
				}
			}
		</script>
	</body>

</html>