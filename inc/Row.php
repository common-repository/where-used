<?php

namespace WhereUsed;

// Prevent Direct Access
( defined( 'ABSPATH' ) ) || die;

use WhereUsed\HelpersLibrary\Base;

/**
 * Class Row - Class reflecting the data in the database
 *
 * @package WhereUsed
 * @since   1.0.0
 *
 * @link https://whereused.com/docs/classes/row/
 */
class Row extends Base {

	// Universal
	protected int $id = 0;

	// From Source
	protected int $from_post_id = 0; // Post ID of the source using this reference
	protected int $from_site_id = 0; // Multisite blog_id of the source of the reference
	protected string $from_post_type = ''; // post, term, user
	protected string $from_how = ''; // How is it referenced (url, id, post)
	protected string $from_where = ''; // The context of where the reference is located (content, menu, featured image, post meta, redirect)
	protected string $from_where_key = ''; // the post meta key, term meta key, user meta key, or other specific reference related to from_where

	// To Destination
	protected int $to_post_id = 0; // Post ID of the reference
	protected int $to_site_id = 0; // Multisite blog_id of the reference
	protected string $to_post_type = ''; // If in network, the post_type of the destination
	protected string $to_type = ''; // Describes what type of reference this is (link, image, block, redirect)
	protected string $to_url = ''; // Original URL provided
	protected string $to_url_full = ''; // The full version of the original URL
	protected string $to_url_absolute = ''; // The absolute version of the URL (not including the anchor link)
	protected int $to_url_status = - 1; // The status code of the original URL (Full version)
	protected string $to_url_status_date = ''; // The date the status code was determined
	protected string $to_anchor_text = ''; // The anchor text used in the link
	protected string $to_block_name = ''; // The name of the block being used

	// Redirect
	protected int $redirection_id = 0; // The unique ID of the redirection rule in the database
	protected int $redirection_site_id = 0; // The blog_id of the site where the redirection rule exists
	protected string $redirection_url = ''; // The URL that the redirect is sending the user to

	// Excluded from Database
	public array $cache = [
		'from_post' => [],
		// WP_Post object
		'to_post' => [],
		// WP_Post object
		'redirection' => [],
		// db row object
	];

	/**
	 * Gets the site domain of the give property type ( redirection, to, from )
	 *
	 * @package WhereUsed
	 * @since   1.0.0
	 *
	 * @param string $type
	 *
	 * @return string
	 */
	public function get_site_domain( string $type ): string {

		$label = '';

		if ( $site_id = $this->get( $type . '_site_id' ) ) {

			$sites = Get::sites();

			$label = $sites[ $site_id ]->domain;
		}

		return $label;

	}

	/**
	 * Retrieves the redirection from the database
	 *
	 * @package WhereUsed
	 * @since   1.0.0
	 *
	 * @return object
	 */
	private function get_redirection(): object {

		if ( Get::using_redirection_plugin() && empty( $this->cache['redirection'] ) ) {

			$id = $this->redirection_id;
			$site_id = $this->redirection_site_id;

			if ( $id ) {

				if ( is_multisite() ) {
					switch_to_blog( $site_id );
				}
				global $wpdb;

				$values = [];
				$values[] = $wpdb->prefix . 'redirection_items';
				$values[] = $id;
				$results = $wpdb->get_results( $wpdb->prepare( 'SELECT * from `%1$s` WHERE `id` = %2$d LIMIT 1', $values ) );

				$this->cache['redirection'] = ( isset( $results[0] ) ) ? $results[0] : $this->cache['redirection'];

				if ( ! empty( $this->cache['redirection'] ) ) {
					// Add site ID for convenience
					$this->cache['redirection']->site_id = $site_id;
				}

				if ( is_multisite() ) {
					restore_current_blog();
				}
			}
		}

		return (object) $this->cache['redirection'];

	}

	/**
	 * Gets redirection rule
	 *
	 * @package WhereUsed
	 * @since   1.0.0
	 *
	 * @return string
	 */
	public function get_redirection_rule(): string {

		if ( Get::using_redirection_plugin() ) {
			$redirection = $this->get_redirection();

			return $redirection->url ?? '';
		}

		return '';
	}

