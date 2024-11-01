<?php

namespace WhereUsed\HelpersLibrary;

// Prevent Direct Access
( defined( 'ABSPATH' ) ) || die;

/**
 * Class Scan
 *
 * @package WhereUsed\HelpersLibrary
 * @since   1.1.0
 *
 * @TODO    : make scanner check inside of certain blocks for other blocks like
 */
abstract class Scan extends Base {

	// Scan Details - only available to Settings()
	protected bool $needed = true; // Is turned to false after a full scan is complete
	protected string $type = ''; // full-scan or status-check
	protected string $start_date = ''; // Date when the full scan started
	protected string $end_date = ''; // Date when the full scan started
	protected string $currently = ''; // What is currently being scanned?
	protected int $progress = 0; // Number of posts scanned
	protected int $progress_total = 0;  // Total amount of items being scanned
	protected int $cancelled = 0; // user ID of the user that cancelled the scan
	protected int $started = 0; // user ID of user who started scan or -1 for wp_cron
	protected array $history = []; // List of historical scans
	protected string $notes = ''; // Details about the scan

	function __construct( $data = [] ) {

		if ( empty( $data ) ) {
			// Clear cache
			wp_cache_delete( static::get_constant_value( 'SCAN_OPTION' ), 'options' );

			// Load the data from the database
			parent::__construct( get_option( static::get_constant_value( 'SCAN_OPTION' ) ) );
		} else {
			// Load provided data
			parent::__construct( $data );
		}

	}

	/**
	 * Registers all hooks needed by the plugin
	 *
	 * @package WhereUsed\HelpersLibrary
	 * @since   1.1.0
	 *
	 * @return void
	 */
	abstract public static function init(): void;

	/**
	 * Displays the start scan button
	 *
	 * @package WhereUsed\HelpersLibrary
	 * @since   1.1.0
	 *
	 * @param string $class
	 * @param array  $scan_types
	 */
	final public static function display_start_scan_button( string $class = 'initial', array $scan_types = [] ): void {

		$settings = static::get_class( 'Settings' )::get_current_settings();

		if ( $settings->can_user_access_settings() ) {
			$current_user_id = get_current_user_id();
			$nonce = wp_create_nonce( static::get_constant_value( 'SLUG' ) . '-start-scan-' . $current_user_id );

			$scan = static::get_current();
			$scan_types = empty( $scan_types ) ? [
				'full-scan',
				'check-status',
			] : $scan_types;

			$needed = $scan->get( 'needed' );
			$text = ( 'initial' == $class ) ? __( 'Initial Scan', static::get_constant_value( 'SLUG' ) ) : __( 'Run Full Scan', static::get_constant_value( 'SLUG' ) );

			if ( 'initial' == $class ) {
				$class .= ( $needed ) ? ' scan-needed' : '';

				if ( in_array( 'full-scan', $scan_types ) ) {
					echo '<a href="#scan" data-type="full-scan" id="scan-button" class="scan-link ' . esc_attr( $class ) . '" data-nonce="' . esc_attr( $nonce ) . '"><span class="inner-button"><span class="start">' . esc_html__( 'Start', static::get_constant_value( 'SLUG' ) ) . '</span>' . esc_html( $text ) . '</span></a>';
				}
				echo '<p style="text-align: center"><span class="warning-text">' . esc_html__( 'Warning:', static::get_constant_value( 'SLUG' ) ) . '</span> ' . esc_html__( 'Please review the scan settings before you start an initial scan. Reference data is only accurate if a full scan is completed.', static::get_constant_value( 'SLUG' ) ) . '</p>';
			} else {
				echo '<p style="text-align:center;">';

				if ( in_array( 'full-scan', $scan_types ) ) {
					echo '<a href="#scan" data-type="full-scan" class="scan-link" data-nonce="' . esc_attr( $nonce ) . '">' . esc_html( $text ) . '</a>';
				}

				if ( in_array( 'check-status', $scan_types ) && static::has_full_scan_ran() ) {
					echo ' | ';
					echo '<a href="#" data-type="check-status" class="scan-link" data-nonce="' . esc_attr( $nonce ) . '">' . esc_html__( 'Check Status Codes', static::get_constant_value( 'SLUG' ) ) . '</a>';
				}

				echo '</p>';

				echo '<p style="text-align: center"><span class="notice-text">' . esc_html__( 'Notice:', static::get_constant_value( 'SLUG' ) ) . '</span> ' . esc_html__( 'Running a new scan may take a few minutes depending on your settings. Reference data is only accurate if a full scan is completed.', static::get_constant_value( 'SLUG' ) ) . '</p>';
			}

		}

	}

