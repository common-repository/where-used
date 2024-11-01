<?php

namespace WhereUsed\HelpersLibrary;

// Prevent Direct Access
( defined( 'ABSPATH' ) ) || die;

/**
 * Class Scan_Process
 *
 * Credits: This class was built from the plugin "WP Background Processing" by Delicious Brains Inc.
 * Due to the amount of code that was eventually overwritten, we decided remove the dependency and combine all the code into this one class.
 *
 * @link    https://github.com/A5hleyRich/wp-background-processing
 *
 * @package WhereUsed\HelpersLibrary
 * @since   1.3.0
 */
class Scan_Process {

	use Constants;

	// Prefix for hooks
	protected $identifier;

	//These variables are going to be the classes in the scope of the plugin
	protected object $scan;
	protected object $notification;
	protected string $debug;

	// Manage resource usage
	protected $start_time = 0;
	protected bool $time_exceeded = false;
	protected bool $memory_exceeded = false;

	// the queue broken into groups
	protected array $groups;

	// Store cache in options table temporarily
	public string $cache_status_codes_option;

	protected $cron_hook_identifier;
	protected $cron_interval_identifier;

	/**
	 * Initiate new background process
	 *
	 * @package WhereUsed\HelpersLibrary
	 * @since   1.3.0
	 */
	public function __construct() {

		$this->identifier = static::get_constant_value( 'HOOK_PREFIX' ) . 'scan';
		$this->cron_hook_identifier = $this->identifier . '_cron';
		$this->cron_interval_identifier = $this->cron_hook_identifier . '_interval';
		$this->cache_status_codes_option = $this->identifier . '_cache_status_codes';

		// Setting scope so that we can use the plugin's classes
		$this->debug = static::get_class( 'Debug' );
		$this->scan = static::get_class( 'Scan' )::get_current( true );

		$notification = static::get_class( 'Notification' );
		$this->notification = new $notification();

		$this->set_hooks();
	}

	/**
	 * The type of scans that are allowed
	 *
	 * @package WhereUsed\HelpersLibrary
	 * @since   1.5.0
	 *
	 * @return array
	 */
	protected function get_scan_types() : array {

		return [
			'full-scan',
		];

	}

	/**
	 * Setup background process: which always need to be running so that crons can be registered properly
	 *
	 * @package WhereUsed\HelpersLibrary
	 * @since   1.3.0
	 *
	 * @return void
	 */
	public static function init(): void {

		$plugin_global = static::get_constant_value( 'GLOBAL' );

		// Grab plugin's global
		global $$plugin_global;

		$debug = static::get_class( 'Debug' );

		$debug::log( 'Scan_Process init' );

		$class = static::get_class( 'Scan_Process' );

		$$plugin_global['scan-process'] = new $class();

		$debug::log( 'Scan_Process init done' );

	}

	/**
	 * Set the hooks
	 *
	 * @package WhereUsed\HelpersLibrary
	 * @since   1.3.0
	 *
	 * @return void
	 */
	protected function set_hooks(): void {

		// start scan
		add_action( 'wp_ajax_' . $this->identifier . '_start', [
			$this,
			'try_to_start',
		], 10, 1 );

		// cancel scan
		add_action( 'wp_ajax_' . $this->identifier . '_cancel', [
			$this,
			'cancel',
		], 10, 0 );

		// display progress bar
		add_action( 'wp_ajax_' . $this->identifier . '_progress_bar', [
			static::class,
			'display_progress_bar',
		], 10, 0 );

		add_action( 'wp_ajax_' . $this->identifier, [
			$this,
			'maybe_handle',
		] );
		add_action( 'wp_ajax_nopriv_' . $this->identifier, [
			$this,
			'maybe_handle',
		] );

		// Crons
		add_action( $this->cron_hook_identifier, [
			$this,
			'handle_cron_healthcheck',
		] );
		add_filter( 'cron_schedules', [
			$this,
			'schedule_cron_healthcheck',
		] );

	}

	/**
	 * The interval of minutes that the cron runs
	 *
	 * @package WhereUsed\HelpersLibrary
	 * @since   1.3.0
	 *
	 * @return int Interval in minutes
	 */
	public function get_cron_interval(): int {

		// Grab the current time limit (default 20 seconds)
		$seconds = $this->get_time_limit();

		// Add 30 seconds to allow the server to breathe
		// 20 + 30 = 50 seconds
		$seconds += 30;

		// Convert to minutes
		// ceil( 50 / 60 ) = 1  minute
		$minutes = ceil( $seconds / 60 );

		return $minutes;

	}

	/**
	 * Update content of the queue file
	 *
	 * @package WhereUsed\HelpersLibrary
	 * @since   1.3.0
	 *
	 * @return void
	 */
	public function update( string $type, array $group ): void {

		$write_to_file = false;
		$file_path = $this->get_queue_file_path( $type );

		if ( file_exists( $file_path ) ) {
			// Open the file for reading;
			$fh = fopen( $file_path, 'r+' );

			// Handle failure
			if ( $fh === false ) {
				$this->debug::log( 'Could not open file: ' . $file_path );
			} else {

				foreach ( $group as $line ) {
					$current_line = trim( stream_get_line( $fh, 1024, "~" ) );

					if ( $current_line == $line || $current_line . '~' == $line ) {
						// Move the pointer down one
						fgets( $fh );
						$write_to_file = true;
					} else {
						$this->debug::log( 'Could not find line: ' . $line . '!=' . $current_line );
					}
				}

				if ( $write_to_file ) {
					// Overwrite the file with everything after the current position of the pointer
					file_put_contents( $file_path, stream_get_contents( $fh ) );
				}

				// Close the file handle; when you are done using
				if ( fclose( $fh ) === false ) {
					$this->debug::log( 'Could not close file: ' . $file_path );
				}

			}
		}

	}

	/**
	 * Delete queue files for current site
	 *
	 * @package WhereUsed\HelpersLibrary
	 * @since   1.3.0
	 *
	 * @return void
	 */
	public function delete(): void {

		// Clear groups
		$this->groups = [];

		$queue_files = $this->get_queue_files();

		if ( ! empty( $queue_files ) ) {
			foreach ( $queue_files as $queue_file ) {
				if ( file_exists( $queue_file ) ) {
					// Delete queue file
					unlink( $queue_file );
				}
			}

			clearstatcache();
		}

	}

	/**
	 * Retrieves an array of queue file paths for the current site
	 *
	 * @package WhereUsed\HelpersLibrary
	 * @since   1.3.0
	 *
	 * @return array
	 * @throws \ReflectionException
	 */
	public function get_queue_files(): array {

		$file_prefix = WHEREUSED_HELPERSLIBRARY_CURRENT_SITE_ID . '-';
		$queue_dir = static::get_constant_value( 'QUEUE_DIR' );

		$queue_files = [];

		if ( $queue_dir && file_exists( $queue_dir ) ) {
			// Find all queue files
			$files = scandir( $queue_dir );

			if ( ! empty( $files ) ) {
				foreach ( $files as $file ) {
					if ( strpos( $file, $file_prefix ) !== false ) {
						$queue_files[] = $queue_dir . '/' . $file;
					}
				}
			}
		}

		return $queue_files;
	}

