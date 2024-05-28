// WIP tuesday 24-05-28
// Attach scoring events to score and deduct inputs
function attachScoringEvents() {
  // Attach event listeners to score and deduct inputs
  document.querySelectorAll(".score-input, .deduct-input").forEach((input) => {
    input.addEventListener("change", function () {
      const row = this.closest("tr");
      const competitorId = row.getAttribute("data-competitor-id");
      calculateAndUpdateTotalScore(row, competitorId);
    });
  });

  // Calculate points for a given score and deduct value
  function calculatePoints(scoreValue, deductValue) {
    if (scoreValue && deductValue) {
      return parseInt(deductValue) || 0;
    } else if (scoreValue) {
      return parseInt(scoreValue) || 0;
    } else if (deductValue) {
      return parseInt(deductValue) || 0;
    }
    return 0;
  }

  // Calculate points and update row total
  function calculateAndUpdateTotalScore(row, competitorId) {
    let left_score = null;
    let left_deduct = null;
    let right_score = null;
    let right_deduct = null;

    // Collect scores and deductions
    row.querySelectorAll(".score-input").forEach((input) => {
      if (input.name.includes("left_score") && input.checked) {
        left_score = input.value;
      } else if (input.name.includes("right_score") && input.checked) {
        right_score = input.value;
      }
    });

    row.querySelectorAll(".deduct-input").forEach((input) => {
      if (input.name.includes("left_deduct") && input.checked) {
        left_deduct = input.value;
      } else if (input.name.includes("right_deduct") && input.checked) {
        right_deduct = input.value;
      }
    });

    // Calculate left and right points
    const left_points = calculatePoints(left_score, left_deduct);
    const right_points = calculatePoints(right_score, right_deduct);

    // Ensure non-negative points
    const total = Math.max(0, left_points + right_points);

    // Update total in the row
    const totalCell = row.querySelector(".total-score-row");
    if (totalCell) {
      totalCell.innerHTML = total;
    }

    // Update overall total for the competitor
    updateCompetitorsTotal(competitorId);
  }

  // Update overall total for the competitor
  function updateCompetitorsTotal(competitorId) {
    let totalPoints = 0;

    document
      .querySelectorAll(
        `[data-competitor-id="${competitorId}"] .total-score-row`
      )
      .forEach((cell) => {
        totalPoints += parseInt(cell.innerText) || 0;
      });

    const competitorsTotalRow = document.getElementById(
      `competitor-total-${competitorId}`
    );
    if (competitorsTotalRow) {
      const totalPointsCell =
        competitorsTotalRow.querySelector(".total-points");
      if (totalPointsCell) {
        totalPointsCell.innerText = totalPoints;
      }
    }
  }
} // Call the function somewhere to attach scoring events

