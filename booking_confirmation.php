<?php
$booking_id = filter_input(INPUT_GET, 'bookingID', FILTER_VALIDATE_INT);
$user_id = (int) ($_SESSION['user_id'] ?? 0);
if (!$booking_id || !$user_id) {
    header('Location: index.php?page=bookings');
    exit();
}

$stmt_booking = $conn->prepare("SELECT b.*, s.status_name, u.first_name, u.last_name FROM bookings b LEFT JOIN booking_statuses s ON s.status_id = b.booking_status_id LEFT JOIN users u ON u.user_id = b.user_id WHERE b.booking_id = ? AND (b.user_id = ? OR ? IN (1, 2))");
$user_level = (int) ($_SESSION['user_level'] ?? 0);
$stmt_booking->bind_param('iii', $booking_id, $user_id, $user_level);
$stmt_booking->execute();
$booking = $stmt_booking->get_result()->fetch_assoc();
if (!$booking) {
    http_response_code(404);
    exit('Booking not found.');
}

$stmt_items = $conn->prepare("SELECT bi.item_code, bi.quantity_needed, i.item_name, GROUP_CONCAT(bic.component_code ORDER BY bic.component_code SEPARATOR ', ') AS component_codes FROM booking_items bi LEFT JOIN items i ON i.item_code = bi.item_code LEFT JOIN booking_item_components bic ON bic.booking_id = bi.booking_id AND bic.item_code = bi.item_code WHERE bi.booking_id = ? GROUP BY bi.item_code, bi.quantity_needed, i.item_name ORDER BY i.item_name");
$stmt_items->bind_param('i', $booking_id);
$stmt_items->execute();
$items = $stmt_items->get_result()->fetch_all(MYSQLI_ASSOC);
$status_explanations = [
    'Pending' => 'Your request has been submitted and is waiting for approval.',
    'Approved' => 'Your equipment has been reserved against this booking.',
    'Collected' => 'The assigned physical units are currently out with your group.',
    'Returned' => 'The equipment has been returned and checked back into stock.',
    'Cancelled' => 'This booking is no longer active.'
];
$status_explanation = $status_explanations[$booking['status_name'] ?? 'Pending'] ?? 'Your booking is being processed.';
?>

<div class="main">
    <div class="booking-confirmation">
        <div class="booking-confirmation-header">
            <span class="booking-confirmation-kicker">Booking submitted</span>
            <h1><?php echo htmlspecialchars($booking['event_name']); ?></h1>
            <p>Booking #<?php echo (int) $booking['booking_id']; ?> is currently <strong><?php echo htmlspecialchars($booking['status_name'] ?? 'Pending'); ?></strong>.</p>
            <p class="booking-confirmation-explanation"><?php echo htmlspecialchars($status_explanation); ?></p>
        </div>

        <div class="booking-confirmation-status">
            <div><span>Event dates</span><strong><?php echo htmlspecialchars($booking['event_start']); ?> to <?php echo htmlspecialchars($booking['event_end']); ?></strong></div>
            <div><span>Collection</span><strong><?php echo htmlspecialchars($booking['collection_date']); ?></strong></div>
            <div><span>Return</span><strong><?php echo htmlspecialchars($booking['return_date']); ?></strong></div>
            <div><span>Section / end use</span><strong><?php echo htmlspecialchars($booking['group_name'] ?? '-'); ?></strong></div>
        </div>

        <section class="booking-confirmation-section">
            <h2>Equipment requested</h2>
            <?php if ($items): ?>
                <div class="booking-confirmation-items">
                    <?php foreach ($items as $item): ?>
                        <article>
                            <div><strong><?php echo htmlspecialchars($item['item_name'] ?? $item['item_code']); ?></strong><small><?php echo htmlspecialchars($item['item_code']); ?></small></div>
                            <span><?php echo (int) $item['quantity_needed']; ?> unit<?php echo (int) $item['quantity_needed'] === 1 ? '' : 's'; ?></span>
                            <?php if ($item['component_codes']): ?><small class="booking-component-codes">Assigned units: <?php echo htmlspecialchars($item['component_codes']); ?></small><?php endif; ?>
                        </article>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <p>No equipment has been added to this booking yet.</p>
            <?php endif; ?>
        </section>

        <div class="booking-confirmation-actions">
            <a class="edit-button" href="index.php?page=bookings">View all bookings</a>
            <a class="booking-secondary-link" href="index.php?page=booking">Create another booking</a>
        </div>
    </div>
</div>
