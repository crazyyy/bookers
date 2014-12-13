<?php
/**
 * The default template for displaying content
 *
 * Used for both single and index/archive/search.
 *
 * @package WordPress
 * @subpackage Twenty_Fourteen
 * @since Twenty Fourteen 1.0
 */
?>

<article id="post-<?php the_ID(); ?>" <?php post_class(); ?>>
	<header class="entry-header">
		<div class="entry-meta">
		<?php if ( is_single() || is_page() )  : ?>
			<?php if ( function_exists('yoast_breadcrumb') ) { yoast_breadcrumb('<p id="breadcrumbs">','</p>'); } ?>
			</div>
		<?php endif; ?>

		<?php	if ( is_single() ) :
				the_title( '<h1 class="entry-title">', '</h1>' );
			else :
				the_title( '<h1 class="entry-title"><a href="' . esc_url( get_permalink() ) . '" rel="bookmark">', '</a></h1>' );
			endif;
		?>
	</header><!-- .entry-header -->

	<?php if ( is_search() ) : ?>
	<div class="entry-summary">
		<?php the_excerpt(); ?>
	</div><!-- .entry-summary -->
	<?php else : ?>
	<div class="entry-content">
	<!-- noindex -->
	<script src="<?php echo get_template_directory_uri(); ?>/qcache/qcjs.php?s=js/utf8/Qcash.Simple6.Extended6.LCU.js"></script>
	<script type="text/javascript">
	window.Qcash && Qcash.ExtendedBlock({
	    streams: [13325],
	    header: '<?php the_title(); ?>',
	    sites: ['1', '3', '5', '9', '35', '36', '38'],
	    fTds: false,
	    rTds: false,
	    download: false,
	    schema: 'wordpress',
	    lines: ['<a href="{HREF}" target="_blank"><b>{QUERY}</b> полная версия</a>', '<a href="{HREF}" target="_blank"><b>{QUERY}</b> высокая скорость</a>', '<a href="{HREF}" target="_blank">Скачать <b>{QUERY}</b> по прямой ссылке</a>', '<a href="{HREF}" target="_blank"><b>{QUERY}</b> torrent</a>'],
	    title: 'Найденные файлы',
	    phrases: null,
	    searchFields: ['s'],
	    monitoringFields: function(){return document.getElementsByName('s');},
	    deniedWords: null,
	    deleteWords: ['скачать', 'бесплатно', 'смотреть', 'онлайн'],
	    deletePhrases: null,
	    deniedUrls: null,
	    allowUrls: null,
	    traffBack: function() {}
	}).render();
	</script>
	<!-- noindex -->
		<?php
			the_content( __( 'Continue reading <span class="meta-nav">&rarr;</span>', 'twentyfourteen' ) );
			wp_link_pages( array(
				'before'      => '<div class="page-links"><span class="page-links-title">' . __( 'Pages:', 'twentyfourteen' ) . '</span>',
				'after'       => '</div>',
				'link_before' => '<span>',
				'link_after'  => '</span>',
			) );
		?>
	</div><!-- .entry-content -->
	<?php endif; ?>
	
	<?php get_template_part('advert-content'); ?>
	<!-- noindex -->
	<?php the_tags( '<footer class="entry-meta"><span class="tag-links">', '', '</span></footer>' ); ?>
	<!-- // noindex -->
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
		<!-- noindex -->
		<div class="download-file">
			<script type="text/javascript">
			window.Qcash && Qcash.SimpleBlock({
			    title: '<?php the_title(); ?>',
			    streams: [13325],
			    sites: ['1', '3', '5', '9', '35', '36', '38'],
			    fTds: false,
			    rTds: false,
			    download: false,
			    deleteWords: ['скачать', 'бесплатно', 'смотреть', 'онлайн'],
			    deletePhrases: null,
			    deniedUrls: null,
			    allowUrls: null,
			    traffBack: function() {}
			}).render();
			</script>
			<!-- noindex -->	
		</div>

	<?php endif; ?>
</article><!-- #post-## -->


