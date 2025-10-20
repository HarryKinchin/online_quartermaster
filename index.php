<?php
session_start();

$is_logged_in = isset($_SESSION['user_id']);
if ($is_logged_in) {
    $page = isset($_GET['page']) ? $_GET['page'] : 'home';
} else {
    $page = 'login';
}

include_once("db_conn.php");
include_once("top.php");
include_once("sidebar.php");

switch ($page) {
    case "home":
        $page_group = "main";
        include_once("home.php");
        break;
    case "booking":
        $page_group = "bookings";
        include_once("booking_page.php");
        break;
    case "faq_contact":
        $page_group = "faq_contacts";
        include_once("faq_contact.php");
        break;
    case "login":
        $page_group = "login_page";
        include_once("login.php");
        break;
    case "account":
        $page_group ="account_page";
        include_once("account.php");
        break;
    case "store_details":
        $page_group = "store_details";
        include_once("store_details.php");
        break;
    default:
        $page_group = "main";
        include_once("home.php");
        break;
}

include_once("bottom.php");

?>