<?php

namespace WhereUsed;

// Prevent Direct Access
( defined( 'ABSPATH' ) ) || die;

use DOMDocument;
use WP_Post;
use WhereUsed\HelpersLibrary\Scan as Library_Scan;

/**
 * Class Scan
 *
 * @package WhereUsed
 * @since   1.0.0
 *
 * @TODO    : make scanner check inside of certain blocks for other blocks like
 */
final class Scan extends Library_Scan {

	/**
	 * Registers all hooks
	 *
	 * @package WhereUsed
	 * @since   1.0.0
	 */
	public static function init(): void {

		//------------- POSTS ----------------------------------------------//

		// Save Post
		add_action( 'wp_after_insert_post', [
			self::class,
			'save_post_scan',
		], 999, 2 );

		// Trash Post
		add_action( 'wp_trash_post', [
			self::class,
			'delete_post_entries',
		], 999, 1 );

		// Delete Post
		add_action( 'deleted_post', [
			self::class,
			'deleted_post',
		], 999, 2 );

		//------------- ATTACHMENTS ----------------------------------------------//

		// Save Attachment
		add_action( 'attachment_updated', [
			self::class,
			'save_post_scan',
		], 999, 1 );

		add_action( 'add_attachment', [
			self::class,
			'save_post_scan',
		], 999, 1 );

		// Cant's trash an attachment.
		// Delete attachment is the same as delete post ("deleted_post" hook)

		//------------- TERMS ----------------------------------------------//

		// Term saved, so we need to scan term
		add_action( 'saved_term', [
			self::class,
			'save_term_scan',
		], 999, 3 );

		// Term deleted, so we need to purge scan index table of this term
		add_action( 'delete_term', [
			self::class,
			'delete_term_entries',
		], 999, 1);

		//------------- USERS ----------------------------------------------//

		// User profile updated: so we need to scan user
		add_action( 'profile_update', [
			self::class,
			'user_updated',
		], 999, 1 );

		// User deletes: so we need to purge scan index table of this user
		add_action('deleted_user', [
			self::class,
			'delete_user_entries'
		], 999, 1);

		// Schedules Cron to Run
		self::schedule_cron();

		// @todo correlate post using Navigation block to wp_navigation post ID when a navigation block is found. Use hook whereused_scan_block

	}

	/**
	 * Scans the post after the post is saved
	 *
	 * @package WhereUsed
	 * @since   1.0.0
	 *
	 * @param int      $post_id
	 */
	public static function save_post_scan( int $post_id ): void {

		if( wp_is_post_revision( $post_id) || wp_is_post_autosave( $post_id ) ) {
			return;
		}

		Run::prevent_caching(true);

		$this_post = get_post( $post_id );

		if ( ! isset( $this_post->post_type ) ) {
			// Post does not exist

			Debug::log( sprintf( __( 'Post "%s" does not exist. Scan aborted for this post.', WHEREUSED_SLUG ), $post_id ) );
			return;
		} elseif ( in_array( $this_post->post_type, Get::excluded_post_types() ) ) {
			// Post type is excluded from getting scanned

			Debug::log( sprintf( __( 'Post type "%s" is excluded from getting scanned', WHEREUSED_SLUG ), $this_post->post_type ) );

			return;
		} elseif ( in_array( $this_post->post_status, [ 'trash' ] ) ) {
			// These post statuses are not scanned
			Debug::log( sprintf( __( 'Post "%s" is excluded from getting scanned due to post status: "%s"', WHEREUSED_SLUG ), $post_id, $this_post->post_status ) );
			return;
		}

		$settings = Settings::get_current_settings();

		// Make sure this post type can be scanned
		if ( ! in_array( $this_post->post_type, $settings->get( 'scan_post_types' ) ) ) {
			// Post type is not set to be scanned

			Debug::log( sprintf( __( 'Post type "%s" is not set to be scanned', WHEREUSED_SLUG ), $this_post->post_type ) );
			return;
		}

		$cache_status_codes_option = Get::cache_status_codes_option_name('save_post', $this_post->ID );

		// Scan this post
		self::scan_post( $this_post, WHEREUSED_CURRENT_SITE_ID, $cache_status_codes_option, true );

		// clear cache
		delete_option( $cache_status_codes_option );

	}

	/**
	 * Deletes the entries of the user in the scan index table when the user is deleted.
	 *
	 * @param int $user_id
	 *
	 * @return void
	 */
	public static function delete_user_entries(int $user_id ): void {

		Run::prevent_caching(true);

		// Delete all current entries in database for this post
		Reference::delete_outgoing_entries( $user_id, 'user' );

	}

	/**
	 * Scan user if their profile is updated
	 *
	 * @param int $user_id
	 *
	 * @return void
	 */
	public static function user_updated( int $user_id ): void {

		Run::prevent_caching(true);

		$user = get_user_by('id', $user_id );

		if ( isset( $user->ID ) && $user->ID ) {

			$cache_status_codes_option = Get::cache_status_codes_option_name('scan_user', $user->ID );

			Scan::scan_user( $user, WHEREUSED_CURRENT_SITE_ID, $cache_status_codes_option );

			// clear cache
			delete_option( $cache_status_codes_option );

		}

	}

	/**
	 * Rescans the taxonomy term when the term is saved
	 *
	 * @since   1.0.4
	 *
	 * @param int                $term_id       Term ID
	 * @param int                $tt_id         Term taxonomy ID
	 * @param string             $taxonomy_slug Taxonomy slug
	 *
	 * @return void
	 */
	public static function save_term_scan( int $term_id, int $tt_id, string $taxonomy_slug ): void {

		Run::prevent_caching(true);

		$settings = Settings::get_current_settings();

		$taxonomies_to_scan = $settings->get( 'scan_taxonomies', 'array' );

		if ( in_array( $taxonomy_slug, $taxonomies_to_scan) ) {
			// We need to scan this taxonomy term

			// get the term
			$term = get_term($term_id, $taxonomy_slug);

			// Return false on this filter if you want to skip this particular term
			/**
			 * Filter: whereused_save_term_scan
			 *
			 * @package WhereUsed
			 * @since 1.0.4
			 *
			 * @link https://whereused.com/docs/hooks/whereused_save_term_scan/
			 *
			 * @param \WP_Term
			 * @return bool
			 */
			if ( apply_filters( WHEREUSED_HOOK_PREFIX . 'save_term_scan', $term ) ) {

				$cache_status_codes_option = Get::cache_status_codes_option_name('scan_term', $term_id );

				Scan::scan_term( $term, WHEREUSED_CURRENT_SITE_ID, $cache_status_codes_option );

				// clear cache
				delete_option( $cache_status_codes_option );

			}
		}

	}

