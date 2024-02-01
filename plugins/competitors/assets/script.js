// The js
console.log("I am public!");

document.addEventListener("DOMContentLoaded", function() {

    var masterCheckbox = document.getElementById('check_all');
    if (masterCheckbox) {
        masterCheckbox.addEventListener('change', function() {
            checkAll(this); // 'this' is the #check_all checkbox
        });
    }

    function checkAll(ele) {
        var checkboxes = document.querySelectorAll('input[type="checkbox"].roll-checkbox');
        if (checkboxes.length > 0) {
            for (var i = 0; i < checkboxes.length; i++) {
                if (checkboxes[i] !== ele) {
                    checkboxes[i].checked = ele.checked;
                }
            }
        }
    }


    var competitorsList = document.getElementById('competitors-list');
    // Check if the competitors-list exists before adding the event listener
    if (competitorsList) {
        competitorsList.addEventListener('click', function(e) {
            // Check if the clicked element has the class 'competitors-list-item'
            if (e.target && e.target.matches('.competitors-list-item')) {
                var competitorId = e.target.getAttribute('data-competitor-id');
                console.log(competitorId);

                // Remove 'current' class from any previously selected item
                var currentItem = document.querySelector('.competitors-list-item.current');
                if (currentItem) {
                    currentItem.classList.remove('current');
                }

                // Add 'current' class to the clicked item
                e.target.classList.add('current');

                // Perform the AJAX request using the Fetch API
                fetch(competitorsAjax.ajaxurl, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: 'action=load_competitor_details&competitor_id=' + encodeURIComponent(competitorId)
                })
                .then(response => response.text())
                .then(response => {
                    document.getElementById('competitors-details-container').innerHTML = response;
                })
                .catch(error => console.error('Error:', error));
            }
        });
    }
});
