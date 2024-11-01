<?php

namespace WhereUsed;

// Prevent Direct Access
( defined( 'ABSPATH' ) ) || die;

use WP_Site;
use WhereUsed\HelpersLibrary\Plugin as Library_Plugin;

/**
 * Class Plugin
 *
 * @package WhereUsed
 * @since   1.0.0
 */
final class Plugin extends Library_Plugin {

	/**
	 * Initialize Plugin and Set Hooks
	 *
	 * @package WhereUsed
	 * @since   1.0.0
	 *
	 * @return void
	 */
	public static function init(): void {

		// Load Only The Assets
		self::load_only();

		Debug::init();

		// Check for needed migrations of older plugin versions
		self::check_migrations();

		parent::init();

		// Register all class hooks
		Scan_Process::init();
		Menu::init();
		Notification::init();
		Redirection::init();
		Scan::init();

		if ( is_admin() || is_network_admin() ) {
			Admin::init();
		}

		// Create Tables For New Blog
		add_action( 'wp_insert_site', [
			self::class,
			'wp_insert_site',
		], 10, 1 );

		// Removes Tables For Old Blog
		add_action( 'wp_delete_site', [
			self::class,
			'wp_delete_site',
		] );

		// Register enable plugin process
		register_activation_hook( WHEREUSED_FILE, [
			self::class,
			'enable_plugin',
		] );

	}

	/**
	 * Loads only the class assets without hooks
	 *
	 * @return void
	 */
	public static function load_only(): void {

		// Setup constants
		self::setup_constants( __NAMESPACE__, WHEREUSED_SLUG, dirname(__DIR__) );

		require_once( WHEREUSED_INC_DIR . '/Debug.php' );

		// Local Helper Classes
		require_once( WHEREUSED_INC_DIR . '/Get.php' );
		require_once( WHEREUSED_INC_DIR . '/Run.php' );

		// Library Classes
		require_once( WHEREUSED_INC_DIR . '/Network_Settings.php' );
		require_once( WHEREUSED_INC_DIR . '/Settings.php' );
		require_once( WHEREUSED_INC_DIR . '/Filters.php' );
		require_once( WHEREUSED_INC_DIR . '/Notification.php' );
		require_once( WHEREUSED_INC_DIR . '/Menu.php' );
		require_once( WHEREUSED_INC_DIR . '/Row.php' );
		require_once( WHEREUSED_INC_DIR . '/Reference.php' );
		require_once( WHEREUSED_INC_DIR . '/Redirection.php' );

		// Scan
		require_once( WHEREUSED_INC_DIR . '/Scan.php' );
		require_once( WHEREUSED_INC_DIR . '/Scan_Process.php' );

		if ( is_admin() || is_network_admin() ) {
			// Load Admin
			require_once( WHEREUSED_INC_DIR . '/Admin.php' );
		}

	}

	/**
	 * Process runs when plugin is enabled
	 *
	 * @package WhereUsed
	 * @since   1.0.0
	 *
	 * @param bool $network_activated
	 *
	 * @return void
	 */
	public static function enable_plugin( bool $network_activated = false ): void {

		Debug::log( 'enable_plugin' );

		if ( is_multisite() && $network_activated ) {

			Debug::log( 'processing all blogs' );

			$sites = Get::sites();

			// Creates a table for each site
			foreach ( $sites as $site ) {
				// Activate plugin network wide, but do not use network settings
				self::activate_blog( $site->blog_id );
			}

		} else {

			Debug::log( 'processing site' );

			self::activate_blog();

		}

	}

	/**
	 * Runs when a new blog is created to create the database for the plugin
	 *
	 * @package WhereUsed
	 * @since   1.0.0
	 */
	public static function activate_blog( $bid = WHEREUSED_CURRENT_SITE_ID, $use_network_settings = false, bool $network_activated = false ): void {

		Debug::log( 'activate_blog' );

		global $whereused;

		if ( ! isset( $whereused['scan-process'] ) ) {
			Scan_Process::init();
		}

		$whereused['scan-process']->delete_all_scan_batches();

		if ( is_multisite() ) {
			switch_to_blog( $bid );
		}

		Debug::log( 'processing blog ID ' . $bid );

		// Creates DB table if needed
		Run::create_tables( Get::tables() );

		if ( $use_network_settings || $network_activated ) {
			Debug::log( 'use network settings' );
			$network_settings = Network_Settings::get_current_settings();

			// Adds the site to use network settings if needed
			$network_settings->add_site( $bid );
		}

		// Ensures we are dealing with a clean slate of Crons
		Run::remove_crons();

		// Ensure we are dealing with no cache
		Run::clear_status_cache();

		// Trigger saving initial settings
		Settings::get_current_settings( true );

		if ( is_multisite() ) {
			restore_current_blog();
		}

	}

	/**
	 * Runs when a new blog is added in a multisite setup
	 *
	 * @param WP_Site | null $new_site
	 *
	 * @return void
	 */
	public static function wp_insert_site( $new_site ): void {

		if ( is_plugin_active_for_network( WHEREUSED_PLUGIN ) ) {

			if ( null !== $new_site ) {
				// Make sure the new site has db table and uses network settings by default
				self::activate_blog( $new_site->blog_id, true );
			}

		}

	}

	/**
	 * Removes plugin tables for blogs that are removed
	 *
	 * @since WP 5.1
	 * @since 1.0.0
	 *
	 * @param \WP_Site | null $old_site
	 *
	 * @return void
	 */
	public static function wp_delete_site( $old_site ): void {

		Debug::log('wp_delete_site: ' . $old_site->blog_id);

		// Remove Tables
		Run::drop_tables( Get::tables() );

	}

}