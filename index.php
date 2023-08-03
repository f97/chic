<?php
get_header(); // Include the header part of the theme.
?>

<main id="main" class="site-main" role="main">

    <?php
    while (have_posts()) : // Start the loop to display posts.
        the_post(); // Set up the post data.
    ?>
        <article id="post-<?php the_ID(); ?>" <?php post_class(); ?>>
            <header class="entry-header">
                <h1 class="entry-title"><?php the_title(); ?></h1>
            </header>
            <div class="entry-content">
                <?php the_content(); // Display the post content. ?>
            </div>
        </article>
    <?php endwhile; // End the loop. ?>

</main>

<?php
get_footer(); // Include the footer part of the theme.