	/**
	 * Deletes the entries of the taxonomy term in the scan index table when the term is deleted.
	 *
	 * @since   1.0.4
	 *
	 * @param int                $term_id       Term ID
	 * @return void
	 */
	public static function delete_term_entries( int $term_id ): void {

		Run::prevent_caching(true);

		// Delete all current entries in database for this post
		Reference::delete_outgoing_entries( $term_id, 'taxonomy term' );

	}

	/**
	 * Schedules the Check Status cron
	 *
	 * @package WhereUsed
	 * @since   1.0.0
	 */
	public static function schedule_cron() {

		$settings = Settings::get_current_settings(true);

		$cron_check_status = $settings->get( 'cron_check_status' );

		if ( $cron_check_status ) {

			add_action( WHEREUSED_HOOK_PREFIX . 'check_status_codes_cron', [
				self::class,
				'check_status_codes_cron',
			] );

			// If not scheduled, schedule it to run
			if ( ! wp_next_scheduled( WHEREUSED_HOOK_PREFIX . 'check_status_codes_cron' ) ) {

				$now = strtotime( wp_date( 'Y-m-d H:i:s' ) );
				$frequency = $settings->get( 'cron_check_status_frequency' );
				$time = $settings->get( 'cron_check_status_tod' );

				if ( 'monthly' === $frequency ) {
					// Monthly Cron

					$dom = $settings->get( 'cron_check_status_dom' );
					$dom = ( $dom < 10 ) ? '0' . $dom : $dom; // Make sure we have leading zero

					// Schedule for this month
					$next_time = strtotime( date( 'Y-m-' . $dom . ' ' . $time, $now ) );

					if ( $now >= $next_time ) {
						// Schedule for next month
						$next_time = strtotime( date( 'Y-m-' . $dom . ' ' . $time, strtotime( "+1 month", $now ) ) );
					}

				} else {
					// Weekly or bi-weekly Cron

					$dow = $settings->get( 'cron_check_status_dow' );

					// Schedule for this week
					$next_time = strtotime( date( 'Y-m-d ' . $time, strtotime( $dow . ' this week' ) ) );

					if ( 'bi-weekly' === $frequency ) {
						// bi-weekly

						if ( $now >= $next_time ) {
							// Schedule two weeks from now
							$next_time = strtotime( date( 'Y-m-d' . ' ' . $time, strtotime( "+2 weeks", $next_time ) ) );
						}
					} else {
						// Weekly

						if ( $now >= $next_time ) {
							// Schedule for next week
							$next_time = strtotime( date( 'Y-m-d' . ' ' . $time, strtotime( "+1 week", $next_time ) ) );
						}
					}

				}

				// Reschedule cron
				wp_schedule_single_event( $next_time, WHEREUSED_HOOK_PREFIX . 'check_status_codes_cron' );
			}
		}

		// Maintenance Status Checks: Handles delayed checks from saving a post
		add_action( WHEREUSED_HOOK_PREFIX . 'maintenance_check_status_codes_cron', [
			self::class,
			'maintenance_check_status_codes_cron',
		] );

		register_deactivation_hook( WHEREUSED_FILE, [
			self::class,
			'deactivate_crons',
		] );

	}

	/**
	 * The maintenance status code check cron that is triggered by the saving of a post,term,user,attachment or menu
	 *
	 * @package WhereUsed
	 * @since 1.4.0
	 *
	 * @return void
	 */
	public static function maintenance_check_status_codes_cron() : void {

		global $whereused;

		Debug::log('maintenance_check_status_codes_cron');

		if ( ! wp_doing_cron() ) {
			// Not running cron, bail
			//Debug::log('not doing cron jobs: bail');
			//return;
		}

		Run::prevent_caching(true);

		if ( $whereused['scan-process']->can_we_start() ) {

			Debug::log('we can start maintenance scan');

			// Start Scan
			$whereused['scan-process']->start( 'maintenance-check-status' );

		} else {

			// There's something already running

			Debug::log('something already running');

			self::deactivate_cron_maintenance();

			$next_time = strtotime( "+2 minutes", time() );

			// Reschedule cron to try again later
			wp_schedule_single_event( $next_time, WHEREUSED_HOOK_PREFIX . 'maintenance_check_status_codes_cron' );

		}
	}

	/**
	 * Deactivates all crons on plugin disabled
	 *
	 * @package WhereUsed
	 * @since 1.4.0
	 *
	 * @return void
	 */
	public static function deactivate_crons(): void {

		self::deactivate_cron_maintenance();
		self::deactivate_cron_status_check();

	}

	/**
	 * Removes the schedule cron on plugin deactivation
	 *
	 * @package WhereUsed
	 * @since 1.4.0
	 *
	 * @return void
	 */
	public static function deactivate_cron_maintenance(): void {

		wp_clear_scheduled_hook( WHEREUSED_HOOK_PREFIX . 'maintenance_check_status_codes_cron' );

	}

	/**
	 * Removes the schedule cron on plugin deactivation
	 *
	 * @package WhereUsed
	 * @since   1.0.0
	 */
	public static function deactivate_cron_status_check(): void {

		wp_clear_scheduled_hook( WHEREUSED_HOOK_PREFIX . 'check_status_codes_cron' );

	}

	public static function check_status_codes_cron(): void {

		global $whereused;

		if ( ! wp_doing_cron() ) {
			// Not running cron, bail
			return;
		}

		Run::prevent_caching(true);

		if ( Scan::has_full_scan_ran() ) {
			if ( $whereused['scan-process']->can_we_start() ) {

				// Start Scan
				$whereused['scan-process']->start( 'check-status' );

			} else {

				// Ensure cron is cleared
				self::deactivate_cron_status_check();

				// Reschedule cron
				$try_again = strtotime( "+10 minutes", time() );
				wp_schedule_single_event( $try_again, WHEREUSED_HOOK_PREFIX . 'check_status_codes_cron' );
			}
		} else {
			// A full scan has ot been ran thus we cannot run a full status check on everything

			// Ensure cron is cleared
			self::deactivate_cron_status_check();
		}

	}

