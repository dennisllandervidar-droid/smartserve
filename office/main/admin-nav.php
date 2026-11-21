<?php include '../styles/assets/css/color/color-1.php';  ?>
<style>
	  body {
    font-family: 'Open Sans', 'Roboto', sans-serif; /* Body font */
    font-weight: 400;
    color: #333;
  }

  h1, h2, h3, h4, h5, h6 {
    font-family: 'Montserrat', 'Poppins', 'Lato', sans-serif; /* Heading font */
    font-weight: 600;
  }

</style>
	<body class="ttr-opened-sidebar ttr-pinned-sidebar"  style="background-color: #FCFCFC!important;">
	<header class="ttr-header">
		<div class="ttr-header-wrapper">
			<div class="ttr-toggle-sidebar ttr-material-button">
				<i class="ti-menu ttr-open-icon"></i>
				<i class="ti-close ttr-close-icon"></i>
			</div>
			<div class="ttr-logo-box">
				<div>
					<a href="index" class="ttr-logo">
						<img alt="" class="ttr-logo-mobile" src="../assets/img/dashboard-logo.png" style="width: 295px; height: 40px;">
						<img alt="" class="ttr-logo-desktop" src="../assets/img/dashboard-logo.png" style="width: 295px; height: 40px;">
					</a>
				</div>
			</div>
			<div class="ttr-header-menu">
			</div>
			<div class="ttr-header-right ttr-with-seperator">
				<ul class="ttr-header-navigation">
					<li>
						<!-- <a href="#" class="ttr-material-button ttr-submenu-toggle">
							<i class="fa fa-cog" style="font-size:30px;"></i>
						</a> -->
						<div class="ttr-header-submenu noti-menu">
							<div class="ttr-notify-header">
								<span class="ttr-notify-text-top" style="color: black!important;"><b><?php echo $account_fname; ?> <?php echo $account_lname; ?></b></span>
								<span class="ttr-notify-text" style="color: black!important;"><?php echo $account_access_txt; ?></span>
							</div>
							<div class="noti-box-list">
								<ul>
									<a href="my-profile" style="text-decoration: none;color: black;"><li>
										
										<span class="notification-icon dashbg-green">
											<i class="fa fa-user" style="font-size: 18px;"></i>
										</span>
										<span style="font-size: 22px;" style="color: black!important;">
											My Profile
										</span>
									</li></a><hr>
									<a href="main/logout" style="text-decoration: none;color: black;"><li>
										<span class="notification-icon dashbg-red">
											<i class="fa fa-sign-out" style="font-size: 18px;"></i>
										</span>
										<span style="font-size: 22px;" style="color: black!important;">
											Logout
										</span>
									</li></a>
								</ul>
							</div>
						</div>
					</li>
				</ul>
			</div>
		</div>
	</header>

	<div class="ttr-sidebar">
		<div class="ttr-sidebar-wrapper content-scroll">
            <div class="ttr-sidebar-logo" style="background-image: url('../assets/img/dashboard-cover.jpg');background-position: center;background-repeat: no-repeat;background-size: cover;height: 135px;">
                <div class="ttr-sidebar-toggle-button">
                    <i class="ti-arrow-left" style="color: white;"></i>
                </div>
                <div style="padding-left: 12px; padding-top: 12px;">
                    <div class="image">
                        <img src="../assets/img/profile-pictures/<?php echo $account_photo; ?>.jpg" style="width: 50px; height: 50px; border-radius: 50%;object-fit: cover;" alt="User">
                    </div>
                    <div style="height: 8px;">
                    </div>
                    <div class="info-container">
                        <div class="name" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false" style="color: white; font-size: 15px;"><b><?php echo $account_fname; ?> <?php echo $account_lname; ?></b></div>
                        <div class="email" style="color: white; font-size: 12px;"><?php echo $account_uname; ?></div>
                    </div>
                </div>
            </div>

			<style type="text/css">
				.show2 {
					background-color: <?php echo $secondary_color; ?>!important;
					color: white!important;
				}
				.txtclr {
					color: <?php echo $secondary_color; ?>!important;
				}
				.secondnav {
					background-color: #eeeeee!important;
					color: white!important;
				}
				/* Target only the sub-menus inside the sidebar navigation */
                .ttr-sidebar-navi > ul > li > ul {
                  display: none; /* Hide sub-menus by default */
                  padding-left: 0;
                }
                
                /* Show the sub-menu when the user hovers over the parent list item */
                .ttr-sidebar-navi > ul > li:hover > ul {
                  display: block !important; /* Force display block on hover */
                  animation: slideFadeDown 0.3s ease forwards;
                }
                
                /* Optional: Smoothly rotate the dropdown arrow when hovered */
                .ttr-sidebar-navi > ul > li .ttr-arrow-icon {
                  transition: transform 0.3s ease;
                }
                
                .ttr-sidebar-navi > ul > li:hover .ttr-arrow-icon {
                  transform: rotate(-180deg);
                }
                
                /* Keyframes for a smooth dropdown entrance */
                @keyframes slideFadeDown {
                  0% {
                    opacity: 0;
                    transform: translateY(-8px);
                  }
                  100% {
                    opacity: 1;
                    transform: translateY(0);
                  }
                }
                
                /* Ensure the active/clicked menu stays open if you want it to */
                .ttr-sidebar-navi > ul > li.show > ul {
                  display: block;
                }
			</style>


            <nav class="ttr-sidebar-navi">
                <ul>
                    <li style="padding-left: 20px; padding-top: 5px; padding-bottom: 5px; background-color: #b3b3b3; margin-top: -1px; margin-bottom: 0px;">
                        <span class="ttr-label" style="color: black; font-weight: 800;">Menu</span>
                    </li>

                    <!-- Profile -->
                    <li style="margin-top: -1px;">
                        <a href="index" class="ttr-material-button <?php echo ($page == 'my-profile') ? 'show2' : ''; ?>">
                            <span class="ttr-icon">
                                <i class="ti-home <?php echo ($page == 'my-profile') ? 'show2' : ''; ?>"></i>
                            </span>
                            <span class="ttr-label <?php echo ($page == 'my-profile') ? 'show2' : ''; ?>">Profile</span>
                        </a>
                    </li>

                    <!-- Accounts -->
                    <!--<li class="<?php echo ($page == 'accounts') ? 'show' : ''; ?>">-->
                    <!--    <a href="#" class="ttr-material-button <?php echo ($page == 'accounts') ? 'show2' : ''; ?>">-->
                    <!--        <span class="ttr-icon">-->
                    <!--            <i class="ti-user"></i> </span>-->
                    <!--        <span class="ttr-label">Accounts</span>-->
                    <!--        <span class="ttr-arrow-icon"><i class="fa fa-angle-up"></i></span> </a>-->
                    <!--    <ul>-->
                    <!--        <li class="<?php echo ($secondnav == 'admins') ? 'secondnav' : ''; ?>">-->
                    <!--            <a href="account-admins" class="ttr-material-button">-->
                    <!--                <span class="ttr-label">Administrators</span>-->
                    <!--            </a>-->
                    <!--        </li>-->
                    <!--        <li class="<?php echo ($secondnav == 'offices') ? 'secondnav' : ''; ?>">-->
                    <!--            <a href="account-offices" class="ttr-material-button">-->
                    <!--                <span class="ttr-label">Offices</span>-->
                    <!--            </a>-->
                    <!--        </li>-->
                    <!--        <li class="<?php echo ($secondnav == 'students') ? 'secondnav' : ''; ?>">-->
                    <!--            <a href="account-students" class="ttr-material-button">-->
                    <!--                <span class="ttr-label">Students</span>-->
                    <!--            </a>-->
                    <!--        </li>-->
                    <!--    </ul>-->
                    <!--</li>-->

                    <li class="<?php echo ($page == 'appointments') ? 'show' : ''; ?>">
                        <a href="#" class="ttr-material-button <?php echo ($page == 'appointments') ? 'show2' : ''; ?>">
                            <span class="ttr-icon">
                                <i class="ti-calendar"></i> 
                            </span>
                            <span class="ttr-label">Appointments</span>
                            <span class="ttr-arrow-icon"><i class="fa fa-angle-up"></i></span> 
                        </a>
                        <ul>
                            <li class="<?php echo ($secondnav == 'queuing') ? 'secondnav' : ''; ?>">
                                <a href="queue" class="ttr-material-button">
                                    <span class="ttr-label">Queuing Dashboard</span>
                                </a>
                            </li>
                            <!--<li class="<?php echo ($secondnav == 'walk-in') ? 'secondnav' : ''; ?>">-->
                            <!--    <a href="walk-in" class="ttr-material-button">-->
                            <!--        <span class="ttr-label">Walk-in</span>-->
                            <!--    </a>-->
                            <!--</li>-->
                            <li class="<?php echo ($secondnav == 'history') ? 'secondnav' : ''; ?>">
                                <a href="history" class="ttr-material-button">
                                    <span class="ttr-label">Appointment Records</span>
                                </a>
                            </li>
                        </ul>
                    </li>
                    

                    <li class="<?php echo ($page == 'announcements') ? 'show' : ''; ?>">
                        <a href="#" class="ttr-material-button <?php echo ($page == 'announcements') ? 'show2' : ''; ?>">
                            <span class="ttr-icon">
                                <i class="ti-announcement"></i> </span>
                            <span class="ttr-label">Advisories</span>
                            <span class="ttr-arrow-icon"><i class="fa fa-angle-up"></i></span> </a>
                        <ul>
                            <li class="<?php echo ($secondnav == 'announcements') ? 'secondnav' : ''; ?>">
                                <a href="announcement" class="ttr-material-button">
                                    <span class="ttr-label">My Advisories</span>
                                </a>
                            </li>
                            <li class="<?php echo ($secondnav == 'newsfeed') ? 'secondnav' : ''; ?>">
                                <a href="newsfeed" class="ttr-material-button">
                                    <span class="ttr-label">Newsfeed</span>
                                </a>
                            </li>
                        </ul>
                    </li>


                    <!-- Separator -->
                    <li class="ttr-seperate"></li>


                    <li style="margin-top: -1px;">
                        <a href="main/logout" class="ttr-material-button">
                            <span class="ttr-icon">
                                <i class="ti-power-off" style="color: #ff4d4d; font-weight: 600;"></i>
                            </span>
                            <span class="ttr-label" style="color: #ff4d4d; font-weight: 600;">Logout</span>
                        </a>
                    </li>
      
                </ul>


            </nav>



		</div>
	</div>

    <style>
    /* Primary (blue) */
    .btn-primary {
      background-color: #007bff !important;
      border-color: #007bff !important;
      color: #fff !important;
    }
    .btn-primary:hover {
      background-color: #0069d9 !important;
      border-color: #0062cc !important;
    }

    /* Secondary (gray) */
    .btn-secondary {
      background-color: #6c757d !important;
      border-color: #6c757d !important;
      color: #fff !important;
    }
    .btn-secondary:hover {
      background-color: #5a6268 !important;
      border-color: #545b62 !important;
    }

    /* Success (green) */
    .btn-success {
      background-color: #28a745 !important;
      border-color: #28a745 !important;
      color: #fff !important;
    }
    .btn-success:hover {
      background-color: #218838 !important;
      border-color: #1e7e34 !important;
    }

    /* Danger (red) */
    .btn-danger {
      background-color: #dc3545 !important;
      border-color: #dc3545 !important;
      color: #fff !important;
    }
    .btn-danger:hover {
      background-color: #c82333 !important;
      border-color: #bd2130 !important;
    }

    /* Warning (yellow) */
    .btn-warning {
      background-color: #ffc107 !important;
      border-color: #ffc107 !important;
      color: #212529 !important;
    }
    .btn-warning:hover {
      background-color: #e0a800 !important;
      border-color: #d39e00 !important;
    }

    /* Info (teal) */
    .btn-info {
      background-color: #17a2b8 !important;
      border-color: #17a2b8 !important;
      color: #fff !important;
    }
    .btn-info:hover {
      background-color: #138496 !important;
      border-color: #117a8b !important;
    }
    </style>