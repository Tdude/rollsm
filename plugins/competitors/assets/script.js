document.addEventListener("DOMContentLoaded", () => {
  const form = document.getElementById("competitors-registration-form");
  const validationMessageContainer = form.querySelector("#validation-message");
  const submitButton = form.querySelector("#submit-button");
  const radioButtons = form.querySelectorAll(
    'input[type="radio"][name="participation_class"]'
  );

  function setupEventListeners() {
    form.addEventListener("submit", handleSubmit);
    form
      .querySelectorAll('.extra-visible input[type="checkbox"]')
      .forEach((checkbox) => {
        checkbox.addEventListener("change", (event) =>
          toggleClass(
            event.target.closest(".extra-visible"),
            "border-danger",
            !event.target.checked
          )
        );
      });
    const radioButtons = form.querySelectorAll(
      'input[type="radio"][name="participation_class"]'
    );
    radioButtons.forEach((radio) =>
      radio.addEventListener("change", toggleLicenseCheckbox)
    );
    toggleLicenseCheckbox(); // Set initial state
  }

  async function handleSubmit(event) {
    event.preventDefault();
    showButtonLoading(submitButton, true);

    if (validateForm()) {
      showSpinner();
      try {
        await submitForm();
      } catch (error) {
        console.error("Failed to submit form:", error);
        alert("Failed to submit form, please try again.");
        showButtonLoading(submitButton, false);
      }
      // Assuming page will redirect and thus not requiring hiding the spinner
    } else {
      showButtonLoading(submitButton, false);
    }
  }

  function validateForm() {
    closeValidationMessage();
    let isValid = true;

    // Validate required fields
    ["phone", "email", "name"].forEach((fieldName) => {
      const field = form.querySelector(`[name="${fieldName}"]`);
      if (!field.value.trim()) {
        const capitalizedMessage =
          fieldName.charAt(0).toUpperCase() + fieldName.slice(1);
        displayValidationMessage(capitalizedMessage + " is required.", false);
        toggleClass(field, "border-danger", true);
        isValid = false;
      } else {
        toggleClass(field, "border-danger", false);
      }
    });

    // Validate participation class and consent
    isValid &= validateRadioSection(
      "participation_class",
      "You are required to choose which class to participate in."
    );
    isValid &= validateCheckbox("consent", "Your consent is required.");

    // Validate the selected date
    const dateField = form.querySelector("#competition_date");
    const selectedDate = dateField.value.trim();

    if (!selectedDate) {
      displayValidationMessage("A competition date is required.", false);
      toggleClass(dateField, "border-danger", true);
      isValid = false;
    } else {
      toggleClass(dateField, "border-danger", false);
    }

    return isValid;
  }

  function validateRadioSection(name, message) {
    const container = form.querySelector(`#${name}-container`);
    if (!form.querySelector(`input[name="${name}"]:checked`)) {
      displayValidationMessage(message, false);
      toggleClass(container, "border-danger", true);
      return false;
    } else {
      toggleClass(container, "border-danger", false);
      return true;
    }
  }

  function validateCheckbox(name, message) {
    const checkbox = form.querySelector(`[name="${name}"]`);
    const container = checkbox.closest(".form-group");
    if (!checkbox.checked) {
      displayValidationMessage(message, false);
      toggleClass(container, "border-danger", true);
      return false;
    } else {
      toggleClass(container, "border-danger", false);
      return true;
    }
  }

  function toggleLicenseCheckbox() {
    const championshipSelected = form.querySelector("#championship").checked;
    const licenseContainer = form.querySelector("#license-container");
    toggleClass(licenseContainer, "hidden", !championshipSelected);
  }

  function toggleClass(element, className, condition) {
    if (element && condition) {
      element.classList.add(className);
    } else if (element) {
      element.classList.remove(className);
    }
  }

  function displayValidationMessage(message, isSuccess) {
    validationMessageContainer.textContent = message;
    validationMessageContainer.classList.toggle("hidden", false);
    validationMessageContainer.classList.toggle("danger", !isSuccess);
    validationMessageContainer.classList.toggle("success", isSuccess);
  }

  function handleValidationMessage({
    message = "Ärrår",
    success = true,
    show = false,
    fadeOut = true,
  }) {
    validationMessageContainer.querySelector(".message-content").textContent =
      message;
    validationMessageContainer.classList.toggle("hidden", !show);
    validationMessageContainer.classList.toggle("danger", !success && show);
    validationMessageContainer.classList.toggle("success", success && show);

    if (fadeOut) {
      validationMessageContainer.classList.add("fade-out");
      setTimeout(() => closeValidationMessage(), 8000);
    }
  }

  function closeValidationMessage() {
    validationMessageContainer.classList.add("hidden");
  }

  function showButtonLoading(button, isLoading) {
    button.disabled = isLoading;
    button.value = isLoading ? "Processing..." : "Submit";
  }

  async function submitForm() {
    //console.log("Handling form submission async");
    const formData = new FormData(form);
    formData.append("action", "competitors_form_submit");
    formData.append("competitors_nonce", competitorsPublicAjax.nonce);

    try {
      const response = await fetch(competitorsPublicAjax.ajaxurl, {
        method: "POST",
        credentials: "same-origin",
        body: formData,
      });

      if (!response.ok)
        throw new Error("Oops! Server not reachable. Please try again later.");

      const data = await response.json();
      if (!data.success) {
        throw new Error(`Error from server: ${data.data.message}`);
      } else {
        handleValidationMessage(
          "Yay! Your submission was successful. We will stay in touch via email.",
          true,
          true,
          true
        );

        form.reset();
        setTimeout(() => {
          window.location.href = `${competitorsPublicAjax.baseURL}/${competitorsPublicAjax.thankYouSlug}`;
        }, 5000);
      }
    } catch (error) {
      console.error("Error during form submission:", error);
      let errorMessage = "Oops! There was a problem with your submission."; // Default message
      if (error.message) {
        errorMessage = `Oops! There was a problem with your submission: ${error.message}`;
      }
      handleValidationMessage(errorMessage, false, true, true);
    } finally {
      resetSubmitButton();
    }
  }

  function resetSubmitButton() {
    submitButton.removeAttribute("disabled");
    submitButton.value = "Submit";
  }

  setupEventListeners();

  // Attach the toggle function
  radioButtons.forEach(function (radioButton) {
    radioButton.addEventListener("change", toggleLicenseCheckbox);
  });

  // Utility function to toggle display, opacity, and classes for elements
  function toggleElementDisplay(
    element,
    displayStyle,
    opacity = null,
    addClasses = [],
    removeClasses = []
  ) {
    if (element) {
      element.style.display = displayStyle;
      if (opacity !== null) {
        requestAnimationFrame(() => {
          element.style.opacity = opacity;
        });
      }
      addClasses.forEach((className) => {
        element.classList.add(className);
      });
      removeClasses.forEach((className) => {
        element.classList.remove(className);
      });
    }
  }

  // Handle row and checkbox interactions
  const rows = form.querySelectorAll(".clickable-row");
  rows.forEach((row) => {
    row.addEventListener("click", function (event) {
      const isCheckbox = event.target.type === "checkbox";
      const checkbox = isCheckbox
        ? event.target
        : this.querySelector(".roll-checkbox");
      if (checkbox) {
        if (!isCheckbox) {
          checkbox.checked = !checkbox.checked;
        }
        toggleClass(checkbox.closest("tr"), "grayed-out", !checkbox.checked);
        // If the checkbox state was changed manually, trigger the change event
        if (!isCheckbox) {
          checkbox.dispatchEvent(new Event("change", { bubbles: true }));
        }
      }
    });
  });

  // "Master" checkbox functionality
  const masterCheckbox = form.querySelector("#check_all");
  masterCheckbox?.addEventListener("change", function () {
    const checkboxes = form.querySelectorAll(
      'input[type="checkbox"].roll-checkbox'
    );
    checkboxes.forEach((checkbox) => {
      // Check or uncheck all except the master checkbox itself
      if (checkbox !== masterCheckbox) {
        checkbox.checked = masterCheckbox.checked;
        checkbox.dispatchEvent(new Event("change"));
      }
    });
  });

  // Spinner display controls
  const spinner = document.getElementById("spinner");
  const showSpinner = () => toggleElementDisplay(spinner, "flex", "1");
  const hideSpinner = () => {
    spinner.style.opacity = "0";
    spinner.addEventListener("transitionend", function handler(e) {
      if (e.propertyName === "opacity") {
        spinner.style.display = "none";
        spinner.removeEventListener("transitionend", handler);
      }
    });
  };

  // Utility for toggling display attributes
  function toggleElementDisplay(element, displayStyle, opacity) {
    element.style.display = displayStyle;
    element.style.opacity = opacity;
  }

  // Function to dynamically add the close event listener
  const detailsContainer = document.getElementById(
    "competitors-details-container"
  );
  function addCloseButtonListener() {
    const closeDetailsButton = document.getElementById("close-details");
    closeDetailsButton.addEventListener(
      "click",
      (e) => {
        e.preventDefault();
        detailsContainer.style.display = "none";
        detailsContainer.innerHTML = "";
        document
          .getElementById("competitors-list")
          .scrollIntoView({ behavior: "smooth" });
      },
      { once: true }
    );
  }
  // Utility for fetching competitor data
  function fetchCompetitorsData(url, params) {
    return fetch(url, {
      method: "POST",
      headers: { "Content-Type": "application/x-www-form-urlencoded" },
      body: new URLSearchParams(params),
    }).then((response) => {
      if (!response.ok) {
        throw new Error(`HTTP error, status = ${response.status}`);
      }
      const contentType = response.headers.get("content-type");
      if (contentType && contentType.includes("application/json")) {
        return response.json();
      } else {
        return response.text(); // Parse as text (HTML)
      }
    });
  }

  // Function to fetch and display competitor details
  function fetchCompetitorDetails(competitorId) {
    const params = {
      action: "load_competitor_details",
      competitor_id: competitorId,
      security: competitorsPublicAjax.nonce,
    };

    showSpinner();
    fetchCompetitorsData(competitorsPublicAjax.ajaxurl, params)
      .then((html) => {
        if (!html.trim()) {
          detailsContainer.innerHTML =
            "<p>No details available for this competitor.</p>";
        } else {
          detailsContainer.innerHTML = html;
        }
        detailsContainer.style.display = "block";
        document
          .getElementById("close-details")
          .scrollIntoView({ behavior: "smooth" });
        addCloseButtonListener();
      })
      .catch((error) => {
        console.error("Fetch Error:", error);
        alert("Error loading details: " + error.message);
      })
      .finally(() => {
        hideSpinner();
      });
  }

  // Event listener for clicks on competitor list items
  document
    .getElementById("competitors-list")
    .addEventListener("click", (event) => {
      const target = event.target.closest(".competitors-list-item");
      if (target) {
        fetchCompetitorDetails(target.getAttribute("data-competitor-id"));
      }
    });

  // Handle date dropdown selection
  document
    .getElementById("date-select")
    .addEventListener("change", function () {
      const selectedDate = this.value;
      const params = {
        action: "load_competitors_list",
        date_select: selectedDate,
        security: competitorsPublicAjax.nonce,
      };

      showSpinner();
      fetchCompetitorsData(competitorsPublicAjax.ajaxurl, params)
        .then((data) => {
          // Reference and clear content before inserting new data
          const competitorsTable = document.querySelector(".competitors-table");
          competitorsTable.innerHTML = ""; // Clear the existing content

          if (typeof data === "string") {
            if (
              !data.trim() ||
              data.trim() === '<ul class="competitors-table"></ul>'
            ) {
              competitorsTable.innerHTML =
                "<p>No competitors registered for this date.</p>";
            } else {
              competitorsTable.innerHTML = data;
            }
          } else if (data.success && data.data.content) {
            competitorsTable.innerHTML = data.data.content;
          } else {
            console.error("Error or missing content:", data.message);
            throw new Error(data.message || "Missing content.");
          }
        })
        .catch((error) => {
          console.error("Fetch Error:", error);
          alert("Error loading competitors: " + error.message);
        })
        .finally(() => {
          hideSpinner();
        });
    });
});
