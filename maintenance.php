<?php
session_start();

if (!isset($_SESSION['user_level']) || !in_array($_SESSION['user_level'], [1, 2], true)) {
    header('Location: index.php?page=home');
    exit();
}

require_once 'db_conn.php';

$search = trim($_GET['q'] ?? '');
$state = $_GET['state'] ?? 'open';
if (!in_array($state, ['all', 'open', 'resolved', 'retired'], true)) {
    $state = 'open';
}

$where = [];
$params = [];
$types = '';
if ($search !== '') {
    $where[] = '(ml.component_code LIKE ? OR ml.item_code LIKE ? OR items.item_name LIKE ? OR ml.issue_description LIKE ?)';
    $like = '%' . $search . '%';
    $params = [$like, $like, $like, $like];
    $types = 'ssss';
}
if ($state === 'open') {
    $where[] = 'ml.resolved_at IS NULL AND UPPER(TRIM(components.item_location_code)) <> \'RETIRED\'';
} elseif ($state === 'resolved') {
    $where[] = 'ml.resolved_at IS NOT NULL';
} elseif ($state === 'retired') {
    $where[] = 'UPPER(TRIM(components.item_location_code)) = \'RETIRED\'';
}
$where_sql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

$stmt = $conn->prepare("SELECT ml.log_id, ml.component_code, ml.item_code, items.item_name, ml.reported_at, ml.issue_description, ml.action_taken, ml.condition_before, ml.condition_after, ml.cost, ml.resolved_at, components.item_location_code, locations.location_name FROM item_maintenance_log ml LEFT JOIN components ON components.component_code = ml.component_code LEFT JOIN items ON items.item_code = ml.item_code LEFT JOIN locations ON locations.location_code = components.item_location_code {$where_sql} ORDER BY ml.resolved_at IS NULL DESC, ml.reported_at DESC");
if (!$stmt) {
    http_response_code(500);
    exit('Maintenance history is not available. Run migrate_components.php first.');
}
if ($types !== '') {
    $bind_params = [$types];
    foreach ($params as $key => $value) $bind_params[] = &$params[$key];
    call_user_func_array([$stmt, 'bind_param'], $bind_params);
}
$stmt->execute();
$entries = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

$stmt_counts = $conn->prepare("SELECT COUNT(*) AS total, SUM(resolved_at IS NULL) AS open_count, SUM(resolved_at IS NOT NULL) AS resolved_count FROM item_maintenance_log");
$stmt_counts->execute();
$counts = $stmt_counts->get_result()->fetch_assoc();

$maintenance_cost = 0;
foreach ($entries as $entry) {
    $maintenance_cost += (float) ($entry['cost'] ?? 0);
}
?>

<div class="main">
    <div class="maintenance-page">
        <header class="maintenance-header">
            <div>
                <span class="home-dashboard-kicker">Quartermaster tools</span>
                <h1>Maintenance history</h1>
                <p>Track repairs, inspections, costs, and retired physical components.</p>
            </div>
            <a class="edit-button" href="index.php?page=store_details">Browse stock</a>
        </header>

        <section class="maintenance-summary" aria-label="Maintenance summary">
            <div><span>Total events</span><strong><?php echo (int) ($counts['total'] ?? 0); ?></strong></div>
            <div><span>Open issues</span><strong><?php echo (int) ($counts['open_count'] ?? 0); ?></strong></div>
            <div><span>Resolved</span><strong><?php echo (int) ($counts['resolved_count'] ?? 0); ?></strong></div>
            <div><span>Shown cost</span><strong>&pound;<?php echo number_format($maintenance_cost, 2); ?></strong></div>
        </section>

        <form method="get" class="maintenance-filters">
            <input type="hidden" name="page" value="maintenance">
            <div>
                <label class="form-label" for="maintenance-search">Search</label>
                <input id="maintenance-search" class="form-input" type="search" name="q" value="<?php echo htmlspecialchars($search); ?>" placeholder="Component, item, or issue">
            </div>
            <div>
                <label class="form-label" for="maintenance-state">Show</label>
                <select id="maintenance-state" class="form-input" name="state">
                    <option value="open" <?php echo $state === 'open' ? 'selected' : ''; ?>>Open issues</option>
                    <option value="resolved" <?php echo $state === 'resolved' ? 'selected' : ''; ?>>Resolved events</option>
                    <option value="retired" <?php echo $state === 'retired' ? 'selected' : ''; ?>>Retired components</option>
                    <option value="all" <?php echo $state === 'all' ? 'selected' : ''; ?>>All history</option>
                </select>
            </div>
            <button class="submit-button" type="submit">Apply filters</button>
        </form>

        <?php if ($entries): ?>
            <div class="maintenance-table-wrap">
                <table class="maintenance-table">
                    <thead>
                        <tr>
                            <th>Component</th>
                            <th>Item</th>
                            <th>Reported</th>
                            <th>Issue</th>
                            <th>Action taken</th>
                            <th>Condition</th>
                            <th>Location</th>
                            <th>Cost</th>
                            <th>State</th>
                            <th>View</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($entries as $entry): ?>
                            <tr>
                                <td><strong><?php echo htmlspecialchars($entry['component_code']); ?></strong></td>
                                <td><?php echo htmlspecialchars($entry['item_name'] ?? $entry['item_code']); ?><br><small><?php echo htmlspecialchars($entry['item_code']); ?></small></td>
                                <td><?php echo htmlspecialchars($entry['reported_at']); ?></td>
                                <td><?php echo nl2br(htmlspecialchars($entry['issue_description'] ?? '-')); ?></td>
                                <td><?php echo nl2br(htmlspecialchars($entry['action_taken'] ?? '-')); ?></td>
                                <td><?php echo htmlspecialchars($entry['condition_before']); ?> &rarr; <?php echo htmlspecialchars($entry['condition_after']); ?></td>
                                <td><?php echo htmlspecialchars($entry['location_name'] ?? $entry['item_location_code'] ?? '-'); ?></td>
                                <td>&pound;<?php echo number_format((float) ($entry['cost'] ?? 0), 2); ?></td>
                                <td>
                                    <?php if (strtoupper(trim((string) $entry['item_location_code'])) === 'RETIRED'): ?>
                                        <span class="maintenance-badge maintenance-badge-retired">Retired</span>
                                    <?php elseif ($entry['resolved_at']): ?>
                                        <span class="maintenance-badge maintenance-badge-resolved">Resolved</span>
                                    <?php else: ?>
                                        <span class="maintenance-badge maintenance-badge-open">Open</span>
                                    <?php endif; ?>
                                </td>
                                <td><a class="maintenance-view-link" href="index.php?page=manage_stock&amp;item_code=<?php echo urlencode($entry['item_code']); ?>">Stock</a></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <p class="reports-empty">No maintenance history matches these filters.</p>
        <?php endif; ?>
    </div>
</div>
