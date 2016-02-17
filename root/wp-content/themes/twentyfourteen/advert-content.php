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
		<td class="cel-1 dl-but"><a href="http://adf.ly/a26F0" rel="nofollow" target="_blank">Сервер "Минск"</a></td>
		<td class="cel-2 dl-but"><a href="http://adf.ly/a26F5" rel="nofollow" target="_blank">Сервер "Киев"</a></td>
		<td class="cel-3 dl-but"><a href="http://adf.ly/a26FA" rel="nofollow" target="_blank">Сервер "Москва"</a></td>
	</tr>
</table>
<table class="download-lin">
	<tr>
		<td class="cel-1 dl-but"><a href="http://adf.ly/1IFInu" rel="nofollow" target="_blank">Сервер "Минск"</a></td>
		<td class="cel-2 dl-but"><a href="http://adf.ly/1IFInx" rel="nofollow" target="_blank">Сервер "Киев"</a></td>
		<td class="cel-3 dl-but"><a href="http://adf.ly/1IFIo2" rel="nofollow" target="_blank">Сервер "Москва"</a></td>
	</tr>
</table>
<table class="download-lin">
	<tr>
		<td class="cel-1 dl-but"><a href="http://adf.ly/1IFInv" rel="nofollow" target="_blank">Сервер "Минск"</a></td>
		<td class="cel-2 dl-but"><a href="http://adf.ly/1IFInz" rel="nofollow" target="_blank">Сервер "Киев"</a></td>
		<td class="cel-3 dl-but"><a href="http://adf.ly/1IFIo3" rel="nofollow" target="_blank">Сервер "Москва"</a></td>
	</tr>
</table>
<table class="download-lin">
	<tr>
		<td class="cel-1 dl-but"><a href="http://adf.ly/1IFInw" rel="nofollow" target="_blank">Сервер "Минск"</a></td>
		<td class="cel-2 dl-but"><a href="http://adf.ly/1IFIo1" rel="nofollow" target="_blank">Сервер "Киев"</a></td>
		<td class="cel-3 dl-but"><a href="http://adf.ly/1IFIo4" rel="nofollow" target="_blank">Сервер "Москва"</a></td>
	</tr>
</table>
<table class="download-lin">
	<tr>
		<td class="cel-1 dl-but"><a href="http://adf.ly/a26F3" rel="nofollow" target="_blank">Сервер "Минск"</a></td>
		<td class="cel-2 dl-but"><a href="http://adf.ly/a26F8" rel="nofollow" target="_blank">Сервер "Киев"</a></td>
		<td class="cel-3 dl-but"><a href="http://adf.ly/a26FD" rel="nofollow" target="_blank">Сервер "Москва"</a></td>
	</tr>
</table>
<script>
	window.onload=function() {
	  var E = document.getElementsByClassName("download-lin");
	  var m = E.length;
	  var n = parseInt(Math.random()*m);
	  for (var i=m-1;i>=0;i--) {
		  var e = E[i];
		  e.style.display='none';
	  }
	  E[n].style.display='';
	}
</script>



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