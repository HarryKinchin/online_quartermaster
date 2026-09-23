<?php
session_start();
require_once 'db_conn.php';

if (!in_array((int) ($_SESSION['user_level'] ?? 0), [1, 2], true)) {
    http_response_code(403);
    exit('Unauthorised');
}

$component_code = trim($_POST['component_code'] ?? '');
if ($component_code !== '') {
    $stmt = $conn->prepare("UPDATE components SET item_quality = '1', quality_desc = '', return_note = NULL WHERE component_code = ?");
    $stmt->bind_param('s', $component_code);
    $stmt->execute();
}

header('Location: index.php?page=reports');
exit;
