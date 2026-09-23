<?php
session_start();

if (!isset($_SESSION['user_level']) || !in_array($_SESSION['user_level'], [1, 2], true)) {
    echo '<div class="form-container"><h1 class="form-title">Not authorised</h1>';
    echo '<p>You do not have permission to edit items.</p>';
    echo '<p><a href="index.php?page=store_details">Return to store details</a></p>';
    echo '</div>';
    exit();
}

require_once 'db_conn.php';

function create_component_with_unique_code($conn, $item_code, $category_code, $quantity, $location_code, $replacement_cost, $date_purchased, $end_of_life) {
    $stmt_component = $conn->prepare("INSERT INTO components (component_code, item_code, quantity, item_quality, quality_desc, replacement_cost, date_purchased, end_of_life, item_location_code) VALUES (?, ?, 1, '1', 'Good', NULLIF(?, ''), NULLIF(?, ''), NULLIF(?, ''), ?)");
    $prefix = strtoupper(substr(preg_replace('/[^A-Za-z0-9]/', '', $category_code), 0, 6));
    $prefix = $prefix !== '' ? $prefix : 'ITEM';

    for ($unit = 0; $unit < $quantity; $unit++) {
        $inserted = false;
        for ($attempt = 0; $attempt < 10; $attempt++) {
            $component_code = $prefix . str_pad((string) random_int(0, 9999), 4, '0', STR_PAD_LEFT);
            $stmt_component->bind_param("ssssss", $component_code, $item_code, $replacement_cost, $date_purchased, $end_of_life, $location_code);
            if ($stmt_component->execute()) {
                $inserted = true;
                break;
            }
            if ($stmt_component->errno !== 1062) {
                return false;
            }
        }
        if (!$inserted) {
            return false;
        }
    }

    return true;
}

$item_code = $_GET['item_code'] ?? '';
if (!$item_code) {
    echo "<p>Item not found.</p>";
    return;
}

$stmt = $conn->prepare("SELECT item_code, item_name, item_desc, image_1, category_code FROM items WHERE item_code = ?");
$stmt->bind_param("s", $item_code);
$stmt->execute();
$item = $stmt->get_result()->fetch_assoc();

