document.addEventListener('DOMContentLoaded', function() {
    const bookingIdField = document.getElementById('booking_id');
    const bookingId = bookingIdField ? bookingIdField.value : null;

    // If no booking ID, we are in "Create" mode, so don't attach AJAX listeners
    if (!bookingId) return;

    // Listen for changes on BOTH forms
    const forms = document.querySelectorAll('#details-form, #items-form');

    forms.forEach(form => {
        form.addEventListener('change', function(event) {
            const field = event.target;
            
            // Prepare data
            let formData = new FormData();
            formData.append('bookingID', bookingId);
            formData.append('fieldName', field.name);
            formData.append('fieldValue', field.value);

            fetch('formchanges.php', {
                method: "POST",
                body: formData
            })
            .then(response => response.text())
            .then(text => {
                console.log("Server Response:", text);
                field.classList.add('updated');
                setTimeout(() => field.classList.remove('updated'), 2000);
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Connection lost. Changes not saved!');
            });
        });
    });
});