// DOMContentLoaded event listener
document.addEventListener("DOMContentLoaded", function () {
  const scoringContainer = document.getElementById("judges-scoring-container");
  let filterButton = document.getElementById("filter_button");
  let resetButton = document.getElementById("reset_button");
  let filterDateSelect = document.getElementById("filter_date");
  let filterClassSelect = document.getElementById("filter_class");
  const nonce = competitorsAdminAjax.nonce;
  const spinner = document.getElementById("spinner");

  function showSpinner() {
    console.log("Showing spinner - before change", spinner.classList);
    spinner.classList.remove("hidden");
    spinner.classList.add("show");
    console.log("Showing spinner - after change", spinner.classList);
  }

  function hideSpinner() {
    console.log("Hiding spinner - before change", spinner.classList);
    spinner.classList.add("hidden");
    spinner.classList.remove("show");
    console.log("Hiding spinner - after change", spinner.classList);
  }

  function filterCompetitors() {
    const filterDate = filterDateSelect.value;
    const filterClass = filterClassSelect.value;

    if (filterDate) {
      localStorage.setItem("filter_date", filterDate);
    } else {
      localStorage.removeItem("filter_date");
    }

    if (filterClass) {
      localStorage.setItem("filter_class", filterClass);
    } else {
      localStorage.removeItem("filter_class");
    }

    showSpinner();

    fetch(competitorsAdminAjax.ajaxurl, {
      method: "POST",
      headers: {
        "Content-Type": "application/x-www-form-urlencoded; charset=UTF-8",
      },
      body: new URLSearchParams({
        action: "filter_competitors_by_date",
        filter_date: filterDate,
        filter_class: filterClass,
        nonce: nonce,
      }),
    })
      .then((response) => response.json())
      .then((data) => {
        if (data.success && data.data.html) {
          scoringContainer.innerHTML = data.data.html;

          // Reinitialize the variables to target the new elements
          filterButton = document.getElementById("filter_button");
          resetButton = document.getElementById("reset_button");
          filterDateSelect = document.getElementById("filter_date");
          filterClassSelect = document.getElementById("filter_class");

          // Reattach events after updating the content
          reattachAllEvents();
        } else {
          scoringContainer.innerHTML = `
            <p>Error loading competitors. Please try again (and reload the page).</p>
            <button type="button" id="retry_button" class="button button-secondary">Retry</button>
          `;
        }
        hideSpinner();
      })
      .catch((error) => {
        console.error("Error:", error);
        scoringContainer.innerHTML = `
          <p>Error loading competitors. Please try again.</p>
          <button type="button" id="retry_button" class="button button-secondary">Retry</button>
        `;
        hideSpinner();
      });
  }

  function resetFilters() {
    filterDateSelect.value = "";
    filterClassSelect.value = "";

    localStorage.removeItem("filter_date");
    localStorage.removeItem("filter_class");

    scoringContainer.innerHTML = "<p>Loading...</p>";
    showSpinner();

    fetch(competitorsAdminAjax.ajaxurl, {
      method: "POST",
      headers: {
        "Content-Type": "application/x-www-form-urlencoded; charset=UTF-8",
      },
      body: new URLSearchParams({
        action: "filter_competitors_by_date",
        filter_date: "",
        filter_class: "",
        nonce: nonce,
      }),
    })
      .then((response) => response.json())
      .then((data) => {
        if (data.success && data.data.html) {
          scoringContainer.innerHTML = data.data.html;

          // Reinitialize the variables to target the new elements
          filterButton = document.getElementById("filter_button");
          resetButton = document.getElementById("reset_button");
          filterDateSelect = document.getElementById("filter_date");
          filterClassSelect = document.getElementById("filter_class");

          // Reattach events after updating the content
          reattachAllEvents();
        } else {
          scoringContainer.innerHTML = `
            <p>Error loading competitors. Please try again (and reload the page).</p>
            <button type="button" id="retry_button" class="button button-secondary">Retry</button>
          `;
        }
        hideSpinner();
      })
      .catch((error) => {
        console.error("Error:", error);
        scoringContainer.innerHTML = `
          <p>Error loading competitors. Please try again.</p>
          <button type="button" id="retry_button" class="button button-secondary">Retry</button>
        `;
        hideSpinner();
      });
  }

  function reattachAllEvents() {
    if (
      !filterDateSelect ||
      !filterClassSelect ||
      !filterButton ||
      !resetButton
    ) {
      console.error(
        "One or more elements are missing. Cannot reattach events."
      );
      return;
    }

    filterDateSelect.removeEventListener("change", filterCompetitors);
    filterClassSelect.removeEventListener("change", filterCompetitors);
    filterButton.removeEventListener("click", filterCompetitors);
    resetButton.removeEventListener("click", resetFilters);

    filterDateSelect.addEventListener("change", filterCompetitors);
    filterClassSelect.addEventListener("change", filterCompetitors);
    filterButton.addEventListener("click", filterCompetitors);
    resetButton.addEventListener("click", resetFilters);

    attachCompetitorToggleEvents();
    getFilterValues();
    attachScoringEvents();
    attachTimerEvents();
  }

  function getFilterValues() {
    const savedFilterDate = localStorage.getItem("filter_date");
    const savedFilterClass = localStorage.getItem("filter_class");

    if (savedFilterDate) {
      filterDateSelect.value = savedFilterDate;
    }
    if (savedFilterClass) {
      filterClassSelect.value = savedFilterClass;
    }
    if (savedFilterDate || savedFilterClass) {
      filterCompetitors();
    }
  }

  function attachCompetitorToggleEvents() {
    // Remove previous event listeners to prevent duplicates
    scoringContainer.removeEventListener("click", handleCompetitorToggle);

    // Attach new event listener
    scoringContainer.addEventListener("click", handleCompetitorToggle);
  }

  function handleCompetitorToggle(event) {
    const header = event.target.closest(".competitor-header");
    if (header) {
      const competitorId = header.getAttribute("data-competitor-id");

      // Hide all other competitor rows
      const allCompetitorRows = scoringContainer.querySelectorAll(
        ".competitor-columns, .competitor-scores, .competitor-info, .competitor-totals"
      );
      allCompetitorRows.forEach((row) => {
        if (row.getAttribute("data-competitor-id") !== competitorId) {
          row.classList.add("hidden");
        }
      });

      const rowsToToggle = scoringContainer.querySelectorAll(
        `.competitor-columns[data-competitor-id="${competitorId}"], 
         .competitor-scores[data-competitor-id="${competitorId}"],
         .competitor-info[data-competitor-id="${competitorId}"],
         .competitor-totals[data-competitor-id="${competitorId}"]:not(.grand-total)`
      );

      let anyRowVisible = false;
      Array.from(rowsToToggle).forEach((row) => {
        row.classList.toggle("hidden");
        if (!row.classList.contains("hidden")) {
          anyRowVisible = true;
        }
      });

      // Show spinner based on row visibility
      if (anyRowVisible) {
        console.log("Showing spinner");
        updateOverlayPosition(rowsToToggle);
        showSpinner();
      } else {
        console.log("Hiding spinner");
        hideSpinner();
      }

      toggleIcons(header);
    }
  }

  function updateOverlayPosition(rowsToToggle) {
    const firstRow = rowsToToggle[0];
    const lastRow = rowsToToggle[rowsToToggle.length - 1];
    const containerRect = scoringContainer.getBoundingClientRect();
    const firstRowRect = firstRow.getBoundingClientRect();
    const lastRowRect = lastRow.getBoundingClientRect();

    spinner.style.position = "absolute";
    spinner.style.top = `${firstRowRect.top - containerRect.top}px`;
    spinner.style.height = `${lastRowRect.bottom - firstRowRect.top}px`;
    spinner.style.left = "0";
    spinner.style.right = "0";

    console.log(
      `Spinner position updated: top=${spinner.style.top}, height=${spinner.style.height}`
    );
  }

  function toggleIcons(clickedHeader) {
    const icon = clickedHeader.querySelector(".dashicons");
    if (icon) {
      icon.classList.toggle("dashicons-arrow-down-alt2");
      icon.classList.toggle("dashicons-arrow-up-alt2");
    }
  }

  // Timer logic here...
  function attachTimerEvents() {
    console.log("attachTimerEvents called");
    const timer = document.getElementById("timer");
    const timerDisplay = document.getElementById("timer-display");
    const startBtn = document.getElementById("start-timer");
    const resetBtn = document.getElementById("reset-timer");
    const timezone = Intl.DateTimeFormat().resolvedOptions().timeZone;
    let timerStarted = false;
    let paused = true;
    let elapsedTime = 0;
    let interval;
    let currentCompetitorId = null;

    if (timer) {
      window.addEventListener("scroll", function () {
        timer.classList.toggle("fixed-timer", window.scrollY > 50);
      });

      // Display timeout or warnings
      function showTimeout(msg) {
        const overlayWarning = document.getElementById("message-overlay");
        overlayWarning.innerText = msg;
        overlayWarning.classList.add("show");
        setTimeout(() => overlayWarning.classList.remove("show"), 5000);
      }

      // Update timer display
      function updateTimerDisplay() {
        const hours = Math.floor(elapsedTime / 3600000)
          .toString()
          .padStart(2, "0");
        const minutes = Math.floor((elapsedTime % 3600000) / 60000)
          .toString()
          .padStart(2, "0");
        const seconds = Math.floor((elapsedTime % 60000) / 1000)
          .toString()
          .padStart(2, "0");
        timerDisplay.textContent = `${hours}:${minutes}:${seconds}`;
        checkTimeMilestones();
      }

      // Check time milestones
      function checkTimeMilestones() {
        if (elapsedTime >= 900000 && elapsedTime < 901000) {
          showTimeout("Half Time");
        }
        if (elapsedTime >= 1500000 && elapsedTime < 1501000) {
          showTimeout("5 minutes to go");
        }
      }

      // Reset timer and data
      function resetTimerDisplayAndData() {
        clearInterval(interval);
        timerStarted = false;
        paused = true;
        elapsedTime = 0;
        updateTimerDisplay();
        startBtn.textContent = "Start";
        clearCompetitorTimes();
      }

      // Clear competitor times
      function clearCompetitorTimes() {
        if (currentCompetitorId) {
          document.getElementById(`start-time-${currentCompetitorId}`).value =
            "";
          document.getElementById(`stop-time-${currentCompetitorId}`).value =
            "";
          document.getElementById(`elapsed-time-${currentCompetitorId}`).value =
            "";
        }
      }

      // Event to handle timer reset
      resetBtn.addEventListener("click", () => {
        if (confirm("Are you sure you want to reset the timer?")) {
          resetTimerDisplayAndData();
        }
      });

      // Pause the timer
      function pauseTimer() {
        clearInterval(interval);
        paused = true;
        startBtn.textContent = "Continue";
        showSpinner(); // Show spinner when paused
      }

      // Handle start/pause actions
      startBtn.addEventListener("click", (e) => {
        e.preventDefault();
        if (!currentCompetitorId) {
          alert("Please select a competitor first.");
          return;
        }
        if (!timerStarted || paused) {
          startOrContinueTimer();
          hideSpinner(); // Hide spinner when starting/continuing
        } else {
          pauseTimer();
        }
      });

      // Start or continue timer
      function startOrContinueTimer() {
        timerStarted = true;
        paused = false;
        startBtn.textContent = "Pause";
        if (elapsedTime === 0) {
          document.getElementById(`start-time-${currentCompetitorId}`).value =
            getLocalizedTime(timezone);
        }
        interval = setInterval(() => {
          elapsedTime += 100;
          updateTimerDisplay();
        }, 100);
      }

      function getLocalizedTime(timezone) {
        const date = new Date().toISOString();
        const options = {
          timeZone: timezone,
          year: "numeric",
          month: "numeric",
          day: "numeric",
          hour: "2-digit",
          minute: "2-digit",
          second: "2-digit",
          hour12: false,
        };

        return date.toLocaleString("en-US", options);
      }

      // Handle score saving
      function prepareFormData(competitorId) {
        if (competitorId) {
          document.getElementById(`stop-time-${competitorId}`).value =
            getLocalizedTime(timezone);
          document.getElementById(`elapsed-time-${competitorId}`).value =
            timerDisplay.textContent;
        }
      }

      // Add event listener to store form data locally if offline, otherwise submit
      const scoringForm = document.getElementById("scoring-form");
      if (scoringForm) {
        scoringForm.addEventListener("submit", (event) => {
          console.log("Form submit event triggered");
          event.preventDefault();
          console.log("Default event prevented");

          prepareFormData(currentCompetitorId);

          if (navigator.onLine) {
            console.log("Online: Sending data via AJAX");
            syncDataAuto();
          } else {
            console.log("Offline: Storing data locally");
            storeFormDataLocally();
          }
        });
      }

      // For merging data
      function deepMergeObjects(target, source) {
        Object.keys(source).forEach((key) => {
          if (
            source[key] &&
            typeof source[key] === "object" &&
            !Array.isArray(source[key])
          ) {
            if (!target[key]) target[key] = {};
            deepMergeObjects(target[key], source[key]);
          } else {
            target[key] = source[key];
          }
        });
        return target;
      }

      // Store form data in local storage if offline
      function storeFormDataLocally() {
        try {
          const existingData = localStorage.getItem(
            "CompetitorsUnsyncedFormData"
          );
          const competitorsData = existingData ? JSON.parse(existingData) : {};
          const newCompetitorData = Object.fromEntries(
            new FormData(scoringForm)
          );

          // Use a more robust merging strategy if necessary
          const newData = { [currentCompetitorId]: newCompetitorData };
          deepMergeObjects(competitorsData, newData);

          localStorage.setItem(
            "CompetitorsUnsyncedFormData",
            JSON.stringify(competitorsData)
          );
          alert(
            "You're offline. Data is saved in local storage and will need to be synced later."
          );
        } catch (error) {
          console.error("Failed to store data locally:", error);
          alert("Failed to store data locally.");
        }
      }

      // Data submission via AJAX
      function syncDataAuto() {
        const formData = new FormData(scoringForm);
        const dataObject = Object.fromEntries(formData);
        dataObject["action"] = "competitors_score_update";
        dataObject["competitors_score_update_nonce"] =
          competitorsAdminAjax.nonce;

        console.log("Sending AJAX request", dataObject);

        fetch(competitorsAdminAjax.ajaxurl, {
          method: "POST",
          credentials: "same-origin",
          headers: {
            "Content-Type": "application/x-www-form-urlencoded; charset=UTF-8",
          },
          body: new URLSearchParams(dataObject),
        })
          .then((response) => {
            console.log("Response received");
            return response.json();
          })
          .then((data) => {
            console.log("Processing response", data);
            handleServerResponse(data);
          })
          .catch(handleSyncError);
      }

      function handleServerResponse(data) {
        if (data.success) {
          console.log("Data synced successfully:", data);
          localStorage.removeItem("CompetitorsUnsyncedFormData");
          if (
            confirm(
              "Data has been successfully synced! Do you want to reload the page to reflect the changes?"
            )
          ) {
            window.location.reload();
          }
        } else {
          console.error("Failed to sync data:", data);
          alert(`Sync failed: ${data.message || "Unknown error"}`);
        }
      }

      function handleSyncError(error) {
        console.error("Sync Error:", error);
        alert(error.message || "An unknown error occurred during sync.");
      }

      // Store the current competitor's data before switching
      document.querySelectorAll(".competitor-header").forEach((header) => {
        header.addEventListener("click", function () {
          if (!navigator.onLine) {
            storeFormDataLocally();
          }
          currentCompetitorId = this.getAttribute("data-competitor-id");
          resetTimerDisplayAndData();
        });
      });
    }
  }

  if (scoringContainer) {
    // Call the functions only on scoring page to reattach events and get filter values
    reattachAllEvents();
  }
});