	/**
	 * Gets a specific queue file
	 *
	 * @package WhereUsed\HelpersLibrary
	 * @since   1.3.0
	 *
	 * @param string $type
	 *
	 * @return string
	 * @throws \ReflectionException
	 */
	public function get_queue_file( string $type ): string {

		$queue_file = '';
		$files = $this->get_queue_files();

		if ( ! empty( $files ) ) {
			foreach ( $files as $file ) {
				if ( strpos( $file, $type ) !== false ) {
					$queue_file = $file;
					break;
				}
			}
		}

		return $queue_file;
	}

	/**
	 * The path location of the queue file
	 *
	 * @package WhereUsed\HelpersLibrary
	 * @since   1.3.0
	 *
	 * @param string $type
	 *
	 * @return string
	 * @throws \ReflectionException
	 */
	public function get_queue_file_path( string $type ): string {

		return static::get_constant_value( 'QUEUE_DIR' ) . '/' . WHEREUSED_HELPERSLIBRARY_CURRENT_SITE_ID . '-' . $type . '.txt';

	}

	/**
	 * Is queue empty?
	 *
	 * @package WhereUsed\HelpersLibrary
	 * @since   1.3.0
	 *
	 * @return bool
	 */
	public function is_queue_empty(): bool {

		return ! $this->get_queue_count();

	}

	/**
	 * Starts an admin session, so we have proper access to scan everthing
	 *
	 * @package WhereUsed\HelpersLibrary
	 * @since   1.3.1
	 *
	 * @return void
	 */
	private function start_admin_session(): void {

		if ( ! current_user_can( 'manage_options' ) ) {
			// Get admin users
			$admin_users = get_users( [ 'capability__in' => [ 'manage_options' ] ] );

			if ( ! empty( $admin_users ) ) {

				// Check If First Admin User Exists
				if ( ! empty( $admin_users[0]->ID ) ) {

					// 1 hour expiration
					$expiration = time() + 3600;

					// Include class WP_Session_Tokens
					require_once( ABSPATH . '/wp-includes/class-wp-session-tokens.php' );

					// Create token
					$manager = \WP_Session_Tokens::get_instance( $admin_users[0]->ID );
					$token = $manager->create( $expiration );

					// Set login cookies
					$_COOKIE['wordpress_test_cookie'] = 'WP Cookie check';
					$_COOKIE[ LOGGED_IN_COOKIE ] = wp_generate_auth_cookie( $admin_users[0]->ID, $expiration, 'logged_in', $token );

				} else {
					debug::log( 'this should not happen - no admin user' );
				}
			}
		}

	}

	/**
	 * Handle
	 *
	 * Pass each queue item to the task handler, while remaining
	 * within server memory and time limit constraints.
	 *
	 * @package WhereUsed\HelpersLibrary
	 * @since   1.3.0
	 *
	 * @return void
	 */
	public function handle(): void {

		// Prevent object caching by WP
		static::get_class( 'Run' )::prevent_caching( true );

		$this->debug::log( __( 'Running Background Scan', static::get_constant_value( 'SLUG' ) ) );

		$this->lock_process();

		// Ensure we have admin access
		$this->start_admin_session();

		$continue = true;

		if ( ! $this->is_queue_empty() ) {

			// Process everything

			if ( file_exists( $this->get_queue_file( 'menus' ) ) ) {
				$continue = $this->process( 'menus', 1 );
			}

			if ( $continue && file_exists( $this->get_queue_file( 'users' ) ) ) {
				$continue = $this->process( 'users', 10 );
			}

			if ( $continue && file_exists( $this->get_queue_file( 'posts' ) ) ) {
				$continue = $this->process( 'posts', 5 );
			}

			if ( $continue && file_exists( $this->get_queue_file( 'terms' ) ) ) {
				$continue = $this->process( 'terms', 10 );
			}

			if ( $continue && file_exists( $this->get_queue_file( 'statuses' ) ) ) {
				$this->process( 'statuses', 5 );
			}

		}

		$this->debug::log( __( 'Unlocking Scan', static::get_constant_value( 'SLUG' ) ) );

		$this->unlock_process();

		if ( ! $this->time_exceeded() && ! $this->memory_exceeded() ) {

			// Start next batch or complete process.
			if ( ! $this->is_queue_empty() ) {
				$this->debug::log( __( 'Continuing Background Scan', static::get_constant_value( 'SLUG' ) ) );
				$this->dispatch();
			} else {
				$this->debug::log( __( 'Scan: Queue is empty. [1]', static::get_constant_value( 'SLUG' ) ) );
				$this->complete();
			}

		} else {
			// Ran out of resources, let's check to see if we actually finished

			$this->debug::log( __( 'Scan: Out of resources.', static::get_constant_value( 'SLUG' ) ) );

			if ( $this->is_queue_empty() ) {
				$this->debug::log( __( 'Scan: Queue is empty. [2]', static::get_constant_value( 'SLUG' ) ) );
				$this->complete();
			}
		}

		wp_die();
	}

	/**
	 * Groups the array into chunks for better efficiency of SQL queries and memory usage
	 *
	 * @package WhereUsed\HelpersLibrary
	 * @since   1.3.3
	 *
	 * @param string $type
	 * @param int    $group_size
	 *
	 * @return array
	 * @throws \ReflectionException
	 */
	public function get_group( string $type = '', int $group_size = 10 ): array {

		$group = [];

		// Queue File path
		$file_path = $this->get_queue_file( $type );

		/**
		 * Modify the size of the group. The greater the group the fewer DB queries, BUT it will take longer to run and risk possibly timing out with PHP.
		 * WARNING: Modifying these group sizes could potentially cause high server load. Make sure you know what you are doing.
		 */
		$group_size = apply_filters( $this->identifier . '_group_size', $group_size, $type );

		// Governor to prevent people from being reckless
		if ( $group_size > 50 ) {
			// Limit group size to 50
			$group_size = 50;
		}

		$this->debug::log( 'Group size: ' . $group_size . ' - Queue File: ' . $file_path );

		// Grab the first X lines of the queue file
		if ( file_exists( $file_path ) ) {

			// Open the file for reading;
			$handle = fopen( $file_path, 'r+' );

			// Handle failure
			if ( $handle === false ) {
				$this->debug::log( 'Could not open file: ' . $file_path );
			} else {
				$num = 1;
				$flag = $this->get_prepend_flag();

				// Grab the current line of the queue file
				$current_line = trim( stream_get_line( $handle, 1024, '~' ) );

				if ( $current_line . '~' == $flag ) {
					// Placeholder goes into group
					$group[] = $flag;
				} else {
					// Build group based on group size
					while ( $num <= $group_size && $current_line ) {

						$group[] = $current_line;

						// move pointer
						fgets( $handle );

						// Grab the current line of the queue file
						$current_line = trim( stream_get_line( $handle, 1024, '~' ) );

						++ $num;
					}
				}

				fclose( $handle );

				if ( ! $current_line ) {
					// Reached end of queue file. Delete file.
					unlink( $file_path );
				}

			}
		}

		$this->debug::log( 'Group: ' . print_r( $group, true ) );

		return $group;

	}

