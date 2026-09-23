<?php
// Restrict access to admins and quartermasters only
if (!isset($_SESSION['user_level']) || !in_array($_SESSION['user_level'], [1, 2], true)) {
    header("Location: index.php?page=home");
    exit();
}

$is_admin = $_SESSION['user_level'] === 1;
$is_quartermaster = $_SESSION['user_level'] === 2;

// Get the user ID to edit
$edit_user_id = isset($_GET['user_id']) ? (int)$_GET['user_id'] : 0;

if ($edit_user_id === 0) {
    header("Location: index.php?page=manage_accounts");
    exit();
}

// Fetch user details
$stmt = $conn->prepare("SELECT user_id, first_name, last_name, email, group_name, group_type, role_id FROM users WHERE user_id = ?");
$stmt->bind_param("i", $edit_user_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    header("Location: index.php?page=manage_accounts");
    exit();
}

$user = $result->fetch_assoc();
$stmt->close();

// Check permissions: Admins can edit anyone, Quartermasters can edit any non-admin
if ($is_quartermaster && $user['role_id'] === 1) {
    // Quartermasters cannot edit admins
    header("Location: index.php?page=manage_accounts");
    exit();
}

$form_error = '';
$form_message = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $first_name = trim($_POST['first_name'] ?? '');
    $last_name = trim($_POST['last_name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $role_id = isset($_POST['role_id']) ? (int)$_POST['role_id'] : 0;
    $section = $_POST['section'] ?? '';
    $new_password = $_POST['new_password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    
    // Validation
    if (empty($first_name) || empty($last_name)) {
        $form_error = 'First name and last name are required.';
    } elseif (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $form_error = 'Valid email is required.';
    } elseif ($role_id === 0) {
        $form_error = 'Please select a role.';
    } elseif (($new_password !== '' || $confirm_password !== '') && $new_password !== $confirm_password) {
        $form_error = 'Passwords do not match.';
    } elseif (!$is_admin && $new_password !== '') {
        $form_error = 'Only admins can change user passwords.';
    }
    
    // Validate role change permissions
    if ($form_error === '' && $new_password === '') {
        if (!$is_admin && $role_id !== $user['role_id']) {
            $form_error = 'Only admins can change user roles.';
        }
    }
    
    if ($form_error === '') {
        $group_name = '';
        $group_type = '';
        
        if ($section !== '' && $section !== 'No Group') {
            $section_parts = explode('|', $section, 2);
            if (count($section_parts) === 2) {
                $group_type = $section_parts[0];
                $group_name = $section_parts[1];
            }
        }
        
        // Update user
        if ($new_password !== '') {
            $password_hash = password_hash($new_password, PASSWORD_DEFAULT);
            $stmt = $conn->prepare("UPDATE users SET first_name = ?, last_name = ?, email = ?, role_id = ?, group_type = ?, group_name = ?, password_hash = ? WHERE user_id = ?");
            $stmt->bind_param("sssissi", $first_name, $last_name, $email, $role_id, $group_type, $group_name, $password_hash, $edit_user_id);
        } else {
            $stmt = $conn->prepare("UPDATE users SET first_name = ?, last_name = ?, email = ?, role_id = ?, group_type = ?, group_name = ? WHERE user_id = ?");
            $stmt->bind_param("sssisi", $first_name, $last_name, $email, $role_id, $group_type, $group_name, $edit_user_id);
        }
        
        if ($stmt->execute()) {
            $form_message = 'Account updated successfully.';
            // Refresh user data
            $user['first_name'] = $first_name;
            $user['last_name'] = $last_name;
            $user['email'] = $email;
            $user['role_id'] = $role_id;
            $user['group_name'] = $group_name;
            $user['group_type'] = $group_type;
        } else {
            $form_error = 'Failed to update account.';
        }
        $stmt->close();
    }
}

// Fetch available roles
$stmt = $conn->prepare("SELECT role_id, role_name FROM user_roles ORDER BY role_id ASC");
$stmt->execute();
$roles_result = $stmt->get_result();
$stmt->close();

// Fetch available sections
if ($is_admin) {
    $stmt = $conn->prepare("SELECT group_name, group_type FROM sections ORDER BY group_weight ASC");
} else {
    // Quartermasters can only assign users to their own section
    $stmt = $conn->prepare("SELECT group_name, group_type FROM users WHERE user_id = ?");
    $stmt->bind_param("i", $_SESSION['user_id']);
}
$stmt->execute();
$sections_result = $stmt->get_result();
$stmt->close();
?>

<div class="main">
    <div class="form-container">
        <h2 class="form-title">Edit Account: <?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?></h2>
        <hr>
        
        <?php if ($form_message): ?>
            <div style="background-color: #dcfce7; border: 1px solid #22c55e; color: #16a34a; padding: 1rem; border-radius: 0.5rem; margin-bottom: 1rem; text-align: center; font-weight: bold;">
                <?php echo htmlspecialchars($form_message); ?>
            </div>
        <?php endif; ?>
        
        <?php if ($form_error): ?>
            <div class="error">
                <?php echo htmlspecialchars($form_error); ?>
            </div>
        <?php endif; ?>
        
        <form method="POST" class="form-layout">
            <div class="form-row">
                <div>
                    <label for="first_name" class="form-label">First Name *</label>
                    <input type="text" id="first_name" name="first_name" class="form-input" value="<?php echo htmlspecialchars($user['first_name']); ?>" required>
                </div>
                <div>
                    <label for="last_name" class="form-label">Last Name *</label>
                    <input type="text" id="last_name" name="last_name" class="form-input" value="<?php echo htmlspecialchars($user['last_name']); ?>" required>
                </div>
            </div>
            
            <div class="form-row">
                <div>
                    <label for="email" class="form-label">Email *</label>
                    <input type="email" id="email" name="email" class="form-input" value="<?php echo htmlspecialchars($user['email']); ?>" required>
                </div>
            </div>
            
            <?php if ($is_admin): ?>
                <div class="form-row">
                    <div>
                        <label for="role_id" class="form-label">Role *</label>
                        <select id="role_id" name="role_id" class="form-input" required>
                            <option value="">Select a role</option>
                            <?php while ($role = $roles_result->fetch_assoc()): ?>
                                <option value="<?php echo $role['role_id']; ?>" <?php echo ($role['role_id'] === $user['role_id']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($role['role_name']); ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                </div>
            <?php else: ?>
                <div class="form-row">
                    <div>
                        <label for="role_id" class="form-label">Role</label>
                        <input type="text" id="role_id" class="form-input" disabled value="<?php 
                            $stmt = $conn->prepare("SELECT role_name FROM user_roles WHERE role_id = ?");
                            $stmt->bind_param("i", $user['role_id']);
                            $stmt->execute();
                            $role_result = $stmt->get_result();
                            if ($role_result->num_rows > 0) {
                                $role_row = $role_result->fetch_assoc();
                                echo htmlspecialchars($role_row['role_name']);
                            }
                            $stmt->close();
                        ?>">
                        <p class="form-help">Only admins can change user roles.</p>
                    </div>
                </div>
            <?php endif; ?>
            
            <div class="form-row">
                <div>
                    <label for="section" class="form-label">Section</label>
                    <select id="section" name="section" class="form-input">
                        <option value="">No Section</option>
                        <?php 
                        $current_section = '';
                        if ($user['group_name']) {
                            if ($user['group_type'] === $user['group_name']) {
                                $current_section = $user['group_type'] . '|' . $user['group_name'];
                            } else {
                                $current_section = $user['group_type'] . '|' . $user['group_name'];
                            }
                        }
                        
                        // Rebuild sections result for display
                        $stmt = $conn->prepare("SELECT group_name, group_type FROM sections ORDER BY group_weight ASC");
                        $stmt->execute();
                        $sections_result = $stmt->get_result();
                        
                        while ($section = $sections_result->fetch_assoc()): 
                            $section_value = $section['group_type'] . '|' . $section['group_name'];
                            $section_display = ($section['group_type'] === $section['group_name']) ? $section['group_name'] : $section['group_name'] . ' ' . $section['group_type'];
                        ?>
                            <option value="<?php echo htmlspecialchars($section_value); ?>" <?php echo ($section_value === $current_section) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($section_display); ?>
                            </option>
                        <?php endwhile; 
                        $stmt->close();
                        ?>
                    </select>
                </div>
            </div>
            
            <?php if ($is_admin): ?>
                <hr style="margin: 1.5rem 0; border: none; border-top: 1px solid #d1d5db;">
                <h3 style="text-align: left; font-size: 1.1rem; margin-top: 1.5rem;">Change Password (Optional)</h3>
                <p class="form-help">Leave blank to keep the current password.</p>
                
                <div class="form-row">
                    <div>
                        <label for="new_password" class="form-label">New Password</label>
                        <input type="password" id="new_password" name="new_password" class="form-input" placeholder="Leave blank to keep current password">
                    </div>
                    <div>
                        <label for="confirm_password" class="form-label">Confirm Password</label>
                        <input type="password" id="confirm_password" name="confirm_password" class="form-input" placeholder="Confirm new password">
                    </div>
                </div>
            <?php endif; ?>
            
            <div style="text-align: center; margin-top: 2rem;">
                <button type="submit" style="width: auto; padding: 0.75rem 2rem;">Save Changes</button>
                <a href="index.php?page=manage_accounts" style="margin-left: 1rem; color: #666; text-decoration: underline;">Cancel</a>
            </div>
        </form>
    </div>
</div>
