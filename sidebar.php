<?php
$current_page = isset($page) ? $page : 'home';
?>
<div class="sidenav" id="my-sidenav">
    <div class="sidenav-brand">
        <span class="sidenav-brand-mark" aria-hidden="true">QM</span>
        <span>
            <strong>QuarterMaster</strong>
            <small>Equipment desk</small>
        </span>
    </div>
    <nav class="sidenav-nav" aria-label="Main navigation">
        <a class="nav-link <?php echo ($current_page === 'home') ? 'active' : ''; ?>" href="index.php?page=home"><span class="nav-link-icon" aria-hidden="true">⌂</span><span>Home</span></a>
        <a class="nav-link <?php echo ($current_page === 'account') ? 'active' : ''; ?>" href="index.php?page=account"><span class="nav-link-icon" aria-hidden="true">◎</span><span>Your Account</span></a>
        <a class="nav-link <?php echo ($current_page === 'booking') ? 'active' : ''; ?>" href="index.php?page=booking"><span class="nav-link-icon" aria-hidden="true">＋</span><span>Create a Booking</span></a>
        <a class="nav-link <?php echo ($current_page === 'bookings') ? 'active' : ''; ?>" href="index.php?page=bookings"><span class="nav-link-icon" aria-hidden="true">▤</span><span>View Bookings</span></a>
        <a class="nav-link <?php echo ($current_page === 'store_details') ? 'active' : ''; ?>" href="index.php?page=store_details"><span class="nav-link-icon" aria-hidden="true">▦</span><span>Store Details</span></a>
        <a class="nav-link <?php echo ($current_page === 'reports') ? 'active' : ''; ?>" href="index.php?page=reports"><span class="nav-link-icon" aria-hidden="true">▥</span><span>Reports</span></a>
        <?php if (isset($_SESSION['user_level']) && in_array($_SESSION['user_level'], [1, 2], true)): ?>
            <a class="nav-link <?php echo ($current_page === 'maintenance') ? 'active' : ''; ?>" href="index.php?page=maintenance"><span class="nav-link-icon" aria-hidden="true">⚒</span><span>Maintenance</span></a>
        <?php endif; ?>
        <a class="nav-link <?php echo ($current_page === 'faq_contact') ? 'active' : ''; ?>" href="index.php?page=faq_contact"><span class="nav-link-icon" aria-hidden="true">?</span><span>F.A.Q &amp; Contact</span></a>
        <?php
        if (isset($_SESSION['user_level']) && in_array($_SESSION['user_level'], [1, 2], true)) {
            echo '<a class="nav-link ' . (($current_page === 'manage_accounts') ? 'active' : '') . '" href="index.php?page=manage_accounts"><span class="nav-link-icon" aria-hidden="true">♙</span><span>Manage Accounts</span></a>';
        }
        ?>
    </nav>
    <div class="sidenav-footer">Scout equipment management</div>
</div>