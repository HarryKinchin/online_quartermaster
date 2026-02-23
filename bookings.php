<div class="main">
<style>
    .bookings-grid-layout {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    grid-template-rows: repeat(2, 1fr);
    gap: 0.5rem;
    outline-width: 2px;
    outline-style: outset;
    outline-color: #868d97;
    margin-top: 0.5%;
    margin-bottom: 0.5%;
    padding-bottom: 0.5%;
    }

    .booker-name { grid-area: 1 / 1 / 2 / 2; }
    .booker-event-name { grid-area: 2 / 1 / 3 / 2; }
    .start-date { grid-area: 1 / 2 / 2 / 3; }
    .end-date { grid-area: 1 / 3 / 2 / 4; }
    .collection-date { grid-area: 2 / 2 / 3 / 3; }
    .dropoff-date { grid-area: 2 / 3 / 3 / 4; }
</style>
<?php
$stmt_bookings = $conn->prepare("SELECT * FROM bookings");
$stmt_bookings->execute();
$bookings_result = $stmt_bookings->get_result();
while ($row = mysqli_fetch_array($bookings_result, MYSQLI_NUM)) {
    $stmt_user = $conn->prepare("SELECT first_name, last_name FROM users WHERE user_id = $row[1]");     //converts user ID into their username
    $stmt_user->execute();
    $user_result = $stmt_user->get_result();
    $user_result = $user_result->fetch_array(MYSQLI_NUM);
    echo "<button class='accordion-header'>
        <h4>" . $row[2] . " starting: " . $row[3] . "
        </button>
        <div class='accordion-content'>
            <div class='bookings-grid-layout'>
                <div class='booker-name'>" . $user_result[0] . " " . $user_result[1] . "</div>
                <div class='booker-event-name'>" . $row[2] . "</div>
                <div class='start-date'>Event Start: " . $row[3] . "</div>
                <div class='end-date'>Event End: " . $row[4] . "</div>
                <div class='collection-date'>Collection: " . $row[5] . "</div>
                <div class='dropoff-date'>Return: " . $row[6] . "</div>
            </div>
        </div>";
}
?>
<script src="./static/js/faq_funcs.js"></script>
</div>