	/**
	 * Process the queue
	 *
	 * @package WhereUsed\HelpersLibrary
	 * @since   1.3.3
	 *
	 * @return bool
	 */
	protected function process( string $type, int $group_size = 10 ): bool {

		global $wpdb;

		$this->debug::log( sprintf( __( 'Scan: Processing %s', static::get_constant_value( 'SLUG' ) ), $type ) );

		if ( $this->time_exceeded() || $this->memory_exceeded() ) {
			$this->debug::log( sprintf( __( 'Scan: Resources exceeded. Aborting %s scan.', static::get_constant_value( 'SLUG' ) ), $type ) );

			// Tell next process to NOT continue
			return false;
		}

		$group = $this->get_group( $type, $group_size );

		// Skip if we have no groups
		if ( empty( $group ) ) {
			$this->debug::log( sprintf( __( 'Scan: Nothing to scan. Aborting %s scan.', static::get_constant_value( 'SLUG' ) ), $type ) );

			// Tell next process to continue
			return true;
		}

		$tables = [
			'terms' => $wpdb->prefix . 'term_taxonomy',
			'posts' => $wpdb->prefix . 'posts',
			'users' => $wpdb->prefix . 'users',
		];

		$table = $tables[ $type ] ?? '';

		$flag = $this->get_prepend_flag();

		while ( ! empty( $group ) ) {

			$first_queue = $group[0] ?? '';

			if ( $first_queue != $flag ) {

				$values = [];
				$values[] = $table;

				$placeholders = [];

				foreach ( $group as $value ) {
					// Grab the queue ID or URL
					$queue = strstr( $value, '|', true );

					if ( false === $queue ) {
						// Didn't find delimiter "|"
						$queue = trim( $value ?? '' );
					}

					$values[] = $queue;
					$placeholders[] = '%' . count( $values ) . '$s';
				}

				// Grab terms from DB - Use a custom query so that it's faster and uses less memory
				if ( 'terms' == $type ) {
					$sql = $wpdb->prepare( 'SELECT `term_id`, `taxonomy`, `description` FROM `%1$s` WHERE `term_id` IN ( ' . implode( ',', $placeholders ) . ')', $values );
				} elseif ( 'posts' == $type ) {
					$sql = $wpdb->prepare( 'SELECT * FROM `%1$s` WHERE `ID` IN ( ' . implode( ',', $placeholders ) . ');', $values );
				} elseif ( 'users' == $type ) {
					$sql = $wpdb->prepare( 'SELECT * FROM `%1$s` WHERE `ID` IN (' . implode( ",", $placeholders ) . ');', $values );
				} else {
					$sql = apply_filters( $this->identifier . '_process_' . $type . '_sql', false );
				}

				if ( $sql ) {
					// Grab results via mysqli
					$mysqli = static::get_class( 'Get' )::db_connection();
					$result = $mysqli->query( $sql );
				} else {
					// The group is the results
					$result = $group;
				}

				// Process each object
				if ( $result ) {

					// Set the object
					if ( $sql ) {
						$object = $result->fetch_object();
					} else {
						$object = array_pop( $result );
					}

					while ( $object ) {

						// Return false on this filter if you want to skip this particular object
						if ( apply_filters( $this->identifier . '_process_' . $type, $object ) ) {

							if ( 'terms' == $type ) {
								$this->debug::log( sprintf( __( 'Scanning term ID: %d', static::get_constant_value( 'SLUG' ) ), $object->term_id ) );
								//$this->debug::log( 'Memory- (' . __LINE__ . '): ' . round( memory_get_usage() / 1024 / 1024, 2 ) . 'MB' );
								$this->scan::scan_term( $object, WHEREUSED_HELPERSLIBRARY_CURRENT_SITE_ID, $this->cache_status_codes_option );
								//$this->debug::log( 'Memory- (' . __LINE__ . '): ' . round( memory_get_usage() / 1024 / 1024, 2 ) . 'MB' );
							} elseif ( 'posts' == $type ) {
								$this->debug::log( sprintf( __( 'Scanning post ID: %d', static::get_constant_value( 'SLUG' ) ), $object->ID ) );
								//$this->debug::log( 'Memory- (' . __LINE__ . '): ' . round( memory_get_usage() / 1024 / 1024, 2 ) . 'MB' );
								$this->scan::scan_post( $object, WHEREUSED_HELPERSLIBRARY_CURRENT_SITE_ID, $this->cache_status_codes_option );
								//$this->debug::log( 'Memory- (' . __LINE__ . '): ' . round( memory_get_usage() / 1024 / 1024, 2 ) . 'MB' );
							} elseif ( 'users' == $type ) {
								$this->debug::log( sprintf( __( 'Scanning user ID: %d', static::get_constant_value( 'SLUG' ) ), $object->ID ) );
								//$this->debug::log( 'Memory- (' . __LINE__ . '): ' . round( memory_get_usage() / 1024 / 1024, 2 ) . 'MB' );
								$this->scan::scan_user( $object, WHEREUSED_HELPERSLIBRARY_CURRENT_SITE_ID, $this->cache_status_codes_option );
								//$this->debug::log( 'Memory- (' . __LINE__ . '): ' . round( memory_get_usage() / 1024 / 1024, 2 ) . 'MB' );
							} elseif ( 'statuses' == $type ) {
								if ( Get::is_real_url( $object ) ) {
									$this->debug::log( sprintf( __( 'Scanning URL ID: %s', static::get_constant_value( 'SLUG' ) ), $object ) );
									//$this->debug::log( 'Memory- (' . __LINE__ . '): ' . round( memory_get_usage() / 1024 / 1024, 2 ) . 'MB' )
									$this->scan::status_check_update( $object, [], $this->cache_status_codes_option );
									//$this->debug::log( 'Memory- (' . __LINE__ . '): ' . round( memory_get_usage() / 1024 / 1024, 2 ) . 'MB' )
								}
							} elseif ( 'menus' == $type ) {
								$menu = static::get_class( 'Menu' )::get_by_id( (int) $object );
								if ( $menu ) {
									$this->debug::log( sprintf( __( 'Scanning menu ID: %d', static::get_constant_value( 'SLUG' ) ), $object ) );
									//$this->debug::log( 'Memory- (' . __LINE__ . '): ' . round( memory_get_usage() / 1024 / 1024, 2 ) . 'MB' );
									$this->scan::scan_menu( $menu, WHEREUSED_HELPERSLIBRARY_CURRENT_SITE_ID, $this->cache_status_codes_option );
									//$this->debug::log( 'Memory- (' . __LINE__ . '): ' . round( memory_get_usage() / 1024 / 1024, 2 ) . 'MB' );
								}
							}
						}

						// Set the object
						if ( $sql ) {
							$object = $result->fetch_object();
						} else {
							$object = array_pop( $result );
						}

					}

					if ( $sql ) {
						// Clear mysqli memory
						$result->free_result();
					}

				}

			}

			if ( $this->update_progress( $group, $type ) ) {

				// Process done or cancelled
				$this->debug::log( __( 'Stopping process.', static::get_constant_value( 'SLUG' ) ) );

				break;
			}

			// Refresh the group
			$group = $this->get_group( $type, $group_size );

		}

		// Tell next process to continue
		return true;

	}

