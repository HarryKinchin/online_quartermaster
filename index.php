<?php
session_start();

include_once("db_conn.php");

$is_logged_in = isset($_SESSION['user_id']);
if ($is_logged_in) {
    if(isset($_GET['process'])){
        $process = $_GET['process'];
    }elseif(isset($_GET['page'])){;
        $page = isset($_GET['page']) ? $_GET['page'] : 'home';
    }else{
        $page = 'login';
    }
} else {
    $page = 'login';
}


if(isset($process)){

     switch ($process) {
        case "formchanges":
            include_once("formchanges.php");
            break;
        default:
            echo 'how quaint';
            break;
     }


}else{

    include_once("top.php");
    include_once("sidebar.php");

    switch ($page) {
        case "home":
            include_once("home.php");
            break;
        case "create_booking":
            include_once("booking_creation.php");
            include_once("booking_page.php");
            break;
        case "booking":
            include_once("booking_page.php");
            break;
        case "bookings":
            include_once("bookings.php");
            break;
        case "faq_contact":
            include_once("faq_contact.php");
            break;
        case "login":
            include_once("login.php");
            break;
        case "account":
            include_once("account.php");
            break;
        case "store_details":
            include_once("under_construction.php");
            break;
        case "reports":
            include_once("under_construction.php");
            break;
        default:
            include_once("home.php");
            break;
    }

    include_once("bottom.php");

}



?>