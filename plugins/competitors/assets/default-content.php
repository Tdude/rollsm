<?php
echo <<<HTML
<div class="entry-content">
    <h2>The what</h2>
    <p>This is a plugin for every installation of WordPress which uses a fairly upgraded, less than 5 years old, version of server software. Upon request you can have more details but nothing will break if you try to install it. It has the usual “one-click-install” and uninstall of WordPress.</p>
    <h2>The how</h2>
    <p>Once installed, you need to go to “Competitors settings” in the admin menu and check the prefilled roll names for test.</p>
    <p>You can display the registration form or competitors scoring list with WordPress shortcodes.</p>
    <pre class="wp-block-code">
    <code>&#91;competitors_scoring_public&#93;  show a listing of all competitors and their scoresheets 
&#91;competitors_form_public&#93; show the registration form where competitors fill in their data</code>
    </pre>
    <ol>
        <li><b>Once installed</b>, you need to go to “Competitors settings” in the admin menu and check what rolls (or maneuvers) your competition will include. This gets prefilled on installing the plugin but you might want to change langiuage or insert additional maneuvers.</li>
        <li><b>Use Shortcodes</b> on Wordpress Posts or Pages. They are a convenient means to show this plugin's functionality on any page of your WordPress site. They look like the above for the public competitors scoring display and registration form. You can have them on separate pages for live use or on the same page for convenient testing purposes. They will work either way. Upon install, the plugin creates this very page for you. You can use it or delete it as you wish.</li>
        <li><b>In the WP Admin</b> on the page <em>Competitors settings &gt; Judges scoring</em> you now choose a competitor, click the green Start button, give the competitor some scores and then Save scores. You will occasionally see a pesky spinner and overlay to remind you of the Timer.</li>
        <li><b>Intended for live use!</b> Once you click on another competitor in the Admin as a “Judge”, the timer resets! Keep this in mind. You also have a Pause or Reset button if you need it. Normally, only the (green) Start/Pause button and the (blue) "Save scores" button will be used.</li>
        <li><b>At the registration</b> there is a list of checkboxes for rolls. A competitor can uncheck rolls which they will not try to perform. The rolls they uncheck will be grayed out but fully functional for the judges if the competitor decides to perform those rolls anyway. This is for better communication and make for a more interesting comp with less downtime between competitors.</li>
        <li>If you're a web dev interested in contributing or just want to take the code apart, feel free to clone <a href="https://github.com/Tdude/rollsm">my Repository at Github</a>. Also if anyone has good ideas about making this better, contact me!</li>
    </ol>
</div>
[competitors_scoring_public]
[competitors_form_public]
HTML;
