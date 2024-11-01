<?php

namespace WhereUsed\HelpersLibrary;

// Prevent Direct Access
( defined( 'ABSPATH' ) ) || die;

trait Constants {

	/**
	 * Builds the constant from given constant suffix and returns the value of it
	 *
	 * @package WhereUsed\HelpersLibrary
	 * @since   1.1.0
	 *
	 * @param string $constant_suffix
	 *
	 * @return mixed
	 * @throws \ReflectionException
	 */
	public static function get_constant_value( string $constant_suffix ) {

		// $constant_suffix = SLUG
		// return value of PLUGIN_SLUG
		return constant( static::get_constant_name( $constant_suffix ) );

	}

	public static function get_constant_name( string $constant_suffix ): string {

		// static::get_constant_prefix() = PLUGIN_
		// $constant_suffix = SLUG
		// return string of PLUGIN_SLUG
		return static::get_constant_prefix() . $constant_suffix;

	}

	/**
	 * Gets the plugin's constant prefix based on the namespace
	 *
	 * @package WhereUsed\HelpersLibrary
	 * @since   1.1.0
	 *
	 * @return string
	 * @throws \ReflectionException
	 */
	public static function get_constant_prefix(): string {

		// Grabs the class that was originally called
		// Set the constant prefix: PLUGIN_ from the namespace
		return strtoupper( strstr( get_called_class(), "\\", true ) . '_' );

	}

	/**
	 * @param string $class_name
	 *
	 * @return string
	 **/
	public static function get_class( string $class_name ): string {

		return static::get_constant_value( 'NAMESPACE' ) . '\\' . $class_name;

	}

	/**
	 * Defines all the constants for this plugin
	 *
	 * @package WhereUsed\HelpersLibrary
	 * @since   1.1.0
	 *
	 * @param string $namespace
	 * @param string $plugin_slug
	 * @param string $plugin_dir
	 *
	 * @return void
	 */
	final public static function setup_constants( string $namespace, string $plugin_slug, string $plugin_dir ): void {

		/**
		 * NOTICE: It is assumed that the following constant variables are already defined in the main plugin file
		 *
		 * $prefix . _SLUG - example-plugin
		 * $prefix . _NAME - Example Plugin
		 * $prefix . _VERSION - 1.0.0
		 * $prefix . _MIN_PHP - 5.6.0
		 * $prefix . _MIN_WP - 5.0.0
		 */
		$prefix = str_replace( '-', '', strtoupper( $plugin_slug ) );

		if ( ! defined( $prefix . '_PREFIX' ) ) {
			// Setup Constants
			define( $prefix . '_PREFIX', $prefix );
			define( $prefix . '_NAMESPACE', $namespace );

			$global = strtolower( $namespace );
			define( $prefix . '_GLOBAL', $global );

			$option = str_replace( '-', '_', $plugin_slug );
			define( $prefix . '_OPTION', $option );
			define( $prefix . '_NETWORK_OPTION', $option . '_network' );
			define( $prefix . '_SCAN_OPTION', $option . '_scan' );
			define( $prefix . '_NOTIFICATIONS_OPTION', $option . '_notifications' );
			define( $prefix . '_HOOK_PREFIX', $global . '_' );
			define( $prefix . '_DIR', $plugin_dir );

			$file = $plugin_dir . '/' . $plugin_slug . '.php';
			define( $prefix . '_FILE', $file );
			define( $prefix . '_PLUGIN', $plugin_slug . '/' . $plugin_slug . '.php' );
			define( $prefix . '_ASSETS_DIR', $plugin_dir . '/assets' );

			$inc_dir = $plugin_dir . '/inc';
			define( $prefix . '_INC_DIR', $inc_dir );
			define( $prefix . '_AJAX_DIR', $inc_dir . '/ajax' );
			define( $prefix . '_TABLES_DIR', $inc_dir . '/tables' );
			define( $prefix . '_LIBRARY_DIR', $plugin_dir . '/library' );
			define( $prefix . '_TEMPLATES_DIR', $plugin_dir . '/templates' );
			define( $prefix . '_QUEUE_DIR', $plugin_dir . '/queue' );
			define( $prefix . '_LOGS_DIR', $plugin_dir . '/logs' );
			define( $prefix . '_LANGUAGES_DIR', $plugin_dir . '/languages' );

			$url = plugin_dir_url( $file );
			define( $prefix . '_URL', $url );
			define( $prefix . '_ASSETS_URL', $url . 'assets/' );
			define( $prefix . '_LIBRARY_JS_URL', $url . 'library/js/' );

			// Set current site
			$current_site_id = ( is_multisite() ) ? get_current_blog_id() : 1;
			define( $prefix . '_CURRENT_SITE_ID', $current_site_id );

			// Admin URLs
			$admin_uri = 'tools.php?page=' . $plugin_slug;
			define( $prefix . '_ADMIN_URI', $admin_uri );
			define( $prefix . '_ADMIN_URL', admin_url( $admin_uri ) );

			// Setup the current admin page
			$admin_uri_current = $admin_uri . REQUEST::key( 'tab', '', '', '&tab=' );
			define( $prefix . '_ADMIN_URI_CURRENT', $admin_uri_current );
			define( $prefix . '_ADMIN_URL_CURRENT', admin_url( $admin_uri_current ) );

			// Settings URLs
			$settings_uri = $admin_uri . '&tab=settings';
			define( $prefix . '_SETTINGS_URI', $settings_uri );
			define( $prefix . '_SETTINGS_URL', admin_url( $settings_uri ) );

			$settings_network_uri = 'network/settings.php?page=' . $plugin_slug;
			define( $prefix . '_SETTINGS_NETWORK_URI', $settings_network_uri );
			define( $prefix . '_SETTINGS_NETWORK_URL', admin_url( $settings_network_uri ) );
		}

	}

}