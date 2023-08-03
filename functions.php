<?php

/**
 * Get all posts' slugs for Next.js static generation
 */
function get_post_slugs() {
    global $wpdb;

    // Query to fetch the list of post slugs
    $query = "SELECT post_name FROM {$wpdb->prefix}posts WHERE post_type = 'post' AND post_status = 'publish'";
    $results = $wpdb->get_results($query);

    $slugs = array();
    foreach ($results as $result) {
        // Collect all slugs in the array
        $slugs[] = $result->post_name;
    }
    return $slugs;
}

add_action('rest_api_init', function () {
    // Register a REST route to expose the post slugs
    register_rest_route('f97/v1', '/slugs', array(
        'methods'  => 'GET',
        'callback' => 'get_post_slugs',
    ));
});

/**
 * Get post image for REST API response.
 *
 * @param array $post The post object.
 * @return array The post image URL.
 */
function get_post_img_for_api($post) {
    $post_img = array();
    $post_img['url'] = get_the_post_thumbnail_url($post['id']);
    return $post_img;
}

// Register REST field for post image.
register_rest_field(
    'post',                  // Post type to apply the field.
    'post_img',              // Name of the field.
    array(
        'get_callback'    => 'get_post_img_for_api', // Callback function to get the field value.
        'update_callback' => null,                 // No update callback as the field is read-only.
        'schema'          => null,                 // No schema for the field.
    )
);

/**
 * Get categories links for a post in REST API response.
 *
 * @param array $post The post object.
 * @return array The post categories with their term IDs, names, and links.
 */
function wp_rest_get_categories_links($post) {
    $post_categories = array();
    $categories = wp_get_post_terms($post['id'], 'category', array('fields' => 'all'));

    foreach ($categories as $term) {
        // Get the term link
        $term_link = get_term_link($term);

        // Skip to the next category if there's an error with the term link
        if (is_wp_error($term_link)) {
            continue;
        }

        // Add category details to the post_categories array
        $post_categories[] = array(
            'term_id' => $term->term_id,
            'name'    => $term->name,
            'link'    => $term_link,
        );
    }

    return $post_categories;
}

// Register REST field for post categories.
register_rest_field(
    'post',                           // Post type to apply the field.
    'post_categories',                // Name of the field.
    array(
        'get_callback'    => 'wp_rest_get_categories_links', // Callback function to get the field value.
        'update_callback' => null,                          // No update callback as the field is read-only.
        'schema'          => null,                          // No schema for the field.
    )
);

/**
 * Get plain excerpts for a post in REST API response.
 *
 * @param array $post The post object.
 * @return array The post excerpts in different lengths.
 */
function wp_rest_get_plain_excerpt($post) {
    $excerpts = array();

    // Excerpt with 160 words (nine).
    $excerpts['nine'] = wp_trim_words(get_the_content($post['id']), 160);

    // Excerpt with 70 words (four).
    $excerpts['four'] = wp_trim_words(get_the_content($post['id']), 70);

    // Plain excerpt (default length).
    $excerpts['rss'] = get_the_excerpt($post['id']);

    return $excerpts;
}

// Register REST field for post excerpts.
register_rest_field(
    'post',                                 // Post type to apply the field.
    'post_excerpt',                         // Name of the field.
    array(
        'get_callback'    => 'wp_rest_get_plain_excerpt', // Callback function to get the field value.
        'update_callback' => null,                        // No update callback as the field is read-only.
        'schema'          => null,                        // No schema for the field.
    )
);

/**
 * Get post meta data for REST API response.
 *
 * @param array $post The post object.
 * @return array An array containing various post meta data.
 */
