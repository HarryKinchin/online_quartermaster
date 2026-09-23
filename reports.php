<?php
$search = trim($_GET['q'] ?? '');
$category_filter = trim($_GET['category'] ?? '');
$location_filter = trim($_GET['location'] ?? '');
$quality_filter = trim($_GET['quality'] ?? '');
$sort = $_GET['sort'] ?? 'item';
$warning_filter = $_GET['warning'] ?? '';
$today = date('Y-m-d');
$warning_date = date('Y-m-d', strtotime('+90 days'));

$where = [];
$params = [];
$types = '';
if ($search !== '') {
    $where[] = '(components.item_code LIKE ? OR items.item_name LIKE ? OR components.component_code LIKE ?)';
    $search_like = '%' . $search . '%';
    $params[] = $search_like;
    $params[] = $search_like;
    $params[] = $search_like;
    $types .= 'sss';
}
if ($category_filter !== '') {
    $where[] = 'categories.category_code = ?';
    $params[] = $category_filter;
    $types .= 's';
}
if ($location_filter !== '') {
    $where[] = 'components.item_location_code = ?';
    $params[] = $location_filter;
    $types .= 's';
}
if ($quality_filter !== '') {
    $where[] = 'components.item_quality = ?';
    $params[] = $quality_filter;
    $types .= 's';
}
$where_sql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

$sort_options = [
    'item' => 'items.item_name ASC, components.component_code ASC',
    'component' => 'components.component_code ASC',
    'location' => 'components.item_location_code ASC, items.item_name ASC',
    'condition' => 'components.item_quality ASC, items.item_name ASC',
    'end_of_life' => 'components.end_of_life IS NULL, components.end_of_life ASC, items.item_name ASC',
    'cost_desc' => 'components.replacement_cost DESC, items.item_name ASC'
];
$order_by = $sort_options[$sort] ?? $sort_options['item'];

$stmt_components = $conn->prepare("\n    SELECT components.component_code, components.item_code, items.item_name,\n           categories.category_name, components.item_quality,\n           components.quality_desc, components.return_note,\n           components.replacement_cost, components.date_purchased,\n           components.end_of_life, components.item_location_code,\n           locations.location_name\n    FROM components\n    LEFT JOIN items ON components.item_code = items.item_code\n    LEFT JOIN categories ON items.category_code = categories.category_code\n    LEFT JOIN locations ON components.item_location_code = locations.location_code\n    {$where_sql}\n    ORDER BY {$order_by}\n");
if ($types !== '') {
    $bind_params = [$types];
    foreach ($params as $key => $value) {
        $bind_params[] = &$params[$key];
    }
    call_user_func_array([$stmt_components, 'bind_param'], $bind_params);
}
$stmt_components->execute();
$components = $stmt_components->get_result()->fetch_all(MYSQLI_ASSOC);

$filtered_components = [];
$item_summary = [];
$total_value = 0;
$available_units = 0;
$in_use_units = 0;
$maintenance_units = 0;
$warning_units = 0;

foreach ($components as $component) {
    $is_in_use = strtoupper(trim($component['item_location_code'])) === 'IU';
    $is_retired = strtoupper(trim($component['item_location_code'])) === 'RETIRED';
    $is_damaged = $component['item_quality'] === '0';
    $is_expired = !empty($component['end_of_life']) && $component['end_of_life'] < $today;
    $is_due_soon = !empty($component['end_of_life']) && !$is_expired && $component['end_of_life'] <= $warning_date;
    $has_maintenance = $is_damaged || trim((string) $component['quality_desc']) !== '' || trim((string) $component['return_note']) !== '';
    $warnings = [];
    if ($is_expired) $warnings[] = 'End of life passed';
    elseif ($is_due_soon) $warnings[] = 'End of life within 90 days';
    if ($has_maintenance) $warnings[] = $is_damaged ? 'Needs attention' : 'Maintenance note';

    if ($warning_filter === 'expired' && !$is_expired) continue;
    if ($warning_filter === 'due' && !$is_due_soon) continue;
    if (in_array($warning_filter, ['damaged', 'maintenance'], true) && !$has_maintenance) continue;
    if ($warning_filter === 'any' && !$warnings) continue;

    $component['_is_in_use'] = $is_in_use;
    $component['_is_damaged'] = $is_damaged;
    $component['_warnings'] = $warnings;
    $filtered_components[] = $component;

    $item_code = $component['item_code'];
    if (!isset($item_summary[$item_code])) {
        $item_summary[$item_code] = [
            'item_name' => $component['item_name'] ?? 'Unknown item',
            'category_name' => $component['category_name'] ?? 'Uncategorised',
            'total' => 0,
            'available' => 0,
            'in_use' => 0,
            'attention' => 0
        ];
    }
    $item_summary[$item_code]['total']++;
    if ($is_in_use) {
        $in_use_units++;
        $item_summary[$item_code]['in_use']++;
    } elseif (!$is_retired) {
        $available_units++;
        $item_summary[$item_code]['available']++;
    }
    if ($has_maintenance) {
        $maintenance_units++;
        $item_summary[$item_code]['attention']++;
    }
    if ($warnings) $warning_units++;
    if ($component['replacement_cost'] !== null) {
        $total_value += (float) $component['replacement_cost'];
    }
}

