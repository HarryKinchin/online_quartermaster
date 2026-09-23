<?php
$stmt_equipment = $conn->prepare("SELECT item_code, category_code, item_name, item_desc, image_1 FROM items");
$stmt_equipment->execute();
$equipment_result = $stmt_equipment->get_result();

$stmt_category = $conn->prepare("SELECT category_code, category_name FROM categories");
$stmt_category->execute();
$category_result = $stmt_category->get_result();

$grouped_equipment = [];
// Loop through all equipment items
if ($equipment_result->num_rows > 0) {
    while ($item = $equipment_result->fetch_assoc()) {
        $category_code = strtolower($item['category_code']);
        // Group the item into the array indexed by its category_code
        if (!isset($grouped_equipment[$category_code])) {
            $grouped_equipment[$category_code] = [];
        }
        $grouped_equipment[$category_code][] = $item;
    }
}
$item_category_map = [];
foreach ($grouped_equipment as $category_code => $items) {
    foreach ($items as $item) {
        $item_category_map[$item['item_code']] = $category_code;
    }
}

$stmt_components = $conn->prepare("SELECT item_code, item_quality, item_location_code FROM components");
$stmt_components->execute();
$component_result = $stmt_components->get_result();
$inventory_summary = [];
if ($component_result->num_rows > 0) {
    while ($component = $component_result->fetch_assoc()) {
        $item_code = $component['item_code'];
        $location_code = $component['item_location_code'];

        // Initialize the array for the item_code if it doesn't exist
        if (!isset($inventory_summary[$item_code])) {
            $inventory_summary[$item_code] = [
                'total' => 0,
                'available' => 0,
                'reserved' => 0
            ];
        }
        $inventory_summary[$item_code]['total']++;
        if (!in_array(strtoupper(trim($location_code)), ['IU', 'RETIRED'], true) && $component['item_quality'] !== '0') {
            $inventory_summary[$item_code]['available']++;
        }
    }
}

// Approved bookings reserve physical units before collection.
$reserved_summary = [];
$table_check = $conn->query("SHOW TABLES LIKE 'booking_item_components'");
if ($table_check && $table_check->num_rows > 0) {
    $stmt_reserved = $conn->prepare("SELECT bic.item_code, COUNT(*) AS reserved_units FROM booking_item_components bic INNER JOIN bookings b ON b.booking_id = bic.booking_id WHERE b.booking_status_id = 2 GROUP BY bic.item_code");
    $stmt_reserved->execute();
    $reserved_result = $stmt_reserved->get_result();
    while ($reserved = $reserved_result->fetch_assoc()) {
        $reserved_summary[$reserved['item_code']] = (int) $reserved['reserved_units'];
    }
}
foreach ($reserved_summary as $item_code => $reserved_units) {
    if (isset($inventory_summary[$item_code])) {
        $inventory_summary[$item_code]['reserved'] = $reserved_units;
        $inventory_summary[$item_code]['available'] = max(0, $inventory_summary[$item_code]['available'] - $reserved_units);
    }
}

$user_group_name = '';
if (!empty($_SESSION['user_id'])) {
    $stmt_user_group = $conn->prepare("SELECT group_name FROM users WHERE user_id = ?");
    $stmt_user_group->bind_param("i", $_SESSION['user_id']);
    $stmt_user_group->execute();
    $user_group = $stmt_user_group->get_result()->fetch_assoc();
    $user_group_name = $user_group['group_name'] ?? '';
}

$stmt_group = $conn->prepare("SELECT group_name, group_type FROM sections ORDER BY group_weight ASC");
$stmt_group->execute();
$group_result = $stmt_group->get_result();

// Check if we are editing an existing booking
$booking_id = $_GET['bookingID'] ?? null;
$existing_booking = null;

