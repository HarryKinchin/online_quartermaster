<?php
if (!function_exists('reports_pagination_info')) {
    function reports_pagination_info(int $page, int $page_size, int $total, string $label): string
    {
        if ($total <= 0) {
            return '';
        }
        $start = ($page - 1) * $page_size + 1;
        $end = min($page * $page_size, $total);
        return 'Showing ' . $label . ' ' . $start . '-' . $end . ' of ' . $total;
    }
}
$is_manager = in_array((int) ($_SESSION['user_level'] ?? 0), [1, 2], true);
$search = trim($_GET['q'] ?? '');
$category_filter = trim($_GET['category'] ?? '');
$location_filter = trim($_GET['location'] ?? '');
$quality_filter = trim($_GET['quality'] ?? '');
$date_from = trim($_GET['date_from'] ?? '');
$date_to = trim($_GET['date_to'] ?? '');
$warning_filter = $_GET['warning'] ?? '';
$sort = $_GET['sort'] ?? 'category';
$today = date('Y-m-d');
$warning_date = date('Y-m-d', strtotime('+90 days'));

$where = [];
$params = [];
$types = '';
if ($search !== '') {
    $where[] = '(components.item_code LIKE ? OR items.item_name LIKE ? OR components.component_code LIKE ?)';
    $search_like = '%' . $search . '%';
    array_push($params, $search_like, $search_like, $search_like);
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
$booking_date_sql = '';
$booking_date_params = [];
$booking_date_types = '';
if ($date_from !== '') {
    $booking_date_sql .= ' AND bookings.return_date >= ?';
    $booking_date_params[] = $date_from;
    $booking_date_types .= 's';
}
if ($date_to !== '') {
    $booking_date_sql .= ' AND bookings.return_date <= ?';
    $booking_date_params[] = $date_to;
    $booking_date_types .= 's';
}
$where_sql = $where ? 'WHERE ' . implode(' AND ', $where) : '';
$sort_options = [
    'category' => 'categories.category_name, items.item_name, components.component_code',
    'quantity_desc' => 'item_total_quantity DESC, items.item_name',
    'quantity_asc' => 'item_total_quantity ASC, items.item_name',
    'end_of_life' => 'components.end_of_life IS NULL, components.end_of_life, items.item_name',
    'cost_desc' => 'components.replacement_cost DESC, items.item_name'
];
$order_by = $sort_options[$sort] ?? $sort_options['category'];

$stmt_components = $conn->prepare("
    SELECT components.component_code, components.item_code, items.item_name, items.image_1,
           categories.category_name, components.quantity, components.item_quality,
           components.quality_desc, components.return_note, components.replacement_cost,
           components.date_purchased, components.end_of_life,
           components.item_location_code, locations.location_name,
           COALESCE(item_totals.total_quantity, 0) AS item_total_quantity,
           COALESCE(booked_totals.booked_quantity, 0) AS booked_quantity
    FROM components
    LEFT JOIN items ON components.item_code = items.item_code
    LEFT JOIN categories ON items.category_code = categories.category_code
    LEFT JOIN locations ON components.item_location_code = locations.location_code
    LEFT JOIN (SELECT item_code, COUNT(*) AS total_quantity FROM components WHERE UPPER(TRIM(item_location_code)) <> 'RETIRED' GROUP BY item_code) item_totals
        ON components.item_code = item_totals.item_code
    LEFT JOIN (
        SELECT booking_items.item_code, SUM(booking_items.quantity_needed) AS booked_quantity
        FROM booking_items
        INNER JOIN bookings ON booking_items.booking_id = bookings.booking_id
        WHERE bookings.booking_status_id NOT IN (1, 4, 5)
        GROUP BY booking_items.item_code
    ) booked_totals ON components.item_code = booked_totals.item_code
    {$where_sql}
    ORDER BY {$order_by}
");
if ($types !== '') {
    $bind_params = [$types];
    foreach ($params as $key => $value) $bind_params[] = &$params[$key];
    call_user_func_array([$stmt_components, 'bind_param'], $bind_params);
}
$stmt_components->execute();
$components = $stmt_components->get_result()->fetch_all(MYSQLI_ASSOC);

foreach ($components as $key => $component) {
    $available = max((int) $component['item_total_quantity'] - (int) $component['booked_quantity'], 0);
    $warnings = [];
    if (!empty($component['end_of_life']) && $component['end_of_life'] < $today) $warnings[] = 'expired';
    elseif (!empty($component['end_of_life']) && $component['end_of_life'] <= $warning_date) $warnings[] = 'due';
    $quality_description = strtolower(trim((string) $component['quality_desc']));
    if (($quality_description !== '' && $quality_description !== 'good') || trim((string) $component['return_note']) !== '') $warnings[] = 'maintenance';
    $components[$key]['_warnings'] = $warnings;
}
$components = array_values(array_filter($components, function ($component) use ($warning_filter) {
    if ($warning_filter === 'any') return count($component['_warnings']) > 0;
    if ($warning_filter === 'expired') return in_array('expired', $component['_warnings'], true);
    if ($warning_filter === 'due') return in_array('due', $component['_warnings'], true);
    if ($warning_filter === 'damaged') return in_array('maintenance', $component['_warnings'], true);
    if ($warning_filter === 'maintenance') return in_array('maintenance', $component['_warnings'], true);
    return true;}));

// If print view parameter is active, fetch all rows without pagination limit
$is_print_mode = isset($_GET['print']) && $_GET['print'] === '1';
$print_section = in_array($_GET['section'] ?? '', ['item_summary', 'components'], true) ? $_GET['section'] : 'all';

$component_page_size = 25;
$component_page = max(1, (int) ($_GET['component_page'] ?? 1));
$component_page_count = max(1, (int) ceil(count($components) / $component_page_size));
$component_page = min($component_page, $component_page_count);
$component_rows = $is_print_mode ? $components : array_slice($components, ($component_page - 1) * $component_page_size, $component_page_size);

$item_summary = [];
foreach ($components as $component) {
    $item_code = $component['item_code'];
    if (!isset($item_summary[$item_code])) {
        $item_summary[$item_code] = ['item_name' => $component['item_name'] ?? 'Unknown item', 'image_1' => $component['image_1'] ?? '', 'category_name' => $component['category_name'] ?? 'Uncategorised', 'total' => (int) $component['item_total_quantity'], 'booked' => (int) $component['booked_quantity'], 'warnings' => [], 'value' => 0, 'components' => []];
    }
    $item_summary[$item_code]['warnings'] = array_unique(array_merge($item_summary[$item_code]['warnings'], $component['_warnings']));
    $item_summary[$item_code]['value'] += (float) ($component['replacement_cost'] ?? 0);
    $item_summary[$item_code]['components'][] = $component;
}
$item_summary_page_size = 25;
$item_summary_page = max(1, (int) ($_GET['item_summary_page'] ?? 1));
$item_summary_page_count = max(1, (int) ceil(count($item_summary) / $item_summary_page_size));
$item_summary_page = min($item_summary_page, $item_summary_page_count);
$item_summary_rows = $is_print_mode ? $item_summary : array_slice($item_summary, ($item_summary_page - 1) * $item_summary_page_size, $item_summary_page_size, true);

$total_quantity = array_sum(array_column($item_summary, 'total'));
$total_booked = array_sum(array_column($item_summary, 'booked'));
$total_available = 0;
foreach ($item_summary as $summary) $total_available += max($summary['total'] - $summary['booked'], 0);
$location_summary = [];
$category_summary = [];
foreach ($components as $component) {
    $location_name = $component['location_name'] ?? $component['item_location_code'];
    if (!isset($location_summary[$location_name])) $location_summary[$location_name] = ['total' => 0, 'in_use' => 0];
    $location_summary[$location_name]['total']++;
    if (strtoupper(trim($component['item_location_code'])) === 'IU') $location_summary[$location_name]['in_use']++;
    $category_name = $component['category_name'] ?? 'Uncategorised';
    $category_summary[$category_name] = ($category_summary[$category_name] ?? 0) + 1;
}
arsort($category_summary);

$stmt_categories = $conn->prepare('SELECT category_code, category_name FROM categories ORDER BY category_name');
$stmt_categories->execute();
$categories = $stmt_categories->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt_locations = $conn->prepare('SELECT location_code, location_name FROM locations ORDER BY location_name');
$stmt_locations->execute();
$locations = $stmt_locations->get_result()->fetch_all(MYSQLI_ASSOC);
$qualities = [
    ['item_quality' => '1', 'label' => 'Good'],
    ['item_quality' => '0', 'label' => 'Bad']
];

$stmt_in_use = $conn->prepare("SELECT bookings.booking_id, bookings.event_name, bookings.collection_date, bookings.return_date, users.first_name, users.last_name, users.email, booking_items.item_code, items.item_name, booking_items.quantity_needed FROM booking_items INNER JOIN bookings ON booking_items.booking_id = bookings.booking_id LEFT JOIN users ON bookings.user_id = users.user_id LEFT JOIN items ON booking_items.item_code = items.item_code WHERE bookings.booking_status_id = 3 {$booking_date_sql} ORDER BY bookings.return_date, items.item_name");
if ($booking_date_types !== '') {
    $in_use_params = [$booking_date_types];
    foreach ($booking_date_params as $key => $value) $in_use_params[] = &$booking_date_params[$key];
    call_user_func_array([$stmt_in_use, 'bind_param'], $in_use_params);
}
$stmt_in_use->execute();
$in_use_items = $stmt_in_use->get_result()->fetch_all(MYSQLI_ASSOC);
$in_use_page_size = 25;
$in_use_page = max(1, (int) ($_GET['in_use_page'] ?? 1));
$in_use_page_count = max(1, (int) ceil(count($in_use_items) / $in_use_page_size));
$in_use_page = min($in_use_page, $in_use_page_count);
$in_use_rows = $is_print_mode ? $in_use_items : array_slice($in_use_items, ($in_use_page - 1) * $in_use_page_size, $in_use_page_size);
$warning_names = ['expired' => 'End of life passed', 'due' => 'End of life within 90 days', 'maintenance' => 'Needs maintenance'];

$overdue_items = [];
$overdue_rows = [];
$damaged_components = [];
$financial_summary = ['total_value' => 0, 'expired_value' => 0, 'damaged_value' => 0];
$quality_records = ['missing_location' => [], 'missing_dates' => [], 'missing_quantity' => [], 'missing_cost' => []];
$data_quality = ['missing_location' => 0, 'missing_dates' => 0, 'missing_quantity' => 0, 'missing_cost' => 0];
if ($is_manager) {
    $stmt_overdue = $conn->prepare("SELECT bookings.booking_id, bookings.event_name, bookings.return_date, users.first_name, users.last_name, booking_items.item_code, items.item_name, booking_items.quantity_needed FROM booking_items INNER JOIN bookings ON booking_items.booking_id = bookings.booking_id LEFT JOIN users ON bookings.user_id = users.user_id LEFT JOIN items ON booking_items.item_code = items.item_code WHERE bookings.booking_status_id = 3 AND bookings.return_date < CURDATE() {$booking_date_sql} ORDER BY bookings.return_date, items.item_name");
    if ($booking_date_types !== '') {
        $overdue_params = [$booking_date_types];
        foreach ($booking_date_params as $key => $value) $overdue_params[] = &$booking_date_params[$key];
        call_user_func_array([$stmt_overdue, 'bind_param'], $overdue_params);
    }
    $stmt_overdue->execute();
    $overdue_items = $stmt_overdue->get_result()->fetch_all(MYSQLI_ASSOC);
    $overdue_page_size = 25;
    $overdue_page = max(1, (int) ($_GET['overdue_page'] ?? 1));
    $overdue_page_count = max(1, (int) ceil(count($overdue_items) / $overdue_page_size));
    $overdue_page = min($overdue_page, $overdue_page_count);
    $overdue_rows = $is_print_mode ? $overdue_items : array_slice($overdue_items, ($overdue_page - 1) * $overdue_page_size, $overdue_page_size);

    $stmt_damaged = $conn->prepare("SELECT components.component_code, components.item_code, items.item_name, components.item_quality, components.quality_desc, components.return_note, components.item_location_code FROM components LEFT JOIN items ON components.item_code = items.item_code WHERE (LOWER(TRIM(COALESCE(components.quality_desc, ''))) <> '' AND LOWER(TRIM(components.quality_desc)) <> 'good') OR TRIM(COALESCE(components.return_note, '')) <> '' ORDER BY items.item_name, components.component_code");
    $stmt_damaged->execute();
    $damaged_components = $stmt_damaged->get_result()->fetch_all(MYSQLI_ASSOC);

    $stmt_financial = $conn->prepare("SELECT COALESCE(SUM(COALESCE(replacement_cost, 0)), 0) AS total_value, COALESCE(SUM(CASE WHEN end_of_life IS NOT NULL AND end_of_life < CURDATE() THEN COALESCE(replacement_cost, 0) ELSE 0 END), 0) AS expired_value, COALESCE(SUM(CASE WHEN (LOWER(TRIM(COALESCE(quality_desc, ''))) <> '' AND LOWER(TRIM(quality_desc)) <> 'good') OR TRIM(COALESCE(return_note, '')) <> '' THEN COALESCE(replacement_cost, 0) ELSE 0 END), 0) AS damaged_value FROM components");
    $stmt_financial->execute();
    $financial_summary = $stmt_financial->get_result()->fetch_assoc();
}
$stmt_quality = $conn->prepare("SELECT components.component_code, components.item_code, items.item_name, components.quantity, components.item_location_code, locations.location_name, components.date_purchased, components.replacement_cost FROM components LEFT JOIN items ON components.item_code = items.item_code LEFT JOIN locations ON components.item_location_code = locations.location_code ORDER BY components.component_code");
$stmt_quality->execute();
$quality_result = $stmt_quality->get_result();
while ($quality_row = $quality_result->fetch_assoc()) {
    if (trim((string) $quality_row['item_location_code']) === '' || $quality_row['location_name'] === null) $quality_records['missing_location'][] = $quality_row;
    if ($quality_row['date_purchased'] === null) $quality_records['missing_dates'][] = $quality_row;
    if ($quality_row['quantity'] === null || (int) $quality_row['quantity'] !== 1) $quality_records['missing_quantity'][] = $quality_row;
    if ($quality_row['replacement_cost'] === null) $quality_records['missing_cost'][] = $quality_row;
}
foreach ($quality_records as $quality_key => $records) $data_quality[$quality_key] = count($records);
?>

<div class="main<?php echo $is_print_mode && $print_section !== 'all' ? ' reports-print-section-' . htmlspecialchars($print_section) : ''; ?>">
    <div class="reports-container">
        <div class="reports-header"><div><h1>Inventory Reports</h1><p>Summary, warnings, and current equipment use.</p></div><div class="reports-summary"><div><span class="reports-summary-label">Items</span><strong><?php echo count($item_summary); ?></strong></div><div><span class="reports-summary-label">Total</span><strong><?php echo $total_quantity; ?></strong></div><div><span class="reports-summary-label">Available</span><strong><?php echo $total_available; ?></strong></div><div><span class="reports-summary-label">Booked</span><strong><?php echo $total_booked; ?></strong></div></div></div>

        <form method="get" class="reports-filters">
            <input type="hidden" name="page" value="reports">
            <div><label class="form-label" for="report-search">Search</label><input id="report-search" name="q" type="search" class="form-input" value="<?php echo htmlspecialchars($search); ?>" placeholder="Item or component"></div>
            <div><label class="form-label" for="report-category">Category</label><select id="report-category" name="category" class="form-input"><option value="">All categories</option><?php foreach ($categories as $category): ?><option value="<?php echo htmlspecialchars($category['category_code']); ?>" <?php echo $category_filter === $category['category_code'] ? 'selected' : ''; ?>><?php echo htmlspecialchars($category['category_name']); ?></option><?php endforeach; ?></select></div>
            <div><label class="form-label" for="report-location">Location</label><select id="report-location" name="location" class="form-input"><option value="">All locations</option><?php foreach ($locations as $location): ?><option value="<?php echo htmlspecialchars($location['location_code']); ?>" <?php echo $location_filter === $location['location_code'] ? 'selected' : ''; ?>><?php echo htmlspecialchars($location['location_name']); ?></option><?php endforeach; ?></select></div>
            <div><label class="form-label" for="report-quality">Quality</label><select id="report-quality" name="quality" class="form-input"><option value="">All quality levels</option><?php foreach ($qualities as $quality): ?><option value="<?php echo htmlspecialchars($quality['item_quality']); ?>" <?php echo $quality_filter === $quality['item_quality'] ? 'selected' : ''; ?>><?php echo htmlspecialchars($quality['label']); ?></option><?php endforeach; ?></select></div>
            <div><label class="form-label" for="report-warning">Warnings</label><select id="report-warning" name="warning" class="form-input"><option value="">All components</option><option value="any" <?php echo $warning_filter === 'any' ? 'selected' : ''; ?>>Any warning</option><option value="expired" <?php echo $warning_filter === 'expired' ? 'selected' : ''; ?>>Expired</option><option value="due" <?php echo $warning_filter === 'due' ? 'selected' : ''; ?>>End of life within 90 days</option><option value="damaged" <?php echo $warning_filter === 'damaged' ? 'selected' : ''; ?>>Damaged</option><option value="maintenance" <?php echo $warning_filter === 'maintenance' ? 'selected' : ''; ?>>Needs maintenance</option></select></div>
            <div><label class="form-label" for="report-sort">Sort by</label><select id="report-sort" name="sort" class="form-input"><option value="category" <?php echo $sort === 'category' ? 'selected' : ''; ?>>Category and item</option><option value="quantity_desc" <?php echo $sort === 'quantity_desc' ? 'selected' : ''; ?>>Quantity, highest first</option><option value="quantity_asc" <?php echo $sort === 'quantity_asc' ? 'selected' : ''; ?>>Quantity, lowest first</option><option value="end_of_life" <?php echo $sort === 'end_of_life' ? 'selected' : ''; ?>>End of life</option><option value="cost_desc" <?php echo $sort === 'cost_desc' ? 'selected' : ''; ?>>Replacement cost</option></select></div>
            <div><label class="form-label" for="date-from">Return date from</label><input id="date-from" name="date_from" type="date" class="form-input" value="<?php echo htmlspecialchars($date_from); ?>"></div>
            <div><label class="form-label" for="date-to">Return date to</label><input id="date-to" name="date_to" type="date" class="form-input" value="<?php echo htmlspecialchars($date_to); ?>"></div>
            <button type="submit" class="submit-button">Apply filters</button>
            <?php $report_query = http_build_query(array_filter(['q' => $search, 'category' => $category_filter, 'location' => $location_filter, 'quality' => $quality_filter, 'warning' => $warning_filter, 'sort' => $sort, 'date_from' => $date_from, 'date_to' => $date_to], function ($value) { return $value !== ''; })); ?>
            <a class="edit-button reports-action" href="export_components.php<?php echo $report_query ? '?' . htmlspecialchars($report_query) : ''; ?>">Export CSV</a>
            <button type="button" class="edit-button reports-action" onclick="printReportSection('item_summary')">Print item summary</button>
            <button type="button" class="edit-button reports-action" onclick="printReportSection('components')">Print component details</button>
        </form>

        <?php if ($is_manager): ?><div class="reports-export-links"><strong>Additional exports</strong><a class="reports-export-link" href="export_report.php?report=locations">Location summary CSV</a><a class="reports-export-link" href="export_report.php?report=overdue">Overdue returns CSV</a><a class="reports-export-link" href="export_report.php?report=damaged">Damaged components CSV</a></div><?php endif; ?>

        <section class="reports-section" id="item-summary-section"><div class="reports-section-header"><h2>Item Summary</h2><span><?php echo reports_pagination_info($item_summary_page, $item_summary_page_size, count($item_summary), 'items'); ?></span></div><?php if ($item_summary): ?><div class="reports-table-wrap"><table class="reports-table"><thead><tr><th>Item</th><th>Category</th><th>Components</th><th>Total</th><th>Booked</th><th>Available</th><?php if ($is_manager): ?><th>Replacement value</th><?php endif; ?><th>Warnings</th></tr></thead><tbody><?php foreach ($item_summary_rows as $item_code => $summary): ?><tr class="<?php echo $summary['warnings'] ? 'reports-warning-row' : ''; ?>"><td><strong><?php echo htmlspecialchars($summary['item_name']); ?></strong><br><small><?php echo htmlspecialchars($item_code); ?></small></td><td><?php echo htmlspecialchars($summary['category_name']); ?></td><td><?php echo count($summary['components']); ?></td><td class="reports-number"><?php echo $summary['total']; ?></td><td class="reports-number"><?php echo $summary['booked']; ?></td><td class="reports-number"><?php echo max($summary['total'] - $summary['booked'], 0); ?></td><?php if ($is_manager): ?><td class="reports-number">&pound;<?php echo number_format($summary['value'], 2); ?></td><?php endif; ?><td><?php echo htmlspecialchars(implode(', ', array_map(function ($warning) use ($warning_names) { return $warning_names[$warning]; }, $summary['warnings'])) ?: '-'); ?></td></tr><?php endforeach; ?></tbody></table></div><div class="reports-pagination"><?php for ($page = 1; $page <= $item_summary_page_count; $page++): ?><a class="<?php echo $page === $item_summary_page ? 'active' : ''; ?>" href="?page=reports&<?php echo htmlspecialchars($report_query); ?>&item_summary_page=<?php echo $page; ?>"><?php echo $page; ?></a><?php endfor; ?></div><?php else: ?><p class="reports-empty">No items match the selected filters.</p><?php endif; ?></section>

        <section class="reports-section"><div class="reports-section-header"><h2>Currently In Use</h2><span><?php echo count($in_use_items); ?> booked items</span></div><?php if ($in_use_items): ?><div class="reports-table-wrap"><table class="reports-table"><thead><tr><th>Event</th><th>Booked by</th><th>Item</th><th>Quantity</th><th>Collected</th><th>Due back</th><th>Action</th></tr></thead><tbody><?php foreach ($in_use_rows as $in_use): ?><tr><td><?php echo htmlspecialchars($in_use['event_name']); ?></td><td><?php echo htmlspecialchars(trim(($in_use['first_name'] ?? '') . ' ' . ($in_use['last_name'] ?? '')) ?: 'Unknown'); ?><br><small><?php echo htmlspecialchars($in_use['email'] ?? ''); ?></small></td><td><?php echo htmlspecialchars($in_use['item_name'] ?? $in_use['item_code']); ?></td><td class="reports-number"><?php echo (int) $in_use['quantity_needed']; ?></td><td><?php echo htmlspecialchars($in_use['collection_date']); ?></td><td><?php echo htmlspecialchars($in_use['return_date']); ?></td><td><a class="edit-button" href="index.php?page=booking&amp;bookingID=<?php echo (int) $in_use['booking_id']; ?>">View booking</a></td></tr><?php endforeach; ?></tbody></table></div><div class="reports-pagination"><?php for ($page = 1; $page <= $in_use_page_count; $page++): ?><a class="<?php echo $page === $in_use_page ? 'active' : ''; ?>" href="?page=reports&amp;<?php echo htmlspecialchars($report_query); ?>&amp;in_use_page=<?php echo $page; ?>"><?php echo $page; ?></a><?php endfor; ?></div><?php else: ?><p class="reports-empty">No items are currently marked as in use.</p><?php endif; ?></section>

        <?php if ($is_manager): ?>
        <section class="reports-section"><div class="reports-section-header"><h2>Overdue Returns</h2><span><?php echo count($overdue_items); ?> items</span></div><?php if ($overdue_items): ?><div class="reports-table-wrap"><table class="reports-table"><thead><tr><th>Event</th><th>Booked by</th><th>Item</th><th>Quantity</th><th>Return date</th><th>Days overdue</th><th>Action</th></tr></thead><tbody><?php foreach ($overdue_rows as $overdue): ?><tr class="reports-warning-row"><td><?php echo htmlspecialchars($overdue['event_name']); ?></td><td><?php echo htmlspecialchars(trim(($overdue['first_name'] ?? '') . ' ' . ($overdue['last_name'] ?? '')) ?: 'Unknown'); ?></td><td><?php echo htmlspecialchars($overdue['item_name'] ?? $overdue['item_code']); ?></td><td class="reports-number"><?php echo (int) $overdue['quantity_needed']; ?></td><td><?php echo htmlspecialchars($overdue['return_date']); ?></td><td class="reports-number"><?php echo max(1, (int) ((strtotime($today) - strtotime($overdue['return_date'])) / 86400)); ?></td><td><a class="edit-button" href="index.php?page=booking&amp;bookingID=<?php echo (int) $overdue['booking_id']; ?>">View booking</a></td></tr><?php endforeach; ?></tbody></table></div><div class="reports-pagination"><?php for ($page = 1; $page <= $overdue_page_count; $page++): ?><a class="<?php echo $page === $overdue_page ? 'active' : ''; ?>" href="?page=reports&amp;<?php echo htmlspecialchars($report_query); ?>&amp;overdue_page=<?php echo $page; ?>"><?php echo $page; ?></a><?php endfor; ?></div><?php else: ?><p class="reports-empty">No overdue returns.</p><?php endif; ?></section>

        <section class="reports-section"><div class="reports-section-header"><h2>Damaged Components</h2><span><?php echo count($damaged_components); ?> components</span></div><?php if ($damaged_components): ?><div class="reports-table-wrap"><table class="reports-table"><thead><tr><th>Component</th><th>Item</th><th>Location</th><th>Quality</th><th>Quality notes</th><th>Return note</th><th>Action</th></tr></thead><tbody><?php foreach ($damaged_components as $damaged): ?><tr class="reports-warning-row"><td><?php echo htmlspecialchars($damaged['component_code']); ?></td><td><?php echo htmlspecialchars($damaged['item_name'] ?? $damaged['item_code']); ?></td><td><?php echo htmlspecialchars($damaged['item_location_code']); ?></td><td><?php echo htmlspecialchars($damaged['item_quality']); ?></td><td><?php echo htmlspecialchars($damaged['quality_desc'] ?: '-'); ?></td><td><?php echo htmlspecialchars($damaged['return_note'] ?: '-'); ?></td><td><a class="edit-button" href="index.php?page=edit_item&amp;item_code=<?php echo urlencode($damaged['item_code']); ?>">Edit</a> <form class="reports-inline-form" method="post" action="resolve_component.php"><input type="hidden" name="component_code" value="<?php echo htmlspecialchars($damaged['component_code']); ?>"><button type="submit" class="status-button">Resolve</button></form></td></tr><?php endforeach; ?></tbody></table></div><?php else: ?><p class="reports-empty">No damaged components found.</p><?php endif; ?></section>

        <section class="reports-section"><div class="reports-section-header"><h2>Replacement Value</h2><span>Quartermaster view</span></div><div class="reports-summary"><div><span class="reports-summary-label">All components</span><strong>&pound;<?php echo number_format((float) $financial_summary['total_value'], 2); ?></strong></div><div><span class="reports-summary-label">Expired stock</span><strong>&pound;<?php echo number_format((float) $financial_summary['expired_value'], 2); ?></strong></div><div><span class="reports-summary-label">Damaged stock</span><strong>&pound;<?php echo number_format((float) $financial_summary['damaged_value'], 2); ?></strong></div></div></section>

        <section class="reports-section"><div class="reports-section-header"><h2>Data Quality Checks</h2><span>Records needing review</span></div>
            <?php
            $quality_labels = [
                'missing_location' => 'Components with an invalid location',
                'missing_dates' => 'Components missing purchase dates',
                'missing_quantity' => 'Items with a missing quantity',
                'missing_cost' => 'Components missing replacement cost'
            ];
            foreach ($quality_labels as $quality_key => $quality_label):
                $records = $quality_records[$quality_key];
            ?>
                <details class="reports-quality-check" <?php echo $records ? '' : 'aria-disabled="true"'; ?>>
                    <summary><?php echo htmlspecialchars($quality_label); ?><span><?php echo count($records); ?> records</span></summary>
                    <?php if ($records): ?>
                        <div class="reports-table-wrap"><table class="reports-table"><thead><tr>
                            <th>Component</th><th>Item</th><th>Quantity</th>
                            <?php if ($quality_key === 'missing_location'): ?><th>Location code</th><th>Location name</th><?php endif; ?>
                            <?php if ($quality_key === 'missing_dates'): ?><th>Purchase date</th><?php endif; ?>
                            <?php if ($quality_key === 'missing_cost'): ?><th>Replacement cost</th><?php endif; ?><th>Action</th>
                        </tr></thead><tbody>
                            <?php foreach ($records as $record): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($record['component_code']); ?></td>
                                    <td><?php echo htmlspecialchars($record['item_name'] ?? $record['item_code']); ?><br><small><?php echo htmlspecialchars($record['item_code']); ?></small></td>
                                    <td class="reports-number"><?php echo $record['quantity'] === null ? '-' : (int) $record['quantity']; ?></td>
                                    <?php if ($quality_key === 'missing_location'): ?><td><?php echo htmlspecialchars($record['item_location_code']); ?></td><td><?php echo htmlspecialchars($record['location_name'] ?? '-'); ?></td><?php endif; ?>
                                    <?php if ($quality_key === 'missing_dates'): ?><td><?php echo htmlspecialchars($record['date_purchased'] ?: '-'); ?></td><?php endif; ?>
                                    <?php if ($quality_key === 'missing_cost'): ?><td><?php echo $record['replacement_cost'] === null ? '-' : '&pound;' . number_format((float) $record['replacement_cost'], 2); ?></td><?php endif; ?><td><a class="edit-button" href="index.php?page=edit_item&amp;item_code=<?php echo urlencode($record['item_code']); ?>">Edit</a></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody></table></div>
                    <?php else: ?>
                        <p class="reports-empty">No records found.</p>
                    <?php endif; ?>
                </details>
            <?php endforeach; ?>
        </section>

        <section class="reports-section" id="component-details-section"><div class="reports-section-header"><h2>Component Details</h2><span><?php echo reports_pagination_info($component_page, $component_page_size, count($components), 'components'); ?></span></div><?php if ($components): ?><div class="reports-table-wrap"><table class="reports-table"><thead><tr><th>Component</th><th>Item</th><th>Quantity</th><th>Location</th><th>Quality</th><th>Quality description</th><th>Return note</th><th>Purchase date</th><th>End of life</th><th>Replacement cost</th><th>Warnings</th><th>Action</th></tr></thead><tbody><?php foreach ($component_rows as $component): ?><tr class="<?php echo $component['_warnings'] ? 'reports-warning-row' : ''; ?>"><td><?php echo htmlspecialchars($component['component_code']); ?></td><td><?php echo htmlspecialchars($component['item_name'] ?? 'Unknown item'); ?><br><small><?php echo htmlspecialchars($component['item_code']); ?></small></td><td class="reports-number"><?php echo (int) $component['quantity']; ?></td><td><?php echo htmlspecialchars($component['location_name'] ?? $component['item_location_code']); ?></td><td><?php echo (int) $component['item_quality'] === 1 ? 'Good' : 'Needs attention'; ?></td><td><?php echo htmlspecialchars($component['quality_desc'] ?: '-'); ?></td><td><?php echo htmlspecialchars($component['return_note'] ?: '-'); ?></td><td><?php echo htmlspecialchars($component['date_purchased'] ?: '-'); ?></td><td><?php echo htmlspecialchars($component['end_of_life'] ?: '-'); ?></td><td>&pound;<?php echo $component['replacement_cost'] === null ? '0.00' : number_format((float) $component['replacement_cost'], 2); ?></td><td><?php echo htmlspecialchars(implode(', ', array_map(function ($warning) use ($warning_names) { return $warning_names[$warning]; }, $component['_warnings'])) ?: '-'); ?></td><td><a class="edit-button" href="index.php?page=edit_item&amp;item_code=<?php echo urlencode($component['item_code']); ?>">Edit item</a></td></tr><?php endforeach; ?></tbody></table></div><div class="reports-pagination"><?php for ($page = 1; $page <= $component_page_count; $page++): ?><a class="<?php echo $page === $component_page ? 'active' : ''; ?>" href="?page=reports&amp;<?php echo htmlspecialchars($report_query); ?>&amp;component_page=<?php echo $page; ?>"><?php echo $page; ?></a><?php endfor; ?></div><?php else: ?><p class="reports-empty">No component records match the selected filters.</p><?php endif; ?></section>
        <?php endif; ?>
        <?php
        $report_item_images = [];
        foreach ($components as $component) {
            $report_item_images[$component['item_code']] = $component['image_1'] ?? '';
        }
        ?>
        <script>
            function printReportSection(section) {
                const url = new URL(window.location.href);
                url.searchParams.set('print', '1');
                url.searchParams.set('section', section);
                const printWindow = window.open(url.toString(), '_blank');
                printWindow.onload = function() {
                    printWindow.print();
                };
            }

            <?php if ($is_print_mode): ?>
            window.addEventListener('DOMContentLoaded', () => {
                setTimeout(() => { window.print(); }, 500);
            });
            <?php endif; ?>

            const reportItemImages = <?php echo json_encode($report_item_images, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT); ?>;
            const reportFallbackImage = './static/images/not-found.svg';

            document.querySelectorAll('.reports-section').forEach((section) => {
                const heading = section.querySelector('h2');
                const table = section.querySelector('.reports-table');
                if (!heading || !table || !['Item Summary', 'Component Details'].includes(heading.textContent.trim())) return;

                const isComponentTable = heading.textContent.trim() === 'Component Details';
                const headerRow = table.querySelector('thead tr');
                const imageHeader = document.createElement('th');
                imageHeader.textContent = 'Image';
                headerRow.prepend(imageHeader);

                table.querySelectorAll('tbody tr').forEach((row) => {
                    const itemCell = row.cells[isComponentTable ? 1 : 0];
                    const itemCode = itemCell.querySelector('small')?.textContent.trim();
                    const imageName = reportItemImages[itemCode] || '';
                    const imageCell = document.createElement('td');
                    imageCell.className = 'reports-item-image';
                    const image = document.createElement('img');
                    image.src = imageName ? './static/images/item_images/' + encodeURIComponent(imageName) : reportFallbackImage;
                    image.alt = itemCell.textContent.trim();
                    image.onerror = () => {
                        image.onerror = null;
                        image.src = reportFallbackImage;
                    };
                    imageCell.append(image);
                    row.prepend(imageCell);
                });
            });
        </script>
    </div>
</div>