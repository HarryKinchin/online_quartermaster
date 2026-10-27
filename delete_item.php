<?php
session_start();

if (!isset($_SESSION['user_level']) || !in_array($_SESSION['user_level'], [1, 2], true)) {
    http_response_code(403);
    exit('You do not have permission to delete items.');
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
$session_token = $_SESSION['delete_item_token'] ?? '';

if (!is_string($submitted_token) || !is_string($session_token) || $session_token === '' || !hash_equals($session_token, $submitted_token)) {
    $_SESSION['store_delete_feedback'] = [
        'type' => 'error',
        'message' => 'The delete request could not be verified. Please try again.'
    ];
    header('Location: ' . $redirect_url);
    exit();
}

unset($_SESSION['delete_item_token']);

if ($item_code === '') {
    $_SESSION['store_delete_feedback'] = [
        'type' => 'error',
        'message' => 'An item code is required.'
    ];
    header('Location: ' . $redirect_url);
    exit();
}

require_once 'db_conn.php';

$transaction_started = false;
try {
    $conn->begin_transaction();
    $transaction_started = true;

    $stmt_item = $conn->prepare('SELECT item_code FROM items WHERE item_code = ? FOR UPDATE');
    $stmt_item->bind_param('s', $item_code);
    $stmt_item->execute();
    if (!$stmt_item->get_result()->fetch_assoc()) {
        $conn->rollback();
        $transaction_started = false;
        $_SESSION['store_delete_feedback'] = [
            'type' => 'error',
            'message' => 'The item was not found or has already been deleted.'
        ];
        header('Location: ' . $redirect_url);
        exit();
    }

    $stmt_references = $conn->prepare('
        SELECT 1 FROM booking_items WHERE item_code = ?
        UNION ALL
        SELECT 1 FROM booking_item_components WHERE item_code = ?
        LIMIT 1
    ');
    $stmt_references->bind_param('ss', $item_code, $item_code);
    $stmt_references->execute();
    if ($stmt_references->get_result()->fetch_assoc()) {
        $conn->rollback();
        $transaction_started = false;
        $_SESSION['store_delete_feedback'] = [
            'type' => 'error',
            'message' => 'This item cannot be deleted because it is referenced by booking history.'
        ];
        header('Location: ' . $redirect_url);
        exit();
    }

    $stmt_components = $conn->prepare('DELETE FROM components WHERE item_code = ?');
    $stmt_components->bind_param('s', $item_code);
    $stmt_components->execute();

    $stmt_delete = $conn->prepare('DELETE FROM items WHERE item_code = ?');
    $stmt_delete->bind_param('s', $item_code);
    $stmt_delete->execute();
    if ($stmt_delete->affected_rows !== 1) {
        $conn->rollback();
        $transaction_started = false;
        throw new RuntimeException('The item row was not deleted.');
    }

    $conn->commit();
    $transaction_started = false;
    $_SESSION['store_delete_feedback'] = [
        'type' => 'success',
        'message' => 'The item and all its units were deleted.'
    ];
} catch (mysqli_sql_exception $e) {
    if ($transaction_started) {
        $conn->rollback();
    }
    error_log('Item deletion failed for ' . $item_code . ': ' . $e->getMessage());
    $_SESSION['store_delete_feedback'] = [
        'type' => 'error',
        'message' => 'The item could not be deleted because of a database error.'
    ];
} catch (RuntimeException $e) {
    if ($transaction_started) {
        $conn->rollback();
    }
    error_log('Item deletion failed for ' . $item_code . ': ' . $e->getMessage());
    $_SESSION['store_delete_feedback'] = [
        'type' => 'error',
        'message' => 'The item could not be deleted. Please try again.'
    ];
}

header('Location: ' . $redirect_url);
exit();
