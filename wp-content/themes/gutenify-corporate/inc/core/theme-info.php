<?php
/**
 * Add theme page
 */
function gutenify_corporate_menu() {
	add_theme_page( esc_html__( 'Gutenify Corporate', 'gutenify-corporate' ), esc_html__( 'Gutenify Theme', 'gutenify-corporate' ), 'edit_theme_options', 'gutenify-corporate-info', 'gutenify_corporate_theme_page_display' );
}
add_action( 'admin_menu', 'gutenify_corporate_menu' );

/**
 * Display About page
 */
function gutenify_corporate_theme_page_display() {
	$theme = wp_get_theme();

	if ( is_child_theme() ) {
		$theme = wp_get_theme()->parent();
	}

	include_once 'templates/theme-info.php';
}

function gutenify_corporate_admin_plugin_notice() {
	include 'templates/admin-plugin-notice.php';
}
add_action( 'admin_notices', 'gutenify_corporate_admin_plugin_notice' );
