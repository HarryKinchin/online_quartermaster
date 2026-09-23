<?php
session_start();
require_once 'db_conn.php';

if (!in_array((int) ($_SESSION['user_level'] ?? 0), [1, 2], true)) {
    http_response_code(403);
    exit('Unauthorised');
}

$report = $_GET['report'] ?? '';
$queries = [
    'overdue' => [
        'name' => 'overdue-returns.csv',
        'header' => ['Event', 'Booked by', 'Item', 'Quantity', 'Return date'],
        'sql' => "SELECT bookings.event_name, CONCAT(users.first_name, ' ', users.last_name), items.item_name, booking_items.quantity_needed, bookings.return_date FROM booking_items INNER JOIN bookings ON booking_items.booking_id = bookings.booking_id LEFT JOIN users ON bookings.user_id = users.user_id LEFT JOIN items ON booking_items.item_code = items.item_code WHERE bookings.booking_status_id = 3 AND bookings.return_date < CURDATE() ORDER BY bookings.return_date"
    ],
    'damaged' => [
        'name' => 'damaged-components.csv',
        'header' => ['Component', 'Item', 'Location', 'Quality', 'Quality notes', 'Return note'],
        'sql' => "SELECT components.component_code, items.item_name, components.item_location_code, components.item_quality, components.quality_desc, components.return_note FROM components LEFT JOIN items ON components.item_code = items.item_code WHERE (LOWER(TRIM(COALESCE(components.quality_desc, ''))) <> '' AND LOWER(TRIM(components.quality_desc)) <> 'good') OR TRIM(COALESCE(components.return_note, '')) <> '' ORDER BY items.item_name"
    ],
    'locations' => [
        'name' => 'location-summary.csv',
        'header' => ['Location', 'Total quantity', 'In use quantity'],
        'sql' => "SELECT locations.location_name, COUNT(*), COUNT(CASE WHEN components.item_location_code = 'IU' THEN 1 END) FROM components LEFT JOIN locations ON components.item_location_code = locations.location_code GROUP BY locations.location_name ORDER BY locations.location_name"
    ]
];
if (!isset($queries[$report])) {
    http_response_code(400);
    exit('Invalid report');
}

$result = $conn->query($queries[$report]['sql']);
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $queries[$report]['name'] . '"');
$output = fopen('php://output', 'w');
fputcsv($output, $queries[$report]['header']);
while ($row = $result->fetch_row()) fputcsv($output, $row);
fclose($output);
