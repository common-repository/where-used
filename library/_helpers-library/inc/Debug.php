<?php

namespace WhereUsed\HelpersLibrary;

// Prevent Direct Access
( defined( 'ABSPATH' ) ) || die;

/**
 * Class Debug - Handles all the debug functionality
 *
 * @package WhereUsed\HelpersLibrary
 */
abstract class Debug {

	use Constants;

	/**
	 * Start Debug Process
	 *
	 * @since 1.1.0
	 */
	public static function init(): void {

		$plugin_global = static::get_constant_value( 'GLOBAL' );

		// Grab plugin's global
		global $$plugin_global;

		if ( defined( static::get_constant_name( 'DEBUG' ) ) ) {

			// Using user defined constant in wp-config.php
			$$plugin_global['debug'] = static::get_constant_value( 'DEBUG' );

		} else {

			// Debug mode is based on settings
			$settings = static::get_class( 'Settings' )::get_current_settings();

			$$plugin_global['debug'] = $settings->get( 'debug' ) ? true : false;

		}

		// Make sure we don't have any logs hanging around
		static::cleanup();

		if ( static::is_enabled() ) {

			$debug_mode_on = sprintf( __( 'Debug mode is on for site %s', static::get_constant_value( 'SLUG' ) ), get_site_url() );

			if ( wp_doing_cron() ) {
				static::log( '---------------------------------- Cron - ' . $debug_mode_on . ' ----------------------------------' );
			} elseif ( wp_doing_ajax() ) {
				static::log( '---------------------------------- Ajax - ' . $debug_mode_on . ' ----------------------------------' );

				add_filter( 'heartbeat_send', [
					static::class,
					'log_heartbeat',
				], 1, 1 );
			} else {
				static::log( '---------------------------------- ' . $debug_mode_on . ' ----------------------------------' );
			}
		}

		add_action( static::get_constant_value( 'HOOK_PREFIX' ) . 'admin_notices', [
			static::class,
			'debug_notices',
		] );

	}

	/**
	 * Displays notices in the admin related to whether Debug is enabled
	 *
	 * @return void
	 * @throws \ReflectionException
	 */
	public static function debug_notices(): void {

		if ( defined( static::get_constant_name( 'DEBUG' ) ) ) {

			// Debug mode is dictated by constant PLUGIN_DEBUG

			if ( static::is_enabled() ) {

				if ( is_network_admin() || is_admin() ) {
					if ( is_multisite() ) {
						static::get_class( 'Admin' )::add_notice( [
							'message' => sprintf( __( 'Debug mode is on and writing to logs for all sites using plugin %s. Please turn off Debug Mode by removing the constant %s from your code. This is likely found in your wp-config.php file in the web root.', static::get_constant_value( 'SLUG' ) ), static::get_constant_value( 'NAME' ), static::get_constant_name( 'DEBUG' ) ),
							'alert_level' => 'info',
						] );
					} else {
						static::get_class( 'Admin' )::add_notice( [
							'message' => sprintf( __( 'Debug mode is on and writing to logs. Please turn off by removing the constant %s from your code. This is likely found in your wp-config.php file in the web root.', static::get_constant_value( 'SLUG' ) ), static::get_constant_name( 'DEBUG' ) ),
							'alert_level' => 'info',
						] );
					}
				}
			}

		} else {

			// Debug is dictated by plugin settings

			$plugin_global = static::get_constant_value( 'GLOBAL' );

			// Grab plugin's global
			global $$plugin_global;

			if ( is_network_admin() ) {
				// Debug mode is based on Network Settings
				$settings = static::get_class( 'Network_Settings' )::get_current_settings( true );

			} else {
				// Debug mode is based on settings
				$settings = static::get_class( 'Settings' )::get_current_settings( true );
			}

			$$plugin_global['debug'] = $settings->get( 'debug' ) ? true : false;

			if ( static::is_enabled() ) {

				if ( is_network_admin() ) {
					static::get_class( 'Admin' )::add_notice( [
						'message' => sprintf( __( 'Debug mode is on and writing to logs for all sites using Network Settings. Please turn this off on the Network Settings page if you are not troubleshooting issues with %s plugin on one of the network sites.', static::get_constant_value( 'SLUG' ) ), static::get_constant_value( 'NAME' ) ),
						'alert_level' => 'info',
					] );
				} elseif ( is_admin() ) {
					static::get_class( 'Admin' )::add_notice( [
						'message' => sprintf( __( 'Debug mode is on and writing to logs. Please turn this off on the settings page if you are not troubleshooting issues with %s plugin.', static::get_constant_value( 'SLUG' ) ), static::get_constant_value( 'NAME' ) ),
						'alert_level' => 'info',
					] );
				}

			}

		}

	}