	/**
	 * Sets the cache for the status codes and updates the database if update flag is true.
	 *
	 * @param string $cache_status_codes_option The options key that will be referenced for storing / retrieval of data from the database
	 * @param array  $args The data to be stored into cache
	 * @param array  $status_codes_cache The local array of values
	 * @param bool   $update Updates the database with status codes cache
	 *
	 * @return array
	 */
	public static function set_cache_status_codes( string $cache_status_codes_option, array $args = [], array $status_codes_cache = [], bool $update = false ): array {

		Debug::log('set_cache_status_codes - update: ' . $update);

		if ( empty( $status_codes_cache ) ) {
			$status_codes_cache = get_option( $cache_status_codes_option );
			$status_codes_cache = ( empty( $status_codes_cache ) ) ? [] : $status_codes_cache;
		}

		if ( ! empty( $args ) ) {
			// set cache status codes
			$status_codes_cache[ $args['to_url_absolute'] ]['to_url_status'] = $args['to_url_status'];
			$status_codes_cache[ $args['to_url_absolute'] ]['to_url_redirection_url'] = $args['to_url_redirection_url'];
			$status_codes_cache[ $args['to_url_absolute'] ]['to_url_status_date'] = $args['to_url_status_date'];
		}

		if ( $update ) {
			// Update cache
			if ( update_option( $cache_status_codes_option, $status_codes_cache, false ) ) {
				// Clear wp cache
				wp_cache_delete( $cache_status_codes_option, 'options' );
			}
		}

		return $status_codes_cache;

	}

	/**
	 * Caches the status codes of outgoing references of a given post ID
	 *
	 * @param int    $post_id
	 * @param string $post_type
	 * @param int    $from_site_id
	 * @param string $cache_status_codes_option
	 *
	 * @return void
	 */
	public static function cache_status_codes( int $post_id, string $post_type, int $from_site_id, string $cache_status_codes_option  ) : void {

		Debug::log('cache_status_codes');

		// Get all the entries from the current post ID
		$outgoing_entries = Reference::get_outgoing_entries( $post_id, $post_type, $from_site_id );

		if ( ! empty( $outgoing_entries ) ) {
			$status_codes_cache = [];

			foreach ( $outgoing_entries as $entry ) {

				$args = [
					'to_url_absolute' => $entry->to_url_absolute ?? '',
					'to_url_status' => $entry->to_url_status ?? '',
					'to_url_status_date' => $entry->to_url_status_date ?? '',
					'to_url_redirection_url' => $entry->to_url_redirection_url ?? '',
				];

				// set cache status codes
				$status_codes_cache = self::set_cache_status_codes( $cache_status_codes_option, $args, $status_codes_cache );

			}

			// Update the database
			self::set_cache_status_codes( $cache_status_codes_option, [], $status_codes_cache, true );

		}

	}

	/**
	 * Scans an individual post to find all references and insert into the database
	 *
	 * @package WhereUsed
	 * @since   1.0.0
	 *
	 * @param object $this_post
	 * @param int    $from_site_id
	 * @param string $cache_status_codes_option
	 */
	public static function scan_post( object $this_post, int $from_site_id = WHEREUSED_CURRENT_SITE_ID, string $cache_status_codes_option = '', bool $queue_status_check = false ) : array {

		// Array of all Reference objects created from the page
		$all_references = [];

		if ( is_multisite() ) {
			switch_to_blog( $from_site_id );
		}

		// Just incase a revision or autosave slips trhough the cracks
		if( wp_is_post_revision( $this_post->ID) || wp_is_post_autosave( $this_post->ID ) ) {
			return [];
		}

		Debug::log( sprintf( __( 'Scanning post %d: %s', WHEREUSED_SLUG ), $this_post->ID, $this_post->post_title ) );

		// Grab settings
		$settings = Settings::get_current_settings();

		// Set default caching for efficiency
		if ( empty( $cache_status_codes_option ) ) {

			$cache_status_codes_option = Get::cache_status_codes_option_name('scan_post', $this_post->ID, $from_site_id );

		}

		if ( $queue_status_check ) {
			// We are going to queue run the status checks to the background process

			Debug::log('queue_status_check');

			// Store all the status codes into cache
			self::cache_status_codes( $this_post->ID, $this_post->post_type, $from_site_id, $cache_status_codes_option );
		}

		// Delete all current entries in database for this post
		Reference::delete_outgoing_entries( $this_post->ID, $this_post->post_type, $from_site_id );

		if ( $this_post->post_content ) {
			$args = [
				'from_post_id' => $this_post->ID,
				'from_site_id' => $from_site_id,
				'from_post_type' => $this_post->post_type,
				'from_where' => 'content',
				'cache_status_codes_option' => $cache_status_codes_option,
				'queue_status_code_check' => $queue_status_check,
			];

			// Grab all Links, Images, and block references from the content
			$all_references = array_merge( $all_references, self::get_from_html( $this_post->post_content, $args ) );

		}

		if ( $this_post->post_excerpt ) {
			$args = [
				'from_post_id' => $this_post->ID,
				'from_site_id' => $from_site_id,
				'from_post_type' => $this_post->post_type,
				'from_where' => 'excerpt',
				'cache_status_codes_option' => $cache_status_codes_option,
				'queue_status_code_check' => $queue_status_check,
			];

			// Grab all Links and images from the excerpt
			$all_references = array_merge( $all_references, self::get_from_html( $this_post->post_excerpt, $args ) );
		}

		// Scan post meta as well
		$all_references = array_merge( $all_references, self::scan_meta( $this_post->ID, $this_post->post_type, 'post meta', $cache_status_codes_option ) );

		// Featured Images
		if ( post_type_supports( $this_post->post_type, 'thumbnail' ) ) {

			// grab the id from featured image
			$featured_img_id = get_post_thumbnail_id( $this_post->ID );
			if ( $featured_img_id ) {
				$data = [
					'from_post_id' => $this_post->ID,
					'from_site_id' => $from_site_id,
					'from_where' => 'featured image',
					'to_anchor_text' => '',
					'to_post_id' => $featured_img_id,
					'to_post_type' => 'attachment',
					'to_type' => 'image',
					'to_url' => '',
					'cache_status_codes_option' => $cache_status_codes_option,
					'queue_status_code_check' => $queue_status_check,
				];

				$all_references[] = new Reference( $data );

			}

		}


		// Clear existing redirect rule references entries
		self::delete_incoming_redirect_rule_references( $this_post->ID, $from_site_id );

		// Get all the redirections referencing this post
		$all_redirections = Redirection::get_redirections( get_permalink( $this_post->ID ), $this_post->post_type, 'both', $from_site_id, $this_post->ID );

		foreach ( Reference::convert_redirections_to_references( $all_redirections, $cache_status_codes_option, $queue_status_check ) as $reference ) {
			$all_references[] = $reference;
		}
		unset( $all_redirections );

		if ( ! empty( $all_references ) ) {
			// Insert in this local table
			Reference::add_entries( $all_references );

			if ( $queue_status_check ) {
				// Maintenance Check Status Codes
				wp_schedule_single_event( time() + 10, WHEREUSED_HOOK_PREFIX . 'maintenance_check_status_codes_cron' );
			}

			// Insert references into other network site tables
			if ( is_multisite() ) {

				$sites_rows = [];

				$sites = Get::sites();

				// Sort references by site
				foreach ( $all_references as $ref ) {
					$to_site_id = $ref->get( 'to_site_id' );
					$redirection_site_id = $ref->get( 'redirection_site_id' );

					foreach ( $sites as $site ) {
						if ( $site->blog_id == $from_site_id ) {
							// Skip this table (we already did it)
							continue;
						}
						if ( $site->blog_id == $to_site_id || $site->blog_id == $redirection_site_id ) {
							$sites_rows[ $site->blog_id ][] = $ref;
						}
					}
				}

				// Add data to network site tables
				if ( ! empty( $sites_rows ) ) {
					foreach ( $sites_rows as $site_id => $site_rows ) {

						switch_to_blog( $site_id );

						if ( is_plugin_active( WHEREUSED_PLUGIN ) ) {

							// Add new entries
							Reference::add_entries( $site_rows );

							if ( $queue_status_check ) {
								// Maintenance Check Status Codes
								wp_schedule_single_event( time() + 10, WHEREUSED_HOOK_PREFIX . 'maintenance_check_status_codes_cron' );
							}

						}

						restore_current_blog();

					}
				}

			}
		}

		if ( is_multisite() ) {
			restore_current_blog();
		}

		return $all_references;

	}

