<?php
session_start();
require_once 'db_conn.php';

if ($_SERVER["REQUEST_METHOD"] == "POST") {

    $eventName = trim(htmlspecialchars($_POST['eventName']));
    $eventStartDate = trim(htmlspecialchars($_POST['eventStartDate']));
    $eventFinishDate = trim(htmlspecialchars($_POST['eventFinishDate']));
    $collectionDate = trim(htmlspecialchars($_POST['collectionDate']));
    $returnDate = trim(htmlspecialchars($_POST['returnDate']));

    $userId = $_SESSION['user_id'] ?? null;
    
    if (empty($eventName) || empty($eventStartDate) || empty($eventFinishDate) || empty($collectionDate) || empty($returnDate) || empty($userId)) {
        die("Missing required information.");
    }

    $sql = "INSERT INTO `bookings` (`user_id`, `event_name`, `event_start`, `event_end`, `collection_date`, `return_date`) VALUES (?, ?, ?, ?, ?, ?)";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("isssss", $userId, $eventName, $eventStartDate, $eventFinishDate, $collectionDate, $returnDate);

    if ($stmt->execute()) {
        header("Location: index.php?page=booking");
        echo "<script type=text/javascript src='static/js/booking_funcs.js'>get_items();</script>";
        exit();
    } else {
        error_log("Database error during booking: " . $stmt->error);
        echo "Error executing statement: " . $stmt->error;
    }

    $stmt->close();

} else {
    header("Location: index.php?page=booking");
    exit();
}
?>