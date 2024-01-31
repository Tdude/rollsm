// The js
document.addEventListener("DOMContentLoaded", function(){

console.log("I am a public js");

    // Check all boxes with event listener for checkbox "<input type="checkbox" onchange="checkAll(this)" name="checks-all" />"
    function checkAll(e) {
        var checkboxes = document.getElementsByTagName('input');
        if (e.checked) {
            for (var i = 0; i < checkboxes.length; i++) {
                if (checkboxes[i].type == 'checkbox') {
                    checkboxes[i].checked = true;
                }
            }
        } else {
            for (var i = 0; i < checkboxes.length; i++) {
                console.log(i)
                if (checkboxes[i].type == 'checkbox') {
                    checkboxes[i].checked = false;
                }
            }
        }
    }


});



jQuery(document).ready(function($) {
    $('#competitors-list').on('click', '.competitors-list-item', function() {
        var competitorId = $(this).data('competitor-id');
        console.log(competitorId);

        $.ajax({
            url: competitorsAjax.ajaxurl,
            type: 'POST',
            data: {
                action: 'load_competitor_details',
                competitor_id: competitorId
            },
            success: function(response) {
                $('#competitors-details-container').html(response);
            }
        });
    });
});
    

