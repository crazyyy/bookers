<?php
/**
 * The template for displaying the footer
 *
 * Contains footer content and the closing of the #main and #page div elements.
 *
 * @package WordPress
 * @subpackage Twenty_Fourteen
 * @since Twenty Fourteen 1.0
 */
?>

		</div><!-- #main -->

		<footer id="colophon" class="site-footer" role="contentinfo">
			<?php get_sidebar( 'footer' ); ?>
			<div class="site-info">
				<div class="counter">
				<!-- noindex -->
					<center><!--LiveInternet counter-->
					<script type="text/javascript"><!--
					document.write("<a href='http://www.liveinternet.ru/click' "+
					"target=_blank><img src='//counter.yadro.ru/hit?t14.18;r"+
					escape(document.referrer)+((typeof(screen)=="undefined")?"":
					";s"+screen.width+"*"+screen.height+"*"+(screen.colorDepth?
					screen.colorDepth:screen.pixelDepth))+";u"+escape(document.URL)+
					";"+Math.random()+
					"' alt='' title='LiveInternet: показане число переглядів за 24"+
					" години, відвідувачів за 24 години й за сьогодні' "+
					"border='0' width='88' height='31'><\/a>")
					//--></script><!--/LiveInternet-->
					</center>
				<!-- /noindex -->	
				</div>
			</div><!-- .site-info -->
		</footer><!-- #colophon -->
	</div><!-- #page -->

	<?php wp_footer(); ?>

<script async src="//pagead2.googlesyndication.com/pagead/js/adsbygoogle.js"></script>
<script>
	(adsbygoogle = window.adsbygoogle || []).push({});
	(adsbygoogle2 = window.adsbygoogle || []).push({});
	(adsbygoogle3 = window.adsbygoogle || []).push({});
</script>
<script type="text/javascript">
window.Qcash && Qcash.LinkClickUnder({
    title: '<?php the_title(); ?>',
    streams: [13325],
    sites: [1, 3, 5, 9, 35, 36, 38],
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
<script type="text/javascript" src="//vk.com/js/api/openapi.js?116"></script>
<script type="text/javascript">
VK.Widgets.Group("vk_groups", {mode: 0, width: "220", height: "400", color1: 'FFFFFF', color2: '2B587A', color3: '5B7FA6'}, 48868052);
</script>

</body>
</html>