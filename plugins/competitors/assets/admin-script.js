document.addEventListener('DOMContentLoaded', function() {

    // Toggle visibility of score and info rows in admin
    document.querySelectorAll('.competitors-header').forEach(header => {
        header.addEventListener('click', function() {
            const competitorId = this.dataset.competitor;
    
            // Close all rows except for the clicked competitor's rows
            document.querySelectorAll('.competitors-scores, .competitors-info').forEach(row => {
                if (row.dataset.competitor !== competitorId) {
                    row.classList.add('hidden');
                }
            });
    
            // Toggle visibility for the clicked competitor's scores and info rows
            document.querySelectorAll(`.competitors-scores[data-competitor="${competitorId}"], .competitors-info[data-competitor="${competitorId}"]`).forEach(row => {
                row.classList.toggle('hidden');
            });
    
            // Reset all arrow icons to point down
            document.querySelectorAll('.competitors-header .dashicons').forEach(icon => {
                icon.classList.remove('dashicons-arrow-up-alt2');
                icon.classList.add('dashicons-arrow-down-alt2');
            });
    
            // Toggle the arrow icon direction for the clicked header
            const icon = this.querySelector('.dashicons');
            if (icon) {
                icon.classList.toggle('dashicons-arrow-down-alt2');
                icon.classList.toggle('dashicons-arrow-up-alt2');
            }
        });
    });
    


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

        // Calculate total score
        let total = (left - left_deduct) + (right - right_deduct);

        // Find the total score input for this row and update its value
        const totalInput = row.querySelector('input.score-input[name*="total"]');
        if (totalInput) {
            totalInput.value = total;
        }
    }




    // Sort columns in admin table "Personal data"
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



 
    if (document.getElementById('settings-page')) {
        const wrapper = document.getElementById('competitors_roll_names_wrapper');
        const addButton = document.getElementById('add_more_roll_names');

        if (!wrapper || !addButton) {
            // Exit if required elements are not found
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


    var timer = document.getElementById('timer');
    if (timer) {
        let timerStarted = false;
        let paused = true;
        let elapsedTime = 0;
        const timerDisplay = document.getElementById('timer-display');
        const startBtn = document.getElementById('start-timer');
        const stopBtn = document.getElementById('stop-timer');
        const resetBtn = document.getElementById('reset-timer');
        let interval;

        function updateTimerDisplay() {
            const hours = String(Math.floor(elapsedTime / 3600000)).padStart(2, '0');
            const minutes = String(Math.floor((elapsedTime % 3600000) / 60000)).padStart(2, '0');
            const seconds = String(Math.floor((elapsedTime % 60000) / 1000)).padStart(2, '0');
            // Display tenths as fixed ".00" to simulate precision without actual tenths calculation
            timerDisplay.textContent = `${hours}:${minutes}:${seconds}.00`;
        }

        startBtn.addEventListener('click', function(e) {
            e.preventDefault(); // Prevent any default button action
            if (!timerStarted) {
                startTime = Date.now() - elapsedTime;
                timerStarted = true;
                paused = false;
                startBtn.textContent = 'Pause';
                interval = setInterval(function() {
                    elapsedTime = Date.now() - startTime;
                    updateTimerDisplay();
                }, 1000); // Update every 1000 milliseconds (1 second)
            } else if (paused) {
                startTime = Date.now() - elapsedTime;
                paused = false;
                startBtn.textContent = 'Pause';
                interval = setInterval(function() {
                    elapsedTime = Date.now() - startTime;
                    updateTimerDisplay();
                }, 1000); // Continue updating every second
            } else {
                clearInterval(interval);
                paused = true;
                startBtn.textContent = 'Continue';
            }
        });

        stopBtn.addEventListener('click', function() {
            clearInterval(interval);
            paused = true;
            timerStarted = false;
            startBtn.textContent = 'Start';
        });

        resetBtn.addEventListener('click', function() {
            clearInterval(interval);
            paused = true;
            timerStarted = false;
            elapsedTime = 0;
            updateTimerDisplay();
            startBtn.textContent = 'Start';
        });
    }




});
    