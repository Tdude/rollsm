document.addEventListener("DOMContentLoaded", function() {
/*
    const registrationForm = document.getElementById('competitors-registration-form');
    if (registrationForm) {
        registrationForm.addEventListener('submit', function(event) {
            // Disable the ID submit-button to prevent multiple submissions
            const submitButton = document.getElementById('submit-button');
            event.preventDefault();
            submitButton.disabled = true;
            submitButton.value = 'Processing...';

            // Validation logic
            const notRequired = ['license', 'club', 'sponsors', 'speaker_info'];
            const inputs = registrationForm.querySelectorAll('input[type=text], textarea');
            let isFormValid = true;

            for (let i = 0; i < inputs.length; i++) {
                if (notRequired.indexOf(inputs[i].name) === -1 && !inputs[i].value.trim()) {
                    // Show validation message and prevent form submission
                    const validationMessage = document.getElementById('validation-message');
                    validationMessage.classList.remove('hidden');
                    setTimeout(function() {
                        validationMessage.classList.add('hidden');
                    }, 10000); // Hide the message after 10 seconds

                    isFormValid = false;
                    break; // Exit loop on first validation failure
                }
            }

            if (!isFormValid) {
                event.preventDefault();
                // Re-enable the submit button for user to correct and resubmit
                submitButton.disabled = false;
                submitButton.value = 'Submit';
                return false;
            }
            // If form is valid, allow submission (submitButton remains disabled to prevent resubmit)
        });

        // Prevent form submission on Enter key in text inputs
        registrationForm.addEventListener('keydown', function(event) {
            if (event.key === "Enter" && event.target.tagName !== 'TEXTAREA') {
                event.preventDefault();
                return false;
            }
        });
    }
*/



    const masterCheckbox = document.getElementById('check_all');
    if (masterCheckbox) {
        masterCheckbox.addEventListener('change', function() {
            checkAll(this);
        });
    }

    function checkAll(ele) {
        const checkboxes = document.querySelectorAll('input[type="checkbox"].roll-checkbox');
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


    function showDetailsContainer() {
        const detailsContainer = document.getElementById('competitors-details-container');
        detailsContainer.style.display = 'block'; // block/flex
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
    
                fetch(competitorsAjax.ajaxurl, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: new URLSearchParams({
                        'action': 'load_competitor_details',
                        'competitor_id': competitorId,
                        'security': competitorsAjax.nonce
                    })
                })
                .then(response => {
                    if (!response.ok) throw new Error(`HTTP error! Status: ${response.status}`);
                    return response.text();
                })
                .then(html => {
                    document.getElementById('competitors-details-container').innerHTML = html;
                    showDetailsContainer();
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
            //document.getElementById('competitors-details-container').innerHTML = ''; // Optional, to clear shit totally
            hideSpinner(); 
            var currentItem = document.querySelector('.competitors-list-item.current');
            if (currentItem) {
                currentItem.classList.remove('current');
            }
        });
    }


});
