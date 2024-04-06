<?php

return [
    'messages' => [
        'no_competitors' => esc_html__('Looks like there are no competitors to score right now. Please add some competitors or check back later.', 'competitors'),
        'access_denied' => esc_html__('Access denied to scoring, dude. You dont seem to be The Judge.','competitors'),
    ],
    'labels' => [
        'function_title' => esc_html__('Judges Scoring Page', 'competitors'),
        'timer' => esc_html__('Timer', 'competitors'),
    ],
    'titles' => [
        'start_timer' => esc_attr__('Start timer before scoring competitors!', 'competitors'),
        'save_scores' => esc_attr__('Saves scores and time, resets Timer', 'competitors'),
        'reset_timer' => esc_attr__('This button and changing competitor resets Timer', 'competitors'),
        'competitor_row' => esc_attr__('Clicking here always resets Timer. Careful!', 'competitors'),
    ],
    'paragraph' => [
        'clicking_info' => esc_html__('Clicking any competitor name row <b><i>always resets the timer</i></b>. Timing for a particular competitor can be Paused or saved when you click "Save scores". This is live score timing. <b><i>There is no going back to adjust!</i></b> If you resave a competitor\'s score, the timing for that competitor will be reset. If you change competitor view, timing will reset. Do not mess around during the competition. You have now been warned. Practice. ', 'competitors'),
        'contact_admin' => esc_html('Please contact the Admin for questions: ','competitors'),
        // Add more labels as needed
    ],
];
