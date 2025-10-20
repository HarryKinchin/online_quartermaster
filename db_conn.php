<?php
$servername = "localhost";
$username = "admin";
$password = "Mp_26u44u@d)JBqH";
$dbname = "scout_bookings";

// Create connection
$conn = new mysqli(hostname: $servername, username: $username, password: $password, database: $dbname);
// Check connection
if ($conn->connect_error) {
  die("Connection failed: " . $conn->connect_error);
}
?>