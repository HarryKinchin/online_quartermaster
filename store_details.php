<div class="main">
<?php
$stmt_category = $conn->prepare("SELECT category_name FROM categories");
$stmt_category->execute();
$category_result = $stmt_category->get_result();
while ($row = mysqli_fetch_array($category_result, MYSQLI_NUM)) {
        foreach ($row as $r) {
            print "$r, ";
        }
        print "<br>";
    }

$stmt_group = $conn->prepare("SELECT group_name, group_type FROM sections");
$stmt_group->execute();
$group_result = $stmt_group->get_result();

$stmt_equipment = $conn->prepare("SELECT item_code, item_name, item_desc, image_1 FROM items");
$stmt_equipment->execute();
$equipment_result = $stmt_equipment->get_result();

$stmt_category = $conn->prepare("SELECT category_name FROM categories");
$stmt_category->execute();
$category_result = $stmt_category->get_result();

$stmt_components = $conn->prepare("SELECT item_code, quantity, item_quality, item_location_code FROM components");
$stmt_components->execute();
$component_result = $stmt_components->get_result();
?>
</div>