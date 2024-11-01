<?php

namespace WhereUsed;

// Prevent Direct Access
( defined( 'ABSPATH' ) ) || die;

use WhereUsed\HelpersLibrary\Run as Library_Run;

/**
 * Class Run - Run maintenance tasks and other misc tasks
 */
class Run extends Library_Run {

	/**
	 * Deletes all the options related to the plugin
	 *
	 * @return array
	 */
	public static function delete_options(): array {

		global $whereused;

		$responses = [];

		$responses[] = delete_option( WHEREUSED_OPTION );
		wp_cache_delete( WHEREUSED_OPTION, 'options' );

		if ( is_multisite() ){
			$responses[] = delete_site_option( WHEREUSED_NETWORK_OPTION );
			wp_cache_delete( '1:notoptions', 'site-options' );
		}

		$responses[] = delete_option( WHEREUSED_SCAN_OPTION );
		wp_cache_delete( WHEREUSED_SCAN_OPTION, 'options' );

		$responses[] = delete_option( WHEREUSED_NOTIFICATIONS_OPTION );
		wp_cache_delete( WHEREUSED_NOTIFICATIONS_OPTION, 'options' );

		if ( ! isset( $whereused['scan-process'] ) ) {
			Scan_Process::init();
		}
		$whereused['scan-process']->delete_all_scan_batches();

		return $responses;
	}

	/**
	 * Clears the all the status code cache for URLs that is located in options tables
	 *
	 * @return void
	 */
	public static function clear_status_cache(): void {

		global $wpdb;

		$values = [];
		$values[] = $wpdb->prefix;
		$values[] = '%' . WHEREUSED_HOOK_PREFIX . '%';

		$sql = $wpdb->prepare('SELECT `option_name` FROM `%1$soptions` WHERE `option_name` LIKE "%2$s";', $values);
		$option_names = $wpdb->get_col( $sql );

		if ( ! empty( $option_names ) ) {
			foreach ( $option_names as $option_name ) {
				// Delete the option from the table
				delete_option( $option_name );
			}
		}

	}

}
