<div class="sidenav">
    <a href="index.php?page=home">Home</a><br>
    <?php
        if (isset($_SESSION['username'])) {
            echo '<a href="logout.php">Logout</a><br>';
        } else {
            echo '<a href="index.php?page=login">Log In</a><br>';
        }
    ?>
    <a href="index.php?page=account">Account</a><br>
    <a href="index.php?page=booking">Bookings</a><br>
    <a href="index.php?page=store_details">Store Details</a><br>
    <a href="index.php?page=reports">Reports</a><br>
    <a href="index.php?page=faq_contact">F.A.Q</a><br>
    <a href="index.php?page=faq_contact">Contact</a><br>
</div>