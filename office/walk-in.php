<?php
    ob_start();
    include('../global/model.php');
    $model = new Model();

    include('main/department.php');
    $account_id = $_SESSION['sess']; 
    
    // FETCH OFFICE ID (Using your new multi-window structure)
    $staff_details = $model->displayAccountDetails($account_id);
    $office_id = null;
    if (!empty($staff_details)) {
        foreach ($staff_details as $dep) {
            $office_id = $dep['course'] ?? null; 
            break;
        }
    }

    // Handle Walk-in Form Submission
    if (isset($_POST['submit_walkin'])) {
        $student_id = $_POST['student_id'] ?? null; // Will be empty if it's a guest
        $guest_name = null;
        
        // If student_id is empty, it means they typed a custom name. We grab that from the visible input field.
        if(empty($student_id)) {
            $guest_name = trim($_POST['manual_name_input']);
        }
        
        $notes = trim($_POST['notes']);
        // Add this to capture the radio button value
        $priority = $_POST['priority'] ?? 'N/A';
        $is_priority = $priority === 'N/A' ? 0 : 1;

        if(empty($student_id) && empty($guest_name)) {
            smartserve_notify('Error: Please select a student or type a guest name.', 'danger');
        } else {
            // Pass the new variable here
            $result = $model->addWalkInAppointment($student_id, $guest_name, $office_id, $notes, $is_priority, $priority);
            
            if ($result['status'] == 'success') {
                $q_num = $result['queue_number'];
                
                smartserve_notify('Walk-in registered! Queue number: ' . $q_num, 'success', 'queue');
                exit;
            } else {
                smartserve_notify('System error: ' . $result['msg'], 'danger');
            }
        }
    }

    // Fetch all students and encode them to JSON for our custom JS search
    $students = $model->getStudents();
    $students_json = json_encode($students);

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <?php require_once __DIR__ . '/../global/form-assets.php'; ?>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="icon" href="../assets/img/<?php echo $web_icon; ?>.png" type="image/x-icon" />
    <title><?php echo $web_name; ?> | Office Walk-in</title>
    
    <link rel="stylesheet" type="text/css" href="../dashboard/assets/css/dataTables.bootstrap4.min.css">
    <link rel="stylesheet" type="text/css" href="../dashboard/assets/css/assets.css">
    <link rel="stylesheet" type="text/css" href="../dashboard/assets/css/typography.css">
    <link rel="stylesheet" type="text/css" href="../dashboard/assets/css/style.css">
    <link rel="stylesheet" type="text/css" href="../dashboard/assets/css/dashboard.css">
    
    <style>
        :root { --primary-green: <?php echo $primary_color; ?>; --soft-green: #eef7f1; }
        
        .appointment-card { background: #fff; border-radius: 20px; box-shadow: 0 10px 30px rgba(0,0,0,0.05); padding: 40px; border: 1px solid rgba(0,0,0,0.05); }
        .form-label { font-weight: 600; color: #2d3436; margin-bottom: 8px; display: block; }
        
        .custom-input { 
            border-radius: 12px; border: 2px solid #eee; padding: 12px 20px; 
            width: 100%; outline: none; transition: 0.3s; background: #fff;
            color: #495057; font-size: 1rem;
        }
        .custom-input:focus { border-color: var(--primary-green); box-shadow: 0 0 0 4px var(--soft-green); }

        .readonly-box { background: #f8f9fa; border: 2px solid #eee; border-radius: 12px; padding: 12px 20px; color: #6c757d; font-weight: 500; cursor: not-allowed; }

        /* --- Custom Search Dropdown Styles --- */
        .search-container { position: relative; }
        .search-dropdown {
            position: absolute;
            top: 100%; left: 0; right: 0;
            background: #fff;
            border: 1px solid #ddd;
            border-radius: 12px;
            box-shadow: 0 10px 25px rgba(0,0,0,0.1);
            max-height: 250px;
            overflow-y: auto;
            z-index: 1000;
            display: none;
            margin-top: 5px;
        }
        .search-item {
            padding: 12px 20px;
            border-bottom: 1px solid #f8f9fa;
            cursor: pointer;
            transition: background 0.2s;
        }
        .search-item:last-child { border-bottom: none; }
        .search-item:hover { background: var(--soft-green); }
        .search-item-title { font-weight: 600; color: #2d3436; display: block; }
        .search-item-sub { font-size: 0.8rem; color: #6c757d; }
        .no-results { padding: 15px; text-align: center; color: #6c757d; font-style: italic; }
    </style>
</head>
<body class="ttr-opened-sidebar ttr-pinned-sidebar" style="background-color: #FCFCFC;">
    <?php
        $page = 'appointments';
        $secondnav = 'walk-in';
        include 'main/admin-nav.php'; 
    ?>

    <main class="ttr-wrapper" style="background-color: #FCFCFC;">
        <div class="container-fluid">
            <div class="db-breadcrumb">
                <h4 class="breadcrumb-title"><i class="ti ti-calendar"></i> Appointment</h4>
                <ul class="db-breadcrumb-list"><li>Walk-in</li></ul>
            </div>
            
            <div class="row justify-content-center">
                <div class="col-lg-12 m-b30">
                    <div class="appointment-card">
                        
                        <div class="d-flex justify-content-between align-items-center mb-4 border-bottom pb-3">
                            <div>
                                <h3 class="fw-bold m-0" style="color: var(--primary-green);">Walk-in Registration</h3>
                                <p class="text-muted m-0">Generate an instant queue number for a student or guest.</p>
                            </div>
                            <div class="text-center bg-light p-2" style="border-radius: 10px;">
                                <small class="text-uppercase text-muted fw-bold d-block">Current Time</small>
                                <span class="fw-bold text-dark" style="font-size: 1.1rem;"><?php echo date('h:i A'); ?></span>
                            </div>
                        </div>

                        <form method="POST" id="walkinForm">
                            
                            <div class="form-group m-b25">
                                <label class="form-label">1. Student or Guest Name</label>
                                <div class="search-container">
                                    <input type="text" name="manual_name_input" id="searchInput" class="custom-input" placeholder="Type name, email, or course to search, OR type a guest name..." autocomplete="off" required>
                                    
                                    <input type="hidden" name="student_id" id="hiddenStudentId">
                                    
                                    <div id="searchDropdown" class="search-dropdown"></div>
                                </div>
                                <small class="text-muted mt-2 d-block"><i class="ti-info-circle"></i> If the person is not a registered student, simply type their full name above.</small>
                            </div>
                            
                            <div class="form-group m-b25">
                                <label class="form-label">2. Priority Type</label>
                                <p id="verified-priority" class="text-success" hidden></p>
                                <div id="guest-priority" class="d-flex" style="gap: 12px; align-items: center; padding: 10px 0; flex-wrap:wrap;">
                                    <label style="cursor: pointer; display: flex; align-items: center; gap: 8px; font-weight: 500;">
                                        <input type="radio" name="priority" value="PWD" style="width: 18px; height: 18px;"> PWD
                                    </label>
                                    <label style="cursor: pointer; display: flex; align-items: center; gap: 8px; font-weight: 500;">
                                        <input type="radio" name="priority" value="N/A" checked style="width: 18px; height: 18px;"> N/A
                                    </label>
                                    <label><input type="radio" name="priority" value="Senior Citizen"> Senior Citizen</label>
                                    <label><input type="radio" name="priority" value="Pregnant"> Pregnant</label>
                                </div>
                                <small class="text-danger d-block" style="font-weight: 600;">
                                    <i class="ti-alert"></i> Note: Registered students use the priority verified on their profile.
                                </small>
                            </div>

                            <div class="row m-b25">
                                <div class="col-md-6 form-group">
                                    <label class="form-label">Date</label>
                                    <div class="readonly-box"><i class="ti-calendar"></i> <?php echo date('M d, Y'); ?></div>
                                </div>
                                <div class="col-md-6 form-group">
                                    <label class="form-label">Status</label>
                                    <div class="readonly-box text-success" style="background: var(--soft-green); border-color: var(--primary-green);"><i class="ti-check"></i> Auto-Queued</div>
                                </div>
                            </div>

                            <div class="form-group m-b30">
                                <label class="form-label">3. Purpose of Visit</label>
                                <textarea name="notes" class="custom-input" rows="3" placeholder="Briefly describe what the person needs..." required></textarea>
                            </div>

                            <div class="form-group border-top pt-4 text-right d-flex justify-content-between align-items-center">
                                <a href="queue" class="btn btn-secondary radius-xl outline">Cancel</a>
                                <button type="submit" name="submit_walkin" class="btn green radius-xl" style="background-color: var(--primary-green); padding: 12px 30px; font-size: 1.1rem; font-weight: 600;">
                                    <i class="ti-ticket"></i> Generate Queue
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </main>
    <div class="ttr-overlay"></div>

    <script src="../dashboard/assets/js/jquery.min.js"></script>
    <script src="../dashboard/assets/vendors/bootstrap/js/bootstrap.min.js"></script>
    <script src="../dashboard/assets/js/admin.js"></script>

    <script>
        $(document).ready(function() {
            const students = <?php echo $students_json; ?>;
            const searchInput = $('#searchInput');
            const searchDropdown = $('#searchDropdown');
            const hiddenId = $('#hiddenStudentId');

            function renderList(list) {
                searchDropdown.empty();
                if (list.length === 0) {
                    searchDropdown.append('<div class="no-results">Press Enter to continue as Guest.</div>');
                } else {
                    list.forEach(function(stu) {
                        const fullName = stu.fname + ' ' + stu.lname;
                        const itemHtml = `
                            <div class="search-item" data-id="${stu.id}" data-name="${fullName}">
                                <span class="search-item-title">${fullName} (${stu.course})</span>
                                <span class="search-item-sub"><i class="ti-email"></i> ${stu.email}</span>
                            </div>
                        `;
                        searchDropdown.append(itemHtml);
                    });
                }
                searchDropdown.show();
            }

            searchInput.on('focus', function() {
                renderList(students);
            });

            searchInput.on('keyup', function() {
                const val = $(this).val().toLowerCase();
                
                // If they start typing something new, clear the hidden ID so they are treated as a guest
                hiddenId.val('');
                $('#guest-priority').addClass('d-flex').show().find('input').prop('disabled', false);
                $('#verified-priority').prop('hidden', true);
                searchInput.css('border-color', '#eee');

                const filtered = students.filter(function(stu) {
                    const searchStr = (stu.fname + ' ' + stu.lname + ' ' + stu.email + ' ' + stu.course).toLowerCase();
                    return searchStr.includes(val);
                });
                renderList(filtered);
            });

            $(document).on('click', '.search-item', function() {
                const selectedId = $(this).data('id');
                const selectedName = $(this).data('name');
                
                searchInput.val(selectedName);
                const selectedStudent = students.find(student => Number(student.id) === Number(selectedId));
                $('#guest-priority').removeClass('d-flex').hide().find('input').prop('disabled', true);
                $('#verified-priority').prop('hidden', false).text('Verified profile priority: ' + (selectedStudent?.effective_priority || 'N/A'));
                hiddenId.val(selectedId);
                
                searchInput.css('border-color', 'var(--primary-green)');
                searchDropdown.hide();
            });

            $(document).on('click', function(e) {
                if (!$(e.target).closest('.search-container').length) {
                    searchDropdown.hide();
                }
            });

            // Removed the old constraint!
            // The form will now submit perfectly fine even if hiddenId is empty.
        });
    </script>
</body>
</html>
