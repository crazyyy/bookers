<article class="entry-content">
	<?php query_posts("showposts=1&orderby=rand"); ?>
	<?php if ( have_posts() ) : while ( have_posts() ) : the_post(); ?>
		<?php the_excerpt(); ?>
	<?php endwhile; endif; ?>
	<?php wp_reset_query(); ?>
</article>