// From hereon there be jQuery because its included in WP
// "Quick edit" custom order-by in Admin list view, the WP way
jQuery(document).ready(function ($) {
  // Sort columns in Personal Data view page when clicking html table headers
  if ($("#sortable-table").length) {
    $("#sortable-table th").each(function () {
      $(this).on("click", function () {
        var $table = $(this).closest("table");
        var $rows = $table.find("tbody tr").toArray();
        var index = $(this).index();
        var asc = !(this.asc = !this.asc);

        $rows.sort(function (rowA, rowB) {
          var cellA = $(rowA).children("td").eq(index).text();
          var cellB = $(rowB).children("td").eq(index).text();
          var isNumericA = !isNaN(parseFloat(cellA)) && isFinite(cellA);
          var isNumericB = !isNaN(parseFloat(cellB)) && isFinite(cellB);

          if (isNumericA && isNumericB) {
            return cellA - cellB;
          } else {
            return cellA.localeCompare(cellB);
          }
        });

        if (asc) {
          $rows.reverse();
        }

        $.each($rows, function (index, row) {
          $table.children("tbody").append(row);
        });
      });
    });
  }

  // "Quick edit" custom order-by in Admin list view, the WP way
  $(document).on("click", ".editinline", function () {
    var postID = $(this).closest("tr").attr("id").replace("post-", "");

    var customOrderValue = $("#post-" + postID)
      .find(".column-custom_order")
      .text();
    // Clear any previously added custom order fields to avoid duplicates in list
    $(".competitors-custom-order-field").remove();
    // Ensure the Quick Edit row is fully visible before appending
    setTimeout(function () {
      // Find the right spot for the custom order field
      var $lastField = $(".inline-edit-row:visible").find(
        ".inline-edit-col .inline-edit-group:last"
      );

      // Check Quick Edit form layout and adjust the above selector if needed
      var customOrderField = `<div class="inline-edit-group competitors-custom-order-field">
           <label><span class="title">Order</span>
           <span class="input-text-wrap"><input type="number" name="competitors_custom_order" value="${customOrderValue.trim()}">
           </span></label></div>`;

      // Append or prepend based on your layout needs
      $lastField.after(customOrderField);
    }, 150); // A slight delay to ensure the Quick Edit form is fully rendered
  });

  // The following is for adding/removing dates/events in settings, not on the scoring page
  // Initialize the date picker
  $(".date-picker").datepicker({
    dateFormat: "yy-mm-dd",
  });

  // Add event button functionality for Settings page
  $(".add-event").click(function () {
    var newDate = $("#new_competition_date").val();
    var eventName = $("#new_event_name").val();
    if (newDate && eventName) {
      var eventObj = {
        date: newDate,
        name: eventName,
      };
      var eventString = JSON.stringify(eventObj); // Convert the object to a string for storage
      $("#existing_events").append(
        `<li data-date="${escapeHtml(newDate)}" data-name="${escapeHtml(
          eventName
        )}">
           <input type="hidden" name="available_competition_dates[]" value="${encodeURIComponent(
             eventString
           )}">
           ${escapeHtml(newDate)} - ${escapeHtml(eventName)}
           <button type="button" class="remove-event">Remove</button>
         </li>`
      );
      $("#new_competition_date").val("").datepicker("setDate", null);
      $("#new_event_name").val("");
    }
  });

  // Function to escape HTML
  function escapeHtml(text) {
    var map = {
      "&": "&amp;",
      "<": "&lt;",
      ">": "&gt;",
      '"': "&quot;",
      "'": "&#039;",
    };
    return text.replace(/[&<>"']/g, function (m) {
      return map[m];
    });
  }

  // Adding more roll names in Settings
  $(document).on("click", ".plus-button", function () {
    var classType = $(this).attr("id").replace("add_more_roll_names_", "");
    var $wrapper = $("#competitors_roll_names_wrapper_" + classType);
    var index = $wrapper.find("p").length;

    var newField = `
      <p data-index="${index}">
        <label for="maneuver_${classType}_${index}">Maneuver: </label>
        <input type="text" id="maneuver_${classType}_${index}" name="competitors_custom_values_${classType}[]" size="60" value="" />
        <label for="points_${classType}_${index}"> Points: </label>
        <input type="text" class="numeric-input" id="points_${classType}_${index}" name="competitors_numeric_values_${classType}[]" size="2" maxlength="2" pattern="\\d*" value="0" />
        <label for="numeric_${classType}_${index}"> Numeric:</label>
        <input type="checkbox" id="numeric_${classType}_${index}" name="competitors_is_numeric_field_${classType}[${index}]" value="1">
        <button type="button" class="button custom-button button-secondary remove-row">Remove</button>
      </p>
    `;

    $wrapper.append(newField);
  });

  // Removing a row in Settings
  $(document).on("click", ".remove-row", function () {
    $(this).parent().remove();
  });

  // Settings page AJAX add/remove rows
  if ($("#settings-page").length) {
    const $wrapper = $("#competitors_roll_names_wrapper");
    const $addButton = $("#add_more_roll_names");

    if (!$wrapper.length || !$addButton.length) {
      return;
    }

    function addRow() {
      const newIndex = $wrapper.find("p").length;
      const newField = $(`
        <p data-index="${newIndex}">
          <label for="maneuver_${newIndex}">Maneuver: </label>
          <input type="text" id="maneuver_${newIndex}" name="competitors_custom_values[]" size="60" />
          <label for="points_${newIndex}"> Points: </label>
          <input type="text" class="numeric-input" id="points_${newIndex}" name="competitors_numeric_values[]" size="2" maxlength="2" pattern="\\d*" title="Only 2 digits allowed" />
          <label for="numeric_field_${newIndex}"> Numeric:</label>
          <input type="checkbox" id="numeric_field_${newIndex}" name="competitors_is_numeric_field[${newIndex}]" value="1">
          <button type="button" class="button custom-button button-secondary remove-row">Remove</button>
        </p>
      `);
      $wrapper.append(newField);
    }

    $addButton.on("click", addRow);

    // Removing a row in settings
    $wrapper.on("click", ".remove-row", function (e) {
      e.preventDefault();
      if (confirm("Remove, destroy, kill this row irrevocably?")) {
        const rowIndex = $(this).parent().data("index");
        const nonce = $("#competitors_nonce").val();

        // AJAX request to WordPress
        $.ajax({
          url: competitorsAdminAjax.ajaxurl,
          type: "POST",
          data: {
            action: "remove_competitor_row",
            index: rowIndex,
            security: nonce,
          },
          success: function (response) {
            if (response.success) {
              console.log(response.message);
              $(`[data-index="${rowIndex}"]`).remove(); // Remove the parent <p> element
            } else {
              console.error(response.message);
              alert("Failed to remove row.");
            }
          },
          error: function (error) {
            console.error("Error:", error);
            alert("Error removing row.");
          },
        });
      }
    });

    $wrapper.on("input", ".numeric-input", function (e) {
      const value = $(this).val();
      $(this).val(value.slice(0, 2)); // Only 2 digits
    });
  }
});
