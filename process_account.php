<?php 
session_start();
require_once 'db_conn.php';
date_default_timezone_set('Europe/London');

if ($_SERVER["REQUEST_METHOD"] == "POST") {

    $creator_level = (int) ($_SESSION['user_level'] ?? 0);
    if (!in_array($creator_level, [1, 2], true)) {
        header("Location: index.php?page=home");
        exit();
    }

    $firstName = trim(htmlspecialchars($_POST['first_name']));
    $lastName = trim(htmlspecialchars($_POST['last_name']));
    $email = trim(htmlspecialchars($_POST['email']));
    $role_id = trim(htmlspecialchars($_POST['group_role']));
    $group = trim(htmlspecialchars($_POST['group_name']));
    $tempPword = trim(htmlspecialchars($_POST['password']));
    
    if (empty($firstName) || empty($lastName) || empty($email) || empty($role_id)) {
        die("Missing required information");
    }

    $role_id = (int) $role_id;
    if ($creator_level === 2 && $role_id !== 3) {
        die("Quartermasters can only create group volunteer accounts.");
    }
    
    $hashed_pword = password_hash($tempPword, PASSWORD_DEFAULT);

    // Normalize group into name (all but last word) and type (last word)
    $group = trim($group);
    $parts = preg_split('/\s+/', $group);

    if ($group === 'No Group') {
        $group_name = '';
        $group_type = '';
    } elseif ($group === 'Personal Use') {
        $group_name = 'Personal Use';
        $group_type = 'Personal Use';
    } elseif ($group === 'Network') {
        $group_name = 'Network';
        $group_type = 'Network';
    } else {
        if (count($parts) === 1) {
            // single word -> use it for both name and type
            $group_name = $parts[0];
            $group_type = $parts[0];
        } else {
            // last word is type, everything before is name
            $group_type = array_pop($parts);
            $group_name = implode(' ', $parts);
        }
    }

    if ($creator_level === 2) {
        $stmt_creator_group = $conn->prepare("SELECT group_name, group_type FROM users WHERE user_id = ?");
        $stmt_creator_group->bind_param("i", $_SESSION['user_id']);
        $stmt_creator_group->execute();
        $creator_group = $stmt_creator_group->get_result()->fetch_assoc();

        if (!$creator_group || $group_name !== ($creator_group['group_name'] ?? '') || $group_type !== ($creator_group['group_type'] ?? '')) {
            die("Quartermasters can only create accounts for their own group.");
        }
    }

    $sql = "INSERT INTO `users` (`password_hash`, `first_name`, `last_name`, `email`, `group_type`, `group_name`, `role_id`) VALUES (?, ?, ?, ?, ?, ?, ?)";
    $stmt_new_user = $conn->prepare($sql);
    $stmt_new_user->bind_param("ssssssi", $hashed_pword, $firstName, $lastName, $email, $group_type, $group_name, $role_id);
    $stmt_new_user->execute();

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
            $mail->Subject = 'New Account Login';
            $mail->Body    = "Click here to login to your Online QuarterMaster account: <a href='$resetLink'>$resetLink</a>. This link expires in 1 hour.";

            $mail->send();
            $error_message = "Reset link sent to your email.";
        } catch (Exception $e) {
            $error_message = "Mail error: " . $mail->ErrorInfo;
        }
        header("Location: index.php?page=check_email");
    } else {
        $error_message = "Email not found.";
    }

} else {
    header("Location: index.php?page=new_account");
    exit();
}
?>