<?php

namespace WhereUsed;

// Prevent Direct Access
( defined( 'ABSPATH' ) ) || die;

use WhereUsed\HelpersLibrary\Base;

/**
 * Class Redirection
 *
 * @package WhereUsed
 * @since   1.0.0
 */
class Redirection extends Base {

	protected int $id = 0; // The Redirection plugin ID
	protected string $rule_url = ''; // The regex/rule applicable to the url
	protected string $url = ''; // The URL provided
	protected string $rule_destination = ''; // The regex/rule dictaion the url destination
	protected string $destination = ''; // Final full url destination
	protected int $site_id = 0; // The blog id that this redirection is associated with

	/**
	 * @package WhereUsed
	 * @since   1.0.0
	 *
	 * @param array|object $data
	 */
	function __construct( $data ) {

		parent::__construct( $data );

		$this->set( 'destination', $this->get_destination() );

	}

	public static function init() {

		// Delete Redirect
		add_action( 'redirection_redirect_deleted', [
			static::class,
			'redirection_redirect_deleted',
		], 999, 1 );

		// Add/Update Redirect
		add_action( 'redirection_redirect_updated', [
			static::class,
			'redirection_redirect_updated',
		], 999, 2 );

	}

	private function get_destination(): string {

		return Reference::get_full_url( $this->rule_destination );
	}

	/**
	 * Gets the redirection rules pointed to this post
	 *
	 * @param string $full_url         The full URL you want to check if there are redirections matching i
	 * @param string $type
	 * @param string $matching_pattern Value equals "url", "action_data", or "both" which is the column in the redirection_items table we are checking against
	 * @param int    $site_id
	 * @param int    $post_id
	 *
	 * @return array
	 */
	public static function get_redirections( string $full_url, string $type = '', string $matching_pattern = 'action_data', int $site_id = WHEREUSED_CURRENT_SITE_ID, int $post_id = 0 ): array {

		$redirections = [];
		$matching_patterns = [];

		if ( Get::using_redirection_plugin() ) {

			$first_char = substr( $full_url, 0, 1 );

			// If URL is relative, bail
			if ( 'h' !== $first_char ) {
				Debug::log( sprintf( __( '$full_url is relative in %s:%d', WHEREUSED_SLUG ), __FILE__, __LINE__ ), 'error' );

				return [];
			}

			if ( 'both' === $matching_pattern ) {
				$matching_patterns[] = 'url';
				$matching_patterns[] = 'action_data';
			} else {
				$matching_patterns[] = $matching_pattern;
			}

			$sites = Get::sites();
			$parsed_url = parse_url( $full_url );
			$host = $parsed_url['host'] ?? '';
			$local = false;

			// Determine if URL is local
			foreach ( $sites as $site ) {
				if ( $site->domain === $host || $site->domain === 'www.' . $host ) {
					$local = true;
					break;
				}
			}

			// If URL is not local, bail
			if ( ! $local ) {
				Debug::log( sprintf(__('$full_url is not local in %s: %d', WHEREUSED_SLUG ), __FILE__, __LINE__), 'notice' );
				return [];
			}

			$is_multisite = is_multisite();

			$all_urls_original = Get::all_urls( [ $full_url ], $type, $post_id );

			foreach ( $sites as $site ) {

				if ( $is_multisite ) {
					switch_to_blog( $site->blog_id );
				}

				foreach ( $matching_patterns as $matching ) {

					if ( 'url' === $matching && $site->blog_id != $site_id ) {
						// Skip as we only want the current site for from redirections
						continue;
					}

					$all_urls = $all_urls_original;

					// Grab the exact url matches
					if ( ! empty( $all_urls ) ) {

						foreach ( $all_urls as $key => $value ) {

							$keep = true; // default
							$value = trim( $value ?? '' );

							if ( $value ) {
								// Ok, We have a value

								if ( substr( $value, 0, 1 ) != 'h' ) {
									// Dealing with relative URL

									if ( WHEREUSED_CURRENT_SITE_ID != $site->blog_id ) {
										// We are on a different site

										if ( Get::using_network_media_library() && $type == 'attachment' ) {
											// Multisite Attachments using Network Media Library all reference same files
											$keep = true;
										} else {
											// By default, a relative URL search on another site doesn't make sense
											$keep = false;
										}
									}
								}
							} else {
								// No value, trash it
								$keep = false;
							}

							if ( ! $keep ) {
								/* We only keep URLs in these situations
								- URL is a full URL
								- URL is relative, and we are on the current site
								- URL is relative, we are on a different site, and we are using the Network Shared Media Library plugin
								*/
								unset( $all_urls[ $key ] );
							}

						}

						//d($matching, $site->domain);

						// Check to see if any redirection rules reference this post explicitly
						static::get_exact_matches( $redirections, $all_urls, $matching, $site->blog_id );

						// Check to see if this post matches any redirection regex rules
						static::get_regex_matches( $redirections, $all_urls, $matching, $site->blog_id );

						//d($redirections);
					}

				}

				if ( $is_multisite ) {
					restore_current_blog();
				}

			}

		}

		return $redirections;
	}

