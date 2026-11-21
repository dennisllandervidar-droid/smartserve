<?php
    include('../global/model.php');
    $model = new Model();
    include('main/department.php');

    if (isset($_POST['add_faq'])) {
        $question = trim($_POST['question']);
        $answer = trim($_POST['answer']);
        $model->addFaq($question, $answer);
        echo "<script>window.location.href='faqs';</script>";
        exit();
    }

    if (isset($_POST['edit_faq'])) {
        $faq_id = (int)$_POST['faq_id'];
        $question = trim($_POST['question']);
        $answer = trim($_POST['answer']);
        $model->editFaq($faq_id, $question, $answer);
        echo "<script>window.location.href='faqs';</script>";
        exit();
    }

    if (isset($_POST['delete_faq'])) {
        $faq_id = (int)$_POST['faq_id'];
        $model->deleteFaq($faq_id);
        echo "<script>window.location.href='faqs';</script>";
        exit();
    }

    $faqs = $model->displayFaqs();
?>
<!DOCTYPE html>
<html lang="en">
    <head>
    <?php require_once __DIR__ . '/../global/form-assets.php'; ?>
        <meta charset="utf-8">
        <meta http-equiv="X-UA-Compatible" content="IE=edge">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <link rel="icon" href="../assets/img/<?php echo $web_icon; ?>.png" type="image/x-icon" />
        <title><?php echo $web_name; ?> | FAQs</title>

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
            .btn-view { background: #eef2f7; color: #3b82f6; }
            .btn-view:hover { background: #3b82f6; color: #ffffff; }
            .btn-edit { background: #fff3cd; color: #856404; }
            .btn-edit:hover { background: #856404; color: #ffffff; }
            .btn-archive { background: #fdeceb; color: #dc3545; }
            .btn-archive:hover { background: #dc3545; color: #ffffff; }
            .faq-summary { background: linear-gradient(135deg, #ffffff, #f8fbf9); border: 1px solid rgba(0,0,0,0.05); border-radius: 18px; padding: 18px 22px; margin-bottom: 24px; }
            .faq-summary h5 { margin-bottom: 4px; font-weight: 700; color: #2d3436; }
            .faq-summary p { margin-bottom: 0; color: #6c757d; }
            .faq-answer-cell { max-width: 520px; white-space: normal; }
        </style>
    </head>

    <body class="ttr-opened-sidebar ttr-pinned-sidebar" style="background-color: #FCFCFC;">
        <?php
            $page = 'faqs';
            $secondnav = '';
            include 'main/admin-nav.php';
        ?>
        <main class="ttr-wrapper" style="background-color: #FCFCFC;">
            <div class="container-fluid">
                <div class="db-breadcrumb">
                    <h4 class="breadcrumb-title"><i class="ti ti-help-alt"></i> FAQs</h4>
                    <ul class="db-breadcrumb-list"><li>Frequently Asked Questions</li></ul>
                </div>

                <div class="row">
                    <div class="col-lg-12 m-b30">
                        <div align="right" class="mb-4">
                            <a href="" class="btn green radius-xl" style="background-color: <?php echo $primary_color; ?>; padding: 10px 20px; font-weight: 600;" data-toggle="modal" data-target="#add-faq">
                                <i class="ti-plus"></i><span>&nbsp;ADD NEW FAQ</span>
                            </a>
                        </div>

                        <div id="add-faq" class="modal fade" role="dialog">
                            <form method="POST">
                                <div class="modal-dialog modal-md">
                                    <div class="modal-content" style="border-radius: 15px;">
                                        <div class="modal-header">
                                            <h4 class="modal-title" style="font-weight: 600;">Add FAQ</h4>
                                            <button type="button" class="close" data-dismiss="modal">&times;</button>
                                        </div>
                                        <div class="modal-body">
                                            <div class="form-group">
                                                <label class="col-form-label">Question</label>
                                                <input class="form-control" type="text" name="question" required placeholder="Enter the FAQ question">
                                            </div>
                                            <div class="form-group mb-0">
                                                <label class="col-form-label">Answer</label>
                                                <textarea class="form-control" name="answer" rows="5" required placeholder="Enter the FAQ answer"></textarea>
                                            </div>
                                        </div>
                                        <div class="modal-footer">
                                            <input type="submit" class="btn btn-success radius-xl" style="background-color: <?php echo $primary_color; ?>;" name="add_faq" value="Save FAQ">
                                            <button type="button" class="btn btn-secondary radius-xl" data-dismiss="modal">Close</button>
                                        </div>
                                    </div>
                                </div>
                            </form>
                        </div>

                 

                        <div class="modern-table-wrapper">
                            <div class="table-responsive">
                                <table id="table" class="table hover display" style="width:100%">
                                    <thead>
                                        <tr>
                                            <th width="60">ID</th>
                                            <th>Question</th>
                                            <th class="faq-answer-cell">Answer</th>
                                            <th>Status</th>
                                            <th width="140" class="text-center">Action</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if (!empty($faqs)) { ?>
                                            <?php foreach ($faqs as $faq) { 
                                                $faq_id = (int)$faq['faq_id'];
                                                $status = (int)$faq['status'];
                                                $status_txt = ($status === 1) ? '<span class="badge-soft-success">Active</span>' : '<span class="badge-soft-danger">Deleted</span>';
                                            ?>
                                            <tr>
                                                <td><?php echo $faq_id; ?></td>
                                                <td><strong style="color: #2d3436;"><?php echo htmlspecialchars($faq['question']); ?></strong></td>
                                                <td class="faq-answer-cell"><?php echo nl2br(htmlspecialchars($faq['answer'])); ?></td>
                                                <td><?php echo $status_txt; ?></td>
                                                <td class="text-center">
                                                    <a href="javascript:void(0);" class="btn-action btn-view" data-toggle="modal" data-target="#edit-<?php echo $faq_id; ?>" title="Edit FAQ">
                                                        <i class="ti-pencil"></i>
                                                    </a>

                                                    <?php if ($status === 1) { ?>
                                                        <form method="POST" style="display:inline;">
                                                            <input type="hidden" name="faq_id" value="<?php echo $faq_id; ?>">
                                                            <button type="submit" name="delete_faq" class="btn-action btn-archive ml-1" data-ss-confirm="Delete this FAQ?" title="Delete FAQ">
                                                                <i class="ti-trash"></i>
                                                            </button>
                                                        </form>
                                                    <?php } ?>
                                                </td>
                                            </tr>

                                            <div id="edit-<?php echo $faq_id; ?>" class="modal fade" role="dialog">
                                                <form method="POST">
                                                    <div class="modal-dialog modal-md">
                                                        <div class="modal-content" style="border-radius: 15px;">
                                                            <div class="modal-header">
                                                                <h4 class="modal-title" style="font-weight: 600;">Edit FAQ</h4>
                                                                <button type="button" class="close" data-dismiss="modal">&times;</button>
                                                            </div>
                                                            <div class="modal-body">
                                                                <input type="hidden" name="faq_id" value="<?php echo $faq_id; ?>">
                                                                <div class="form-group">
                                                                    <label class="col-form-label">Question</label>
                                                                    <input class="form-control" type="text" name="question" value="<?php echo htmlspecialchars($faq['question']); ?>" required>
                                                                </div>
                                                                <div class="form-group mb-0">
                                                                    <label class="col-form-label">Answer</label>
                                                                    <textarea class="form-control" name="answer" rows="5" required><?php echo htmlspecialchars($faq['answer']); ?></textarea>
                                                                </div>
                                                            </div>
                                                            <div class="modal-footer">
                                                                <input type="submit" class="btn btn-success radius-xl" style="background-color: <?php echo $primary_color; ?>;" name="edit_faq" value="Save Changes">
                                                                <button type="button" class="btn btn-secondary radius-xl" data-dismiss="modal">Close</button>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </form>
                                            </div>
                                            <?php } ?>
                                        <?php } else { ?>
                                            <tr>
                                                <td colspan="5" class="text-center text-muted py-4">No FAQs found.</td>
                                            </tr>
                                        <?php } ?>
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
        <script>$(document).ready(function() { $('#table').DataTable(); });</script>
    </body>
</html>