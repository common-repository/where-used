<?php

namespace WhereUsed\HelpersLibrary;

// Prevent Direct Access
( defined( 'ABSPATH' ) ) || die;

/**
 * Class Run - Run maintenance tasks and other misc tasks
 */
abstract class Run {

	use Constants;

	/**
	 * Creates the table for all the data this plugin stores
	 *
	 * @param array $table
	 */
	public final static function create_table( array $table ): void {

		global $wpdb;

		$table_name = $table['name'] ?? '';
		$columns = $table['columns'] ?? [];
		$primary_key = $table['index']['primary'] ?? '';
		$index_keys = $table['index']['key'] ?? [];
		$engine = $table['engine'] ?? 'InnoDB';
		unset( $table );

		if ( $table_name && ! empty( $columns ) && ! empty( $index_keys ) && $primary_key ) {

			$lines = [];

			$values = [];
			$values[] = $wpdb->prefix . $table_name;

			foreach ( $columns as $column ) {
				$values[] = $column['name'];
				$line = '`%' . count( $values ) . '$s`';

				if ( isset( $column['type'] ) ) {
					$values[] = $column['type'];
					$line .= ' %' . count( $values ) . '$s';
				}

				if ( isset( $column['null'] ) ) {
					if ( $column['null'] ) {
						$line .= ' NULL';
					} else {
						$line .= ' NOT NULL';
					}
				}

				if ( isset( $column['auto-increment'] ) ) {
					if ( true === $column['auto-increment'] ) {
						$line .= ' AUTO_INCREMENT';
					}
				}

				if ( isset( $column['default'] ) ) {
					if ( '' === $column['default'] ) {
						$line .= ' DEFAULT ""';
					} else {
						$values[] = $column['default'];
						$line .= ' DEFAULT "%' . count( $values ) . '$s"';
					}
				}

				$lines[] = $line;

			}

			// Set the Primary Key
			$values[] = $primary_key;
			$line = 'PRIMARY KEY `%' . count( $values ) . '$s`';

			$values[] = $primary_key;
			$line .= '(`%' . count( $values ) . '$s`)';

			$lines[] = $line;

			foreach ( $index_keys as $index_key => $key ) {

				if ( is_array( $key ) ) {
					$keys = [];
					foreach ( $key as $k ) {
						$values[] = $k;
						$keys[] = '`%' . count( $values ) . '$s`';
					}
					$values[] = $index_key;
					$lines[] = 'KEY `%' . count( $values ) . '$s` (' . implode( ',', $keys ) . ')';
				} else {
					$values[] = $key;
					$line = 'KEY `%' . count( $values ) . '$s`';

					$values[] = $key;
					$line .= '(`%' . count( $values ) . '$s`)';

					$lines[] = $line;
				}

			}

			$values[] = $engine;
			$wpdb->query( $wpdb->prepare( 'CREATE TABLE IF NOT EXISTS `%1$s` (' . implode( ',', $lines ) . ') ENGINE=%' . count( $values ) . '$s ' . $wpdb->get_charset_collate(), $values ) );

		} else {

			echo 'Creating table failed: ';

			// Debug why it didn't work
			if ( ! $table_name ) {
				echo 'empty table';
			} elseif ( empty( $columns ) ) {
				echo 'empty columns';
			} elseif ( ! empty( $index_keys ) ) {
				echo 'empty index keys';
			} elseif ( ! $primary_key ) {
				echo 'no primary key';
			} else {
				echo 'not sure why it did not work.';
			}

			die();
		}

	}

	/**
	 * Creates all the tables for the current site
	 *
	 * @param array $tables
	 *
	 * @return void
	 */
	public final static function create_tables( array $tables = [] ): void {

		if ( ! empty( $tables ) ) {

			// Create tables for current site

			foreach ( $tables as $table ) {
				// Creates DB table if needed
				static::create_table( $table );
			}

		}

	}

	/**
	 * Removes the provided table and then recreates it: purges all existing data.
	 *
	 * @param array $table
	 *
	 * @return bool
	 */
	public final static function purge_table( array $table ): bool {

		$response = false;

		if ( ! empty ( $table ) ) {

			// Removes table
			$dropped = static::drop_table( $table['name'] );

			// Recreates Table
			$created = static::create_table( $table );

			if ( $dropped && $created ) {
				$response = true;
			}

		}

		return $response;

	}

	/**
	 * Deletes the table from the database
	 *
	 * @param string $table_name
	 *
	 * @return bool
	 */
	public final static function drop_table( string $table_name = '' ): bool {

		global $wpdb;

		$response = false;

		if ( $table_name ) {

			$table_name = $wpdb->prefix . $table_name;

			Debug::log( 'Deleting table: ' . $table_name );

			// Removes table
			$response = $wpdb->query( $wpdb->prepare( 'DROP TABLE IF EXISTS `%1$s`;', $table_name ) );
		}

		return $response;

	}

