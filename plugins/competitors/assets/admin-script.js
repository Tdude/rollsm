document.addEventListener('DOMContentLoaded', function() {

    if (document.getElementById('spinner')) {
        const spinner = document.getElementById('spinner');

        document.querySelectorAll('.competitors-header').forEach(header => {
            header.addEventListener('click', function() {
                const competitorId = this.getAttribute('data-competitor');
                const spinner = document.getElementById('spinner');

                // Toggle visibility for other competitors' sections
                document.querySelectorAll('.competitors-header').forEach(otherHeader => {
                    if (otherHeader.getAttribute('data-competitor') !== competitorId) {
                        document.querySelectorAll(`.th-columns[data-competitor="${otherHeader.getAttribute('data-competitor')}"], .competitors-info[data-competitor="${otherHeader.getAttribute('data-competitor')}"], .competitors-scores[data-competitor="${otherHeader.getAttribute('data-competitor')}"], .competitors-totals[data-competitor="${otherHeader.getAttribute('data-competitor')}"]:not(.grand-total)`).forEach(row => {
                            row.classList.add('hidden');
                        });
                    }
                });

                // Toggle visibility for the clicked competitor's sections
                const rowsToToggle = document.querySelectorAll(`.th-columns[data-competitor="${competitorId}"], .competitors-scores[data-competitor="${competitorId}"], .competitors-info[data-competitor="${competitorId}"], .competitors-totals[data-competitor="${competitorId}"]:not(.grand-total)`);
                let anyRowVisible = false;
                rowsToToggle.forEach(row => {
                    row.classList.toggle('hidden');
                    if (!row.classList.contains('hidden')) {
                        anyRowVisible = true;
                    }
                });

                // Calculate and set spinner position and size
                if (anyRowVisible) {
                    const firstRow = rowsToToggle[0];
                    const lastRow = rowsToToggle[rowsToToggle.length - 1];
                    // Assuming the spinner's top offset calculation within the event listener:
                    const containerRect = document.getElementById('judges-scoring-container').getBoundingClientRect();
                    const firstRowRect = firstRow.getBoundingClientRect();
                    const lastRowRect = lastRow.getBoundingClientRect();
                    // Calculate the top position relative to the container, not the viewport
                    const topPosition = firstRowRect.top - containerRect.top;
                    // Cover from top of first row to bottom of last row
                    const totalHeight = lastRowRect.bottom - firstRowRect.top;
                    // Apply styles to the spinner based on these calculations
                    showSpinner(); // Here is where it should be for clicking rows
                    spinner.style.position = 'absolute';
                    spinner.style.top = `${topPosition}px`;
                    spinner.style.height = `${totalHeight}px`;
                    spinner.style.left = '0';
                    spinner.style.right = '0';

                } else {
                    hideSpinner(); // If no rows are visible
                }

                // Toggle icon direction
                toggleIcons(this);
            });
        });

        function showSpinner() {
            const spinner = document.getElementById('spinner');
            spinner.classList.remove('hidden');
            spinner.classList.add('show');
            spinner.style.display = 'flex'; // Ensure spinner is visible
            console.log("Show");
        }

        function hideSpinner() {
            const spinner = document.getElementById('spinner');
            spinner.classList.add('hidden');
            spinner.classList.remove('show');
            spinner.style.display = 'none'; // Hide spinner
            console.log("Hide");
        }

        function toggleIcons(clickedHeader) {
            document.querySelectorAll('.competitors-header .dashicons').forEach(icon => {
                icon.classList.remove('dashicons-arrow-up-alt2');
                icon.classList.add('dashicons-arrow-down-alt2');
            });
            clickedHeader.querySelector('.dashicons').classList.toggle('dashicons-arrow-down-alt2');
            clickedHeader.querySelector('.dashicons').classList.toggle('dashicons-arrow-up-alt2');
        }
    }


    // Admin calculate and update the total score
    if (document.getElementById('judges-scoring')) {
        // Event delegation for score input to handle dynamically added elements
        document.addEventListener('input', function(e) {
            if (e.target && e.target.classList.contains('score-input')) {
                const row = e.target.closest('tr');
                const competitorId = row.dataset.competitor;
                calculateAndUpdateTotalScore(row, competitorId);
            }
        });
        // Function to calculate and update the total score for a competitor's row in the admin.
        function calculateAndUpdateTotalScore(row, competitorId) {
            // Initialize variables for each score component
            let left = 0, left_deduct = 0, right = 0, right_deduct = 0;

            // Collect and assign values to each score component based on the input names
            row.querySelectorAll('.score-input').forEach(input => {
                const name = input.name;
                const value = parseInt(input.value) || 0;
                if (name.includes('left_score')) {
                    left = value;
                } else if (name.includes('left_deduct')) {
                    left_deduct = value;
                } else if (name.includes('right_score')) {
                    right = value;
                } else if (name.includes('right_deduct')) {
                    right_deduct = value;
                }
            });

            // Ensure deducts are not greater than their corresponding scores
            left_deduct = Math.min(left_deduct, left);
            right_deduct = Math.min(right_deduct, right);

            // Calculate total score ensuring it's not below zero
            let total = Math.max(0, (left - left_deduct) + (right - right_deduct));

            // Find the total score input for this row and update its value
            const totalInput = row.querySelector('input.score-input[name*="total"]');
            if (totalInput) {
                totalInput.value = total >= 0 ? total : 0; // Additional check redundant due to Math.max above
            }
        }
    }


    // Sort columns in admin html table "Personal data"
    if (document.getElementById('sortable-table')) {
        var tableHeaders = document.querySelectorAll('#sortable-table th');

        Array.from(tableHeaders).forEach(function(header) {
            header.addEventListener('click', function() {
                var table = this.closest('table');
                var rowsArray = Array.from(table.querySelectorAll('tbody tr')); // Corrected to select all data rows
                var index = Array.from(table.querySelectorAll('th')).indexOf(this);
                var asc = !(this.asc = !this.asc);

                rowsArray.sort(function(rowA, rowB) {
                    var cellA = rowA.querySelectorAll('td')[index].textContent;
                    var cellB = rowB.querySelectorAll('td')[index].textContent;
                    var isNumericA = !isNaN(parseFloat(cellA)) && isFinite(cellA);
                    var isNumericB = !isNaN(parseFloat(cellB)) && isFinite(cellB);

                    return isNumericA && isNumericB ? cellA - cellB : cellA.localeCompare(cellB);
                });

                if (asc) { rowsArray.reverse(); }

                rowsArray.forEach(function(row) { table.querySelector('tbody').appendChild(row); });
            });
        });
    }


    // Settings page AJAX add/remove rows
    if (document.getElementById('settings-page')) {
        const wrapper = document.getElementById('competitors_roll_names_wrapper');
        const addButton = document.getElementById('add_more_roll_names');

        if (!wrapper || !addButton) {
            // Exit if elements not found
            return;
        }

        function addRow() {
            const newIndex = wrapper.querySelectorAll('p').length;
            const newField = document.createElement('p');
            newField.setAttribute('data-index', newIndex);
            newField.innerHTML = `<label for="maneuver_${newIndex}">Maneuver: </label>` +
                                    `<input type="text" id="maneuver_${newIndex}" name="competitors_custom_values[]" size="60" />` +
                                    `<label for="points_${newIndex}"> Points: </label>` +
                                    `<input type="text" class="numeric-input" id="points_${newIndex}" name="competitors_numeric_values[]" size="2" maxlength="2" pattern="\\d*" title="Only 2 digits allowed" />` +
                                    `<button type="button" class="button custom-button button-secondary remove-row">Remove</button>`;
            wrapper.appendChild(newField);
        }

        addButton.addEventListener('click', addRow);

        wrapper.addEventListener('click', function(e) {
            if (e.target.classList.contains('remove-row')) {
                e.preventDefault(); // Prevent form submission
                if (confirm('Remove, destroy, kill this row irrevocably?')) {
                    const rowIndex = e.target.parentNode.getAttribute('data-index');
                    const nonce = document.querySelector('#competitors_nonce').value;

                    // AJAX request to WordPress
                    fetch(competitorsData.ajaxurl, {
                        method: 'POST',
                        credentials: 'same-origin',
                        headers: {
                            'Content-Type': 'application/x-www-form-urlencoded',
                        },
                        body: new URLSearchParams({
                            action: 'remove_competitor_row',
                            index: rowIndex,
                            security: competitorsData.nonce,
                        })
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            console.log(data.message); // Log success message
                            e.target.parentNode.remove(); // Remove the parent <p> element
                            alert('Row removed successfully.');
                        } else {
                            // Handle failure
                            console.error(data.message); // Log failure message
                            alert('Failed to remove row.');
                        }
                    })
                    .catch(error => {
                        console.error('Error:', error); // Log error
                        alert('Error removing row.');
                    });
                }    
            }
        });

        wrapper.addEventListener('input', function(e) {
            if (e.target.classList.contains('numeric-input')) {
                e.target.value = e.target.value.slice(0, 2); // Ensure only 2 digits
            }
        });
    }


    // Timer/buttons/overlay mess in admin, judges scores page. Headache.
    if (document.getElementById('timer')) {
        var timer = document.getElementById('timer');
        var saveScoresBtn = document.querySelector('.save-scores');
        var form = document.querySelector('form');

        if (timer) {
            let timerStarted = false;
            let paused = true;
            let elapsedTime = 0;
            let interval;
            let currentCompetitorId = null;

            const timerDisplay = document.getElementById('timer-display');
            const startBtn = document.getElementById('start-timer');
            const resetBtn = document.getElementById('reset-timer');
            if (saveScoresBtn) {
                saveScoresBtn.addEventListener('click', function(e) {
                    // Prevent default form submission to ensure stop time is updated before submitting
                    e.preventDefault();
                    // Stop the timer and update the stop time just before submission
                    if (timerStarted && !paused) {
                        stopTimerAndUpdateStopTime();
                    }
                    // Optionally, manually submit the form here if default submission was prevented
                    form.submit();
                });
            }
            function stopTimerAndUpdateStopTime() {
                clearInterval(interval);
                paused = true;
                timerStarted = false;
                let stopTime = new Date().toISOString();
                if (currentCompetitorId) {
                    document.getElementById(`stop-time-${currentCompetitorId}`).value = stopTime;
                }
                updateTimerDisplay();
                startBtn.textContent = 'Start';
                showSpinner();
            }
            // Listen for clicks on competitor headers to set the currentCompetitorId
            document.querySelectorAll('.competitors-header').forEach(header => {
                header.addEventListener('click', function() {
                    currentCompetitorId = this.getAttribute('data-competitor');
                    resetTimerDisplayAndData(); // Reset when a new competitor is selected
                    // console.log(`Current Competitor ID: ${currentCompetitorId}`);
                });
            });

            function resetTimerDisplayAndData() {
                clearInterval(interval);
                timerStarted = false;
                paused = true;
                elapsedTime = 0;
                updateTimerDisplay();
                startBtn.textContent = 'Start';
                // Dont trigger spinner here. It loops.
                if (currentCompetitorId) {
                    document.getElementById(`start-time-${currentCompetitorId}`).value = '';
                    document.getElementById(`stop-time-${currentCompetitorId}`).value = '';
                }
            }

            function updateTimerDisplay() {
                const hours = Math.floor(elapsedTime / 3600000).toString().padStart(2, '0');
                const minutes = Math.floor((elapsedTime % 3600000) / 60000).toString().padStart(2, '0');
                const seconds = Math.floor((elapsedTime % 60000) / 1000).toString().padStart(2, '0');
                timerDisplay.textContent = `${hours}:${minutes}:${seconds}`;
            }

            resetBtn.addEventListener('click', function() {
                var resetConfirmed = confirm("Are you sure you want to reset the timer?");
                if (resetConfirmed) {
                    resetTimerDisplayAndData();
                } else {
                    // Logic for when reset is canceled; potentially continue the timer.
                    console.log("Timer reset canceled.");
                }
            });

            startBtn.addEventListener('click', function(e) {
                e.preventDefault();
                if (!currentCompetitorId) {
                    alert('Please select a competitor first.');
                    return;
                }

                if (!timerStarted) {
                    timerStarted = true;
                    paused = false;
                    startBtn.textContent = 'Pause';
                    if (elapsedTime === 0) {
                        let startTime = new Date().toISOString();
                        document.getElementById(`start-time-${currentCompetitorId}`).value = startTime;
                    }
                    interval = setInterval(function() {
                        elapsedTime += 100;
                        updateTimerDisplay();
                    }, 100);
                    hideSpinner();
                } else if (!paused) {
                    clearInterval(interval);
                    paused = true;
                    startBtn.textContent = 'Continue';
                    let pauseTime = new Date().toISOString();
                    document.getElementById(`stop-time-${currentCompetitorId}`).value = pauseTime;
                    showSpinner();
                } else {
                    paused = false;
                    startBtn.textContent = 'Pause';
                    interval = setInterval(function() {
                        elapsedTime += 100;
                        updateTimerDisplay();
                    }, 100);
                    hideSpinner();
                } 
            });
        }
    }

});


// "Quick edit" custom order in Admin list view
jQuery(document).ready(function($) {
    $(document).on('click', '.editinline', function() {

        var postID = $(this).closest('tr').attr('id');
        postID = postID.replace("post-", "");

        var customOrderValue = $('#post-' + postID).find('.column-custom_order').text();
        // Clear any previously added custom order fields to avoid duplicates in list
        $('.competitors-custom-order-field').remove();
        // Ensure the Quick Edit row is fully visible before appending
        setTimeout(function() {
            // Find the right spot for the custom order field
            var $lastField = $('.inline-edit-row').filter(':visible').find('.inline-edit-col .inline-edit-group:last');

            // If your Quick Edit form layout differs, you might need to adjust the above selector
            var customOrderField =  '<div class="inline-edit-group competitors-custom-order-field">' +
                                    '<label><span class="title">Order</span>' +
                                    '<span class="input-text-wrap"><input type="number" name="competitors_custom_order" value="' + customOrderValue.trim() + '">' +
                                    '</span></label></div>';

            // Append or prepend based on your layout needs
            $lastField.after(customOrderField);
        }, 150); // A slight delay to ensure the Quick Edit form is fully rendered
    });
});
