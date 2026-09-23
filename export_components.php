<?php
session_start();
require_once 'db_conn.php';

if (!in_array((int) ($_SESSION['user_level'] ?? 0), [1, 2], true)) {
    http_response_code(403);
    exit('Unauthorised');
}

$search = trim($_GET['q'] ?? '');
$category = trim($_GET['category'] ?? '');
$location = trim($_GET['location'] ?? '');
$quality = trim($_GET['quality'] ?? '');
$warning = $_GET['warning'] ?? '';
$where = [];
$params = [];
$types = '';
if ($search !== '') {
    $where[] = '(components.item_code LIKE ? OR items.item_name LIKE ? OR components.component_code LIKE ?)';
    $like = '%' . $search . '%';
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
    $types .= 'sss';
}
if ($category !== '') {
    $where[] = 'categories.category_code = ?';
    $params[] = $category;
    $types .= 's';
}
if ($location !== '') {
    $where[] = 'components.item_location_code = ?';
    $params[] = $location;
    $types .= 's';
}
if ($quality !== '') {
    $where[] = 'components.item_quality = ?';
    $params[] = $quality;
    $types .= 's';
}
$where_sql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

$stmt = $conn->prepare("SELECT components.component_code, components.item_code, items.item_name, categories.category_name, components.item_quality, components.quality_desc, components.return_note, components.replacement_cost, components.date_purchased, components.end_of_life, components.item_location_code, locations.location_name FROM components LEFT JOIN items ON components.item_code = items.item_code LEFT JOIN categories ON items.category_code = categories.category_code LEFT JOIN locations ON components.item_location_code = locations.location_code {$where_sql} ORDER BY categories.category_name, items.item_name, components.component_code");
if ($types !== '') {
    $bind_params = [$types];
    foreach ($params as $key => $value) $bind_params[] = &$params[$key];
    call_user_func_array([$stmt, 'bind_param'], $bind_params);
}
$stmt->execute();
$rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$today = date('Y-m-d');
$warning_date = date('Y-m-d', strtotime('+90 days'));

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="physical-component-report.csv"');
$output = fopen('php://output', 'w');
fputcsv($output, ['Component code', 'Item', 'Category', 'Status', 'Condition', 'Location', 'Purchase date', 'End of life', 'Replacement cost', 'Notes', 'Warnings']);
foreach ($rows as $row) {
    $is_in_use = strtoupper(trim($row['item_location_code'])) === 'IU';
    $is_damaged = $row['item_quality'] === '0';
    $warnings = [];
    if (!empty($row['end_of_life']) && $row['end_of_life'] < $today) {
        $warnings[] = 'End of life passed';
    } elseif (!empty($row['end_of_life']) && $row['end_of_life'] <= $warning_date) {
        $warnings[] = 'End of life within 90 days';
    }
    $quality_description = trim((string) $row['quality_desc']);
    $return_note = trim((string) $row['return_note']);
    if ($is_damaged || ($quality_description !== '' && strtolower($quality_description) !== 'good') || $return_note !== '') {
        $warnings[] = 'Needs maintenance';
    }
    if ($warning === 'expired' && !in_array('End of life passed', $warnings, true)) continue;
    if ($warning === 'due' && !in_array('End of life within 90 days', $warnings, true)) continue;
    if (in_array($warning, ['damaged', 'maintenance'], true) && !in_array('Needs maintenance', $warnings, true)) continue;
    if ($warning === 'any' && !$warnings) continue;

    fputcsv($output, [
        $row['component_code'],
        $row['item_name'] ?? 'Unknown item',
        $row['category_name'] ?? 'Uncategorised',
        $is_in_use ? 'In use' : 'Stored',
        $is_damaged ? 'Needs attention' : 'Good',
        $row['location_name'] ?? $row['item_location_code'],
        $row['date_purchased'] ?? '',
        $row['end_of_life'] ?? '',
        $row['replacement_cost'] ?? '',
        $quality_description ?: ($return_note ?: ''),
        implode(', ', $warnings)
    ]);
}
fclose($output);
