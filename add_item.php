<?php
session_start();

if (!isset($_SESSION['user_level']) || !in_array($_SESSION['user_level'], [1, 2], true)) {
    echo '<div class="form-container"><h1 class="form-title">Not authorised</h1>';
    echo '<p>You do not have permission to add items.</p>';
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

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $item_code = trim($_POST['item_code']);
    $item_type = 'individual';
    $item_name = trim($_POST['item_name']);
    $item_desc = trim($_POST['item_desc']);
    $category_code = trim($_POST['category_code']);
    $location_code = trim($_POST['location_code'] ?? '');
    $replacement_cost = trim($_POST['replacement_cost'] ?? '');
    $date_purchased = trim($_POST['date_purchased'] ?? '');
    $end_of_life = trim($_POST['end_of_life'] ?? '');
    $total_quantity = filter_var($_POST['total_quantity'] ?? null, FILTER_VALIDATE_INT);
    $quantity_error = '';
    $image_1 = '';

    if ($total_quantity === false || $total_quantity < 0) {
        $quantity_error = 'Total amount must be a whole number of 0 or more.';
    }

    if ($quantity_error === '' && ($item_code === '' || $item_name === '' || $category_code === '')) {
        $quantity_error = 'Item code, item name, and category are required.';
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

    if ($quantity_error === '' && ($replacement_cost !== '' && (!is_numeric($replacement_cost) || (float) $replacement_cost < 0))) {
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

    if ($quantity_error === '') {
        $stmt_location = $conn->prepare("SELECT location_code FROM locations WHERE location_code = ? AND UPPER(TRIM(location_code)) <> 'IU'");
        $stmt_location->bind_param("s", $location_code);
        $stmt_location->execute();
        if ($stmt_location->get_result()->num_rows === 0) {
            $quantity_error = 'Please select a valid item location.';
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

            if (getimagesize($_FILES['image_1']['tmp_name']) !== false &&
                move_uploaded_file($_FILES['image_1']['tmp_name'], $targetPath)) {
                $image_1 = $newFileName;
            }
        }
    }

    if ($quantity_error === '') {
        $conn->begin_transaction();

        $stmt = $conn->prepare("
            INSERT INTO items (item_code, category_code, item_type, item_name, item_desc, image_1)
            VALUES (?, ?, ?, ?, ?, ?)
        ");
        $stmt->bind_param("ssssss", $item_code, $category_code, $item_type, $item_name, $item_desc, $image_1);
        $item_created = $stmt->execute();

        if (!$item_created) {
            $conn->rollback();
            if ($stmt->errno === 1062) {
                $quantity_error = 'The item code already exists. Please choose a different item code.';
            } elseif ($stmt->errno === 1364) {
                $quantity_error = 'The item could not be created because a required item field is missing.';
            } else {
                $quantity_error = 'The item could not be created. Please check the item details and try again.';
            }
        } else {
            $component_created = create_component_with_unique_code($conn, $item_code, $category_code, $total_quantity, $location_code, $replacement_cost, $date_purchased, $end_of_life);

            if ($component_created) {
                $conn->commit();
                header("Location: index.php?page=store_details");
                exit();
            }

            $conn->rollback();
            $quantity_error = 'The item could not be created because its components could not be added. No changes were saved; please try again.';
        }
    }
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
        <h1 class="form-title">Add New Item</h1>
        <?php if (!empty($quantity_error)): ?>
            <p class="error" role="alert"><?php echo htmlspecialchars($quantity_error); ?></p>
        <?php endif; ?>
        <form class="form-layout" method="post" enctype="multipart/form-data">
            <div class="form-row">
                <div>
                    <label class="form-label" for="item_code">Item Code</label>
                    <input id="item_code" name="item_code" class="form-input" required>
                    <p class="form-help">Unique identifier for the item. Must be unique to other items, and in camelCase. (e.g., testItemCode)</p>
                </div>
                <div>
                    <label class="form-label" for="item_name">Item Name</label>
                    <input id="item_name" name="item_name" class="form-input" required>
                </div>
            </div>
            <div class="form-row">
                <div>
                    <label class="form-label" for="category_code">Category</label>
                    <select id="category_code" name="category_code" class="form-input" required>
                        <?php foreach ($categories as $cat): ?>
                            <option value="<?php echo htmlspecialchars($cat['category_code']); ?>">
                                <?php echo htmlspecialchars($cat['category_name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="form-label" for="location_code">Item location</label>
                    <select id="location_code" name="location_code" class="form-input" required>
                        <option value="" disabled <?php echo empty($_POST['location_code']) ? 'selected' : ''; ?>>Select a location</option>
                        <?php foreach ($locations as $location): ?>
                            <option value="<?php echo htmlspecialchars($location['location_code']); ?>" <?php echo ($_POST['location_code'] ?? '') === $location['location_code'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($location['location_name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <div class="form-row">
                <div>
                    <label class="form-label" for="total_quantity">Total amount</label>
                    <input id="total_quantity" name="total_quantity" type="number" min="0" step="1" class="form-input" value="<?php echo htmlspecialchars((string) ($_POST['total_quantity'] ?? '0')); ?>" required>
                </div>
                <div>
                    <label class="form-label" for="replacement_cost">Replacement cost</label>
                    <input id="replacement_cost" name="replacement_cost" type="number" min="0" step="0.01" class="form-input" value="<?php echo htmlspecialchars($_POST['replacement_cost'] ?? ''); ?>" required>
                </div>
            </div>
            <div class="form-row">
                <div>
                    <label class="form-label" for="date_purchased">Purchase date</label>
                    <input id="date_purchased" name="date_purchased" type="date" class="form-input" value="<?php echo htmlspecialchars($_POST['date_purchased'] ?? ''); ?>" required>
                </div>
                <div>
                    <label class="form-label" for="end_of_life">End of life</label>
                    <input id="end_of_life" name="end_of_life" type="date" class="form-input" value="<?php echo htmlspecialchars($_POST['end_of_life'] ?? ''); ?>">
                </div>
            </div>
            <div>
                <label class="form-label" for="item_desc">Description</label>
                <textarea id="item_desc" name="item_desc" class="form-input" rows="4"></textarea>
            </div>
            <div>
                <label class="form-label" for="image_1">Upload image</label>
                <input id="image_1" name="image_1" type="file" class="form-input" accept=".jpg,.jpeg,.png,.gif">
                <p class="form-help">Optional. Leave blank if you do not want to upload an image.</p>
            </div>
            <button type="submit" class="submit-button">Create item</button>
        </form>
    </div>
</div>