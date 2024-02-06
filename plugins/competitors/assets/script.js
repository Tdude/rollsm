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

    const competitorsList = document.getElementById('competitors-list');
    const spinner = document.getElementById("spinner");


    // Show the details container
    function showDetailsContainer() {
        const detailsContainer = document.getElementById('competitors-details-container');
        detailsContainer.style.display = 'block'; // Adjust as needed
    }

    function showSpinner() {
        spinner.style.display = 'flex';
        requestAnimationFrame(() => {
            spinner.style.opacity = '1';
        });
    }
    
    function hideSpinner() {
        spinner.style.opacity = '0';
        spinner.addEventListener('transitionend', function handler(e) {
            if (e.propertyName === 'opacity') {
                spinner.style.display = 'none';
                spinner.removeEventListener('transitionend', handler);
            }
        });
    }



    if (competitorsList) {
        competitorsList.addEventListener('click', function(e) {
            const target = e.target.closest('.competitors-list-item'); // Use closest to ensure clicks on child elements are captured
            if (target) {
                showSpinner();
    
                const competitorId = target.getAttribute('data-competitor-id');
                document.querySelectorAll('.competitors-list-item.current').forEach(item => item.classList.remove('current')); // Clear current from all items
                target.classList.add('current'); // Set current class to clicked item
    
                // Corrected AJAX URL property based on standard naming
                fetch(competitorsAjax.ajaxurl, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: new URLSearchParams({
                        'action': 'load_competitor_details',
                        'competitor_id': competitorId,
                        'security': competitorsAjax.nonce // Ensure nonce is sent correctly for security
                    })
                })
                .then(response => {
                    if (!response.ok) throw new Error(`HTTP error! Status: ${response.status}`);
                    return response.text();
                })
                .then(html => {
                    document.getElementById('competitors-details-container').innerHTML = html;
                    showDetailsContainer(); // Ensure this function exists and correctly handles the display logic
                    hideSpinner();
                })
                .catch(error => {
                    console.error('Fetch Error:', error);
                    hideSpinner();
                });
            }
        });
    }
    

    
    const closeDetails = document.getElementById('close-details');
    if (closeDetails) {
        closeDetails.addEventListener('click', function(e) {
            e.preventDefault();
            document.getElementById('competitors-details-container').style.display = 'none';
            //document.getElementById('competitors-details-container').innerHTML = ''; // Optional
            hideSpinner(); 
            var currentItem = document.querySelector('.competitors-list-item.current');
            if (currentItem) {
                currentItem.classList.remove('current');
            }
        });
    }


});