$quantity_error = '';
$location_code = trim($_POST['location_code'] ?? '');
$replacement_cost = trim($_POST['replacement_cost'] ?? '');
$date_purchased = trim($_POST['date_purchased'] ?? '');
$end_of_life = trim($_POST['end_of_life'] ?? '');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $item_name = trim($_POST['item_name']);
    $item_desc = trim($_POST['item_desc']);
    $image_1 = $item['image_1'];
    $category_code = trim($_POST['category_code']);
    $location_code = trim($_POST['location_code'] ?? '');

    $total_quantity = filter_var($_POST['total_quantity'] ?? null, FILTER_VALIDATE_INT);
    if ($total_quantity === false || $total_quantity < 0) {
        $quantity_error = 'Total amount must be a whole number of 0 or more.';
    }

    if ($quantity_error === '' && ($item_name === '' || $category_code === '')) {
        $quantity_error = 'Item name and category are required.';
    }

    if ($quantity_error === '' && $location_code === '') {
        $quantity_error = 'Please select an item location.';
    }

    if ($quantity_error === '' && $replacement_cost === '') {
        $quantity_error = 'Replacement cost is required.';
    }

    if ($quantity_error === '' && $date_purchased === '') {
        $quantity_error = 'Purchase date is required.';
    }

    if ($quantity_error === '') {
        $stmt_location = $conn->prepare("SELECT location_code FROM locations WHERE location_code = ? AND UPPER(TRIM(location_code)) <> 'IU'");
        $stmt_location->bind_param("s", $location_code);
        $stmt_location->execute();
        if ($stmt_location->get_result()->num_rows === 0) {
            $quantity_error = 'Please select a valid item location.';
        }
    }

    if ($quantity_error === '' && ($replacement_cost === '' || is_numeric($replacement_cost)) && $replacement_cost !== '' && (float) $replacement_cost < 0) {
        $quantity_error = 'Replacement cost must be a valid amount of 0 or more.';
    } elseif ($quantity_error === '' && $replacement_cost !== '' && !is_numeric($replacement_cost)) {
        $quantity_error = 'Replacement cost must be a valid amount of 0 or more.';
    }

    foreach (['date_purchased' => $date_purchased, 'end_of_life' => $end_of_life] as $date_field => $date_value) {
        if ($quantity_error === '' && $date_value !== '') {
            $date = DateTime::createFromFormat('Y-m-d', $date_value);
            if (!$date || $date->format('Y-m-d') !== $date_value) {
                $quantity_error = 'Please enter valid component dates.';
            }
        }
    }


    if ($quantity_error === '' && !empty($_FILES['image_1']['name']) && $_FILES['image_1']['error'] === UPLOAD_ERR_OK) {
        $allowed = ['jpg', 'jpeg', 'png', 'gif'];
        $fileName = basename($_FILES['image_1']['name']);
        $fileExt = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));

        if (in_array($fileExt, $allowed, true)) {
            $uploadDir = __DIR__ . '/static/images/item_images/';
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0755, true);
            }

            $newFileName = $item_code . '_' . uniqid() . '.' . $fileExt;
            $targetPath = $uploadDir . $newFileName;

            $imageInfo = getimagesize($_FILES['image_1']['tmp_name']);
            if ($imageInfo !== false && move_uploaded_file($_FILES['image_1']['tmp_name'], $targetPath)) {
                $image_1 = $newFileName;
            } else {
                echo '<p class="error">Failed to upload image.</p>';
            }
        } else {
            echo '<p class="error">Allowed image types: jpg, jpeg, png, gif.</p>';
        }
    }

    if ($quantity_error === '') {
        $stmt_components = $conn->prepare("SELECT component_code, quantity, item_location_code FROM components WHERE item_code = ? ORDER BY item_location_code = 'IU' DESC, component_code");
        $stmt_components->bind_param("s", $item_code);
        $stmt_components->execute();
        $component_result = $stmt_components->get_result();
        $components = $component_result->fetch_all(MYSQLI_ASSOC);
        $current_total = count($components);
        $in_use_total = 0;
        foreach ($components as $component) {
            if (strtoupper(trim($component['item_location_code'])) === 'IU') {
                $in_use_total++;
            }
        }
        $quantity_difference = $total_quantity - $current_total;

        if ($total_quantity < $in_use_total) {
            $quantity_error = 'Total amount cannot be lower than the number of units currently in use.';
        }

        if ($quantity_error === '') {
            $conn->begin_transaction();

        $stmt = $conn->prepare("
            UPDATE items
            SET item_name = ?, item_desc = ?, image_1 = ?, category_code = ?
            WHERE item_code = ?
        ");
        $stmt->bind_param("sssss", $item_name, $item_desc, $image_1, $category_code, $item_code);
        $item_updated = $stmt->execute();

        $stmt_component_details = $conn->prepare("UPDATE components SET replacement_cost = NULLIF(?, ''), date_purchased = NULLIF(?, ''), end_of_life = NULLIF(?, '') WHERE item_code = ? AND UPPER(TRIM(item_location_code)) <> 'IU'");
        $stmt_component_details->bind_param("ssss", $replacement_cost, $date_purchased, $end_of_life, $item_code);
        $component_details_updated = $stmt_component_details->execute();

        $quantity_updated = $component_details_updated;
        if (count($components) === 0) {
            $quantity_updated = $quantity_updated && create_component_with_unique_code($conn, $item_code, $category_code, $total_quantity, $location_code, $replacement_cost, $date_purchased, $end_of_life);
        } elseif ($quantity_difference > 0) {
                $quantity_updated = $quantity_updated && create_component_with_unique_code($conn, $item_code, $category_code, $quantity_difference, $location_code, $replacement_cost, $date_purchased, $end_of_life);
        } elseif ($quantity_difference < 0) {
                $remaining_reduction = abs($quantity_difference);
                foreach ($components as $component) {
                    if ($remaining_reduction === 0) {
                        break;
                    }
                    if (strtoupper(trim($component['item_location_code'])) === 'IU') {
                        continue;
                    }

                    $stmt_quantity = $conn->prepare("DELETE FROM components WHERE component_code = ? AND item_code = ? AND UPPER(TRIM(item_location_code)) <> 'IU'");
                    $component_code = $component['component_code'];
                    $stmt_quantity->bind_param("ss", $component_code, $item_code);
                    $deleted = $stmt_quantity->execute() && $stmt_quantity->affected_rows === 1;
                    $quantity_updated = $quantity_updated && $deleted;
                    if ($deleted) {
                        $remaining_reduction--;
                    }
                }
                $quantity_updated = $quantity_updated && $remaining_reduction === 0;
        }

        if ($item_updated && $quantity_updated) {
            $conn->commit();
            header("Location: index.php?page=store_details");
            exit();
        }

        $conn->rollback();
        $quantity_error = 'The item could not be updated.';
        }
    }
}

$stmt_total = $conn->prepare("SELECT COUNT(*) AS total_quantity FROM components WHERE item_code = ?");
$stmt_total->bind_param("s", $item_code);
$stmt_total->execute();
$current_total_quantity = (int) $stmt_total->get_result()->fetch_assoc()['total_quantity'];