	/**
	 * Detects redirections associated with URL based on exact matching
	 *
	 * @param array  $redirections
	 * @param array  $all_urls
	 * @param string $matching_pattern Value equals "url" or "action_data" which is the column in the redirection_items table we are checking against
	 * @param int    $site_id
	 *
	 * @return void
	 */
	private static function get_exact_matches( array &$redirections, array &$all_urls, string &$matching_pattern, int &$site_id ): void {

		global $wpdb;

		$values = [];
		$values[] = $wpdb->prefix . 'redirection_items'; // Table Name
		$values[] = ( 'url' === $matching_pattern ) ? 'url' : 'action_data';

		$placeholders = [];
		foreach ( $all_urls as $url ) {
			$values[] = $url;
			$placeholders[] = '"%' . count( $values ) . '$s"';
		}

		$results = $wpdb->get_results( $wpdb->prepare( 'SELECT `id`, `url` as `rule_url`, `action_data` as `rule_destination` FROM `%1$s` WHERE `status` = "enabled" AND `%2$s` IN (' . implode( ',', $placeholders ) . ') ORDER BY `position`, `id` ASC', $values ) );

		if ( ! empty( $results ) ) {
			foreach ( $results as $result ) {
				$result->site_id = $site_id;
				$redirections[ $site_id ][ $result->id ] = new Redirection( $result );
			}
		}

	}

	/**
	 * Detects redirections associated with URLs based on regex matching
	 *
	 * @param array  $redirections
	 * @param array  $all_urls
	 * @param string $matching_pattern Value equals "url" or "action_data" which is the column in the redirection_items table we are checking against
	 * @param int    $site_id
	 *
	 * @return void
	 */
	private static function get_regex_matches( array &$redirections, array &$all_urls, string &$matching_pattern, int &$site_id ): void {

		global $wpdb;

		if ( ! empty( $all_urls ) ) {

			$values = [];
			$values[] = $wpdb->prefix . 'redirection_items';

			$regex_replace = [];

			if ( 'url' === $matching_pattern ) {
				// url: Check redirection target: Check if these URLs are getting redirected
				foreach ( $all_urls as $url ) {
					$values[] = $url;
					$regex_replace[] = 'REGEXP_LIKE ( "%' . count( $values ) . '$s", `url`, "i" )';
				}

				$results = $wpdb->get_results( $wpdb->prepare( 'SELECT `id`, `url` as `rule_url`, `action_data` as `rule_destination` FROM `%1$s` WHERE `match_url` = "regex" AND ( ' . implode( ' OR ', $regex_replace ) . ' ) ORDER BY `position`, `id` ASC', $values ) );
			} else {
				// action_data: Check redirection destination: Check for redirections pointed at these URLs
				foreach ( $all_urls as $url ) {
					$values[] = $url;
					$regex_replace[] = '"%' . count( $values ) . '$s" LIKE REGEXP_REPLACE(`action_data`, "\\\$[1-9]+", "%")';
				}

				$results = $wpdb->get_results( $wpdb->prepare( 'SELECT `id`, `url` as `rule_url`, `action_data` as `rule_destination` FROM `%1$s` WHERE `action_data` LIKE "%$%" AND ( ' . implode( ' OR ', $regex_replace ) . ' ) ORDER BY `position`, `id` ASC', $values ) );
			}

			if ( ! empty( $results ) ) {
				foreach ( $results as $result ) {
					$result->site_id = $site_id;
					$redirections[ $site_id ][ $result->id ] = new Redirection( $result );
				}
			}

		}
	}

