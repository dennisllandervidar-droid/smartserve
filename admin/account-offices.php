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
			.btn.dropdown-toggle.btn-default:hover,
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
				--accent-gold: #FFB800;
				--sidebar-bg: #D9D9D9;
				--sidebar-hover: #C4C4C4;
			}

			/* --- Modern Table UI Additions --- */
			.modern-table-wrapper {
				background: #ffffff;
				border-radius: 20px;
				box-shadow: 0 10px 30px rgba(0,0,0,0.03);
				padding: 30px;
				border: 1px solid rgba(0,0,0,0.05);
			}

			table.dataTable thead th {
				border-bottom: 2px solid #eef7f1 !important;
				border-top: none;
				color: #6c757d;
				font-weight: 600;
				text-transform: uppercase;
				font-size: 0.8rem;
				letter-spacing: 0.5px;
				padding-bottom: 15px;
			}

			table.dataTable tbody td {
				vertical-align: middle;
				border-bottom: 1px solid #f8f9fa;
				padding: 16px 10px;
				color: #2d3436;
				font-size: 0.95rem;
			}

			table.dataTable.hover tbody tr:hover, 
			table.dataTable.display tbody tr:hover {
				background-color: #f4fbf6 !important;
				transition: background-color 0.2s ease;
			}

			/* --- Soft Badges --- */
			.badge-soft-success { 
				background-color: #eef7f1; 
				color: #087830; 
				padding: 6px 12px; 
				border-radius: 30px; 
				font-weight: 600; 
				font-size: 0.8rem;
			}
			.badge-soft-danger { 
				background-color: #fdeceb; 
				color: #dc3545; 
				padding: 6px 12px; 
				border-radius: 30px; 
				font-weight: 600; 
				font-size: 0.8rem;
			}

			/* --- Modern Action Buttons --- */
			.btn-action {
				width: 36px; 
				height: 36px;
				border-radius: 10px;
				display: inline-flex;
				align-items: center;
				justify-content: center;
				transition: all 0.3s ease;
				border: none;
				text-decoration: none !important;
			}
			.btn-action i { font-size: 16px; margin: 0; }

			.btn-view { background: #eef2f7; color: #3b82f6; }
			.btn-view:hover { background: #3b82f6; color: #ffffff; transform: translateY(-2px); }

            .btn-edit { background: #fff3cd; color: #856404; }
			.btn-edit:hover { background: #856404; color: #ffffff; transform: translateY(-2px); }

			.btn-archive { background: #fdeceb; color: #dc3545; }
			.btn-archive:hover { background: #dc3545; color: #ffffff; transform: translateY(-2px); }

			.btn-restore { background: #eef7f1; color: #087830; }
			.btn-restore:hover { background: #087830; color: #ffffff; transform: translateY(-2px); }

			.dataTables_wrapper .dataTables_filter input {
				border-radius: 8px;
				border: 1px solid #ddd;
				padding: 6px 12px;
				outline: none;
			}
			.dataTables_wrapper .dataTables_filter input:focus {
				border-color: #087830;
			}
		</style>
	</head>
	
	<body class="ttr-opened-sidebar ttr-pinned-sidebar" style="background-color: #FCFCFC;">
		<?php
				$page = 'accounts';
				$secondnav = 'offices';
				include 'main/admin-nav.php'; 

				function generateRandomAlphanumeric($length = 8) {
				    $characters = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789';
				    $charLength = strlen($characters);
				    $randomString = '';

				    for ($i = 0; $i < $length; $i++) {
				        $randomString .= $characters[random_int(0, $charLength - 1)];
				    }

				    return $randomString;
				}

                // Fetch offices once at the top so we can use it for mapping IDs to Names later
                $offices = $model->displayOffices();
                $office_map = [];
                if (!empty($offices)) {
                    foreach ($offices as $off) {
                        // Creates an array like: [ 1 => 'Registrar', 2 => 'Cashier' ]
                        $office_map[$off['id']] = $off['office_name'];
                    }
                }
		?>
		<main class="ttr-wrapper" style="background-color: #FCFCFC;">
			<div class="container-fluid">
				<div class="db-breadcrumb">
					<h4 class="breadcrumb-title"><i class="ti ti-user"></i> Accounts</h4>
					<ul class="db-breadcrumb-list">
						<li>Staffs</li>
					</ul>
				</div>
				
				<div class="row">
					<div class="col-lg-12 m-b30">
						<div align="right" class="mb-4">
							<a href="" class="btn green radius-xl" style="background-color: <?php echo $primary_color; ?>; padding: 10px 20px; font-weight: 600;" data-toggle="modal" data-target="#add-announcement">
								<i class="ti-plus"></i><span>&nbsp;ADD NEW STAFF</span>
							</a>
						</div>

						<div id="add-announcement" class="modal fade" role="dialog">
							<form class="edit-profile m-b30" method="POST">
								<div class="modal-dialog modal-md">
									<div class="modal-content" style="border-radius: 15px;">
										<div class="modal-header">
											<h4 class="modal-title" style="font-weight: 600;">Add New Staff Account</h4>
											<button type="button" class="close" data-dismiss="modal">&times;</button>
										</div>
										<div class="modal-body">
											<div class="row">
												<div class="form-group col-4">
													<label class="col-form-label"><b>First Name</b></label>
													<input title="Letters, spaces, and hyphens only." pattern="[\p{L}\p{M} \-]+" class="form-control" type="text" name="fname" required maxlength="30">
												</div>
												<div class="form-group col-4">
													<label class="col-form-label"><b>Middle Name</b></label>
													<input title="Letters, spaces, and hyphens only." pattern="[\p{L}\p{M} \-]+" class="form-control" type="text" name="mname" maxlength="30">
												</div>
												<div class="form-group col-4">
													<label class="col-form-label"><b>Last Name</b></label>
													<input title="Letters, spaces, and hyphens only." pattern="[\p{L}\p{M} \-]+" class="form-control" type="text" name="lname" required maxlength="30">
												</div>
												
												<div class="form-group col-12">
													<label class="col-form-label"><b>Contact </b></label>
													<input title="Use 09XXXXXXXXX (11 characters) or +639XXXXXXXXX (13 characters)." pattern="(?:09[0-9]{9}|\+639[0-9]{9})" maxlength="13" type="tel" class="form-control" name="contact" required >
												</div>
												<div class="form-group col-12">
													<label class="col-form-label"><b>Address </b></label>
													<input maxlength="120" class="form-control" type="text" name="address" required >
												</div>

												<div class="form-group col-6">
													<label class="col-form-label"><b>Office</b></label>
													<select class="form-control" name="office" required>
                                                        <option value="" disabled selected>Select Office</option>
                                                        <?php
                                                            if (!empty($offices)) {
                                                                foreach ($offices as $off) {
                                                                    if ($off['status'] == 1) {
                                                                        // NOW USING ID AS THE VALUE
                                                                        echo '<option value="'.htmlspecialchars($off['id']).'">'.htmlspecialchars($off['office_name']).'</option>';
                                                                    }
                                                                }
                                                            }
                                                        ?>
                                                    </select>
												</div>
                                                <div class="form-group col-6">
													<label class="col-form-label"><b>Window</b></label>
													<select class="form-control" name="window" required>
                                                        <option value="" disabled selected>Assign Window</option>
                                                        <?php 
                                                            for($i=1; $i<=10; $i++) { 
                                                                echo "<option value='Window $i'>Window $i</option>"; 
                                                            } 
                                                        ?>
                                                    </select>
												</div>

												<div class="form-group col-6">
													<label class="col-form-label"><b>Email</b></label>
													<input pattern="[^@\s]+@[A-Za-z0-9](?:[A-Za-z0-9\-]*[A-Za-z0-9])?(?:\.[A-Za-z0-9](?:[A-Za-z0-9\-]*[A-Za-z0-9])?)*\.[A-Za-z]{2,63}" maxlength="50" class="form-control" type="email" name="uname" required>
												</div>
												<div class="form-group col-6">
													<label class="col-form-label"><b>Password</b></label>
													<input type="password" minlength="8" maxlength="72" class="form-control" name="pword" value="" required>
												</div>
											</div>
										</div>
										<div class="modal-footer">
											<input type="submit" class="btn btn-success radius-xl" style="background-color: <?php echo $primary_color; ?>;" name="add_user" value="Add Staff">
									        <button type="button" class="btn btn-secondary radius-xl" data-dismiss="modal">Close</button>
										</div>
									</div>
								</div>
							</form>
						</div>

						<?php
							$category = 0;
							$status = 1;
							if (isset($_POST['add_user'])) {
							    $fname   = ucwords(strtolower(trim($_POST['fname'] ?? '')));
							    $mname   = ucwords(strtolower(trim($_POST['mname'] ?? '')));
							    $lname   = ucwords(strtolower(trim($_POST['lname'] ?? '')));
							    $uname   = strtolower(trim($_POST['uname'] ?? ''));
							    $pword   = $_POST['pword'] ?? '';
							    $contact = preg_replace('/\s+/', '', $_POST['contact'] ?? '');
							    $address = ucwords(strtolower(preg_replace('/\s+/', ' ', trim($_POST['address'] ?? ''))));
							    $temp_pword = password_hash($pword, PASSWORD_DEFAULT);

								$office   = $_POST['office'] ?? ''; // This is now an ID
                                $window   = $_POST['window'] ?? '';

								$position = 'Staff';
								$photo = 'default';
								$access = '2';
								$subscription = NULL;
								$company_id = NULL;
								$added_by = $_SESSION['sess']; 
								$status = '1';		
                                
								$result = $model->addAccount($fname, $mname, $lname, $uname, $temp_pword, $contact, $address, $position, $photo, $access, $window, $office, $status);
                                
                                if($result) {
                                    echo "<script>window.location.href='account-offices';</script>";
                                }
							}
                            
                            // Edit Form Handler
                            if (isset($_POST['edit_user'])) {
                                $edit_id = $_POST['edit_id'];
							    $fname   = trim($_POST['fname'] ?? '');
							    $mname   = trim($_POST['mname'] ?? '');
							    $lname   = trim($_POST['lname'] ?? '');
							    $uname   = trim($_POST['uname'] ?? '');
							    $contact = preg_replace('/\s+/', '', $_POST['contact'] ?? '');
							    $address = trim($_POST['address'] ?? '');
								
                                $office   = $_POST['office'] ?? ''; // This is now an ID
                                $window   = $_POST['window'] ?? '';

                                // Passing data to model
                                $edit_result = $model->editAccount($edit_id, $fname, $mname, $lname, $uname, $contact, $address, $window, $office);
                                if ($edit_result === true) {
                                    smartserve_notify('Account updated successfully.', 'success', 'account-offices');
                                } elseif ($edit_result === 'unchanged') {
                                    smartserve_notify('No changes to save.', 'info');
                                }
                            }
						?>

						<div class="modern-table-wrapper">
							<div class="table-responsive">
								<table id="table" class="table hover display" style="width:100%">
									<thead>
										<tr>
											<th>Name</th>
											<th>Office & Window</th>
											<th>Email</th>
											<th>Contact</th>
											<th>Status</th>
											<th width="150" class="text-center">Action</th>
										</tr>
									</thead>
									<tbody>
										<?php
											$access = 2;
											$rows = $model->displayAccounts($access);
											if (!empty($rows)) {
												foreach ($rows as $row) {
													$id           = $row['id'] ?? '';
													$fname        = $row['fname'] ?? '';
										            $mname        = $row['mname'] ?? '';
										            $lname        = $row['lname'] ?? '';
										            $position     = $row['position'] ?? '';
										            $uname        = $row['uname'] ?? '';
										            $contact      = $row['contact'] ?? '';
										            $address      = $row['address'] ?? '';
										            $photo        = $row['photo'] ?? '';
										            $date_created = $row['date_created'] ?? '';
										            $course       = $row['course'] ?? ''; // This is now an ID
                                                    $window_name  = $row['year'] ?? ''; 
										            $status       = $row['status'] ?? '';

                                                    // Map the numeric ID back to the actual Office Name for the table display
                                                    $display_office_name = isset($office_map[$course]) ? $office_map[$course] : "Unknown Office";

										            if ($status == 1) {
													    $status_txt = '<span class="badge-soft-success">Active</span>';
													} elseif ($status == 0) {
													    $status_txt = '<span class="badge-soft-danger">Archived</span>';
													} else {
													    $status_txt = '<span class="badge badge-secondary">Unknown</span>';
													}
										?>
										<tr>
											<td>
												<div class="d-flex align-items-center">
													<img src="../assets/img/profile-pictures/<?php echo $photo ? htmlspecialchars($photo, ENT_QUOTES, 'UTF-8') : 'default'; ?>.jpg" 
														 alt="User" 
														 class="rounded-circle shadow-sm mr-3" 
														 style="width: 40px; height: 40px; object-fit: cover; border: 2px solid #fff;"> 
													<div>
														<span style="font-weight: 600; color: #2d3436;">
															<?php echo htmlspecialchars($fname, ENT_QUOTES, 'UTF-8') . ' ' . htmlspecialchars($lname, ENT_QUOTES, 'UTF-8'); ?>
														</span>
													</div>
												</div>
											</td>
											<td>
                                                <strong><?php echo htmlspecialchars($display_office_name, ENT_QUOTES, 'UTF-8'); ?></strong><br>
                                                <small class="text-muted"><?php echo htmlspecialchars($window_name, ENT_QUOTES, 'UTF-8'); ?></small>
                                            </td>
											<td><?php echo htmlspecialchars($uname, ENT_QUOTES, 'UTF-8'); ?></td>
											<td><?php echo htmlspecialchars($contact, ENT_QUOTES, 'UTF-8'); ?></td>
											<td><?php echo $status_txt; ?></td>
											<td class="text-center">
												<a href="account-offices-profile?id=<?php echo $id; ?>" class="btn-action btn-view" data-toggle="tooltip" title="Profile">
													<i class="ti-eye"></i>
												</a>

                                                <a href="javascript:void(0);" class="btn-action btn-edit ml-1" data-toggle="modal" data-target="#edit-modal-<?php echo $id; ?>" title="Edit">
													<i class="ti-pencil"></i>
												</a>
												
												<?php if ($status == 1) { ?>
													<a href="javascript:void(0);" class="btn-action btn-archive ml-1" data-toggle="modal" data-target="#manage-<?php echo $id; ?>" title="Archive">
														<i class="ti-archive"></i>
													</a>
												<?php } elseif ($status == 0) { ?>
													<a href="javascript:void(0);" class="btn-action btn-restore ml-1" data-toggle="modal" data-target="#manage-<?php echo $id; ?>" title="Restore">
														<i class="ti-back-left"></i>
													</a>
												<?php } ?>
											</td>
										</tr>

                                        <div id="edit-modal-<?php echo $id; ?>" class="modal fade" role="dialog" data-ss-reset-on-close>
                                            <form class="edit-profile m-b30" method="POST" style="text-align: left;">
                                                <div class="modal-dialog modal-md">
                                                    <div class="modal-content" style="border-radius: 15px;">
                                                        <div class="modal-header">
                                                            <h4 class="modal-title" style="font-weight: 600;">Edit Staff Account</h4>
                                                            <button type="button" class="close" data-dismiss="modal">&times;</button>
                                                        </div>
                                                        <div class="modal-body">
                                                            <input type="hidden" name="edit_id" value="<?php echo $id; ?>">
                                                            <div class="row">
                                                                <div class="form-group col-4">
                                                                    <label class="col-form-label">First Name</label>
                                                                    <input title="Letters, spaces, and hyphens only." pattern="[\p{L}\p{M} \-]+" class="form-control" type="text" name="fname" value="<?php echo htmlspecialchars($fname); ?>" required maxlength="30">
                                                                </div>
                                                                <div class="form-group col-4">
                                                                    <label class="col-form-label">Middle Name</label>
                                                                    <input title="Letters, spaces, and hyphens only." pattern="[\p{L}\p{M} \-]+" class="form-control" type="text" name="mname" value="<?php echo htmlspecialchars($mname); ?>" maxlength="30">
                                                                </div>
                                                                <div class="form-group col-4">
                                                                    <label class="col-form-label">Last Name</label>
                                                                    <input title="Letters, spaces, and hyphens only." pattern="[\p{L}\p{M} \-]+" class="form-control" type="text" name="lname" value="<?php echo htmlspecialchars($lname); ?>" required maxlength="30">
                                                                </div>
                                                                
                                                                <div class="form-group col-12">
                                                                    <label class="col-form-label">Contact</label>
                                                                    <input title="Use 09XXXXXXXXX (11 characters) or +639XXXXXXXXX (13 characters)." pattern="(?:09[0-9]{9}|\+639[0-9]{9})" maxlength="13" type="tel" class="form-control" name="contact" value="<?php echo htmlspecialchars($contact); ?>" required>
                                                                </div>
                                                                <div class="form-group col-12">
                                                                    <label class="col-form-label">Address</label>
                                                                    <input maxlength="120" class="form-control" type="text" name="address" value="<?php echo htmlspecialchars($address); ?>" required>
                                                                </div>
                                        
                                                                <div class="form-group col-6">
                                                                    <label class="col-form-label">Office</label>
                                                                    <select class="form-control" name="office" required>
                                                                        <option value="" disabled>Select Office</option>
                                                                        <?php
                                                                            if (!empty($offices)) {
                                                                                foreach ($offices as $off) {
                                                                                    if ($off['status'] == 1 || $off['id'] == $course) {
                                                                                        $selected = ($off['id'] == $course) ? 'selected' : '';
                                                                                        echo '<option value="'.htmlspecialchars($off['id']).'" '.$selected.'>'.htmlspecialchars($off['office_name']).'</option>';
                                                                                    }
                                                                                }
                                                                            }
                                                                        ?>
                                                                    </select>
                                                                </div>
                                                                <div class="form-group col-6">
                                                                    <label class="col-form-label">Window</label>
                                                                    <select class="form-control" name="window" required>
                                                                        <option value="" disabled>Assign Window</option>
                                                                        <?php 
                                                                            for($i=1; $i<=10; $i++) { 
                                                                                $win_val = "Window $i";
                                                                                $selected = ($win_val == $window_name) ? 'selected' : '';
                                                                                echo "<option value='$win_val' $selected>$win_val</option>"; 
                                                                            } 
                                                                        ?>
                                                                    </select>
                                                                </div>
                                        
                                                                <div class="form-group col-12">
                                                                    <label class="col-form-label">Email</label>
                                                                    <input pattern="[^@\s]+@[A-Za-z0-9](?:[A-Za-z0-9\-]*[A-Za-z0-9])?(?:\.[A-Za-z0-9](?:[A-Za-z0-9\-]*[A-Za-z0-9])?)*\.[A-Za-z]{2,63}" maxlength="50" class="form-control" type="email" name="uname" value="<?php echo htmlspecialchars($uname); ?>" required>
                                                                </div>
                                                            </div>
                                                        </div>
                                                        <div class="modal-footer">
                                                            <input type="submit" class="btn btn-warning radius-xl" style="color: white!important;" name="edit_user" value="Save Changes">
                                                            <button type="button" class="btn btn-secondary radius-xl" data-dismiss="modal">Close</button>
                                                        </div>
                                                    </div>
                                                </div>
                                            </form>
                                        </div>

										<div id="manage-<?php echo $id; ?>" class="modal fade" role="dialog">
										    <form class="edit-profile m-b30" method="POST">
										        <div class="modal-dialog modal-md">
										            <div class="modal-content" style="border-radius: 15px; border: none; box-shadow: 0 10px 30px rgba(0,0,0,0.1);">
										                <div class="modal-header" style="border-bottom: 1px solid #eee;">
										                    <h4 class="modal-title" style="font-weight: 600;">Account Details</h4>
										                    <button type="button" class="close" data-dismiss="modal">&times;</button>
										                </div>
										                <div class="modal-body">
															<input type="hidden" name="approve_hidden" value="<?php echo (int)$id; ?>">
															<div class="text-center mb-4 mt-2">
															    <img src="../assets/img/profile-pictures/<?php echo $photo ? htmlspecialchars($photo, ENT_QUOTES, 'UTF-8') : 'default'; ?>.jpg"
															         alt="User"
															         class="img-fluid rounded-circle shadow-sm mb-3"
															         style="width: 90px; height: 90px; object-fit: cover; border: 3px solid #eef7f1;">
															    <h5 class="mb-0" style="font-weight: 700; color: #2d3436;">
															        <?php echo htmlspecialchars($fname . ' ' . $lname, ENT_QUOTES, 'UTF-8'); ?>
															    </h5>
															    <small class="text-muted" style="letter-spacing: 0.5px; text-transform: uppercase;"><?php echo htmlspecialchars($position, ENT_QUOTES, 'UTF-8'); ?></small>
															</div>
															
															<div class="list-group list-group-flush" style="border-radius: 10px; overflow: hidden; border: 1px solid #eee;">
																<div class="list-group-item flex-column align-items-start" style="padding: 15px 20px;">
															        <strong style="color: #6c757d; font-size: 0.85rem; text-transform: uppercase; display: block; margin-bottom: 5px;">Office & Window</strong>
															        <span style="color: #2d3436; font-weight: 500; line-height: 1.4; display: block;"><?php echo htmlspecialchars($display_office_name . ' - ' . $window_name, ENT_QUOTES, 'UTF-8'); ?></span>
															    </div>
															    <div class="list-group-item d-flex justify-content-between align-items-center" style="padding: 15px 20px;">
															        <strong style="color: #6c757d; font-size: 0.85rem; text-transform: uppercase;">Email</strong>
															        <span style="color: #2d3436; font-weight: 500;"><?php echo htmlspecialchars($uname, ENT_QUOTES, 'UTF-8'); ?></span>
															    </div>
															    <div class="list-group-item d-flex justify-content-between align-items-center" style="padding: 15px 20px;">
															        <strong style="color: #6c757d; font-size: 0.85rem; text-transform: uppercase;">Contact</strong>
															        <span style="color: #2d3436; font-weight: 500;"><?php echo htmlspecialchars($contact, ENT_QUOTES, 'UTF-8'); ?></span>
															    </div>
															    <div class="list-group-item flex-column align-items-start" style="padding: 15px 20px;">
															        <strong style="color: #6c757d; font-size: 0.85rem; text-transform: uppercase; display: block; margin-bottom: 5px;">Address</strong>
															        <span style="color: #2d3436; font-weight: 500; line-height: 1.4; display: block;"><?php echo htmlspecialchars($address, ENT_QUOTES, 'UTF-8'); ?></span>
															    </div>
															    <div class="list-group-item d-flex justify-content-between align-items-center" style="padding: 15px 20px; background: #f8f9fa;">
															        <strong style="color: #6c757d; font-size: 0.85rem; text-transform: uppercase;">Current Status</strong>
															        <?php echo $status_txt; ?>
															    </div>
															</div>
														</div>
										                <div class="modal-footer" style="border-top: 1px solid #eee; padding: 15px 20px;">
										                	<?php if ($status == 1) { ?>
																<input type="submit" class="btn btn-danger radius-xl" name="archive"
										                            data-ss-confirm="Are you sure you want to archive this user?"
										                            value="Archive Access">
															<?php } elseif ($status == 0) { ?>
																<input type="submit" class="btn btn-success radius-xl" name="restore" style="background-color: <?php echo $primary_color; ?>; border-color: <?php echo $primary_color; ?>;"
										                            data-ss-confirm="Are you sure you want to restore this user?"
										                            value="Restore Access">
															<?php } ?>
										                    <button type="button" class="btn btn-secondary radius-xl outline" data-dismiss="modal">Close</button>
										                </div>
										            </div>
										        </div>
										    </form>
										</div>
										<?php
												}
											}

											// Handling Actions
											if (isset($_POST['restore'])) {
												$approve_id = $_POST['approve_hidden'];
												$model->updateAccountStatus(1, $approve_id);
												echo "<script>window.open('account-offices', '_self');</script>";
											}

											if (isset($_POST['archive'])) {
												$approve_id = $_POST['approve_hidden'];
												$model->updateAccountStatus(0, $approve_id);
												echo "<script>window.open('account-offices', '_self');</script>";
											}
										?>
									</tbody>
								</table>
							</div>
						</div>
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
		<script src="../assets/js/edit-modal-reset.js?v=<?php echo filemtime(__DIR__ . '/../assets/js/edit-modal-reset.js'); ?>"></script>

		<script type="text/javascript">
			$(document).ready(function() {
				$('#table').DataTable();
			});
			$(document).ready(function(){
				$('[data-toggle="tooltip"]').tooltip();
			});
		</script>
	</body>
</html>
