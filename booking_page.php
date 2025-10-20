<?php
$stmt_group = $conn->prepare("SELECT group_name, group_type FROM sections");
$stmt_group->execute();
$group_result = $stmt_group->get_result();

$stmt_equipment = $conn->prepare("SELECT item_code, item_name, item_desc, image_1 FROM items");
$stmt_equipment->execute();
$equipment_result = $stmt_equipment->get_result();

$stmt_category = $conn->prepare("SELECT category_name FROM categories");
$stmt_category->execute();
$category_result = $stmt_category->get_result();

$stmt_components = $conn->prepare("SELECT item_code, quantity, item_quality, item_location_code FROM components");
$stmt_components->execute();
$component_result = $stmt_components->get_result();
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
                    <!-- MAKE RECUSIVE FOR EACH CATEGORY -->
                    <div class="accordian-item">
                        <button class="accordion-header" type="button">
                            <h4><!-- ADD CATEGORY NAME HERE -->Lightwieght Tentage</h4>
                        </button>
                        <div class="accordion-content">
                            <!-- ADD EACH ITEM IN CATEGORY HERE -->
                            <div class="grid-layout">
                                <div class="item-image"><img src="/online_quartermaster/static/images/item_images/Northgear4-1.png" style="max-width: max-content;"></div>
                                
                                <div class="item-name">
                                    NorthGear 4 Person Tent
                                    <span class="info-button" onclick="toggleDescription('item-desc-1')">i</span>
                                </div>
                                
                                <div class="item-desc hidden-desc" id="item-desc-1">
                                    Inner, flysheet, 2xlong poles, 1xshort pole, pole bag (with some), 20 x BD pegs
                                </div>
                                
                                <div class="item-total">Total: 6</div>
                                <div class="item-available">Available: 6</div>
                                <div class="item-booked">This booking: <input type="number" style="width: 40px;" min="0"></div>
                            </div>
                        </div>
                    </div>
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