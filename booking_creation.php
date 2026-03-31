<?php
session_start();
require_once 'db_conn.php';

if ($_SERVER["REQUEST_METHOD"] == "POST") {

    $eventName = trim(htmlspecialchars($_POST['eventName']));
    $eventStartDate = trim(htmlspecialchars($_POST['eventStartDate']));
    $eventFinishDate = trim(htmlspecialchars($_POST['eventFinishDate']));
    $collectionDate = trim(htmlspecialchars($_POST['collectionDate']));
    $returnDate = trim(htmlspecialchars($_POST['returnDate']));
    $groupName = trim(htmlspecialchars($_POST['group']));

    $userId = $_SESSION['user_id'] ?? null;
    
    if (empty($eventName) || empty($eventStartDate) || empty($eventFinishDate) || empty($collectionDate) || empty($returnDate) || empty($userId)) {
        die("Missing required information.");
    }

    $sql = "INSERT INTO `bookings` (`user_id`, `event_name`, `event_start`, `event_end`, `collection_date`, `return_date`, `group_name`) VALUES (?, ?, ?, ?, ?, ?, ?)";
    $stmt_new_booking = $conn->prepare($sql);
    $stmt_new_booking->bind_param("issssss", $userId, $eventName, $eventStartDate, $eventFinishDate, $collectionDate, $returnDate, $groupName);

    if ($stmt_new_booking->execute()) {
        $stmt_fetch_id = $conn->prepare("SELECT booking_id FROM bookings WHERE (`user_id`, `event_name`, `event_start`, `event_end`, `collection_date`, `return_date`) = (?, ?, ?, ?, ?, ?)");
        $stmt_fetch_id->bind_param("isssss", $userId, $eventName, $eventStartDate, $eventFinishDate, $collectionDate, $returnDate);
        $stmt_fetch_id->execute();
        $bookingID = $stmt_fetch_id->get_result();
    
        header("Location: index.php?page=bookings");
        exit();
    } else {
        error_log("Database error during booking: " . $stmt_new_booking->error);
        echo "Error executing statement: " . $stmt_new_booking->error;
    }

    $stmt_new_booking->close();

} else {
    header("Location: index.php?page=booking");
    exit();
}
?>