<?php

/**
 * Get all posts' slugs for Next.js static generation
 */
function get_post_slugs() {
    $slugs = get_transient('post_slugs');

    if (!$slugs) {
        global $wpdb;

        // Query to fetch the list of post slugs
        $query = "SELECT post_name FROM {$wpdb->prefix}posts WHERE post_type = 'post' AND post_status = 'publish'";
        $results = $wpdb->get_results($query);

        $slugs = array();
        foreach ($results as $result) {
            // Collect all slugs in the array
            $slugs[] = $result->post_name;
        }

        // Cache the slugs for 1 hour
        set_transient('post_slugs', $slugs, HOUR_IN_SECONDS);
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
    'post',
    'post_img',
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
    'post',
    'post_categories',
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
    'post',
    'post_excerpt',
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
    $fineToolMetaKeys = array('itemName', 'itemDes', 'itemLinkName', 'itemLink', 'itemImgBorder');
    $fineToolMeta = array_intersect_key(get_post_meta($post['id']), array_flip($fineToolMetaKeys));
    if (!empty($fineToolMeta['itemName'])) {
        $post_meta['fineTool'] = array(
            'itemName'      => $fineToolMeta['itemName'][0],
            'itemDes'       => $fineToolMeta['itemDes'][0],
            'itemLinkName'  => $fineToolMeta['itemLinkName'][0],
            'itemLink'      => $fineToolMeta['itemLink'][0],
            'itemImgBorder' => $fineToolMeta['itemImgBorder'][0],
        );
    }

    // Additional image link meta (if available).
    $post_meta['linkImg'] = get_post_meta($post['id'], 'linkImg', true);

    // Mark count meta.
    $post_meta['mark_count'] = (int) get_post_meta($post['id'], 'mark_count', true);

    // Podcast meta (if available).
    $podcastMetaKeys = array(
        'podcast_name_chinese',
        'podcast_name_english',
        'podcast_episode',
        'podcast_audio_url',
        'podcast_episode_url',
        'podcast_duration',
        'podcast_file_size',
    );
    $podcastMeta = array_intersect_key(get_post_meta($post['id']), array_flip($podcastMetaKeys));
    if (!empty($podcastMeta['podcast_name_chinese'])) {
        $post_meta['podcast'] = array(
            'chineseName' => $podcastMeta['podcast_name_chinese'][0],
            'englishName' => $podcastMeta['podcast_name_english'][0],
            'episode'     => $podcastMeta['podcast_episode'][0],
            'audioUrl'    => $podcastMeta['podcast_audio_url'][0],
            'episodeUrl'  => $podcastMeta['podcast_episode_url'][0],
            'duration'    => $podcastMeta['podcast_duration'][0],
            'fileSize'    => $podcastMeta['podcast_file_size'][0],
        );
    }

    return $post_meta;
}

// Register REST field for post meta on 'post' and 'page' post types.
function register_post_meta_rest_field() {
    $post_types = array('post', 'page');
    foreach ($post_types as $post_type) {
        register_rest_field(
            $post_type,
            'post_metas',
            array(
                'get_callback'    => 'get_post_meta_for_api', // Callback function to get the field value.
                'update_callback' => null,                    // No update callback as the field is read-only.
                'schema'          => null,                    // No schema for the field.
            )
        );
    }
}
add_action('rest_api_init', 'register_post_meta_rest_field');

/**
 * Handle post visit count.
 *
 * @param WP_REST_Request $params The REST request parameters.
 * @return array|WP_Error An array with updated status and visit count or WP_Error if there's an error.
 */
function handle_visit($params) {
    $id = $params['id'];
    if (!empty($id)) {
        $visitCountBefore = (int) get_post_meta($id, 'post_views_count', true);

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
    if (!empty($id)) {
        $markCountBefore = (int) get_post_meta($id, 'mark_count', true);

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
    $posts = get_transient('posts_for_rss_feed');

    if (!$posts) {
        $args = array(
            'posts_per_page' => -1,
            'category__not_in' => array(5, 2, 74, 120, 58),
        );

        $posts = get_posts($args);
        $slugs = wp_list_pluck($posts, 'post_name');
        $titles = wp_list_pluck($posts, 'post_title');
        $contents = wp_list_pluck($posts, 'post_content_filtered');
        $dates = wp_list_pluck($posts, 'post_date_gmt');

        $posts_data = array(
            'slugs' => $slugs,
            'titles' => $titles,
            'contents' => $contents,
            'dates' => $dates,
        );

        // Cache the posts data for 1 hour
        set_transient('posts_for_rss_feed', $posts_data, HOUR_IN_SECONDS);
    }

    return $posts_data;
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