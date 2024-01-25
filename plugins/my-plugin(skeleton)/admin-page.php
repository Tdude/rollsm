<?php
function my_plugin_add_admin_menu() {
    add_menu_page(
        'My Plugin Settings',
        'My Plugin',
        'manage_options',
        'my-plugin-settings',
        'my_plugin_settings_page'
    );
}
add_action('admin_menu', 'my_plugin_add_admin_menu');

function my_plugin_settings_page() {
    // Content for the settings page
    echo '<div class="wrap"><h1>My Plugin Settings</h1>';
    // Settings form or other content goes here
    echo '</div>';
}
