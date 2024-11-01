<?php

namespace WhereUsed\HelpersLibrary;

// Prevent Direct Access
( defined( 'ABSPATH' ) ) || die;

/**
 * Class Migration
 *
 * @package WhereUsed\HelpersLibrary
 * @since 1.1.0
 */
abstract class Migration {

	use Constants;

	/**
	 * Initiates migration process
	 *
	 * @return void
	 */
	public final static function init(): void {

		// Start series of upgrade migrations

		$sites = Get::sites();

		// Run for all sites
		foreach ( $sites as $site ) {

			if ( is_multisite() ) {
				switch_to_blog( $site->blog_id );
			}

			if ( is_plugin_active( static::get_constant_value( 'PLUGIN' ) ) ) {

				// Refresh settings data
				$settings = static::get_class( 'Settings' )::get_current_settings( true );
				$db_version = $settings->get( 'db_version' );

				static::run_all( $db_version );

				// Update the network db settings version
				if ( is_multisite() && is_main_site() ) {
					$network_settings = static::get_class( 'Network_Settings' )::get_current_settings( true );
					$network_settings->set_version( static::get_constant_value( 'VERSION' ) );
					$network_settings->save( false );
				}

				// Update the db settings version
				$settings->set_version( static::get_constant_value( 'VERSION' ) );
				$settings->save( false );

			}

			if ( is_multisite() ) {
				restore_current_blog();
			}
		}

	}

	/**
	 * Runs all the migrations
	 *
	 * @package WhereUsed\HelpersLibrary
	 * @since   1.1.0
	 *
	 * @param string $db_version
	 *
	 * @return void
	 */
	abstract protected static function run_all( string $db_version ): void;

}