function get_post_meta_for_api($post) {
    $post_meta = array();

    // Views count meta.
    $post_meta['views'] = get_post_meta($post['id'], 'post_views_count', true);

    // Custom link meta.
    $post_meta['link'] = get_post_meta($post['id'], 'link', true);

    // Status meta.
    $post_meta['status'] = get_post_meta($post['id'], 'status', true);

    // Featured image meta.
    $post_meta['img'] = wp_get_attachment_image_src(get_post_thumbnail_id($post['id']), 'full');

    // Post title meta.
    $post_meta['title'] = get_the_title($post['id']);

    // First tag name meta.
    $tags = get_the_tags($post['id']);
    $post_meta['tag_name'] = !empty($tags) ? $tags[0]->name : '';

    // Reading meta (word count and time required).
    $content = get_the_content($post['id']);
    $post_meta['reading']['word_count'] = mb_strlen(preg_replace('/\s/', '', html_entity_decode(strip_tags($content))), 'UTF-8');
    $post_meta['reading']['time_required'] = ceil($post_meta['reading']['word_count'] / 300);

    // FineTool meta (if available).
    if (!empty(get_post_meta($post['id'], 'itemName', true))) {
        $post_meta['fineTool'] = array(
            'itemName'      => get_post_meta($post['id'], 'itemName', true),
            'itemDes'       => get_post_meta($post['id'], 'itemDes', true),
            'itemLinkName'  => get_post_meta($post['id'], 'itemLinkName', true),
            'itemLink'      => get_post_meta($post['id'], 'itemLink', true),
            'itemImgBorder' => get_post_meta($post['id'], 'itemImgBorder', true),
        );
    }

    // Additional image link meta (if available).
    if (!empty(get_post_meta($post['id'], 'linkImg', true))) {
        $post_meta['linkImg'] = get_post_meta($post['id'], 'linkImg', true);
    }

    // Mark count meta.
    $post_meta['mark_count'] = !empty(get_post_meta($post['id'], 'mark_count', true)) ? (int)get_post_meta($post['id'], 'mark_count', true) : 0;

    // Podcast meta (if available).
    if (!empty(get_post_meta($post['id'], 'podcast_name_chinese', true))) {
        $post_meta['podcast'] = array(
            'chineseName' => get_post_meta($post['id'], 'podcast_name_chinese', true),
            'englishName' => get_post_meta($post['id'], 'podcast_name_english', true),
            'episode'     => get_post_meta($post['id'], 'podcast_episode', true),
            'audioUrl'    => get_post_meta($post['id'], 'podcast_audio_url', true),
            'episodeUrl'  => get_post_meta($post['id'], 'podcast_episode_url', true),
            'duration'    => get_post_meta($post['id'], 'podcast_duration', true),
            'fileSize'    => get_post_meta($post['id'], 'podcast_file_size', true),
        );
    }

    return $post_meta;
}

// Register REST field for post meta on 'post' post type.
register_rest_field(
    'post',
    'post_metas',
    array(
        'get_callback'    => 'get_post_meta_for_api', // Callback function to get the field value.
        'update_callback' => null,                    // No update callback as the field is read-only.
        'schema'          => null,                    // No schema for the field.
    )
);

// Register REST field for post meta on 'page' post type.
register_rest_field(
    'page',
    'post_metas',
    array(
        'get_callback'    => 'get_post_meta_for_api', // Callback function to get the field value.
        'update_callback' => null,                    // No update callback as the field is read-only.
        'schema'          => null,                    // No schema for the field.
    )
);

/**
 * Handle post visit count.
 *
 * @param WP_REST_Request $params The REST request parameters.
 * @return array|WP_Error An array with updated status and visit count or WP_Error if there's an error.
 */
function handle_visit($params) {
    $id = $params['id'];
    $visitCountBefore = (int) get_post_meta($id, 'post_views_count', true);

    if (!empty($id)) {
        // If the visit count is not set, set it to 0.
        if (!$visitCountBefore) {
            $visitCountBefore = 0;
        }

        // Update the visit count.
        $status = update_post_meta($id, 'post_views_count', $visitCountBefore + 1);

        // Return updated status and visit count in an array.
        return $status
            ? array(
                'status'        => true,
                'visitCountNow' => $visitCountBefore + 1,
            )
            : new WP_Error('update_failed', 'Unknown error', array('status' => 404));
    } else {
        // Return WP_Error if the post ID is empty or does not exist.
        return new WP_Error('post_not_found', 'Invalid post ID', array('status' => 404));
    }
}

