<?php
/**
 * The template for displaying Tag pages
 *
 * Used to display archive-type pages for posts in a tag.
 *
 * @link http://codex.wordpress.org/Template_Hierarchy
 *
 * @package WordPress
 * @subpackage Twenty_Fourteen
 * @since Twenty Fourteen 1.0
 */

get_header(); ?>

	<section id="primary" class="content-area">
		<div id="content" class="site-content" role="main">

			<?php if ( have_posts() ) : ?>

			<article class="entry-content">
			<?php query_posts("showposts=1&orderby=rand"); ?>
			<?php if ( have_posts() ) : while ( have_posts() ) : the_post(); ?>
				<?php the_excerpt(); ?>
			<?php endwhile; endif; ?>
			<?php wp_reset_query(); ?>
			</article>

			<?php
					// Start the Loop.
					while ( have_posts() ) : the_post();

						/*
						 * Include the post format-specific template for the content. If you want to
						 * use this in a child theme, then include a file called called content-___.php
						 * (where ___ is the post format) and that will be used instead.
						 */
						get_template_part( 'content', get_post_format() );

					endwhile;
					// Previous/next page navigation.
					twentyfourteen_paging_nav();

				else :
					// If no content, include the "No posts found" template.
					get_template_part( 'content', 'none' );

				endif;
			?>

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
			<article class="entry-content">
			<?php query_posts("showposts=2&orderby=rand"); ?>
			<?php if ( have_posts() ) : while ( have_posts() ) : the_post(); ?>
			
				<?php the_excerpt(); ?>
			
			<?php endwhile; endif; ?>
			<?php wp_reset_query(); ?>
			</article>

		</div><!-- #content -->
	</section><!-- #primary -->

<?php
get_sidebar( 'content' );
get_sidebar();
get_footer();
