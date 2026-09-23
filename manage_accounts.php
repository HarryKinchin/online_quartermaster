<?php
// Restrict access to admins and quartermasters only
if (!isset($_SESSION['user_level']) || !in_array($_SESSION['user_level'], [1, 2], true)) {
    header("Location: index.php?page=home");
    exit();
}

date_default_timezone_set('Europe/London');

$is_admin = $_SESSION['user_level'] === 1;
$is_quartermaster = $_SESSION['user_level'] === 2;

// Handle delete request
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];
    $user_id = isset($_POST['user_id']) ? (int)$_POST['user_id'] : 0;
    
    if ($action === 'delete' && $user_id > 0 && $user_id !== $_SESSION['user_id']) {
        // Only admins can delete accounts; quartermasters cannot delete admin accounts
        if ($is_admin) {
            $stmt = $conn->prepare("DELETE FROM users WHERE user_id = ?");
            $stmt->bind_param("i", $user_id);
            if ($stmt->execute()) {
                $success_message = "Account deleted successfully.";
            } else {
                $error_message = "Failed to delete account.";
            }
            $stmt->close();
        } else {
            $error_message = "You don't have permission to delete accounts.";
        }
    } elseif ($action === 'reset_password' && $user_id > 0) {
        // Send password reset email
        // Get user email
        $stmt = $conn->prepare("SELECT email, first_name, last_name FROM users WHERE user_id = ?");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 1) {
            $user = $result->fetch_assoc();
            $email = $user['email'];
            
            // Generate reset token
            $token = bin2hex(random_bytes(16));
            $hash = hash("sha256", $token);
            $expiry = date("Y-m-d H:i:s", strtotime('+1 hour'));
            
            // Update user with reset token
            $update = $conn->prepare("UPDATE users SET reset_token_hash = ?, reset_expiry = ? WHERE user_id = ?");
            $update->bind_param("ssi", $hash, $expiry, $user_id);
            
            if ($update->execute()) {
                // Send email
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
                    $mail->Body    = "Hello " . htmlspecialchars($user['first_name']) . ",<br><br>A password reset has been requested for your account.<br><br>Click here to reset your password: <a href='$resetLink'>$resetLink</a><br><br>This link expires in 1 hour.<br><br>If you did not request this, please contact the administrator.";
                    
                    $mail->send();
                    $success_message = "Password reset email sent to " . htmlspecialchars($user['first_name'] . ' ' . $user['last_name']) . ".";
                } catch (Exception $e) {
                    $error_message = "Failed to send email: " . $mail->ErrorInfo;
                }
            } else {
                $error_message = "Failed to generate reset token.";
            }
            $update->close();
        }
        $stmt->close();
    }
}

// Fetch all users
$query = "SELECT u.user_id, u.first_name, u.last_name, u.email, u.group_name, u.group_type, u.role_id, ur.role_name, u.creation_date 
          FROM users u 
          LEFT JOIN user_roles ur ON u.role_id = ur.role_id 
          ORDER BY u.creation_date DESC";
$stmt = $conn->prepare($query);
$stmt->execute();
$users_result = $stmt->get_result();
$stmt->close();
?>

