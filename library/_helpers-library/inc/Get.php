<?php

namespace WhereUsed\HelpersLibrary;

// Prevent Direct Access
( defined( 'ABSPATH' ) ) || die;

/**
 * Abstract Class Get - Get the information you need
 */
abstract class Get {

	// Caches all the sites
	protected static array $sites;

	use Constants;

	/**
	 * Grabs the first table's name
	 *
	 * @return string
	 */
	public final static function table_name( string $name = '' ): string {

		global $wpdb;

		$tables = static::tables();

		if ( isset( $tables[ $name ] ) ) {
			// Get requested table
			$table = $tables[ $name ];
		} else {
			// Get the first table
			$table = static::table();
		}

		return $wpdb->prefix . $table['name'];
	}

	/**
	 * Gets a specific table or the first entry
	 *
	 * @param string $name
	 *
	 * @return array
	 */
	public final static function table( string $name = '' ): array {

		$table = [];
		$tables = static::tables();

		if ( isset( $tables[ $name ] ) ) {
			// Grab specific table
			$table = $tables[ $name ];
		} else {
			if ( ! empty( $tables ) ) {
				foreach ( $tables as $table ) {
					// Grab first table
					break;
				}
			}
		}

		return $table;
	}

	/**
	 * Abstract method to get the tables
	 *
	 * NOTICE: Table name should NOT include site prefix.
	 */
	abstract public static function tables(): array;

	/**
	 * Tells you what the mime type is of a local file
	 *
	 * @param string $local_file Absolute local path of the file
	 *
	 * @return string
	 */
	public final static function mime_type( string $local_file ): string {

		if ( file_exists( $local_file ) ) {
			// Let's grab the mime type so that we can check it
			$finfo = finfo_open( FILEINFO_MIME_TYPE );
			$mime_type = finfo_file( $finfo, $local_file ); // This will return the mime-type
			finfo_close( $finfo );
		} else {
			$mime_type = 'file does not exist.';
		}

		return $mime_type;

	}

	/**
	 * Grabs an array of all the sites or an array with boolean false
	 *
	 * @return array
	 */
	public final static function sites(): array {

		if ( empty( static::$sites ) ) {

			$sites = [];

			if ( is_multisite() ) {
				$sites_temp = get_sites();

				// Build the sites array with blog_id as key
				foreach ( $sites_temp as $site ) {
					$site->blog_id = (int) $site->blog_id;
					$sites[ $site->blog_id ] = $site;
				}
			} else {
				// Add the only site to the array with same structure

				$site = [];
				$site['blog_id'] = WHEREUSED_HELPERSLIBRARY_CURRENT_SITE_ID;
				$site['domain'] = str_replace( [
					'http://',
					'https://',
				], '', get_site_url() );

				$sites[WHEREUSED_HELPERSLIBRARY_CURRENT_SITE_ID] = (object) $site;
			}

			static::$sites = $sites;
		}

		return static::$sites;

	}

	/**
	 * Get the current site object or property of the object
	 *
	 * @return object
	 */
	public final static function current_site(): object {

		$sites = static::sites();

		return $sites[ WHEREUSED_HELPERSLIBRARY_CURRENT_SITE_ID ];
	}

	/**
	 * Grabs the current site's requested propery
	 *
	 * @param string $property
	 *
	 * @return string
	 */
	public final static function current_site_property( string $property = 'domain' ): string {

		$sites = static::sites();

		return $sites[ WHEREUSED_HELPERSLIBRARY_CURRENT_SITE_ID ]->$property ?? '';
	}

	/**
	 * Converts user ID to Name
	 *
	 * @param int $id
	 *
	 * @return string
	 */
	public final static function convert_id_to_name( int $id ): string {

		if ( 0 === $id ) {
			$name = 'Not Set';
		} elseif ( - 1 === $id ) {
			$name = 'WP Cron';
		} else {
			$user = get_user_by( 'ID', $id );

			$name = $user->display_name;
		}

		return $name;

	}

	/**
	 * Gets option key name for caching status codes specific to an action
	 *
	 * @param string $action
	 * @param int    $post_id
	 * @param int    $site_id
	 *
	 * @return string
	 */
	public static function cache_status_codes_option_name( string $action, int $post_id = 0, int $site_id = WHEREUSED_HELPERSLIBRARY_CURRENT_SITE_ID ): string {

		$key_name = static::get_constant_value( 'HOOK_PREFIX' ) . $action . '_' . $site_id;
		$key_name .= ( $post_id ) ? '_' . $post_id : '';
		$key_name .= '_cache_status_codes';

		// clear cache
		delete_option( $key_name );

		return $key_name;

	}

	/**
	 * Variable that dictates how the results are ordered
	 *
	 * @param array  $sortable_columns
	 * @param string $default
	 *
	 * @return string
	 */
	public final static function orderby( array $sortable_columns = [], string $default = '' ): string {

		$orderby = REQUEST::key( 'orderby', '', $default );

		if ( ! empty( $sortable_columns ) ) {
			// Make sure this is a sortable column (if provided)
			$orderby = $sortable_columns[ $orderby ] ?? $orderby;
		}

		return $orderby;

	}

