<?php

namespace WhereUsed;

// Prevent Direct Access
( defined( 'ABSPATH' ) ) || die;

use WhereUsed\HelpersLibrary\REQUEST;
use WhereUsed\HelpersLibrary\Scan_Process as Library_Scan_Process;

// Include dependency
require_once( WHEREUSED_HELPERSLIBRARY_INC_DIR . '/Scan_Process.php' );

/**
 * Class Background
 *
 * @package WhereUsed
 * @since   1.0.0
 */
class Scan_Process extends Library_Scan_Process {

	/**
	 * The type of scans that are allowed
	 *
	 * @return array
	 */
	protected function get_scan_types() : array {

		return [
			'full-scan',
			'check-status',
			'maintenance-check-status',
		];

	}

	/**
	 * Set the hooks
	 *
	 * @return void
	 */
	protected function set_hooks(): void {

		parent::set_hooks();

		// Refresh Status
		add_action( 'wp_ajax_' . $this->identifier . '_check_row_status', [
			$this,
			'ajax_check_row_status',
		], 10, 0 );

	}

	/**
	 * AJAX - Manually checks the status of a Redirection row in display table
	 */
	public function ajax_check_row_status(): void {

		if ( ! wp_verify_nonce( REQUEST::text_field( 'nonce' ), WHEREUSED_SLUG . '-check-status-' . get_current_user_id() ) ) {
			die( '0-1' . REQUEST::text_field( 'nonce' ) );
		}

		$url = REQUEST::url( 'url' );

		// Require valid ID
		if ( ! $url ) {
			die( '0-2' );
		}

		$to_url_full = Reference::get_full_url( $url );

		// recheck status and update db: no status caching needed as we are only dealing with a single URL
		$response = Scan::status_check_update( $to_url_full );

		// Refresh the nonce on the page
		$response['_nonce-check-status'] = wp_create_nonce( WHEREUSED_SLUG . '-check-status-' . get_current_user_id() );

		wp_send_json( $response );

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

		global $wpdb;

		$total_count = 0;
		$response = '';

		$plugin_slug = static::get_constant_value( 'SLUG' );

		Debug::log('scan type: ' . $type);

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

		} elseif ( 'check-status' === $type ) {
			// Check Status of All Urls

			$values = [];
			$values[] = static::get_class( 'Get' )::table_name();

			$sql = $wpdb->prepare( 'SELECT `to_url_full` as `queue` FROM `%1$s` GROUP BY `queue` ORDER BY `queue` ASC;', $values );

			// Queue Term IDs and update total count
			$total_count += $this->push_to_queue( $sql, 'statuses' );

			// Make sure we have some URLs left over
			if ( $total_count ) {

				$message = __( 'A check status scan has started.', $plugin_slug );

				if ( wp_doing_cron() ) {
					$message = 'WPCron: ' . $message;
				}

				$this->debug::log( $message );

				$this->notification::add_notification( [
					'message' => $message,
					'link_url' => static::get_constant_value( 'ADMIN_URL' ) . '#scan',
					'link_anchor_text' => __( 'View Scan Progress', $plugin_slug ),
					'alert_level' => 'notice',
				] );

			} else {

				$message = __( 'A check status scan has failed. There are not any URLs in the database to check. Maybe you should run a full scan first.', $plugin_slug );

				if ( wp_doing_cron() ) {
					$message = 'WPCron: ' . $message;
				}

				$this->debug::log( $message, 'error' );

				$this->notification::add_notification( [
					'message' => $message,
					'link_url' => static::get_constant_value( 'SETTINGS_URL' ),
					'link_anchor_text' => __( 'Check Settings', $plugin_slug ),
					'alert_level' => 'error',
				] );

			}
		} elseif ( 'maintenance-check-status' === $type ) {

			$this->debug::log( 'Running scan maintenance-check-status' );

			$values = [];
			$values[] = static::get_class( 'Get' )::table_name();

			$sql = $wpdb->prepare( 'SELECT `to_url_full` as `queue` FROM `%1$s` WHERE `to_url_status_date` = "1970-01-01 00:00:00" GROUP BY `queue` ORDER BY `queue` ASC;', $values );

			$this->debug::log( 'sql: ' . $sql, 'notice' );

			// Queue Term IDs and update total count
			$total_count += $this->push_to_queue( $sql, 'statuses' );
		}

		Debug::log('total count: ' . $total_count);

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

}