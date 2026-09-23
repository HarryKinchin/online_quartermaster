<div class="main">
    <div class="form-container">
        <h1 class="form-title">Store Details</h1>

        <form method="get" action="index.php" class="form-layout">
            <input type="hidden" name="page" value="store_details">
            <div>
                <label for="q" class="form-label">Search items</label>
                <input type="search" id="q" name="q" class="form-input" value="<?php echo isset($_GET['q']) ? htmlspecialchars($_GET['q']) : ''; ?>">
            </div>
            <button type="submit" class="submit-button">Search</button>
        </form>

        <?php if (isset($_SESSION['user_level']) && in_array($_SESSION['user_level'], [1, 2], true)): ?>
            <div class="store-card-actions">
                <a class="edit-button" href="index.php?page=add_item">
                    Add new item
                </a>
            </div>
        <?php endif; ?>

        <?php
        require_once 'db_conn.php';
        $search = isset($_GET['q']) ? trim($_GET['q']) : '';

        $sql = "
            SELECT i.item_code, i.item_name, i.item_desc, i.image_1, c.category_name
            FROM items i
            LEFT JOIN categories c ON i.category_code = c.category_code
            WHERE i.item_code LIKE ?
            OR i.item_name LIKE ?
            OR i.item_desc LIKE ?
            OR c.category_name LIKE ?
        ";

        // Dynamically change the sorting strategy and parameter count based on search input
        if ($search !== '') {
            // Strategy 1: User is searching -> Description is now the least important match
            $sql .= "
                ORDER BY
                    (i.item_name LIKE ?) DESC,
                    (i.item_code LIKE ?) DESC,
                    (c.category_name LIKE ?) DESC,
                    (i.item_desc LIKE ?) DESC,
                    i.item_name ASC
            ";

            $stmt_equipment = $conn->prepare($sql);
            $like = '%' . $search . '%';
            $stmt_equipment->bind_param(
                "ssssssss",
                $like, $like, $like, $like,
                $like, $like, $like, $like
            );
        } else {
            // Strategy 2: Search is empty -> Group by Category alphabetically, then Item Name alphabetically
            $sql .= "
                ORDER BY
                    c.category_name ASC,
                    i.item_name ASC
            ";

            $stmt_equipment = $conn->prepare($sql);
            $like = '%';
            $stmt_equipment->bind_param(
                "ssss",
                $like, $like, $like, $like
            );
        }

        // Fetch total quantities and subtract quantities from active bookings
        $stmt_quantities = $conn->prepare("
            SELECT component_totals.item_code,
                   component_totals.total_qty,
                   GREATEST(component_totals.total_qty - COALESCE(booked_totals.booked_qty, 0), 0) AS available_qty
            FROM (
                SELECT item_code, COUNT(*) AS total_qty
                FROM components
                WHERE UPPER(TRIM(item_location_code)) <> 'RETIRED'
                GROUP BY item_code
            ) AS component_totals
            LEFT JOIN (
                SELECT booking_items.item_code, SUM(booking_items.quantity_needed) AS booked_qty
                FROM booking_items
                INNER JOIN bookings ON booking_items.booking_id = bookings.booking_id
                WHERE bookings.booking_status_id NOT IN (1, 4, 5)
                GROUP BY booking_items.item_code
            ) AS booked_totals ON component_totals.item_code = booked_totals.item_code
        ");
        $stmt_quantities->execute();
        $quantities_result = $stmt_quantities->get_result();
        $item_quantities = [];
        while ($qty_row = $quantities_result->fetch_assoc()) {
            $item_quantities[$qty_row['item_code']] = [
                'total' => (int) $qty_row['total_qty'],
                'available' => (int) $qty_row['available_qty']
            ];
        }

        $stmt_equipment->execute();
        $equipment_result = $stmt_equipment->get_result();

        if ($search !== '') {
            echo '<p>Showing results for: <strong>' . htmlspecialchars($search) . '</strong></p>';
        }

        if ($equipment_result->num_rows > 0): ?>
            <div class="store-grid">
                <?php while ($item = $equipment_result->fetch_assoc()): ?>
                    <article class="store-card">
                        <div class="store-card-image">
                            <?php if (!empty($item['image_1'])): ?>
                                <img src="./static/images/item_images/<?php echo htmlspecialchars($item['image_1']); ?>">
                            <?php else: ?>
                                <img class="store-card-no-image" src="./static/images/not-found.svg">
                            <?php endif; ?>
                        </div>

                        <div class="store-card-content">
                            <div class="store-card-header">
                                <span class="store-card-code"><?php echo 'Item code: ' . htmlspecialchars($item['item_code']); ?></span>
                                <h2><?php echo htmlspecialchars($item['item_name']); ?></h2>
                            </div>

                            <p class="store-card-category"><?php echo htmlspecialchars($item['category_name']); ?></p>

                            <p class="store-card-desc">
                                <?php echo !empty($item['item_desc']) ? htmlspecialchars($item['item_desc']) : 'No description'; ?>
                            </p>

                            <p class="store-card-quantity">
                                <strong>Total:</strong> <?php echo $item_quantities[$item['item_code']]['total'] ?? 0; ?> units<br>
                                <strong>Available:</strong> <?php echo $item_quantities[$item['item_code']]['available'] ?? 0; ?> units
                            </p>
                            <div class="store-card-actions">
                                <?php if (isset($_SESSION['user_level']) && in_array($_SESSION['user_level'], [1, 2], true)): ?>
                                    <a class="edit-button" href="index.php?page=edit_item&item_code=<?php echo urlencode($item['item_code']); ?>" style="margin-right: 0.5rem;">
                                        ✏️ Edit Item
                                    </a>
                                    <a class="edit-button" href="index.php?page=manage_stock&item_code=<?php echo urlencode($item['item_code']); ?>" style="background-color: #3a8a9e; color: white;">
                                        📦 Manage Stock
                                    </a>
                                <?php endif; ?>
                            </div>
                        </div>
                    </article>
                <?php endwhile; ?>
            </div>
        <?php else: ?>
            <p>No items found.</p>
        <?php endif; ?>
    </div>
</div>