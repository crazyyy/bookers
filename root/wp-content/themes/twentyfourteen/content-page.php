<?php
/**
 * The template used for displaying page content
 *
 * @package WordPress
 * @subpackage Twenty_Fourteen
 * @since Twenty Fourteen 1.0
 */
?>

<article id="post-<?php the_ID(); ?>" <?php post_class(); ?>>
	<?php
		// Page thumbnail and title.
		twentyfourteen_post_thumbnail();
		the_title( '<header class="entry-header"><h1 class="entry-title">', '</h1></header><!-- .entry-header -->' );
	?>

	<div class="entry-content">
	<?php if ( is_single() || is_page() )  : ?>
		<ins class="adsbygoogle adaptive"
			style="display: block"
			data-ad-client="ca-pub-7907557357919250"
			data-ad-slot="9455730313">
		</ins>
	<?php endif; ?>
		<?php
			the_content();
			wp_link_pages( array(
				'before'      => '<div class="page-links"><span class="page-links-title">' . __( 'Pages:', 'twentyfourteen' ) . '</span>',
				'after'       => '</div>',
				'link_before' => '<span>',
				'link_after'  => '</span>',
			) );

		?>
		<?php if ( is_single() || is_page() )  : ?>
		<?php query_posts("showposts=5&orderby=rand"); ?>
		<table class="random-also">
			<tr>
			<?php if ( have_posts() ) : while ( have_posts() ) : the_post(); ?>
				<td><a title="<?php the_title(); ?>" href="<?php the_permalink(); ?>">
					<img src="<?php echo catchFirstImage(); ?>" title="<?php the_title(); ?>" alt="<?php the_title(); ?>" class="attachment-twentyfourteen-full-width wp-post-image" />
					<h6><?php the_title(); ?></h6></a>
				</td>
			<?php endwhile; endif; ?>
			</tr>
		</table>
		<?php wp_reset_query(); ?>
		<?php endif; ?>
	</div><!-- .entry-content -->
</article><!-- #post-## -->
