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
    margin-top: 1rem;
    margin-bottom: 1rem;
    padding-bottom: 1rem;
    }

    .booker-info { grid-area: 1 / 1 / 2 / 4; place-self: center stretch; justify-self: stretch;}
    .edit-booking { grid-area: 2 / 2 / 3 / 3 ; margin: auto; padding: 0rem; width: 100%;}
</style>
<?php
$stmt_bookings = $conn->prepare("SELECT * FROM bookings LEFT JOIN sections ON bookings.group_name = sections.group_name");
$stmt_bookings->execute();
$bookings_result = $stmt_bookings->get_result();
while ($row = mysqli_fetch_array($bookings_result, MYSQLI_BOTH)) {
    $stmt_user = $conn->prepare("SELECT first_name, last_name FROM users WHERE user_id = $row[1]");     //converts user ID into their username
    $stmt_user->execute();
    $user_result = $stmt_user->get_result();
    $user_result = $user_result->fetch_array(MYSQLI_NUM);
    if($row['group_name']  == $row['group_type']){
        $displayValue = $row['group_name'] ;
    } else {
        $displayValue = $row['group_name']  . ' ' . $row['group_type'];
    }
    echo "<button class='accordion-header' id=" . $row[0] . ">
        " . $row[2] . " starting: " . $row[3] . "
        </button>
        <div class='accordion-content'>
            <div class='bookings-grid-layout'>
                <div class='booker-info'>".$row['event_name'] ." ~ ". $displayValue . "<br>
                ".date_format(date_create($row['event_start']),"d/m/y ")." - ". date_format(date_create($row['event_end']),"d/m/y ")."</div> 
                <button class='edit-booking' type='button'onclick='edit_items(". $row[0] . ")'>Edit</button>
            </div>
        </div>";
}
?>
<script src="./static/js/faq_funcs.js"></script>
<script>
    function edit_items($bookingID) {
        window.location.replace(`index.php?page=booking&bookingID=${$bookingID}`)
    };
</script>
</div>