document.addEventListener("DOMContentLoaded", function() {

    var masterCheckbox = document.getElementById('check_all');
    if (masterCheckbox) {
        masterCheckbox.addEventListener('change', function() {
            checkAll(this); // 'this' is the #check_all checkbox
        });
    }

    function checkAll(ele) {
        var checkboxes = document.querySelectorAll('input[type="checkbox"].roll-checkbox');
        if (checkboxes.length > 0) {
            for (var i = 0; i < checkboxes.length; i++) {
                if (checkboxes[i] !== ele) {
                    checkboxes[i].checked = ele.checked;
                }
            }
        }
    }

    const competitorsList = document.getElementById('competitors-list');
    const spinner = document.getElementById("spinner");

    function showSpinner() {
        spinner.style.display = 'flex';
        requestAnimationFrame(() => {
            spinner.style.opacity = '1';
        });
    }
    
    function hideSpinner() {
        spinner.style.opacity = '0';
        spinner.addEventListener('transitionend', function handler(e) {
            if (e.propertyName === 'opacity') {
                spinner.style.display = 'none';
                spinner.removeEventListener('transitionend', handler);
            }
        });
    }

    // Fetch and show the details container
    function showDetailsContainer() {
        var detailsContainer = document.getElementById('competitors-details-container');
        detailsContainer.style.display = 'block'; // or 'flex' 
    }

    if (competitorsList) {
        competitorsList.addEventListener('click', function(e) {
            showSpinner();

            if (e.target && e.target.matches('.competitors-list-item')) {
                var competitorId = e.target.getAttribute('data-competitor-id');
                var currentItem = document.querySelector('.competitors-list-item.current');
                if (currentItem) {
                    currentItem.classList.remove('current');
                }
                e.target.classList.add('current');
    
                showDetailsContainer();

                fetch(competitorsAjax.ajaxurl, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: 'action=load_competitor_details&competitor_id=' + encodeURIComponent(competitorId)
                })
                .then(response => response.text())
                .then(response => {
                    document.getElementById('competitors-details-container').innerHTML = response;
                    hideSpinner();
                })
                .catch(error => {
                    console.error('Error:', error);
                    hideSpinner();
                });
            }
        });
    }


    document.getElementById('close-details').addEventListener('click', function(e) {
        e.preventDefault();
        document.getElementById('competitors-details-container').style.display = 'none';
        //document.getElementById('competitors-details-container').innerHTML = ''; // Optional
        hideSpinner(); 
        var currentItem = document.querySelector('.competitors-list-item.current');
        if (currentItem) {
            currentItem.classList.remove('current');
        }
    });

    

});
