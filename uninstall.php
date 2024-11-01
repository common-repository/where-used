<?php

namespace WhereUsed;

use WhereUsed\HelpersLibrary\REQUEST;

if ( defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	// Uninstall request initiated

	// Create nonce
	$nonce = wp_create_nonce( 'uninstall-plugin-where-used-' . get_current_user_id() );

	$plugin_path = dirname( __DIR__ ) . '/' . WP_UNINSTALL_PLUGIN;

	// URL to retrieve (this file as URL)
	$url = plugin_dir_url( $plugin_path ) . 'uninstall.php';

	$args = [
		'body' => [ 'nonce' => $nonce ],
		'cookies' => $_COOKIE,
		'sslverify' => apply_filters( 'https_local_ssl_verify', false ),
	];

	// Remotely load this same file directly and pass nonce to it
	wp_remote_get( $url, $args );

} else {
	// Accessed directly

	// Set flag to prevent loading Helpers Library initially
	define( 'WP_UNINSTALL_PLUGIN', 'where-used.php' );

	// Load WordPress
	require( dirname( __DIR__, 3 ) . '/wp-load.php' );

	// Load plugin
	require_once( __DIR__ . '/where-used.php' );

	if ( check_compatibility() ) {

		require_once( __DIR__ . '/inc/Plugin.php' );

		// Load classes
		Plugin::load_only();

		// Check nonce
		if ( ! wp_verify_nonce( REQUEST::text_field( 'nonce' ), 'uninstall-plugin-where-used-' . get_current_user_id() ) ) {
			die( 'invalid session. Install aborted.' );
		}

		$sites = Get::sites();

		if ( ! empty( $sites ) ) {
			// Creates a table for each site
			foreach ( $sites as $site ) {

				if ( is_multisite() ) {
					switch_to_blog( $site->blog_id );
				}

				// Remove Tables
				Run::drop_tables( Get::tables() );

				// Remove Settings
				Run::delete_options();

				// Remove Crons For This Plugin
				Run::remove_crons();

				// Remove all status code cache
				Run::clear_status_cache();

				if ( is_multisite() ) {
					restore_current_blog();
				}
			}
		}

	} else {
		error_log( 'WhereUsed - Not compatible: Could not load Helpers Library. Uninstall script not ran.' );
	}

}