<div class="main">
    <div style="padding: 2rem;">
        <h1>Account Management</h1>
        
        <?php if (isset($success_message)): ?>
            <div class="success-message" style="background-color: #dcfce7; border: 1px solid #22c55e; color: #16a34a; padding: 1rem; border-radius: 0.5rem; margin-bottom: 1rem;">
                <?php echo htmlspecialchars($success_message); ?>
            </div>
        <?php endif; ?>
        
        <?php if (isset($error_message)): ?>
            <div class="error" style="margin-bottom: 1rem;">
                <?php echo htmlspecialchars($error_message); ?>
            </div>
        <?php endif; ?>
        
        <div style="margin-bottom: 1.5rem; display: flex; gap: 1rem;">
            <a href="index.php?page=new_account" class="create-account-btn" style="display: inline-block; background: linear-gradient(to bottom, #ededed 5%, #bab1ba 100%); background-color: #ededed; border-radius: 0.938rem; border: 0.063rem solid #d6bcd6; padding: 0.438rem 1.563rem; color: #3a8a9e; font-size: 1.063rem; text-decoration: none; cursor: pointer; box-shadow: 0.188rem 0.25rem 0rem 0rem #899599;">
                ➕ Create New Account
            </a>
        </div>
        
        <div style="overflow-x: auto;">
            <table style="width: 100%; border-collapse: collapse; margin-top: 1rem;">
                <thead>
                    <tr style="background-color: #e2e2e2; border-bottom: 2px solid #435354;">
                        <th style="padding: 1rem; text-align: left; border: 1px solid #d1d5db;">Name</th>
                        <th style="padding: 1rem; text-align: left; border: 1px solid #d1d5db;">Email</th>
                        <th style="padding: 1rem; text-align: left; border: 1px solid #d1d5db;">Role</th>
                        <th style="padding: 1rem; text-align: left; border: 1px solid #d1d5db;">Section</th>
                        <th style="padding: 1rem; text-align: left; border: 1px solid #d1d5db;">Created</th>
                        <th style="padding: 1rem; text-align: center; border: 1px solid #d1d5db;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($user = $users_result->fetch_assoc()): ?>
                        <tr style="border-bottom: 1px solid #d1d5db; background-color: <?php echo ($user['user_id'] === $_SESSION['user_id']) ? '#f0f8ff' : 'white'; ?>;">
                            <td style="padding: 0.75rem 1rem; border: 1px solid #d1d5db;">
                                <strong><?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?></strong>
                                <?php if ($user['user_id'] === $_SESSION['user_id']): ?>
                                    <span style="background-color: #3a8a9e; color: white; padding: 0.25rem 0.5rem; border-radius: 0.25rem; font-size: 0.75rem; margin-left: 0.5rem;">You</span>
                                <?php endif; ?>
                            </td>
                            <td style="padding: 0.75rem 1rem; border: 1px solid #d1d5db;">
                                <a href="mailto:<?php echo htmlspecialchars($user['email']); ?>" style="color: #3a8a9e; text-decoration: underline;">
                                    <?php echo htmlspecialchars($user['email']); ?>
                                </a>
                            </td>
                            <td style="padding: 0.75rem 1rem; border: 1px solid #d1d5db;">
                                <strong><?php echo htmlspecialchars($user['role_name'] ?? 'Not Assigned'); ?></strong>
                            </td>
                            <td style="padding: 0.75rem 1rem; border: 1px solid #d1d5db;">
                                <?php 
                                if ($user['group_name']) {
                                    echo htmlspecialchars($user['group_name']);
                                    if ($user['group_type'] && $user['group_type'] !== $user['group_name']) {
                                        echo ' (' . htmlspecialchars($user['group_type']) . ')';
                                    }
                                } else {
                                    echo '<em style="color: #999;">No Section</em>';
                                }
                                ?>
                            </td>
                            <td style="padding: 0.75rem 1rem; border: 1px solid #d1d5db;">
                                <?php echo date('M d, Y', strtotime($user['creation_date'])); ?>
                            </td>
                            <td style="padding: 0.75rem 1rem; border: 1px solid #d1d5db; text-align: center;">
                                <a class="edit-button account-action" href="index.php?page=edit_user&user_id=<?php echo $user['user_id']; ?>">
                                    ✏️ Edit
                                </a>
                                
                                <form method="POST" style="display: inline;" onsubmit="return confirm('Send password reset email to <?php echo htmlspecialchars($user['first_name']); ?>?');">
                                    <input type="hidden" name="action" value="reset_password">
                                    <input type="hidden" name="user_id" value="<?php echo $user['user_id']; ?>">
                                    <button type="submit" class="account-action">
                                        📧 Reset
                                    </button>
                                </form>

                                <?php if ($user['user_id'] !== $_SESSION['user_id']): ?>
                                    <?php if ($is_admin): ?>
                                        <form method="POST" style="display: inline;" onsubmit="return confirm('Are you sure you want to delete this account? This cannot be undone.');">
                                            <input type="hidden" name="action" value="delete">
                                            <input type="hidden" name="user_id" value="<?php echo $user['user_id']; ?>">
                                            <button type="submit" class="account-action">
                                                🗑️ Delete
                                            </button>
                                        </form>
                                    <?php elseif ($user['role_id'] !== 1): ?>
                                        <!-- Quartermasters cannot delete users -->
                                    <?php endif; ?>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
        
        <?php if ($users_result->num_rows === 0): ?>
            <div style="text-align: center; padding: 2rem; color: #666;">
                <p>No accounts found.</p>
            </div>
        <?php endif; ?>
    </div>
</div>

<style>
    .success-message {
        font-weight: bold;
        text-align: center;
    }

    .account-action {
        width: auto;
        min-width: 0;
        margin: 0 0.25rem 0 0;
        padding: 0.25rem 0.75rem;
        vertical-align: middle;
    }
    
    table a {
        transition: opacity 0.2s;
    }
    
    table a:hover {
        opacity: 0.7;
    }
    
    .create-account-btn {
        transition: transform 150ms ease, background-color 150ms ease, box-shadow 150ms ease, color 150ms ease !important;
    }
    
    .create-account-btn:hover {
        background: rgba(15, 118, 110, 0.95) !important;
    }
</style>
