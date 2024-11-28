document.addEventListener("DOMContentLoaded", () => {
  // Utility Functions
  const toggleClass = (element, className, condition) =>
    element?.classList.toggle(className, condition);

  const toggleElementDisplay = (element, displayStyle, opacity = null) => {
    if (!element) return;
    element.style.display = displayStyle;
    if (opacity !== null) {
      requestAnimationFrame(() => {
        element.style.opacity = opacity;
      });
    }
  };

  const form = document.getElementById("competitors-registration-form");
  const detailsContainer = document.getElementById(
    "competitors-details-container"
  );
  const competitorsList = document.getElementById("competitors-list");
  const dateSelect = document.getElementById("date-select");
  const classSelect = document.getElementById("class-select");
  const genderSelect = document.getElementById("gender-select");
  const spinner = document.getElementById("spinner");

  const showSpinner = () => {
    if (!spinner) return;
    toggleElementDisplay(spinner, "flex", "1");
  };

  const hideSpinner = () => {
    if (!spinner) return;
    spinner.style.opacity = "0";
    spinner.addEventListener("transitionend", function handler(e) {
      if (e.propertyName === "opacity") {
        spinner.style.display = "none";
        spinner.removeEventListener("transitionend", handler);
      }
    });
  };

  const isRegistrationPage = !!form;
  const isListingPage = !!competitorsList;
  if (isRegistrationPage) {
    setupForm();
    const observer = new MutationObserver((mutations) => {
      mutations.forEach((mutation) => {
        if (mutation.type === "childList") {
          mutation.addedNodes.forEach((node) => {
            if (node.classList && node.classList.contains("clickable-row")) {
              setupRowsAndCheckboxes();
            }
          });
        }
      });
    });
    observer.observe(form, { childList: true, subtree: true });
  }

  function debounce(func, wait) {
    let timeout;
    return function executedFunction(...args) {
      const later = () => {
        clearTimeout(timeout);
        func(...args);
      };
      clearTimeout(timeout);
      timeout = setTimeout(later, wait);
    };
  }

  const debouncedFetchCompetitorsList = debounce(fetchCompetitorsList, 300);

  if (isListingPage) {
    if (
      (dateSelect && dateSelect.options.length > 1) ||
      (classSelect && classSelect.options.length > 1) ||
      (genderSelect && genderSelect.options.length > 1)
    ) {
      dateSelect && dateSelect.options.length > 1
        ? (dateSelect.selectedIndex = 1)
        : null;
      fetchCompetitorsList();
    }

    dateSelect?.addEventListener("change", debouncedFetchCompetitorsList);
    classSelect?.addEventListener("change", debouncedFetchCompetitorsList);
    genderSelect?.addEventListener("change", debouncedFetchCompetitorsList);
    loadCompetitorFromURL();
  }

  // Fetch Function
  async function fetchData(action, params = {}) {
    const url = competitorsPublicAjax.ajaxurl;
    const defaultParams = {
      action,
      security: competitorsPublicAjax.nonce,
      ...params,
    };

    //console.log(`Fetching data for action: ${action}`, defaultParams);

    try {
      showSpinner();
      const response = await fetch(url, {
        method: "POST",
        headers: { "Content-Type": "application/x-www-form-urlencoded" },
        body: new URLSearchParams(defaultParams),
      });

      if (!response.ok)
        throw new Error(`HTTP error, status = ${response.status}`);

      const contentType = response.headers.get("content-type");
      const data =
        contentType && contentType.includes("application/json")
          ? await response.json()
          : await response.text();

      //console.log(`Received data for action: ${action}`, data);
      return data;
    } catch (error) {
      console.error(`Error fetching ${action}:`, error);
      throw error;
    } finally {
      hideSpinner();
    }
  }

  // Form Handling Functions
  function setupForm() {
    if (!form) return;

    const validationMessageContainer = form.querySelector(
      "#validation-message"
    );
    const submitButton = form.querySelector("#submit-button");

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
        setTimeout(() => {
          validationMessageContainer.classList.add("hidden");
          validationMessageContainer.textContent = "";
          validationMessageContainer.classList.remove(
            "danger",
            "success",
            "fade-out"
          );
        }, 8000);
      }
    }

    function validateForm() {
      let isValid = true;

      ["phone", "email", "name"].forEach((fieldName) => {
        const field = form.querySelector(`[name="${fieldName}"]`);
        if (!field.value.trim()) {
          handleValidationMessage({
            message: `${
              fieldName.charAt(0).toUpperCase() + fieldName.slice(1)
            } is required.`,
            success: false,
            show: true,
          });
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
      if (!dateField.value.trim()) {
        handleValidationMessage({
          message: "A competition date is required.",
          success: false,
          show: true,
        });
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
        handleValidationMessage({ message, success: false, show: true });
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
        handleValidationMessage({ message, success: false, show: true });
        toggleClass(container, "border-danger", true);
        return false;
      } else {
        toggleClass(container, "border-danger", false);
        return true;
      }
    }

    async function submitForm() {
      const formData = new FormData(form);
      formData.append("action", "competitors_form_submit");

      try {
        const data = await fetchData(
          "competitors_form_submit",
          Object.fromEntries(formData)
        );

        if (!data.success)
          throw new Error(data.data.message || "Form submission failed");

        handleValidationMessage({
          message: `Thank you for your registration! Your total fee is SEK ${data.data.total_sum}. Please check your inbox and spam folder for a confirmation. Pay with Swish on the next page.`,
          success: true,
          show: true,
          fadeOut: true,
        });

        form.reset();
        setTimeout(() => {
          window.location.href = data.data.redirect_url;
        }, 2000);
      } catch (error) {
        console.error("Error during form submission:", error);
        handleValidationMessage({
          message: `Oops! ${
            error.message || "There was a problem with your submission."
          }`,
          success: false,
          show: true,
          fadeOut: true,
        });
      } finally {
        submitButton.disabled = false;
        submitButton.value = "Submit";
      }
    }

    async function handleSubmit(event) {
      event.preventDefault();
      submitButton.disabled = true;
      submitButton.value = "Processing...";

      if (validateForm()) {
        await submitForm();
      } else {
        submitButton.disabled = false;
        submitButton.value = "Submit";
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
      form
        .querySelectorAll('input[name="participation_class"]')
        .forEach((radio) => {
          radio.addEventListener("change", () => {
            toggleLicenseCheckbox();
            updatePerformingRolls(radio.value);
          });
        });
      toggleLicenseCheckbox();
    }

    setupEventListeners();
  }

  // Competitor List and Details Functions
  function setupRowsAndCheckboxes() {
    const rows = document.querySelectorAll(".clickable-row");
    rows.forEach((row) => {
      row.addEventListener("click", function (event) {
        const checkbox =
          event.target.type === "checkbox"
            ? event.target
            : this.querySelector(".roll-checkbox");
        if (checkbox) {
          checkbox.checked = !checkbox.checked;
          toggleClass(checkbox.closest("tr"), "grayed-out", !checkbox.checked);
          checkbox.dispatchEvent(new Event("change", { bubbles: true }));
        }
      });
    });

    const masterCheckbox = document.querySelector("#check_all");
    masterCheckbox?.addEventListener("change", function () {
      document
        .querySelectorAll('input[type="checkbox"].roll-checkbox')
        .forEach((checkbox) => {
          if (checkbox !== masterCheckbox) {
            checkbox.checked = masterCheckbox.checked;
            checkbox.dispatchEvent(new Event("change"));
          }
        });
    });
  }

  async function updatePerformingRolls(classType) {
    try {
      const data = await fetchData("get_performing_rolls", {
        class_type: classType,
      });
      if (data.success) {
        document.getElementById("performing-rolls-container").innerHTML =
          data.data.html;
        setupRowsAndCheckboxes();
      } else {
        throw new Error("Failed to update performing rolls.");
      }
    } catch (error) {
      console.error("Error updating performing rolls:", error);
      alert(error.message);
    }
  }

  async function fetchCompetitorDetails(competitorId, participationClass) {
    try {
      const html = await fetchData("load_competitor_details", {
        competitor_id: competitorId,
        participation_class: participationClass,
      });
      detailsContainer.innerHTML = html.trim()
        ? html
        : "<p>No details available for this competitor.</p>";
      detailsContainer.style.display = "block";
      document
        .getElementById("close-details")
        .scrollIntoView({ behavior: "smooth" });
      addCloseButtonListener();

      // Update URL with competitor ID
      updateURL(competitorId);
    } catch (error) {
      console.error("Error loading competitor details:", error);
      alert("Error loading details: " + error.message);
    }
  }

  function addCloseButtonListener() {
    const closeDetailsButton = document.getElementById("close-details");
    closeDetailsButton.addEventListener(
      "click",
      (e) => {
        e.preventDefault();
        detailsContainer.style.display = "none";
        detailsContainer.innerHTML = "";
        competitorsList.scrollIntoView({ behavior: "smooth" });

        // Remove competitor ID from URL
        updateURL(null);
      },
      { once: true }
    );
  }

  function updateURL(competitorId) {
    if (competitorId) {
      const newUrl = new URL(window.location);
      newUrl.searchParams.set("competitor", competitorId);
      window.history.pushState({}, "", newUrl);
    } else {
      const newUrl = new URL(window.location);
      newUrl.searchParams.delete("competitor");
      window.history.pushState({}, "", newUrl);
    }
  }

  function getCompetitorIdFromURL() {
    const urlParams = new URLSearchParams(window.location.search);
    return urlParams.get("competitor");
  }

  async function loadCompetitorFromURL() {
    const competitorId = getCompetitorIdFromURL();
    if (competitorId) {
      // You might need to fetch the participation class separately or modify your backend to not require it
      await fetchCompetitorDetails(competitorId, null);
    }
  }

  if (competitorsList) {
    competitorsList.addEventListener("click", (event) => {
      const target = event.target.closest(".competitors-list-item");
      if (target) {
        fetchCompetitorDetails(
          target.getAttribute("data-competitor-id"),
          target.getAttribute("data-participation-class")
        );
      }
    });
  }

  async function fetchCompetitorsList() {
    try {
      //console.log("Fetching competitors list...");
      console.log("Filter values:", {
        date: dateSelect.value,
        class: classSelect.value,
        gender: genderSelect.value,
      });

      const data = await fetchData("load_competitors_list", {
        date_select: dateSelect.value,
        class_select: classSelect.value,
        gender_select: genderSelect.value,
      });

      //console.log("Received competitors list data:", data);

      if (!data || !data.success) {
        throw new Error(data?.data?.message || "Invalid response from server");
      }

      updateCompetitorsTable(data);
      setupRowsAndCheckboxes();
    } catch (error) {
      console.error("Error loading competitors list:", error);
      console.error("Error details:", error.message);
      alert("Error loading competitors: " + error.message);
    }
  }

  function updateCompetitorsTable(data) {
    const competitorsTable = document.querySelector(".competitors-table");
    //console.log("Updating competitors table with data:", data);
    competitorsTable.innerHTML = "";

    if (!data) {
      console.error("No data received");
      competitorsTable.innerHTML =
        "<p>No competitors found with current filters</p>";
      return;
    }

    if (typeof data === "string") {
      console.log("Data is a string:", data);
      competitorsTable.innerHTML =
        data.trim() && data.trim() !== '<ul class="competitors-table"></ul>'
          ? data
          : "<p>No competitors found for these selections</p>";
    } else if (
      typeof data === "object" &&
      data.success &&
      data.data &&
      data.data.content
    ) {
      competitorsTable.innerHTML =
        data.data.content || "<p>No competitors match your filter criteria</p>";
    } else {
      console.error("Unexpected data format:", data);
      competitorsTable.innerHTML = "<p>No competitors found</p>";
    }
  }

  // Event Listeners
  if (competitorsList) {
    competitorsList.addEventListener("click", (event) => {
      const target = event.target.closest(".competitors-list-item");
      if (target) {
        fetchCompetitorDetails(
          target.getAttribute("data-competitor-id"),
          target.getAttribute("data-participation-class")
        );
      }
    });
  }

  if (
    (dateSelect && dateSelect.options.length > 1) ||
    (classSelect && classSelect.options.length > 1) ||
    (genderSelect && genderSelect.options.length > 1)
  ) {
    dateSelect && dateSelect.options.length > 1
      ? (dateSelect.selectedIndex = 1)
      : null;
    fetchCompetitorsList();
  } else {
    console.warn("No select elements found or have insufficient options");
  }

  dateSelect?.addEventListener("change", debouncedFetchCompetitorsList);
  classSelect?.addEventListener("change", debouncedFetchCompetitorsList);
  genderSelect?.addEventListener("change", debouncedFetchCompetitorsList);

  // Initialize
  setupForm();
  loadCompetitorFromURL(); // Load competitor details if ID is in URL

  // Observe for dynamically added rows
  const observer = new MutationObserver((mutations) => {
    mutations.forEach((mutation) => {
      if (mutation.type === "childList") {
        mutation.addedNodes.forEach((node) => {
          if (node.classList && node.classList.contains("clickable-row")) {
            setupRowsAndCheckboxes();
          }
        });
      }
    });
  });

  if (form) {
    observer.observe(form, { childList: true, subtree: true });
  }
});
