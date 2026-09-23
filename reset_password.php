<?php
session_start();
include_once("db_conn.php");
include_once("top.php");
include_once("sidebar.php");

$token = $_GET['token'] ?? '';
$email = $_GET['email'] ?? '';
$hash = hash("sha256", $token);

$stmt = $conn->prepare("SELECT user_id FROM users WHERE email = ? AND reset_token_hash = ? AND reset_expiry > NOW()");
$stmt->bind_param("ss", $email, $hash);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    die("Invalid or expired link.");
}

$user = $result->fetch_assoc();

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $new_password = password_hash($_POST['new_pword'], PASSWORD_DEFAULT);
    
    $update = $conn->prepare("UPDATE users SET password_hash = ?, reset_token_hash = NULL, reset_expiry = NULL WHERE user_id = ?");
    $update->bind_param("si", $new_password, $user['user_id']);
    
    if ($update->execute()) {
        $_SESSION['user_id'] = $user['user_id'];
        header("Location: index.php?page=home&msg=password_updated");
        exit;
    }
}
?>
<div class="main">
    <form class="form-layout" method="POST">
        <h2>Set New Password</h2>
        <input type="password" name="new_pword" placeholder="New Password" class="form-input" required>
        <button type="submit"class="submit-button">Update & Login</button>
    </form>
</div>

<?php
    include_once("bottom.php");
?>