	/**
	 * Gets redirection destination
	 *
	 * @package WhereUsed
	 * @since   1.0.0
	 *
	 * @return string
	 */
	public function get_redirection_action(): string {

		$action = '';

		if ( Get::using_redirection_plugin() ) {
			$redirection = $this->get_redirection();

			$action = $redirection->action_data ?? '';
		}

		return $action;

	}

	/**
	 * Converts data from the database into Row objects
	 *
	 * @package WhereUsed
	 * @since   1.0.0
	 *
	 * @param array $results
	 * @param bool  $load_only
	 *
	 * @return array
	 */
	public static function get_results( array $results, bool $load_only = true ): array {

		$rows = [];

		if ( ! empty( $results ) ) {
			foreach ( $results as $result ) {
				$result->load_only = $load_only;
				$rows[] = new static( $result );
			}
		}

		return $rows;

	}

	/**
	 * Inserts multiple entries at the same time
	 *
	 * @package WhereUsed
	 * @since   1.0.0
	 *
	 * @param array $rows
	 *
	 * @return bool
	 */
	public static function add_entries( array $rows ): bool {

		global $wpdb;

		$results = false;

		if ( ! empty( $rows ) ) {

			$columns_set = false;
			$exclude_properties = [
				'id' => true,
				'cache' => true,
			];

			// Insert the data into the database
			$column_name_placeholders = []; // Holds all the placeholders for the column names
			$value_placeholders = []; // Will hold all the sanitization data types
			$values = [];
			$values[] = Get::table_name();

			// Get Row properties
			$properties = (new class extends Row{})->get_properties();

			// Array of traces to insert
			foreach ( $rows as $row ) {

				if ( false === $columns_set ) {
					// Add in the column placeholders and values

					$columns_set = true; // set flag so we only do this once

					foreach ( $properties as $property_name => $default_value ) {
						if ( ! isset( $exclude_properties[ $property_name ] ) ) {
							// Build the columns names placeholders
							$values[] = $property_name;
							$column_name_placeholders[] = '`%' . count( $values ) . '$s`';
						}
					}
				}

				$column_value_placeholders = [];

				foreach ( $properties as $property_name => $not_used ) {
					// Add in each rows' placeholders and values

					if ( isset( $properties[ $property_name ] ) && ! isset( $exclude_properties[ $property_name ] ) ) {
						// Property exists and is not excluded, let's add this data

						$value = $row->get( $property_name );

						if ( is_array( $value ) ) {
							// We are dealing with an array: let's convert it to a JSON string
							$values[] = json_encode( $value );
							$column_value_placeholders[] = '"%' . count( $values ) . '$s"';
						} else {
							$values[] = $value;
							$column_value_placeholders[] = is_int( $value ) ? '%' . count( $values ) . '$d' : '"%' . count( $values ) . '$s"';
						}
					}
				}

				if ( ! empty( $column_value_placeholders ) ) {
					$value_placeholders[] = "(" . implode( ',', $column_value_placeholders ) . ")";
				}

			}

			$results = $wpdb->query( $wpdb->prepare( 'INSERT INTO `%1$s` (' . implode( ',', $column_name_placeholders ) . ') ' . 'VALUES ' . implode( ', ', $value_placeholders ), $values ) );

			if ( ! $results ) {

				// Table might not exist, let's try again

				// Create Tables for current site
				Run::create_tables( Get::tables() );

				// Try query again
				$results = $wpdb->query( $wpdb->prepare( 'INSERT INTO `%1$s` (' . implode( ',', $column_name_placeholders ) . ') ' . 'VALUES ' . implode( ', ', $value_placeholders ), $values ) );

			}

		}

		return (bool) $results;

	}

