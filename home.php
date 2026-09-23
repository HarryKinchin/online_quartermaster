<?php
$user_id = (int) ($_SESSION['user_id'] ?? 0);
$username = htmlspecialchars($_SESSION['username'] ?? 'there');
$today = date('Y-m-d');

$stmt_booking_summary = $conn->prepare("SELECT COUNT(*) AS total, SUM(booking_status_id = 1) AS pending, SUM(booking_status_id = 2) AS approved, SUM(booking_status_id = 3) AS collected FROM bookings WHERE user_id = ? AND booking_status_id IN (1, 2, 3)");
$stmt_booking_summary->bind_param('i', $user_id);
$stmt_booking_summary->execute();
$booking_summary = $stmt_booking_summary->get_result()->fetch_assoc();

$stmt_upcoming = $conn->prepare("SELECT b.booking_id, b.event_name, b.event_start, b.collection_date, b.return_date, s.status_name FROM bookings b LEFT JOIN booking_statuses s ON s.status_id = b.booking_status_id WHERE b.user_id = ? AND b.booking_status_id IN (1, 2, 3) AND b.return_date >= ? ORDER BY b.collection_date ASC, b.event_start ASC LIMIT 5");
$stmt_upcoming->bind_param('is', $user_id, $today);
$stmt_upcoming->execute();
$upcoming_bookings = $stmt_upcoming->get_result()->fetch_all(MYSQLI_ASSOC);

$stmt_availability = $conn->prepare("SELECT COUNT(*) AS total_units, SUM(UPPER(TRIM(item_location_code)) NOT IN ('IU', 'RETIRED') AND item_quality <> '0') AS available_units FROM components");
$stmt_availability->execute();
$availability = $stmt_availability->get_result()->fetch_assoc();
?>

<div class="main">
    <main class="home-dashboard">
        <section class="home-dashboard-hero">
            <div>
                <span class="home-dashboard-kicker">Equipment desk</span>
                <h1>Welcome, <?php echo $username; ?></h1>
                <p>Keep track of bookings, collection dates, and equipment availability from one place.</p>
            </div>
            <a class="home-primary-action" href="index.php?page=booking">Create a booking</a>
        </section>

        <section class="home-stat-grid" aria-label="Booking summary">
            <a class="home-stat-card" href="index.php?page=bookings"><span>Active bookings</span><strong><?php echo (int) ($booking_summary['total'] ?? 0); ?></strong><small>Pending, approved, or collected</small></a>
            <a class="home-stat-card" href="index.php?page=bookings"><span>Awaiting approval</span><strong><?php echo (int) ($booking_summary['pending'] ?? 0); ?></strong><small>Requests needing a decision</small></a>
            <a class="home-stat-card" href="index.php?page=bookings"><span>Approved</span><strong><?php echo (int) ($booking_summary['approved'] ?? 0); ?></strong><small>Equipment reserved</small></a>
            <a class="home-stat-card" href="index.php?page=store_details"><span>Available equipment</span><strong><?php echo (int) ($availability['available_units'] ?? 0); ?></strong><small>Good units not in use</small></a>
        </section>

        <section class="home-dashboard-grid">
            <div class="home-dashboard-panel">
                <div class="home-panel-heading"><div><span class="home-dashboard-kicker">Your schedule</span><h2>Upcoming bookings</h2></div><a href="index.php?page=bookings">View all</a></div>
                <?php if ($upcoming_bookings): ?>
                    <div class="home-booking-list">
                        <?php foreach ($upcoming_bookings as $booking): ?>
                            <a class="home-booking-row" href="index.php?page=booking_confirmation&bookingID=<?php echo (int) $booking['booking_id']; ?>">
                                <span><strong><?php echo htmlspecialchars($booking['event_name']); ?></strong><small>Collection <?php echo htmlspecialchars($booking['collection_date']); ?> · Return <?php echo htmlspecialchars($booking['return_date']); ?></small></span>
                                <span class="home-booking-status"><?php echo htmlspecialchars($booking['status_name'] ?? 'Pending'); ?></span>
                            </a>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <p class="home-empty-state">You have no upcoming bookings.</p>
                <?php endif; ?>
            </div>

            <div class="home-dashboard-panel home-quick-links">
                <div class="home-panel-heading"><div><span class="home-dashboard-kicker">Shortcuts</span><h2>Quick actions</h2></div></div>
                <a href="index.php?page=booking"><span aria-hidden="true">＋</span> Create a booking</a>
                <a href="index.php?page=store_details"><span aria-hidden="true">▦</span> Browse equipment</a>
                <a href="index.php?page=account"><span aria-hidden="true">◎</span> Manage your account</a>
                <a href="index.php?page=faq_contact"><span aria-hidden="true">?</span> FAQ and contact</a>
            </div>
        </section>
    </main>
</div>
