<?php

namespace WhereUsed;

// Prevent Direct Access
( defined( 'ABSPATH' ) ) || die;

use WhereUsed\HelpersLibrary\Get as Library_Get;
use function Network_Media_Library\get_site_id;

/**
 * Class Get - Get the information you need
 */
class Get extends Library_Get {

	/**
	 * Abstract method to get the tables.
	 * NOTICE: Table name should NOT include site prefix.
	 */
	public static function tables(): array {

		return [
			'whereused_references' => [
				'name' => 'whereused_references',
				'columns' => [
					[
						'name' => 'id',
						'type' => 'bigint',
						'null' => false,
						'auto-increment' => true,
					],
					[
						'name' => 'from_post_id',
						'type' => 'bigint',
						'null' => false,
						'default' => '0',
					],
					[
						'name' => 'from_site_id',
						'type' => 'smallint',
						'null' => false,
						'default' => '0',
					],
					[
						'name' => 'from_post_type',
						'type' => 'varchar(20)',
						'null' => false,
					],
					[
						'name' => 'from_how',
						'type' => 'varchar(20)',
						'null' => false,
					],
					[
						'name' => 'from_where',
						'type' => 'varchar(20)',
						'null' => false,
					],
					[
						'name' => 'from_where_key',
						'type' => 'varchar(100)',
						'null' => false,
					],
					[
						'name' => 'to_post_id',
						'type' => 'bigint',
						'null' => false,
						'default' => '0',
					],
					[
						'name' => 'to_site_id',
						'type' => 'smallint',
						'null' => false,
						'default' => '0',
					],
					[
						'name' => 'to_type',
						'type' => 'varchar(20)',
						'null' => false,
					],
					[
						'name' => 'to_url',
						'type' => 'varchar(2083)',
						'default' => '',
					],
					[
						'name' => 'to_url_full',
						'type' => 'varchar(2083)',
						'default' => '',
					],
					[
						'name' => 'to_url_absolute',
						'type' => 'varchar(2083)',
						'default' => '',
					],
					[
						'name' => 'to_url_status',
						'type' => 'smallint',
						'null' => false,
						'default' => '0',
					],
					[
						'name' => 'to_url_status_date',
						'type' => 'datetime',
						'null' => true,
					],
					[
						'name' => 'to_anchor_text',
						'type' => 'text',
						'null' => false,
					],
					[
						'name' => 'to_block_name',
						'type' => 'varchar(100)',
						'null' => false,
					],
					[
						'name' => 'to_post_type',
						'type' => 'varchar(20)',
						'null' => false,
					],
					[
						'name' => 'redirection_id',
						'type' => 'bigint',
						'null' => false,
						'default' => '0',
					],
					[
						'name' => 'redirection_site_id',
						'type' => 'smallint',
						'null' => false,
						'default' => '0',
					],
					[
						'name' => 'redirection_url',
						'type' => 'varchar(2083)',
						'default' => '',
					],
				],
				'index' => [
					'primary' => 'id',
					'key' => [
						'from_post_id',
						'from_post_type',
						'from_where',
						'to_post_id',
						'to_post_type',
					],
				],
			],
		];
	}

	/**
	 * Gets all the URL variations of a given URL
	 *
	 * @param array  $urls
	 * @param string $type
	 * @param int    $post_id
	 *
	 * @return array
	 */
	public static function all_urls( array $urls = [], string $type = '', int $post_id = 0 ): array {

		global $post;

		if ( ! $post_id && isset( $post->ID ) ) {
			$post_id = $post->ID;
		}

		$urls = ( $type === 'attachment' && $post_id ) ? static::attachment_urls( $post_id ) : $urls;

		$all_urls = [];

		$primary_network_media_site_id = ( Get::using_network_media_library() ) ? get_site_id() : 0;

		if ( isset( $urls[0] ) ) {

			$sites = static::sites();

			// Build full list of URLs
			foreach ( $urls as $url ) {

				// Include both non-secure and secure versions
				$non_protocol_url = str_replace( [
					'https://',
					'http://',
				], '', $url );
				$all_urls[] = 'http://' . $non_protocol_url;
				$all_urls[] = static::get_no_trailing_slash_url( 'http://' . $non_protocol_url );

				$all_urls[] = 'https://' . $non_protocol_url;
				$all_urls[] = static::get_no_trailing_slash_url( 'https://' . $non_protocol_url );

				// Let's Loop through each site to build attachment URLs
				foreach ( $sites as $site ) {

					// Search for relative URL usage
					if ( WHEREUSED_CURRENT_SITE_ID === $site->blog_id || Get::using_network_media_library() ) {

						// We must have double quote at the beginning to match relative href
						$relative = static::relative_url( $url, $site->domain, $type );
						$all_urls[] = $relative;
						$all_urls[] = static::get_no_trailing_slash_url( $relative );

						// Add all network versions of the URL for attachments
						if ( $type === 'attachment' && Get::using_network_media_library() ) {

							$network_url = $url;
							if ( $primary_network_media_site_id !== $site->blog_id ) {
								$network_url = str_replace( $sites[ $primary_network_media_site_id ]->domain, $site->domain, $url );
							}

							$all_urls[] = $network_url;
							$all_urls[] = static::get_no_trailing_slash_url( $network_url );
						}

					}

				}

			}

		}

		// @todo: Compensate For Known Bug: sometimes the url starts with the domain name (missing the protocol), which makes the URL invalid
		if ( ! empty( $all_urls ) ) {
			foreach ( $all_urls as $key => $url ) {
				$first_char = substr( $url, 0, 1 );
				if ( $first_char != '/' && $first_char != 'h' ) {
					// We have an invalid relative URL: remove it
					unset( $all_urls[ $key ] );
				}
			}
		}

		// Get rid of duplicates
		return array_unique( $all_urls );

	}

