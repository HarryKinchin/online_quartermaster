<?php
require_once 'db_conn.php';

$bookingID = $_POST['bookingID'] ?? null;
$fieldName = $_POST['fieldName'] ?? null;
$fieldValue = $_POST['fieldValue'] ?? null;

if (!$bookingID || !$fieldName) {
    die("Incomplete data");
}

// Check if the field is an item quantity or a booking detail
if (strpos($fieldName, 'booked_item_') !== false) {
    // IT IS AN ITEM
    $itemCode = str_replace('booked_item_', '', $fieldName);
    
    $stmt = $conn->prepare("INSERT INTO booking_items (booking_id, item_code, quantity_needed) 
                            VALUES (?, ?, ?) 
                            ON DUPLICATE KEY UPDATE quantity_needed = ?");
    $stmt->bind_param("isii", $bookingID, $itemCode, $fieldValue, $fieldValue);
    $stmt->execute();
    echo "Item Updated";

} else {
    // IT IS A BOOKING FIELD (e.g., eventName, eventStartDate)
    // Map form names to database column names
    $map = [
    'eventName' => 'event_name',
    'eventStartDate' => 'event_start',
    'eventFinishDate' => 'event_end',
    'collectionDate' => 'collection_date',
    'returnDate' => 'return_date',
    'group' => 'group_name'
    ];

    if (array_key_exists($fieldName, $map)) {
        $column = $map[$fieldName];
        $sql = "UPDATE bookings SET $column = ? WHERE booking_id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("si", $fieldValue, $bookingID);
        $stmt->execute();
        echo "Group Updated";
    }
}
?>