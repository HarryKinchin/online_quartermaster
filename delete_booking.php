<?php
session_start();
require_once 'db_conn.php';

$booking_id = filter_input(INPUT_POST, 'booking_id', FILTER_VALIDATE_INT);
$user_id = (int) ($_SESSION['user_id'] ?? 0);
$user_level = (int) ($_SESSION['user_level'] ?? 0);

if (!$booking_id || !$user_id) {
    header('Location: index.php?page=bookings');
    exit();
}

$stmt_booking = $conn->prepare('SELECT user_id, booking_status_id FROM bookings WHERE booking_id = ?');
$stmt_booking->bind_param('i', $booking_id);
$stmt_booking->execute();
$booking = $stmt_booking->get_result()->fetch_assoc();

if ($booking && in_array((int) $booking['booking_status_id'], [3, 4, 5], true)) {
    http_response_code(400);
    exit('Collected, returned, or cancelled bookings cannot be deleted.');
}

$can_delete = $booking && ($user_level === 1 || $user_level === 2 || (int) $booking['user_id'] === $user_id);
if (!$can_delete) {
    http_response_code(403);
    exit('You do not have permission to delete this booking.');
}

$conn->begin_transaction();

$stmt_items = $conn->prepare('DELETE FROM booking_items WHERE booking_id = ?');
$stmt_items->bind_param('i', $booking_id);
$items_deleted = $stmt_items->execute();

$stmt_mappings = $conn->prepare('DELETE FROM booking_item_components WHERE booking_id = ?');
$stmt_mappings->bind_param('i', $booking_id);
$mappings_deleted = $stmt_mappings->execute();

$stmt_booking_delete = $conn->prepare('DELETE FROM bookings WHERE booking_id = ?');
$stmt_booking_delete->bind_param('i', $booking_id);
$booking_deleted = $stmt_booking_delete->execute();

if ($items_deleted && $mappings_deleted && $booking_deleted) {
    $conn->commit();
    header('Location: index.php?page=bookings');
    exit();
}

$conn->rollback();
http_response_code(500);
exit('The booking could not be deleted.');