	/**
	 * Gets the current scan details
	 *
	 * @package WhereUsed\HelpersLibrary
	 * @since   1.1.0
	 *
	 * @param bool $from_db
	 *
	 * @return Scan
	 */
	final public static function get_current( bool $from_db = false ): Scan {

		$plugin_global = static::get_constant_value( 'GLOBAL' );

		// Grab plugin's global
		global $$plugin_global;

		if ( $from_db || empty( $$plugin_global['scan'] ) ) {
			$scan_class = static::get_class( 'Scan' );
			$$plugin_global['scan'] = new $scan_class();
		}

		return $$plugin_global['scan'];
	}

	/**
	 * Display the lastest scan stats
	 *
	 * @package WhereUsed\HelpersLibrary
	 * @since   1.1.0
	 *
	 * @return void
	 */
	final public static function display_scan_stats(): void {

		$scan = static::get_current();

		$scan_start_date = $scan->get( 'start_date' );
		$scan_end_date = $scan->get( 'end_date' );

		// Duration
		$start = date_create( $scan_start_date );
		$end = date_create( $scan_end_date );
		$difference = date_diff( $start, $end );
		$scan_duration_days = (int) $difference->format( '%d' );
		$scan_duration_hours = (int) $difference->format( '%h' );
		$scan_duration_minutes = (int) $difference->format( '%i' );
		$scan_duration_seconds = (int) $difference->format( '%s' );

		$scan_duration = ($scan_duration_days) ? $scan_duration_days . ' '. __( 'Days', static::get_constant_value( 'SLUG' ) ) . ' ' : '';
		$scan_duration .= ($scan_duration_hours) ? $scan_duration_hours . ' '. __( 'Hours', static::get_constant_value( 'SLUG' ) ) . ' ' : '';
		$scan_duration .= ($scan_duration_minutes) ? $scan_duration_minutes . ' '. __( 'Minutes', static::get_constant_value( 'SLUG' ) ) . ' ' : '';
		$scan_duration .= ($scan_duration_seconds) ? $scan_duration_seconds . ' '. __( 'Seconds', static::get_constant_value( 'SLUG' ) ) . ' ' : '';

		$cancelled = ( $scan->get( 'cancelled' ) ) ? __( 'Yes', static::get_constant_value( 'SLUG' ) ) : __( 'No', static::get_constant_value( 'SLUG' ) );

		echo '<h3>' . esc_html__( 'The last scan details:', static::get_constant_value( 'SLUG' ) ) . '</h3>
		<ul>
		<li><b>' . esc_html__( 'Type', static::get_constant_value( 'SLUG' ) ) . ':</b> ' . esc_html( $scan->get( 'type' ) ) . '</li>
		<li><b>' . esc_html__( 'Started By', static::get_constant_value( 'SLUG' ) ) . ':</b> ' . esc_html( $scan->get_started_by() ) . '</li>
		<li><b>' . esc_html__( 'Start Date', static::get_constant_value( 'SLUG' ) ) . ':</b> ' . esc_html( $scan_start_date ) . '</li>
		<li><b>' . esc_html__( 'End Date', static::get_constant_value( 'SLUG' ) ) . ':</b> ' . esc_html( $scan_end_date ) . '</li>';

		if ( $scan->get( 'type' ) == 'check-status'){
			echo '<li><b>' . esc_html__( 'Total Unique URLs Checked', static::get_constant_value( 'SLUG' ) ) . ':</b> ' . esc_html( $scan->get( 'progress' ) ) . '</li>';
		} else {
			echo '<li><b>' . esc_html__( 'Total Posts Scanned', static::get_constant_value( 'SLUG' ) ) . ':</b> ' . esc_html( $scan->get( 'progress' ) ) . '</li>';
		}

		echo '<li><b>' . esc_html__( 'Total Duration', static::get_constant_value( 'SLUG' ) ) . ':</b> ' . esc_html( $scan_duration ) . '</li>
		<li><b>' . esc_html__( 'Cancelled', static::get_constant_value( 'SLUG' ) ) . ':</b> ' . esc_html( $cancelled ) . '</li>';

		if ( $scan->get( 'cancelled' ) ) {
			echo '<li><b>' . esc_html__( 'Cancelled By', static::get_constant_value( 'SLUG' ) ) . ':</b> ' . esc_html( $scan->get_cancelled_by() ) . '</li>';
		}

		echo '</ul> ';
	}

