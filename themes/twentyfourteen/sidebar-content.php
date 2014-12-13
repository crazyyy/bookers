<?php
/**
 * The Content Sidebar
 *
 * @package WordPress
 * @subpackage Twenty_Fourteen
 * @since Twenty Fourteen 1.0
 */

if ( ! is_active_sidebar( 'sidebar-2' ) ) {
	return;
}
?>
<div id="content-sidebar" class="content-sidebar widget-area" role="complementary">
	<div class="adaptive3-container">
		<ins class="adsbygoogle adaptive3"
	    	style="display:block"
	     	data-ad-client="ca-pub-7907557357919250"
	     	data-ad-slot="1792862717"
	     	data-ad-format="auto"></ins>
	</div>
	<!-- /.adaptive3-container -->
	<?php dynamic_sidebar( 'sidebar-2' ); ?>
</div><!-- #content-sidebar -->
