<?php
session_start();
require 'db_conn.php';
$user_level = $_SESSION['user_level'] ?? 0;
$user_id = $_SESSION['user_id'] ?? null;
if (!in_array($user_level, [1, 2, 3], true)) {
    header('Location: index.php');
    exit;
}

$conn->query("CREATE TABLE IF NOT EXISTS booking_item_components (booking_id INT NOT NULL, item_code VARCHAR(50) NOT NULL, component_code VARCHAR(10) NOT NULL, source_location_code VARCHAR(50) DEFAULT NULL, PRIMARY KEY (booking_id, item_code, component_code), UNIQUE KEY (component_code)) ENGINE=InnoDB DEFAULT CHARSET=utf8");

$mapping_columns = $conn->query("SHOW COLUMNS FROM booking_item_components");
$has_source_location = false;
while ($mapping_column = $mapping_columns->fetch_assoc()) {
    if ($mapping_column['Field'] === 'source_location_code') {
        $has_source_location = true;
        break;
    }
}
if (!$has_source_location) {
    $conn->query("ALTER TABLE booking_item_components ADD source_location_code VARCHAR(50) DEFAULT NULL");
}

$primary_column_count = 0;
$primary_columns = $conn->query("SHOW INDEX FROM booking_item_components WHERE Key_name = 'PRIMARY'");
while ($primary_column = $primary_columns->fetch_assoc()) {
    $primary_column_count++;
}
if ($primary_column_count !== 3) {
    $conn->query("ALTER TABLE booking_item_components DROP PRIMARY KEY, ADD PRIMARY KEY (booking_id, item_code, component_code)");
}

function reserve_booking_items($conn, $booking_id) {
    $stmt_items = $conn->prepare("SELECT item_code, quantity_needed FROM booking_items WHERE booking_id = ?");
    $stmt_items->bind_param("i", $booking_id);
    $stmt_items->execute();
    $items = $stmt_items->get_result()->fetch_all(MYSQLI_ASSOC);

    foreach ($items as $item) {
        $quantity_needed = (int) $item['quantity_needed'];
        if ($quantity_needed <= 0) continue;

        $item_code = $item['item_code'];
        $stmt_stock = $conn->prepare("SELECT c.component_code, c.item_location_code FROM components c WHERE c.item_code = ? AND c.quantity = 1 AND c.item_quality <> '0' AND UPPER(TRIM(c.item_location_code)) NOT IN ('IU', 'RETIRED') AND NOT EXISTS (SELECT 1 FROM booking_item_components bic INNER JOIN bookings reserved_booking ON reserved_booking.booking_id = bic.booking_id WHERE bic.component_code = c.component_code AND reserved_booking.booking_status_id IN (2, 3)) ORDER BY c.component_code LIMIT ? FOR UPDATE");
        $stmt_stock->bind_param("si", $item_code, $quantity_needed);
        $stmt_stock->execute();
        $stock_rows = $stmt_stock->get_result()->fetch_all(MYSQLI_ASSOC);
        if (count($stock_rows) < $quantity_needed) return false;

        foreach ($stock_rows as $stock_row) {
            $stmt_mapping = $conn->prepare("INSERT INTO booking_item_components (booking_id, item_code, component_code, source_location_code) VALUES (?, ?, ?, ?)");
            $stmt_mapping->bind_param("isss", $booking_id, $item_code, $stock_row['component_code'], $stock_row['item_location_code']);
            if (!$stmt_mapping->execute()) return false;
        }
    }
    return true;
}

function release_booking_reservations($conn, $booking_id) {
    $stmt = $conn->prepare("DELETE FROM booking_item_components WHERE booking_id = ?");
    $stmt->bind_param("i", $booking_id);
    return $stmt->execute();
}