	/**
	 * Displays an objects contents in a table
	 *
	 * @param        $object
	 * @param string $id
	 * @param array  $exclude
	 *
	 * @return void
	 */
	public static function display_table( $object, string $id, array $exclude = [] ): void {
		?>
        <table id="debug-<?php
		echo esc_html( $id ); ?>" class="wp-list-table widefat fixed striped">
            <tr class="heading">
                <th><?php
					_e( 'Property', static::get_constant_value( 'SLUG' ) ); ?></th>
                <th><?php
					_e( 'Type', static::get_constant_value( 'SLUG' ) ); ?></th>
                <th><?php
					_e( 'Value', static::get_constant_value( 'SLUG' ) ); ?></th>
            </tr>
			<?php
			if ( is_object( $object ) ) {
				foreach ( $object->get_properties() as $property => $type ) {

					if ( ! in_array( $property, $exclude ) ) {
						?>
                        <tr>
                            <td style="text-align:center;"><?php
								echo esc_html( $property ); ?></td>
                            <td style="text-align:center;"><?php
								echo esc_html( $type ); ?></td>
                            <td style="text-align:center;"><?php
								$value = $object->get( $property );

								if ( is_array( $value ) || is_object( $value ) ) {
									$value = print_r( $value, true );
								}
								echo esc_html( $value );
								?>
                            </td>
                        </tr>
						<?php
					}
				}
			} else {
				// Assuming it is an array
				foreach ( $object as $key => $value ) {

					if ( ! in_array( $key, $exclude ) ) {

						if ( is_array( $value ) ) {
							$type = 'array';
						} elseif ( is_object( $value ) ) {
							$type = 'object';
						} elseif ( is_int( $value ) ) {
							$type = 'integer';
						} elseif ( is_bool( $value ) ) {
							$type = 'boolean';
						} else {
							$type = 'string';
						}
						?>
                        <tr>
                            <td style="text-align:center;"><?php
								echo esc_html( $key ); ?></td>
                            <td style="text-align:center;"><?php
								echo esc_html( $type ); ?></td>
                            <td style="text-align:center;"><?php

								if ( is_array( $value ) || is_object( $value ) ) {
									$value = print_r( $value, true );
								}
								echo esc_html( $value );
								?>
                            </td>
                        </tr>
						<?php
					}
				}
			}
			?>
        </table>
		<?php
	}

	/**
	 * Displays all the constants for the plugin and Helpers Library in a table
	 *
	 * @package WhereUsed\HelpersLibrary
	 * @since 1.1.0
	 *
	 * @return void
	 */
	public final static function display_constants_table(): void {

		$constants = get_defined_constants( true );
		$plugin_constants = [];
		foreach ( $constants['user'] as $key => $value ) {
			if ( strpos( $key, static::get_constant_value( 'PREFIX' ) ) !== false || strpos( $key, 'HELPERSLIBRARY' ) !== false ) {
				$plugin_constants[ $key ] = $value;
			}
		}

		ksort( $plugin_constants );

		static::display_table( $plugin_constants, 'constants' );
	}