if ($booking_id) {
    $stmt_load = $conn->prepare("SELECT * FROM bookings WHERE booking_id = ?");
    $stmt_load->bind_param("i", $booking_id);
    $stmt_load->execute();
    $existing_booking = $stmt_load->get_result()->fetch_assoc();

    $current_user_level = (int) ($_SESSION['user_level'] ?? 0);
    if (!in_array($current_user_level, [1, 2], true)) {
        $stmt_user_section = $conn->prepare("SELECT group_name FROM users WHERE user_id = ?");
        $stmt_user_section->bind_param("i", $_SESSION['user_id']);
        $stmt_user_section->execute();
        $current_user_section = $stmt_user_section->get_result()->fetch_assoc();

        if (!$existing_booking || empty($current_user_section['group_name']) || $current_user_section['group_name'] !== $existing_booking['group_name']) {
            echo '<div class="form-container"><h1 class="form-title">Not authorised</h1><p>You can only edit bookings for your own section.</p></div>';
            return;
        }
    }
}
$selected_group_name = $existing_booking['group_name'] ?? $user_group_name;

$saved_items = [];
if ($booking_id) {
    // Fetch items already added to this booking
    $stmt_items = $conn->prepare("SELECT item_code, quantity_needed FROM booking_items WHERE booking_id = ?");
    $stmt_items->bind_param("i", $booking_id);
    $stmt_items->execute();
    $saved_items_result = $stmt_items->get_result();
    
    while ($row = $saved_items_result->fetch_assoc()) {
        // Create a key-value pair: [ 'ITEM001' => 5 ]
        $saved_items[$row['item_code']] = $row['quantity_needed'];
    }
};

// Logic to determine visibility
$info_display = "block";
$items_display = $booking_id ? "block" : "none";
$form_action = $booking_id ? "#" : "booking_creation.php"; // Change action if editing
$booked_category_counts = [];
if ($booking_id) {
    foreach ($saved_items as $item_code => $qty) {
        if ($qty > 0 && isset($item_category_map[$item_code])) {
            $cat = $item_category_map[$item_code];
            $booked_category_counts[$cat] = ($booked_category_counts[$cat] ?? 0) + 1;
        }
    }
}