	/**
	 * Get all the attachment URL size URLs
	 *
	 * @param int $attachment_id
	 *
	 * @return array
	 */
	private static function attachment_urls( int $attachment_id ): array {

		// Main Attachment URL
		$attachment_urls = [ wp_get_attachment_url( $attachment_id ) ];

		// Add Permalink
		$attachment_urls[] = get_permalink( $attachment_id );

		// Grab All The Sizes
		if ( wp_attachment_is_image( $attachment_id ) ) {
			foreach ( get_intermediate_image_sizes() as $size ) {
				$intermediate = image_get_intermediate_size( $attachment_id, $size );
				if ( $intermediate ) {
					$attachment_urls[] = $intermediate['url'];
				}
			}
		}

		// Ensure that we are including the attachment URL version: domain.com/?get_attachment_id=XX
		$site = Get::current_site();
		$base_url = is_ssl() ? 'https://' : 'http://';
		$attachment_urls[] = $base_url . $site->domain . '/?attachment_id=' . $attachment_id;

		return $attachment_urls;

	}

	/**
	 * Returns the URL without a trailing slash
	 *
	 * @param string $url
	 *
	 * @return string
	 */
	public static function get_no_trailing_slash_url( string $url = '' ): string {

		if ( $url ) {
			if ( substr( $url, - 1, 1 ) == '/' ) {
				// Has trailing slash. Let's include missing trailing slash
				$length = strlen( $url ) - 1;

				$url = substr( $url, 0, $length );
			}
		}

		return $url;

	}

	/**
	 * Creates a relative URL from a given URL
	 *
	 * @param string $url
	 * @param string $domain
	 * @param string $type
	 *
	 * @return string
	 */
	static function relative_url( string $url, string $domain, string $type ): string {

		$replace_me = [
			'https://',
			'http://',
			$domain,
		];

		if ( Get::using_network_media_library() ) {

			$sites = static::sites();

			$primary_network_media_site_id = get_site_id();

			// Add the main domain to the replace_me array if using Network Media Library
			if ( $type === 'attachment' && ! in_array( $sites[ $primary_network_media_site_id ]->domain, $replace_me ) ) {
				$replace_me[] = $sites[ $primary_network_media_site_id ]->domain;
			}

		}

		// Add relative URL
		return str_replace( $replace_me, '', $url );

	}

	/**
	 * Gets the html for displaying an icon
	 *
	 * @param string $name
	 *
	 * @return string
	 */
	public static function icon( string $name = 'bad' ): string {

		$icons = [
			'good' => 'yes-alt',
			'warning' => 'warning',
			'bad' => 'no-alt',
		];

		$icon = $icons[ $name ] ?? 'no-alt';

		$color = [
			'good' => 'green',
			'warning' => 'orange',
			'bad' => 'red',
		];

		$color = $color[ $name ] ?? 'orange';

		return '<span class="dashicons dashicons-' . esc_attr( $icon ) . '" style="color:' . esc_attr( $color ) . ';"></span> ';

	}

	/**
	 * Gets the unused attachments
	 *
	 * @param int $limit_start
	 * @param int $per_page
	 *
	 * @return array
	 */
	public static function unused_attachments( int $limit_start = 0, int $per_page = 25 ): array {

		global $wpdb;

		$values = [];
		$values[] = $wpdb->prefix . 'posts';
		$values[] = Get::table_name();
		$values[] = $limit_start;
		$values[] = $per_page;

		return $wpdb->get_results( $wpdb->prepare( 'SELECT SQL_CALC_FOUND_ROWS * FROM `%1$s` WHERE `post_type` = \'attachment\' AND `post_mime_type` != \'application/pdf\' AND `ID` NOT IN ( SELECT `to_post_id` from `%2$s` WHERE `to_post_id` > 0 GROUP BY `to_post_id` ) LIMIT %3$d, %4$d', $values ) );
	}