	/**
	 * Scans an individual term to find all references and insert into the database
	 *
	 * @param object $term
	 * @param int    $from_site_id
	 * @param string $cache_status_codes_option
	 *
	 * @return void
	 */
	public static function scan_term( object $term, int $from_site_id = WHEREUSED_CURRENT_SITE_ID, string $cache_status_codes_option = '' ): void {

		if ( is_multisite() ) {
			switch_to_blog( $from_site_id );
		}

		// Delete all current entries in database for this post
		Reference::delete_outgoing_entries( $term->term_id, 'taxonomy term' );

		// Set default caching for efficiency
		if ( empty( $cache_status_codes_option ) ) {

			$cache_status_codes_option = Get::cache_status_codes_option_name('scan_term', $term->term_id, $from_site_id );

		}

		// Array of all Reference objects created from the page
		$all_references = [];

		// Check term description
		$args = [
			'from_post_id' => $term->term_id,
			'from_site_id' => $from_site_id,
			'from_post_type' => 'taxonomy term',
			'from_where' => 'term description',
			'cache_status_codes_option' => $cache_status_codes_option,
		];

		if ( $term->description ) {
			$all_references = array_merge( $all_references, self::get_from_html( $term->description, $args ) );
		}

		//ddd($all_references);

		// @todo Known Bug: we are not detecting incoming redirects if this term has a url

		// Scan term meta to get all references
		$all_references = array_merge( $all_references, self::scan_meta( $term->term_id, 'taxonomy term', 'term meta', $cache_status_codes_option ) );

		//ddd($all_references);

		// Adds the references to the database
		Reference::add_entries( $all_references );

		if ( is_multisite() ) {
			restore_current_blog();
		}

	}

	/**
	 * Scans an individual user to find all references and insert int26o the database
	 *
	 * @param object $user
	 * @param int    $from_site_id
	 * @param string $cache_status_codes_option
	 *
	 * @return void
	 */
	public static function scan_user( object $user, int $from_site_id = WHEREUSED_CURRENT_SITE_ID, string $cache_status_codes_option = '' ): void {

		if ( is_multisite() ) {
			switch_to_blog( $from_site_id );
		}

		// Delete all current entries in database for this post
		Reference::delete_outgoing_entries( $user->ID, 'user' );

		// Set default caching for efficiency
		if ( empty( $cache_status_codes_option ) ) {

			$cache_status_codes_option = Get::cache_status_codes_option_name('scan_user', $user->ID, $from_site_id );

		}

		// @todo Known Bug: we are not detecting incoming redirects if this user has a profile URL

		// Scan user meta to get all references
		$all_user_references = self::scan_meta( $user->ID, 'user', 'user meta', $cache_status_codes_option );

		// Adds the references to the database
		Reference::add_entries( $all_user_references );

		if ( is_multisite() ) {
			restore_current_blog();
		}
	}

	/**
	 * Scans a menu to find all references and insert into the database
	 *
	 * @param object $menu
	 * @param int    $from_site_id
	 * @param string $cache_status_codes_option
	 *
	 * @return void
	 */
	public static function scan_menu( object $menu, int $from_site_id, string $cache_status_codes_option = '' ): void {

		if ( is_multisite() ) {
			switch_to_blog( $from_site_id );
		}

		// Delete all current entries in database for this post
		Reference::delete_outgoing_entries( $menu->get( 'id' ), 'menu' );

		$all_menu_references = [];

		$args = [
			'from_post_id' => $menu->get( 'id' ),
			'from_post_type' => 'menu',
			'from_site_id' => $from_site_id,
		];

		$menu_items = $menu->get( 'items' );

		// Scan menu items to get all references
		if ( ! empty( $menu_items ) ) {
			foreach ( $menu_items as $menu_item ) {
				$this_args = $args;
				$this_args['to_url'] = $menu_item->url;
				$this_args['to_anchor_text'] = $menu_item->title;

				$all_menu_references[] = new Reference( $this_args );
			}
		}

		// Adds the references to the database
		Reference::add_entries( $all_menu_references );

		if ( is_multisite() ) {
			restore_current_blog();
		}
	}

