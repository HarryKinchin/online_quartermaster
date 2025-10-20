function get_items() {
    document.getElementById("info_form").style.display="none";
    document.getElementById("item_form").style.display="block";
    document.getElementById("form_title").innerHTML="Add items to booking";
}

function go_back() {
    document.getElementById("item_form").style.display="none";
    document.getElementById("info_form").style.display="block";
    document.getElementById("form_title").innerHTML="Booking info";
}

function toggleDescription(descId) {
    var description = document.getElementById(descId);
    if (description.style.display === "none" || description.style.display === "") {
        description.style.display = "block"; // Show the description
    } else {
        description.style.display = "none"; // Hide the description
    }
}