usort($item_summary, function ($left, $right) {
    return strcasecmp($left['item_name'], $right['item_name']);
});

$stmt_categories = $conn->prepare("SELECT category_code, category_name FROM categories ORDER BY category_name");
$stmt_categories->execute();
$categories = $stmt_categories->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt_locations = $conn->prepare("SELECT location_code, location_name FROM locations ORDER BY location_name");
$stmt_locations->execute();
$locations = $stmt_locations->get_result()->fetch_all(MYSQLI_ASSOC);
$qualities = [
    ['item_quality' => '1', 'label' => 'Good'],
    ['item_quality' => '0', 'label' => 'Needs attention']
];
$report_query = http_build_query(array_filter([
    'q' => $search,
    'category' => $category_filter,
    'location' => $location_filter,
    'quality' => $quality_filter,
    'sort' => $sort
]));
?>

<div class="main">
    <div class="reports-container">
        <div class="reports-header">
            <div>
                <h1>Physical Inventory Report</h1>
                <p>One row represents one physical component. Location shows whether a unit is stored or currently in use.</p>
            </div>
            <div class="reports-summary">
                <div>
                    <span class="reports-summary-label">Units shown</span>
                    <strong><?php echo count($filtered_components); ?></strong>
                </div>
                <div>
                    <span class="reports-summary-label">Available</span>
                    <strong><?php echo $available_units; ?></strong>
                </div>
                <div>
                    <span class="reports-summary-label">In use</span>
                    <strong><?php echo $in_use_units; ?></strong>
                </div>
                <div>
                    <span class="reports-summary-label">Attention</span>
                    <strong><?php echo $maintenance_units; ?></strong>
                </div>
            </div>
        </div>

        <div class="reports-unit-note">
            <strong>Inventory model:</strong> every component code identifies one physical unit. Units at <strong>In Use</strong> are assigned to a collected booking; other locations are storage locations.
        </div>

        <form method="get" class="reports-filters">
            <input type="hidden" name="page" value="reports">
            <div>
                <label class="form-label" for="report-search">Search</label>
                <input id="report-search" name="q" type="search" class="form-input" value="<?php echo htmlspecialchars($search); ?>" placeholder="Item or component code">
            </div>
            <div>
                <label class="form-label" for="report-category">Category</label>
                <select id="report-category" name="category" class="form-input">
                    <option value="">All categories</option>
                    <?php foreach ($categories as $category): ?>
                        <option value="<?php echo htmlspecialchars($category['category_code']); ?>" <?php echo $category_filter === $category['category_code'] ? 'selected' : ''; ?>><?php echo htmlspecialchars($category['category_name']); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label class="form-label" for="report-location">Location</label>
                <select id="report-location" name="location" class="form-input">
                    <option value="">All locations</option>
                    <?php foreach ($locations as $location): ?>
                        <option value="<?php echo htmlspecialchars($location['location_code']); ?>" <?php echo $location_filter === $location['location_code'] ? 'selected' : ''; ?>><?php echo htmlspecialchars($location['location_name']); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label class="form-label" for="report-quality">Condition</label>
                <select id="report-quality" name="quality" class="form-input">
                    <option value="">All conditions</option>
                    <?php foreach ($qualities as $quality): ?>
                        <option value="<?php echo htmlspecialchars($quality['item_quality']); ?>" <?php echo $quality_filter === $quality['item_quality'] ? 'selected' : ''; ?>><?php echo htmlspecialchars($quality['label']); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label class="form-label" for="report-warning">Flagged units</label>
                <select id="report-warning" name="warning" class="form-input">
                    <option value="">All units</option>
                    <option value="any" <?php echo $warning_filter === 'any' ? 'selected' : ''; ?>>Any warning</option>
                    <option value="damaged" <?php echo $warning_filter === 'damaged' ? 'selected' : ''; ?>>Needs attention</option>
                    <option value="expired" <?php echo $warning_filter === 'expired' ? 'selected' : ''; ?>>Past end of life</option>
                    <option value="due" <?php echo $warning_filter === 'due' ? 'selected' : ''; ?>>End of life within 90 days</option>
                </select>
            </div>
            <div>
                <label class="form-label" for="report-sort">Sort by</label>
                <select id="report-sort" name="sort" class="form-input">
                    <option value="item" <?php echo $sort === 'item' ? 'selected' : ''; ?>>Item and code</option>
                    <option value="component" <?php echo $sort === 'component' ? 'selected' : ''; ?>>Component code</option>
                    <option value="location" <?php echo $sort === 'location' ? 'selected' : ''; ?>>Location</option>
                    <option value="condition" <?php echo $sort === 'condition' ? 'selected' : ''; ?>>Condition</option>
                    <option value="end_of_life" <?php echo $sort === 'end_of_life' ? 'selected' : ''; ?>>End of life</option>
                    <option value="cost_desc" <?php echo $sort === 'cost_desc' ? 'selected' : ''; ?>>Replacement cost</option>
                </select>
            </div>
            <button type="submit" class="submit-button">Apply filters</button>
            <a class="edit-button reports-action" href="index.php?page=reports">Clear</a>
        </form>

        <div class="reports-toolbar">
            <span><?php echo count($item_summary); ?> item types, <?php echo count($filtered_components); ?> physical units shown</span>
            <a class="edit-button reports-action" href="export_components.php<?php echo $report_query ? '?' . htmlspecialchars($report_query) : ''; ?>">Export CSV</a>
        </div>

        <section class="reports-section">
            <div class="reports-section-header">
                <h2>Item summary</h2>
                <span>Counts are physical component rows</span>
            </div>
            <?php if ($item_summary): ?>
                <div class="reports-summary-grid">
                    <?php foreach ($item_summary as $summary): ?>
                        <article class="reports-item-summary">
                            <div>
                                <strong><?php echo htmlspecialchars($summary['item_name']); ?></strong>
                                <small><?php echo htmlspecialchars($summary['category_name']); ?></small>
                            </div>
                            <div class="reports-item-metrics">
                                <span><b><?php echo $summary['total']; ?></b> total</span>
                                <span><b><?php echo $summary['available']; ?></b> stored</span>
                                <span><b><?php echo $summary['in_use']; ?></b> in use</span>
                                <?php if ($summary['attention'] > 0): ?><span class="reports-attention-text"><b><?php echo $summary['attention']; ?></b> attention</span><?php endif; ?>
                            </div>
                        </article>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <p class="reports-empty">No item summaries match the selected filters.</p>
            <?php endif; ?>
        </section>

        <section class="reports-section">
            <div class="reports-section-header">
                <h2>Physical unit details</h2>
                <span><?php echo $warning_units; ?> flagged unit<?php echo $warning_units === 1 ? '' : 's'; ?></span>
            </div>
            <?php if ($filtered_components): ?>
                <div class="reports-table-wrap">
                    <table class="reports-table">
                        <caption class="sr-only">Physical component inventory details</caption>
                        <thead>
                            <tr>
                                <th scope="col">Component code</th>
                                <th scope="col">Item</th>
                                <th scope="col">Status</th>
                                <th scope="col">Condition</th>
                                <th scope="col">Location</th>
                                <th scope="col">Purchased</th>
                                <th scope="col">End of life</th>
                                <th scope="col">Replacement cost</th>
                                <th scope="col">Notes and warnings</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($filtered_components as $component): ?>
                                <?php $row_class = $component['_warnings'] ? ' class="reports-warning-row"' : ''; ?>
                                <tr<?php echo $row_class; ?>>
                                    <td><strong><?php echo htmlspecialchars($component['component_code']); ?></strong></td>
                                    <td>
                                        <strong><?php echo htmlspecialchars($component['item_name'] ?? 'Unknown item'); ?></strong><br>
                                        <small><?php echo htmlspecialchars($component['item_code']); ?></small>
                                    </td>
                                    <td>
                                        <?php if ($component['_is_in_use']): ?>
                                            <span class="reports-status reports-status-use">In use</span>
                                        <?php else: ?>
                                            <span class="reports-status reports-status-stored">Stored</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($component['_is_damaged']): ?>
                                            <span class="reports-status reports-status-attention">Needs attention</span>
                                        <?php else: ?>
                                            <span class="reports-status reports-status-good">Good</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo htmlspecialchars($component['location_name'] ?? $component['item_location_code']); ?></td>
                                    <td><?php echo htmlspecialchars($component['date_purchased'] ?: '-'); ?></td>
                                    <td><?php echo htmlspecialchars($component['end_of_life'] ?: '-'); ?></td>
                                    <td><?php echo $component['replacement_cost'] !== null ? '&pound;' . htmlspecialchars(number_format((float) $component['replacement_cost'], 2)) : '-'; ?></td>
                                    <td>
                                        <?php if ($component['_warnings']): ?><strong><?php echo htmlspecialchars(implode(', ', $component['_warnings'])); ?></strong><br><?php endif; ?>
                                        <?php echo htmlspecialchars($component['quality_desc'] ?: $component['return_note'] ?: '-'); ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <p class="reports-empty">No physical units match the selected filters.</p>
            <?php endif; ?>
        </section>
    </div>
</div>
