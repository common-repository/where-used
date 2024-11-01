<?php

namespace WhereUsed;

// Prevent Direct Access
( defined( 'ABSPATH' ) ) || die;

use WhereUsed\HelpersLibrary\REQUEST;

/**
 * Class Reference
 *
 * @package WhereUsed
 * @since   1.0.0
 *
 * @link https://whereused.com/docs/classes/reference/
 */
class Reference extends Row {

	// From Source
	protected string $from_url = ''; // Original URL provided
	protected string $from_url_full = ''; // The full version of the original URL

	protected bool $from_is_full = false; // Is the original URL a full URL?
	protected string $from_url_base = ''; // The base version of the full URL
	protected string $from_relative = ''; // The relative version of the URL
	protected bool $from_is_relative = false; // Is the original URL a relative URL?
	protected bool $from_is_public = false; // Can it be accessed publicly?
	protected bool $from_is_external = false; // Is it a link from an external website beyond the multisite network?

	// To Destination
	protected bool $to_is_full = false; // Is the original to URL a full URL?
	protected string $to_url_base = ''; // The base version of the to full URL
	protected string $to_relative = ''; // The relative version of the URL
	protected bool $to_is_relative = false; // Is the original to URL a relative URL?
	protected bool $to_is_public = false; // Can the to reference be accessed publicly?
	protected bool $to_is_external = false; // Is it a link to an external website beyond the multisite network?
	protected bool $to_is_mail = false; // Is it a mail link?
	protected bool $to_is_tel = false; // Is it a telephone number?

	// Controls
	protected bool $load_only = false; // Controls whether it only loads the data given to the object on construct
	protected bool $set_public = true; // Controls whether the public properties are set during the construct of the object
	protected bool $set_full_urls = true; // Controls whether the full url properties are set during the construct of the object
	protected bool $set_relative = true; // Controls whether the relative url properties are set during the construct of the object
	protected bool $set_post_id = true; // Controls whether the post IDs are found and set for the object during construct
	protected bool $set_status = true; // Controls whether the status properties are set during object construct
	protected bool $set_redirection = true; // Controls whether the redirection data is retrieved and set during construct
	protected string $cache_status_codes_option = ''; // The place in the DB where we temporarily cache status code results for a scan
	protected bool $queue_status_code_check = false; // Forces the status codes to be queued and refreshed via cron job later


	/**
	 * @package WhereUsed
	 * @since   1.0.0
	 *
	 * @param array|object $data
	 */
	function __construct( $data ) {

		if ( ! empty( $data ) ) {
			$this->load( $data );
		}

		$this->set_cache( 'from' );
		$this->set_cache( 'to' );

		if ( $this->set_public ) {
			$this->set_public( 'from' );
			$this->set_public( 'to' );
		}

		if ( ! $this->load_only ) {

			if ( ! $this->from_how ) {
				if ( $this->to_post_id ) {
					// Set id early if provided
					$this->set( 'from_how', 'id' );
				} else {
					$this->set( 'from_how', 'url' );
				}
			}

			if ( $this->set_full_urls ) {
				$this->set_full_urls();
			}
			if ( $this->set_relative ) {
				$this->set_relative();
			}
			if ( $this->set_post_id ) {
				$this->set_post_id();
			}
			if ( $this->set_status ) {
				$this->set_status();
			}
			if ( $this->set_redirection ) {
				$this->set_redirection();
			}
		}

	}

	/**
	 * Sets the post objects so we can reference them again easily
	 *
	 * @param string $type
	 *
	 * @return void
	 */
	private function set_cache( string $type = '' ) {

		$post_id = $this->get( $type . '_post_id' );
		$post_type = $this->get( $type . '_post_type' );

		if ( $post_id && empty( $this->cache[ $type . '_post' ] ) ) {
			if ( is_multisite() ) {
				switch_to_blog( $this->get( $type . '_site_id' ) );
			}

			if ( 'user' === $post_type ) {
				$this->cache[ $type . '_post' ] = get_user_by( 'ID', $post_id );
			} elseif ( 'taxonomy term' === $post_type ) {
				$this->cache[ $type . '_post' ] = Get::term_by_id( $post_id );
			} elseif ( 'menu' === $post_type ) {
				// We are dealing with a menu
				$this->cache[ $type . '_post' ] = Menu::get_by_id( $post_id );
			} else {
				// We are assuming it's a post
				$this->cache[ $type . '_post' ] = get_post( $post_id );
			}

			if ( is_multisite() ) {
				restore_current_blog();
			}
		}

	}

