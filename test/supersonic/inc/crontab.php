<?php

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) exit;


function wpss_clear_f( $max_count = 99 ) {
	global $wpdb;
	$count_row = 0;
	$settings = get_option( "wpss_settings" );
	$cf = new cloudflare_api( $settings['cloudflare_login'], $settings['cloudflare_api_key'] );
	$sql = 'select url from ' . $wpdb->prefix . 'wpss_clear order by priority';
	$rows = $wpdb->get_results( $sql );
	foreach ( $rows as $row ) {
		if ( $count_row > $max_count ) {
			$schedule = wp_next_scheduled( 'wpss_clear' );
			if ( $schedule > time()+65 ) {
				wp_schedule_single_event( time()+60, 'wpss_clear' );
			}
			return;
		}
		$count_row++;
		$url = trim( $row->url );
		if ( strpos( $url, '/' ) === 0 ) {
			$url = site_url() . $url;
		}
		if (trim( $url ) == '') {
			$url = trailingslashit( site_url() );
		}
		$ret = $cf->zone_file_purge( $settings['cloudflare_domain'], $url );
		if ( $ret->result != 'success' ) {
			$schedule = wp_next_scheduled( 'wpss_clear' );
			if ( $schedule > time()+65 ) {
				wp_schedule_single_event( time()+65, 'wpss_clear' );
			}
			wpss_log( 20, 'Purge failed: ' . $ret->msg . ' for &quot;' . $url . '&quot;' );
			if ( strpos( $ret->msg, 'Invalid url' ) === false ) {
				return;
			}
		}
		else {
			$wpdb->delete( $wpdb->prefix . 'wpss_clear', array( 'url' => $row->url ) );
		}
	}
	if ( ! wp_next_scheduled( 'wpss_clear' ) ) {
		wp_schedule_event( time()+65, 'hourly', 'wpss_clear' );
	}
	if (!wp_next_scheduled('wpss_log_clear')) {
		wp_schedule_event( time(), 'hourly', 'wpss_log_clear' );
	}
}
add_action( 'wpss_clear', 'wpss_clear_f' );

