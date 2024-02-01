//console.log("I am admin js. Yes, really!");

// for judges_scoring_page
document.addEventListener('DOMContentLoaded', function() {
    const headers = document.querySelectorAll('.competitors-header');
    headers.forEach(header => {
        header.addEventListener('click', function() {
            const competitorId = this.dataset.competitor;
            const scores = document.querySelectorAll('.competitors-scores[data-competitor="' + competitorId + '"]');
            scores.forEach(row => row.style.display = row.style.display === 'none' || row.style.display === '' ? 'table-row' : 'none');
            
            // The competitor info row is immediately following the header row in the DOM
            const infoRow = this.nextElementSibling;
            infoRow.style.display = infoRow.style.display === 'none' || infoRow.style.display === '' ? 'table-row' : 'none';

            // Toggle arrow icon
            const icon = this.querySelector('.dashicons');
            if (icon.classList.contains('dashicons-arrow-down-alt2')) {
                icon.classList.remove('dashicons-arrow-down-alt2');
                icon.classList.add('dashicons-arrow-up-alt2');
            } else {
                icon.classList.remove('dashicons-arrow-up-alt2');
                icon.classList.add('dashicons-arrow-down-alt2');
            }
        });
    });


    document.querySelectorAll('.score-input').forEach(input => {
        input.addEventListener('input', function() {
            const nameParts = this.name.split('_');
            const competitorId = nameParts[nameParts.length - 2]; // Adjusted index for competitor ID
            const rollIndex = nameParts[nameParts.length - 1]; // Adjusted index for roll index
    
            const scoreNames = ['left_score', 'left_deduct', 'right_score', 'right_deduct'].map(
                type => `${type}_${competitorId}_${rollIndex}`
            );
    
            const [leftScore, leftDeduct, rightScore, rightDeduct] = scoreNames.map(
                name => parseInt(document.querySelector(`[name='${name}']`).value) || 0
            );
    
            let total = (leftScore - leftDeduct) + (rightScore - rightDeduct);
            total = Math.max(total, 0); // Ensure total is not negative
    console.log(total);
            const totalField = document.querySelector(`[name='total_${competitorId}_${rollIndex}']`);
            if (totalField) {
                totalField.value = total;
            }
        });
    });
    
});

// sorts by clicking on competitors data table headers in admin
jQuery(document).ready(function($) {
    $('#sortable-table th').on('click', function() {
        var table = $(this).parents('table').eq(0);
        var rows = table.find('tr:gt(0)').toArray().sort(comparer($(this).index()));
        this.asc = !this.asc;
        if (!this.asc) { rows = rows.reverse(); }
        for (var i = 0; i < rows.length; i++) { table.append(rows[i]); }
    });

    function comparer(index) {
        return function(a, b) {
            var valA = getCellValue(a, index), valB = getCellValue(b, index);
            return $.isNumeric(valA) && $.isNumeric(valB) ? valA - valB : valA.localeCompare(valB);
        };
    }

    function getCellValue(row, index) { 
        return $(row).children('td').eq(index).text(); 
    }

    // Adding the JS for more rows
    $('#add_more_roll_names').click(function() {
        $('#competitors_roll_names_wrapper').append('<p><input type=\"text\" name=\"competitors_custom_values[]\" size=\"60\" value=\"\" /></p>');
    });

});// docready



   