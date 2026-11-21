<?php

    session_start();

    // Require login
    if (!isset($_SESSION['sess'], $_SESSION['username'], $_SESSION['access'])) {
        header("Location: ../login");
        exit();
    }

    // Require admin access only
    // if ($_SESSION['access'] !== 1) {
    //     header("Location: ../login");
    //     exit();
    // }

    if ($_SESSION['access'] == 1) {
        header("Location: ../admin/index");
    } 
    elseif ($_SESSION['access'] == 2) {
        //header("Location: ../office/index");
    } 
    elseif ($_SESSION['access'] == 3) {
        header("Location: ../student/index");
    }
    else {
        unset($_SESSION['sess']);
        header("Location: ../logout");
    }

    // Extra security: check session fingerprint (IP can change on mobile/proxy networks)
    $fingerprint = md5($_SERVER['HTTP_USER_AGENT'] ?? 'unknown-agent');
    if (!isset($_SESSION['fingerprint'])) {
        $_SESSION['fingerprint'] = $fingerprint;
    } 
    elseif ($_SESSION['fingerprint'] !== $fingerprint) {
        // Possible hijacking
        session_unset();
        session_destroy();
        header("Location: ../login.php");
        exit();
    }

    // Optional: session timeout (e.g., 30 minutes)
    // $timeout = 1800; // 30 min
    // if (isset($_SESSION['last_login']) && (time() - $_SESSION['last_login'] > $timeout)) {
    //     session_unset();
    //     session_destroy();
    //     header("Location: ../login?timeout=1");
    //     exit();
    // } 
    // else {
    //     $_SESSION['last_login'] = time(); // refresh timer
    // }
    
    $department = $model->displayAccountDetails($_SESSION['sess']);
    $display_office_name = "Unknown Office"; 
    if (!empty($department)) {
        foreach ($department as $dep) {
            $account_fname        = $dep['fname'] ?? '';
            $account_mname        = $dep['mname'] ?? '';
            $account_lname        = $dep['lname'] ?? '';
            $account_position     = $dep['position'] ?? '';
            $account_uname        = $dep['uname'] ?? '';
            $account_contact      = $dep['contact'] ?? '';
            $account_address      = $dep['address'] ?? '';
            $account_photo        = $dep['photo'] ?? '';
            $account_access       = $dep['access'] ?? '';
            $account_date_created = $dep['date_created'] ?? '';
            $account_status       = $dep['status'] ?? '';
            $account_course       = $dep['course'] ?? '';
            $account_year         = $dep['year'] ?? '';

            switch ($account_access) {
                case 1:
                    $account_access_txt = "Administrator";
                    break;
                case 2:
                    $account_access_txt = "Office";
                    break;
                case 3:
                    $account_access_txt = "Student";
                    break;
                default:
                    $account_access_txt = "Unknown";
                    break;
            }

            // --- FETCH THE OFFICE NAME USING THE ID ---
            if (!empty($account_course)) {
                $all_offices = $model->displayOffices();
                if (!empty($all_offices)) {
                    foreach ($all_offices as $off) {
                        if ($off['id'] == $account_course) {
                            $account_course = $off['office_name'];
                            break; // Stop looping once we find the match
                        }
                    }
                }
            }
        }
    }

    if ($account_status == '0') {
    	unset($_SESSION['sess']);
		echo "<script>window.open('../archived', '_self');</script>";
    }

	$rows = $model->website_details();
	if (!empty($rows)) {
		foreach ($rows as $row) {
			$web_name = $row['web_name'];
			$web_code = strtoupper($row['web_code']);
			$web_header = $row['web_header'];
			$primary_color = $row['primary_color'];
			$secondary_color = $row['secondary_color'];
			$web_icon = $row['web_icon'];
		}
	}

	$page = "";
	$secondnav = "";
		
?> 