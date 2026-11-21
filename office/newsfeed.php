<?php
	include('../global/model.php');
	$model = new Model();
	include('main/department.php');

	// Helper function for Facebook-style dates
	if (!function_exists('timeAgo')) {
		function timeAgo($datetime) {
			$timestamp = is_numeric($datetime) ? $datetime : strtotime($datetime);
			$diff = time() - $timestamp;

			if ($diff < 60) {
				return "a few seconds ago";
			} elseif ($diff < 3600) {
				$minutes = floor($diff / 60);
				return $minutes . " minute" . ($minutes > 1 ? "s" : "") . " ago";
			} elseif ($diff < 86400) {
				$hours = floor($diff / 3600);
				return $hours . " hour" . ($hours > 1 ? "s" : "") . " ago";
			} elseif ($diff < 2592000) {
				$days = floor($diff / 86400);
				return $days . " day" . ($days > 1 ? "s" : "") . " ago";
			} elseif ($diff < 31536000) {
				$months = floor($diff / 2592000);
				return $months . " month" . ($months > 1 ? "s" : "") . " ago";
			} else {
				$years = floor($diff / 31536000);
				return $years . " year" . ($years > 1 ? "s" : "") . " ago";
			}
		}
	}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <?php require_once __DIR__ . '/../global/form-assets.php'; ?>
	<meta charset="utf-8">
	<meta http-equiv="X-UA-Compatible" content="IE=edge">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<link rel="icon" href="../assets/img/<?php echo $web_icon; ?>.png" type="image/x-icon" />
	<title><?php echo $web_name; ?></title>

	<link rel="stylesheet" type="text/css" href="../dashboard/assets/css/assets.css">
	<link rel="stylesheet" type="text/css" href="../dashboard/assets/css/typography.css">
	<link rel="stylesheet" type="text/css" href="../dashboard/assets/css/style.css">
	<link rel="stylesheet" type="text/css" href="../dashboard/assets/css/dashboard.css">
	
	<style type="text/css">
		/* Newsfeed Specific Styles */
		.advisory-card {
			background: #fff;
			border-radius: 12px;
			border: 1px solid #eee;
			overflow: hidden;
			box-shadow: 0 4px 15px rgba(0,0,0,0.05);
			transition: all 0.3s ease;
		}
		.advisory-card:hover {
			transform: translateY(-3px);
			box-shadow: 0 8px 25px rgba(0,0,0,0.1);
			border-color: #3B82F6;
		}
		.image-container {
			background-color: #f8f9fa;
			min-height: 250px;
			display: flex;
			align-items: center;
			justify-content: center;
		}
		.advisory-img {
			width: 100%;
			height: 100%;
			min-height: 300px;
			object-fit: cover;
		}
		.full-text {
			display: none;
		}
		.no-gutters {
			display: flex;
			flex-wrap: wrap;
			margin: 0;
		}
		@media (max-width: 768px) {
			.advisory-img { height: 200px; min-height: 200px; }
		}
	</style>
<link rel="stylesheet" href="../assets/css/advisories.css?v=<?php echo filemtime(__DIR__ . '/../assets/css/advisories.css'); ?>">
</head>
<body class="ttr-opened-sidebar ttr-pinned-sidebar ss-advisories" style="background-color: #F3F4F6;">

	<div class="ttr-sidebar">
		<div class="ttr-sidebar-wrapper content-scroll">
			<?php $page = 'announcements'; $secondnav = 'newsfeed'; include 'main/admin-nav.php'; ?>
		</div>
	</div>

	<main class="ttr-wrapper">
		<div class="container-fluid">
			<div class="db-breadcrumb">
				<h4 class="breadcrumb-title"><i class="ti ti-announcement" aria-hidden="true"></i> Advisories</h4>
                <ul class="db-breadcrumb-list"><li>Newsfeed</li></ul>
			</div>

			<div class="row">
				<div class="col-lg-12"> <?php
						$rows = $model->displayAnnouncements(1, 1);
						if (!empty($rows)) {
							foreach ($rows as $row) {
								$poster = htmlspecialchars($row['fname'] . " " . $row['lname'] . "");
								$posted_date = timeAgo($row['date']);
					?>
					
					
					    <div class="m-b30">
							<div class="advisory-card">
								<div class="row no-gutters">
									<div class="col-md-5">
										<a href="../assets/img/announcement/<?php echo $row['image_unique']; ?>.jpg" target="_blank">
											<img src="../assets/img/announcement/<?php echo $row['image_unique']; ?>.jpg" class="advisory-img" alt="Advisory attachment" loading="lazy">
										</a>
									</div>
									<div class="col-md-7">
										<div class="card-body d-flex flex-column h-100" style="padding: 25px;">
											<div class="m-b10">
											    <h6 class="m-b0" style="color: #1F2937; font-weight: 700;"><?php echo $poster; ?></h6>
												<small class="text-muted"><i class="ti-calendar"></i> <?php echo timeAgo($row['date']); ?> • <?php echo date('M d, Y', strtotime($row['date'])); ?></small>
											</div>
											<h4 class="m-b10"><?php echo htmlspecialchars($row['title']); ?></h4>
											<div class="flex-grow-1 m-b20" style="font-size: 14px; color: #555;">
												<?php
													$details = $row['details'];
													if (strlen($details) > 200) {
														echo '<span class="short-text">'.nl2br(htmlspecialchars(substr($details, 0, 200))).'...</span>';
														echo '<span class="full-text">'.nl2br(htmlspecialchars($details)).'</span>';
														echo ' <a href="javascript:void(0);" onclick="toggleDetails(this)" style="color: #3B82F6;">See More</a>';
													} else {
														echo nl2br(htmlspecialchars($details));
													}
												?>
											</div>
											
											<div class="action-buttons pt-3" style="border-top: 1px solid #eee;">
												<span class="badge badge-primary"><?php echo $row['course']; ?> Advisory</span>
											</div>
										</div>
									</div>
								</div>
							</div>
						</div>

					<?php 
							}
						} else {
							    echo '<div class="ss-empty-advisories"><h5>No advisories yet</h5><p>Published updates will appear here.</p></div>'; 
							}
					?>
				</div>
			</div>
		</div>
	<?php require __DIR__ . '/../global/advisory-footer.php'; ?>
</main>

	<script src="../dashboard/assets/js/jquery.min.js"></script>
	<script src="../dashboard/assets/vendors/bootstrap/js/bootstrap.min.js"></script>
	<script src="../dashboard/assets/js/admin.js"></script>

	<script>
		function toggleDetails(element) {
			var container = $(element).parent();
			var shortText = container.find('.short-text');
			var fullText = container.find('.full-text');

			if (fullText.is(':hidden')) {
				shortText.hide();
				fullText.fadeIn().css('display', 'inline');
				$(element).text('See Less');
			} else {
				shortText.fadeIn().css('display', 'inline');
				fullText.hide();
				$(element).text('See More');
			}
		}
	</script>
</body>
</html>