$booked_category_totals = [];
if ($booking_id) {
    foreach ($saved_items as $item_code => $qty) {
        if ($qty > 0 && isset($item_category_map[$item_code])) {
            $cat = $item_category_map[$item_code];
            $booked_category_totals[$cat] = ($booked_category_totals[$cat] ?? 0) + $qty;
        }
    }
}
?>
<div class="main">
    <input type="hidden" id="booking_id" value="<?php echo $booking_id; ?>">
    <div class="form-container">
            <h1 class="form-title" id="info_title">Booking Info</h1>
            <form id="details-form" class="form-layout" action="<?php echo $form_action; ?>" method="post" onsubmit="return validateBookingDates()">
            <div id="info_form">
                <div id="dateValidationMessage" class="validation-error hidden"></div>

                <!-- Name of Event -->
                <div>
                    <label for="eventName-form" class="form-label">Name of Event</label>
                    <input type="text" id="eventName-form" name="eventName" class="form-input" value="<?php echo htmlspecialchars($existing_booking['event_name'] ?? ''); ?>" required>
                </div><br>

                <!-- Event Dates -->
                <div class="grid-layout-form">
                    <div>
                        <label for="eventStartDate" class="form-label">Event Start Date</label>
                        <input type="date" id="eventStartDate" name="eventStartDate" class="form-input" min="<?php echo date('Y-m-d'); ?>" value="<?php echo htmlspecialchars($existing_booking['event_start'] ?? ''); ?>" required>
                    </div>
                    <div>
                        <label for="eventFinishDate" class="form-label">Event Finish Date</label>
                        <input type="date" id="eventFinishDate" name="eventFinishDate" class="form-input" min="<?php echo date('Y-m-d'); ?>" value="<?php echo htmlspecialchars($existing_booking['event_end'] ?? ''); ?>" required>
                    </div>
                </div>

                <!-- Equipment Dates -->
                <div class="grid-layout-form">
                    <div>
                        <label for="collectionDate" class="form-label">Collection Date</label>
                        <input type="date" id="collectionDate" name="collectionDate" class="form-input" min="<?php echo date('Y-m-d'); ?>" value="<?php echo htmlspecialchars($existing_booking['collection_date'] ?? ''); ?>" required>
                    </div>
                    <div>
                        <label for="returnDate" class="form-label">Return Date</label>
                        <input type="date" id="returnDate" name="returnDate" class="form-input" min="<?php echo date('Y-m-d'); ?>" value="<?php echo htmlspecialchars($existing_booking['return_date'] ?? ''); ?>" required>
                    </div>
                </div>

                <!-- Booker Name and Group -->
                <div class="grid-layout-form">
                    <div>
                        <label for="bookerName" class="form-label">Booking made by</label>
                        <input type="text" id="bookerName" name="bookerName" class="form-input" value="<?php echo htmlspecialchars($_SESSION["username"]); ?>" required>
                    </div>
                    <div>
                        <label for="group" class="form-label">For</label>
                        <select id="group" name="group" class="form-input" required>
                            <option value="" disabled <?php echo $selected_group_name === '' ? 'selected' : ''; ?>>Select a section / end use</option>
                            <?php
                                while ($row = $group_result->fetch_assoc()) {
                                    $groupName = htmlspecialchars($row['group_name']);
                                    $groupType = htmlspecialchars($row['group_type']);
                                    if($groupName == $groupType){
                                        $displayValue = $groupName;
                                    } else {
                                        $displayValue = $groupName . ' ' . $groupType;
                                    }
                                    
                                    $selected = ($selected_group_name === $row['group_name']) ? 'selected' : '';
                                    
                                    echo "<option value=\"$groupName\" $selected>$displayValue</option>";
                                }
                            ?>
                        </select>
                    </div>
                </div><br>

                <div class="grid-layout-form">
                    <button class="submit-button" type="submit" onclick="get_items()">
                        <?php echo $booking_id ? 'Save Booking' : 'Create Booking'; ?>
                    </button>
                </div>
            </div>
            </form>
            <?php if ($booking_id): ?>
                <form action="delete_booking.php" method="post" onsubmit="return confirm('Are you sure you want to delete this booking? This cannot be undone.');">
                    <input type="hidden" name="booking_id" value="<?php echo (int) $booking_id; ?>">
                    <button class="delete-button" type="submit">Delete Booking</button>
                </form>
            <?php endif; ?>
            <h1 class="form-title<?php echo $items_display === 'none' ? ' hidden' : ''; ?>" id="items_title">Booking Items</h1>
            <form id="items-form" class="form-layout<?php echo $items_display === 'none' ? ' hidden' : ''; ?>" action="#" method="post">
                <div id="item_form">
                    <div>
                        <?php
                        if ($category_result->num_rows > 0) {
                            while ($category = $category_result->fetch_assoc()) {
                                $category_name = htmlspecialchars($category['category_name']);
                                $category_code = $category['category_code'];
                        ?>
                                <div class="accordian-item">
                                    <?php
                                    $booked_count = $booked_category_counts[$category_code] ?? 0;
                                    $booked_total = $booked_category_totals[$category_code] ?? 0;
                                    $badge = ($booking_id && ($booked_count > 0 || $booked_total > 0))
                                        ? " ({$booked_count} unique, {$booked_total} total)"
                                        : '';
                                    ?>
                                    <button class="accordion-header" type="button">
                                        <?php echo $category_name . $badge; ?>
                                    </button>
                                    <div class="accordion-content">
                                        <?php
                                        // Check if there are items for the current category code
                                        if (isset($grouped_equipment[$category_code])) {
                                            // Loop through the items for this specific category
                                            foreach ($grouped_equipment[$category_code] as $item) {
                                                $item_name = htmlspecialchars($item['item_name']);
                                                $item_desc = htmlspecialchars($item['item_desc']);
                                                $image_1_value = $item['image_1'];
                                                if (!empty($image_1_value)) {
                                                    $image_path = './static/images/item_images/' . rawurlencode($image_1_value);
                                                } else {
                                                    $image_path = './static/images/not-found.svg';
                                                }
                                                $item_code = $item['item_code'];

                                                // Get the total and available counts for the current item_code
                                                $total_count = $inventory_summary[$item_code]['total'] ?? 0;
                                                $available_count = $inventory_summary[$item_code]['available'] ?? 0;
                                                $reserved_count = $inventory_summary[$item_code]['reserved'] ?? 0;
                                        ?>
                                                <div class="grid-layout booking-item-card">
                                                    <div class="item-image">
                                                        <img src="<?php echo htmlspecialchars($image_path); ?>" alt="<?php echo $item_name; ?>" class="item-image-img">
                                                    </div>
                                                    
                                                    <div class="item-name">
                                                        <?php echo $item_name; ?>
                                                        <span class="info-button" onclick="toggleDescription('item-desc-<?php echo $item_code; ?>')">i</span>
                                                    </div>
                                                    
                                                    <div class="item-desc hidden-desc" id="item-desc-<?php echo $item_code; ?>">
                                                        <?php
                                                        if ($item_desc != '') { 
                                                            echo $item_desc;
                                                        } else {
                                                            echo 'No description found';
                                                        }
                                                        ?> 
                                                    </div>
                                                    
                                                    <div class="item-total">Total: <b><?php echo $total_count; ?></b></div> 
                                                    <div class="item-available">Available: <b><?php echo $available_count; ?></b></div>
                                                    <div class="item-reserved">Reserved: <b><?php echo $reserved_count; ?></b></div>
                                                    <div class="item-booked">
                                                        This:
                                                        <?php
                                                        // Determine the value to display (default to empty/0 if not found)
                                                        $current_qty = isset($saved_items[$item_code]) ? $saved_items[$item_code] : '';

                                                        // If there are no items available, the booking amount selector will be disabled
                                                        if ($available_count == 0 && $current_qty <= 0) {
                                                            echo '<input type="number" class="booked_item" name="booked_item_', $item_code, '" disabled>';
                                                        } else {
                                                            echo '<input type="number" 
                                                                        name="booked_item_', $item_code, '" 
                                                                        class="booked_item"
                                                                        min="0" 
                                                                        max="', ($available_count + (int)$current_qty), '" 
                                                                        value="', $current_qty, '">';
                                                        }
                                                        ?>
                                                    </div>
                                                </div>
                                        <?php
                                            } // End of item loop
                                        } else {
                                            echo '<p>No items found in this category.</p>';
                                        }
                                        ?>
                                        </div>
                                </div>
                        <?php
                            } // End of category loop
                        } else {
                            echo '<p>No item categories available.</p>';
                        }
                        $category_result->free();
                        ?>
                        </div>
                </div>
            </form>

    </div>
    <script src="./static/js/faq_funcs.js"></script>
    <script src="./static/js/booking_funcs.js"></script>
    <script src="./static/js/formchanges.js"></script>
    <script>
        function validateBookingDates() {
            const eventStart = document.getElementById('eventStartDate').value;
            const eventEnd = document.getElementById('eventFinishDate').value;
            const collectionDate = document.getElementById('collectionDate').value;
            const returnDate = document.getElementById('returnDate').value;
            const validationMessage = document.getElementById('dateValidationMessage');

            validationMessage.style.display = 'none';
            validationMessage.textContent = '';

            if (!eventStart || !eventEnd || !collectionDate || !returnDate) {
                return true;
            }

            const start = new Date(eventStart);
            const end = new Date(eventEnd);
            const collect = new Date(collectionDate);
            const ret = new Date(returnDate);

            if (start > end) {
                validationMessage.textContent = 'Event start date must be before or on the event end date.';
                validationMessage.style.display = 'block';
                return false;
            }

            if (collect > start) {
                validationMessage.textContent = 'Collection date must be before or on the event start date.';
                validationMessage.style.display = 'block';
                return false;
            }

            if (ret < end) {
                validationMessage.textContent = 'Event end date must be before or on the return date.';
                validationMessage.style.display = 'block';
                return false;
            }

            return true;
        }
    </script>
</div>