	/**
	 * Gets all recorded incoming entries from the database.
	 *
	 * @param int    $to_post_id
	 * @param string $to_post_type
	 * @param int    $to_site_id
	 *
	 * @return void
	 */
	public static function get_incoming_entries( int $to_post_id, string $to_post_type, int $to_site_id = WHEREUSED_CURRENT_SITE_ID ): array {

		global $wpdb;

		$sites = Get::sites();
		$incoming = [];

		foreach ( $sites as $site ) {

			if ( is_multisite() ) {
				switch_to_blog( $site->blog_id );
			}

			if ( is_plugin_active( WHEREUSED_PLUGIN ) ) {

				$values = [];
				$values[] = Get::table_name();
				$values[] = $to_post_id;
				$values[] = $to_post_type;
				$values[] = $to_site_id;

				$results = $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM `%1$s` WHERE (
						`to_post_id` = %2$d AND 
						`to_post_type` = "%3$s" AND 
						`to_site_id` = %4$d
						);', $values ) );

				$incoming = array_merge( $incoming, $results );

			}

			if ( is_multisite() ) {
				restore_current_blog();
			}

		}

		return $incoming;

	}

	/**
	 * Gets all recorded outgoing entries from the database.
	 *
	 * @param int    $from_post_id
	 * @param string $from_post_type
	 * @param int    $from_site_id
	 *
	 * @return array
	 */
	public static function get_outgoing_entries( int $from_post_id, string $from_post_type, int $from_site_id = WHEREUSED_CURRENT_SITE_ID ): array {

		global $wpdb;

		$sites = Get::sites();
		$outgoing = [];

		foreach ( $sites as $site ) {

			if ( is_multisite() ) {
				switch_to_blog( $site->blog_id );
			}

			if ( is_plugin_active( WHEREUSED_PLUGIN ) ) {

				$values = [];
				$values[] = Get::table_name();
				$values[] = $from_post_id;
				$values[] = $from_post_type;
				$values[] = $from_site_id;

				$sql = $wpdb->prepare( 'SELECT * FROM `%1$s` WHERE (
						`from_post_id` = %2$d AND 
						`from_post_type` = "%3$s" AND 
						`from_site_id` = %4$d
						);', $values );

				Debug::log($sql);
				$results = $wpdb->get_results( $sql );

				$outgoing = array_merge( $outgoing, $results );

			}

			if ( is_multisite() ) {
				restore_current_blog();
			}

		}

		return $outgoing;

	}

	/**
	 * Deletes all recorded references this post id points at (outgoing)
	 *
	 * @package WhereUsed
	 * @since   1.0.0
	 *
	 * @param int    $from_post_id   The specific post ID
	 * @param string $from_post_type The specific post type we want to delete
	 * @param int    $from_site_id   The site where this post exists
	 */
	public static function delete_outgoing_entries( int $from_post_id, string $from_post_type, int $from_site_id = WHEREUSED_CURRENT_SITE_ID ): void {

		global $wpdb;

		$sites = Get::sites();

		foreach ( $sites as $site ) {

			if ( is_multisite() ) {
				switch_to_blog( $site->blog_id );
			}

			if ( is_plugin_active( WHEREUSED_PLUGIN ) ) {

				$values = [];
				$values[] = Get::table_name();
				$values[] = $from_post_id;
				$values[] = $from_post_type;
				$values[] = $from_site_id;
				$values[] = $from_post_id;
				$values[] = $from_post_type;
				$values[] = $from_site_id;

				$wpdb->query( $wpdb->prepare( 'DELETE FROM `%1$s` WHERE (
					`from_post_id` = %2$d AND 
					`from_post_type` = "%3$s" AND 
					`from_site_id` = %4$d) OR ( 
					`to_post_id` = %5$d AND 
					`to_post_type` = "%6$s" AND 
					`to_site_id` = %7$d AND 
					`from_where` = "redirection" );', $values ) );

			}

			if ( is_multisite() ) {
				restore_current_blog();
			}

		}

	}

	/**
	 * Returns properties as array
	 *
	 * @package WhereUsed
	 * @since 1.1.0
	 *
	 * @return array
	 */
	public function get_properties(): array {

		$array = [];

		foreach ( $this as $property => $value ) {
			$array[ $property ] = true;
		}

		return $array;

	}

}