	/**
	 * List of Gutenberg core blocks that are deprecated
	 *
	 * @package WhereUsed
	 * @since   1.4.0
	 * @link https://developer.wordpress.org/block-editor/reference-guides/core-blocks/
	 *
	 * @TODO Add metabox with detected issues regarding deprecated blocks found
	 *
	 * @return array
	 */
	public static function deprecated_blocks(): array {

		return [
			'core/text-columns' => true,
			'core/post-comment' => true,
			'core/comment-author-avatar' => true,
		];

	}

	/**
	 * The blocks that are excluded from recording as references since they are either container blocks or so common that it is not helpful to know about their usage.
	 *
	 * @package WhereUsed
	 * @since   1.0.0
	 * @link    https://whereused.com/docs/hooks/whereused_ignored_blocks/
	 *
	 * @return array
	 */
	public static function ignored_blocks(): array {

		// Ignore core/image since we detect images directly
		$ignored_blocks = [
			'core/columns' => true,
			'core/column' => true,
			'core/group' => true,
			'core/heading' => true,
			'core/image' => true,
			'core/list-item' => true,
			'core/navigation-link' => true,
			'core/navigation-submenu' => true,
			'core/page-list-item' => true,
			'core/paragraph' => true,
			'core/separator' => true,
			'core/spacer' => true,
		];

		/**
		 * Filter: WhereUsed/ignored_blocks
		 *
		 * @deprecated 1.1.0
		 * @link https://whereused.com/docs/hooks/whereused-ignored_blocks/
		 * @note remove in version 2.0.0
		 */
		$ignored_blocks = apply_filters_deprecated( 'WhereUsed/ignored_blocks', [ $ignored_blocks ], '1.1.0', 'whereused_ignored_blocks', 'This hook will be removed in WhereUsed version 2.0.0' );

		/**
		 * Filter: whereused_ignored_blocks
		 *
		 * @package WhereUsed
		 * @since   1.1.0
		 * @link    https://whereused.com/docs/hooks/whereused_ignored_blocks/
		 *
		 * @return array
		 */
		return apply_filters( WHEREUSED_HOOK_PREFIX . 'ignored_blocks', $ignored_blocks );
	}

	/**
	 * Gets all known status codes with labels
	 *
	 * @return array
	 */
	public static function all_status_codes(): array {

		return [
			- 1 => 'Not Applicable',
			0 => 'No Response',
			100 => '100 Continue',
			101 => '101 Switching Protocol',
			102 => '102 Processing (WebDAV)',
			103 => '103 Early Hints',
			200 => '200 OK',
			201 => '201 Created',
			202 => '202 Accepted',
			203 => '203 Non-Authoritative Information',
			204 => '204 No Content',
			205 => '205 Reset Content',
			206 => '206 Partial Content',
			207 => '207 Multi-Status (WebDAV)',
			208 => '208 Already Reported (WebDAV)',
			226 => '226 IM Used (HTTP Delta encoding)',
			300 => '300 Multiple Choice',
			301 => '301 Moved Permanently',
			302 => '302 Found',
			303 => '303 See Other',
			304 => '304 Not Modified',
			305 => '305 Use Proxy',
			306 => '306 Unused',
			307 => '307 Temporary Redirect',
			308 => '308 Permanent Redirect',
			400 => '400 Bad Request',
			401 => '401 Unauthorized',
			402 => '402 Payment Required',
			403 => '403 Forbidden',
			404 => '404 Not Found',
			405 => '405 Method Not Allowed',
			406 => '406 Not Acceptable',
			407 => '407 Proxy Authentication Required',
			408 => '408 Request Timeout',
			409 => '409 Conflict',
			410 => '410 Gone',
			411 => '411 Length Required',
			412 => '412 Precondition Failed',
			413 => '413 Payload Too Large',
			414 => '414 URI Too Long',
			415 => '415 Unsupported Media Type',
			416 => '416 Range Not Satisfiable',
			417 => '417 Expectation Failed',
			418 => '418 I’m a teapot',
			421 => '421 Misdirected Request',
			422 => '422 Unprocessable Entity (WebDAV)',
			423 => '423 Locked (WebDAV)',
			424 => '424 Failed Dependency (WebDAV)',
			425 => '425 Too Early',
			426 => '426 Upgrade Required',
			428 => '428 Precondition Required',
			429 => '429 Too Many Requests',
			431 => '431 Request Header Fields Too Large',
			451 => '451 Unavailable For Legal Reasons',
			499 => '499 Client Closed Request',
			500 => '500 Internal Server Error',
			501 => '501 Not Implemented',
			502 => '502 Bad Gateway',
			503 => '503 Service Unavailable',
			504 => '504 Gateway Timeout',
			505 => '505 HTTP Version Not Supported',
			506 => '506 Variant Also Negotiates',
			507 => '507 Insufficient Storage (WebDAV)',
			508 => '508 Loop Detected (WebDAV)',
			510 => '510 Not Extended',
			511 => '511 Network Authentication Required',
		];

	}

	/**
	 * Tells us if we are using the Redirection plugin
	 *
	 * @return bool
	 */
	public static function using_redirection_plugin(): bool {

		return is_plugin_active( 'redirection/redirection.php' );

	}

}