$stmt_default_location = $conn->prepare("SELECT item_location_code FROM components WHERE item_code = ? AND UPPER(TRIM(item_location_code)) <> 'IU' ORDER BY component_code LIMIT 1");
$stmt_default_location->bind_param("s", $item_code);
$stmt_default_location->execute();
$default_location = $stmt_default_location->get_result()->fetch_assoc()['item_location_code'] ?? 'OOS1';
if ($location_code === '') {
    $location_code = $default_location;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $stmt_component_details = $conn->prepare("SELECT replacement_cost, date_purchased, end_of_life FROM components WHERE item_code = ? AND UPPER(TRIM(item_location_code)) <> 'IU' ORDER BY component_code LIMIT 1");
    $stmt_component_details->bind_param("s", $item_code);
    $stmt_component_details->execute();
    $component_details = $stmt_component_details->get_result()->fetch_assoc();
    $replacement_cost = $component_details['replacement_cost'] ?? '';
    $date_purchased = $component_details['date_purchased'] ?? '';
    $end_of_life = $component_details['end_of_life'] ?? '';
}

$categories = [];
$stmt2 = $conn->prepare("SELECT category_code, category_name FROM categories ORDER BY category_name");
$stmt2->execute();
$result2 = $stmt2->get_result();
while ($row = $result2->fetch_assoc()) {
    $categories[] = $row;
}
$locations = [];
$stmt_locations = $conn->prepare("SELECT location_code, location_name FROM locations WHERE UPPER(TRIM(location_code)) <> 'IU' ORDER BY location_name");
$stmt_locations->execute();
$locations_result = $stmt_locations->get_result();
while ($row = $locations_result->fetch_assoc()) {
    $locations[] = $row;
}
?>

<div class="main">
    <div class="form-container">
        <h1 class="form-title">Edit Item</h1>
        <form class="form-layout" method="post" enctype="multipart/form-data">
            <div>
                <label class="form-label" for="item_name">Item Name</label>
                <input id="item_name" name="item_name" class="form-input" value="<?php echo htmlspecialchars($item['item_name']); ?>" required>
            </div>

            <div class="form-row">
                <div>
                    <label class="form-label" for="category_code">Category</label>
                    <select id="category_code" name="category_code" class="form-input" required>
                        <?php foreach ($categories as $cat): ?>
                            <option value="<?php echo htmlspecialchars($cat['category_code']); ?>"
                                <?php if (strcasecmp(trim($cat['category_code']), trim($item['category_code'])) === 0) echo 'selected'; ?>>
                                <?php echo htmlspecialchars($cat['category_name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div>
                    <label class="form-label" for="total_quantity">Total amount</label>
                    <input id="total_quantity" name="total_quantity" type="number" min="0" step="1" class="form-input" value="<?php echo htmlspecialchars((string) $current_total_quantity); ?>" required>
                    <?php if ($quantity_error !== ''): ?>
                        <p class="error"><?php echo htmlspecialchars($quantity_error); ?></p>
                    <?php endif; ?>
                </div>
                <div>
                    <label class="form-label" for="location_code">Location for added items</label>
                    <select id="location_code" name="location_code" class="form-input" required>
                        <?php foreach ($locations as $location): ?>
                            <option value="<?php echo htmlspecialchars($location['location_code']); ?>" <?php echo $location_code === $location['location_code'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($location['location_name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <div class="form-row">
                <div>
                    <label class="form-label" for="date_purchased">Purchase date</label>
                    <input id="date_purchased" name="date_purchased" type="date" class="form-input" value="<?php echo htmlspecialchars($date_purchased); ?>" required>
                </div>
                <div>
                    <label class="form-label" for="end_of_life">End of life</label>
                    <input id="end_of_life" name="end_of_life" type="date" class="form-input" value="<?php echo htmlspecialchars($end_of_life); ?>">
                </div>
            </div>
            <div>
                <label class="form-label" for="replacement_cost">Replacement cost</label>
                <input id="replacement_cost" name="replacement_cost" type="number" min="0" step="0.01" class="form-input" value="<?php echo htmlspecialchars($replacement_cost); ?>" required>
            </div>

            <div>
                <label class="form-label" for="item_desc">Description</label>
                <textarea id="item_desc" name="item_desc" class="form-input" rows="4"><?php echo htmlspecialchars($item['item_desc']); ?></textarea>
            </div>

            <div>
                <label class="form-label" for="image_1">Upload new image</label>
                <input id="image_1" name="image_1" type="file" class="form-input" accept=".jpg,.jpeg,.png,.gif">
                <p class="form-help">Leave blank to keep the current image. (Current image is displayed below if available)</p>
            </div>

            <button type="submit" class="submit-button">Save item</button>
        </form>
    </div>
</div>

<?php if (!empty($item['image_1'])): ?>
    <div class="form-label">Current image</div>
    <div class="store-card-image">
        <img src="./static/images/item_images/<?php echo htmlspecialchars($item['image_1']); ?>"
             alt="<?php echo htmlspecialchars($item['item_name']); ?>"
             class="store-card-image-img">
    </div>
<?php else: ?>
    <p>No current image.</p>
<?php endif; ?>