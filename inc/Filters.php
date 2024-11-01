<?php

namespace WhereUsed;

// Prevent Direct Access
( defined( 'ABSPATH' ) ) || die;

/**
 * Class Filters
 *
 * @package WhereUsed
 * @since   1.0.0
 */
final class Filters {

	/**
	 * Gets all the from_post_type values in the db table
	 *
	 * @return array
	 */
	public static function get_from_post_types(): array {

		global $wpdb;

		$values = [];
		$values[] = Get::table_name();
		$from_post_types = $wpdb->get_col( $wpdb->prepare( 'SELECT `from_post_type` FROM `%1$s` WHERE `from_post_type` != "" GROUP BY `from_post_type`', $values ) );

		// Make the keys match the values
		foreach ( $from_post_types as $key => $value ) {
			unset( $from_post_types[ $key ] );
			$from_post_types[ $value ] = $value;
		}

		return $from_post_types;
	}

	/**
	 * Gets all the from_post_type values in the db table
	 *
	 * @return array
	 */
	public static function get_to_post_types(): array {

		global $wpdb;

		$values = [];
		$values[] = Get::table_name();
		$to_post_types = $wpdb->get_col( $wpdb->prepare( 'SELECT `to_post_type` FROM `%1$s` WHERE `to_post_type` != "" GROUP BY `to_post_type`', $values ) );

		// Make the keys match the values
		foreach ( $to_post_types as $key => $value ) {
			unset( $to_post_types[ $key ] );
			$to_post_types[ $value ] = $value;
		}

		return $to_post_types;
	}

	/**
	 * Retrieves the To types values
	 *
	 * @return array
	 */
	public static function get_to_types(): array {

		return [
			'link' => 'link',
			'image' => 'image',
			'block' => 'block',
			'iframe' => 'iframe',
			'id' => 'id',
		];

	}

	/**
	 * Grabs all blocks that have been scanned from the database table
	 *
	 * @return array
	 */
	public static function get_scanned_blocks(): array {

		global $wpdb;

		$ignored_blocks = Get::ignored_blocks();

		$values = [];
		$values[] = Get::table_name();
		$scanned_blocks = $wpdb->get_col( $wpdb->prepare( 'SELECT `to_block_name` FROM `%1$s` WHERE `to_type` = \'block\';', $values ) );

		if ( ! empty( $scanned_blocks ) ) {

			// Sort the alphabetically
			sort( $scanned_blocks );

			foreach ( $scanned_blocks as $key => $scanned_block ) {
				unset( $scanned_blocks[ $key ] );

				if ( ! isset( $ignored_blocks[ $scanned_block ] ) ) {
					// Add back in a format that can used in a select box for filters
					$scanned_blocks[ $scanned_block ] = $scanned_block;
				}
			}

		}

		return $scanned_blocks;
	}

	/**
	 * Retrieves the from_where values
	 *
	 * @return array
	 */
	public static function get_from_where(): array {

		global $wpdb;

		$where = $wpdb->get_col( $wpdb->prepare( 'SELECT `from_where` FROM `%1$s` GROUP BY `from_where` ORDER BY `from_where` ASC;', Get::table_name() ) );

		$all = [];
		foreach ( $where as $w ) {

			if ( $w = trim( $w ?? '' ) ) {
				$all[ $w ] = ucwords( str_replace( [
					'-',
					'_',
				], ' ', $w ) );
			}

		}

		return $all;

	}

	public static function get_locations(): array {

		// Create dropdowns for locations
		$sites = Get::sites();
		$locations = [];
		foreach ( $sites as $site ) {
			$locations[ $site->blog_id ] = $site->domain;
		}

		return $locations;

	}

	public static function get_statuses(): array {

		return [
			'0' => 'No Response',
			'1' => '1XX',
			'2' => '2XX',
			'3' => '3XX',
			'4' => '4XX',
			'5' => '5XX',
		];
	}

	public static function get_redirection_types(): array {

		$types = [
			'3' => '3XX Status',
		];

		if ( Get::using_redirection_plugin() ) {

			$types = [
				'1' => 'All Redirects (Rules or 3XX Status)',
				'2' => 'Redirection Rules',
				'3' => '3XX Status',
				'4' => 'Incoming Redirection Rules',
				'5' => 'Outgoing Redirection Rules',
			];

		}

		return $types;

	}

	/**
	 * Grabs all the mime types for the attachments in the database that are not referenced anywhere
	 *
	 * @package WhereUsed
	 * @since   1.0.0
	 *
	 * @return array
	 */
	public static function get_mime_types(): array {

		global $wpdb;

		$values = [];
		$values[] = $wpdb->prefix . 'posts';
		$values[] = Get::table_name();
		$mime_types = $wpdb->get_col( $wpdb->prepare( 'SELECT `post_mime_type` FROM `%1$s` WHERE `post_type` = \'attachment\' AND `ID` NOT IN ( SELECT `to_post_id` from `%2$s` WHERE `to_post_id` > 0 GROUP BY `to_post_id` );', $values ) );

		$all = [];
		foreach ( $mime_types as $mime ) {
			$all[ $mime ] = $mime;
		}

		return $all;

	}

	/**
	 * The icons for filters
	 *
	 * @return array
	 */
	public static function get_icons(): array {

		return [
			'from_location' => 'admin-multisite',
			'to_location' => 'admin-multisite',
			'redirection_type' => 'redo flip-v',
			'redirection_location' => 'admin-multisite',
			'from_where' => 'location',
			'from_post_type' => 'admin-post',
			'to_post_type' => 'admin-post',
			'type' => 'tag',
			'status' => 'cloud',
			'block' => 'block-default',
		];

	}

}