	/**
	 * Updates the progress for the batch data.
	 *
	 * @package WhereUsed\HelpersLibrary
	 * @since   1.3.0
	 *
	 * @return bool If the process should stop
	 */
	private function update_progress( array $group, string $currently_scanning = '' ): bool {

		$this->debug::log( sprintf( __( 'Updating progress for %s...', static::get_constant_value( 'SLUG' ) ), $currently_scanning ) );

		// Should we stop the scan process?
		$stop = false;

		$scan = $this->scan::get_current( true );

		$scan->set_currently( ucfirst( $currently_scanning ) );

		// Update the queue with the new numbers
		$this->update( $currently_scanning, $group );

		$queue_count = $this->get_queue_count();

		// Update stored scan progress
		$scan->update_progress( $queue_count );

		// Update or delete current batch.
		if ( $queue_count ) {

			$this->debug::log( __( 'We still have items in the queue.', static::get_constant_value( 'SLUG' ) ) );

			if ( $scan->get( 'cancelled' ) ) {
				// The scan has been cancelled
				$this->debug::log( __( 'Scan cancelled...stopping process.', static::get_constant_value( 'SLUG' ) ) );

				// Delete the queue
				$this->delete();

				// Clear Status Code Cache
				delete_option( $this->cache_status_codes_option );

				$stop = true;
			} elseif ( $this->time_exceeded() || $this->memory_exceeded() ) {
				// Batch limits reached.
				$this->debug::log( __( 'Resource limits reached...pausing process.', static::get_constant_value( 'SLUG' ) ) );
				$stop = true;
			}

		} else {
			// Delete the queue
			$this->delete();

		}

		return $stop;

	}

	/**
	 * Counts the number of lines in all the queue files
	 *
	 * @package WhereUsed\HelpersLibrary
	 * @since   1.3.0
	 *
	 * @return int
	 */
	public function get_queue_count(): int {

		$count = 0;

		// Grab counts directly from teh queue files

		$queue_files = $this->get_queue_files();

		if ( ! empty( $queue_files ) ) {
			// count lines of each file

			foreach ( $queue_files as $queue_file ) {

				$handle = fopen( $queue_file, "r" );

				$file_count = 0;
				while ( ! feof( $handle ) ) {
					// Grab next line of file
					$line = fgets( $handle );
					$file_count ++;
				}

				fclose( $handle );

				if ( $file_count ) {
					$count += $file_count;
				} else {
					// File is empty; remove it
					unlink( $queue_file );
					clearstatcache();
				}
			}

		}

		$this->debug::log( sprintf( __( 'Queue count: %d', static::get_constant_value( 'SLUG' ) ), $count ) );

		return $count;
	}

	/**
	 * Complete
	 *
	 * @package WhereUsed\HelpersLibrary
	 * @since   1.3.0
	 *
	 * @return void
	 */
	protected function complete(): void {

		// Unschedule the cron healthcheck.
		$this->clear_scheduled_event();

		$plugin_slug = static::get_constant_value( 'SLUG' );
		$plugin_admin_url = static::get_constant_value( 'ADMIN_URL' );

		$this->debug::log( __( 'Scan Complete', $plugin_slug ) );

		// Mark the scan as complete
		$scan = $this->scan::get_current( true );
		$type = $scan->get( 'type' );
		$scan->set_end_date();
		$cancelled = $scan->get( 'cancelled' );
		$scan->save();

		if ( 'full-scan' === $type ) {
			if ( $cancelled > 0 ) {
				$user = get_user_by( 'ID', $cancelled );

				$this->notification::add_notification( [
					'message' => sprintf( __( 'A full scan has been cancelled by %s. It is recommended to run a full scan uninterrupted.', $plugin_slug ), $user->display_name ),
					'link_url' => $plugin_admin_url . '#scan',
					'link_anchor_text' => __( 'View Previous Scan Details', $plugin_slug ),
					'alert_level' => 'warning',
				] );
			} else {
				$this->notification::add_notification( [
					'message' => __( 'A full scan has completed.', $plugin_slug ),
					'link_url' => $plugin_admin_url . '#scan',
					'link_anchor_text' => __( 'View Scan Details', $plugin_slug ),
					'alert_level' => 'success',
				] );
			}
		} elseif ( 'status-check' === $type ) {
			// Status check scan

			if ( $cancelled > 0 ) {
				$user = get_user_by( 'ID', $cancelled );

				$this->notification::add_notification( [
					'message' => sprintf( __( 'The status check scan has been cancelled by %s.', $plugin_slug ), $user->display_name ),
					'link_url' => $plugin_admin_url . '#scan',
					'link_anchor_text' => __( 'View Previous Scan Details', $plugin_slug ),
					'alert_level' => 'warning',
				] );
			} else {

				$message = __( 'All status codes have been updated.', $plugin_slug );

				if ( wp_doing_cron() ) {
					$message = 'WPCron: ' . $message;
				}

				$this->notification::add_notification( [
					'message' => $message,
					'link_url' => $plugin_admin_url . '#scan',
					'link_anchor_text' => __( 'View Scan Details', $plugin_slug ),
					'alert_level' => 'success',
				] );
			}
		}

		// Clear Status Code Cache
		delete_option( $this->cache_status_codes_option );

	}

	/**
	 * Determine if we can actually start this scan IF process is not running and queue is empty
	 *
	 * @package WhereUsed\HelpersLibrary
	 * @since   1.3.0
	 *
	 * @return bool
	 */
	public function can_we_start(): bool {

		$return = true;

		$plugin_slug = static::get_constant_value( 'SLUG' );

		if ( $this->is_process_running() ) {

			$this->debug::log( __( 'Cannot start a scan due to current running process. Try again later.', $plugin_slug ) );
			$return = false;

		} elseif ( ! $this->is_queue_empty() ) {

			$this->debug::log( __( 'Cannot start a scan due to queue not empty.', $plugin_slug ), 'error' );
			$return = false;

		}

		return $return;
	}

