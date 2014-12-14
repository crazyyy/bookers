<?php
/**
 * The Footer Sidebar
 *
 * @package WordPress
 * @subpackage Twenty_Fourteen
 * @since Twenty Fourteen 1.0
 */

if ( ! is_active_sidebar( 'sidebar-3' ) ) {
	return;
}
?>

<div id="supplementary">
	<div id="footer-sidebar" class="footer-sidebar widget-area" role="complementary">

	<aside class="widget widget_text masonry-brick">
		<div id="vk_groups"></div>
	</aside>
	<?php dynamic_sidebar( 'sidebar-3' ); ?>

	<?php query_posts("showposts=2&orderby=rand"); ?>
	<?php if ( have_posts() ) : while ( have_posts() ) : the_post(); ?>
	<aside class="widget widget_text masonry-brick">
		<div class="textwidget"><?php the_excerpt(); ?></div>
	</aside>
	<?php endwhile; endif; ?>
	<?php wp_reset_query(); ?>

	</div><!-- #footer-sidebar -->
</div><!-- #supplementary -->