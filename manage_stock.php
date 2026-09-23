<?php
session_start();

// Restrict access to admins and quartermasters only
if (!isset($_SESSION['user_level']) || !in_array($_SESSION['user_level'], [1, 2], true)) {
    header("Location: index.php?page=home");
    exit();
}

require_once 'db_conn.php';

$item_code = $_GET['item_code'] ?? '';
if (!$item_code) {
    header("Location: index.php?page=store_details");
    exit();
}

// Fetch item details
$stmt = $conn->prepare("SELECT i.item_code, i.item_name, i.item_desc, i.image_1, c.category_name FROM items i LEFT JOIN categories c ON i.category_code = c.category_code WHERE i.item_code = ?");
$stmt->bind_param("s", $item_code);
$stmt->execute();
$item = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$item) {
    header("Location: index.php?page=store_details");
    exit();
}

$success_message = '';
$error_message = '';
$action = $_POST['action'] ?? '';
$current_user_id = (int) ($_SESSION['user_id'] ?? 0);

// Handle add bulk units
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'add_bulk') {
    $bulk_quantity = filter_var($_POST['bulk_quantity'] ?? 0, FILTER_VALIDATE_INT);
    $location_code = trim($_POST['location_code'] ?? '');
    $replacement_cost = trim($_POST['replacement_cost'] ?? '');
    $date_purchased = trim($_POST['date_purchased'] ?? '');
    $end_of_life = trim($_POST['end_of_life'] ?? '');
    
    if ($bulk_quantity === false || $bulk_quantity < 1) {
        $error_message = "Quantity must be at least 1.";
    } elseif (empty($location_code)) {
        $error_message = "Location is required.";
    } else {
        $prefix = strtoupper(substr(preg_replace('/[^A-Za-z0-9]/', '', $item_code), 0, 6));
        $prefix = $prefix !== '' ? $prefix : 'ITEM';
        
        $added_count = 0;
        for ($i = 0; $i < $bulk_quantity; $i++) {
            for ($attempt = 0; $attempt < 10; $attempt++) {
                $component_code = $prefix . str_pad((string) random_int(0, 9999), 4, '0', STR_PAD_LEFT);
                
                $stmt = $conn->prepare("INSERT INTO components (component_code, item_code, quantity, item_quality, quality_desc, replacement_cost, date_purchased, end_of_life, item_location_code) VALUES (?, ?, 1, '1', 'Good', NULLIF(?, ''), NULLIF(?, ''), NULLIF(?, ''), ?)");
                $stmt->bind_param("ssssss", $component_code, $item_code, $replacement_cost, $date_purchased, $end_of_life, $location_code);
                
                if ($stmt->execute()) {
                    $added_count++;
                    $stmt->close();
                    break;
                }
                $stmt->close();
            }
        }
        
        if ($added_count > 0) {
            $success_message = "Added $added_count unit" . ($added_count !== 1 ? "s" : "") . " successfully.";
        } else {
            $error_message = "Failed to add units.";
        }
    }
}

// Handle delete component
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'delete_component') {
    $component_code = trim($_POST['component_code'] ?? '');
    
    if (empty($component_code)) {
        $error_message = "Component code is required.";
    } else {
        $stmt = $conn->prepare("DELETE FROM components WHERE component_code = ? AND item_code = ? AND UPPER(TRIM(item_location_code)) NOT IN ('IU', 'RETIRED')");
        $stmt->bind_param("ss", $component_code, $item_code);
        
        if ($stmt->execute()) {
            if ($stmt->affected_rows > 0) {
                $success_message = "Unit removed successfully.";
            } else {
                $error_message = "Unit not found, retired, or currently in use.";
            }
        } else {
            $error_message = "Failed to remove unit.";
        }
        $stmt->close();
    }
}