	/**
	 * Tries to start a scan
	 *
	 * @package WhereUsed\HelpersLibrary
	 * @since   1.3.0
	 *
	 * @param string $type
	 * @param bool   $force
	 *
	 * @return void
	 * @throws \ReflectionException
	 */
	public function try_to_start( string $type = '', bool $force = false ): void {

		$type = $type ?: REQUEST::key( 'type' );

		$current_user_id = get_current_user_id();
		$settings = static::get_class( 'Settings' )::get_current_settings();
		$plugin_slug = $this::get_constant_value( 'SLUG' );

		if ( ! in_array( $type, $this->get_scan_types() ) ) {

			$response = $plugin_slug . ' - ' . __( 'Invalid scan type.', $plugin_slug );

			$this->debug::log( $response, 'error' );
			error_log( $response );

			if ( $force ) {
				echo esc_html( $response );
			} else {
				wp_send_json( [ 'html' => $response ], 403 );
			}

		} elseif ( ! $force && ! wp_verify_nonce( REQUEST::text_field( 'nonce' ), $plugin_slug . '-start-scan-' . $current_user_id ) ) {

			// Require nonce specific to this user

			$response = $plugin_slug . ' - ' . __( 'Valid nonce required to start full scan.', $plugin_slug );

			$this->debug::log( $response, 'error' );
			error_log( $response );

			if ( $force ) {
				echo esc_html( $response );
			} else {
				wp_send_json( [ 'html' => $response ], 401 );
			}

		} elseif ( ! $force && ! $settings->can_user_access_settings() ) {

			// Only users who have access to settings can initiate a scan

			$response = $plugin_slug . ' - ' . __( 'User must have ability to access settings to start a scan.', $plugin_slug );

			$this->debug::log( $response, 'error' );
			error_log( $response );

			wp_send_json( [ 'html' => $response ], 401 );

		} elseif ( $this->can_we_start() ) {

			$this->debug::log( 'Starting scan' );

			// start scan
			$this->start( $type );

		} else {

			// Already a scan running somewhere

			$sites = Get::sites();

			if ( is_multisite() ) {

				foreach ( $sites as $site ) {

					switch_to_blog( $site->blog_id );

					if ( is_plugin_active( $this::get_constant_value( 'PLUGIN' ) ) ) {

						// Get scan settings
						$scan = $this->scan::get_current( true );

						if ( $scan->get( 'start_date' ) && ! $scan->get( 'end_date' ) ) {

							// We found the scan that is running
							break;
						}

					}

					restore_current_blog();
				}

			} else {

				// Single Site
				$site = $sites[0];

				// Get scan settings
				$scan = $this->scan;

			}

			if ( $scan->get( 'start_date' ) ) {
				// We have a scan running

				$started_by = $scan->get_started_by();

				$done = $scan->get( 'progress' );
				$total = $scan->get( 'progress_total' );
				$percent = ( $total ) ? round( ( $done / $total ) * 100, 1 ) : 0;

				ob_start();

				if ( is_multisite() ) {

					$message = sprintf( __( 'There is already a scan running on %s. Please wait until it is finished or cancel the scan.', $plugin_slug ), $site->domain );

					echo '<p>' . esc_html( $message ) . '</p>';
				} else {

					$message = __( 'There is already a scan running. Please wait until it is finished or cancel the scan.', $plugin_slug );

					echo '<p>' . esc_html( $message ) . '</p>';
				}

				$this->debug::log( $message, 'warning' );

				echo '<h3>' . esc_html__( 'Scan Details', $plugin_slug ) . '</h3>';
				echo '<ul>';

				if ( is_multisite() ) {
					echo '<li><b>' . esc_html__( 'Site', $plugin_slug ) . ':</b> ' . esc_html( $site->domain ) . '</li>';
				}

				echo '<li><b>' . esc_html__( 'Type', $plugin_slug ) . ':</b> ' . esc_html( $scan->get( 'type' ) ) . '</li>';
				echo '<li><b>' . esc_html__( 'Start Date', $plugin_slug ) . ':</b> ' . esc_html( $scan->get( 'start_date' ) ) . '</li>';
				echo '<li><b>' . esc_html__( 'Started By', $plugin_slug ) . ':</b> ' . esc_html( $started_by ) . '</li>';
				echo '<li><b>' . esc_html__( 'Progress', $plugin_slug ) . ':</b> ' . esc_html( $percent ) . '%</li>';
				echo '</ul>';

				if ( $force ) {
					echo ob_get_clean();
				} else {
					wp_send_json( [ 'html' => ob_get_clean() ] );
				}

			} else {
				// Attempt to start again

				// Delete all batches bc we know there isn't a scan actually running
				$this->delete_all_scan_batches();

				// Clear queue
				$this->delete();

				if ( $this->can_we_start() ) {

					// start scan
					$this->start( $type );

				} else {

					// Unknown scenario

					$message = __( 'This should never happen. If you are seeing this error, please contact the developers so we can fix this.', $plugin_slug );

					$this->debug::log( $message, 'error' );

					echo '<p>' . esc_html( $message ) . '</p>';

					if ( $force ) {
						echo ob_get_clean();
					} else {
						wp_send_json( [ 'html' => ob_get_clean() ] );
					}

				}
			}

		}

	}

	/**
	 * Memory exceeded
	 *
	 * Ensures the batch process never exceeds 90%
	 * of the maximum WordPress memory.
	 *
	 * @package WhereUsed\HelpersLibrary
	 * @since   1.3.0
	 *
	 * @return bool
	 */
	protected function memory_exceeded(): bool {

		if ( ! $this->memory_exceeded ) {
			$memory_limit = $this->get_memory_limit() * 0.8; // 80% of max memory
			$current_memory = memory_get_usage( true );

			if ( $current_memory >= $memory_limit ) {
				$this->memory_exceeded = true;

				$message = sprintf( __( 'The %s scan ran out of memory resources (%s of %s). We will attempt the scan again in 1 minute', static::get_constant_value( 'SLUG' ) ), $this->scan->get( 'type' ), $current_memory, $memory_limit );

				$this->debug::log( $message, 'warning' );

				$this->notification::add_notification( [
					'message' => $message,
					'alert_level' => 'warning',
				] );
			}
		}

		return $this->memory_exceeded;
	}

	/**
	 * Time exceeded.
	 *
	 * Ensures the batch never exceeds a sensible time limit.
	 * A timeout limit of 30s is common on shared hosting.
	 *
	 * @package WhereUsed\HelpersLibrary
	 * @since   1.3.0
	 *
	 * @return bool
	 */
	protected function time_exceeded(): bool {

		if ( ! $this->time_exceeded ) {
			$finish = $this->start_time + $this->get_time_limit();

			if ( time() >= $finish ) {
				$this->debug::log( __( 'Time limit exceeded...pausing process. Process will continue in a few minutes.', static::get_constant_value( 'SLUG' ) ) );
				$this->time_exceeded = true;
			}
		}

		return apply_filters( $this->identifier . '_time_exceeded', $this->time_exceeded );
	}

