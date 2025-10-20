<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <meta content="width=device-width, initial-scale=1.0" name="viewport">
    <title>Online QuarterMaster</title>
    <link rel="icon" type="image/x-icon" href="/online_quartermaster/static/images/favicon.ico">
    <link rel="apple-touch-icon" href="/online_quartermaster/static/images/apple-touch-icon.png">
    <link rel="stylesheet" type="text/css" href="/online_quartermaster/static/css/stylings.css">
</head>

<body>

<div class="main">
<nav>
    <div class="top">
    <ul class="top_nav">
        <li>
        <a href="index.php?page=home"><img src="/online_quartermaster/static/images/logo.png"></a>
        </li>
        <?php
            if (isset($_SESSION['username'])) {
                echo '<li style="float: right;"><a href=index.php?page=account>' . htmlspecialchars(string: $_SESSION['username']) . '</a></li>';
                echo '<li style="float: right;"><a href="logout.php">Logout</a></li>';
            }
        ?>
    </ul>
    </div>
</nav>
</div>