// Register REST route for handling post visit count.
add_action('rest_api_init', function () {
    register_rest_route(
        'f97/v1',
        '/visit/(?P<id>\d+)',
        array(
            'methods'   => 'GET',
            'callback'  => 'handle_visit',
        )
    );
});

/**
 * Get previous and next posts for REST API response.
 *
 * @param array $post The post object.
 * @return array An array containing the previous and next post details.
 */
function get_post_prenext_for_api($post) {
    $array = array();

    // Get the previous post.
    $prev_post = get_previous_post(false, '');

    // Get the next post.
    $next_post = get_next_post(false, '');

    // Add details of the previous post to the array.
    $array['prev'][0] = $prev_post ? $prev_post->post_name : '';
    $array['prev'][1] = $prev_post ? $prev_post->post_title : '';
    $array['prev'][2] = $prev_post ? wp_get_post_categories($prev_post->ID)[0] : '';

    // Add details of the next post to the array.
    $array['next'][0] = $next_post ? $next_post->post_name : '';
    $array['next'][1] = $next_post ? $next_post->post_title : '';
    $array['next'][2] = $next_post ? wp_get_post_categories($next_post->ID)[0] : '';

    return $array;
}

// Register REST field for previous and next posts.
register_rest_field(
    'post',
    'post_prenext',
    array(
        'get_callback'    => 'get_post_prenext_for_api', // Callback function to get the field value.
        'update_callback' => null,                        // No update callback as the field is read-only.
        'schema'          => null,                        // No schema for the field.
    )
);

/**
 * Handle post marking.
 *
 * @param WP_REST_Request $params The REST request parameters.
 * @return array|WP_Error An array with updated status and mark count or WP_Error if there's an error.
 */
function handle_mark($params) {
    $id = $params['id'];
    $markCountBefore = (int) get_post_meta($id, 'mark_count', true);

    if (!empty($id)) {
        // If the mark count is not set, set it to 0.
        if (!$markCountBefore) {
            $markCountBefore = 0;
        }

        // Update the mark count.
        $status = update_post_meta($id, 'mark_count', $markCountBefore + 1);

        // Return updated status and mark count in an array.
        return $status
            ? array(
                'status'       => true,
                'markCountNow' => $markCountBefore + 1,
            )
            : new WP_Error('update_failed', 'Unknown error', array('status' => 404));
    } else {
        // Return WP_Error if the post ID is empty or does not exist.
        return new WP_Error('post_not_found', 'Invalid post ID', array('status' => 404));
    }
}

// Register REST route for handling post marking.
add_action('rest_api_init', function () {
    register_rest_route(
        'f97/v1',
        '/mark/(?P<id>\d+)',
        array(
            'methods'  => 'GET',
            'callback' => 'handle_mark',
        )
    );
});

/**
 * Get posts for RSS feed.
 *
 * @return array An array containing post IDs, titles, contents, and dates.
 */
function get_posts_for_rss_feed() {
    $posts = get_posts(array(
        'posts_per_page' => -1,
        'category__not_in' => [5, 2, 74, 120, 58],
    ));

    $slugs = array_column($posts, 'post_name');
    $titles = array_column($posts, 'post_title');
    $contents = array_column($posts, 'post_content_filtered');
    $dates = array_column($posts, 'post_date_gmt');

    return array(
        'slugs' => $slugs,
        'titles' => $titles,
        'contents' => $contents,
        'dates' => $dates,
    );
}

// Register REST route for getting posts for the RSS feed.
add_action('rest_api_init', function () {
    register_rest_route(
        'f97/v1',
        '/rss',
        array(
            'methods' => 'GET',
            'callback' => 'get_posts_for_rss_feed',
        )
    );
});