	/**
	 * Creates the directory structure where the logs will reside
	 *
	 * @return void
	 */
	public final static function setup(): void {

		$debug_file = static::get_debug_log_file();

		// The log directory
		$parent_dir = dirname( $debug_file );

		if ( ! file_exists( $parent_dir ) ) {

			// Parent log directory doesn't exist, so add it
			mkdir( $parent_dir );
		}

		// File to prevent dir browsing
		$browsing_file = $parent_dir . '/index.html';

		if ( ! file_exists( $browsing_file ) ) {

			// Add index.html to prevent creeps from peeping
			file_put_contents( $browsing_file, 'silence is golden' );
		}

	}

	/**
	 * Removed the debug log for the current site
	 *
	 * @return void
	 */
	protected static function remove_debug_log(): void {

		$debug_file = static::get_debug_log_file();

		if ( file_exists( $debug_file ) ) {
			unlink( $debug_file );
			clearstatcache();
		}

	}

	/**
	 * Removes all files in the logs directory if debug mode is off
	 *
	 * @since 1.1.0
	 * @return void
	 */
	public static function cleanup(): void {

		if ( static::is_enabled() ) {

			static::setup();

			if ( REQUEST::text_field( 'tab' ) == 'debug' && REQUEST::bool( 'reset' ) ) {
				// Clear the log
				static::remove_debug_log();

				static::log( __( 'Cleared debug log.', static::get_constant_value( 'SLUG' ) ) );

				static::get_class( 'Admin' )::add_notice( [
					'message' => __( 'Debug manually cleared', static::get_constant_value( 'SLUG' ) ),
					'alert_level' => 'info',
				] );
			} elseif ( file_exists( static::get_debug_log_file() ) && filesize( static::get_debug_log_file() ) > 50000000 ) {
				// Make sure the files are not too big

				// Clear the log
				static::remove_debug_log();

				static::log( __( 'Debug log over 5MB. Cleared log.', static::get_constant_value( 'SLUG' ) ), 'warning' );
			}
		} else {

			// Clear the log
			static::remove_debug_log();

		}
	}

	/**
	 * Logs debug info to a log file
	 *
	 * @param string $message
	 * @param string $type
	 *
	 * @return void
	 */
	public static function log( string $message = '', string $type = 'info' ): void {

		if ( static::is_enabled() && $message ) {

			$user_id = ( wp_doing_cron() ) ? 0 : get_current_user_id();
			if ( wp_doing_cron() ) {
				$who = 'wp_cron';
			} elseif ( wp_doing_ajax() ) {
				$who = 'ajax';
			} else {
				$who = 'unknown';
			}

			// Write the log to a file
			file_put_contents( static::get_debug_log_file(), "\n" . wp_date( 'Y-m-d H:i:s' ) . ' - ' . $user_id . ' - ' . $who . ' - ' . $type . ' - ' . static::get_constant_value( 'NAME' ) . ': ' . $message, FILE_APPEND );

		}

	}

	/**
	 * Detects the WP heartbeat and logs it to the debug log
	 *
	 * @since 1.1.0
	 *
	 * @param $response
	 *
	 * @return mixed
	 */
	public static function log_heartbeat( array $response ): array {

		static::log( __( 'Detected WP Heartbeat', static::get_constant_value( 'SLUG' ) ) );

		return $response;
	}

	/**
	 * The location of the logs directory
	 *
	 * @since 1.1.0
	 * @return string
	 */
	public static function get_logs_dir(): string {

		return static::get_constant_value( 'LOGS_DIR' );

	}

	/**
	 * The location of the debug log file
	 *
	 * @since 1.1.0
	 * @return string
	 */
	public static function get_debug_log_file(): string {

		return static::get_logs_dir() . '/' . static::get_constant_value( 'CURRENT_SITE_ID' ) . '-debug.log';

	}

	/**
	 * Tells us whether Debug Mode is enabled
	 *
	 * @since 1.1.0
	 * @return bool
	 */
	public static function is_enabled(): bool {

		$plugin_global = static::get_constant_value( 'GLOBAL' );

		// Grab plugin's global
		global $$plugin_global;

		// Default to off
		$$plugin_global['debug'] = $$plugin_global['debug'] ?? false;

		return $$plugin_global['debug'];

	}

}