function move_booking_items_to_use($conn, $booking_id) {
    $stmt_items = $conn->prepare("SELECT item_code, quantity_needed FROM booking_items WHERE booking_id = ?");
    $stmt_items->bind_param("i", $booking_id);
    $stmt_items->execute();
    $items = $stmt_items->get_result()->fetch_all(MYSQLI_ASSOC);

    foreach ($items as $item) {
        $quantity_needed = (int) $item['quantity_needed'];
        if ($quantity_needed <= 0) {
            continue;
        }

        $item_code = $item['item_code'];
        $stmt_stock = $conn->prepare("SELECT c.component_code, c.item_location_code FROM booking_item_components bic INNER JOIN components c ON c.component_code = bic.component_code WHERE bic.booking_id = ? AND bic.item_code = ? AND UPPER(TRIM(c.item_location_code)) NOT IN ('IU', 'RETIRED') AND c.quantity = 1 FOR UPDATE");
        $stmt_stock->bind_param("is", $booking_id, $item_code);
        $stmt_stock->execute();
        $stock_rows = $stmt_stock->get_result()->fetch_all(MYSQLI_ASSOC);
        if (count($stock_rows) !== $quantity_needed) return false;

        foreach ($stock_rows as $stock_row) {
            $component_code = $stock_row['component_code'];
            $source_location_code = $stock_row['item_location_code'];
            $stmt_move = $conn->prepare("UPDATE components SET item_location_code = 'IU' WHERE component_code = ? AND item_code = ? AND quantity = 1 AND UPPER(TRIM(item_location_code)) NOT IN ('IU', 'RETIRED')");
            $stmt_move->bind_param("ss", $component_code, $item_code);
            if (!$stmt_move->execute() || $stmt_move->affected_rows !== 1) {
                return false;
            }
        }
    }

    return true;
}

function return_booking_items_from_use($conn, $booking_id, $return_items = null) {
    $allowed_quality_descriptions = [
        'Good',
        'Damaged',
        'End of life passed',
        'Needs maintenance'
    ];
    $stmt_items = $conn->prepare("SELECT item_code, quantity_needed FROM booking_items WHERE booking_id = ?");
    $stmt_items->bind_param("i", $booking_id);
    $stmt_items->execute();
    $items = $stmt_items->get_result()->fetch_all(MYSQLI_ASSOC);

    foreach ($items as $item) {
        $item_code = $item['item_code'];
        $return_details = $return_items[$item_code] ?? null;

        $stmt_mapping = $conn->prepare("SELECT component_code, source_location_code FROM booking_item_components WHERE booking_id = ? AND item_code = ?");
        $stmt_mapping->bind_param("is", $booking_id, $item_code);
        $stmt_mapping->execute();
        $mapped_components = $stmt_mapping->get_result()->fetch_all(MYSQLI_ASSOC);
        if (count($mapped_components) !== (int) $item['quantity_needed']) {
            return false;
        }

        $component_returns = $return_details['components'] ?? [];
        if ($return_items !== null && count($component_returns) !== count($mapped_components)) {
            return false;
        }

        foreach ($mapped_components as $mapped_component) {
            $return_location = $mapped_component['source_location_code'] ?: 'OOS1';
            if ($return_items === null) {
                $stmt_return = $conn->prepare("UPDATE components SET item_location_code = ? WHERE component_code = ? AND item_code = ? AND item_location_code = 'IU'");
                $stmt_return->bind_param("sss", $return_location, $mapped_component['component_code'], $item_code);
            } else {
                $component_return = $component_returns[$mapped_component['component_code']] ?? null;
                $quality_description = trim($component_return['quality_desc'] ?? '');
                $return_note = trim($component_return['return_note'] ?? '');
                if (!in_array($quality_description, $allowed_quality_descriptions, true) || $return_note === '') {
                    return false;
                }
                $item_quality = strtolower($quality_description) === 'good' ? '1' : '0';
                $stmt_return = $conn->prepare("UPDATE components SET item_location_code = ?, item_quality = ?, quality_desc = ?, return_note = ? WHERE component_code = ? AND item_code = ? AND item_location_code = 'IU'");
                $stmt_return->bind_param("ssssss", $return_location, $item_quality, $quality_description, $return_note, $mapped_component['component_code'], $item_code);
            }
            if (!$stmt_return->execute() || $stmt_return->affected_rows !== 1) {
                return false;
            }
        }

        $stmt_release = $conn->prepare("DELETE FROM booking_item_components WHERE booking_id = ? AND item_code = ?");
        $stmt_release->bind_param("is", $booking_id, $item_code);
        if (!$stmt_release->execute()) return false;

        if ($return_items !== null) {
            $quantity_returned = (int) $item['quantity_needed'];
            $return_notes = [];
            foreach ($component_returns as $component_return) {
                $return_notes[] = trim($component_return['return_note'] ?? '');
            }
            $return_note = implode(' | ', array_filter($return_notes));
            $stmt_booking_item = $conn->prepare("UPDATE booking_items SET quantity_returned = ?, damage_notes_on_return = ? WHERE booking_id = ? AND item_code = ?");
            $stmt_booking_item->bind_param("isis", $quantity_returned, $return_note, $booking_id, $item_code);
            if (!$stmt_booking_item->execute()) {
                return false;
            }
        }
    }

    return true;
}

