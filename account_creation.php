<?php
if (!isset($_SESSION['user_level']) || !in_array($_SESSION['user_level'], [1, 2], true)) {
    header("Location: index.php?page=home");
    exit();
}

$is_quartermaster = $_SESSION['user_level'] === 2;
$current_user_group = null;
if ($is_quartermaster) {
    $stmt_user_group = $conn->prepare("SELECT group_name, group_type FROM users WHERE user_id = ?");
    $stmt_user_group->bind_param("i", $_SESSION['user_id']);
    $stmt_user_group->execute();
    $current_user_group = $stmt_user_group->get_result()->fetch_assoc();
}

if ($is_quartermaster && empty($current_user_group['group_name'])) {
    echo '<div class="form-container"><h1 class="form-title">Account creation unavailable</h1>';
    echo '<p>Your account is not assigned to a group.</p></div>';
    exit();
}

if ($is_quartermaster) {
    $stmt_group = $conn->prepare("SELECT group_name, group_type FROM sections WHERE group_name = ? AND group_type = ?");
    $stmt_group->bind_param("ss", $current_user_group['group_name'], $current_user_group['group_type']);
} else {
    $stmt_group = $conn->prepare("SELECT group_name, group_type FROM sections ORDER BY group_weight ASC");
}
$stmt_group->execute();
$group_result = $stmt_group->get_result();

$stmt_role = $conn->prepare("SELECT * FROM user_roles");
$stmt_role->execute();
$role_result = $stmt_role->get_result();

// Random Password Generator:
// Source - https://stackoverflow.com/a/4356295
// Posted by Stephen Watkins, modified by community. See post 'Timeline' for change history
// Retrieved 2026-06-18, License - CC BY-SA 4.0
function generateRandomString($length = 16) {
    $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ!@#$%^&*-_';
    $charactersLength = strlen($characters);
    $randomString = '';

    for ($i = 0; $i < $length; $i++) {
        $randomString .= $characters[random_int(0, $charactersLength - 1)];
    }

    return $randomString;
}
?>

<div class="main">
<div  class="form-container">
    <h2 class="form-title">Create New Account</h2>
    <hr>

    <form action="process_account.php" method="POST" class="form-container">
        <!-- Personal Details -->
        <div>
            <label for="first_name" class="form-label">First Name:</label>
            <input type="text" id="first_name" name="first_name" required class="form-input">

            <label for="last_name" class="form-label">Last Name:</label>
            <input type="text" id="last_name" name="last_name" required class="form-input">

            <label for="email" class="form-label">Email:</label>
            <input type="email" id="email" name="email" required class="form-input">
        </div>

        <!-- Account Details -->
        <div>
            <label for="group_name" class="form-label">Section Name:</label>
            <select type="text" id="group_name" name="group_name" required class="form-input">
                <option value="" disabled selected>Select a section / end use</option>
                <?php if (!$is_quartermaster): ?>
                    <option value="No Group">No Section</option>
                <?php endif; ?>
                <?php
                    while ($row = $group_result->fetch_assoc()) {
                        $groupName = htmlspecialchars($row['group_name']);
                        $groupType = htmlspecialchars($row['group_type']);
                        if($groupName == $groupType){
                            $displayValue = $groupName;
                        } else {
                            $displayValue = $groupName . ' ' . $groupType;
                        }
                        echo "<option value=\"$displayValue\">$displayValue</option>";
                    }
                ?>
            </select>

            <label for="group_role" class="form-label">Section Role:</label>
            <select type="text" id="group_role" name="group_role" required class="form-input">
                <option value="" disabled selected>Select a role</option>
                <?php
                    while ($row = $role_result->fetch_assoc()) {
                        $role_displayValue = htmlspecialchars($row['role_name']);
                        $role_innerValue = htmlspecialchars($row['role_id']);
                        if (!$is_quartermaster || (int) $row['role_id'] === 3) {
                            echo "<option value=\"$role_innerValue\">$role_displayValue</option>";
                        }
                    }
                ?>
            </select>
        <!-- Security -->
            <label for="password" class="form-label">Default Password:</label>
            <input type="text" id="password" name="password" value='<?php echo generateRandomString() ?>' readonly class="form-input">
        </div>

        <button type="submit" name="submit_account" class="submit-button">Create Account</button>
    </form>
</div>
</div>
