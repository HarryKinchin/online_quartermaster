<?php
session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: index.php?page=login");
    exit;
}

$user_id = $_SESSION['user_id'];
$is_editing = isset($_GET['edit']) && $_GET['edit'] === '1';
$form_error = '';
$form_message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $first_name = trim($_POST['first_name'] ?? '');
    $last_name = trim($_POST['last_name'] ?? '');
    $section = $_POST['section'] ?? '';
    $current_password = $_POST['current_password'] ?? '';
    $new_password = $_POST['new_password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    $section_parts = explode('|', $section, 2);

    // First, verify current password
    if ($current_password === '') {
        $form_error = 'Current password is required to make changes.';
    } elseif ($first_name === '' || $last_name === '') {
        $form_error = 'First name and last name are required.';
    } elseif (count($section_parts) !== 2) {
        $form_error = 'Please select a valid section.';
    } elseif (($new_password !== '' || $confirm_password !== '') && $new_password !== $confirm_password) {
        $form_error = 'The passwords do not match.';
    }

    if ($form_error === '') {
        // Verify current password against stored hash
        $stmt_password = $conn->prepare("SELECT password_hash FROM users WHERE user_id = ?");
        $stmt_password->bind_param("i", $user_id);
        $stmt_password->execute();
        $password_result = $stmt_password->get_result();
        $password_row = $password_result->fetch_assoc();
        $stmt_password->close();
        
        if (!password_verify($current_password, $password_row['password_hash'])) {
            $form_error = 'Current password is incorrect.';
        }
    }

    if ($form_error === '') {
        $group_type = $section_parts[0];
        $group_name = $section_parts[1];
        $stmt_section = $conn->prepare("SELECT group_name FROM sections WHERE group_type = ? AND group_name = ?");
        $stmt_section->bind_param("ss", $group_type, $group_name);
        $stmt_section->execute();

        if ($stmt_section->get_result()->num_rows === 0 && $section !== '|') {
            $form_error = 'Please select a valid section.';
        }
    }

    if ($form_error === '') {
        if ($new_password !== '') {
            $password_hash = password_hash($new_password, PASSWORD_DEFAULT);
            $stmt_update = $conn->prepare("UPDATE users SET first_name = ?, last_name = ?, group_type = ?, group_name = ?, password_hash = ? WHERE user_id = ?");
            $stmt_update->bind_param("sssssi", $first_name, $last_name, $group_type, $group_name, $password_hash, $user_id);
        } else {
            $stmt_update = $conn->prepare("UPDATE users SET first_name = ?, last_name = ?, group_type = ?, group_name = ? WHERE user_id = ?");
            $stmt_update->bind_param("ssssi", $first_name, $last_name, $group_type, $group_name, $user_id);
        }

        if ($stmt_update->execute()) {
            $form_message = 'Account details updated.';
            $is_editing = false;
        } else {
            $form_error = 'Your account details could not be updated.';
            $is_editing = true;
        }
    }

    if ($form_error !== '') {
        $is_editing = true;
    }
}

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
$stmt_sections = $conn->prepare("SELECT group_name, group_type FROM sections ORDER BY group_weight, group_name");
$stmt_sections->execute();
$sections_result = $stmt_sections->get_result();
if (!$user) {
    echo "Error: User data not found.";
    exit;
}

$section_display = 'N/A';
if (!empty($user['group_name'])) {
    $section_display = in_array(strtolower($user['group_type']), ['network', 'personal use'], true)
        ? $user['group_type']
        : $user['group_type'] . ' - ' . $user['group_name'];
}

?>

<div class="main">
    <div class="account-container">
        <h2>Your Account Details</h2>
        <?php if ($form_error !== ''): ?>
            <p class="error"><?php echo htmlspecialchars($form_error); ?></p>
        <?php elseif ($form_message !== ''): ?>
            <p><?php echo htmlspecialchars($form_message); ?></p>
        <?php endif; ?>
        <div class="user-info">
            <p><strong>Name:</strong> <?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?></p>
            <p><strong>Email:</strong> <?php echo htmlspecialchars($user['email']); ?></p>
            <p><strong>Section:</strong> <?php echo htmlspecialchars($section_display); ?></p>
            <p><strong>Role:</strong> <?php echo htmlspecialchars($role_name); ?></p>
        </div>

        <?php if (!$is_editing): ?>
            <a class="edit-button" href="index.php?page=account&amp;edit=1">Edit details</a>
        <?php else: ?>
        <form method="post" class="form-layout">
            <div style="background-color: #fff3cd; border: 1px solid #ffc107; color: #856404; padding: 1rem; border-radius: 0.5rem; margin-bottom: 1.5rem;">
                <strong>⚠️ Security:</strong> Enter your current password to make changes to your account.
            </div>

            <div>
                <label class="form-label" for="current_password">Current password *</label>
                <input id="current_password" name="current_password" type="password" class="form-input" autocomplete="current-password" required>
            </div>

            <hr style="margin: 1.5rem 0; border: none; border-top: 1px solid #d1d5db;">

            <div class="form-row">
                <div>
                    <label class="form-label" for="first_name">First name</label>
                    <input id="first_name" name="first_name" class="form-input" value="<?php echo htmlspecialchars($_POST['first_name'] ?? $user['first_name']); ?>" required>
                </div>
                <div>
                    <label class="form-label" for="last_name">Last name</label>
                    <input id="last_name" name="last_name" class="form-input" value="<?php echo htmlspecialchars($_POST['last_name'] ?? $user['last_name']); ?>" required>
                </div>
            </div>

            <div class="grid-layout-form">
                <label class="form-label" for="section">For</label>
                <select id="section" name="section" class="form-input" required>
                    <option value="|" <?php echo (empty($user['group_type']) && empty($user['group_name'])) ? 'selected' : ''; ?>>No Section</option>
                    <?php while ($section_row = $sections_result->fetch_assoc()): ?>
                        <?php $section_value = $section_row['group_type'] . '|' . $section_row['group_name']; ?>
                        <option value="<?php echo htmlspecialchars($section_value); ?>" <?php echo ($section_row['group_type'] === ($user['group_type'] ?? '') && $section_row['group_name'] === ($user['group_name'] ?? '')) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($section_row['group_name'] === $section_row['group_type'] ? $section_row['group_name'] : $section_row['group_name'] . ' ' . $section_row['group_type']); ?>
                        </option>
                    <?php endwhile; ?>
                </select>
            </div>

            <div>
                <label class="form-label" for="new_password">New password</label>
                <input id="new_password" name="new_password" type="password" class="form-input" autocomplete="new-password">
            </div>
            <div>
                <label class="form-label" for="confirm_password">Confirm new password</label>
                <input id="confirm_password" name="confirm_password" type="password" class="form-input" autocomplete="new-password">
            </div>

            <button type="submit" class="submit-button">Update account</button>
        </form>
        <?php endif; ?>
    </div>
</div>