	/**
	 * The number of seconds that the scan can run before pausing.
	 *
	 * @note    Warning: The default value is 20 seconds to accommodate shared hosting. Any modification of this number can lead to higher CPU usage. Change at your own risk.
	 *
	 * @package WhereUsed\HelpersLibrary
	 * @since   1.1.0
	 *
	 * @return int Time limit in seconds
	 */
	public function get_time_limit(): int {

		return apply_filters( $this->identifier . '_default_time_limit', 20 );

	}

	/**
	 * Get memory limit
	 *
	 * @package WhereUsed\HelpersLibrary
	 * @since   1.3.0
	 *
	 * @return int
	 */
	protected function get_memory_limit(): int {

		if ( defined( 'WP_MEMORY_LIMIT' ) ) {

			$memory_limit = WP_MEMORY_LIMIT;
			$this->debug::log( 'MemoryLIMIT WP_MEMORY_LIMIT - ' . $memory_limit );

		} elseif ( function_exists( 'ini_get' ) ) {
			$memory_limit = ini_get( 'memory_limit' );
			$this->debug::log( 'MemoryLIMIT ini_get - ' . $memory_limit );
		} else {
			// Sensible default.
			$memory_limit = '64M';
			$this->debug::log( 'Memory LIMIT default - ' . $memory_limit );
		}

		if ( ! $memory_limit || - 1 === intval( $memory_limit ) ) {
			// Unlimited, set to 16GB.
			$memory_limit = '16000M';
			$this->debug::log( 'Memory LIMIT MAX - ' . $memory_limit );
		}

		return wp_convert_hr_to_bytes( $memory_limit );
	}

	/**
	 * Returns the identifier property
	 *
	 * @package WhereUsed\HelpersLibrary
	 * @since   1.1.0
	 *
	 * @return string
	 */
	public function get_identifier(): string {

		return $this->identifier;

	}

	/**
	 * Push to queue
	 *
	 * @package WhereUsed\HelpersLibrary
	 * @since   1.2.0
	 *
	 * @param string $sql
	 * @param string $type
	 *
	 * @return int
	 */
	public function push_to_queue( string $sql, string $type ): int {

		//$this->debug::log( 'SQL: ' . $sql );

		$mysqli = static::get_class( 'Get' )::db_connection();
		$result = $mysqli->query( $sql );

		// Count how many results were found
		$count = 0;

		if ( $result ) {
			$file_path = $this->get_queue_file_path( $type );
			$fh = fopen( $file_path, 'w' );

			if ( $fh === false ) {
				echo( 'Could not open file: ' . $file_path );
			} else {
				++ $count;

				// Add prepend flag
				fwrite( $fh, $this->get_prepend_flag() );

				while ( $row = $result->fetch_object() ) {
					++ $count;

					// An ID or URL
					$queue = $row->queue ?? '';

					if ( $queue ) {
						$description = $row->description ?? '';

						if ( 'users' != $type && $description ) {
							// Adds more context to what is getting scanned
							$queue .= '|' . $description;
						}
						fwrite( $fh, "\n" . $queue . '~' );
					}
				}

				// Close the file handle; when you are done using
				if ( fclose( $fh ) === false ) {
					$this->debug::log( 'Could not close file: ' . $file_path );
				}
			}

			// Clear mysqli memory
			$result->free_result();

		}

		return $count;

	}

	/**
	 * Get query args
	 *
	 * @return array
	 */
	protected function get_query_args() {
		if ( property_exists( $this, 'query_args' ) ) {
			return $this->query_args;
		}

		$args = [
			'action' => $this->identifier,
			'nonce' => wp_create_nonce( $this->identifier ),
		];

		/**
		 * Filters the post arguments during an async request.
		 *
		 * @param array $url
		 */
		return apply_filters( $this->identifier . '_query_args', $args );
	}

	/**
	 * Get post args
	 *
	 * @return array
	 */
	protected function get_post_args() {
		if ( property_exists( $this, 'post_args' ) ) {
			return $this->post_args;
		}

		$args = [
			'timeout' => 0.01,
			'blocking' => false,
			'body' => '',
			'cookies' => $_COOKIE,
			'sslverify' => apply_filters( 'https_local_ssl_verify', false ),
		];

		/**
		 * Filters the post arguments during an async request.
		 *
		 * @param array $args
		 */
		return apply_filters( $this->identifier . '_post_args', $args );
	}

	/**
	 * Generate key
	 *
	 * Generates a unique key based on microtime. Queue items are
	 * given a unique key so that they can be merged upon save.
	 *
	 * @param int $length Length.
	 *
	 * @return string
	 */
	protected function generate_key( $length = 64 ) {
		$unique = md5( microtime() . rand() );
		$prepend = $this->identifier . '_batch_';

		return substr( $prepend . $unique, 0, $length );
	}

	/**
	 * Maybe process queue
	 *
	 * Checks whether data exists within the queue and that
	 * the process is not already running.
	 */
	public function maybe_handle() {

		// Don't lock up other requests while processing
		session_write_close();

		$this->debug::log( 'Attempting to start background scan process.' );

		if ( $this->is_process_running() ) {
			// Background process already running.
			$this->debug::log( 'Process already running. Abort.' );
			wp_die();
		}

		if ( $this->is_queue_empty() ) {
			// No data to process.
			$this->debug::log( 'Queue empty. Abort.' );
			wp_die();
		}

		check_ajax_referer( $this->identifier, 'nonce' );

		$this->debug::log( 'Memory - (' . __LINE__ . '): ' . round( memory_get_usage() / 1024 / 1024, 2 ) . 'MB' );

		$this->handle();

		wp_die();
	}

	/**
	 * Is process running
	 *
	 * Check whether the current process is already running
	 * in a background process.
	 */
	protected function is_process_running() {
		if ( get_site_transient( $this->identifier . '_process_lock' ) ) {
			// Process already running.
			return true;
		}

		return false;
	}

	/**
	 * Lock process
	 *
	 * Lock the process so that multiple instances can't run simultaneously.
	 * Override if applicable, but the duration should be greater than that
	 * defined in the time_exceeded() method.
	 */
	protected function lock_process() {
		$this->start_time = time(); // Set start time of current process.

		$lock_duration = ( property_exists( $this, 'queue_lock_time' ) ) ? $this->queue_lock_time : 60; // 1 minute
		$lock_duration = apply_filters( $this->identifier . '_queue_lock_time', $lock_duration );

		set_site_transient( $this->identifier . '_process_lock', microtime(), $lock_duration );
	}