	/**
	 * Deletes the table from the database
	 *
	 * @param array  $tables
	 *
	 * @return array
	 */
	public final static function drop_tables( array $tables ): array {

		$responses = [];

		if ( ! empty( $tables ) ) {

			foreach ( $tables as $table_name => $table ) {
				// Removes table
				$responses[] = static::drop_table( $table_name );
			}

		}

		return $responses;

	}

	/**
	 * Deletes all the options related to the plugin
	 *
	 * @return array
	 */
	public abstract static function delete_options(): array;

	/**
	 * Cleans the Request URI from system variables stating with underscore and empty vars.
	 * This is needed as WP and other plugins will add a bunch of GET variables to the URL making it a nightmare to troubleshoot when there is a problem
	 */
	public final static function clean_request_uri(): void {

		$get_vars = [];

		$get = REQUEST::GET();

		// Filter out garbage from URI
		foreach ( $get as $key => $value ) {

			$remove = false;

			if ( substr( $key, 0, 1 ) == '_' ) {
				// Remove underscore vars
				$remove = true;
			} elseif ( '' === $value ) {
				// remove empty vars
				$remove = true;
			}

			if ( $remove ) {
				unset( $_GET[ $key ] );
			} else {
				$get_vars[] = $key . '=' . $value;
			}

		}

		// Recreate the Request URI
		$request_uri = sanitize_url( $_SERVER['REQUEST_URI'] );
		$request_uri = parse_url( $request_uri );

		// Base path
		$request_path = $request_uri['path'] ?? '';

		// Query vars
		$request_query = implode( '&', $get_vars );
		$request_query = ( $request_query ) ? '?' . $request_query : '';

		// Anchor Link
		$request_fragment = $request_uri['fragment'] ?? '';
		$request_fragment = ( $request_fragment ) ? '#' . $request_fragment : '';

		// Replace URI
		$_SERVER['REQUEST_URI'] = $request_path . $request_query . $request_fragment;

	}

	/**
	 * Clears all the crons related to this plugin
	 *
	 * @return void
	 */
	public static function remove_crons(): void {

		$scheduled = get_option( 'cron' );

		// Catch any legacy named crons
		$cron_prefixes[] = static::get_constant_value( 'GLOBAL' );
		$cron_prefixes[] = static::get_constant_value( 'OPTION' );
		$cron_prefixes[] = static::get_constant_value( 'SLUG' );

		// Remove any WP prefixed crons
		$cron_prefixes[] = 'wp_' . static::get_constant_value( 'GLOBAL' );
		$cron_prefixes[] = 'wp_' . static::get_constant_value( 'OPTION' );
		$cron_prefixes[] = 'wp_' . static::get_constant_value( 'SLUG' );

		if ( ! empty( $scheduled ) ) {

			// We have scheduled timestamps
			foreach ( $scheduled as $timestamp => $crons ) {

				if ( ! empty( $crons ) && is_array( $crons ) ) {

					// We have crons in a schedule run
					foreach ( $crons as $cron_name => $details ) {

						foreach ( $cron_prefixes as $prefix ) {
							if ( strpos( $cron_name, $prefix ) !== false ) {

								// Remove Crons Just In Case They Didn't Get Removed
								wp_clear_scheduled_hook( $cron_name );
							}
						}

					}

				}

			}

		}
	}

	/**
	 * Prevent plugins like WP Super Cache and W3TC from caching any data on this page.
	 *
	 * @param bool $wp_cache
	 *
	 * @return void
	 */
	public final static function prevent_caching( bool $wp_cache = false ): void {

		if ( ! defined( 'WHEREUSED_HELPERSLIBRARY_DONOTCACHE' ) ) {

			// Flag to prevent this from running twice
			define( 'WHEREUSED_HELPERSLIBRARY_DONOTCACHE', true );

			// Compatible with W3TC and WP Super Cache
			( defined( 'DONOTCACHEOBJECT' ) ) || define( 'DONOTCACHEOBJECT', true );
			( defined( 'DONOTCACHEDB' ) ) || define( 'DONOTCACHEDB', true );
			( defined( 'DONOTCACHEPAGE' ) ) || define( 'DONOTCACHEPAGE', true );

			// Compatible with SpinupWP
			( defined( 'WP_REDIS_DISABLED' ) ) || define( 'WP_REDIS_DISABLED', true );

			// Prevent page caching in WP Rocket
			add_filter( 'do_rocket_generate_caching_files', '__return_false' );
		}

		if ( $wp_cache && ! defined( 'WHEREUSED_HELPERSLIBRARY_DONOTCACHE_WP' ) ) {

			// Flag to prevent this from running twice
			define( 'WHEREUSED_HELPERSLIBRARY_DONOTCACHE_WP', true );

			// Prevent WP Caching objects in the global variables
			wp_suspend_cache_addition( true );

		}

	}

}