	/**
	 * Removed db entries when a redirect is deleted from the Redirection plugin
	 *
	 * @param \Red_Item $old_redirection
	 *
	 * @return void
	 */
	public static function redirection_redirect_deleted( \Red_Item $old_redirection ): void {

		// Recheck old references and delete redirection entry
		static::recheck_references( $old_redirection, $old_redirection );

	}

	/**
	 * Runs when a redirection is added or updated
	 *
	 * @param \Red_Item | int $old_redirection Either an insert ID or a old Red_Item() object
	 * @param \Red_Item       $new_redirection
	 *
	 * @return void
	 */
	public static function redirection_redirect_updated( $old_redirection, \Red_Item $new_redirection ): void {

		if ( is_int( $old_redirection ) ) {
			// Only on insert redirection
			$old_redirection = \Red_Item::get_by_id( $old_redirection );
		}

		// Rechecks old and new references, deletes redirection entry and adds it back in
		static::recheck_references( $old_redirection, $new_redirection, true );

	}

	/**
	 * Rechecks all the redirect references
	 *
	 * @param \Red_Item $old_redirection
	 * @param \Red_Item $new_redirection
	 * @param bool      $check_new
	 *
	 * @return void
	 */
	public static function recheck_references( \Red_Item $old_redirection, \Red_Item $new_redirection, bool $check_new = false ): void {

		global $wpdb;

		//d($redirection, $check_new);

		// Grabs known references in db
		$all = [];

		// Add caching of status codes
		$cache_status_codes_option = Get::cache_status_codes_option_name('recheck_references', $old_redirection->get_id(), WHEREUSED_CURRENT_SITE_ID );

		/**
		 * Get the old References from existing data before we delete them.
		 *
		 * Why?: We need to know about the existing references since there's no way for us to be aware of which post the redirect rule is associated with, since the rule could affect multiple posts and of course each post has multiple variations of the URL that we are not aware of in that moment.
		 */
		$existing_references = static::get_references( $old_redirection, true, $cache_status_codes_option );

		//d($existing_references);
		if ( ! empty( $existing_references ) ) {
			$all = array_merge($all, $existing_references );
			unset( $existing_references );
		}

		if ( $check_new ) {
			// Detects New References
			$detected_references = static::detect_references( $new_redirection, true, $cache_status_codes_option );

			if ( ! empty( $detected_references ) ) {
				$all = array_merge($all, $detected_references );
				unset( $detected_references );
			}

			//d($detected_references);
		}

		$all_references = [];

		//d($all);

		if ( ! empty( $all ) ) {
			foreach ( $all as $site_id => $a ) {
				if ( ! empty( $a ) ) {
					foreach ( $a as $key => $reference ) {
						// Build main array with unique list of references per site
						$all_references[ $reference->get( 'redirection_site_id' ) ][ $reference->get( 'redirection_id' ) . '-' . $reference->get( 'from_where' ) ] = $reference;
					}
				}
			}
		}

		//delete the redirection specific self reference from the db table
		if ( $old_redirection->get_id() ) {
			$values = [];
			$values[] = Get::table_name();
			$values[] = $old_redirection->get_id();
			$values[] = WHEREUSED_CURRENT_SITE_ID;
			$wpdb->query( $wpdb->prepare( 'DELETE FROM `%1$s` WHERE `redirection_id` = %2$d AND `redirection_site_id` = %3$d AND `from_post_id` = 0', $values ) );
		}

		//d($all_references);

		// Scan will clear out references related to the redirect and then add them back in with updated info along with the redirect itself as a reference (if it is detected)
		Scan::references( $all_references );

		// clear cache
		delete_option( $cache_status_codes_option );

		// @todo redirections involving attachments are case sensitive if on Linux and our world is a mess as a result. We need to address this later.

	}


