<?php

namespace WhereUsed\HelpersLibrary;

// Prevent Direct Access
( defined( 'ABSPATH' ) ) || die;

/**
 * Class Plugin - Sets up the plugin
 *
 * @package WhereUsed\HelpersLibrary
 * @since   1.1.0
 */
abstract class Plugin {

	use Constants;

	/**
	 * Initialize Plugin and Set Hooks
	 *
	 * @package WhereUsed\HelpersLibrary
	 * @since   1.1.0
	 *
	 * @return void
	 */
	public static function init(): void {

		add_action( 'init', [
			static::class,
			'load_plugin_textdomain',
		] );

	}

	/**
	 * Checks to see if we need to run any version upgrade migration scripts
	 *
	 * @package WhereUsed\HelpersLibrary
	 * @since   1.1.0
	 *
	 * @return void
	 */
	protected static function check_migrations(): void {

		// Check for migrations needed

		$settings = static::get_class( 'Settings' )::get_current_settings( true );
		$db_version = $settings->get( 'db_version' );

		if ( static::get_constant_value( 'VERSION' ) != $db_version ) {

			// Detected that the current site needs migration ran
			require_once( static::get_constant_value( 'INC_DIR' ) . '/Migration.php' );

			static::get_class( 'Migration' )::init();

		}

	}

	/**
	 * Set the plugin's translation files location
	 *
	 * @package WhereUsed\HelpersLibrary
	 * @since   1.1.0
	 *
	 * @return void
	 */
	public static function load_plugin_textdomain(): void {
		load_plugin_textdomain( static::get_constant_value( 'SLUG' ), false, static::get_constant_value( 'LANGUAGES_DIR' ) );
	}

}