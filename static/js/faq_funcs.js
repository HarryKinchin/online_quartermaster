document.addEventListener('DOMContentLoaded', function() {
    const accordionHeaders = document.querySelectorAll('.accordion-header');

    accordionHeaders.forEach(header => {
        header.addEventListener('click', function() {
            // Get the content for the clicked header
            const content = this.nextElementSibling;

            // Check if the current header is already active
            const isActive = this.classList.contains('active');

            // Close all other accordion items
            document.querySelectorAll('.accordion-content').forEach(item => {
                item.style.maxHeight = 0;
            });
            document.querySelectorAll('.accordion-header').forEach(item => {
                item.classList.remove('active');
            });

            // If the clicked header was not active, open it
            if (!isActive) {
                this.classList.add('active');
                content.style.maxHeight = content.scrollHeight + 'px';
            }
        });
    });
});