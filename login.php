<?php
session_start();
include_once("db_conn.php");
date_default_timezone_set('Europe/London');

if (isset($_POST['reset_request'])) {
    $email = $_POST['email'];
    
    $stmt = $conn->prepare("SELECT user_id FROM users WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $res = $stmt->get_result();

    if ($res->num_rows === 1) {
        $token = bin2hex(random_bytes(16));
        $hash = hash("sha256", $token);
        $expiry = date("Y-m-d H:i:s", strtotime('+1 hour'));

        $update = $conn->prepare("UPDATE users SET reset_token_hash = ?, reset_expiry = ? WHERE email = ?");
        $update->bind_param("sss", $hash, $expiry, $email);
        $update->execute();

        require './lib/PHPMailer/PHPMailer.php';
        require './lib/PHPMailer/SMTP.php';
        require './lib/PHPMailer/Exception.php';
        require_once './mail_config.php';

        $mail = new PHPMailer\PHPMailer\PHPMailer(true);
        try {
            configure_qm_mailer($mail);
            $mail->addAddress($email);

            $resetLink = (getenv('QM_APP_URL') ?: '') . "/reset_password.php?token=$token&email=" . rawurlencode($email);
            
            $mail->isHTML(true);
            $mail->Subject = 'Password Reset Request';
            $mail->Body    = "Click here to reset your password: <a href='$resetLink'>$resetLink</a>. This link expires in 1 hour.";

            $mail->send();
            $error_message = "Reset link sent to the given email.";
        } catch (Exception $e) {
            $error_message = "Mail error: " . $mail->ErrorInfo;
        }
        header("Location: index.php?page=check_email");
    } else {
        $error_message = "Email not found.";
    }
    header("Location: index.php?page=check_email");
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $email = $_POST['email'];
    $password = $_POST['pword'];

    $stmt = $conn->prepare(query: "SELECT user_id, first_name, last_name, password_hash, role_id FROM users WHERE email = ?");
    $stmt->bind_param("s",$email);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 1) {
        $user = $result->fetch_assoc();
        if (password_verify(password: $password, hash: $user['password_hash'])) {
            session_regenerate_id(true);
            $_SESSION['user_id'] = $user['user_id'];
            $_SESSION['username'] = $user['first_name'] . ' ' . $user['last_name'];
            $_SESSION['user_level']= $user['role_id'];
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
                <input type="email" name="email" class="form-input" required><br>
            </div>
            <div>
                <label for="pword" class="form-label">Password</label><br>
                <input type="password" name="pword" class="form-input"><br>
            </div>
            <div>
                <button type="submit" name="login" class="submit-button">Log In</button>
                <button type="submit" name="reset_request" class="submit-button">Reset Password</button>
            </div>
            <?php if (isset($error_message)): ?>
                <p class="error"><?php echo $error_message; ?></p>
            <?php endif; ?>
        </form>
    </div>
</div>