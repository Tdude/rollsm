<?php
function my_plugin_shortcode() {
    // Shortcode logic
    ob_start();
    // Display content or forms
    return ob_get_clean();
}
add_shortcode('my-plugin', 'my_plugin_shortcode');