	/**
	 * Detects the references that redirect points to the "from" from the perspective of the Redirection
	 *
	 * @param \Red_Item $redirection
	 * @param bool      $unique If true, the source of the reference `from` will only be included once
	 * @param string    $cache_status_codes_option
	 *
	 * @return array
	 */
	public static function detect_references( \Red_Item $redirection, bool $unique = false, string $cache_status_codes_option = '' ): array {

		global $wpdb;

		$references = [];

		$current_site = Get::current_site();
		$protocol = is_ssl() ? 'https://' : 'http://';
		$current_site_url = $protocol . $current_site->domain;

		$destination_url = $redirection->get_action_data();

		$sites = Get::sites();

		// Find all the posts that this redirection was referenced so they can be scanned and updated with new information
		foreach ( $sites as $site ) {

			if ( is_multisite() ) {
				switch_to_blog( $site->blog_id );
			}

			if ( $redirection->is_regex() ) {

				$match_pattern = $redirection->get_url();

				if ( stripos( $match_pattern, '^' ) !== false ) {
					// has carrot
					$match_pattern = str_replace( '^', $current_site_url, $match_pattern ); // protocol and site_url()
				}

				$match_pattern = str_replace( '/', '\/', $match_pattern ); // case-insensitive match for MySQL

				$values = [];
				$values[] = Get::table_name();
				$values[] = $match_pattern; // regex pattern

				$sql = $wpdb->prepare( 'SELECT * FROM `%1$s` WHERE `to_url_absolute` REGEXP \'%2$s\' = 1;', $values );
				// Grab all links getting redirected
				$results = $wpdb->get_results( $sql );

				if ( ! empty( $results ) ) {
					foreach ( $results as $result ) {

						// Add caching of status codes
						$result->cache_status_codes_option = $cache_status_codes_option;

						// Add the reference
						if ( $unique ) {
							$references[ $result->from_site_id ][ $result->from_post_id . '-' . $result->from_post_type ] = new Reference( $result );
						} else {
							$references[ $result->from_site_id ][] = new Reference( $result );
						}

						// Get the destination reference
						if ( stripos( $destination_url, '$' ) !== false ) {
							// contains variables, we must bring over the variables from regex matches

							// Grab the destination so it can be rescanned
							$match_pattern = '/' . $match_pattern . '/i'; // case-insensitive match for PHP

							$destination_url = preg_replace( $match_pattern, $destination_url, $result->to_url_absolute );

						}

						// Create a reference so that we can get the ID of the post to scan
						$temp_reference = new Reference( [
							'to_url' => $destination_url,
							'cache_status_codes_option' => $cache_status_codes_option,
						] );

						if ( ! $temp_reference->get( 'to_is_external' ) ) {
							$reference = new Reference( [
								'from_post_id' => $temp_reference->get( 'to_post_id' ),
								'from_site_id' => $temp_reference->get( 'to_site_id' ),
								'from_post_type' => $temp_reference->get( 'to_post_type' ),
								'cache_status_codes_option' => $cache_status_codes_option,
							] );

							// Add the destination reference
							if ( $unique ) {
								$references[ $reference->get( 'from_site_id' ) ][ $reference->get( 'from_post_id' ) . '-' . $reference->get( 'from_post_type' ) ] = $reference;
							} else {
								$references[ $reference->get( 'from_site_id' ) ][] = $reference;
							}
						}
						unset( $temp_reference, $reference );

					}
				}

			} else {
				// Get exact matches

				$full_url_getting_redirected = Reference::get_full_url( $redirection->get_url() );
				$absolute_url_getting_redirected = Reference::convert_to_absolute_url( $full_url_getting_redirected );

				$values = [];
				$values[] = Get::table_name();
				$values[] = $full_url_getting_redirected;
				$values[] = $absolute_url_getting_redirected;

				$sql = $wpdb->prepare( 'SELECT * FROM `%1$s` WHERE `to_url_full` = "%2$s" OR `to_url_absolute` = "%3$s";', $values );
				// Grab all links getting redirected
				$results = $wpdb->get_results( $sql );

				if ( ! empty( $results ) ) {
					foreach ( $results as $result ) {

						// Add caching of status codes
						$result->cache_status_codes_option = $cache_status_codes_option;

						// Add the reference
						if ( $unique ) {
							$references[ $result->from_site_id ][ $result->from_post_id . '-' . $result->from_post_type ] = new Reference( $result );
						} else {
							$references[ $result->from_site_id ][] = new Reference( $result );
						}

						// Create a reference so that we can get the ID of the post to scan
						$temp_reference = new Reference( [
							'to_url' => $destination_url,
							'cache_status_codes_option' => $cache_status_codes_option,
						] );

						if ( ! $temp_reference->get( 'to_is_external' ) && $temp_reference->get( 'to_post_id' ) ) {
							$reference = new Reference( [
								'from_post_id' => $temp_reference->get( 'to_post_id' ),
								'from_site_id' => $temp_reference->get( 'to_site_id' ),
								'from_post_type' => $temp_reference->get( 'to_post_type' ),
								'cache_status_codes_option' => $cache_status_codes_option,
							] );

							// Add the destination reference
							if ( $unique ) {
								$references[ $reference->get( 'from_site_id' ) ][ $reference->get( 'from_post_id' ) . '-' . $reference->get( 'from_post_type' ) ] = $reference;
							} else {
								$references[ $reference->get( 'from_site_id' ) ][] = $reference;
							}
						}
						unset( $temp_reference, $reference );

					}
				}

			}

			if ( is_multisite() ) {
				restore_current_blog();
			}
		}

		return $references;

	}

