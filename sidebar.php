<div class="sidenav" id="my-sidenav">
    <a href="index.php?page=home">Home</a><br>
    <?php
        if (isset($_SESSION['username'])) {
            echo '<a href="logout.php">Logout</a><br>';
        } else {
            echo '<a href="index.php?page=login">Log In</a><br>';
        }
    ?>
    <a href="index.php?page=account">Account</a><br>
    <a href="index.php?page=booking">Create a Booking</a><br>
    <a href="index.php?page=bookings">View Bookings</a><br>
    <a href="index.php?page=store_details">Store Details</a><br>
    <a href="index.php?page=reports">Reports</a><br>
    <a href="index.php?page=faq_contact">F.A.Q & Contact</a><br>
</div>