$booking_id = intval($_POST['booking_id'] ?? 0);
$status_id = intval($_POST['status_id'] ?? 0);

if ($booking_id && $status_id) {
    $stmt = $conn->prepare("SELECT booking_status_id FROM bookings WHERE booking_id = ?");
    $stmt->bind_param("i", $booking_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $booking = $result->fetch_assoc();

    if ($booking) {
        $current_status = intval($booking['booking_status_id']);
        $allowed = false;

        if (in_array($user_level, [1, 2], true)) {
            $allowed = true;
        } elseif ($user_level === 3 && $current_status === 2) {
            $allowed = in_array($status_id, [3, 4, 5], true);
        }

        if ($allowed) {
            $conn->begin_transaction();
            $inventory_updated = true;

            if ($current_status === 3 && $status_id !== 3) {
                $inventory_updated = return_booking_items_from_use($conn, $booking_id);
                if ($inventory_updated && $status_id === 2) {
                    $inventory_updated = reserve_booking_items($conn, $booking_id);
                }
            } elseif ($status_id === 2 && $current_status !== 2) {
                $inventory_updated = reserve_booking_items($conn, $booking_id);
            } elseif ($current_status !== 3 && $status_id === 3) {
                if ($current_status !== 2) {
                    $inventory_updated = reserve_booking_items($conn, $booking_id);
                }
                if ($inventory_updated) {
                    $inventory_updated = move_booking_items_to_use($conn, $booking_id);
                }
            } elseif ($status_id === 5 && $current_status === 2) {
                $inventory_updated = release_booking_reservations($conn, $booking_id);
            } elseif ($status_id === 1 && $current_status === 2) {
                $inventory_updated = release_booking_reservations($conn, $booking_id);
            } elseif ($status_id === 4) {
                $inventory_updated = return_booking_items_from_use($conn, $booking_id, $_POST['return_items'] ?? []);
            }

            if (!$inventory_updated) {
                $conn->rollback();
                $_SESSION['status_update_error'] = $status_id === 4
                    ? 'Every returned item must have a quality description and return note, and the booking must have collected items in use.'
                    : 'The booking could not be updated because one or more requested items do not have enough good, unreserved physical units.';
                header('Location: index.php?page=bookings');
                exit;
            }

            if ($status_id === 2) {
                $approval_datetime = date('Y-m-d H:i:s');
                $stmt = $conn->prepare("UPDATE bookings SET booking_status_id = ?, approved_by_user_id = ?, approval_datetime = ? WHERE booking_id = ?");
                $stmt->bind_param("iisi", $status_id, $user_id, $approval_datetime, $booking_id);
            } else {
                $stmt = $conn->prepare("UPDATE bookings SET booking_status_id = ?, approved_by_user_id = NULL, approval_datetime = NULL WHERE booking_id = ?");
                $stmt->bind_param("ii", $status_id, $booking_id);
            }
            if ($stmt->execute()) {
                $conn->commit();
            } else {
                $conn->rollback();
            }
        }
    }
}

header('Location: index.php?page=bookings');