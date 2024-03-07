document.addEventListener("DOMContentLoaded", function() {

    const registrationForm = document.getElementById('competitors-registration-form');
    const validationMessage = document.getElementById('validation-message');
    const submitButton = document.getElementById('submit-button');

    function toggleValidationMessage(show, message = '') {
        validationMessage.innerHTML = message; // Set or clear the message
        validationMessage.classList[show ? 'remove' : 'add']('hidden');
    }

    function appendValidationMessage(message) {
        const newMessage = document.createElement('p');
        newMessage.textContent = message;
        validationMessage.appendChild(newMessage);
        toggleValidationMessage(true); // Make sure the message container is visible
        console.log(`Appending validation message: ${message}`);
    }

    async function handleSubmit(e) {
        e.preventDefault(); // Prevent the form from submitting traditionally
        console.log('Form submit event triggered');

        submitButton.disabled = true;
        submitButton.value = 'Processing...';
        validationMessage.innerHTML = ''; // Clear previous messages
        toggleValidationMessage(false); // Hide the message area initially

        let isFormValid = validateForm();

        if (isFormValid) {
            try {
                const formData = prepareFormData();
                const response = await fetch(competitorsAjax.ajaxurl, {
                    method: 'POST',
                    credentials: 'same-origin',
                    body: formData,
                });

                if (!response.ok) throw new Error('JS says: Network response was not ok.');

                const data = await response.json();

                if (data.success) {
                    console.log('Success:', data);
                    toggleValidationMessage(true, 'JS says: Submission successful!');
                    // Optionally, clear the form or redirect the user
                } else {
                    console.error('Error:', data.data.message);
                    toggleValidationMessage(true, data.data.message);
                }
            } catch (error) {
                console.error('Error:', error);
                toggleValidationMessage(true, 'JS says: There was a problem with your submission. Please try again.');
            } finally {
                resetSubmitButton();
            }
        } else {
            resetSubmitButton();
        }
    }

    function validateForm() {
        let isValid = true;
        ['name', 'email', 'phone'].forEach(field => {
            if (!validateField(field)) isValid = false;
        });

        if (!validateParticipationClass() || !validateConsent()) isValid = false;

        return isValid;
    }

    function validateField(fieldName) {
        const field = registrationForm.querySelector(`[name="${fieldName}"]`);
        if (!field.value.trim()) {
            appendValidationMessage(`${fieldName.charAt(0).toUpperCase() + fieldName.slice(1)} is required.`);
            return false;
        }
        return true;
    }

    function validateParticipationClass() {
        if (!registrationForm.querySelector('input[name="participation_class"]:checked')) {
            appendValidationMessage("JS says: Participation class choice is required.");
            return false;
        }
        return true;
    }

    function validateConsent() {
        if (!registrationForm.querySelector('[name="consent"]').checked) {
            appendValidationMessage("JS says: Consent is required.");
            return false;
        }
        return true;
    }

    function prepareFormData() {
        const formData = new FormData(registrationForm);
        formData.append('action', 'competitors_form_submit');
        formData.append('competitors_nonce', competitorsAjax.nonce);
        return formData;
    }

    function resetSubmitButton() {
        submitButton.disabled = false;
        submitButton.value = 'Submit';
    }

    registrationForm.addEventListener('submit', handleSubmit);





    // Utility function to toggle display and opacity for elements
    function toggleElementDisplay(element, displayStyle, opacity = null) {
        if (element) {
            element.style.display = displayStyle;
            if (opacity !== null) {
                requestAnimationFrame(() => {
                    element.style.opacity = opacity;
                });
            }
        }
    }



    // Handle row clicks to toggle individual checkboxes
    const rows = document.querySelectorAll('.clickable-row');
    rows.forEach(row => {
        row.addEventListener('click', function(event) {
            // Prevent toggling if the clicked element is a checkbox
            if (event.target.type !== 'checkbox') {
                const checkbox = this.querySelector('.roll-checkbox');
                if (checkbox) {
                    checkbox.checked = !checkbox.checked;
                    // Trigger the change event on the checkbox
                    checkbox.dispatchEvent(new Event('change'));
                }
            }
        });
    });

    // Master checkbox functionality
    const masterCheckbox = document.getElementById('check_all');
    masterCheckbox?.addEventListener('change', function() {
        const checkboxes = document.querySelectorAll('input[type="checkbox"].roll-checkbox');
        checkboxes.forEach(checkbox => {
            // Check or uncheck all except the master checkbox itself
            if (checkbox !== masterCheckbox) {
                checkbox.checked = masterCheckbox.checked;
                // Trigger the change event on each checkbox to handle related changes
                checkbox.dispatchEvent(new Event('change'));
            }
        });
    });

    // Optional: Listen to individual checkbox changes if you need to do something when they change
    const checkboxes = document.querySelectorAll('input[type="checkbox"].roll-checkbox');
    checkboxes.forEach(checkbox => {
        checkbox.addEventListener('change', function() {
            // Implement any additional logic needed when a checkbox changes
            // For example, updating a counter, changing styles, etc.
        });
    });



    // Spinner display controls
    const spinner = document.getElementById("spinner");
    const showSpinner = () => toggleElementDisplay(spinner, 'flex', '1');
    const hideSpinner = () => {
        spinner.style.opacity = '0';
        spinner.addEventListener('transitionend', function handler(e) {
            if (e.propertyName === 'opacity') {
                spinner.style.display = 'none';
                spinner.removeEventListener('transitionend', handler);
            }
        });
    };

    // Show competitors details container
    const detailsContainer = document.getElementById('competitors-details-container');
    const showDetailsContainer = () => toggleElementDisplay(detailsContainer, 'block');

    // Handle competitor list item click
    const competitorsList = document.getElementById('competitors-list');
    competitorsList?.addEventListener('click', function (e) {
        const target = e.target.closest('.competitors-list-item');
        if (target) {
            handleCompetitorSelection(target);
        }
    });

    // Fetch and display competitor details
    function handleCompetitorSelection(target) {
        showSpinner();
        const competitorId = target.getAttribute('data-competitor-id');
        document.querySelectorAll('.competitors-list-item.current').forEach(item => item.classList.remove('current'));
        target.classList.add('current');

        fetchCompetitorDetails(competitorId);
    }

    // Fetch competitor details from server
    function fetchCompetitorDetails(competitorId) {
        fetch(competitorsAjax.ajaxurl, {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded', },
            body: new URLSearchParams({
                'action': 'load_competitor_details',
                'competitor_id': competitorId,
                'security': competitorsAjax.nonce
            })
        })
        .then(response => response.ok ? response.text() : Promise.reject(`HTTP error! Status: ${response.status}`))
        .then(html => {
            detailsContainer.innerHTML = html;
            showDetailsContainer();
            hideSpinner();
            document.getElementById('close-details').scrollIntoView({ behavior: 'smooth' });
        })
        .catch(error => {
            console.error('Fetch Error:', error);
            hideSpinner();
        });
    }

    // Close and clear details container
    const closeDetails = document.getElementById('close-details');
    closeDetails?.addEventListener('click', function (e) {
        e.preventDefault();
        toggleElementDisplay(detailsContainer, 'none');
        detailsContainer.innerHTML = '';
        hideSpinner();
        document.querySelector('.competitors-list-item.current')?.classList.remove('current');
        competitorsList.scrollIntoView({ behavior: 'smooth' });
    });


});