	/**
	 * Converts the started ID into a display name
	 *
	 * @package WhereUsed\HelpersLibrary
	 * @since   1.1.0
	 *
	 * @return string
	 */
	final public function get_started_by(): string {

		$id = $this->get( 'started' );

		return Get::convert_id_to_name( $id );

	}

	/**
	 * Converts the cancelled ID into a display name
	 *
	 * @package WhereUsed\HelpersLibrary
	 * @since   1.1.0
	 *
	 * @return string
	 */
	final public function get_cancelled_by(): string {

		$id = $this->get( 'cancelled' );

		return Get::convert_id_to_name( $id );

	}

	/**
	 * Set scan_start_date from outside the method
	 *
	 * @package WhereUsed\HelpersLibrary
	 * @since   1.1.0
	 *
	 */
	final public function set_start_date(): void {

		$this->set( 'start_date', wp_date( 'Y-m-d H:i:s' ) );

	}

	/**
	 * Set scan_end_date from outside the method
	 *
	 * @package WhereUsed\HelpersLibrary
	 * @since   1.1.0
	 *
	 * @return void
	 */
	final public function set_end_date(): void {

		$this->set( 'end_date', wp_date( 'Y-m-d H:i:s' ) );

	}

	/**
	 * Sets whether a scan is needed
	 *
	 * @package WhereUsed\HelpersLibrary
	 * @since   1.1.0
	 *
	 * @param bool $value
	 *
	 * @return void
	 */
	final public function set_needed( bool $value ): void {

		$this->set( 'needed', $value );

	}

	/**
	 * Sets scan type
	 *
	 * @package WhereUsed\HelpersLibrary
	 * @since   1.1.0
	 *
	 * @param string $value
	 *
	 * @return void
	 */
	final public function set_type( string $value ): void {

		$this->set( 'type', $value );

	}

	/**
	 * Sets the scan as cancelled by current user
	 *
	 * @package WhereUsed\HelpersLibrary
	 * @since   1.1.0
	 *
	 * @return void
	 */
	final public function set_cancelled(): void {

		$this->set( 'cancelled', get_current_user_id() );

	}

	/**
	 * Adds a message to the notes property
	 *
	 * @package WhereUsed\HelpersLibrary
	 * @since   1.1.0
	 *
	 * @param string $notes
	 *
	 * @return void
	 */
	final public function set_notes( string $notes = '' ): void {

		$this->set( 'notes', $this->notes . ' ' . $notes );

	}

	/**
	 * Updates progress
	 *
	 * @package WhereUsed\HelpersLibrary
	 * @since   1.1.0
	 *
	 * @param int $count
	 *
	 * @return void
	 */
	final public function update_progress( int $count ): void {

		$total = $this->get( 'progress_total' );
		$progress = $total - $count;
		$this->set_progress( $progress );
		$this->save();

	}

	/**
	 * Sets progress count
	 *
	 * @package WhereUsed\HelpersLibrary
	 * @since   1.1.0
	 *
	 * @return void
	 */
	public function set_progress( int $value ): void {

		$this->set( 'progress', $value );

	}

	/**
	 * Saves the scan details as an array in the database
	 *
	 * @package WhereUsed\HelpersLibrary
	 * @since   1.1.0
	 *
	 * @return bool
	 */
	public function save(): bool {

		$plugin_global = static::get_constant_value( 'GLOBAL' );

		// Grab plugin's global
		global $$plugin_global;

		$array = [];

		// Update the local cache of the scan
		$$plugin_global['scan'] = $this;

		// Convert into an array before storing
		foreach ( $this as $property => $value ) {
			$array[ $property ] = $value;
		}

		$result = update_option( static::get_constant_value( 'SCAN_OPTION' ), $array, false );

		if ( $result ) {
			// Clear cache
			wp_cache_delete( static::get_constant_value( 'SCAN_OPTION' ), 'options' );
		}

		return $result;
	}

	/**
	 * Sets progress total count
	 *
	 * @package WhereUsed\HelpersLibrary
	 * @since   1.1.0
	 *
	 * @return void
	 */
	public function set_progress_total( int $value ): void {

		$this->set( 'progress_total', $value );

	}

