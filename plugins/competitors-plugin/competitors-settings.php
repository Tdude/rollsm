<?php
function competitors_add_admin_menu() {
    add_menu_page(
        'Competitors Custom Settings', // Page title
        'Competitors Settings', // Menu title
        'manage_options', // Capability
        'competitors-settings', // Menu slug
        'competitors_settings_page', // Function to display the page
        'dashicons-groups', // Icon (optional)
         // 10 Position (optional)
    );
}
add_action('admin_menu', 'competitors_add_admin_menu');


// Display the settings page content
function competitors_settings_page() {
    ?>
    <div class="wrap">
    <h1>Competitors Settings</h1>
    <form method="post" action="options.php">
        <?php
        settings_fields('competitors_settings');
        do_settings_sections('competitors_settings');
        submit_button();
        ?>
    </form>
    </div>
    <?php
}

// Register a new setting for our "Competitors" page
function competitors_settings_init() {
    register_setting('competitors_settings', 'competitors_custom_values');

    add_settings_section(
        'competitors_custom_values_section', 
        __('Custom Values for Competitors', 'wordpress'), 
        'competitors_settings_section_callback', 
        'competitors_settings'
    );

    add_settings_field(
        'competitors_text_field', 
        __('Values', 'wordpress'), 
        'competitors_text_field_render', 
        'competitors_settings', 
        'competitors_custom_values_section'
    );
}
add_action('admin_init', 'competitors_settings_init');

function competitors_settings_section_callback() {
    echo __('Enter each value on a new line.', 'wordpress');
}

function competitors_text_field_render() {
    $roll_name = get_option('competitors_custom_values');
    ?>
    <textarea cols='40' rows='10' name='competitors_custom_values'><?php echo $roll_name; ?></textarea>
    <?php
}
