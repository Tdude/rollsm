// The js
document.addEventListener("DOMContentLoaded", function(){


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