<div class="main">
<?php
$sql = "SELECT * FROM items";
$result = $conn->query(query: $sql);

$default_image_path = "static/images/not-found.svg"; 

if ($result->num_rows > 0) {
  while($row = $result->fetch_assoc()) {
    if (!empty($row["image_1"])) {
    $image_src = $row["image_1"];
    } else {
    $image_src = $default_image_path;
    }

    echo $row["item_name"]. " (" . $row["item_desc"]. ')';
        echo '<img src="'.htmlspecialchars(string: $image_src).'" style="max-width:100px;max-height:100px" alt="'.htmlspecialchars(string: $row["item_name"]).'"><br>';
    }
  } else {
  echo "0 results";
}
?>
</div>