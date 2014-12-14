<?php if ( is_single() || is_page() )  : ?>
	<ins class="adsbygoogle adaptive"
		style="display: block"
		data-ad-client="ca-pub-7907557357919250"
		data-ad-slot="9455730313">
	</ins>
<?php endif; ?>
<!-- noindex -->
<table class="download-lin">
	<tr>
		<td class="cel-1 dl-but"><a href="http://adf.ly/ZTBvT" rel="nofollow" target="_blank">Сервер "Минск"</a></td>
		<td class="cel-2 dl-but"><a href="http://adf.ly/ZTBoL" rel="nofollow" target="_blank">Сервер "Киев"</a></td>
		<td class="cel-3 dl-but"><a href="http://adf.ly/ZTC5K" rel="nofollow" target="_blank">Сервер "Москва"</a></td>
	</tr>
</table>
<!-- /noindex -->
<!-- /.download-lin -->
<!-- show random post -->
<!-- query_posts('category_name=Category Name'); -->
<!-- query_posts("showposts=1&cat=1217&orderby=rand"); -->
<?php query_posts("showposts=1&orderby=rand"); ?>
<?php if ( have_posts() ) : while ( have_posts() ) : the_post(); ?>
	<div class="entry-content">
		<?php the_excerpt(); ?>
	</div>
<?php endwhile; endif; ?>
<?php wp_reset_query(); ?>