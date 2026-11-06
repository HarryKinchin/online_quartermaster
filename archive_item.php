<?php
session_start();

if (!isset($_SESSION['user_level']) || !in_array($_SESSION['user_level'], [1, 2], true)) {
    http_response_code(403);
    exit('You do not have permission to archive items.');
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    header('Allow: POST');
    exit('Method not allowed.');
}

$search = trim($_POST['q'] ?? '');
$category = trim($_POST['category'] ?? '');
$redirect_url = 'index.php?' . http_build_query([
    'page' => 'store_details',
    'q' => $search,
    'category' => $category
]);
$item_code = trim($_POST['item_code'] ?? '');
$submitted_token = $_POST['csrf_token'] ?? '';
$session_token = $_SESSION['archive_item_token'] ?? '';

if (!is_string($submitted_token) || !is_string($session_token) || $session_token === '' || !hash_equals($session_token, $submitted_token)) {
    $_SESSION['archive_item_feedback'] = [
        'type' => 'error',
        'message' => 'The archive request could not be verified. Please try again.'
    ];
    header('Location: ' . $redirect_url);
    exit();
}

unset($_SESSION['archive_item_token']);

if ($item_code === '') {
    $_SESSION['archive_item_feedback'] = [
        'type' => 'error',
        'message' => 'An item code is required.'
    ];
    header('Location: ' . $redirect_url);
    exit();
}

require_once 'db_conn.php';

try {
    $stmt_archive = $conn->prepare('UPDATE items SET is_archived = 1 WHERE item_code = ? AND is_archived = 0');
    $stmt_archive->bind_param('s', $item_code);
    $stmt_archive->execute();
    if ($stmt_archive->affected_rows !== 1) {
        $stmt_exists = $conn->prepare('SELECT is_archived FROM items WHERE item_code = ?');
        $stmt_exists->bind_param('s', $item_code);
        $stmt_exists->execute();
        $item = $stmt_exists->get_result()->fetch_assoc();
        $_SESSION['archive_item_feedback'] = [
            'type' => 'error',
            'message' => $item ? 'This item has already been archived.' : 'The item was not found.'
        ];
        header('Location: ' . $redirect_url);
        exit();
    }
    $_SESSION['archive_item_feedback'] = [
        'type' => 'success',
        'message' => 'The item was archived and removed from the store. Its units and maintenance history were retained.'
    ];
} catch (mysqli_sql_exception $e) {
    error_log('Item archiving failed for ' . $item_code . ': ' . $e->getMessage());
    $_SESSION['archive_item_feedback'] = [
        'type' => 'error',
        'message' => 'The item could not be archived because of a database error.'
    ];
}

header('Location: ' . $redirect_url);
exit();
