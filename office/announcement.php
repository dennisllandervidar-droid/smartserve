<?php
    include('../global/model.php');
    $model = new Model();
    include('main/department.php');
    if ((int)($_SESSION['access'] ?? 0) !== 2) {
        header_remove('Location');
        http_response_code(403);
        exit('You cannot manage advisories with this account.');
    }

    // -------------------------------------------------------------
    // TEXTBEE SMS FUNCTION (PRODUCTION READY)
    // -------------------------------------------------------------
    require_once __DIR__ . '/../global/sms.php';



    if (!function_exists('sendAnnouncementSmsBatch')) {
        function sendAnnouncementSmsBatch($recipients, $sms_content) {
            $sent_numbers = [];

            if (empty($recipients)) {
                return 0;
            }

            foreach ($recipients as $recipient) {
                $formatted_number = normalizePhilippineMobileNumber($recipient['contact'] ?? '');
                if ($formatted_number !== '' && !isset($sent_numbers[$formatted_number])) {
                    sendTextbeeSMS($formatted_number, $sms_content);
                    $sent_numbers[$formatted_number] = true;
                    sleep(2);
                }
            }

            return count($sent_numbers);
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

    <link rel="stylesheet" type="text/css" href="../dashboard/assets/css/dataTables.bootstrap4.min.css">
    <link rel="stylesheet" type="text/css" href="../dashboard/assets/css/assets.css">
    <link rel="stylesheet" type="text/css" href="../dashboard/assets/vendors/calendar/fullcalendar.css">
    <link rel="stylesheet" type="text/css" href="../dashboard/assets/css/typography.css">
    <link rel="stylesheet" type="text/css" href="../dashboard/assets/css/shortcodes/shortcodes.css">
    <link rel="stylesheet" type="text/css" href="../dashboard/assets/css/style.css">
    <link rel="stylesheet" type="text/css" href="../dashboard/assets/css/dashboard.css">
    <style type="text/css">
        .advisory-card {
            background: #fff;
            border-radius: 12px;
            border: 1px solid #eee;
            overflow: hidden;
            box-shadow: 0 4px 15px rgba(0,0,0,0.05);
        }
        .advisory-img {
            width: 100%;
            height: 100%;
            min-height: 280px;
            object-fit: cover;
        }
        .full-text { display: none; }
        .no-gutters { display: flex; flex-wrap: wrap; margin: 0; }
        @media (max-width: 768px) { .advisory-img { min-height: 200px; } }
    </style>
<link rel="stylesheet" href="../assets/css/advisories.css?v=<?php echo filemtime(__DIR__ . '/../assets/css/advisories.css'); ?>">
</head>
<body class="ttr-opened-sidebar ttr-pinned-sidebar ss-advisories" style="background-color: #FCFCFC;">

    <div class="ttr-sidebar">
        <div class="ttr-sidebar-wrapper content-scroll">
            <?php $page = 'announcements'; $secondnav = 'announcements'; include 'main/admin-nav.php'; ?>
        </div>
    </div>

    <main class="ttr-wrapper">
        <div class="container-fluid">
            <div class="db-breadcrumb">
                <h4 class="breadcrumb-title"><i class="ti ti-announcement" aria-hidden="true"></i> Advisories</h4>
                <ul class="db-breadcrumb-list"><li>My Advisories</li></ul>
            </div>
            <div class="row">
                <div class="col-lg-12">
                    <?php
                        $category = 1; $status = 1;
                        $current_account = $model->displayAccountDetails($_SESSION['sess']);
                        $current_office_id = !empty($current_account['course']) ? (int) $current_account['course'] : 0;
                        
                        // -------------------------------------------------------------
                        // ADD ADVISORY & SEND TEXTBEE SMS BROADCAST
                        // -------------------------------------------------------------
                        if (isset($_POST['add_announcement'])) {
                            $title = $_POST['title'];
                            $date = $_POST['date'];
                            $details = trim($_POST['details']);
                            $sms_notify = $_POST['sms_notify'] ?? 'none';
                            $email_notify = $_POST['email_notify'] ?? 'none';
                            if (($sms_notify === 'students_pending_today_my_office' || $email_notify === 'students_pending_today_my_office') && $current_office_id <= 0) {
                                smartserve_notify('Choose another notification audience. This account has no assigned office.', 'danger', 'announcement');
                                exit;
                            }
                            $path = '../assets/img/announcement/';
                            $unique = time().uniqid(rand());
                            $destination = $path . $unique . '.jpg';
                            
                            // Image Upload
                            if(isset($_FILES["image"]) && $_FILES["image"]["error"] == 0) {
                                $base = basename($_FILES["image"]["name"]);
                                move_uploaded_file($_FILES["image"]["tmp_name"], $destination);
                            } else {
                                $base = 'default.jpg'; 
                            }
                            
                            // 1. Save Advisory to Database
                            $announcement_id = $model->addAnnouncement($title, $details, $base, $unique, $date, $_SESSION['sess'], $category);
                            if (!$announcement_id) {
                                smartserve_notify('Unable to save the advisory. Please try again.', 'danger', 'announcement');
                                exit;
                            }
                            $model->notifyAnnouncementEmail($announcement_id, $email_notify, $current_office_id);
                            
                            // 2. Prepare the SMS Content
                            $sms_date = date('M d, Y', strtotime($date));
                            $sms_title = strtoupper($title);
                            $sms_content = "SMARTSERVE ADVISORY\n\n{$sms_title}\n\n{$details}";

                            $recipients = [];
                            if ($sms_notify === 'none') {
                                $recipients = [];
                            } elseif ($sms_notify === 'all_students') {
                                $recipients = $model->displayAccounts(3);
                            } elseif ($sms_notify === 'students_pending_today_my_office') {
                                if ($current_office_id > 0) {
                                    $recipients = $model->getPendingSMSAppointmentsByOffice($current_office_id);
                                }
                            } elseif ($sms_notify === 'all_pending_students') {
                                $recipients = $model->getAllPendingSMSAppointments();
                            }

                            if ($sms_notify === 'students_pending_today_my_office' && $current_office_id <= 0) {
                                $error_message = 'SMS Notify option "Students with pending appointments today in my office" requires an office-assigned account.';
                            } elseif ($sms_notify !== 'none') {
                                sendAnnouncementSmsBatch($recipients, $sms_content);
                            }

                            echo "<script>window.open('announcement', '_self');</script>";
                        }
                    ?>
                    <div align="right" class="m-b30">
                        <a href="" class="btn green radius-xl" style="background-color: <?php echo $primary_color; ?>;" data-toggle="modal" data-target="#add-announcement"><i class="ti-plus"></i><span><b>ADD NEW ADVISORY</b></span></a>
                    </div>

                    <div class="row">
                        <?php
                            $rows = $model->displayMyAnnouncements($_SESSION['sess'], $category, $status);
                            if (!empty($rows)) {
                                foreach ($rows as $row) {
                        ?>
                        <div class="col-lg-12 m-b30">
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
                                                <button data-toggle="modal" data-target="#edit-<?php echo $row['id']; ?>" class="btn blue btn-sm radius-xl"><i class="ti-marker-alt"></i> <b>Edit</b></button>
                                                <button data-toggle="modal" data-target="#archive-<?php echo $row['id']; ?>" class="btn red btn-sm radius-xl"><i class="ti-archive"></i> <b>Archive</b></button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div id="edit-<?php echo $row['id']; ?>" class="modal fade" role="dialog">
                            <form class="edit-profile" method="POST" enctype="multipart/form-data">
                                <div class="modal-dialog modal-md">
                                    <div class="modal-content">
                                        <div class="modal-header">
                                            <h4 class="modal-title">Edit Advisory</h4>
                                            <button type="button" class="close" data-dismiss="modal">&times;</button>
                                        </div>
                                        <div class="modal-body">
                                            <input type="hidden" name="edit-id" value="<?php echo $row['id']; ?>">
                                            <div class="form-group">
                                                <label>Title</label>
                                                <input class="form-control" name="title" type="text" value="<?php echo $row['title']; ?>" required>
                                            </div>
                                            <div class="form-group text-center">
                                                <img id="display-img-<?php echo $row['id']; ?>" src="../assets/img/announcement/<?php echo $row['image_unique']; ?>.jpg" style="width: 100%; height: 150px; object-fit: cover; border-radius: 8px;">
                                                <input accept="image/jpeg,image/png" type="file" name="image" class="m-t10" onchange="readURL(this, '<?php echo $row['id']; ?>')">
                                            </div>
                                            <div class="form-group">
                                                <label>Details</label>
                                                <textarea class="form-control" name="details" rows="4" required><?php echo $row['details']; ?></textarea>
                                            </div>
                                            <div class="form-group">
                                                <label>Date</label>
                                                <input class="form-control" name="date" type="datetime-local" value="<?php echo date('Y-m-d\TH:i:s', strtotime($row['date'])); ?>" required>
                                            </div>
                                        </div>
                                        <div class="modal-footer">
                                            <input type="submit" class="btn green radius-xl" name="edit" value="Save Changes">
                                                <button type="button" class="btn btn-secondary radius-xl" data-dismiss="modal">Cancel</button>
                                        </div>
                                    </div>
                                </div>
                            </form>
                        </div>

                        <div id="archive-<?php echo $row['id']; ?>" class="modal fade" role="dialog">
                            <form method="POST">
                                <div class="modal-dialog modal-md">
                                    <div class="modal-content">
                                        <div class="modal-header"><h4 class="modal-title">Archive Advisory</h4><button type="button" class="close" data-dismiss="modal">&times;</button></div>
                                        <div class="modal-body text-center">
                                            <input type="hidden" name="archive-id" value="<?php echo $row['id']; ?>">
                                            <span>Are you sure you want to archive this?</span><br>
                                            <span><strong><?php echo htmlspecialchars($row['title']); ?></strong></span>
                                        </div>
                                        <div class="modal-footer">
                                            <input type="submit" class="btn red radius-xl" name="archive" value="Confirm Archive">
                                            <button type="button" class="btn btn-secondary radius-xl" data-dismiss="modal">Cancel</button>
                                        </div>
                                    </div>
                                </div>
                            </form>
                        </div>
                        <?php 
                                }
                            } 
                            else {
                                echo '<div class="ss-empty-advisories"><h5>No advisories yet</h5><p>Published updates will appear here.</p></div>'; 
                            }
                        ?>
                    </div>

                    <?php
                        // PHP Logic for Edit/Archive
                        if (isset($_POST['edit'])) {
                            $model->editAnnouncement($_POST['title'], $_POST['details'], $_POST['date'], $_POST['edit-id']);
                            if (isset($_FILES['image']) && $_FILES['image']['error'] != UPLOAD_ERR_NO_FILE) {
                                $unique = time().uniqid(rand());
                                move_uploaded_file($_FILES["image"]["tmp_name"], "../assets/img/announcement/" . $unique . '.jpg');
                                $model->editImageAnnouncement(basename($_FILES["image"]["name"]), $unique, $_POST['edit-id']);
                            }
                            echo "<script>window.open('announcement', '_self');</script>";
                        }
                        if (isset($_POST['archive'])) {
                            $model->archiveAnnouncement(2, $_POST['archive-id']);
                            echo "<script>window.open('announcement', '_self');</script>";
                        }
                    ?>

                    <div id="add-announcement" class="modal fade" role="dialog">
                        <form class="edit-profile" method="POST" enctype="multipart/form-data">
                            <div class="modal-dialog modal-md">
                                <div class="modal-content">
                                    <div class="modal-header"><h4 class="modal-title">Add Advisory</h4><button type="button" class="close" data-dismiss="modal">&times;</button></div>
                                    <div class="modal-body">
                                        <div class="form-group"><label>Title</label><input class="form-control" type="text" name="title" required></div>
                                        <div class="form-group">
                                            <img id="display-img-add" style="width: 100%; height: 150px; object-fit: cover; display:none; border-radius: 8px;">
                                            <label>Image</label><input accept="image/jpeg,image/png" type="file" name="image" onchange="readURL(this, 'add')" class="form-control" required>
                                        </div>
                                        <div class="form-group"><label>Details</label><textarea class="form-control" name="details" required rows="4"></textarea></div>
                                        <div class="form-group"><label>Date</label><input class="form-control" type="datetime-local" name="date" required></div>
                                        <div class="form-group">
                                            <label>SMS Notify</label>
                                            <select class="form-control" name="sms_notify" required>
                                                <option value="none">None</option>
                                                <option value="all_students">All students</option>
                                                <option value="students_pending_today_my_office">Students with pending appointments today in my office</option>
                                                <option value="all_pending_students">All students with pending appointments</option>
                                            </select>
                                        </div>
                                        <div class="form-group">
                                            <label>Email Notify</label>
                                            <select class="form-control" name="email_notify" required>
                                                <option value="all_students">All students</option>
                                                <option value="none">None</option>
                                                <option value="students_pending_today_my_office">Students with pending appointments today in my office</option>
                                                <option value="all_pending_students">All students with pending appointments</option>
                                            </select>
                                            <small class="text-muted">Email notifications use the SmartServe school template.</small>
                                        </div>
                                    </div>
                                    <div class="modal-footer">
                                        <input type="submit" class="btn green radius-xl" name="add_announcement" value="Add Advisory">
                                        <button type="button" class="btn red outline radius-xl" data-dismiss="modal">Close</button>
                                    </div>
                                </div>
                            </div>
                        </form>
                    </div>

                </div>
            </div>
        </div>
    <?php require __DIR__ . '/../global/advisory-footer.php'; ?>
</main>

    <script src="../dashboard/assets/js/jquery.min.js"></script>
    <script src="../dashboard/assets/vendors/bootstrap/js/popper.min.js"></script>
    <script src="../dashboard/assets/vendors/bootstrap/js/bootstrap.min.js"></script>
    <script src="../dashboard/assets/js/admin.js"></script>
    <script>
        function toggleDetails(element) {
            var $card = $(element).closest('.card-body');
            var $short = $card.find('.short-text');
            var $full = $card.find('.full-text');
            if ($full.is(':hidden')) {
                $short.hide(); $full.fadeIn().css('display', 'inline'); $(element).text('See Less');
            } else {
                $short.fadeIn().css('display', 'inline'); $full.hide(); $(element).text('See More');
            }
        }
        function readURL(input, id) {
            if (input.files && input.files[0]) {
                var reader = new FileReader();
                reader.onload = function (e) {
                    $('#display-img-' + id).attr('src', e.target.result).show();
                }
                reader.readAsDataURL(input.files[0]);
            }
        }
    </script>
</body>
</html>