	/**
	 * Unlock process
	 *
	 * Unlock the process so that other instances can spawn.
	 *
	 * @return $this
	 */
	protected function unlock_process() {
		delete_site_transient( $this->identifier . '_process_lock' );

		return $this;
	}

	/**
	 * Cancel Process
	 *
	 * Stop processing queue items, clear cronjob and delete batch.
	 *
	 */
	public function cancel_process() {
		if ( ! $this->is_queue_empty() ) {

			$this->delete();

			wp_clear_scheduled_hook( $this->cron_hook_identifier );
		}

	}

	/**
	 * Cancels the current scan running in the background
	 *
	 * @return void
	 */
	public function cancel(): void {

		$plugin_slug = static::get_constant_value( 'SLUG' );

		// Require nonce specific to this user
		if ( ! wp_verify_nonce( REQUEST::text_field( 'nonce' ), $plugin_slug . '-cancel-scan-' . get_current_user_id() ) ) {

			$response = __( 'Valid nonce required to start full scan.', $plugin_slug );
			$this->debug::log( $response, 'error' );

			wp_send_json( [ 'html' => $response ], 401 );

		} else {

			ob_start();

			$this->scan = $this->scan::get_current( true );

			$type = ucwords( str_replace( '-', ' ', $this->scan->get( 'type' ) ) );

			$notes = sprintf( __( '%s was manually cancelled.', $plugin_slug ), $type );
			$this->scan::cancel( $notes );

			$this->debug::log( $notes );

			echo '<p>' . esc_html( sprintf( __( '%s was manually cancelled. This dashboard will refresh in 3 seconds.' ), $type ) ) . '</p>';
			echo '<script>setTimeout(function () { window.location.href = "' . esc_url( static::get_constant_value( 'ADMIN_URL' ) ) . '";}, 3000);</script>';

			wp_send_json( [ 'html' => ob_get_clean() ] );

		}

	}

	/**
	 * Displays the progress bar for the current scan
	 *
	 * @return void
	 */
	final public static function display_progress_bar(): void {

		$progress_only = REQUEST::bool( 'progress_only' );
		$plugin_slug = static::get_constant_value( 'SLUG' );

		$scan = static::get_class( 'Scan' )::get_current( true );
		$type = str_replace( '-', ' ', $scan->get( 'type' ) );
		$start_date = $scan->get( 'start_date' );
		$end_date = $scan->get( 'end_date' );
		$done = $scan->get( 'progress' );
		$total = $scan->get( 'progress_total' );
		$remaining = $total - $done;
		$percent = ( $total ) ? round( ( $done / $total ) * 100, 1 ) : 0;
		$currently = $scan->get( 'currently' );

		if ( $progress_only ) {
			// We are going to respond with JSON

			wp_send_json( [
				'done' => (float) $done,
				// decimal
				'total' => (int) $total,
				//int
				'remaining' => (int) $remaining,
				//int
				'percent' => (float) $percent,
				// decimal
				'startDate' => esc_html( $start_date ),
				//string
				'endDate' => esc_html( $end_date ),
				//string
				'currently' => esc_html( $currently ),
				// string
			] );

		} else {

			if ( ! $currently ) {
				$currently = '...';
			}

			echo '<p class="scan-message" style="text-align: center;">' . esc_html( sprintf( __( 'A %s is running. This scan runs in the background, so you can go do other things.', $plugin_slug ), $type ) ) . '</p>';
			echo '<div id="progress-bar">
                <div class="current-progress" style="width:' . esc_attr( $percent ) . '%;"></div>
                <span class="dashicons spin dashicons-update"></span>
                <span class="text">' . __( 'Scanning' ) . ' <span class="currently">' . esc_html( $currently ) . '</span> - <span class="percent">' . esc_html( $percent ) . '%</span></span>
            </div>

			<p style="text-align: center;"><a href="#scan" id="cancel-scan" data-nonce="' . esc_attr( wp_create_nonce( $plugin_slug . '-cancel-scan-' . get_current_user_id() ) ) . '">' . esc_html__( 'Cancel Scan', $plugin_slug ) . '</a></p>';

		}

	}

	/**
	 * Dispatch the async request
	 *
	 * @access public
	 * @return array|WP_Error
	 */
	public function dispatch() {
		// Schedule the cron healthcheck.
		$this->schedule_event();

		// Perform remote post.
		$url = add_query_arg( $this->get_query_args(), $this->get_query_url() );
		$args = $this->get_post_args();

		return wp_remote_post( esc_url_raw( $url ), $args );
	}

	/**
	 * Schedule event
	 */
	protected function schedule_event() {
		if ( ! wp_next_scheduled( $this->cron_hook_identifier ) ) {
			wp_schedule_event( time(), $this->cron_interval_identifier, $this->cron_hook_identifier );
		}
	}

	/**
	 * Clear scheduled event
	 */
	protected function clear_scheduled_event() {
		$timestamp = wp_next_scheduled( $this->cron_hook_identifier );

		if ( $timestamp ) {
			wp_unschedule_event( $timestamp, $this->cron_hook_identifier );
		}
	}

	/**
	 * Schedule cron healthcheck
	 *
	 * @access public
	 *
	 * @param mixed $schedules Schedules.
	 *
	 * @return mixed
	 */
	public function schedule_cron_healthcheck( $schedules ) {
		$interval = $this->get_cron_interval();

		if ( property_exists( $this, 'cron_interval' ) ) {
			$interval = apply_filters( $this->identifier . '_cron_interval', $this->cron_interval );
		}

		// Adds every X minutes to the existing schedules.
		$schedules[ $this->identifier . '_cron_interval' ] = [
			'interval' => MINUTE_IN_SECONDS * $interval,
			'display' => sprintf( __( 'Every %d Minutes' ), $interval ),
		];

		return $schedules;
	}

	/**
	 * Handle cron healthcheck
	 *
	 * Restart the background process if not already running
	 * and data exists in the queue.
	 */
	public function handle_cron_healthcheck() {

		$this->debug::log( 'handle_cron_healthcheck' );

		if ( $this->is_queue_empty() ) {
			// No data to process.
			$this->clear_scheduled_event();
			exit;
		}

		$this->debug::log( 'restarting background process' );

		$this->dispatch();

		exit;

	}

	/**
	 * Adds an entry at the beginning of the array to serve as a flag. This flag allows the progress bar to know quickly that the scan is working.
	 *
	 * @param $array
	 *
	 * @return string
	 */
	protected function get_prepend_flag(): string {

		return '_update_progress~';

	}

