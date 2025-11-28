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

$stmt_components = $conn->prepare("SELECT item_code, quantity, item_quality, item_location_code FROM components");
$stmt_components->execute();
$component_result = $stmt_components->get_result();
$inventory_summary = [];
if ($component_result->num_rows > 0) {
    while ($component = $component_result->fetch_assoc()) {
        $item_code = $component['item_code'];
        $quantity = $component['quantity'];
        $location_code = $component['item_location_code'];

        // Initialize the array for the item_code if it doesn't exist
        if (!isset($inventory_summary[$item_code])) {
            $inventory_summary[$item_code] = [
                'total' => 0,
                'available' => 0
            ];
        }
        // 1. Calculate Total: Sum up the quantity for all components of this item_code
        $inventory_summary[$item_code]['total'] += $quantity;
        // 2. Calculate Available: Sum up quantity only if item_location_code is NOT 'IU'
        if ($location_code !== 'IU') {
            $inventory_summary[$item_code]['available'] += $quantity;
        }
    }
}

$stmt_group = $conn->prepare("SELECT group_name, group_type FROM sections");
$stmt_group->execute();
$group_result = $stmt_group->get_result();
?>

<div class="main">
    <h1>Booking Page</h1>
    
    <div class="form-container">
            <h1 class="form-title" id="form_title">Booking Info</h1>
            <form id="details-form" class="form-layout" action="booking_creation.php" method="post">
            <div id="info_form">

                <!-- Name of Event -->
                <div>
                    <label for="eventName-form" class="form-label">Name of Event</label>
                    <input type="text" id="eventName" name="eventName" class="form-input" placeholder="e.g., Weekend camp at xyz" required>
                </div><br>

                <!-- Event Dates -->
                <div class="grid-layout-form">
                    <div>
                        <label for="eventStartDate" class="form-label">Event Start Date</label>
                        <input type="date" id="eventStartDate" name="eventStartDate" class="form-input" min="<?php date('Y-m-d') ?>" required>
                    </div>
                    <div>
                        <label for="eventFinishDate" class="form-label">Event Finish Date</label>
                        <input type="date" id="eventFinishDate" name="eventFinishDate" class="form-input" min="<?php date('Y-m-d') ?>" required>
                    </div>
                </div><br>

                <!-- Equipment Dates -->
                <div class="grid-layout-form">
                    <div>
                        <label for="collectionDate" class="form-label">Collection Date</label>
                        <input type="date" id="collectionDate" name="collectionDate" class="form-input" min="<?php date('Y-m-d') ?>" required>
                    </div>
                    <div>
                        <label for="returnDate" class="form-label">Return Date</label>
                        <input type="date" id="returnDate" name="returnDate" class="form-input" min="<?php date('Y-m-d') ?>" required>
                    </div>
                </div><br>

                <!-- Booker Name and Group -->
                <div class="grid-layout-form">
                    <div>
                        <label for="bookerName" class="form-label">Booking made by</label>
                        <input type="text" id="bookerName" name="bookerName" class="form-input" value="<?php echo htmlspecialchars($_SESSION["username"]); ?>" required>
                    </div>
                    <div>
                        <label for="group" class="form-label">For</label>
                        <select id="group" name="group" class="form-input" required>
                            <option value="" disabled selected>Select a group</option>
                            <?php
                                while ($row = $group_result->fetch_assoc()) {
                                    $groupName = htmlspecialchars($row['group_name']);
                                    $groupType = htmlspecialchars($row['group_type']);
                                    $displayValue = $groupName . ' ' . $groupType;
                                    echo "<option value=\"$groupName\">$displayValue</option>";
                                }
                                $stmt_group->close();
                            ?>
                        </select>
                    </div>
                </div><br>

                <div class="grid-layout-form">
                    <button class="submit-button" type="submit" onclick="get_items()">
                        Submit Booking Details
                    </button>
                </div>
            </div>
            </form>

            <form id="items-form" class="form-layout" action="#" method="post">
                <div id="item_form" style="display: block;">
                    <div>
                        <?php
                        if ($category_result->num_rows > 0) {
                            while ($category = $category_result->fetch_assoc()) {
                                $category_name = htmlspecialchars($category['category_name']);
                                $category_code = $category['category_code'];
                        ?>
                                <div class="accordian-item">
                                    <button class="accordion-header" type="button">
                                        <h4><?php echo $category_name; ?></h4>
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
                                                $image_path = '';
                                                if ($image_1_value !== null) {
                                                    // If 'image_1' has a non-null value, use it and secure it with htmlspecialchars
                                                    $image_path = htmlspecialchars($image_1_value);
                                                } else {
                                                    // If 'image_1' is null, use the fallback file path
                                                    $image_path = 'static\images\not-found.svg';
                                                }
                                                $item_code = $item['item_code'];

                                                // Get the total and available counts for the current item_code
                                                $total_count = $inventory_summary[$item_code]['total'] ?? 0;
                                                $available_count = $inventory_summary[$item_code]['available'] ?? 0;
                                        ?>
                                                <div class="grid-layout">
                                                    <div class="item-image">
                                                        <img src="<?php echo $image_path; ?>" alt="<?php echo $item_name; ?>" style="max-width: 200px;">
                                                    </div>
                                                    
                                                    <div class="item-name">
                                                        <?php echo $item_name; ?>
                                                        <span class="info-button" onclick="toggleDescription('item-desc-<?php echo $item_code; ?>')">i</span>
                                                    </div>
                                                    
                                                    <div class="item-desc hidden-desc" id="item-desc-<?php echo $item_code; ?>">
                                                        <?php echo $item_desc; ?>
                                                    </div>
                                                    
                                                    <div class="item-total">Total: <b><?php echo $total_count; ?></b></div> 
                                                    <div class="item-available">Available: <b><?php echo $available_count; ?></b></div>
                                                    <div class="item-booked">
                                                        This booking: 
                                                        <input type="number" name="booked_item_<?php echo $item_code; ?>" style="width: 40px;" min="0" max="<?php echo $available_count; ?>">
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

                    <div class="grid-layout-form">
                        <button class="submit-button" type="submit">
                            Submit Items
                        </button>
                    </div>
                </div>
            </form>

    </div>
    <script src="/online_quartermaster/static/js/faq_funcs.js"></script>
    <script src="static/js/booking_funcs.js"></script>
</div>