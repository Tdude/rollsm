// JS or stuff
document.addEventListener("DOMContentLoaded", () => {
  const form = document.getElementById("competitors-registration-form");
  const validationMessageContainer = form.querySelector("#validation-message");
  const submitButton = form.querySelector("#submit-button");
  const radioButtons = form.querySelectorAll(
    'input[type="radio"][name="participation_class"]'
  );
  const spinner = document.getElementById("spinner");

  if (!form) {
    return;
  }

  // Utility Functions
  function toggleClass(element, className, condition) {
    if (element) {
      element.classList.toggle(className, condition);
    }
  }

  function showButtonLoading(button, isLoading) {
    button.disabled = isLoading;
    button.value = isLoading ? "Processing..." : "Submit";
  }

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
      addClasses.forEach((className) => element.classList.add(className));
      removeClasses.forEach((className) => element.classList.remove(className));
    }
  }

  function displayValidationMessage(message, isSuccess) {
    validationMessageContainer.textContent = message;
    validationMessageContainer.classList.remove("hidden");
    validationMessageContainer.classList.toggle("danger", !isSuccess);
    validationMessageContainer.classList.toggle("success", isSuccess);
  }

  function closeValidationMessage() {
    validationMessageContainer.classList.add("hidden");
    validationMessageContainer.textContent = "";
    validationMessageContainer.classList.remove(
      "danger",
      "success",
      "fade-out"
    );
  }

  function handleValidationMessage({
    message = "Error",
    success = true,
    show = false,
    fadeOut = true,
  }) {
    const messageContent =
      validationMessageContainer.querySelector(".message-content");
    if (messageContent) {
      messageContent.textContent = message;
    } else {
      validationMessageContainer.textContent = message;
    }
    validationMessageContainer.classList.toggle("hidden", !show);
    validationMessageContainer.classList.toggle("danger", !success && show);
    validationMessageContainer.classList.toggle("success", success && show);

    if (fadeOut) {
      validationMessageContainer.classList.add("fade-out");
      setTimeout(() => closeValidationMessage(), 8000);
    }
  }

  function validateForm() {
    closeValidationMessage();
    let isValid = true;

    ["phone", "email", "name"].forEach((fieldName) => {
      const field = form.querySelector(`[name="${fieldName}"]`);
      if (!field.value.trim()) {
        displayValidationMessage(
          `${
            fieldName.charAt(0).toUpperCase() + fieldName.slice(1)
          } is required.`,
          false
        );
        toggleClass(field, "border-danger", true);
        isValid = false;
      } else {
        toggleClass(field, "border-danger", false);
      }
    });

    isValid &= validateRadioSection(
      "participation_class",
      "You are required to choose which class to participate in."
    );
    isValid &= validateCheckbox("consent", "Your consent is required.");

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

  function resetSubmitButton() {
    submitButton.removeAttribute("disabled");
    submitButton.value = "Submit";
  }

  async function submitForm() {
    const formData = new FormData(form);
    formData.append("action", "competitors_form_submit");
    formData.append("competitors_nonce", competitorsPublicAjax.nonce);

    try {
      const response = await fetch(competitorsPublicAjax.ajaxurl, {
        method: "POST",
        credentials: "same-origin",
        body: formData,
      });

      if (!response.ok) {
        throw new Error("Oops! Server not reachable. Please try again later.");
      }

      let data;
      try {
        data = await response.json();
      } catch (e) {
        throw new Error("Invalid server response. Please try again later.");
      }

      if (!data.success) {
        throw new Error(`Error from server: ${data.data.message}`);
      } else {
        handleValidationMessage({
          message: `Yay, thank you for your registration! Your total fee is SEK ${data.data.total_sum}. Please check your inbox and spam folder for a confirmation. Pay with Swish on the next page.`,
          success: true,
          show: true,
          fadeOut: true,
        });

        form.reset();
        setTimeout(() => {
          window.location.href = data.data.redirect_url;
        }, 2000);
      }
    } catch (error) {
      console.error("Error during form submission:", error);
      let errorMessage = "Oops! There was a problem with your submission.";
      if (error.message) {
        errorMessage = `Oops! ${error.message}`;
      }
      handleValidationMessage({
        message: errorMessage,
        success: false,
        show: true,
        fadeOut: true,
      });
    } finally {
      resetSubmitButton();
    }
  }

  async function handleSubmit(event) {
    event.preventDefault();
    showButtonLoading(submitButton, true);

    if (validateForm()) {
      try {
        await submitForm();
      } catch (error) {
        console.error("Failed to submit form:", error);
        alert("Failed to submit form, please try again.");
        showButtonLoading(submitButton, false);
      }
    } else {
      showButtonLoading(submitButton, false);
    }
  }

  function toggleLicenseCheckbox() {
    const championshipSelected = form.querySelector("#championship").checked;
    const licenseContainer = form.querySelector("#license-container");
    toggleClass(licenseContainer, "hidden", !championshipSelected);
  }

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
    radioButtons.forEach((radio) =>
      radio.addEventListener("change", toggleLicenseCheckbox)
    );
    toggleLicenseCheckbox();
  }

  function addRowEventListeners(row) {
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
        if (!isCheckbox) {
          checkbox.dispatchEvent(new Event("change", { bubbles: true }));
        }
      }
    });
  }

  function setupRowsAndCheckboxes() {
    const rows = form.querySelectorAll(".clickable-row");
    rows.forEach((row) => addRowEventListeners(row));

    const masterCheckbox = form.querySelector("#check_all");
    masterCheckbox?.addEventListener("change", function () {
      const checkboxes = form.querySelectorAll(
        'input[type="checkbox"].roll-checkbox'
      );
      checkboxes.forEach((checkbox) => {
        if (checkbox !== masterCheckbox) {
          checkbox.checked = masterCheckbox.checked;
          checkbox.dispatchEvent(new Event("change"));
        }
      });
    });
  }

  const observer = new MutationObserver((mutations) => {
    mutations.forEach((mutation) => {
      if (mutation.type === "childList" && mutation.addedNodes.length > 0) {
        mutation.addedNodes.forEach((node) => {
          if (node.classList && node.classList.contains("clickable-row")) {
            addRowEventListeners(node);
          }
        });
      }
    });
  });

  observer.observe(form, { childList: true, subtree: true });

  setupRowsAndCheckboxes();
  setupEventListeners();

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

  function updatePerformingRolls(classType) {
    const params = new URLSearchParams({
      action: "get_performing_rolls",
      class_type: classType,
      nonce: competitorsPublicAjax.nonce,
    });

    fetch(competitorsPublicAjax.ajaxurl, {
      method: "POST",
      headers: {
        "Content-Type": "application/x-www-form-urlencoded; charset=UTF-8",
      },
      body: params,
    })
      .then((response) => response.json())
      .then((data) => {
        if (data.success) {
          document.getElementById("performing-rolls-container").innerHTML =
            data.data.html;
          setupRowsAndCheckboxes(); // Event listeners to new content after AJAX load
        } else {
          alert("Failed to update performing rolls.");
        }
      })
      .catch((error) => {
        alert("An error occurred while updating performing rolls.");
        console.error("Error:", error);
      });
  }

  document
    .querySelectorAll('input[name="participation_class"]')
    .forEach((radio) => {
      radio.addEventListener("change", function () {
        const selectedClass = this.value;
        updatePerformingRolls(selectedClass);
      });
    });

  const detailsContainer = document.getElementById(
    "competitors-details-container"
  );
  if (!detailsContainer) {
    return;
  }

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
        return response.text();
      }
    });
  }

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

  const dateSelect = document.getElementById("date-select");
  const classSelect = document.getElementById("class-select");
  document
    .getElementById("competitors-list")
    .addEventListener("click", (event) => {
      const target = event.target.closest(".competitors-list-item");
      if (target) {
        fetchCompetitorDetails(target.getAttribute("data-competitor-id"));
      }
    });

  dateSelect.addEventListener("change", fetchCompetitorsList);
  classSelect.addEventListener("change", fetchCompetitorsList);

  async function fetchCompetitorsList() {
    const selectedDate = dateSelect.value;
    const selectedClass = classSelect.value;
    const params = {
      action: "load_competitors_list",
      date_select: selectedDate,
      class_select: selectedClass,
      security: competitorsPublicAjax.nonce,
    };

    try {
      showSpinner();
      const data = await fetchCompetitorsData(
        competitorsPublicAjax.ajaxurl,
        params
      );
      updateCompetitorsTable(data);
      setupRowsAndCheckboxes();
    } catch (error) {
      console.error("Fetch Error:", error);
      alert("Error loading competitors: " + error.message);
    } finally {
      hideSpinner();
    }
  }

  function updateCompetitorsTable(data) {
    const competitorsTable = document.querySelector(".competitors-table");
    competitorsTable.innerHTML = "";

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
  }
});
