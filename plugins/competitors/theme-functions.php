// Detta ska vara i WPCF7 pluginet

<fieldset>
    <legend><label>Anmälan för funktionärer till RollSM 2024</label></legend>
    [hidden your-subject default:"Funktionär till RollSM 2024"] 
    
    <label>För- och efternamn</label>[text* your-name autocomplete:your-name] 
    
    <label>E-post</label>[email* your-email autocomplete:your-email] 
    
    <label>Telefon</label>[tel* phone id:phone autocomplete:phone] 
    
    <label>Deltar som funtionär för</label>[radio category id:category use_label_element default:1 "Sekretariat" "Markservice" "Domare" "Matlag" "Övrigt"]
    
    <label>[checkbox checkbox-dinner use_label_element "Vill vara med på middagen lördag den 31:a!"]</label>

    <div class="showhide-wrapper hidden">
        <label>Antal personer inklusive dig på middagen?</label>[select select-guests "1" "2" "3" "4"]

        <label>Skriv om du/ni har några allergier.</label>[text your-allergies]
    </div>
    
    <label>[checkbox confirmation-email use_label_element "Vill ha ett bekräftelsemail på det jag fyllt i!"]</label> 

    [acceptance your-consent] Du godkänner att dina uppgifter sparas av oss enligt GDPR, samt att film och bilder från eventet, där du kan förekomma, publiceras i media. [/acceptance]
    
    [submit "Spara"]
</fieldset>

    



<?php

// Dessa funktioner ska eller kan vara i temats functions.php
// Se spamskydd för mer finlir: https://contactform7.com/2020/07/18/custom-spam-filtering/#more-36765

// Send confirmation email to registered staff
function send_staff_confirmation_email($contact_form) {
    $submission = WPCF7_Submission::get_instance();
    
    if ($submission) {
        $data = $submission->get_posted_data();
        $send_confirmation = isset($data['confirmation-email']) && !empty($data['confirmation-email']);

        if ($send_confirmation) {
            $user_email = sanitize_email($data['your-email']);
            $user_name = sanitize_text_field($data['your-name']);
            $subject = 'Confirmation Email for RollSM 2024';

            $allergies = isset($data['your-allergies']) ? sanitize_text_field($data['your-allergies']) : 'Inga allergier angivna';
            $dinner = isset($data['checkbox-dinner']) ? 'Ja' : 'Nej';

            $category = isset($data['category']) ? (is_array($data['category']) ? implode(', ', array_map('sanitize_text_field', $data['category'])) : sanitize_text_field($data['category'])) : 'Ingen kategori vald';

            $select_guests = isset($data['select-guests']) ? sanitize_text_field($data['select-guests']) : 'Ingen uppgift';

            $message = "Hej $user_name,\n\n" .
                       "Tack för din anmälan som funktionär till RollSM 2024. Här är detaljerna som du skickade in:\n\n" .
                       "För- och efternamn: $user_name\n" .
                       "E-post: $user_email\n" .
                       "Telefon: " . sanitize_text_field($data['phone']) . "\n" .
                       "Deltar som funktionär för: $category\n" .
                       "Vill du vara med på middagen: $dinner\n" .
                       "Antal personer inklusive dig: $select_guests\n" .
                       "Allergier: $allergies\n" .
                       "Godkännande enligt GDPR: " . (isset($data['your-consent']) ? 'Ja' : 'Nej') . "\n\n" .
                       "Vi ser fram emot ditt deltagande!\n\n" .
                       "Bästa hälsningar,\nRollSM Organisationen";

            wp_mail($user_email, $subject, $message);
        }
    }
}
add_action('wpcf7_before_send_mail', 'send_staff_confirmation_email');

// For to open the allergies dropdown in the staff registration form
function add_staff_confirmation_email_script_to_footer() {
    ?>
    <script type="text/javascript">
        jQuery(document).ready(function($) {
            var $dinnerCheckbox = $('input[name="checkbox-dinner[]"]');
            var $showhideWrapper = $('.showhide-wrapper');

            function toggleShowhideWrapper() {
                if ($dinnerCheckbox.is(':checked')) {
                    $showhideWrapper.slideDown();
                } else {
                    $showhideWrapper.slideUp();
                }
            }

            if ($dinnerCheckbox.length) {
                $dinnerCheckbox.on('change', toggleShowhideWrapper);

                // Initiate correct state on page load
                toggleShowhideWrapper();
            }
        });
    </script>
    <?php
}
add_action('wp_footer', 'add_staff_confirmation_email_script_to_footer');



function custom_email_confirmation_validation_filter( $result, $tag ) {
  if ( 'your-email-confirm' == $tag->name ) {
    $your_email = isset( $_POST['your-email'] ) ? trim( $_POST['your-email'] ) : '';
    $your_email_confirm = isset( $_POST['your-email-confirm'] ) ? trim( $_POST['your-email-confirm'] ) : '';
  
    if ( $your_email != $your_email_confirm ) {
      $result->invalidate( $tag, "Are you sure this is the correct address?" );
    }
  }
  
  return $result;
}
add_filter( 'wpcf7_validate_email*', 'custom_email_confirmation_validation_filter', 20, 2 );
  
/// LÄGG TILL MAILADRESSER I WPCF7 admin: david.o.tang@gmail.com, andreas.zander@gmail.com