	/**
	 * @param int    $from_id
	 * @param string $from_post_type
	 * @param string $from_where
	 * @param string $cache_status_codes_option
	 *
	 * @return array
	 */
	public static function scan_meta( int $from_id, string $from_post_type, string $from_where, string $cache_status_codes_option = '' ): array {

		// Array of all Reference objects created from the page
		$all_meta_references = [];

		if ( 'post' === $from_post_type || 'page' === $from_post_type ) {
			// Blog Post / Page
			$meta = get_post_meta( $from_id );
		} elseif ( 'taxonomy term' === $from_post_type ) {
			// Taxonomy
			$meta = get_term_meta( $from_id );
		} elseif ( 'user' === $from_post_type ) {
			// User
			$meta = get_user_meta( $from_id );
		} else {
			// Custom post type
			$meta = get_post_meta( $from_id );
		}

		if ( ! empty( $meta ) ) {

			$from_site_id = get_current_blog_id();

			foreach ( $meta as $key => $meta_value ) {

				$meta_value = $meta_value[0] ?? '';

				$ref = [
					'from_post_id' => $from_id, // The post ID
					'from_site_id' => $from_site_id, // The site ID (multisite)
					'from_post_type' => $from_post_type, // The post type
					'from_where' => $from_where, // where the reference is found: "post meta", "user meta", etc
					'from_where_key' => $key, // Post Meta Key
					'cache_status_codes_option' => $cache_status_codes_option, // used to cache status code checks
				];

				// We start with no custom meta references
				$custom_meta_refs = [];

				/**
				 * Hook that allows you to handle the scan of a specific post meta
				 *
				 * @link  https://whereused.com/docs/hooks/whereused_scan_meta/
				 * @since 1.1.0
				 *
				 * @param array  $custom_meta_refs
				 * @param string $meta_value
				 * @param array  $ref
				 *
				 * @return array Must return an array of WhereUsed/Reference objects
				 */
				$custom_meta_refs = apply_filters( WHEREUSED_HOOK_PREFIX . 'scan_meta', $custom_meta_refs, $meta_value, $ref );

				// Make Sure We Have Good Data From The Filter
				if ( ! empty( $custom_meta_refs ) ) {
					// We have something

					if ( is_array( $custom_meta_refs ) ) {
						foreach ( $custom_meta_refs as $custom_meta_ref_key => $custom_meta_ref ) {
							if ( ! $custom_meta_ref instanceof Reference ) {
								// Invalid instance: abort
								unset( $custom_meta_refs[ $custom_meta_ref_key ] );
							}
						}
					} else {
						// Invalid value, abort
						$custom_meta_refs = [];
					}

				}

				if ( empty( $custom_meta_refs ) ) {
					// No valid custom meta references, so let's fall back on default html scan
					$meta_refs = self::get_from_html( $meta_value, $ref );
				} else {
					// Use the custom references
					$meta_refs = $custom_meta_refs;
				}

				if ( ! empty( $meta_refs ) ) {
					$all_meta_references = array_merge( $all_meta_references, $meta_refs );
				}

				// Cleanup Memory
				unset( $meta[ $key ] );
			}
		}

		return $all_meta_references;

	}

	/**
	 * Recursively scans for blocks
	 *
	 * @param       $html
	 * @param array $args
	 *
	 * @return array
	 */
	public static function scan_blocks( $html, array $args ): array {

		// Array of all Reference objects
		$all_block_references = [];

		// Array of all discovered reference arrays
		$refs = [];

		$blocks = parse_blocks( $html );

		if ( ! empty( $blocks ) ) {

			// Get all blocks we are looking for
			foreach ( $blocks as $block ) {

				if ( $block['blockName'] ) {

					// Scan the content of a block
					$refs = self::scan_block( $block, $args );

				}

			}

		}

		// Remove Ignored blocks
		if ( ! empty( $refs ) ) {
			$ignored_blocks = Get::ignored_blocks();

			foreach ( $refs as $k => $r ) {
				$block_name = $r['to_block_name'] ?? '';
				if ( $block_name && isset( $ignored_blocks[ $block_name ] ) ) {
					// This block should be ingnored
					unset( $refs[ $k ] );
				}
			}
		}

		/**
		 * Filter: WhereUsed/detected_block
		 *
		 * This hook allows 3rd-parties to remove or modify discovered blocks before they are added as references
		 *
		 * @link       https://whereused.com/docs/hooks/whereused-ignored_blocks/
		 * @since      1.0.0
		 * @deprecated 1.1.0
		 * @note remove in version 2.0
		 *
		 * @return array
		 */
		$refs = apply_filters_deprecated( 'WhereUsed/detected_block', [ $refs ], '1.1.0', 'whereused_scan_block', 'This filter will be removed in version 2.0.0' );

		if ( ! empty( $refs ) ) {
			// Check and see if the hook provided us a single array or an array of arrays

			// Block contains multiple references
			foreach ( $refs as $ref ) {
				// Add each reference as a separate link
				$all_block_references[] = new Reference( $ref );
			}

		}

		return $all_block_references;

	}

	/**
	 * Scans the content of the block and records the existence of the block
	 *
	 * @since 1.1.0
	 *
	 * @param array $ref
	 * @param array $block
	 * @return array
	 */
	public static function scan_block( array $block, array $ref ): array {

		$refs = [];

		// Backup of the args
		$args = $ref;

		// Record the existence of the block itself
		$ref['to_url'] = '';
		$ref['to_anchor_text'] = '';
		$ref['to_block_name'] = $block['blockName'];
		$ref['to_type'] = 'block';

		if ( $block['blockName'] === 'core/block' ) {
			$ref['to_post_type'] = 'wp_block';
			$ref['to_post_id'] = $block['attrs']['ref'] ?? 0;
		}

		$refs[] = $ref;

		// Restore backup of args
		$ref = $args;

		if ( ! empty( $block['innerBlocks'] ) ) {
			foreach ( $block['innerBlocks'] as $inner_block ) {

				// Scan the content of a inner block
				$inner_refs = self::scan_block( $inner_block, $ref );

				if ( ! empty( $inner_refs ) ) {
					// Combine the two arrays of references
					$refs = array_merge( $refs, $inner_refs );
				}

			}
		}

		/**
		 * Filter: whereused_scan_block
		 *
		 * Allows you to intercept the scan of this block and create references using the Reference object.
		 *
		 * @package WhereUsed
		 * @since 1.1.0
		 *
		 * @link  https://whereused.com/docs/hooks/whereused_scan_block/
		 *
		 * @param array $refs Array of WhereUsed\Reference objects
		 * @param array $block Block getting scanned
		 * @param array $ref Reference details which will need to be passed to a new WhereUsed\Reference to add new references
		 *
		 * @return array
		 */
		$refs = apply_filters( WHEREUSED_HOOK_PREFIX . 'scan_block', $refs, $block, $ref);

		return $refs;

	}

