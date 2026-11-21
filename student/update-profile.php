<?php
	include('../global/model.php');
	if (!isset($model) || !($model instanceof Model)) {
		$model = new Model();
	}
	$faqs = $model->displayFaqs(1);
	include('main/department.php');
    require_once '../global/priority.php';
    $priorityAccount = $model->displayStudentAccountDetails($_SESSION['sess']);

	if (isset($_POST['save_profile'])) {
	    // Sanitize inputs
	    $fname    = trim($_POST['new_fname'] ?? '');
	    $mname    = trim($_POST['new_mname'] ?? '');
	    $lname    = trim($_POST['new_lname'] ?? '');
	    $position = "Student";
	    $contact  = $_POST['new_contact'] ?? '';
	    $address  = trim($_POST['new_address'] ?? '');

	    // Shared request validation runs before this handler.

        // Compare the editable values before saving so a no-op is never reported as an update.
        $unchanged = [$fname, $mname, $lname, $contact, $address] === [
            trim((string)$account_fname), trim((string)$account_mname), trim((string)$account_lname),
            (string)$account_contact, trim((string)$account_address)
        ];
        $priorityType = $_POST['account_priority'] ?? ($priorityAccount['priority_type'] ?? 'N/A');
        $hasIdUpload = $priorityType === 'PWD' && ($_FILES['pwd_id']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE;
        $unchanged = $unchanged && $priorityType === ($priorityAccount['priority_type'] ?? 'N/A') && !$hasIdUpload;
        if ($unchanged) {
            smartserve_notify('No changes to save.', 'info', 'my-profile');
            exit;
        }

        if ($model->checkPhoneExists($contact, (int)$_SESSION['sess'])) {
            smartserve_notify('Phone number is already registered.', 'danger', 'back'); exit;
        }

        $idPath = null;
        try {
            if ($priorityType === 'PWD' && ($_FILES['pwd_id']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) $idPath = smartserve_store_pwd_id($_FILES['pwd_id']);
            if ($priorityType === 'PWD' && !$idPath && empty($priorityAccount['pwd_id_path'])) { smartserve_notify('Please upload your PWD ID photo.', 'danger', 'back'); exit; }
        } catch (Throwable $e) { smartserve_notify('Unable to upload the PWD ID. Please try a clear JPG or PNG up to 2 MB.', 'danger', 'back'); exit; }
        // 4. Update securely
	    if ($model->updateStudentProfile($fname, $mname, $lname, $contact, $address, $_SESSION['sess'], $priorityType, $idPath)) {
	        smartserve_notify('Profile updated successfully!', 'success', 'my-profile');
	    } else {
	        smartserve_notify($model->getProfileUpdateError(), 'danger', 'my-profile');
	    }
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
        #updateProfileForm > .ss-priority-card { margin: 0 0 32px; }
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
				<div class="row">
					<div class="col-lg-12 m-b30">
						<div class="left">
							<h2 class="m-b10 title-head">Update Profile</h2>
						</div>
                        <form enctype="multipart/form-data" class="edit-profile" id="updateProfileForm" method="POST">
                            <?php smartserve_pwd_card($priorityAccount); ?>
							<div class="form-group row">
							    <label class="col-sm-2 col-form-label">First Name</label>
							    <div class="col-sm-7">
							        <input title="Letters, spaces, and hyphens only." pattern="[\p{L}\p{M} \-]+" class="form-control" type="text" name="new_fname" maxlength="50" required
							               value="<?php echo htmlspecialchars($account_fname); ?>">
							    </div>
							</div>

							<div class="form-group row">
							    <label class="col-sm-2 col-form-label">Middle Name (optional)</label>
							    <div class="col-sm-7">
							        <input title="Letters, spaces, and hyphens only." pattern="[\p{L}\p{M} \-]+" class="form-control" type="text" name="new_mname" maxlength="50"
							               value="<?php echo htmlspecialchars($account_mname); ?>">
							    </div>
							</div>

							<div class="form-group row">
							    <label class="col-sm-2 col-form-label">Last Name</label>
							    <div class="col-sm-7">
							        <input title="Letters, spaces, and hyphens only." pattern="[\p{L}\p{M} \-]+" class="form-control" type="text" name="new_lname" maxlength="50" required
							               value="<?php echo htmlspecialchars($account_lname); ?>">
							    </div>
							</div>

							<div class="form-group row">
							    <label class="col-sm-2 col-form-label">Contact</label>
							    <div class="col-sm-7">
							        <input title="Use 09XXXXXXXXX (11 characters) or +639XXXXXXXXX (13 characters), without spaces or other characters." pattern="(?:09[0-9]{9}|\+639[0-9]{9})" maxlength="13" type="tel" class="form-control" name="new_contact" required
							               value="<?php echo htmlspecialchars($account_contact); ?>">
							    </div>
							</div>

							<div class="form-group row">
							    <label class="col-sm-2 col-form-label">Address</label>
							    <div class="col-sm-7">
							        <input maxlength="120" class="form-control" type="text" name="new_address" required
							               value="<?php echo htmlspecialchars($account_address); ?>">
							    </div>
							</div>

							<div class="form-group row"><label class="col-sm-2 col-form-label" for="account_priority">Priority Type</label><div class="col-sm-7">
                                <select class="form-control" name="account_priority" id="account_priority"><?php foreach (['N/A','PWD','Senior Citizen','Pregnant'] as $type): ?><option value="<?php echo $type; ?>" <?php echo ($priorityAccount['priority_type'] ?? 'N/A') === $type ? 'selected' : ''; ?>><?php echo $type; ?></option><?php endforeach; ?></select>
                            </div></div>
                            <div class="form-group row" id="pwd-id-wrap" <?php echo ($priorityAccount['priority_type'] ?? 'N/A') !== 'PWD' ? 'hidden style="display:none"' : ''; ?>>
                                <label class="col-sm-2 col-form-label" for="pwd_id">PWD ID Photo</label>
                                <div class="col-sm-7">
                                    <input type="file" name="pwd_id" id="pwd_id" class="form-control" accept="image/jpeg,image/png" <?php echo !empty($priorityAccount['pwd_id_path']) ? 'data-has-id="1"' : ''; ?> <?php echo ($priorityAccount['priority_type'] ?? 'N/A') !== 'PWD' ? 'disabled' : (empty($priorityAccount['pwd_id_path']) ? 'required' : ''); ?>>
                                    <small>JPG or PNG, up to 2 MB. Replacing the photo requires a new admin review.</small>
                                </div>
                            </div>
                            <script src="../assets/js/priority.js?v=<?php echo filemtime(__DIR__ . '/../assets/js/priority.js'); ?>" defer></script>
                            <div class="row">
                                <div class="col-sm-2">
									</div>
								<div class="col-sm-7">
									<label class="col-form-label" id="message"></label><br>
									
									<div class="d-flex flex-wrap">
										<button name="save_profile" type="submit" class="btn button-md radius-xl shadow-sm mb-2 mr-2" style="background-color: #28a745; color: white; border: none;">
											<i class="ti-marker-alt"></i> <span>SAVE PROFILE</span>
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
	</body>
</html>
