<!-- Top Bar End -->
<!-- ========== Left Sidebar Start ========== -->
<div class="left side-menu" style="background-image: url('<?= base_url('assets/images/sp_bg.png') ?>');">
    <div class="slimscroll-menu" id="remove-scroll">
        <!--- Sidemenu -->
        <div id="sidebar-menu">
            <!-- Left Menu Start -->
            <ul class="metismenu" id="side-menu">

                <li><a href="<?= base_url('backend/dashboard/admin') ?>" class="waves-effect"><i class="ti-home"></i>
                        <span>Dashboard</span></a></li>
                <li class="menu-title">Content</li>
                <li><a href="<?= base_url('backend/user') ?>" class="waves-effect"><i class="ion ion-md-contact"></i>
                        <span>User</span></a></li>
                <li><a href="<?= base_url('backend/table') ?>" class="waves-effect"><i class="ion ion-md-contact"></i>
                        <span>Table</span></a></li>
                <li><a href="<?= base_url('backend/tableMaster') ?>" class="waves-effect"><i
                            class="ion ion-md-contact"></i>
                        <span>Table Master</span></a></li>
                <li><a href="<?= base_url('backend/rummyTableMaster') ?>" class="waves-effect"><i
                            class="ion ion-md-contact"></i>
                        <span>Rummy Table Master</span></a></li>
                <li><a href="<?= base_url('backend/chips') ?>" class="waves-effect"><i class="ion ion-md-contact"></i>
                        <span>Chips Management</span></a></li>
                <li><a href="<?= base_url('backend/gift') ?>" class="waves-effect"><i
                            class="ion ion-md-contact"></i><span>Gift Management</span></a></li>
                <li><a href="<?= base_url('backend/Purchase') ?>" class="waves-effect"><i
                            class="ion ion-md-contact"></i> <span>Purchase History</span></a></li>
                <li><a href="<?= base_url('backend/Game/Leaderboard') ?>" class="waves-effect"><i
                            class="ion ion-md-contact"></i> <span>Leadboard</span></a></li>
                <li><a href="<?= base_url('backend/notification') ?>" class="waves-effect"><i
                            class="ion ion-md-list-box"></i> <span>Notification</span></a></li>
                <li><a href="<?= base_url('backend/welcomebonus') ?>" class="waves-effect"><i
                            class="ion ion-md-list-box"></i> <span>Welcome Bonus</span></a></li>
                <li><a href="<?= base_url('backend/setting') ?>" class="waves-effect"><i
                            class="ion ion-md-list-box"></i> <span>Setting</span></a></li>
                <li><a href="<?= base_url('backend/WithdrawalLog/ReedemNow') ?>" class="waves-effect"><i
                            class="ion ion-md-list-box"></i> <span>Reedem Management</span></a></li>
                <li><a href="<?= base_url('backend/WithdrawalLog') ?>" class="waves-effect"><i
                            class="ion ion-md-list-box"></i> <span>Withdrawal Log</span></a></li>
            </ul>
        </div>
        <!-- Sidebar -->
        <div class="clearfix"></div>
    </div>
    <!-- Sidebar -left -->

</div>
<!-- Left Sidebar End -->
<!-- ============================================================== -->
<!-- Start right Content here -->
<!-- ============================================================== -->
<div class="content-page">
    <!-- Start content -->
    <div class="content">
        <div class="container-fluid">
            <div class="page-title-box">
                <div class="row align-items-center">
                    <div class="col-sm-6">
                        <h4 class="page-title"><?= $title ?></h4>

                    </div>
                    <div class="col-sm-6">
                        <div class="float-right d-md-block">
                            <?php

                     if (isset($SideBarbutton) && isset($SideBarbutton[1])) {
                     ?>

                            <a href="<?= base_url($SideBarbutton[0]) ?>"
                                class="btn btn-primary btn-lg btn-dashboard custom-btn">
                                <?= $SideBarbutton[1] ?></a>

                            <?php } ?>

                        </div>
                    </div>
                </div>
            </div>
            <!-- end row -->