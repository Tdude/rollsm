document.addEventListener("DOMContentLoaded", () => {
  const scoringContainer = document.getElementById("judges-scoring-container");
  if (scoringContainer) {
    let filterButton = document.getElementById("filter_button");
    let resetButton = document.getElementById("reset_button");
    let filterDateSelect = document.getElementById("filter_date");
    let filterClassSelect = document.getElementById("filter_class");
    let filterGenderSelect = document.getElementById("filter_gender");
    const nonce = competitorsAdminAjax.nonce;
    const spinner = document.getElementById("spinner");
    // Use v2 action if available (migration complete), otherwise old action
    const filterAction = (typeof competitorsOfflineSync !== "undefined") ? "filter_competitors_v2" : "filter_competitors";

    // Function to initialize all events on DOMContentLoaded
    reattachAllEvents();
    getFilterValues();
    // initializeScores in reattachAllEvents

    function showSpinner() {
      if (spinner) {
        spinner.classList.remove("hidden");
        void spinner.offsetWidth; // force reflow
        spinner.classList.add("show");
      } else {
        console.error("Spinner element not found");
      }
    }

    function hideSpinner() {
      if (spinner) {
        spinner.classList.remove("show");
        spinner.classList.add("hidden");
      } else {
        console.error("Spinner element not found");
      }
    }

    function initializeScores() {
      document.querySelectorAll(".competitor-scores").forEach((row) => {
        const competitorId = row.getAttribute("data-competitor-id");
        const index = row.getAttribute("data-index"); // Make sure to set this data attribute when rendering rows

        let left_score = "";
        let left_deduct = "";
        let right_score = "";
        let right_deduct = "";

        // Collect values from radio buttons
        row.querySelectorAll(".score-input").forEach((input) => {
          if (input.name.includes("[left_group]") && input.checked) {
            left_score = input.value;
          } else if (input.name.includes("[right_group]") && input.checked) {
            right_score = input.value;
          }
        });

        row.querySelectorAll(".deduct-input").forEach((input) => {
          if (input.name.includes("[left_group]") && input.checked) {
            left_deduct = input.value;
          } else if (input.name.includes("[right_group]") && input.checked) {
            right_deduct = input.value;
          }
        });

        // Collect values from numeric inputs
        row.querySelectorAll(".numeric-input").forEach((input) => {
          if (input.name.includes("[left_score]")) {
            left_score = input.value;
          } else if (input.name.includes("[right_score]")) {
            right_score = input.value;
          }
        });

        const left_points = calculatePoints(left_score, left_deduct);
        const right_points = calculatePoints(right_score, right_deduct);
        const total = Math.max(0, left_points + right_points);

        const totalCell = row.querySelector(".total-score-row");
        if (totalCell) {
          totalCell.innerHTML = total;
        }

        // Update the existing hidden input field
        const hiddenInput = row.querySelector(
          `input[name='competitor_scores[${competitorId}][${index}][total_score]']`
        );
        if (hiddenInput) {
          hiddenInput.value = total;
        }
      });

      // Update the total scores for all competitors
      document.querySelectorAll(".competitor-scores").forEach((row) => {
        const competitorId = row.getAttribute("data-competitor-id");
        updateCompetitorsTotal(competitorId);
      });
    }

    function filterCompetitors() {
      const filterDate = filterDateSelect ? filterDateSelect.value : "";
      const filterClass = filterClassSelect ? filterClassSelect.value : "";
      const filterGender = filterGenderSelect ? filterGenderSelect.value : "";

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

      if (filterGender) {
        localStorage.setItem("filter_gender", filterGender);
      } else {
        localStorage.removeItem("filter_gender");
      }

      showSpinner();

      fetch(competitorsAdminAjax.ajaxurl, {
        method: "POST",
        headers: {
          "Content-Type": "application/x-www-form-urlencoded; charset=UTF-8",
        },
        body: new URLSearchParams({
          action: filterAction,
          filter_date: filterDate,
          filter_class: filterClass,
          filter_gender: filterGender,
          nonce: nonce,
        }),
      })
        .then((response) => response.json())
        .then((data) => {
          if (data.success && data.data.html) {
            scoringContainer.innerHTML = data.data.html;

            filterButton = document.getElementById("filter_button");
            resetButton = document.getElementById("reset_button");
            filterDateSelect = document.getElementById("filter_date");
            filterClassSelect = document.getElementById("filter_class");

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
      if (filterDateSelect) filterDateSelect.value = "";
      if (filterClassSelect) filterClassSelect.value = "";
      if (filterGenderSelect) filterGenderSelect.value = "";

      localStorage.removeItem("filter_date");
      localStorage.removeItem("filter_class");
      localStorage.removeItem("filter_gender");

      scoringContainer.innerHTML = "<p>Loading...</p>";
      showSpinner();

      fetch(competitorsAdminAjax.ajaxurl, {
        method: "POST",
        headers: {
          "Content-Type": "application/x-www-form-urlencoded; charset=UTF-8",
        },
        body: new URLSearchParams({
          action: filterAction,
          filter_date: "",
          filter_class: "",
          filter_gender: "",
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

    function getFilterValues() {
      if (scoringContainer) {
        const savedFilterDate = localStorage.getItem("filter_date");
        const savedFilterClass = localStorage.getItem("filter_class");
        const savedFilterGender = localStorage.getItem("filter_gender");

        if (savedFilterDate && filterDateSelect) {
          filterDateSelect.value = savedFilterDate;
        }
        if (savedFilterClass && filterClassSelect) {
          filterClassSelect.value = savedFilterClass;
        }
        if (savedFilterGender && filterGenderSelect) {
          filterGenderSelect.value = savedFilterGender;
        }
        if (savedFilterDate || savedFilterClass || savedFilterGender) {
          filterCompetitors();
        }
      }
    }

    function reattachAllEvents() {
      if (!scoringContainer) {
        console.error("Scoring container not found. Cannot reattach events.");
        return;
      }

      // Re-grab filter elements (they may have been replaced by AJAX)
      filterButton = document.getElementById("filter_button");
      resetButton = document.getElementById("reset_button");
      filterDateSelect = document.getElementById("filter_date");
      filterClassSelect = document.getElementById("filter_class");
      filterGenderSelect = document.getElementById("filter_gender");

      if (filterDateSelect) {
        filterDateSelect.removeEventListener("change", filterCompetitors);
        filterDateSelect.addEventListener("change", filterCompetitors);
      }
      if (filterClassSelect) {
        filterClassSelect.removeEventListener("change", filterCompetitors);
        filterClassSelect.addEventListener("change", filterCompetitors);
      }
      if (filterGenderSelect) {
        filterGenderSelect.removeEventListener("change", filterCompetitors);
        filterGenderSelect.addEventListener("change", filterCompetitors);
      }
      if (filterButton) {
        filterButton.removeEventListener("click", filterCompetitors);
        filterButton.addEventListener("click", filterCompetitors);
      }
      if (resetButton) {
        resetButton.removeEventListener("click", resetFilters);
        resetButton.addEventListener("click", resetFilters);
      }

      scoringContainer.removeEventListener("click", handleCompetitorToggle);
      scoringContainer.addEventListener("click", handleCompetitorToggle);

      attachScoringEvents();
      attachTimerEvents();
      initializeScores();
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
          updateOverlayPosition(rowsToToggle);
          showSpinner();
        } else {
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
    }

    function toggleIcons(clickedHeader) {
      const icon = clickedHeader.querySelector(".dashicons");
      if (icon) {
        icon.classList.toggle("dashicons-arrow-down-alt2");
        icon.classList.toggle("dashicons-arrow-up-alt2");
      }
    }

    // Calculate points for a given score and deduct value
    function calculatePoints(scoreValue, deductValue) {
      const score = parseInt(scoreValue) || 0;
      const deduct = parseInt(deductValue) || 0;

      if (score !== 0) {
        return score;
      } else if (deduct !== 0) {
        return deduct;
      }
      return 0;
    }

    // Initialize event listeners for scoring and reset buttons
    function attachScoringEvents() {
      document
        .querySelectorAll(".score-input, .deduct-input, .numeric-input")
        .forEach((input) => {
          input.addEventListener("change", function () {
            const row = this.closest("tr");
            const competitorId = row.getAttribute("data-competitor-id");
            const index = row.getAttribute("data-index");
            calculateAndUpdateTotalScore(row, competitorId, index);
          });
        });

      // Attach event listeners for reset buttons
      document.querySelectorAll(".reset-row").forEach((button) => {
        button.addEventListener("click", function () {
          const rowId = this.closest("tr").id;
          resetRow(rowId);
        });
      });
    }

    // Calculate points and update row total
    function calculateAndUpdateTotalScore(row, competitorId, index) {
      let left_score = "";
      let left_deduct = "";
      let right_score = "";
      let right_deduct = "";

      // Collect values from radio buttons
      row.querySelectorAll(".score-input").forEach((input) => {
        if (input.name.includes("[left_group]") && input.checked) {
          left_score = input.value;
        } else if (input.name.includes("[right_group]") && input.checked) {
          right_score = input.value;
        }
      });

      row.querySelectorAll(".deduct-input").forEach((input) => {
        if (input.name.includes("[left_group]") && input.checked) {
          left_deduct = input.value;
        } else if (input.name.includes("[right_group]") && input.checked) {
          right_deduct = input.value;
        }
      });

      // Collect values from numeric inputs
      row.querySelectorAll(".numeric-input").forEach((input) => {
        if (input.name.includes("[left_score]")) {
          left_score = input.value;
        } else if (input.name.includes("[right_score]")) {
          right_score = input.value;
        }
      });

      // console.log(
      // `left_score: ${left_score}, left_deduct: ${left_deduct}, right_score: ${right_score}, right_deduct: ${right_deduct}`
      // );

      const left_points = calculatePoints(left_score, left_deduct);
      const right_points = calculatePoints(right_score, right_deduct);
      const total = Math.max(0, left_points + right_points);
      const totalCell = row.querySelector(".total-score-row");

      if (totalCell) {
        totalCell.innerHTML = total;
      } else {
        console.error("Total cell not found");
      }

      // Update the existing hidden input field created by PHP
      const hiddenInput = row.querySelector(
        `input[name='competitor_scores[${competitorId}][${index}][total_score]']`
      );

      if (hiddenInput) {
        hiddenInput.value = total;
      } else {
        console.error("Hidden input not found");
      }

      updateCompetitorsTotal(competitorId);
    }

    function resetRow(rowId) {
      var row = document.getElementById(rowId);
      if (!row) return;

      // Reset radio buttons
      row.querySelectorAll('input[type="radio"]').forEach(function (radio) {
        radio.checked = false;
      });

      // Reset numeric inputs
      row
        .querySelectorAll(
          'input[type="number"], input[type="text"].numeric-input'
        )
        .forEach(function (input) {
          input.value = "";
        });

      // Reset total score
      var totalCell = row.querySelector(".total-score-row");
      if (totalCell) {
        totalCell.textContent = "0";
      }

      // Reset hidden total score input
      var hiddenInput = row.querySelector('input[name$="[total_score]"]');
      if (hiddenInput) {
        hiddenInput.value = "0";
      }

      var competitorId = row.getAttribute("data-competitor-id");
      var index = row.getAttribute("data-index");

      // Update the competitor's total score
      updateCompetitorsTotal(competitorId);

      // Update grand total
      updateGrandTotal();

      if (typeof initializeScores === "function") {
        initializeScores();
      }
    }
    // Update total score for all competitors
    function updateCompetitorsTotal(competitorId) {
      let competitorTotal = 0;

      document
        .querySelectorAll(
          `[data-competitor-id='${competitorId}'] .total-score-row`
        )
        .forEach((cell) => {
          const score = parseInt(cell.textContent) || 0;
          competitorTotal += score;
        });

      const competitorTotalCells = document.querySelectorAll(
        `[data-competitor-id='${competitorId}'] .total-points`
      );
      competitorTotalCells.forEach((cell) => {
        cell.innerHTML = competitorTotal;
      });

      updateGrandTotal();
    }

    // Update grand total
    function updateGrandTotal() {
      let grandTotal = 0;
      document.querySelectorAll(".total-score-row").forEach((cell) => {
        const score = parseInt(cell.textContent) || 0;
        grandTotal += score;
      });

      const grandTotalCell = document.querySelector(`#grand-total-value`);
      if (grandTotalCell) {
        grandTotalCell.textContent = grandTotal;
        console.log("Updated Grand Total:", grandTotal); // Debug log
      } else {
        console.error("Grand Total Cell not found!"); // Error log
      }
    }

    // Call this function whenever scores are updated
    document.addEventListener("DOMContentLoaded", updateGrandTotal);
    // You might also want to call this function after any score updates

    // Timer logic, off/online saving here...
    function attachTimerEvents() {
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
            document.getElementById(
              `elapsed-time-${currentCompetitorId}`
            ).value = "";
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
              toMySQLDatetime(new Date());
          }
          interval = setInterval(() => {
            elapsedTime += 100;
            updateTimerDisplay();
          }, 100);
        }

        function getLocalizedTime(timezone) {
          // Get the server date and time
          const now = new Date();
          // Add 2 hours to the current time
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
          // Convert the date to a localized string
          const localizedTime = new Intl.DateTimeFormat([], options).format(
            now
          );
          return localizedTime;
        }

        // Format a Date as MySQL-compatible datetime: YYYY-MM-DD HH:MM:SS
        function toMySQLDatetime(date) {
          const pad = (n) => String(n).padStart(2, "0");
          return (
            date.getFullYear() + "-" +
            pad(date.getMonth() + 1) + "-" +
            pad(date.getDate()) + " " +
            pad(date.getHours()) + ":" +
            pad(date.getMinutes()) + ":" +
            pad(date.getSeconds())
          );
        }

        // Handle score saving
        function prepareFormData(competitorId) {
          if (competitorId) {
            const stopTime = toMySQLDatetime(new Date());
            const elapsedSeconds = Math.floor(elapsedTime / 1000);

            document.getElementById(`stop-time-${competitorId}`).value =
              stopTime;
            document.getElementById(`elapsed-time-${competitorId}`).value =
              elapsedSeconds;
          }
        }

        // Add event listener to store form data locally if offline, otherwise submit
        const scoringForm = document.getElementById("scoring-form");
        if (scoringForm) {
          scoringForm.addEventListener("submit", (event) => {
            event.preventDefault();
            prepareFormData(currentCompetitorId);

            if (navigator.onLine) {
              syncDataAuto();
            } else {
              console.log("Offline: Storing data locally");
              storeFormDataLocally();
            }
          });
        } else {
          console.error("Form element not found");
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
            const competitorsData = existingData
              ? JSON.parse(existingData)
              : {};
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
          const scoringForm = document.getElementById("scoring-form");
          if (!scoringForm) {
            console.error("Scoring form not found");
            return;
          }

          // Use FormData directly — it preserves nested array keys
          // like competitor_scores[2][37][left_group]
          const formData = new FormData(scoringForm);

          // The form already has a hidden "action" field (competitors_score_update_v2)
          // and a nonce field. Only add nonce if missing.
          if (!formData.has("competitors_score_update_nonce")) {
            formData.append(
              "competitors_score_update_nonce",
              competitorsAdminAjax.nonce
            );
          }

          fetch(competitorsAdminAjax.ajaxurl, {
            method: "POST",
            credentials: "same-origin",
            body: formData,
          })
            .then((response) => {
              if (!response.ok) {
                throw new Error(
                  `Network response was not ok (${response.statusText})`
                );
              }
              return response.json();
            })
            .then((data) => {
              handleServerResponse(data);
            })
            .catch(handleSyncError);
        }

        function handleServerResponse(data) {
          if (data.success) {
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
  }
});

// From hereon there be jQuery because its included in WP
jQuery(document).ready(function ($) {
  // Toggle rows for displaying which rolls a competitor wants to perform
  $(".open-details").on("click", function () {
    // Hide all 'open-details' and 'selected-rolls' rows
    $(".open-details, .selected-rolls").addClass("hidden");

    // Unhide the clicked 'open-details' row
    $(this).removeClass("hidden");

    // Find and toggle the relevant 'selected-rolls' rows
    var nextSibling = $(this).next();
    while (nextSibling.length && !nextSibling.hasClass("open-details")) {
      if (nextSibling.hasClass("selected-rolls")) {
        nextSibling.toggleClass("hidden");
      }
      nextSibling = nextSibling.next();
    }
  });

  // Sort columns in the Personal Data view page when clicking html table headers
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

      $lastField.after(customOrderField);
    }, 150); // A slight delay to ensure the Quick Edit form is rendered
  });

  // Initialize date picker
  $(".date-picker").datepicker({
    dateFormat: "yy-mm-dd",
  });

  // Add Event button functionality for Settings page
  $("#add-event-button").click(function (e) {
    e.preventDefault();
    var newDate = $("#new_competition_date").val();
    var eventName = $("#new_event_name").val();
    if (newDate && eventName) {
      var eventObj = { date: newDate, name: eventName };
      var eventString = JSON.stringify(eventObj);
      // Remove "no items" row if present
      $("#existing_events tbody .no-items").remove();
      $("#existing_events tbody").append(
        `<tr class="event-item" data-date="${escapeHtml(
          newDate
        )}" data-name="${escapeHtml(eventName)}" data-confirm="Remove this unsaved competition?">
                <td><strong>${escapeHtml(newDate)}</strong></td>
                <td>${escapeHtml(eventName)}</td>
                <td><span style="color:#666;font-size:11px;">unsaved</span></td>
                <td>
                  <input type="hidden" name="competitors_options[available_competition_dates][]" value="${encodeURIComponent(
                    eventString
                  )}">
                  <button type="button" class="button-secondary button-small remove-event-button">Remove</button>
                </td>
            </tr>`
      );
      $("#new_competition_date").val("").datepicker("setDate", null);
      $("#new_event_name").val("");
    }
  });

  // Derive a URL-safe slug from a string (JS equivalent of WP sanitize_title)
  function slugify(text) {
    return text
      .toString()
      .toLowerCase()
      .trim()
      .replace(/[^\w\s-]/g, "") // remove non-word chars (except spaces and hyphens)
      .replace(/[\s_]+/g, "-") // spaces/underscores to hyphens
      .replace(/-+/g, "-") // collapse multiple hyphens
      .replace(/^-|-$/g, ""); // trim leading/trailing hyphens
  }

  // Live slug preview when typing class name
  $("#new_class_comment").on("input", function () {
    var slug = slugify($(this).val());
    $("#class-slug-preview").text(slug ? "ID: " + slug : "");
  });

  // Add Class button functionality for Settings page
  // User enters the visible "Class Name" (stored as comment).
  // The internal data name (stored as name) is auto-derived as a slug.
  $("#add-class-button").click(function (e) {
    e.preventDefault();
    var newClassComment = $.trim($("#new_class_comment").val());
    if (!newClassComment) {
      return;
    }
    var newClassName = slugify(newClassComment);
    if (!newClassName) {
      return;
    }
    var classObj = { name: newClassName, comment: newClassComment };
    var classString = JSON.stringify(classObj);
    // Remove "no items" row if present
    $("#existing_classes tbody .no-items").remove();
    $("#existing_classes tbody").append(
      `<tr class="class-item" data-name="${escapeHtml(
        newClassName
      )}" data-comment="${escapeHtml(newClassComment)}">
              <td><strong>${escapeHtml(newClassComment)}</strong></td>
              <td><code>${escapeHtml(newClassName)}</code></td>
              <td>
                <input type="hidden" name="competitors_options[available_competition_classes][]" value="${encodeURIComponent(
                  classString
                )}">
                <button type="button" class="button-secondary button-small remove-class-button">Remove</button>
              </td>
          </tr>`
    );
    $("#new_class_comment").val("");
    $("#class-slug-preview").text("");
  });

  // Remove Class button functionality
  $(document).on("click", ".remove-class-button", function () {
    $(this).closest("tr").remove();
  });

  // Remove Event button functionality
  // Uses per-row data-confirm attribute (set in PHP) so the warning text
  // varies based on whether the event is current/historical/unsaved.
  $(document).on("click", ".remove-event-button", function () {
    var $row = $(this).closest("tr");
    var msg =
      $row.attr("data-confirm") ||
      "Removing this competition deletes its metadata permanently. Continue?";
    if (window.confirm(msg)) {
      $row.remove();
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

  // Re-index checkbox name attributes after add/remove. Text inputs use `[]`
  // so they auto-index on submit, but is_numeric_field/no_right_left checkboxes
  // need explicit numeric indices since unchecked ones don't submit. Indices
  // must match each row's array position in custom_values_*, otherwise data
  // drifts on save.
  function renumberRollRows($wrapper) {
    if (!$wrapper || !$wrapper.length) return;
    $wrapper.find(".roll-item").each(function (idx) {
      var $row = $(this);
      $row.attr("data-index", idx);
      $row.removeClass("alternate");
      if (idx % 2 === 0) $row.addClass("alternate");
      $row.find('input[type="checkbox"]').each(function () {
        var name = $(this).attr("name") || "";
        // matches the trailing [N] of names like
        // competitors_options[is_numeric_field_<class>][N]
        $(this).attr("name", name.replace(/\[\d+\]$/, "[" + idx + "]"));
      });
    });
  }

  // Function to create a new roll field
  // classType = original class name (for form name attributes)
  // classSlug = sanitized slug (for HTML IDs) — falls back to classType if not provided
  function createNewRollField(classType, index, classSlug) {
    var slug = classSlug || classType;
    return `
      <p class="roll-item ${
        index % 2 === 0 ? "alternate" : ""
      }" data-index="${index}">
        <label for="maneuver_${slug}_${index}"><span class="roll-number"></span></label>
        <input type="text" id="maneuver_${slug}_${index}" name="competitors_options[custom_values_${classType}][]" size="60" value="" />
        <label for="points_${slug}_${index}"> Points: </label>
        <input type="text" class="numeric-input" id="points_${slug}_${index}" name="competitors_options[numeric_values_${classType}][]" size="2" maxlength="2" pattern="\\d*" value="0" />
        <label for="numeric_${slug}_${index}"> Numeric:</label>
        <input type="checkbox" id="numeric_${slug}_${index}" name="competitors_options[is_numeric_field_${classType}][${index}]" value="1">
        <label for="no_right_left_${slug}_${index}"> No Right/Left:</label>
        <input type="checkbox" id="no_right_left_${slug}_${index}" name="competitors_options[no_right_left_${classType}][${index}]" value="1">
        <button type="button" class="button custom-button button-primary plus-button" data-class="${classType}" title="Insert new roll below" aria-label="Insert new roll below"></button>
        <button type="button" class="button custom-button remove-row minus-button" title="Remove this roll" aria-label="Remove this roll"></button>
      </p>
    `;
  }

  // Plus button: insert a new empty roll immediately AFTER the clicked row.
  // Falls back to appending at the end of the wrapper if the click somehow
  // isn't inside a row (e.g. an external trigger).
  $(document).on("click", ".plus-button", function () {
    var btnId = $(this).attr("id") || "";
    // Skip event and class add buttons — they have their own handlers
    if (btnId === "add-event-button" || btnId === "add-class-button") {
      return;
    }

    var $row = $(this).closest(".roll-item");
    var $wrapper, classType, classSlug;

    if ($row.length) {
      $wrapper = $row.parent();
      classType = $wrapper.data("class") || $(this).data("class");
      var wrapperId = $wrapper.attr("id") || "";
      classSlug = wrapperId.replace("competitors_roll_names_wrapper_", "");
    } else {
      // Legacy fallback: standalone plus button (id="add_more_roll_names_<slug>")
      classSlug = btnId.replace("add_more_roll_names_", "");
      classType = $(this).data("class") || classSlug;
      $wrapper = $("#competitors_roll_names_wrapper_" + classSlug);
    }

    if (!$wrapper || !$wrapper.length) {
      console.error("Wrapper not found for plus button");
      return;
    }

    var newIndex = $wrapper.find(".roll-item").length;
    var newFieldHtml = createNewRollField(classType, newIndex, classSlug);

    if ($row.length) {
      $row.after(newFieldHtml);
    } else {
      $wrapper.append(newFieldHtml);
    }

    renumberRollRows($wrapper);
  });

  // Remove a roll row from the DOM. Form save reconciles to the option array
  // — no AJAX needed. Renumber after removal so indices match positions.
  $(document).on("click", ".remove-row", function () {
    var $row = $(this).closest(".roll-item");
    var $wrapper = $row.parent();
    if ($wrapper.find(".roll-item").length > 1) {
      $row.remove();
      renumberRollRows($wrapper);
    } else {
      alert("At least one roll must remain. Remove the class instead!");
    }
  });

  // Settings page AJAX add row
  if ($("#settings-page").length) {
    const $wrapper = $("#competitors_roll_names_wrapper");
    const $addButton = $("#add_more_roll_names");

    if ($wrapper.length && $addButton.length) {
      // Function to add a new row
      function addRow() {
        const newIndex = $wrapper.find("p").length;
        const newField = createNewRollField("", newIndex);
        $wrapper.append(newField);
      }

      $addButton.on("click", addRow);

      // Removing a row in settings
      $wrapper.on("click", ".remove-row", function (e) {
        e.preventDefault();
        const $parentWrapper = $(this).closest(".roll-item").parent();
        if ($parentWrapper.find(".roll-item").length > 1) {
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
                  $(`[data-index="${rowIndex}"]`).remove();
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
        } else {
          alert("At least one roll must remain.");
        }
      });

      // Ensure numeric inputs accept max two digits
      $wrapper.on("input", ".numeric-input", function (e) {
        const value = $(this).val();
        $(this).val(value.slice(0, 2));
      });
    }
  }

  // Check the local storage for the admin notice state
  if (localStorage.getItem("instructionsVisible") === "true") {
    $("#instructions-content").show();
  } else {
    $("#instructions-content").hide();
  }

  // Toggle admin notice text
  $("#toggle-instructions").on("click", function () {
    console.log("You have clicked the toggle Button");
    $("#instructions-content").slideToggle(function () {
      console.log("Instructions content toggled");
      // Save the current state in local storage
      localStorage.setItem(
        "instructionsVisible",
        $("#instructions-content").is(":visible")
      );
    });
  });
});