	/**
	 * Check the status of a given URL
	 *
	 * @package WhereUsed
	 * @since   1.0.0
	 *
	 * @param string $to_url_full
	 * @param string $cache_status_codes_option
	 *
	 * @return array
	 */
	public static function check_status( string $to_url_full = '', string $cache_status_codes_option = '' ): array {

		Run::prevent_caching(true);

		Debug::log( sprintf( __('Checking status of %s', WHEREUSED_SLUG), $to_url_full) );

		$to_url_absolute = '';
		$to_url_status = '';
		$to_url_status_date = '';
		$to_url_redirection_url = '';
		$using_cache = false;
		$cache_hit = false;
		$status_codes_cache = [];
		$error = false;
		$processes = [];

		if ( $to_url_full ) {

			$to_url_absolute = Reference::convert_to_absolute_url( $to_url_full );

			// Determine if we are using status code cache to make the checking process more efficient
			if ( $cache_status_codes_option ) {
				$using_cache = true;
				$status_codes_cache = get_option( $cache_status_codes_option );
				$status_codes_cache = ( empty( $status_codes_cache ) ) ? [] : $status_codes_cache;
			}

			// Let's check scan cache to see if we already know the status code
			if ( $using_cache && isset( $status_codes_cache[ $to_url_absolute ] ) ) {
				// Found cached status code

				Debug::log( sprintf( __('Status cache hit for %s', WHEREUSED_SLUG), $to_url_full) );
				$cache_hit = true;

				$to_url_status = $status_codes_cache[ $to_url_absolute ]['to_url_status'];
				$to_url_status_date = $status_codes_cache[ $to_url_absolute ]['to_url_status_date'];
				$to_url_redirection_url = $status_codes_cache[ $to_url_absolute ]['to_url_redirection_url'];

				Debug::log( sprintf( __( 'Response code %s for %s', WHEREUSED_SLUG ), $to_url_status, $to_url_full ) );
			}

			if ( ! $cache_hit ) {

				// No cache hit
				Debug::log( sprintf( __('No status cache hit for %s', WHEREUSED_SLUG), $to_url_full) );

				// Pause (0.2 sec) momentarily to help with getting rate limited
				usleep( 200000 );

				$args = [
					'blocking' => true,
					'timeout' => 10,
					'redirection' => 0,
					'user-agent' => 'WhereUsed Plugin/' . WHEREUSED_VERSION . ' - ' . get_bloginfo( 'url' ),
					'cookies' => $_COOKIE,
					'sslverify' => apply_filters( 'https_local_ssl_verify', false ),
				];

				$processes[] = 1;

				$response = wp_remote_head( $to_url_absolute, $args );

				self::process_response($response, $to_url_full, $to_url_status, $to_url_redirection_url );

				if ( 429 === $to_url_status ) {
					// We are hitting the server too fast

					Debug::log( sprintf( __('We are hitting the server too fast. Taking a 2 second break.', WHEREUSED_SLUG), $to_url_full), 'warning' );

					sleep( 2 );

					Debug::log( sprintf( __( 'Trying to get status code again for %s', WHEREUSED_SLUG ), $to_url_full ) );

					$processes[] = 2;

					// Let's try again
					$response = wp_remote_head( $to_url_absolute, $args );

					self::process_response($response, $to_url_full, $to_url_status, $to_url_redirection_url );

				}

				// Attempt a normal connect
				if ( ! $to_url_status || $to_url_status >= 500 || 206 === $to_url_status ) {

					$processes[] = 3;

					Debug::log( sprintf( __('We encountered a weird error. Status [%s]. Trying again using GET request for %s', WHEREUSED_SLUG), $to_url_status, $to_url_full), 'warning' );

					// If we encounter a weird server error, let's try a normal connect instead of using head
					$response = wp_remote_get( $to_url_absolute, $args );

					self::process_response($response, $to_url_full, $to_url_status, $to_url_redirection_url );

				}

				// Attempt a normal connect
				if ( $to_url_status < 200 || $to_url_status > 399 ) {
					// The destination server is possibly not allowing only a HEAD method

					$processes[] = 4;
					$response = wp_remote_get( $to_url_absolute, $args );

					self::process_response($response, $to_url_full, $to_url_status, $to_url_redirection_url );

				}

				// set status codes
				$to_url_status_date = gmdate( 'Y-m-d H:i:s' );

				if ( $using_cache ) {
					// We are using cache

					Debug::log('using cache');

					$args = [
						'to_url_absolute' => $to_url_absolute,
						'to_url_status' => $to_url_status,
						'to_url_status_date' => $to_url_status_date,
						'to_url_redirection_url' => $to_url_redirection_url,
					];

					// set cache status codes & update the database
					self::set_cache_status_codes( $cache_status_codes_option, $args, $status_codes_cache, true );

				}

				if ( is_wp_error( $response ) ) {
					$error = $response;
					Debug::log( sprintf( __( 'Could not get response for URL: %s in %s:%d', WHEREUSED_SLUG ), $to_url_full, __FILE__, __LINE__ ), 'warning' );
				}
			}
		} else {
			Debug::log( sprintf( __( 'A full url not provided to get the status in %s:%d', WHEREUSED_SLUG ), __FILE__, __LINE__ ), 'warning' );
		}

		$response = [
			'to_url_full' => $to_url_full,
			'to_url_absolute' => $to_url_absolute,
			'to_url_status' => $to_url_status,
			'to_url_status_date' => $to_url_status_date,
			'to_url_redirection_url' => $to_url_redirection_url,
			'using_cache' => $using_cache,
			'cache_hit' => $cache_hit,
			'cache_status_codes_option' => $cache_status_codes_option,
			'error' => $error,
			'processes' => implode(',', $processes),
		];

		return $response;

	}

	/**
	 * Processes the request response from the server
	 * @since 1.1.0
	 *
	 * @param $response
	 * @param $to_url_full
	 * @param $to_url_status
	 * @param $to_url_redirection_url
	 *
	 * @return void
	 */
	private static function process_response(&$response, &$to_url_full, &$to_url_status, &$to_url_redirection_url): void {

		$to_url_status = wp_remote_retrieve_response_code( $response );
		$to_url_redirection_url = wp_remote_retrieve_header( $response, 'location' );

		Debug::log( sprintf( __( 'Response code %s for %s', WHEREUSED_SLUG ), $to_url_status, $to_url_full ) );

		if ( is_wp_error( $response ) ) {
			Debug::log( sprintf( __( 'Received error in response for %s', WHEREUSED_SLUG ), $to_url_full ), 'error' );
		}

	}

