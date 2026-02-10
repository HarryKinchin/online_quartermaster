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

<div class="main">
<nav>
    <div class="top">
    <ul class="top_nav">
        <li>
        <a onclick="show_hide_nav()"><img src="./static/images/menus.png" style="max-width: 5rem" title="https://www.flaticon.com/free-icons/hamburger Hamburger icons created by See Icons - Flaticon"></a>
        </li>
        <?php
            if (isset($_SESSION['username'])) {
                echo '<li style="float: right;"><a href=index.php?page=account>' . htmlspecialchars(string: $_SESSION['username']) . '</a></li><br>';
                echo '<li style="float: right;"><a href="logout.php">Logout</a></li>';
            }
        ?>
    </ul>
    </div>
</nav>
</div>
<script>
    function show_hide_nav() {
        var description = document.getElementById("my-sidenav");
        if (description.style.display === "none" || description.style.display === "") {
            description.style.display = "block"; // Show the sidenav
        } else {
            description.style.display = "none"; // Hide the sidenav
        }
    }
</script>