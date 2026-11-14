<?php
/**
 * Main template for the Ninja Theme.
 *
 * @package NinjaTheme
 */

if (!defined('ABSPATH')) {
    exit; // Not loaded by WordPress.
}
?>
<!doctype html>
<html <?php language_attributes(); ?>>
<head>
    <meta charset="<?php bloginfo('charset'); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <?php wp_head(); ?>
</head>
<body <?php body_class(); ?>>
<header class="site-header">
    <a href="<?php echo esc_url(home_url('/')); ?>"><?php bloginfo('name'); ?></a>
</header>

<main class="site-content">
    <?php if (have_posts()) : ?>
        <?php while (have_posts()) : the_post(); ?>
            <article <?php post_class(); ?>>
                <h1><?php the_title(); ?></h1>
                <?php the_content(); ?>
            </article>
        <?php endwhile; ?>
    <?php else : ?>
        <p>No content found.</p>
    <?php endif; ?>
</main>

<footer class="site-footer">
    &copy; 2026 Ninja Site, built with NinjaBots
</footer>
<?php wp_footer(); ?>
</body>
</html>
