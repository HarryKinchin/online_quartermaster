<?php
require_once 'db_conn.php';

$bookingID = $_POST['bookingID'] ?? null;
$fieldName = $_POST['fieldName'] ?? null;
$fieldValue = $_POST['fieldValue'] ?? null;

if (!$bookingID || !$fieldName) {
    die("<div class='main'>Incomplete data</div>");
}

$stmt_booking_status = $conn->prepare("SELECT booking_status_id FROM bookings WHERE booking_id = ?");
$stmt_booking_status->bind_param("i", $bookingID);
$stmt_booking_status->execute();
$booking_status = (int) ($stmt_booking_status->get_result()->fetch_assoc()['booking_status_id'] ?? 0);
if (in_array($booking_status, [2, 3, 4, 5], true)) {
    http_response_code(400);
    die('Approved, collected, returned, or cancelled bookings cannot be edited. Return the booking to pending before changing it.');
}

$updated = false;

if (strpos($fieldName, 'booked_item_') !== false) {
    $itemCode = str_replace('booked_item_', '', $fieldName);
    $quantity = filter_var($fieldValue, FILTER_VALIDATE_INT);
    if ($quantity === false || $quantity < 0) {
        http_response_code(400);
        die('Quantity must be a whole number of zero or more.');
    }

    $stmt_available = $conn->prepare("SELECT COUNT(*) AS available_units FROM components c WHERE c.item_code = ? AND c.quantity = 1 AND c.item_quality <> '0' AND UPPER(TRIM(c.item_location_code)) NOT IN ('IU', 'RETIRED') AND NOT EXISTS (SELECT 1 FROM booking_item_components bic INNER JOIN bookings reserved_booking ON reserved_booking.booking_id = bic.booking_id WHERE bic.component_code = c.component_code AND reserved_booking.booking_status_id IN (2, 3))");
    $stmt_available->bind_param("s", $itemCode);
    $stmt_available->execute();
    $available_units = (int) ($stmt_available->get_result()->fetch_assoc()['available_units'] ?? 0);

    $stmt_current = $conn->prepare("SELECT quantity_needed FROM booking_items WHERE booking_id = ? AND item_code = ?");
    $stmt_current->bind_param("is", $bookingID, $itemCode);
    $stmt_current->execute();
    $current_row = $stmt_current->get_result()->fetch_assoc();
    $current_quantity = (int) ($current_row['quantity_needed'] ?? 0);
    $maximum_quantity = $available_units + $current_quantity;
    if ($quantity > $maximum_quantity) {
        http_response_code(400);
        die("Only {$maximum_quantity} unit(s) are available for this booking.");
    }

    if ($quantity === 0) {
        $stmt = $conn->prepare("DELETE FROM booking_items WHERE booking_id = ? AND item_code = ?");
        $stmt->bind_param("is", $bookingID, $itemCode);
    } else {
        $stmt = $conn->prepare("INSERT INTO booking_items (booking_id, item_code, quantity_needed) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE quantity_needed = VALUES(quantity_needed)");
        $stmt->bind_param("isi", $bookingID, $itemCode, $quantity);
    }
    if (!$stmt->execute()) {
        http_response_code(500);
        die('The booking item could not be updated.');
    }
    $updated = true;
    echo "Item Updated";

} else {
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

        if ($fieldName === 'collectionDate') {
            $check = $conn->prepare("SELECT event_start FROM bookings WHERE booking_id = ?");
            $check->bind_param("i", $bookingID);
            $check->execute();
            $row = $check->get_result()->fetch_assoc();
            if ($row && strtotime($fieldValue) > strtotime($row['event_start'])) {
                http_response_code(400);
                die('Collection date must be before or on the event start date.');
            }
        }

        if ($fieldName === 'returnDate') {
            $check = $conn->prepare("SELECT event_end FROM bookings WHERE booking_id = ?");
            $check->bind_param("i", $bookingID);
            $check->execute();
            $row = $check->get_result()->fetch_assoc();
            if ($row && strtotime($fieldValue) < strtotime($row['event_end'])) {
                http_response_code(400);
                die('Event end date must be before or on the return date.');
            }
        }

        if ($fieldName === 'eventStartDate') {
            $check = $conn->prepare("SELECT collection_date, event_end FROM bookings WHERE booking_id = ?");
            $check->bind_param("i", $bookingID);
            $check->execute();
            $row = $check->get_result()->fetch_assoc();
            if ($row) {
                if (!empty($row['collection_date']) && strtotime($row['collection_date']) > strtotime($fieldValue)) {
                    http_response_code(400);
                    die('Collection date must be before or on the event start date.');
                }
                if (!empty($row['event_end']) && strtotime($fieldValue) > strtotime($row['event_end'])) {
                    http_response_code(400);
                    die('Event start date must be before or on the event end date.');
                }
            }
        }

        if ($fieldName === 'eventFinishDate') {
            $check = $conn->prepare("SELECT return_date, event_start FROM bookings WHERE booking_id = ?");
            $check->bind_param("i", $bookingID);
            $check->execute();
            $row = $check->get_result()->fetch_assoc();
            if ($row) {
                if (!empty($row['return_date']) && strtotime($row['return_date']) < strtotime($fieldValue)) {
                    http_response_code(400);
                    die('Event end date must be before or on the return date.');
                }
                if (!empty($row['event_start']) && strtotime($fieldValue) < strtotime($row['event_start'])) {
                    http_response_code(400);
                    die('Event start date must be before or on the event end date.');
                }
            }
        }

        $sql = "UPDATE bookings SET $column = ? WHERE booking_id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("si", $fieldValue, $bookingID);
        $stmt->execute();
        $updated = true;
        echo "Group Updated";
    }
}

if ($updated) {
    $stmt = $conn->prepare("UPDATE bookings SET booking_status_id = 1, approved_by_user_id = NULL, approval_datetime = NULL WHERE booking_id = ? AND booking_status_id != 1");
    $stmt->bind_param("i", $bookingID);
    $stmt->execute();
}
?>