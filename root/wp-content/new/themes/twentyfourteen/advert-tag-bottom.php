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

<?php query_posts("showposts=2&orderby=rand"); ?>
<article class="entry-content">
<?php if ( have_posts() ) : while ( have_posts() ) : the_post(); ?>
	<?php the_excerpt(); ?>
<?php endwhile; endif; ?>
</article>
<?php wp_reset_query(); ?>