	/**
	 * Sort Ascending or Descending
	 *
	 * @return string
	 */
	public final static function order(): string {

		$order = REQUEST::key( 'order' );

		return $order && strtolower( 'asc' ) === $order ? 'asc' : 'desc';

	}

	/**
	 * Displays the subheading for the template so that the user knows which site they are currently on in a multisite environment
	 *
	 * @return string
	 */
	public final static function subheader(): string {
		// Must have at least a hidden heading so that the wp notices show at the top of the page properly
		$subheading = '<h2 class="hidden"></h2>';
		if ( is_multisite() ) {
			if ( ! is_network_admin() ) {
				$subheading = '<h2 style="text-align:center">' . esc_html( Get::current_site_property() ) . '</h2>';
			}
		}

		return $subheading;
	}

	/**
	 * Retrieve the term by ID
	 *
	 * @param int  $term_id
	 * @param bool $wp_term
	 *
	 * @return false|\stdClass
	 */
	public final static function term_by_id( int $term_id, bool $wp_term = true ) {

		global $wpdb;

		$term = false;
		$values = [];
		$values[] = $wpdb->prefix . 'term_taxonomy';
		$values[] = $term_id;

		$terms = $wpdb->get_results( $wpdb->prepare( 'SELECT `term_id`, `taxonomy`, `description` FROM `%1$s` WHERE `term_id` = %2$d', $values ) );

		if ( isset( $terms[0] ) ) {
			$term = $terms[0];
		}

		if ( $term && $wp_term ) {
			$term = get_term( $term->term_id, $term->taxonomy );
		}

		return $term;
	}

	/**
	 * Returns the mysqli database connection
	 *
	 * @return \mysqli
	 */
	public final static function db_connection() {

		global $helperslibrary;

		if ( ! isset( $helperslibrary['db_connection'] ) ) {
			$helperslibrary['db_connection'] = new \mysqli( DB_HOST, DB_USER, DB_PASSWORD, DB_NAME );
		}

		return $helperslibrary['db_connection'];

	}

	/**
	 * Retrieves a list of post types that should not be scanned
	 *
	 * @package WhereUsed\HelpersLibrary
	 * @since 1.3.0
	 *
	 * @return array
	 */
	public static function excluded_post_types(): array {

		$excluded_post_types = [
			'revision',
			'nav_menu_item',
			'oembed_cache',
			'wp_navigation',
		];

		return apply_filters( static::get_constant_value( 'HOOK_PREFIX' ) . 'excluded_post_types', $excluded_post_types );
	}


	/**
	 * Retrieves a list of taxonomies that should not be scanned
	 *
	 * @package WhereUsed\HelpersLibrary
	 * @since   1.3.0
	 *
	 * @return array
	 */
	public static function excluded_taxonomies(): array {

		$excluded_taxonomies = [
			'nav_menu',
		];

		return apply_filters( static::get_constant_value( 'HOOK_PREFIX' ) . 'excluded_taxonomies', $excluded_taxonomies );
	}

	/**
	 * Provides the recommended post types to scan
	 *
	 * @package WhereUsed\HelpersLibrary
	 * @since   1.3.0
	 *
	 * @return array
	 */
	public static function recommended_post_types(): array {

		return [
			'post',
			'page',
			'attachment',
			'wp_block',
			'wp_template',
			'wp_template_part',
		];

	}

	/**
	 * Provides the recommended taxonomies to scan
	 *
	 * @package WhereUsed\HelpersLibrary
	 * @since   1.3.0
	 *
	 * @return array
	 */
	public static function recommended_taxonomies(): array {

		return [
			'category',
			'post_tag',
			'wp_template_part_area',
			'wp_theme',
		];

	}

	/**
	 * Test if a value (array, object, string, etc) is a real URL that can be traced
	 *
	 * @param mixed $url
	 *
	 * @return bool
	 */
	public static function is_real_url( $url = '' ): bool {

		$is_real = false;

		if ( ! empty( $url ) ) {
			if (
				is_string( $url ) &&
				'null' != $url &&
				substr( $url, 0, 7 ) != 'mailto:' &&
				substr( $url, 0, 4 ) != 'tel:'
			) {
				$is_real = true;
			}
		}

		return $is_real;
	}

	/**
	 * Detects whether the plugin Network Media Library is in use for multisite installations.
	 *
	 * @package WhereUsed\HelpersLibrary
	 * @since   1.5.0
	 *
	 * @return bool
	 */
	public static function using_network_media_library() {

		if ( ! defined( 'WHEREUSED_HELPERSLIBRARY_NETWORK_MEDIA_LIBRARY' ) ) {
			$active = is_multisite() && class_exists( '\Network_Media_Library\ACF_Value_Filter' );
			define( 'WHEREUSED_HELPERSLIBRARY_NETWORK_MEDIA_LIBRARY', $active );
		}

		return WHEREUSED_HELPERSLIBRARY_NETWORK_MEDIA_LIBRARY;

	}
}