	/**
	 * Starts the scan
	 *
	 * @param string $type
	 * @param bool   $return
	 *
	 * @return void
	 *
	 */
	public function start( string $type = 'full-scan', bool $return = true ): void {

		$total_count = 0;
		$response = '';

		$plugin_slug = static::get_constant_value( 'SLUG' );

		if ( ! in_array( $type, $this->get_scan_types() ) ) {
			die( 'Invalid Scan Type' );
		}

		if ( 'full-scan' === $type ) {

			// Run Full Scan

			$this->debug::log( __( 'Starting Full Scan', $plugin_slug ) );

			$settings = static::get_class( 'Settings' )::get_current_settings( true );

			$post_types = $settings->get( 'scan_post_types' );

			if ( ! empty( $post_types ) ) {
				// Yep, we need to scan defined post types
				$total_count += $this->queue( 'posts', $post_types );
			}

			$taxonomies = $settings->get( 'scan_taxonomies' );

			if ( ! empty( $taxonomies ) ) {
				// Yep, need to scan taxonomies
				$total_count += $this->queue( 'terms', $taxonomies );
			}

			if ( $settings->get( 'scan_users' ) ) {
				// Yep, we need to scan users
				$total_count += $this->queue( 'users' );
			}

			if ( $settings->get( 'scan_menus' ) ) {
				// Yep, need to scan menus
				$total_count += $this->queue( 'menus' );
			}

			// Make sure we have things queued, so we know to clear the DB and start the background process
			if ( $total_count ) {

				// Clear out DB
				static::get_class( 'Run' )::purge_table( static::get_class( 'Get' )::table() );

				$response = __( 'A full scan has started.', $plugin_slug );

				// Tell the user the scan has started via notifications
				$this->notification::add_notification( [
					'message' => $response,
					'link_url' => static::get_constant_value( 'ADMIN_URL' ),
					'link_anchor_text' => __( 'View Scan Progress', $plugin_slug ),
					'alert_level' => 'notice',
				] );

			} else {

				$response = __( 'A full scan has failed. We could not find any posts to scan that match your settings.', static::get_constant_value( 'SLUG' ) );

				// Tell the user that the scan failed via notifications
				$this->notification::add_notification( [
					'message' => $response,
					'link_url' => static::get_constant_value( 'SETTINGS_URL' ),
					'link_anchor_text' => __( 'Check Settings', $plugin_slug ),
					'alert_level' => 'error',
				] );

				error_log( $plugin_slug . ' - ' . $response );

			}

		}

		if ( $total_count ) {

			// Get scan settings
			$scan = $this->scan::get_current( true );

			// Reset scan details to ensure the data is clean and not inherited from previous scans
			$scan->reset();

			$scan->set_type( $type );

			// Mark the scan as no longer needed
			$scan->set_needed( false );

			$user_id = ( wp_doing_cron() ) ? - 1 : get_current_user_id();

			$scan->set_started( $user_id );

			// Start date and time
			$scan->set_start_date();

			// Update the total to be scanned
			$scan->set_progress_total( $total_count );
			$scan->save();

			// clear cache for status codes
			delete_option( $this->cache_status_codes_option );

			$this->debug::log( __( 'Dispatching background scan process', $plugin_slug ) );

			// Run the background process
			$this->dispatch();

		}

		if ( ! wp_doing_cron() ) {
			if ( $return ) {
				// Return progress bar to the user

				ob_start();
				static::display_progress_bar();
				$response = ob_get_clean();
			}

			// Default return a response
			wp_send_json( [ 'html' => $response ] );
		}

		// WP_Cron: Safety net to prevent anything else from loading
		exit;

	}

	/**
	 * Queues the specified type
	 *
	 * @return int
	 */
	public function queue( string $type, array $data = [] ): int {

		global $wpdb;

		$values = [];
		$placeholders = [];
		$excluded = [];

		// Set the table
		if ( 'posts' == $type ) {
			$values[] = $wpdb->prefix . 'posts';
			$excluded = static::get_class( 'Get' )::excluded_post_types();
		} elseif ( 'terms' == $type ) {
			$values[] = $wpdb->prefix . 'term_taxonomy';
		} elseif ( 'users' == $type ) {
			$values[] = $wpdb->prefix . 'users';
		} elseif ( 'menus' == $type ) {
			$values[] = $wpdb->prefix . 'term_taxonomy';
		}

		// Build placeholders
		if ( ! empty( $data ) ) {
			// Add single quotes around values
			foreach ( $data as $key => $value ) {
				if ( empty( $excluded ) || ! in_array( $value, $excluded ) ) {
					// not excluded
					$values[] = $value;
					$placeholders[] = '"%' . count( $values ) . '$s"'; // Example: "%1$s"
				}
			}
		}

		// Set the SQL query
		if ( 'posts' == $type ) {
			$sql = $wpdb->prepare( 'SELECT `ID` as `queue`, `post_type` as `description` FROM `%1$s` 
				WHERE `post_type` IN ( ' . implode( ',', $placeholders ) . ' )
				ORDER BY `description`, `queue` ASC;', $values );
		} elseif ( 'terms' == $type ) {
			$sql = $wpdb->prepare( 'SELECT `term_id` as `queue`, `taxonomy` as `description` FROM `%1$s` 
				WHERE `taxonomy` IN ( ' . implode( ',', $placeholders ) . ' ) 
				ORDER BY `description`, `queue` ASC;', $values );
		} elseif ( 'users' == $type ) {
			$sql = $wpdb->prepare( 'SELECT `ID` as `queue`, `user_login` as `description` FROM `%1$s` ORDER BY `description`, `queue` ASC;', $values );
		} elseif ( 'menus' == $type ) {
			$sql = $wpdb->prepare( 'SELECT `term_id` as `queue`, `taxonomy` as `description` FROM `%1$s` 
				WHERE `taxonomy` LIKE "nav_menu" 
				ORDER BY `queue` ASC;', $values );
		}

		// Queue user IDs and update total count
		return $this->push_to_queue( $sql, $type );

	}


	/**
	 * Get query URL
	 *
	 * @return string
	 */
	protected function get_query_url() {
		if ( property_exists( $this, 'query_url' ) ) {
			return $this->query_url;
		}

		$url = admin_url( 'admin-ajax.php' );

		/**
		 * Filters the post arguments during an async request.
		 *
		 * @param string $url
		 */
		return apply_filters( $this->identifier . '_query_url', $url );
	}

	/**
	 * Deletes all scan batches on the current site
	 *
	 * @return void
	 */
	public function delete_all_scan_batches(): void {

		global $wpdb;

		$this->debug::log( __( 'Deleting all scan batches.', static::get_constant_value( 'SLUG' ) ) );

		$table = $wpdb->options;
		$column = 'option_name';

		if ( is_multisite() ) {
			$table = $wpdb->sitemeta;
			$column = 'meta_key';
		}

		$values = [];
		$values[] = $table;
		$values[] = $column;
		$values[] = $this->get_identifier() . '_batch_%';

		// Delete all
		$wpdb->query( $wpdb->prepare( '
			DELETE
			FROM `%1$s`
			WHERE `%2$s` LIKE "%3$s"
		', $values ) );

		// Delete scan background process transient
		delete_site_transient( $this->get_identifier() . '_process_lock' );

	}

}