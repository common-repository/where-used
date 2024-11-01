<?php

namespace WhereUsed;

// Prevent Direct Access
( defined( 'ABSPATH' ) ) || die;

use WhereUsed\HelpersLibrary\Migration as Library_Migration;

/**
 * Class Migration
 *
 * @package WhereUsed
 * @since   1.1.0
 */
final class Migration extends Library_Migration {

	/**
	 * Runs all the migrations
	 *
	 * @package WhereUsed
	 * @since   1.1.0
	 *
	 * @param string $db_version
	 *
	 * @return void
	 */
	protected static function run_all( string $db_version ): void {

		if ( version_compare( '1.1.7', $db_version, '>' ) ) {
			self::v1_1_7();
		}

		if ( version_compare( '1.3.3', $db_version, '>' ) ) {
			self::v1_3_3();
		}

	}

	/**
	 * Runs the migration script for version 1.3.3: Bug Fix: removes orphan cache in the options table
	 *
	 * @package WhereUsed
	 * @since   1.1.7
	 *
	 * @return array
	 */
	public static function v1_3_3(): void {

		global $wpdb;

		$caches = $wpdb->get_col("SELECT `option_name` FROM `{$wpdb->prefix}options` WHERE `option_name` LIKE 'whereused%cache_status_codes';");

		if ( ! empty( $caches ) ) {
			foreach($caches as $cache) {
				// clear cache
				delete_option( $cache );
			}
		}

		Notification::add_notification( [
			'message' => __( 'WhereUsed database migration to version 1.3.3.', WHEREUSED_SLUG ),
			'link_url' => WHEREUSED_ADMIN_URL,
			'link_anchor_text' => __( 'Start New Scan', WHEREUSED_SLUG ),
			'alert_level' => 'notice',
		] );

	}

	/**
	 * Runs the migration script for version 1.1.7: Bug Fix: WP Crons did not have a consistent prefix
	 *
	 * @package WhereUsed
	 * @since   1.1.7
	 *
	 * @return array
	 */
	public static function v1_1_7(): void {

		global $wpdb;

		/**
		 * Bug Fix: WP Crons did not have a consistent prefix
		 */
		Run::remove_crons();

		/**
		 * Cancel any scans that may be stuck
		 */
		Scan::stop_all_scans( __( 'All scans cancelled due to migration to WhereUsed version 1.1.7.', WHEREUSED_SLUG ) );

		/**
		 * Improvement: DB table columns tuned for performance
		 */
		$db_table_no_prefix = 'whereused_references';
		$db_table_no_prefix_temp = $db_table_no_prefix . '_temp';

		// Rename current table to temp table
		$wpdb->query( $wpdb->prepare('RENAME TABLE `%1$s` TO `%2$s`;', $wpdb->prefix . $db_table_no_prefix, $wpdb->prefix . $db_table_no_prefix_temp) );

		// create new db table
		Run::create_table( GET::table( $db_table_no_prefix ) );

		// Copy old data to new table
		$wpdb->query( $wpdb->prepare('INSERT INTO `%1$s` SELECT * FROM `%2$s`;', $wpdb->prefix . $db_table_no_prefix, $wpdb->prefix . $db_table_no_prefix_temp ) );

		// Delete temp table
		Run::drop_table( $db_table_no_prefix_temp );

		Notification::add_notification( [
			'message' => __( 'WhereUsed database migration to version 1.1.7.', WHEREUSED_SLUG ),
			'link_url' => WHEREUSED_ADMIN_URL,
			'link_anchor_text' => __( 'Start New Scan', WHEREUSED_SLUG ),
			'alert_level' => 'notice',
		] );
	}

}