	/**
	 * Sets started ID
	 *
	 * @package WhereUsed\HelpersLibrary
	 * @since   1.1.0
	 *
	 * @return void
	 */
	public function set_started( int $value ): void {

		$this->set( 'started', $value );

	}

	/**
	 * Sets the value of property "currently" which lets us know what is currenly being scanned
	 *
	 * @package WhereUsed\HelpersLibrary
	 * @since   1.1.0
	 *
	 * @param string $value
	 *
	 * @return void
	 */
	public function set_currently( string $value ): void {

		$this->set( 'currently', $value );

	}

	/**
	 * Resets the scan details to default settings and adds to the scan history
	 *
	 * @package WhereUsed\HelpersLibrary
	 * @since   1.1.0
	 *
	 * @return void
	 */
	public function reset(): void {

		if ( $this->get( 'start_date' ) ) {
			// Only record scans to history that have run

			$new_history[] = [
				'type' => $this->get( 'type' ),
				'start_date' => $this->get( 'start_date' ),
				'end_date' => $this->get( 'end_date' ),
				'progress' => $this->get( 'progress' ),
				'progress_total' => $this->get( 'progress_total' ),
				'cancelled' => $this->get( 'cancelled' ),
				'started' => $this->get( 'started' ),
				'notes' => $this->get( 'notes' ),
			];

			$old_history = $this->get( 'history' );

			if ( ! empty( $old_history ) ) {
				// Build new history array with old history

				$has_full_scan = false;
				foreach ( $old_history as $history ) {
					if ( count( $new_history ) < 10 ) {
						if ( 'full-scan' == $history['type'] ){
							$has_full_scan = true;
						}
						$new_history[] = $history;
					} else {
						break;
					}
				}

				if ( ! $has_full_scan ) {
					foreach ( $old_history as $history ) {
						if ( 'full-scan' == $history['type'] ){
							// Add the full-scan to the end of the history. We must have at least one full-scan in the history if it has been performed in the past.
							$new_history[] = $history;
							break;
						}
					}
				}
			}

			$this->set( 'history', $new_history );
		}

		$this->set( 'needed', true );
		$this->set( 'start_date', '' );
		$this->set( 'end_date', '' );
		$this->set( 'currently', '' );

		$this->set( 'progress', 0 );
		$this->set( 'progress_total', 0 );
		$this->set( 'cancelled', 0 );
		$this->set( 'started', 0 );
		$this->set( 'notes', '' );

	}

	/**
	 * Determine is the current site's scan is running
	 *
	 * @package WhereUsed\HelpersLibrary
	 * @since   1.1.0
	 *
	 * @return bool
	 */
	public function is_running(): bool {

		$is_running = false;

		if ( $this->get( 'start_date' ) && ! $this->get( 'end_date' ) && ! $this->get( 'cancelled' ) ) {
			$is_running = true;
		}

		return $is_running;

	}

	/**
	 * Is the scan complete?
	 *
	 * @return bool
	 */
	public function is_complete(): bool  {

		$is_complete = false;

		if (
			! $this->is_running() &&
			$this->get( 'end_date' ) &&
			! $this->get( 'cancelled' )
		) {
			$is_complete = true;
		}

		return $is_complete;
	}

	/**
	 * Displays the message sorry no results available since the scan hasn't fully ran
	 *
	 * @return void
	 */
	public static function display_results_not_available( bool $warning = false ): void {

		$settings = static::get_class('Settings')::get_current_settings();
		$can_user_access_settings = $settings->can_user_access_settings();
		$slug = static::get_constant_value('SLUG');

		$link_url = static::get_constant_value( 'ADMIN_URL' ) . '#scan';
		$link_anchor_text = __( 'Start Scan', $slug );

		$is_running = static::is_full_scan_running();

		if ( $is_running ) {
			$message = esc_html__( 'Results will be displayed when the full scan is finished scanning.', WHEREUSED_SLUG );
		} else {
			// Scan is needed
			if ( $can_user_access_settings ) {
				$message = esc_html__( 'A full scan needs to be completed before accurate results are available.', $slug );
			} else {
				$message = esc_html__( 'A full scan needs to be completed before results are available.', $slug );
				$message .= esc_html__( 'Contact your site administrator to perform a full scan.', $slug );
			}
		}

		if ( $warning ) {
			static::get_class( 'Admin' )::add_notice( [
				'message' => $message,
				'link_url' => $link_url,
				'link_anchor_text' => $link_anchor_text,
				'alert_level' => 'warning',
			] );
		} else {
			echo '<p>' . esc_html( $message );
			if ( $can_user_access_settings && ! $is_running ) {
				echo ' <a href="' . esc_url( $link_url ) . '" class="dashboard-start-scan">' . esc_html( $link_anchor_text ) . '</a>';
			}
			echo '</p>';
		}

	}