	/**
	 * Checks the given URL and updates the db table's status codes
	 *
	 * @param string $to_url_full
	 * @param array  $response
	 * @param string $cache_status_codes_option
	 *
	 * @return array
	 */
	public static function status_check_update( string $to_url_full = '', array $response = [], string $cache_status_codes_option = '' ): array {

		Debug::log('status_check_update');

		// Allow override for $response to prevent double-checking
		if ( empty( $response ) || ! isset( $response['to_url_status'] ) || ! isset( $response['to_url_status_date'] ) ) {
			$response = self::check_status( $to_url_full, $cache_status_codes_option );
		}

		global $wpdb;

		// If $response['cache_hit'] not set, default to true so the status check update doesn't run
		$cache_hit = $response['cache_hit'] ?? true;

		if ( ! $cache_hit ) {
			// Cache wasn't hit, so we need to update status codes everywhere
			Debug::log('Cache wasn\'t hit, so we need to update status codes everywhere');

			$sites = GET::sites();

			// status check all entries that point to the URL that was getting redirected
			foreach ( $sites as $site ) {

				if ( is_multisite() ) {
					switch_to_blog( $site->blog_id );
				}

				if ( is_plugin_active( WHEREUSED_PLUGIN ) ) {

					$values = [];
					$values[] = Get::table_name();
					$values[] = $response['to_url_status'];
					$values[] = $response['to_url_status_date'];
					$values[] = $response['to_url_absolute'];

					// update all the url status in the database
					$wpdb->query( $wpdb->prepare( 'UPDATE `%1$s` SET `to_url_status` = \'%2$s\', `to_url_status_date` = \'%3$s\' 
					WHERE `to_url_absolute` = \'%4$s\';', $values ) );

				}

				if ( is_multisite() ) {
					restore_current_blog();
				}
			}
		} else {
			Debug::log('Ignore update of status codes due to cache hit');
		}

		return $response;

	}

	/**
	 * Deletes the incoming redirect rule references found in the references table for the provided post ID and site id
	 *
	 * @param int $post_id
	 * @param int $post_site_id
	 *
	 * @return void
	 */
	public static function delete_incoming_redirect_rule_references( int $post_id, int $post_site_id ): void {

		global $wpdb;

		if ( is_multisite() ) {
			switch_to_blog($post_site_id);
		}

		$values = [];
		$values[] = $wpdb->prefix;
		$values[] = $post_id;
		$values[] = $post_site_id;
		$values[] = $post_site_id;

		// Clear all incoming redirections to this post on this site from the references table
		$sql = $wpdb->prepare('DELETE FROM `%1$swhereused_references` WHERE `to_post_id` = %2$d AND `to_site_id` = %3$d AND `from_where` = "redirection" AND `redirection_site_id` = %4$d ORDER BY `id` DESC;', $values );

		$wpdb->query($sql);

		if ( is_multisite() ) {
			restore_current_blog();
		}

	}

	/**
	 * Deletes all post entries in the custom table when a post is deleted
	 *
	 * @package  WhereUsed
	 * @since    1.0.0
	 *
	 * @internal To test this function: create a post that I will delete later.
	 * Link to that post from a term, a user, and another post. Initiate a scan to detect the references,
	 * then delete the target post. The user, term, and post that were referencing should be rescanned
	 * and the status code should be updated to a 404 and the post_id for these references should be 0.
	 *
	 * @param int    $post_id
	 * @param string $post_type
	 *
	 * @return void
	 */
	public static function delete_post_entries( int $post_id, string $post_type = '' ): void {

		if ( $post_id ) {

			// Ensure we have a post type defined
			if ( empty( $post_type ) ) {
				$this_post = get_post( $post_id );
				$post_type = $this_post->post_type ?? '';
			}

			if ( $post_type ) {

				$cache_status_codes_option = Get::cache_status_codes_option_name( 'delete_post_entries', $post_id );

				self::reprocess_entries( $post_id, $post_type, $cache_status_codes_option );
			}

		}

	}

	/**
	 * Post has been deleted. Now we need to cleanup the references table
	 *
	 * @param int      $post_id
	 * @param \WP_Post $this_post
	 *
	 * @return void
	 */
	public static function deleted_post( int $post_id, WP_Post $this_post ) : void {

		self::delete_post_entries( $post_id, $this_post->post_type );

	}