	/**
	 * Convert an attachment URL into a post ID.
	 *
	 * @note    This method is an upgraded version of https://developer.wordpress.org/reference/functions/attachment_url_to_postid/
	 * @link    See ticket https://core.trac.wordpress.org/ticket/51058#comment:5
	 *
	 * @package WhereUsed
	 * @since   1.0.0
	 *
	 * @param string $url The URL to resolve.
	 *
	 * @return int The found post ID, or 0 on failure.
	 */
	public static function attachment_url_to_postid( string $url ): int {

		global $wpdb;

		$post_id = 0;

		if ( $attachment_id_pos = strpos( $url, '?attachment_id=' ) ) {
			// "plain" url (permalinks not enabled)
			$starting_pos = $attachment_id_pos + 15; // 15 = length of "?attachment_id="
			$post_id = substr( $url, $starting_pos );
		} elseif ( $attachment_id = url_to_postid( $url ) ) {
			// this is an attachment permalink
			$post_id = $attachment_id;
		} else {

			$dir = wp_get_upload_dir();
			$path = $url;
			$site_url = parse_url( $dir['url'] );
			$image_path = parse_url( $path );
			$meta_values = [];

			// compensate for sizes
			// reduce path to original filename
			$path_parts = explode( '-', $image_path['path'] );
			$filename = end( $path_parts );
			$found = preg_match( '/^[\d]+x[\d]+(\.[0-9a-zA-Z]{3,4})/', $filename, $matches );

			if ( $found ) {
				// size found
				$meta_values[] = str_replace( '-' . $matches[0], $matches[1], $path );
				$meta_values[] = str_replace( '-' . $matches[0], '-scaled' . $matches[1], $path );
			} else {
				// no size found
				$meta_values[] = $path;
				$extension = '.' . pathinfo( $path, PATHINFO_EXTENSION );
				$meta_values[] = str_replace( $extension, '-scaled' . $extension, $path );
			}

			foreach ( $meta_values as $key => $value ) {
				// Force the protocols to match if needed.
				if ( isset( $image_path['scheme'] ) && ( $image_path['scheme'] !== $site_url['scheme'] ) ) {
					$meta_values[ $key ] = str_replace( $image_path['scheme'], $site_url['scheme'], $value );
				}
				// remove baseurl from path
				if ( 0 === strpos( $meta_values[ $key ], $dir['baseurl'] . '/' ) ) {
					$meta_values[ $key ] = substr( $meta_values[ $key ], strlen( $dir['baseurl'] . '/' ) );
				}
			}

			$values = [];
			$values[] = $wpdb->postmeta;

			$meta_values_placholders = [];
			foreach ( $meta_values as $meta_value ) {
				$values[] = $meta_value;
				$meta_values_placholders[] = '`meta_value` = "%' . count( $values ) . '$s"';
			}

			$results = $wpdb->get_results( $wpdb->prepare( 'SELECT `post_id`, `meta_value` FROM `%1$s` 
			WHERE `meta_key` = "_wp_attached_file" AND (' . implode( ' OR ', $meta_values_placholders ) . ')', $values ) );

			if ( ! empty( $results ) ) {
				// Use the first available result, but prefer a case-sensitive match, if exists.
				$post_id = reset( $results )->post_id;

				if ( count( $results ) > 1 ) {
					foreach ( $results as $result ) {
						if ( in_array( $result->meta_value, $meta_values ) ) {
							$post_id = $result->post_id;
							break;
						}
					}
				}
			}

		}

		/**
		 * Filters an attachment ID found by URL.
		 *
		 * @param int|null $post_id The post_id (if any) found by the function.
		 * @param string   $url     The URL being looked up.
		 */
		return (int) apply_filters( 'attachment_url_to_postid', $post_id, $url );
	}

	/**
	 * Creates the full link based on the original link
	 *
	 * @package WhereUsed
	 * @since   1.0.0
	 */
	private function set_full_urls() {

		// Set all the URLs
		$this->set_full_url( 'from' );
		$this->set_full_url( 'to' );
		$this->to_url_absolute = static::convert_to_absolute_url( $this->to_url_full );

	}

	/**
	 * Creates the full link based on the original link
	 *
	 * @package WhereUsed
	 * @since   1.0.0
	 */
	private function set_full_url( string $target ) {

		if ( 'from' === $target ) {
			// from
			$post_id = $this->from_post_id;
			$post_type = $this->from_post_type;
			$this->cache['from_post'] = $this->cache['from_post'] ?? '';
			$this->cache['from_post'] = $this->cache['from_post'] ?: get_post( $this->from_post_id );
			$cache = $this->cache['from_post'];
			$url = $this->from_url;
		} else {
			// to
			$post_id = $this->to_post_id;
			$post_type = $this->to_post_type;
			$this->cache['to_post'] = $this->cache['to_post'] ?? '';
			$this->cache['to_post'] = $this->cache['to_post'] ?: get_post( $this->to_post_id );
			$cache = $this->cache['to_post'];
			$url = $this->to_url;
		}

		if ( $post_id ) {

			// Create the full URL from the given id

			if ( Get::using_network_media_library() && $post_type === 'attachment' ) {
				$current_bid = \Network_Media_Library\get_site_id();
			} else {
				// Use the current site as the domain
				$current_bid = WHEREUSED_CURRENT_SITE_ID;
			}

			if ( is_multisite() ) {
				switch_to_blog( $current_bid );
			}

			if ( $post_id && $cache ) {

				if ( ! $post_type ) {
					$this->set( $target . '_post_type', $cache->post_type );
				}

				// Set Full version

				if ( $this->is_public( $target ) ) {
					// Only create full URL if the post can have a permalink

					if ( 'to' == $target && 'attachment' === $this->to_post_type ) {

						if ( $this->to_url ) {
							// Build full url from provided URL
							$this->set( $target . '_url_full', static::get_full_url( $this->to_url ) );
						} else {
							// Default file location
							$image_attributes = wp_get_attachment_image_src( $post_id );
							$image_src = $image_attributes[0] ?? '';

							$this->set( $target . '_url_full', $image_src );
						}

					} else {
						// Use permalink
						$this->set( $target . '_url_full', get_permalink( $cache ) );
					}

				}
				$this->set( $target . '_is_full', false );
				$this->set( $target . '_is_relative', false );
				$this->set( $target . '_is_external', false );
				$this->set( $target . '_site_id', $current_bid );
			}

			if ( is_multisite() ) {
				restore_current_blog();
			}

		} elseif ( $url ) {

			// All we have is the URL

			if ( 'from' === $target ) {
				// Get full URL and the additional stats regarding the original URL
				$url_stats = static::get_full_url( $url, '', true );
			} else {
				// target to

				if ( str_starts_with( $url, '#' ) ) {
					// Provided URL is an anchor

					if ( 'menu' == $this->from_post_type || ! $this->from_post_id ) {
						// Default to site URL as base
						$url_stats = static::get_full_url( '/' . $url, get_site_url(), true );
					} else {
						// Attempt to get the permalink of the post that is referencing the anchor link
						$permalink = get_permalink( $this->from_post_id );

						if ( $permalink ) {
							// Use permalink with anchor link
							$url_stats = static::get_full_url( $permalink . $url, '', true );
						} else {
							// Fall back to site URL as base
							$url_stats = static::get_full_url( '/' . $url, get_site_url(), true );
						}
					}

				} else {
					// Build full url from provided URL
					$url_stats = static::get_full_url( $url, '', true );
				}

			}

			// Let's set all the values discovered during the creation of the full URL
			if ( ! empty( $url_stats ) ) {
				foreach ( $url_stats as $property => $value ) {
					$this->set( $target . '_' . $property, $value );
				}
			}
		}

	}

	/**
	 * Returns the full url from a given relative url so that it can be traced
	 *
	 * @package WhereUsed
	 * @since   1.0.0
	 *
	 * @param string $url
	 *
	 * @return string | array
	 */
	public static function get_full_url( string $url, $base = '', $stats = false ) {

		// Let's create the full URL from the given original link

		// This is the variable that will hold all the stats about this URL
		$url_stats = [];
		$url_stats['is_full'] = ''; // Mark as already a full URL
		$url_stats['is_relative'] = ''; // Mark as initally relative link
		$url_stats['is_external'] = ''; // Mark as an outgoing external link
		$url_stats['is_mail'] = ''; // Mark as a mail link
		$url_stats['is_tel'] = ''; // Mark as a telephone link
		$url_stats['site_id'] = ''; // mark the internal site id associated with this URL
		$url_stats['url_base'] = $base; // Base for a relative URL

		if ( ! $url_stats['url_base'] ) {
			// Fall back on the current page the user is on
			$current_site = Get::current_site();

			$scheme = ( is_ssl() ) ? 'https://' : 'http://';
			$url_stats['url_base'] = $scheme . $current_site->domain . REQUEST::SERVER_text_field( 'REQUEST_URI' );
		}

		$parsed_url = parse_url( $url );

		$scheme = $parsed_url["scheme"] ?? '';
		$host = $parsed_url["host"] ?? '';

		$sites = Get::sites();

		// Detect Full Original URL
		if ( $scheme || $host ) {

			// Set Full version
			$url_stats['url_full'] = $url;
			$url_stats['is_full'] = true;
			$url_stats['is_relative'] = false;
			$url_stats['is_external'] = true; // Set as default for this scenario

			if ( 'mailto' === $scheme ) {
				$url_stats['is_mail'] = true;
			} elseif ( 'tel' === $scheme ) {
				$url_stats['is_tel'] = true;
			} elseif ( $host ) {
				// Check to see if this full URL is local

				if ( ! $scheme ) {
					$scheme = ( is_ssl() ) ? 'https' : 'http';
					$url_stats['url_full'] = $scheme . '://' . $url;
				}

				// Relative scheme
				if ( substr( $url, 0, 2 ) == '//' ) {
					// Starts with //
					$url_stats['url_full'] = $scheme . ':' . $url;
				}

				foreach ( $sites as $site ) {
					if ( $host === $site->domain ) {
						// Local site
						$url_stats['is_external'] = false;
						$url_stats['site_id'] = $site->blog_id;
						break;
					}
				}
			}
		} else {

			// Create the full url from this relative URL
			$url_stats['is_relative'] = true;
			$url_stats['site_id'] = WHEREUSED_CURRENT_SITE_ID;

			if ( in_array( substr( $url, 0, 1 ), [
				'#',
				'?',
			] ) ) {
				// The URL is relative and referencing itself

				// Create full URL
				$url_stats['url_full'] = $url_stats['url_base'] . $url;

			} else {
				// Ensure there is a starting slash
				$starting_slash = ( substr( $url, 0, 1 ) == '/' ) ? $url : '/' . $url;

				$scheme = ( is_ssl() ) ? 'https://' : 'http://';

				// Create full URL
				$url_stats['url_full'] = $scheme . $sites[ WHEREUSED_CURRENT_SITE_ID ]->domain . $starting_slash;
			}

		}

		$pared_full_url = parse_url( $url_stats['url_full'] );

		// Prevent domain from having a trailing slash
		if ( isset( $pared_full_url['path'] ) && '/' == $pared_full_url['path'] && ! isset( $pared_full_url['query'] ) && ! isset( $pared_full_url['fragment'] ) ) {
			$url_stats['url_full'] = untrailingslashit( $url_stats['url_full'] );
		}

		return ( $stats ) ? $url_stats : $url_stats['url_full'];
	}

	/**
	 * Removes the hashtag from the URL so that we do not trace a URL multiple times if the link is simply referring to itself
	 *
	 * @package  WhereUsed
	 * @since    1.0.0
	 *
	 * @param string $url Must be a full url
	 *
	 * @return string
	 */
	public static function convert_to_absolute_url( string $url, bool $with_fragment = false ): string {

		$absolute_url = '';

		if ( $url ) {
			$parsed_url = parse_url( $url );

			$scheme = isset( $parsed_url['scheme'] ) ? $parsed_url['scheme'] . '://' : '';
			$host = isset( $parsed_url['host'] ) ? $parsed_url['host'] : '';
			$port = isset( $parsed_url['port'] ) ? ':' . $parsed_url['port'] : '';
			$user = isset( $parsed_url['user'] ) ? $parsed_url['user'] : '';
			$pass = isset( $parsed_url['pass'] ) ? ':' . $parsed_url['pass'] : '';
			$pass = ( $user || $pass ) ? "$pass@" : '';
			$path = isset( $parsed_url['path'] ) ? $parsed_url['path'] : '';
			$query = isset( $parsed_url['query'] ) ? '?' . $parsed_url['query'] : '';

			if ( $with_fragment ) {
				$fragment = isset( $parsed_url['fragment'] ) ? '#' . $parsed_url['fragment'] : '';
			} else {
				$fragment = '';
			}

			$absolute_url = $scheme . $user . $pass . $host . $port . $path . $query . $fragment;
		}

		return $absolute_url;
	}

	/**
	 * Creates a standardized full URL from a given relative URL.
	 * It fixes issues with the URL such as adding trailing slashes
	 * and rebuilding the URL from its parts
	 * (not a good idea since we want to detect 301s).
	 *
	 * @package  WhereUsed
	 * @since    1.0.0
	 *
	 * @internal I wrote this functionality to replace get_full_url() but it
	 * seems risky to rebuild the URL instead of just tracing the URL provided
	 * once the protocol and host are added into the relative URL.
	 * Will keep this here in case we change our mind about this.
	 *
	 * @param string $url
	 *
	 * @return string | array
	 */
	public static function format_full_url( string $url, $base = '', $stats = false ) {

		// Let's create the full URL from the given original link

		// This is the variable that will hold all the stats about this URL
		$url_stats = [];
		$url_stats['is_full'] = '';
		$url_stats['is_relative'] = '';
		$url_stats['is_external'] = '';
		$url_stats['is_mail'] = '';
		$url_stats['site_id'] = '';
		$url_stats['url_base'] = $base;

		$parsed_url = parse_url( $url );

		$scheme = $parsed_url["scheme"] ?? '';
		$host = $parsed_url["host"] ?? '';
		$path = $parsed_url["path"] ?? '';
		$query = $parsed_url["query"] ?? '';
		$fragment = $parsed_url["fragment"] ?? '';

		// Detect Full Original URL
		if ( $scheme || $host ) {

			// Set Full version
			$url_stats['is_full'] = true;
			$url_stats['is_relative'] = false;
			$url_stats['is_external'] = true; // Set as default for this scenario

			if ( $scheme === 'mailto' ) {
				$url_stats['is_mail'] = true;
			}

			// Check to see if this full URL is local
			if ( $host ) {

				if ( ! $scheme && substr( $url, 0, 2 ) == '//' ) {
					// Starts with //
					$scheme = 'https';
				}

				$sites = Get::sites();

				foreach ( $sites as $site ) {
					if ( $host === $site->domain ) {
						// Local site
						$url_stats['is_external'] = false;
						$url_stats['site_id'] = $site->blog_id;
						break;
					}
				}
			}
		} else {

			// Detected relative URL
			$url_stats['is_relative'] = true;
			$url_stats['site_id'] = WHEREUSED_CURRENT_SITE_ID;

			if ( ! $url_stats['url_base'] ) {
				if ( REQUEST::SERVER_text_field( 'HTTPS' ) == 'on' || REQUEST::SERVER_text_field( 'HTTPS' ) == 1 || REQUEST::SERVER_text_field( 'HTTP_X_FORWARDED_PROTO' ) == 'https' ) {
					$protocol = 'https://';
				} else {
					$protocol = 'http://';
				}
				$url_stats['url_base'] = $protocol . REQUEST::SERVER_text_field( 'HTTP_HOST' ) . REQUEST::SERVER_text_field( 'REQUEST_URI' );
			}

			$base_url = parse_url( $url_stats['url_base'] );
			$scheme = $base_url["scheme"] ?? '';
			$host = $base_url["host"] ?? '';

			if ( substr( $path, 0, 1 ) != '/' ) {
				// current page relative
				$base_path = $base_url["path"] ?? '';
				$path = $base_path . $path;
			}

		}

		$url_stats['url_full'] = ''; // start fresh

		if ( $scheme ) {
			if ( in_array( $scheme, [
				'http',
				'https',
			] ) ) {
				$url_stats['url_full'] = $scheme . '://' . $host . '/';
			} else {
				$url_stats['url_full'] = $scheme . ':';
			}
		}

		if ( $path || $query || $fragment ) {

			if ( $path ) {
				// Prevent a double slash
				$url_stats['url_full'] = untrailingslashit( $url_stats['url_full'] );
				$url_stats['url_full'] .= $path;
			}

			if ( ! $url_stats['is_mail'] ) {

				// Grab a file extension if exists
				$parts = explode( '.', $path );
				$ext = end( $parts );

				if ( strlen( $ext ) > 4 ) {
					// Not dealing with a file extension
					$url_stats['url_full'] = trailingslashit( $url_stats['url_full'] );
				}

				if ( $query ) {
					$url_stats['url_full'] .= '?' . $query;
				}
				if ( $fragment ) {
					$url_stats['url_full'] .= '#' . $fragment;
				}

			}

		}

		return ( $stats ) ? $url_stats : $url_stats['url_full'];
	}

	/**
	 * Finds and sets the post id of the link if it is local (within this network)
	 *
	 * @package WhereUsed
	 * @since   1.0.0
	 *
	 * @param int $post_id
	 */
	private function set_post_id( int $post_id = 0 ): void {

		if ( $this->to_post_id ) {
			return;
		}

		// Only get ID for local link
		if ( ! $post_id && ! $this->to_is_external ) {

			if ( is_multisite() ) {
				switch_to_blog( $this->to_site_id );
			}

			// Get page id
			$post_id = url_to_postid( $this->to_url_full );

			if ( ! $post_id ) {

				// It might be an attachment

				if ( Get::using_network_media_library() ) {
					$current_bid = \Network_Media_Library\get_site_id();
					switch_to_blog( $current_bid );
				}

				$post_id = static::attachment_url_to_postid( $this->to_url_full );

				if ( $post_id ) {

					$this->cache['to_post'] = get_post( $post_id );

					if ( $this->cache['to_post'] ) {
						$this->set( 'to_post_type', $this->cache['to_post']->post_type );

						if ( Get::using_network_media_library() ) {
							$this->set('to_site_id', $current_bid );
						}

						$this->is_public(); // sets is_public when checking
					}
				}

				if ( Get::using_network_media_library() ) {
					restore_current_blog();
				}

			}

			// @ todo if no post_id by this point check to see if it is a taxonomy term

			if ( is_multisite() ) {
				restore_current_blog();
			}

		}

		if ( $post_id ) {
			static::set( 'to_post_id', $post_id );

			if ( empty( $this->cache['to_post'] ) ) {

				if ( is_multisite() ) {
					switch_to_blog( $this->to_site_id );
				}

				// Let's set the post_type and whether it is public or not
				$this->set_cache( 'to' );

				if ( $this->cache['to_post'] ) {
					$this->set( 'to_post_type', $this->cache['to_post']->post_type );
					$this->is_public(); // sets is_public when checking
				}

				if ( is_multisite() ) {
					restore_current_blog();
				}
			}
		}

	}

	/**
	 * Tells you if the post type of the destination is publicly accessible.
	 * The default value is true.
	 *
	 * @package WhereUsed
	 * @since   1.0.0
	 *
	 * @return bool
	 */
	public function is_public( string $target = 'to' ): bool {

		return ( 'from' === $target ) ? $this->from_is_public : $this->to_is_public;

	}

	/**
	 * Determines which URLs are public and sets the properties
	 *
	 * @return void
	 */
	private function set_public( string $type = '' ): void {

		$post_type = $this->get( $type . '_post_type' );

		// from source
		if ( $post_type ) {
			if ( is_multisite() ) {
				switch_to_blog( $this->from_site_id );
			}

			if ( 'taxonomy term' === $post_type ) {

				// grab term object from cache
				$term = $this->cache[ $type . '_post' ];

				if ( $term ) {
					// Term exists

					// grab the taxonomy and determine if it is public
					$taxonomy = get_taxonomy( $term->taxonomy );

					if ( $taxonomy ) {
						$this->set( $type . '_is_public', $taxonomy->public );
					}
				}

			} elseif ( 'user' === $this->from_post_type || 'menu' === $this->from_post_type ) {
				// Users and Menus are not public
				$this->set( $type . '_is_public', false );
			} else {
				// We are probably dealing with a post
				$post_types = get_post_types( [
					'public' => true,
				] );

				$is_public = 'attachment' === $post_type || isset( $post_types[ $post_type ] );
				$this->set( $type . '_is_public', $is_public );

			}

			if ( is_multisite() ) {
				restore_current_blog();
			}

		} elseif ( 'to' === $type && ( $this->to_is_external || ( ! $this->to_is_external && $this->to_post_type == '' ) ) ) {
			// Detected an external link
			$this->set( $type . '_is_public', true );
		}

	}

	/**
	 * Gets the status code for the full link
	 *
	 * @package WhereUsed
	 * @since   1.0.0
	 */
	private function set_status(): void {

		// Requirements
		if ( $this->is_public() && // Must be public
			! $this->to_is_mail && // Cannot be mail link
			! $this->to_is_tel && // Cannot be mail link
			'block' != $this->to_type && // Cannot be a block
			'wp_block' != $this->to_post_type && // Cannot be a reusable block
			$this->to_url_full ) {

			if ( $this->queue_status_code_check ) {

				// Bypass checking status codes as we will do that later in background process

				$status_codes_cache = get_option( $this->cache_status_codes_option );

				if ( empty( $status_codes_cache ) ) {
					$status_codes_cache = [];
				}

				if ( isset( $status_codes_cache[ $this->to_url_absolute ] ) ) {
					$response = $status_codes_cache[ $this->to_url_absolute ];
				} else {
					// Not found in cache
					$response = [
						'to_url_status' => 202, // Request is pending
						'to_url_redirection_url' => '',
						'to_url_status_date' => '1970-01-01 00:00:00',
					];
				}

				// Check to see if date is expired 10 minutes from original date
				$expired_date = strtotime( $response['to_url_status_date'] ) + (60 * 10);

				Debug::log('compare dates: '.date('Y-m-d H:i:s', time()).' > '.date('Y-m-d H:i:s', $expired_date));

				if( time() > $expired_date ) {
					// Date is expired and we are marking status code to be rechecked
					$response['to_url_status_date'] = '1970-01-01 00:00:00';
				}
			} else {
				$response = Scan::status_check_update( $this->to_url_full, [], $this->cache_status_codes_option );
			}

			$this->set( 'to_url_status', $response['to_url_status'] );
			$this->set( 'to_url_status_date', $response['to_url_status_date'] );
			$this->set( 'redirection_url', $response['to_url_redirection_url'] );

			Debug::log('status-date: '. $this->to_url_status_date);
		}

	}

	/**
	 * Sets the relative version of the URL
	 *
	 * @package WhereUsed
	 * @since   1.0.0
	 */
	private function set_relative(): void {

		if ( ! $this->to_is_external && ! $this->to_relative && $this->to_url_full && ! $this->to_is_mail ) {
			// Create relative from full version

			$parsed_url = parse_url( $this->to_url_full );

			$relative = $parsed_url['path'] ?? '';
			$relative .= ( isset( $parsed_url['query'] ) && $parsed_url['query'] ) ? '?' . $parsed_url['query'] : '';
			$relative .= ( isset( $parsed_url['fragment'] ) && $parsed_url['fragment'] ) ? '#' . $parsed_url['fragment'] : '';

			$this->set( 'to_relative', $relative );
		}

	}

	/**
	 * Sets the redirection if one exists
	 *
	 * @package WhereUsed
	 * @since   1.0.0
	 */
	private function set_redirection(): void {

		// Must be internal link
		if ( Get::using_redirection_plugin() && ! $this->to_is_external ) {

			// Check to see IF we are dealing with a redirect
			if ( $this->to_url_status >= 300 && $this->to_url_status <= 399 ) {

				$redirections = Redirection::get_redirections( $this->to_url_full, $this->to_post_type, 'url', $this->to_site_id, $this->to_post_id );
				$redirections = $redirections[ $this->to_site_id ] ?? [];

				if ( ! empty( $redirections ) ) {

					foreach ( $redirections as $redirection ) {
						$this->set( 'redirection_id', $redirection->get( 'id' ) );
						$this->set( 'redirection_site_id', $redirection->get( 'site_id' ) );

						if ( ! $this->to_post_id ) {

							// Grab the details from the redirected destination
							$redirection_to = new Reference( [
								'to_url' => $this->get( 'redirection_url' ),
								'cache_status_codes_option' => $this->get( 'cache_status_codes_option' ),
							] );
							$this->set( 'to_post_id', $redirection_to->get( 'to_post_id' ) );
							$this->set( 'to_site_id', $redirection_to->get( 'to_site_id' ) );
							$this->set( 'to_post_type', $redirection_to->get( 'to_post_type' ) );

							// Override the redirection rule in case WP did a 301 for trailing slash and we detected a redirect match on the trailing slash URL.
							// This allows us to show the end destination URL of the redirection rule
							$redirection_url = $redirection_to->get( 'redirection_url' );
							if ( strstr( '$', $redirection_url ) === false && $redirection_url != '' ) {
								$this->set( 'redirection_url', $redirection_url );
							}
						}

						// We can only display 1 redirect (there shouldn't be multiple)
						break;

					}
				}

			}
		}

	}

	/**
	 * Converts a redirection object from the Redirection table into a reference so that we can see the rule itself
	 *
	 * @param array $redirections
	 *
	 * @return array
	 */
	public static function convert_redirections_to_references( array $redirections, string $cache_status_codes_option = '', $queue_status_check = false ): array {
		$references = [];

		if ( ! empty( $redirections ) ) {
			foreach ( $redirections as $site_redirections ) {
				foreach ( $site_redirections as $redirection ) {

					if ( ! strstr( $redirection->get( 'destination' ), '$' ) ) {
						$data = [
							'redirection_id' => $redirection->get( 'id' ),
							'redirection_site_id' => $redirection->get( 'site_id' ),
							'redirection_url' => $redirection->get( 'destination' ),
							'from_where' => 'redirection',
							'to_url' => $redirection->get( 'rule_destination' ),
							'cache_status_codes_option' => $cache_status_codes_option,
							'queue_status_code_check' => $queue_status_check,
						];

						$references[] = new Reference( $data );
					}

				}
			}
		}

		return $references;
	}

}