	/**
	 * Gets already detected references that involve the given redirect across all sites
	 *
	 * @param \Red_Item $redirection
	 * @param bool      $unique If true, the source of the reference `from` will only be included once
	 * @param string    $cache_status_codes_option
	 *
	 * @return array
	 */
	public static function get_references( \Red_Item $redirection, bool $unique = false, string $cache_status_codes_option = '' ): array {

		global $wpdb;

		$references = [];

		$sites = Get::sites();

		// Find all the posts that this redirection was referenced so they can be scanned and updated with new information
		foreach ( $sites as $site ) {

			if ( is_multisite() ) {
				switch_to_blog( $site->blog_id );
			}

			$values = [];
			$values[] = Get::table_name();
			$values[] = $redirection->get_id();
			$values[] = WHEREUSED_CURRENT_SITE_ID;

			$sql = $wpdb->prepare( 'SELECT * FROM `%1$s` WHERE `redirection_id` = %2$s AND `redirection_site_id` = %3$s;', $values );

			// Grab all the post ids from the db table that involved with this redirection in the past
			$results = $wpdb->get_results( $sql );

			if ( ! empty( $results ) ) {
				foreach ( $results as $result ) {

					// Add caching of status codes
					$result->cache_status_codes_option = $cache_status_codes_option;

					if ( $unique ) {
						$references[ $result->from_site_id ][ $result->from_post_id . '-' . $result->from_post_type ] = new Reference( $result );
					} else {
						$references[ $result->from_site_id ][] = new Reference( $result );
					}

				}
			}

			if ( is_multisite() ) {
				restore_current_blog();
			}
		}

		return $references;
	}

}