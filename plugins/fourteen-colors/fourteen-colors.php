<?php
/**
 * Plugin Name: Fourteen Colors
 * Plugin URI: http://celloexpressions.com/plugins/fourteen-colors
 * Description: Customize the colors of the Twenty Fourteen Theme, directly within the Customizer.
 * Version: 1.2
 * Author: Nick Halsey
 * Author URI: http://celloexpressions.com/
 * Tags: Twenty Fourteen, Colors, Customizer, Custom Colors, Theme Colors
 * Text Domain: fourteen-colors
 * License: GPL

=====================================================================================
Copyright (C) 2014 Nick Halsey

This program is free software; you can redistribute it and/or
modify it under the terms of the GNU General Public License
as published by the Free Software Foundation; either version 2
of the License, or (at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with WordPress; if not, write to the Free Software
Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
=====================================================================================
*/

// Only run if theme or parent theme is Twenty Fourteen.
if ( strtolower( get_template() ) != 'twentyfourteen' ) {
	return;
}

/**
 * Loads the plugin textdomain for translations.
 *
 * @since Fourteen Colors 0.6
 *
 * @return void
 */
function fourteen_colors_load_textdomain() {
	// This will load the WordPress-downloaded language pack if it exists, as languages are not bundled with the plugin.
	load_plugin_textdomain( 'fourteen-colors' );
}
add_action( 'plugins_loaded', 'fourteen_colors_load_textdomain' );

/**
 * Checks the plugin version and updates the cached CSS if necessary.
 *
 * Only runs on admin_init because these CSS updates tend to be less
 * important than running the version check on every front-end pageload.
 *
 * @since Fourteen Colors 1.0.1
 *
 * @return void
 */
function fourteen_colors_admin_init() {
	$fourteen_colors_version = '1.2';
	$db_version = get_option( 'fourteen_colors_version', false );

	if ( false === $db_version || $db_version != $fourteen_colors_version ) {
		// Build/re-build the Fourteen Colors CSS output.
		fourteen_colors_rebuild_color_patterns();

		// Update DB version.
		update_option( 'fourteen_colors_version', $fourteen_colors_version );
	}
}
add_action( 'admin_init', 'fourteen_colors_admin_init' );

/**
 * Add and modify the customizer settings and controls.
 *
 * @since Fourteen Colors 0.1
 *
 * @return void
 */
function fourteen_colors_customize_register( $wp_customize ) {
	// Add the custom accent color setting and control.
	$wp_customize->add_setting( 'accent_color', array(
		'default'           => '#24890d',
		'sanitize_callback' => 'sanitize_hex_color',
	) );

	$wp_customize->add_control( new WP_Customize_Color_Control( $wp_customize, 'accent_color', array(
		'label'       => __( 'Accent Color', 'fourteen-colors' ),
		'description' => __( 'Includes links, text selection, the header search bar, and more; use vibrant colors for best results.', 'fourteen-colors' ),
		'section'     => 'colors',
		'priority'    => '1', // Need to push above Site Title & Background colors because running after theme's built-in options.
	) ) );

	// Add the custom contrast color setting and control.
	$wp_customize->add_setting( 'contrast_color', array(
		'default'           => '#000000',
		'sanitize_callback' => 'sanitize_hex_color',
	) );

	$wp_customize->add_control( new WP_Customize_Color_Control( $wp_customize, 'contrast_color', array(
		'label'       => __( 'Contrast Color', 'fourteen-colors' ),
		'description' => __( 'Header, sidebar, footer, and other details; use muted or grayscale colors for best results.', 'fouteen-colors' ),
		'section'     => 'colors',
		'priority'    => '2', // Need to push above Site Title & Background colors because running after theme's built-in options.
	) ) );

	// Remove the Site Title Color control; it is confusing because the color is automatically adjusted based on the contrast color.
	// A custom-set color will still apply and it can still be changed from the Appearance -> Header page.
	$wp_customize->remove_control( 'header_textcolor' );

	add_filter( 'theme_mod_fourteen_colors_css', 'fourteen_colors_generate_css' );
}
add_action( 'customize_register', 'fourteen_colors_customize_register', 11 ); // Needs to run after theme's customize_register so that the colors section's description can be modified.

// Contains functions that generate CSS for all color modification patterns.
require( 'color-patterns.php' );

/**
 * Returns the CSS output of Fourteen Colors.
 *
 * @since Fourteen Colors 0.5
 *
 * @return string
 */
function fourteen_colors_generate_css() {
	return fourteen_colors_contrast_css() . fourteen_colors_accent_css() . fourteen_colors_general_css();
}

/**
 * Caches the CSS output of Fourteen Colors.
 *
 * @since Fourteen Colors 0.5
 *
 * @return void
 */
function fourteen_colors_rebuild_color_patterns() {
	set_theme_mod( 'fourteen_colors_css', fourteen_colors_generate_css() );
}
// Allow Fourteen Colors to run on child themes by not hardcoding "twentyfourteen".
$fourteen_colors_theme = get_stylesheet();
add_action( "update_option_theme_mods_$fourteen_colors_theme", 'fourteen_colors_rebuild_color_patterns' );

/**
 * Output all dynamic custom-color CSS.
 *
 * @since Fourteen Colors 0.5
 *
 * @return void
 */
function fourteen_colors_print_output() {
	echo '<style id="fourteen-colors" type="text/css">' . get_theme_mod( 'fourteen_colors_css', '/* Fourteen Colors is not yet configured. */' ) . '</style>';
}
add_action( 'wp_head', 'fourteen_colors_print_output' );