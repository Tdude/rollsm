document.addEventListener("DOMContentLoaded", function() {

    const form = document.getElementById('competitors-registration-form');
    const validationMessageContainer = document.getElementById('validation-message');
    const submitButton = document.getElementById('submit-button');
    const licenseCheckboxDiv = document.getElementById('license-container');
    const radioButtons = document.querySelectorAll('input[type="radio"][name="participation_class"]');

    // Toggle visibility of validation messages. The success=true makes the box green, all other cases are red.
    function toggleValidationMessage(visible, success, message = '') {
        console.log("Toggling validation message visibility:", visible), message;
        validationMessageContainer.innerHTML = message;
        validationMessageContainer.classList[visible ? 'remove' : 'add']('hidden');
        validationMessageContainer.classList[success ? 'remove' : 'add']('danger');
    }


    // Append a new validation message to the container
    function appendValidationMessage(message) {
        const messageElement = document.createElement('p');
        messageElement.textContent = message;
        validationMessageContainer.appendChild(messageElement);
        toggleValidationMessage(true);
    }

    // Handle form submission
    async function handleSubmit(event) {
        event.preventDefault();
        submitButton.disabled = true;
        submitButton.value = 'Processing...';
        toggleValidationMessage(false);

        if (validateForm()) {
            submitForm();
        } else {
            resetSubmitButton();
        }
    }

    // Validate the entire form
    function validateForm() {
        const isValid = ['name', 'email', 'phone'].every(validateField) &&
                        validateParticipationClass() &&
                        validateConsent();
        return isValid;
    }

    // Validate individual fields
    function validateField(fieldName) {
        const field = form.querySelector(`[name="${fieldName}"]`);
        if (!field.value.trim()) {
            appendValidationMessage(`Form validation says: ${fieldName.charAt(0).toUpperCase() + fieldName.slice(1)} is required.`);
            return false;
        }
        return true;
    }

    // Validate participation class selection
    function validateParticipationClass() {
        if (!form.querySelector('input[name="participation_class"]:checked')) {
            appendValidationMessage("Participation class choice is required.");
            return false;
        }
        return true;
    }

    // Validate consent checkbox
    function validateConsent() {
        if (!form.querySelector('[name="consent"]').checked) {
            appendValidationMessage("Consent is required.");
            return false;
        }
        return true;
    }

    // Submit form data using Fetch API
    async function submitForm() {
        console.log("Handling form submission async"); 
        const formData = new FormData(form);
        formData.append('action', 'competitors_form_submit');
        formData.append('competitors_nonce', competitorsPublicAjax.nonce);

        try {
            const response = await fetch(competitorsPublicAjax.ajaxurl, {
                method: 'POST',
                credentials: 'same-origin',
                body: formData,
            });

            if (!response.ok) throw new Error('JS says: Network response was NOT ok.');

            const data = await response.json();
            if (data.success) {
                toggleValidationMessage(true, true, 'Your submission was successful! We will stay in touch via email.');
                // Handle post-submission logic (e.g., clear form, redirect slug from settings)
                form.reset();
                setTimeout(() => {
                    // @Todo: fix this!
                    window.location.href = `${WPSettings.baseURL}/${WPSettings.thankYouSlug}`;
                }, 5000);
            } else {
                throw new Error(data.data.message);
            }
        } catch (error) {
            appendValidationMessage(`There was a problem with your submission: ${error.message}`);
        } finally {
            resetSubmitButton();
        }
    }

    // Reset the submit button to its initial state
    function resetSubmitButton() {
        submitButton.disabled = false;
        submitButton.value = 'Submit';
        console.log('Submit reset');
    }



    // Handle visibility and class toggling for the license agreement section
    function toggleLicenseCheckbox() {
        const isChampionshipSelected = document.getElementById('championship').checked;
        const licenseCheckboxDiv = document.getElementById('license-container');
        const licenseCheckbox = document.getElementById('license-check');
        if (isChampionshipSelected) {
            // Show the license agreement section and remove the 'hidden' and 'border-danger' classes if needed
            toggleElementDisplay(licenseCheckboxDiv, '', 1, ['show'], ['hidden']);
        } else {
            // Hide the license agreement section, add the 'border-danger' class, and remove 'show' class
            toggleElementDisplay(licenseCheckboxDiv, 'none', 0, ['border-danger'], ['show']);
            // Uncheck the license agreement checkbox
            licenseCheckbox.checked = false;
        }
    }

    // Function to add or remove the border-danger class based on the checkbox state
    function toogleParentBorder() {
        document.querySelectorAll('.extra-visible input[type="checkbox"]').forEach(checkbox => {
            checkbox.addEventListener('change', function() {
                const parentDiv = this.closest('.extra-visible');
                if (this.checked) {
                    parentDiv.classList.remove('border-danger');
                } else {
                    parentDiv.classList.add('border-danger');
                }
            });
        });
    }

    // Call toogleParentBorder to attach the event listeners
    toogleParentBorder();



    // Attach the toggle function to each radio button's change event
    radioButtons.forEach(function(radioButton) {
        radioButton.addEventListener('change', toggleLicenseCheckbox);
    });

    // Initialize event listeners
    function initEventListeners() {
        form.addEventListener('submit', handleSubmit);
        radioButtons.forEach(button => button.addEventListener('change', toggleLicenseCheckbox));
        toggleLicenseCheckbox(); // Set initial state
    }

    initEventListeners();


    // Utility function to toggle display, opacity, and classes for elements
    function toggleElementDisplay(element, displayStyle, opacity = null, addClasses = [], removeClasses = []) {
        if (element) {
            element.style.display = displayStyle;
            if (opacity !== null) {
                requestAnimationFrame(() => {
                    element.style.opacity = opacity;
                });
            }
            addClasses.forEach(className => {
                element.classList.add(className);
            });
            removeClasses.forEach(className => {
                element.classList.remove(className);
            });
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

    // Show container (filled with per competitor data in competitors-table)
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
        fetch(competitorsPublicAjax.ajaxurl, {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded', },
            body: new URLSearchParams({
                'action': 'load_competitor_details',
                'competitor_id': competitorId,
                'security': competitorsPublicAjax.nonce
            })
        })
        .then(response => response.ok ? response.text() : Promise.reject(`JS: HTTP error! Status: ${response.status}`))
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