// Handle update component
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'update_component') {
    $component_code = trim($_POST['component_code'] ?? '');
    $quality = trim($_POST['quality'] ?? '');
    $quality_desc = trim($_POST['quality_desc'] ?? '');
    $location_code = trim($_POST['location_code'] ?? '');
    $replacement_cost = trim($_POST['replacement_cost'] ?? '');
    $date_purchased = trim($_POST['date_purchased'] ?? '');
    $end_of_life = trim($_POST['end_of_life'] ?? '');
    
    if (empty($component_code) || empty($location_code)) {
        $error_message = "Component code and location are required.";
    } else {
        $stmt_existing = $conn->prepare("SELECT item_location_code FROM components WHERE component_code = ? AND item_code = ?");
        $stmt_existing->bind_param("ss", $component_code, $item_code);
        $stmt_existing->execute();
        $existing_component = $stmt_existing->get_result()->fetch_assoc();
        if (!$existing_component || strtoupper(trim($existing_component['item_location_code'])) === 'RETIRED') {
            $error_message = 'Retired units must be restored using the Restore unit action.';
        } else {
            $stmt = $conn->prepare("UPDATE components SET item_quality = ?, quality_desc = ?, replacement_cost = NULLIF(?, ''), date_purchased = NULLIF(?, ''), end_of_life = NULLIF(?, ''), item_location_code = ? WHERE component_code = ? AND item_code = ?");
            $stmt->bind_param("ssssssss", $quality, $quality_desc, $replacement_cost, $date_purchased, $end_of_life, $location_code, $component_code, $item_code);
            if ($stmt->execute()) {
                $success_message = "Unit details updated successfully.";
            } else {
                $error_message = "Failed to update unit.";
            }
            $stmt->close();
        }
    }
}

// Record a maintenance event for one physical component.
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'add_maintenance') {
    $component_code = trim($_POST['component_code'] ?? '');
    $issue_description = trim($_POST['issue_description'] ?? '');
    $action_taken = trim($_POST['action_taken'] ?? '');
    $condition_after = trim($_POST['condition_after'] ?? 'Needs maintenance');
    $cost = trim($_POST['maintenance_cost'] ?? '');

    if ($component_code === '' || $issue_description === '') {
        $error_message = 'Component code and issue description are required.';
    } elseif (!in_array($condition_after, ['Good', 'Needs maintenance', 'Damaged', 'Retired'], true)) {
        $error_message = 'Please select a valid resulting condition.';
    } elseif ($cost !== '' && (!is_numeric($cost) || (float) $cost < 0)) {
        $error_message = 'Maintenance cost must be zero or more.';
    } else {
        $stmt_component = $conn->prepare("SELECT item_code, item_quality, item_location_code FROM components WHERE component_code = ? AND item_code = ?");
        $stmt_component->bind_param("ss", $component_code, $item_code);
        $stmt_component->execute();
        $component_row = $stmt_component->get_result()->fetch_assoc();
        if (!$component_row) {
            $error_message = 'Component not found.';
        } else {
            $condition_before = $component_row['item_quality'] === '1' ? 'Good' : 'Damaged';
            $conn->begin_transaction();
            $stmt_log = $conn->prepare("INSERT INTO item_maintenance_log (component_code, item_code, performed_by_user_id, issue_description, action_taken, condition_before, condition_after, cost, resolved_at, resolved_by_user_id) VALUES (?, ?, ?, ?, ?, ?, ?, NULLIF(?, ''), ?, ?)");
            $resolved_at = in_array($condition_after, ['Good', 'Retired'], true) ? date('Y-m-d H:i:s') : null;
            $resolved_by = $resolved_at === null ? null : $current_user_id;
            $stmt_log->bind_param("ssissssssi", $component_code, $item_code, $current_user_id, $issue_description, $action_taken, $condition_before, $condition_after, $cost, $resolved_at, $resolved_by);
            $new_quality = $condition_after === 'Good' ? '1' : '0';
            $new_location = $condition_after === 'Retired' ? 'RETIRED' : $component_row['item_location_code'];
            $stmt_component_update = $conn->prepare("UPDATE components SET item_quality = ?, quality_desc = ? , item_location_code = ? WHERE component_code = ? AND item_code = ? AND UPPER(TRIM(item_location_code)) <> 'IU'");
            $stmt_component_update->bind_param("sssss", $new_quality, $issue_description, $new_location, $component_code, $item_code);
            if ($stmt_log->execute() && $stmt_component_update->execute() && $stmt_component_update->affected_rows === 1) {
                $conn->commit();
                $success_message = 'Maintenance history recorded.';
            } else {
                $conn->rollback();
                $error_message = 'The maintenance event could not be saved. Units currently in use cannot be changed.';
            }
        }
    }
}

