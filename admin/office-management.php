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
		<meta name="viewport" content="width=device-width, initial-scale=1">
		<link rel="icon" href="../assets/img/<?php echo $web_icon; ?>.png" type="image/x-icon" />
		<title><?php echo $web_name; ?> | Office Management</title>

		<link rel="stylesheet" type="text/css" href="../dashboard/assets/css/dataTables.bootstrap4.min.css">
		<link rel="stylesheet" type="text/css" href="../dashboard/assets/css/assets.css">
		<link rel="stylesheet" type="text/css" href="../dashboard/assets/vendors/calendar/fullcalendar.css">
		<link rel="stylesheet" type="text/css" href="../dashboard/assets/css/typography.css">
		<link rel="stylesheet" type="text/css" href="../dashboard/assets/css/shortcodes/shortcodes.css">
		<link rel="stylesheet" type="text/css" href="../dashboard/assets/css/style.css">
		<link rel="stylesheet" type="text/css" href="../dashboard/assets/css/dashboard.css">
		
		<style type="text/css">
			:root { --sidebar-bg: #D9D9D9; --sidebar-hover: #C4C4C4; }
			.modern-table-wrapper { background: #ffffff; border-radius: 20px; box-shadow: 0 10px 30px rgba(0,0,0,0.03); padding: 30px; border: 1px solid rgba(0,0,0,0.05); }
			table.dataTable thead th { border-bottom: 2px solid #eef7f1 !important; border-top: none; color: #6c757d; font-weight: 600; text-transform: uppercase; font-size: 0.8rem; letter-spacing: 0.5px; padding-bottom: 15px; }
			table.dataTable tbody td { vertical-align: middle; border-bottom: 1px solid #f8f9fa; padding: 16px 10px; color: #2d3436; font-size: 0.95rem; }
			table.dataTable.hover tbody tr:hover { background-color: #f4fbf6 !important; }
			.badge-soft-success { background-color: #eef7f1; color: #087830; padding: 6px 12px; border-radius: 30px; font-weight: 600; font-size: 0.8rem; }
			.badge-soft-danger { background-color: #fdeceb; color: #dc3545; padding: 6px 12px; border-radius: 30px; font-weight: 600; font-size: 0.8rem; }
			.btn-action { width: 36px; height: 36px; border-radius: 10px; display: inline-flex; align-items: center; justify-content: center; transition: all 0.3s ease; border: none; }
			.btn-view { background: #eef2f7; color: #3b82f6; } .btn-view:hover { background: #3b82f6; color: #ffffff; }
			.btn-edit { background: #fff3cd; color: #856404; } .btn-edit:hover { background: #856404; color: #ffffff; }
			.btn-archive { background: #fdeceb; color: #dc3545; } .btn-archive:hover { background: #dc3545; color: #ffffff; }
			.btn-restore { background: #eef7f1; color: #087830; } .btn-restore:hover { background: #087830; color: #ffffff; }
            
            /* Window Cards inside View Modal */
            .window-grid {
                display: grid;
                grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
                gap: 20px;
                padding-top: 15px;
            }
            .window-card {
                background: #f8f9fa;
                border: 1px solid #eef2f7;
                border-radius: 15px;
                padding: 20px 15px;
                text-align: center;
                transition: transform 0.2s;
            }
            .window-card:hover { transform: translateY(-3px); box-shadow: 0 5px 15px rgba(0,0,0,0.05); }
            .window-badge {
                background: #eef7f1; color: #087830; font-weight: bold; font-size: 0.8rem;
                padding: 4px 12px; border-radius: 20px; display: inline-block; margin-bottom: 12px;
            }
		</style>
	</head>
	
	<body class="ttr-opened-sidebar ttr-pinned-sidebar" style="background-color: #FCFCFC;">
		<?php
				$page = 'office-management';
				$secondnav = '';
				include 'main/admin-nav.php'; 
		?>
		<main class="ttr-wrapper" style="background-color: #FCFCFC;">
			<div class="container-fluid">
				<div class="db-breadcrumb">
					<h4 class="breadcrumb-title"><i class="ti ti-server"></i> Services</h4>
					<ul class="db-breadcrumb-list"><li>Offices</li></ul>
				</div>
				
				<div class="row">
					<div class="col-lg-12 m-b30">
						<div align="right" class="mb-4">
							<a href="" class="btn green radius-xl" style="background-color: <?php echo $primary_color; ?>; padding: 10px 20px; font-weight: 600;" data-toggle="modal" data-target="#add-office">
								<i class="ti-plus"></i><span>&nbsp;ADD NEW SERVICE</span>
							</a>
						</div>

						<div id="add-office" class="modal fade" role="dialog">
							<form method="POST">
								<div class="modal-dialog modal-md">
									<div class="modal-content" style="border-radius: 15px;">
										<div class="modal-header">
											<h4 class="modal-title" style="font-weight: 600;">Add Service</h4>
											<button type="button" class="close" data-dismiss="modal">&times;</button>
										</div>
										<div class="modal-body">
											<div class="form-group">
												<label class="col-form-label">Office Name</label>
												<input class="form-control" type="text" name="office_name" required placeholder="e.g. Registrar, Cashier">
											</div>
										</div>
										<div class="modal-footer">
											<input type="submit" class="btn btn-success radius-xl" style="background-color: <?php echo $primary_color; ?>;" name="add_office" value="Save Office">
											<button type="button" class="btn btn-secondary radius-xl" data-dismiss="modal">Close</button>
										</div>
									</div>
								</div>
							</form>
						</div>

						<?php
							if (isset($_POST['add_office'])) {
								$office_name = ucwords(trim($_POST['office_name']));
								$model->addOffice($office_name);
								echo "<script>window.location.href='office-management';</script>";
							}

							if (isset($_POST['edit_office'])) {
								$id = $_POST['edit_id'];
								$office_name = ucwords(trim($_POST['office_name']));
								$model->editOffice($id, $office_name);
								echo "<script>window.location.href='office-management';</script>";
							}

							if (isset($_POST['update_status'])) {
								$id = $_POST['status_id'];
								$status = $_POST['status_val'];
								$model->updateOfficeStatus($id, $status);
								echo "<script>window.location.href='office-management';</script>";
							}
						?>

						<div class="modern-table-wrapper">
							<div class="table-responsive">
								<table id="table" class="table hover display" style="width:100%">
                                    <thead>
                                        <tr>
                                            <th>Office Name</th>
                                            <th>Windows Allocated</th>
                                            <th class="text-center">Public Display</th> <th>Status</th>
                                            <th width="140" class="text-center">Action</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php
                                            $offices = $model->displayOffices();
                                            if (!empty($offices)) {
                                                foreach ($offices as $row) {
                                                    $id          = $row['id'];
                                                    $office_name = $row['office_name'];
                                                    $status      = $row['status'];
                                                    
                                                    $window_count = $model->countOfficeWindows($id);
                                                    $status_txt = ($status == 1) ? '<span class="badge-soft-success">Active</span>' : '<span class="badge-soft-danger">Archived</span>';
                                        ?>
                                        <tr>
                                            <td><strong style="color: #2d3436;"><?php echo htmlspecialchars($office_name); ?></strong></td>
                                            <td><?php echo $window_count; ?> Accounts</td>
                                            
                                            <td class="text-center">
                                                <?php if ($status == 1) { ?>
                                                    <a href="../live?id=<?php echo $id; ?>" target="_blank" class="btn-action" style="background: var(--soft-green); color: var(--primary-green); padding: 5px 15px; border-radius: 20px; font-weight: 600; font-size: 0.8rem; text-decoration: none; display: inline-flex; align-items: center; gap: 5px;">
                                                        <i class="ti-desktop"></i> TV Monitor
                                                    </a>
                                                <?php } else { ?>
                                                    <span class="text-muted" style="font-size: 0.8rem; font-style: italic;">Inactive</span>
                                                <?php } ?>
                                            </td>
                                            
                                            <td><?php echo $status_txt; ?></td>
                                            <td class="text-center">
                                                <a href="javascript:void(0);" class="btn-action btn-view" data-toggle="modal" data-target="#view-<?php echo $id; ?>" title="View Windows">
                                                    <i class="ti-eye"></i>
                                                </a>
                                
                                                <a href="javascript:void(0);" class="btn-action btn-edit ml-1" data-toggle="modal" data-target="#edit-<?php echo $id; ?>" title="Edit">
                                                    <i class="ti-pencil"></i>
                                                </a>
                                                
                                                <?php if ($status == 1) { ?>
                                                    <form method="POST" style="display:inline;">
                                                        <input type="hidden" name="status_id" value="<?php echo $id; ?>">
                                                        <input type="hidden" name="status_val" value="0">
                                                        <button type="submit" name="update_status" class="btn-action btn-archive ml-1" data-ss-confirm="Archive this office?" title="Archive">
                                                            <i class="ti-archive"></i>
                                                        </button>
                                                    </form>
                                                <?php } else { ?>
                                                    <form method="POST" style="display:inline;">
                                                        <input type="hidden" name="status_id" value="<?php echo $id; ?>">
                                                        <input type="hidden" name="status_val" value="1">
                                                        <button type="submit" name="update_status" class="btn-action btn-restore ml-1" data-ss-confirm="Restore this office?" title="Restore">
                                                            <i class="ti-back-left"></i>
                                                        </button>
                                                    </form>
                                                <?php } ?>
                                            </td>
                                        </tr>
                                
                                        <div id="view-<?php echo $id; ?>" class="modal fade" role="dialog">
                                            <div class="modal-dialog modal-lg">
                                                <div class="modal-content" style="border-radius: 15px;">
                                                    <div class="modal-header" style="border-bottom: 1px solid #eee;">
                                                        <h4 class="modal-title" style="font-weight: 700; color: #2d3436;">
                                                            <i class="ti-briefcase" style="color: <?php echo $primary_color; ?>; margin-right: 10px;"></i>
                                                            <?php echo htmlspecialchars($office_name); ?> - Active Windows
                                                        </h4>
                                                        <button type="button" class="close" data-dismiss="modal">&times;</button>
                                                    </div>
                                                    <div class="modal-body bg-light" style="padding: 25px;">
                                                        
                                                        <?php
                                                            // Fetch the actual accounts tied to this office
                                                            $window_accounts = $model->getAccountsByOffice($id);
                                                            if (empty($window_accounts)) {
                                                                echo '<div class="text-center py-5 text-muted"><h5>No active windows assigned to this office yet.</h5></div>';
                                                            } else {
                                                                echo '<div class="window-grid">';
                                                                foreach ($window_accounts as $win) {
                                                                    $w_name = htmlspecialchars($win['fname'] . ' ' . $win['lname']);
                                                                    $w_photo = $win['photo'] ? htmlspecialchars($win['photo']) : 'default';
                                                                    $w_window = htmlspecialchars($win['year']); // Assuming 'year' holds the window string
                                                        ?>
                                                                    <div class="window-card">
                                                                        <div class="window-badge"><?php echo $w_window; ?></div>
                                                                        <img src="../assets/img/profile-pictures/<?php echo $w_photo; ?>.jpg" 
                                                                             alt="User" 
                                                                             class="rounded-circle shadow-sm mb-3" 
                                                                             style="width: 70px; height: 70px; object-fit: cover; border: 3px solid #fff;">
                                                                        <h6 style="font-weight: 600; color: #2d3436; margin: 0;"><?php echo $w_name; ?></h6>
                                                                        <small class="text-muted">Staff Account</small>
                                                                    </div>
                                                        <?php 
                                                                } 
                                                                echo '</div>';
                                                            } 
                                                        ?>
                                                        
                                                    </div>
                                                    <div class="modal-footer" style="border-top: none;">
                                                        <button type="button" class="btn btn-secondary radius-xl outline" data-dismiss="modal">Close</button>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                
                                        <div id="edit-<?php echo $id; ?>" class="modal fade" role="dialog" data-ss-reset-on-close>
                                            <form method="POST">
                                                <div class="modal-dialog modal-md">
                                                    <div class="modal-content" style="border-radius: 15px;">
                                                        <div class="modal-header">
                                                            <h4 class="modal-title" style="font-weight: 600;">Edit Office</h4>
                                                            <button type="button" class="close" data-dismiss="modal">&times;</button>
                                                        </div>
                                                        <div class="modal-body">
                                                            <input type="hidden" name="edit_id" value="<?php echo $id; ?>">
                                                            <div class="form-group">
                                                                <label class="col-form-label">Office Name</label>
                                                                <input class="form-control" type="text" name="office_name" value="<?php echo htmlspecialchars($office_name); ?>" required>
                                                            </div>
                                                        </div>
                                                        <div class="modal-footer">
                                                            <input type="submit" class="btn btn-success radius-xl" style="background-color: <?php echo $primary_color; ?>;" name="edit_office" value="Save Changes">
                                                            <button type="button" class="btn btn-secondary radius-xl" data-dismiss="modal">Close</button>
                                                        </div>
                                                    </div>
                                                </div>
                                            </form>
                                        </div>
                                        <?php }} ?>
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
		<script>$(document).ready(function() { $('#table').DataTable(); });</script>
	</body>
</html>