	/**
	 * Retrieves most recent scan of specific type
	 *
	 * @internal use static::get_current(); if you only want the most recent scan regardless of type.
	 *
	 * @param string $type
	 *
	 * @return object
	 */
	public static function get_recent_scan( string $type, int $nth = 1 ): object {

		// Grab most recent scan regardless of type
		$recent_scan = static::get_current(true);

		if ( ! empty($recent_scan) ){
			// We have a scan to check

			$nth_count = 0;

			if ( $recent_scan->get( 'type' ) == $type ){
				$nth_count = 1;
			}

			$needed = $recent_scan->get( 'needed' );

			if ( $recent_scan->get( 'type' ) != $type || $nth_count != $nth ) {
				$scan_history = $recent_scan->get( 'history' );
				$recent_scan = new \stdClass();

				if ( ! empty( $scan_history ) ) {
					foreach ( $scan_history as $scan ) {
						$scan_type = $scan['type'] ?? 'unknown';

						if ( $scan_type == $type ) {
							// We found the specific scan type
							$nth_count++;
						}

						if ( $nth_count == $nth ){
							// We found the nth scan type
							$recent_scan = $scan;
							break;
						}
					}
				}
			}

			$recent_scan = empty( $recent_scan ) ? $recent_scan : new static( $recent_scan );

			// Must pass the current needed status to the new scan object as history does not have needed status
			$recent_scan->set_needed( $needed );
		}


		return $recent_scan;
	}

	/**
	 * Has a full scan ran and completed and a new scan is not needed?
	 *
	 * @return bool
	 */
	static function has_full_scan_ran(): bool {

		$ran_and_complete = false;

		$recent_scan = static::get_recent_scan( 'full-scan' );

		if ( ! empty( $recent_scan ) && $recent_scan->is_complete() && ! $recent_scan->get('needed') ) {
			$ran_and_complete = true;
		}

		return $ran_and_complete;
	}

	/**
	 * Is a full scan running?
	 *
	 * @return bool
	 */
	static function is_full_scan_running(): bool {

		$running = false;

		$recent_scan = static::get_recent_scan( 'full-scan' );

		if ( ! empty( $recent_scan ) && $recent_scan->is_running() ) {
			$running = true;
		}

		return $running;
	}

	/**
	 * Stops all currently running scans
	 *
	 * @return void
	 */
	static function stop_all_scans( string $notes = '' ): void {

		$sites = Get::sites();

		foreach ( $sites as $site ) {

			if ( is_multisite() ) {
				switch_to_blog( $site->blog_id );
			}

			if ( is_plugin_active( static::get_constant_value( 'PLUGIN' ) ) ) {
				static::cancel( $notes );
			}

			if ( is_multisite() ) {
				restore_current_blog();
			}
		}

	}

	/**
	 * Cancels the current scan
	 *
	 * @param string $notes
	 *
	 * @return void
	 */
	public static function cancel( string $notes = '' ): void {

		$current_site_id = ( is_multisite() ) ? get_current_blog_id() : 1;

		if ( WHEREUSED_HELPERSLIBRARY_CURRENT_SITE_ID == $current_site_id ) {
			// Only delete the lock if we are on the current site

			$plugin_global = static::get_constant_value( 'GLOBAL' );

			// Grab plugin's global
			global $$plugin_global;

			if ( isset( $$plugin_global['scan-process'] ) ) {
				if ( ! $$plugin_global['scan-process']->can_we_start() ) {
					// Delete scan locks
					$$plugin_global['scan-process']->cancel_process();
				}
			}
		}

		// Mark the scan as needed
		$scan = static::get_current( true );

		if ( $scan->get('type') == 'full-scan' ) {
			$scan->set_needed( true );
		}

		$scan->set_cancelled();
		$scan->set_notes( $notes );

		// End date and time
		$scan->set_end_date();

		$scan->save();

	}

}