// Retirement preserves the unit and its history while removing it from stock.
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'retire_component') {
    $component_code = trim($_POST['component_code'] ?? '');
    $retirement_reason = trim($_POST['retirement_reason'] ?? '');
    if ($component_code === '' || $retirement_reason === '') {
        $error_message = 'A retirement reason is required.';
    } else {
        $stmt_component = $conn->prepare("SELECT item_code, item_quality, item_location_code FROM components WHERE component_code = ? AND item_code = ?");
        $stmt_component->bind_param("ss", $component_code, $item_code);
        $stmt_component->execute();
        $component_row = $stmt_component->get_result()->fetch_assoc();
        if (!$component_row) {
            $error_message = 'Component not found.';
        } elseif (strtoupper(trim($component_row['item_location_code'])) === 'IU') {
            $error_message = 'A unit currently in use must be returned before retirement.';
        } else {
            $conn->begin_transaction();
            $condition_before = $component_row['item_quality'] === '1' ? 'Good' : 'Damaged';
            $condition_after = 'Retired';
            $stmt_log = $conn->prepare("INSERT INTO item_maintenance_log (component_code, item_code, performed_by_user_id, issue_description, action_taken, condition_before, condition_after, resolved_at, resolved_by_user_id) VALUES (?, ?, ?, ?, 'Component retired', ?, ?, NOW(), ?)");
            $stmt_log->bind_param("ssisssi", $component_code, $item_code, $current_user_id, $retirement_reason, $condition_before, $condition_after, $current_user_id);
            $stmt_retire = $conn->prepare("UPDATE components SET item_quality = '0', quality_desc = ?, item_location_code = 'RETIRED' WHERE component_code = ? AND item_code = ? AND UPPER(TRIM(item_location_code)) <> 'IU'");
            $stmt_retire->bind_param("sss", $retirement_reason, $component_code, $item_code);
            if ($stmt_log->execute() && $stmt_retire->execute() && $stmt_retire->affected_rows === 1) {
                $conn->commit();
                $success_message = 'Component retired and retained in the audit history.';
            } else {
                $conn->rollback();
                $error_message = 'The component could not be retired.';
            }
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'restore_component') {
    $component_code = trim($_POST['component_code'] ?? '');
    $restore_location = trim($_POST['restore_location'] ?? '');
    if ($component_code === '' || $restore_location === '' || strtoupper($restore_location) === 'IU' || strtoupper($restore_location) === 'RETIRED') {
        $error_message = 'Select a normal storage location to restore this component.';
    } else {
        $conn->begin_transaction();
        $stmt_restore_log = $conn->prepare("INSERT INTO item_maintenance_log (component_code, item_code, performed_by_user_id, issue_description, action_taken, condition_before, condition_after, resolved_at, resolved_by_user_id) VALUES (?, ?, ?, 'Retirement reviewed', 'Component restored to stock', 'Retired', 'Good', NOW(), ?)");
        $stmt_restore_log->bind_param("ssii", $component_code, $item_code, $current_user_id, $current_user_id);
        $stmt_restore = $conn->prepare("UPDATE components SET item_quality = '1', quality_desc = 'Restored from retirement', item_location_code = ? WHERE component_code = ? AND item_code = ? AND item_location_code = 'RETIRED'");
        $stmt_restore->bind_param("sss", $restore_location, $component_code, $item_code);
        if ($stmt_restore_log->execute() && $stmt_restore->execute() && $stmt_restore->affected_rows === 1) {
            $conn->commit();
            $success_message = 'Component restored to stock.';
        } else {
            $conn->rollback();
            $error_message = 'The component could not be restored.';
        }
    }
}

// Fetch all components for this item
$stmt = $conn->prepare("
    SELECT c.component_code, c.item_code, c.quantity, c.item_quality, c.quality_desc, 
           c.replacement_cost, c.date_purchased, c.end_of_life, c.item_location_code,
           l.location_name,
        CASE WHEN UPPER(TRIM(c.item_location_code)) = 'IU' THEN 1 ELSE 0 END AS active_bookings,
        (SELECT COUNT(*) FROM item_maintenance_log ml WHERE ml.component_code = c.component_code) AS maintenance_count
    FROM components c
    LEFT JOIN locations l ON c.item_location_code = l.location_code
    WHERE c.item_code = ?
    GROUP BY c.component_code
    ORDER BY c.component_code
");
$stmt->bind_param("s", $item_code);
$stmt->execute();
$components_result = $stmt->get_result();
$stmt->close();

// Get totals
$total_components = $components_result->num_rows;
$components_result->data_seek(0);
$booked_count = 0;
while ($row = $components_result->fetch_assoc()) {
    if ($row['active_bookings'] > 0) {
        $booked_count++;
    }
}
$retired_count = 0;
$components_result->data_seek(0);
while ($row = $components_result->fetch_assoc()) {
    if (strtoupper(trim($row['item_location_code'])) === 'RETIRED') {
        $retired_count++;
    }
}
$available_count = $total_components - $booked_count - $retired_count;
$components_result->data_seek(0);
$components_result->data_seek(0);

// Fetch locations for dropdown
$stmt = $conn->prepare("SELECT location_code, location_name FROM locations ORDER BY location_name");
$stmt->execute();
$locations_result = $stmt->get_result();
$stmt->close();

$maintenance_by_component = [];
$maintenance_table_check = $conn->query("SHOW TABLES LIKE 'item_maintenance_log'");
if ($maintenance_table_check && $maintenance_table_check->num_rows > 0) {
    $stmt_maintenance = $conn->prepare("SELECT component_code, reported_at, issue_description, action_taken, condition_before, condition_after, cost, resolved_at FROM item_maintenance_log WHERE item_code = ? ORDER BY reported_at DESC");
    $stmt_maintenance->bind_param("s", $item_code);
    $stmt_maintenance->execute();
    $maintenance_result = $stmt_maintenance->get_result();
    while ($maintenance = $maintenance_result->fetch_assoc()) {
        $maintenance_by_component[$maintenance['component_code']][] = $maintenance;
    }
    $stmt_maintenance->close();
}
?>

<div class="main">
    <div style="padding: 2rem;">
        <a href="index.php?page=store_details" style="color: #3a8a9e; text-decoration: underline; margin-bottom: 1rem; display: inline-block;">← Back to Store Details</a>
        
        <h1>Manage Stock: <?php echo htmlspecialchars($item['item_name']); ?></h1>
        
        <?php if ($success_message): ?>
            <div style="background-color: #dcfce7; border: 1px solid #22c55e; color: #16a34a; padding: 1rem; border-radius: 0.5rem; margin-bottom: 1rem; font-weight: bold;">
                ✅ <?php echo htmlspecialchars($success_message); ?>
            </div>
        <?php endif; ?>
        
        <?php if ($error_message): ?>
            <div class="error" style="margin-bottom: 1rem;">
                <?php echo htmlspecialchars($error_message); ?>
            </div>
        <?php endif; ?>
        
        <!-- Summary Cards -->
        <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 1rem; margin-bottom: 2rem;">
            <div style="background-color: #f0f8ff; border: 2px solid #3a8a9e; border-radius: 0.5rem; padding: 1.5rem; text-align: center;">
                <div style="font-size: 2rem; font-weight: bold; color: #3a8a9e;"><?php echo $total_components; ?></div>
                <div style="color: #666;">Total Units</div>
            </div>
            <div style="background-color: #f0fff0; border: 2px solid #22c55e; border-radius: 0.5rem; padding: 1.5rem; text-align: center;">
                <div style="font-size: 2rem; font-weight: bold; color: #22c55e;"><?php echo $available_count; ?></div>
                <div style="color: #666;">Available</div>
            </div>
            <div style="background-color: #fff3cd; border: 2px solid #ffc107; border-radius: 0.5rem; padding: 1.5rem; text-align: center;">
                <div style="font-size: 2rem; font-weight: bold; color: #ffc107;"><?php echo $booked_count; ?></div>
                <div style="color: #666;">Booked</div>
            </div>
        </div>
        
        <!-- Add Units Section -->
        <div style="background-color: #e2e2e2; padding: 1.5rem; border-radius: 0.5rem; margin-bottom: 2rem;">
            <h3 style="margin-top: 0;">Add New Units</h3>
            
            <form method="POST" class="form-layout">
                    <h4 style="text-align: left;">Add Units</h4>
                    <input type="hidden" name="action" value="add_bulk">
                    
                    <div>
                        <label class="form-label">How many units? *</label>
                        <input type="number" name="bulk_quantity" class="form-input" min="1" value="1" required>
                    </div>
                    
                    <div>
                        <label class="form-label">Location *</label>
                        <select name="location_code" class="form-input" required>
                            <option value="">Select location</option>
                            <?php 
                            $locations_result->data_seek(0);
                            while ($loc = $locations_result->fetch_assoc()): 
                            ?>
                                <option value="<?php echo htmlspecialchars($loc['location_code']); ?>">
                                    <?php echo htmlspecialchars($loc['location_name']); ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    
                    <div>
                        <label class="form-label">Replacement Cost (£)</label>
                        <input type="number" name="replacement_cost" class="form-input" step="0.01" placeholder="e.g., 49.99">
                    </div>
                    
                    <div>
                        <label class="form-label">Date Purchased</label>
                        <input type="date" name="date_purchased" class="form-input">
                    </div>

                    <div>
                        <label class="form-label">End of Life Date</label>
                        <input type="date" name="end_of_life" class="form-input">
                    </div>
                    
                    <button type="submit" style="width: 100%; margin-top: 1rem;">➕ Add Units</button>
                </form>
        </div>
        
        <!-- Components Table -->
        <h3>All Units (<?php echo $total_components; ?>)</h3>
        
        <?php if ($total_components > 0): ?>
            <div style="overflow-x: auto;">
                <table style="width: 100%; border-collapse: collapse; margin-top: 1rem;">
                    <thead>
                        <tr style="background-color: #e2e2e2; border-bottom: 2px solid #435354;">
                            <th style="padding: 1rem; text-align: left; border: 1px solid #d1d5db;">Code</th>
                            <th style="padding: 1rem; text-align: left; border: 1px solid #d1d5db;">Quality</th>
                            <th style="padding: 1rem; text-align: left; border: 1px solid #d1d5db;">Location</th>
                            <th style="padding: 1rem; text-align: left; border: 1px solid #d1d5db;">Cost</th>
                            <th style="padding: 1rem; text-align: left; border: 1px solid #d1d5db;">Purchased</th>
                            <th style="padding: 1rem; text-align: left; border: 1px solid #d1d5db;">Status</th>
                            <th style="padding: 1rem; text-align: left; border: 1px solid #d1d5db;">Maintenance</th>
                            <th style="padding: 1rem; text-align: center; border: 1px solid #d1d5db;">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($component = $components_result->fetch_assoc()): ?>
                            <tr style="border-bottom: 1px solid #d1d5db;">
                                <td style="padding: 0.75rem 1rem; border: 1px solid #d1d5db; font-family: monospace; font-weight: bold;">
                                    <?php echo htmlspecialchars($component['component_code']); ?>
                                </td>
                                <td style="padding: 0.75rem 1rem; border: 1px solid #d1d5db;">
                                    <?php 
                                    if ($component['item_quality'] === '1') {
                                        echo '<span style="background-color: #22c55e; color: white; padding: 0.25rem 0.75rem; border-radius: 0.25rem; font-size: 0.875rem;">✓ Good</span>';
                                    } elseif ($component['item_quality'] === '0') {
                                        echo '<span style="background-color: #f97316; color: white; padding: 0.25rem 0.75rem; border-radius: 0.25rem; font-size: 0.875rem;">⚠ Damaged</span>';
                                    } else {
                                        echo htmlspecialchars($component['item_quality']);
                                    }
                                    ?>
                                    <?php if ($component['quality_desc']): ?>
                                        <br><small style="color: #666;"><?php echo htmlspecialchars($component['quality_desc']); ?></small>
                                    <?php endif; ?>
                                </td>
                                <td style="padding: 0.75rem 1rem; border: 1px solid #d1d5db;">
                                    <?php echo htmlspecialchars($component['location_name'] ?? $component['item_location_code']); ?>
                                </td>
                                <td style="padding: 0.75rem 1rem; border: 1px solid #d1d5db;">
                                    <?php echo $component['replacement_cost'] ? '£' . number_format($component['replacement_cost'], 2) : '-'; ?>
                                </td>
                                <td style="padding: 0.75rem 1rem; border: 1px solid #d1d5db;">
                                    <?php echo $component['date_purchased'] ? date('M Y', strtotime($component['date_purchased'])) : '-'; ?>
                                </td>
                                <td style="padding: 0.75rem 1rem; border: 1px solid #d1d5db;">
                                    <?php 
                                    if (strtoupper(trim($component['item_location_code'])) === 'RETIRED') {
                                        echo '<span style="background-color: #64748b; color: white; padding: 0.25rem 0.75rem; border-radius: 0.25rem; font-size: 0.875rem;">Retired</span>';
                                    } elseif ($component['active_bookings'] > 0) {
                                        echo '<span style="background-color: #ffc107; color: #000; padding: 0.25rem 0.75rem; border-radius: 0.25rem; font-size: 0.875rem;">📦 Booked</span>';
                                    } else {
                                        echo '<span style="background-color: #22c55e; color: white; padding: 0.25rem 0.75rem; border-radius: 0.25rem; font-size: 0.875rem;">✓ Available</span>';
                                    }
                                    ?>
                                </td>
                                <td style="padding: 0.75rem 1rem; border: 1px solid #d1d5db;">
                                    <?php $component_history = $maintenance_by_component[$component['component_code']] ?? []; ?>
                                    <?php if ($component_history): ?>
                                        <details>
                                            <summary><?php echo count($component_history); ?> event<?php echo count($component_history) === 1 ? '' : 's'; ?></summary>
                                            <?php foreach ($component_history as $history): ?>
                                                <div style="margin-top: 0.5rem; padding-top: 0.5rem; border-top: 1px solid #d1d5db; font-size: 0.8rem;">
                                                    <strong><?php echo htmlspecialchars($history['condition_before']); ?> → <?php echo htmlspecialchars($history['condition_after']); ?></strong><br>
                                                    <small><?php echo htmlspecialchars($history['reported_at']); ?></small><br>
                                                    <?php echo htmlspecialchars($history['issue_description']); ?>
                                                    <?php if ($history['action_taken']): ?><br><?php echo htmlspecialchars($history['action_taken']); ?><?php endif; ?>
                                                </div>
                                            <?php endforeach; ?>
                                        </details>
                                    <?php else: ?>
                                        <span style="color: #666;">No history</span>
                                    <?php endif; ?>
                                </td>
                                <td style="padding: 0.75rem 1rem; border: 1px solid #d1d5db; text-align: center;">
                                    <a href="#edit-<?php echo htmlspecialchars($component['component_code']); ?>" onclick="toggleEdit('<?php echo htmlspecialchars($component['component_code']); ?>')" style="color: #3a8a9e; text-decoration: none; margin-right: 0.5rem;">
                                        ✏️ Edit
                                    </a>
                                    
                                    <form method="POST" style="display: inline;" onsubmit="return confirm('Remove this unit?');">
                                        <input type="hidden" name="action" value="delete_component">
                                        <input type="hidden" name="component_code" value="<?php echo htmlspecialchars($component['component_code']); ?>">
                                        <button type="submit" style="background-color: #ff4444; color: white; border: none; padding: 0.25rem 0.75rem; border-radius: 0.25rem; cursor: pointer; font-size: 0.875rem;">
                                            🗑️ Delete
                                        </button>
                                    </form>
                                    <?php if (strtoupper(trim($component['item_location_code'])) !== 'RETIRED' && $component['active_bookings'] === '0'): ?>
                                        <details style="margin-top: 0.5rem; text-align: left;">
                                            <summary>Maintenance</summary>
                                            <form method="POST" class="form-layout" style="margin-top: 0.5rem;">
                                                <input type="hidden" name="action" value="add_maintenance">
                                                <input type="hidden" name="component_code" value="<?php echo htmlspecialchars($component['component_code']); ?>">
                                                <input class="form-input" name="issue_description" placeholder="Issue or inspection note" required>
                                                <input class="form-input" name="action_taken" placeholder="Action taken">
                                                <select class="form-input" name="condition_after">
                                                    <option>Needs maintenance</option>
                                                    <option>Good</option>
                                                    <option>Damaged</option>
                                                    <option>Retired</option>
                                                </select>
                                                <input class="form-input" type="number" name="maintenance_cost" step="0.01" min="0" placeholder="Cost">
                                                <button type="submit">Save maintenance</button>
                                            </form>
                                            <form method="POST" onsubmit="return confirm('Retire this component? It will remain in the audit history.');">
                                                <input type="hidden" name="action" value="retire_component">
                                                <input type="hidden" name="component_code" value="<?php echo htmlspecialchars($component['component_code']); ?>">
                                                <input class="form-input" name="retirement_reason" placeholder="Retirement reason" required>
                                                <button type="submit" style="background-color: #64748b; color: white;">Retire unit</button>
                                            </form>
                                        </details>
                                    <?php elseif (strtoupper(trim($component['item_location_code'])) === 'RETIRED'): ?>
                                        <form method="POST" style="margin-top: 0.5rem;">
                                            <input type="hidden" name="action" value="restore_component">
                                            <input type="hidden" name="component_code" value="<?php echo htmlspecialchars($component['component_code']); ?>">
                                            <select class="form-input" name="restore_location" required>
                                                <option value="">Restore to...</option>
                                                <?php $locations_result->data_seek(0); while ($loc = $locations_result->fetch_assoc()): ?>
                                                    <?php if (!in_array(strtoupper($loc['location_code']), ['IU', 'RETIRED'], true)): ?>
                                                        <option value="<?php echo htmlspecialchars($loc['location_code']); ?>"><?php echo htmlspecialchars($loc['location_name']); ?></option>
                                                    <?php endif; ?>
                                                <?php endwhile; ?>
                                            </select>
                                            <button type="submit">Restore unit</button>
                                        </form>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            
                            <!-- Edit Row (Hidden) -->
                            <tr id="edit-<?php echo htmlspecialchars($component['component_code']); ?>" style="display: none; background-color: #f9f9f9;">
                                <td colspan="8" style="padding: 1.5rem; border: 1px solid #d1d5db;">
                                    <form method="POST" class="form-layout">
                                        <input type="hidden" name="action" value="update_component">
                                        <input type="hidden" name="component_code" value="<?php echo htmlspecialchars($component['component_code']); ?>">
                                        
                                        <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 1rem;">
                                            <div>
                                                <label class="form-label">Quality</label>
                                                <select name="quality" class="form-input">
                                                    <option value="1" <?php echo $component['item_quality'] === '1' ? 'selected' : ''; ?>>✓ Good</option>
                                                    <option value="0" <?php echo $component['item_quality'] === '0' ? 'selected' : ''; ?>>⚠ Damaged</option>
                                                </select>
                                            </div>
                                            
                                            <div>
                                                <label class="form-label">Location</label>
                                                <select name="location_code" class="form-input" required>
                                                    <?php 
                                                    $locations_result->data_seek(0);
                                                    while ($loc = $locations_result->fetch_assoc()): 
                                                    ?>
                                                        <?php if (strtoupper($loc['location_code']) !== 'RETIRED'): ?>
                                                            <option value="<?php echo htmlspecialchars($loc['location_code']); ?>" <?php echo $loc['location_code'] === $component['item_location_code'] ? 'selected' : ''; ?>>
                                                                <?php echo htmlspecialchars($loc['location_name']); ?>
                                                            </option>
                                                        <?php endif; ?>
                                                    <?php endwhile; ?>
                                                </select>
                                            </div>
                                            
                                            <div>
                                                <label class="form-label">Replacement Cost (£)</label>
                                                <input type="number" name="replacement_cost" class="form-input" step="0.01" value="<?php echo $component['replacement_cost'] ?? ''; ?>">
                                            </div>
                                        </div>
                                        
                                        <div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 1rem; margin-top: 1rem;">
                                            <div>
                                                <label class="form-label">Quality Notes</label>
                                                <textarea name="quality_desc" class="form-input" style="resize: vertical;"><?php echo htmlspecialchars($component['quality_desc']); ?></textarea>
                                            </div>
                                            
                                            <div>
                                                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 0.5rem;">
                                                    <div>
                                                        <label class="form-label">Date Purchased</label>
                                                        <input type="date" name="date_purchased" class="form-input" value="<?php echo $component['date_purchased'] ?? ''; ?>">
                                                    </div>
                                                    
                                                    <div>
                                                        <label class="form-label">End of Life</label>
                                                        <input type="date" name="end_of_life" class="form-input" value="<?php echo $component['end_of_life'] ?? ''; ?>">
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                        
                                        <div style="margin-top: 1rem; display: flex; gap: 1rem;">
                                            <button type="submit" style="flex: 1;">💾 Save Changes</button>
                                            <button type="button" onclick="toggleEdit('<?php echo htmlspecialchars($component['component_code']); ?>')" style="flex: 1; background-color: #999;">✕ Cancel</button>
                                        </div>
                                    </form>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <div style="text-align: center; padding: 2rem; color: #666; background-color: #f9f9f9; border-radius: 0.5rem;">
                <p>No units in stock yet.</p>
                <p>Use the "Add New Units" section above to add some.</p>
            </div>
        <?php endif; ?>
    </div>
</div>

<script>
function toggleEdit(componentCode) {
    const editRow = document.getElementById('edit-' + componentCode);
    if (editRow.style.display === 'none') {
        editRow.style.display = 'table-row';
    } else {
        editRow.style.display = 'none';
    }
}
</script>
