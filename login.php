<?php
session_start();
include_once("db_conn.php");

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $email = $_POST['email'];
    $password = $_POST['pword'];

    $stmt = $conn->prepare(query: "SELECT user_id, first_name, last_name, password_hash FROM users WHERE email = ?");
    $stmt->bind_param("s",$email);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 1) {
        $user = $result->fetch_assoc();
        if (password_verify(password: $password, hash: $user['password_hash'])) {
            $_SESSION['user_id'] = $user['user_id'];
            $_SESSION['username'] = $user['first_name'] . ' ' . $user['last_name'];
            header("Location: index.php?page=home");
            exit;
        } else {
            $error_message = "Invalid password.";
        }
    } else {
        $error_message = "Invalid email.";
    }
    $stmt->close();
}
?>

<div class="main">
    <div class="form-container">
        <form class="form-layout" action="index.php?page=login" method="POST">
        <div>
            <label for="email" class="form-label">Email</label><br>
            <input type="email" placeholder="E-mail" name="email" class="form-input" required><br>
        </div>
        <div>
            <label for="pword" class="form-label">Password</label><br>
            <input type="password" placeholder="Password" name="pword" class="form-input" required><br>
        </div>
        <div>
            <button type="submit" class="submit-button">Log In</button>
        </div>
        <?php if (isset($error_message)): ?>
            <p class="error"><?php echo $error_message; ?></p>
        <?php endif; ?>
        </form>
    </div>
</div>