	/**
	 * @param int    $post_id
	 * @param string $post_type
	 * @param string $cache_status_codes_option
	 *
	 * @return void
	 */
	public static function reprocess_entries( int $post_id, string $post_type, string $cache_status_codes_option = '' ) {

		global $wpdb;

		Run::prevent_caching(true);

		// Cleans up old records
		Reference::delete_outgoing_entries( $post_id, $post_type );

		// Find all the references that pointed to this post and rescan them
		$entries_to_scan = Reference::get_incoming_entries( $post_id, $post_type );

		if ( ! empty( $entries_to_scan ) ) {

			$terms = [];
			$users = [];
			$posts = [];
			$menus = [];

			// Group By Site ID and Type so we can scan later
			foreach ( $entries_to_scan as $entry ) {
				if ( 'taxonomy term' == $entry->from_post_type ) {
					$terms[ $entry->from_site_id ][] = $entry->from_post_id;
				} elseif ( 'user' == $entry->from_post_type ) {
					$users[ $entry->from_site_id ][] = $entry->from_post_id;
				} elseif ( 'menu' == $entry->from_post_type ) {
					$menus[ $entry->from_site_id ][] = $entry->from_post_id;
				} else {
					$posts[ $entry->from_site_id ][] = $entry->from_post_id;
				}
			}

			$sites = Get::sites();

			if ( ! empty( $sites ) ) {
				foreach ( $sites as $site ) {

					if ( isset( $terms[ $site->blog_id ] ) || isset( $users[ $site->blog_id ] ) || isset( $posts[ $site->blog_id ] ) ) {

						if ( is_multisite() ) {
							switch_to_blog( $site->blog_id );
						}

						if ( is_plugin_active( WHEREUSED_PLUGIN ) ) {
							// Process Terms
							if ( ! empty( $terms[ $site->blog_id ] ) ) {

								$values = [];
								$values[] = $wpdb->prefix . 'term_taxonomy';

								$terms_placeholders = [];

								foreach ( $terms[ $site->blog_id ] as $term_id ) {
									$values[] = $term_id;
									$terms_placeholders[] = '%' . count( $values ) . '$d';
								}

								// Grab terms from DB - Use a custom query so that it's faster and uses less memory
								$all_terms = $wpdb->get_results( $wpdb->prepare( 'SELECT `term_id`, `taxonomy`, `description` FROM `%1$s` 
								WHERE `term_id` IN ( ' . implode( ',', $terms_placeholders ) . ')', $values ) );

								// Process each term
								if ( ! empty( $all_terms ) ) {
									foreach ( $all_terms as $key => $this_term ) {
										// Clean Memory
										unset( $all_terms[ $key ] );

										self::scan_term( $this_term, $site->blog_id, $cache_status_codes_option );
									}
								}
							}

							// Process Users
							if ( ! empty( $users[ $site->blog_id ] ) ) {

								// Grab users ids from the database
								$all_users = get_users( [
									'blog_id' => $site->blog_id,
									'include' => $users[ $site->blog_id ],
								] );

								// Process each user
								if ( ! empty( $all_users ) ) {
									foreach ( $all_users as $key => $this_user ) {
										// Clean Memory
										unset( $all_users[ $key ] );

										self::scan_user( $this_user, $site->blog_id, $cache_status_codes_option );
									}
								}

							}

							// Process menus
							if ( ! empty( $menus[ $site->blog_id ] ) ) {

								foreach ( $menus[ $site->blog_id ] as $menu_id ) {
									$menu = Menu::get_by_id( $menu_id );
									if ( $menu ) {
										self::scan_menu( $menu, $site->blog_id, $cache_status_codes_option );
									}
								}

							}

							// Process Posts
							if ( ! empty( $posts[ $site->blog_id ] ) ) {

								$values = [];
								$values[] = $wpdb->prefix . 'posts';

								$post_placeholders = [];

								foreach ( $posts[ $site->blog_id ] as $post_id ) {
									$values[] = $post_id;
									$post_placeholders[] = '%' . count( $values ) . '$d';
								}

								// Grab posts from DB - Use a custom query so that it's faster and uses less memory
								$all_posts = $wpdb->get_results( $wpdb->prepare( 'SELECT `ID`, `post_content`, `post_status`, `post_type`, `post_title`, `post_excerpt`, `post_mime_type` FROM `%1$s` 
								WHERE `ID` IN ( ' . implode( ',', $post_placeholders ) . ')', $values ) );

								// Process each post
								if ( ! empty( $all_posts ) ) {
									foreach ( $all_posts as $key => $this_post ) {
										// Clean Memory
										unset( $all_posts[ $key ] );

										self::scan_post( $this_post, $site->blog_id, $cache_status_codes_option );
									}
								}
							}
						}

						if ( is_multisite() ) {
							restore_current_blog();
						}
					}
				}
			}

		}

	}

	/**
	 * Scans all existing references in a given array categorized by site ID. See example: $references[ $site_id ][] = new Reference()
	 *
	 * @param array $references
	 *
	 * @return void
	 */
	public static function references( array $references ): void {

		// Rescan Pages/Posts
		if ( ! empty( $references ) ) {
			$cache_status_codes_option = Get::cache_status_codes_option_name('redirection_references' );

			foreach ( $references as $site_id => $array ) {

				if ( ! empty( $array ) ) {

					if ( is_multisite() ) {
						switch_to_blog( $site_id );
					}

					foreach ( $array as $reference ) {
						// Scan those post_ids if they are local/network to update all incoming redirection references
						if ( $reference->get( 'from_post_id' ) ) {

							if ( $reference->get( 'from_post_type' ) == 'user' ) {
								// Scan User
								$this_user = get_user_by( 'ID', $reference->get( 'from_post_id' ) );
								if ( ! empty( $this_user ) ) {
									Scan::scan_user( $this_user, $reference->get( 'from_site_id' ), $cache_status_codes_option );
								}
							} elseif ( $reference->get( 'from_post_type' ) == 'taxonomy term' ) {
								// Scan Term
								$this_term = Get::term_by_id( $reference->get( 'from_post_id' ), false );
								if ( ! empty( $this_term ) ) {
									Scan::scan_term( $this_term, $reference->get( 'from_site_id' ), $cache_status_codes_option );
								}
							} else {
								// Scan Post
								$this_post = get_post( $reference->get( 'from_post_id' ) );
								if ( ! empty( $this_post ) ) {
									Scan::scan_post( $this_post, $reference->get( 'from_site_id' ), $cache_status_codes_option );
								}
							}

						}

						// Account for redirection rules so we can rescan the target post
						if( $reference->get('from_where') == 'redirection') {
							$this_post = get_post( $reference->get( 'to_post_id' ) );
							if ( ! empty( $this_post ) ) {
								Scan::scan_post( $this_post, $reference->get( 'to_site_id' ), $cache_status_codes_option );
							}
						}
					}

					if ( is_multisite() ) {
						restore_current_blog();
					}

				}

			}

			delete_option( $cache_status_codes_option );
		}
	}

	/**
	 * Grabs links and images from the DOM cotnent
	 *
	 * @package WhereUsed
	 * @since   1.0.0
	 *
	 * @param string $html
	 * @param array  $args
	 *
	 * @return array
	 */
	public static function get_from_html( string $html, array $args = [] ): array {

		// Must have something to scan
		if ( empty( $args ) ) {
			return [];
		}

		// Require HTML
		if ( empty( $html ) ) {
			return [];
		}

		// Empty array to hold all links to return
		$all_references = [];

		// Create DOM structure so we can reliably grab all img tags
		$dom = new DOMDocument();

		// Silence warnings
		libxml_use_internal_errors( true );

		// Ensure we are using UTF-8 Encoding
		if ( function_exists( 'mb_convert_encoding' ) ) {
			// mbstring non-default extension installed and enabled
			$html = mb_convert_encoding( $html, 'HTML-ENTITIES', 'UTF-8' );
		} else {
			// fallback
			$html = htmlspecialchars_decode( utf8_decode( htmlentities( $html, ENT_COMPAT, 'utf-8', false ) ) );
		}

		// Load the URL's content into the DOM
		$dom->loadHTML( $html, LIBXML_HTML_NODEFDTD | LIBXML_HTML_NOIMPLIED );

		// Loop through each <a> tag in the dom and add it to the link array
		foreach ( $dom->getElementsByTagName( 'a' ) as $d ) {
			if ( $href = $d->getAttribute( 'href' ) ) {
				// Inherit base details from args
				$ref = $args;

				$ref['to_url'] = $href;
				$ref['to_anchor_text'] = $d->nodeValue;
				$ref['to_type'] = 'link';

				$all_references[] = new Reference ( $ref );
			}
		}

		// Loop through each <a> tag in the dom and add it to the link array
		foreach ( $dom->getElementsByTagName( 'img' ) as $d ) {
			// Inherit base details from args
			$ref = $args;

			$ref['to_url'] = $d->getAttribute( 'src' );
			$ref['to_anchor_text'] = '';
			$ref['to_type'] = 'image';

			$all_references[] = new Reference ( $ref );
		}

		// Loop through each <a> tag in the dom and add it to the link array
		foreach ( $dom->getElementsByTagName( 'iframe' ) as $d ) {
			// Inherit base details from args
			$ref = $args;

			$ref['to_url'] = $d->getAttribute( 'src' );
			$ref['to_anchor_text'] = '';
			$ref['to_type'] = 'iframe';

			$all_references[] = new Reference ( $ref );
		}

		// Get blocks
		$all_references = array_merge( $all_references, static::scan_blocks( $html, $args ) );

		//Return the links
		return $all_references;
	}

}