<?php
session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: index.php?page=login");
    exit;
}

$user_id = $_SESSION['user_id'];

$stmt = $conn->prepare("SELECT first_name, last_name, email, group_name, group_type, role_id FROM users WHERE user_id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();
$stmt->close();

$role_name = "Not Assigned";
if (!empty($user['role_id'])) {
    $stmt_role = $conn->prepare("SELECT role_name FROM user_roles WHERE role_id = ?");
    $stmt_role->bind_param("i", $user['role_id']);
    $stmt_role->execute();
    $result_role = $stmt_role->get_result();
    if ($result_role->num_rows > 0) {
        $role = $result_role->fetch_assoc();
        $role_name = $role['role_name'];
    }
    $stmt_role->close();
}
if (!$user) {
    echo "Error: User data not found.";
    exit;
}

?>

<div class="main">
    <div class="account-container">
        <h2>Your Account Details</h2>
        <div class="user-info">
            <p><strong>Name:</strong> <?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?></p>
            <p><strong>Email:</strong> <?php echo htmlspecialchars($user['email']); ?></p>
            <p><strong>Group:</strong> <?php echo !empty($user['group_name']) ? htmlspecialchars($user['group_type']) . ' - ' . htmlspecialchars($user['group_name']) : 'N/A'; ?></p>
            <p><strong>Role:</strong> <?php echo htmlspecialchars($role_name); ?></p>
        </div>
    </div>
</div>