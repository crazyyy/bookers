
<?php require_once('admin.author.class.php'); ?>


<form method="post" action="<?php echo admin_url('admin-post.php?action=abovethefold_extract'); ?>" class="clearfix">
	<?php wp_nonce_field('abovethefold'); ?>
	<div class="wrap abovethefold-wrapper">
		<div id="poststuff">
			<div id="post-body" class="metabox-holder">
				<div id="post-body-content">
					<div class="postbox">
						<h3 class="hndle">
							<span><?php _e( 'Online Critical Path CSS generator', 'abovethefold' ); ?></span>
						</h3>
						<div class="inside">

							<p>The <a href="https://jonassebastianohlsson.com/" target="_blank">author of Penthouse.js</a> has made a free online generator available. It has no configuration options but it results in good quality Critical Path CSS code. <a href="https://www.google.nl/search?q=online%20critical%20path%20css%20generator" target="_blank">Check Google</a> for the availability of other online tools.<p>

							<p>If you would like more configuration options you can look into Grunt.js en Gulp.js based solutions. <a href="https://www.google.com/search?q=grunt%20critical%20path%20css" target="_blank">Check Google</a> for info.</p>

							<strong>Instructions</strong>
							<ol>
								<li><a href="./admin.php?page=abovethefold&tab=extract">Extract the full-CSS</a> for the pages you want to create critical-path CSS for</li>
								<li>Open <a href="http://jonassebastianohlsson.com/criticalpathcssgenerator/" target="_blank">http://jonassebastianohlsson.com/criticalpathcssgenerator/</a> and enter the full CSS into the <em>Full-CSS</em> field.</li>
								<li>Click on the button <em>Generate Critical Path CSS</em></li>
							</ol>

						</div>
					</div>

	<!-- End of #post_form -->

				</div>
			</div> <!-- End of #post-body -->
		</div> <!-- End of #poststuff -->
	</div> <!-- End of .wrap .nginx-wrapper -->
</form>
