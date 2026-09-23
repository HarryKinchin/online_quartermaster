<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Online QuarterMaster</title>
    <link rel="icon" type="image/x-icon" href="./static/images/favicon.ico">
    <link rel="apple-touch-icon" href="./static/images/apple-touch-icon.png">
    <link rel="stylesheet" type="text/css" href="./static/css/stylings.css">
</head>

<body>

<header class="topbar">
    <div class="topbar-inner">
        <button class="menu-toggle" type="button" onclick="show_hide_nav()" aria-controls="my-sidenav" aria-expanded="false" aria-label="Open navigation">
            <span></span><span></span><span></span>
        </button>
        <div class="topbar-title">
            <span class="topbar-kicker">Scout equipment</span>
            <strong>QuarterMaster</strong>
        </div>
        <?php if (isset($_SESSION['user_id'])): ?>
            <div class="topbar-account">
                <a class="topbar-user" href="index.php?page=account">
                    <span class="topbar-avatar" aria-hidden="true">◎</span>
                    <span><?php echo htmlspecialchars(isset($_SESSION['username']) ? $_SESSION['username'] : 'Account'); ?></span>
                </a>
                <a class="topbar-logout" href="logout.php">Logout</a>
            </div>
        <?php endif; ?>
    </div>
</header>
<script>
    function show_hide_nav() {
        var navigation = document.getElementById("my-sidenav");
        var toggle = document.querySelector(".menu-toggle");
        var isOpen = navigation.classList.toggle("is-open");
        toggle.setAttribute("aria-expanded", isOpen ? "true" : "false");
        toggle.setAttribute("aria-label", isOpen ? "Close navigation" : "Open navigation");
    }
</script>