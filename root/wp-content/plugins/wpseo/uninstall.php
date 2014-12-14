<?php
if (defined('WP_UNINSTALL_PLUGIN')) {
 	/* Cronjob löschen */
	if (wp_next_scheduled('wpseo_daily_cronjob')) {
		wp_clear_scheduled_hook('wpseo_daily_cronjob');
	}
 	
 	/* Optionen löschen */
	delete_option('wpseo_options');
}
?>