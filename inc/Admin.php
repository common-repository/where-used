<?php

namespace WhereUsed;

// Prevent Direct Access
( defined( 'ABSPATH' ) ) || die;

use WhereUsed\HelpersLibrary\REQUEST;
use WhereUsed\HelpersLibrary\Admin as Library_Admin;

/**
 * Class Admin - Handles all the admin functionality
 *
 * @package WhereUsed
 * @since   1.0.0
 */
final class Admin extends Library_Admin {

	/**
	 * Set Hooks and display errors
	 *
	 * @package WhereUsed
	 * @since   1.0.0
	 *
	 * @return void
	 */
	public static function init(): void {

		parent::init();

		if ( ! wp_doing_ajax() ) {

			if ( WHEREUSED_SLUG === REQUEST::key( 'page' ) ) {

				if ( ! defined( 'WHEREUSED_HELPERSLIBRARY_ADMIN_HIDE_NOTICES' ) ) {
					// Hide all other plugin notices on this page
					define( 'WHEREUSED_HELPERSLIBRARY_ADMIN_HIDE_NOTICES', true );
				}

				if ( ! defined( 'WHEREUSED_HELPERSLIBRARY_ADMIN_BODY_CLASS' ) ) {
					// Force styling to plugin pages
					define( 'WHEREUSED_HELPERSLIBRARY_ADMIN_BODY_CLASS', true );
				}

			}

			add_action( 'network_admin_menu', [
				self::class,
				'admin_menu',
			], 9999 );

			add_action( 'admin_menu', [
				self::class,
				'admin_menu',
			], 9999 );

			// Scripts that load on a full page load
			add_action( 'admin_enqueue_scripts', [
				self::class,
				'scripts',
			], 9 );

			add_action( 'add_meta_boxes', [
				self::class,
				'add_meta_boxes',
			] );

			// Add links to plugins page
			add_filter( 'plugin_action_links', [
				self::class,
				'plugin_action_links',
			], 10, 2 );

			add_filter( 'network_admin_plugin_action_links', [
				self::class,
				'plugin_action_links',
			], 10, 2 );
		}

		// Hook into Helper's Library to display notices inside of display_page()
		add_action( 'helpers_library_admin_notices', [
			self::class,
			'check_notices',
		], 0 );

		// Hook into Helper's Library get_current_tab()
		add_filter( 'helpers_library_admin_current_tab', [
			self::class,
			'get_current_tab',
		], 10, 0 );

		add_action( 'whereused_display_header', [
			self::class,
			'display_scan_needed_notice',
		], 10, 0 );

	}

	/**
	 * Displays the notice to tell user that a scan is needed
	 *
	 * @return void
	 */
	public static function display_scan_needed_notice(): void {
		if ( ! Scan::has_full_scan_ran() && ! Scan::is_full_scan_running() ) {
			// Scan is needed or the last scan is not finished
			Scan::display_results_not_available( true );
		}
	}

	/**
	 * Tells you which tab you are on adn sets the default tab
	 *
	 * @package WhereUsed
	 * @since   1.0.0
	 *
	 * @return string
	 */
	public static function get_current_tab(): string {

		$tab = REQUEST::key( 'tab' );

		// Compensate for missing dashboard in network
		if ( is_network_admin() ) {
			if ( '' === $tab ) {
				$tab = 'settings';
			}
		} else {

			// Default to dashboard
			if ( '' === $tab ) {
				$tab = 'dashboard';
			}
		}

		return $tab;
	}

	/**
	 * Displays notices if needed
	 *
	 * @package WhereUsed
	 * @since   1.0.0
	 *
	 * @return void
	 */
	public static function check_notices(): void {

		$scan = Scan::get_current( true );
		if ( $scan->get( 'needed' ) ) {
			// Scan is needed

			$settings = Settings::get_current_settings();

			if ( $settings->can_user_access_settings() ) {
				// User can run a scan themselves
				if ( $scan->get( 'start_date' ) ) {
					// Not our first rodeo
					$message = __( 'Settings have changed.', WHEREUSED_SLUG );
					$link_url = WHEREUSED_ADMIN_URL . '#scan';
					$link_anchor_text = __( 'Please run a new scan', WHEREUSED_SLUG );
				} else {
					$message = __( 'First, review settings and then, run an initial scan.', WHEREUSED_SLUG );
				}

			} else {
				// User doesn't have access to run a scan

				if ( $scan->get( 'start_date' ) ) {
					// Not our first rodeo
					$message = __( 'Settings have changed. Please request the administrator to run a new scan.', WHEREUSED_SLUG );
				} else {
					// Needs an initial scan
					$message = __( 'Please request the administrator to run an initial full scan.', WHEREUSED_SLUG );
				}
			}

			if ( $message ) {
				// Set defaults
				$link_url = $link_url ?? '';
				$link_anchor_text = $link_anchor_text ?? '';

				Admin::add_notice( [
					'message' => $message,
					'link_url' => $link_url,
					'link_anchor_text' => $link_anchor_text,
					'alert_level' => 'warning',
				] );
			}

		}
	}

	/**
	 * Adds links to this plugin on the plugin's management page
	 *
	 * @package WhereUsed
	 * @since   1.0.0
	 *
	 * @param array  $links Array of links for the plugins, adapted when the current plugin is found.
	 * @param string $file  The filename for the current plugin, which the filter loops through.
	 *
	 * @return array
	 */
	public static function plugin_action_links( $links, $file ): array {

		// Show Settings Link
		if ( WHEREUSED_PLUGIN === $file ) {

			if ( is_network_admin() ) {
				$label = __( 'Network Settings', WHEREUSED_SLUG );
				$settings_url = WHEREUSED_SETTINGS_NETWORK_URL;
			} else {
				$label = __( 'Settings', WHEREUSED_SLUG );
				$settings_url = WHEREUSED_SETTINGS_URL;
			}
			// Settings Link
			$settings_link = '<a href="' . esc_url( $settings_url ) . '">' . esc_html( $label ) . '</a>';
			array_unshift( $links, $settings_link );

			if ( ! is_network_admin() ) {
				// Dashboard Link

				$dashboard_link = '<a href="' . esc_url( WHEREUSED_ADMIN_URL . '&tab=dashboard' ) . '">' . esc_html__( 'Dashboard', WHEREUSED_SLUG ) . '</a>';
				array_unshift( $links, $dashboard_link );
			}

			// Style Links
			$rate_link = '
<br /><a href="https://wordpress.org/support/plugin/where-used/reviews/#new-post">' . esc_html__( 'Rate:', WHEREUSED_SLUG ) . ' <span class="rate-us" data-stars="5"><span class="dashicons dashicons-star-filled star-1" title="'.esc_html__( 'Poor', WHEREUSED_SLUG).'"></span><span class="dashicons dashicons-star-filled star-2" title="'.esc_html__( 'Works', WHEREUSED_SLUG).'"></span><span class="dashicons dashicons-star-filled star-3" title="'.esc_html__( 'Good', WHEREUSED_SLUG).'"></span><span class="dashicons dashicons-star-filled star-4" title="'.esc_html__( 'Great', WHEREUSED_SLUG).'"></span><span class="dashicons dashicons-star-filled star-5" title="'.esc_html__( 'Fantastic!', WHEREUSED_SLUG).'"></span></span></a>
<style>
	.plugins .plugin-title [class*=dashicons-star-]{
		float: none;
		width: auto;
		height: auto;
		padding: 0;
		background: none;
	}
	.plugins .plugin-title .rate-us [class*=dashicons-star-]:before {
        font-size: 20px;
        color: #ffb900;
        background: none;
        padding: 0;
        box-shadow: none;
	}
	.plugins .plugin-title .rate-us:hover span:before {
		content: "\f154";
	}
	
	.plugins .plugin-title .rate-us:hover .star-1:before,
	.plugins .plugin-title .rate-us[data-stars="2"]:hover span.star-2:before,
	.plugins .plugin-title .rate-us[data-stars="3"]:hover span.star-2:before,
	.plugins .plugin-title .rate-us[data-stars="3"]:hover span.star-3:before,
	.plugins .plugin-title .rate-us[data-stars="4"]:hover span.star-2:before,
	.plugins .plugin-title .rate-us[data-stars="4"]:hover span.star-3:before,
	.plugins .plugin-title .rate-us[data-stars="4"]:hover span.star-4:before,
	.plugins .plugin-title .rate-us[data-stars="5"]:hover span:before {
		content: "\f155";
	}
</style>
<script>
jQuery(".plugins .plugin-title .rate-us span").on("mouseover", function(){
    let stars = jQuery(this).index() + 1;
   jQuery(this).closest(".rate-us").attr("data-stars", stars);
});
</script>';
			$links[] = $rate_link;
		}

		return $links;

	}

	/**
	 * Load Scripts
	 *
	 * @package WhereUsed
	 * @since   1.0.0
	 */
	public static function scripts(): void {

		Debug::log( __( 'Loading Scripts', WHEREUSED_SLUG ) );

		$page = REQUEST::key( 'page' );
		$tab = Admin::get_current_tab();
		$action = REQUEST::key( 'action' );

		if ( WHEREUSED_SLUG === $page ) {

			if ( ! defined( 'WHEREUSED_HELPERSLIBRARY_ADMIN_SCRIPTS' ) ) {
				define( 'WHEREUSED_HELPERSLIBRARY_ADMIN_SCRIPTS', true );
			}

			wp_enqueue_script( WHEREUSED_SLUG . '-settings', WHEREUSED_ASSETS_URL . 'js/settings.js', [ 'jquery', 'wp-i18n' ], filemtime( WHEREUSED_ASSETS_DIR . '/js/settings.js' ), 'all' );
			wp_localize_script( WHEREUSED_SLUG . '-settings', 'WhereUsedAjax', [ 'ajaxURL' => admin_url( 'admin-ajax.php' ) ] );
			wp_set_script_translations( WHEREUSED_SLUG . '-settings', WHEREUSED_SLUG );

			wp_enqueue_script( WHEREUSED_SLUG . '-table', WHEREUSED_ASSETS_URL . 'js/table.js', [ 'jquery', 'wp-i18n' ], filemtime( WHEREUSED_ASSETS_DIR . '/js/table.js' ), 'all' );
			wp_localize_script( WHEREUSED_SLUG . '-table', 'WhereUsedAjax', [ 'ajaxURL' => admin_url( 'admin-ajax.php' ) ] );
			wp_set_script_translations( WHEREUSED_SLUG . '-table', WHEREUSED_SLUG );

			if ( ! is_network_admin() && ('' == $tab || 'dashboard' == $tab) ) {
				if ( ! defined( 'WHEREUSED_HELPERSLIBRARY_ADMIN_DASHBOARD_SCRIPTS' ) ) {
					define( 'WHEREUSED_HELPERSLIBRARY_ADMIN_DASHBOARD_SCRIPTS', true );
				}

				wp_enqueue_script( WHEREUSED_SLUG . '-dashboard', WHEREUSED_ASSETS_URL . 'js/dashboard.js', [ 'jquery', 'wp-i18n' ], filemtime( WHEREUSED_ASSETS_DIR . '/js/dashboard.js' ), true );
				wp_localize_script( WHEREUSED_SLUG . '-dashboard', 'WhereUsedAjax', [ 'ajaxURL' => admin_url( 'admin-ajax.php' ) ] );
				wp_set_script_translations( WHEREUSED_SLUG . '-dashboard', WHEREUSED_SLUG );
			}

		}

		if ( WHEREUSED_SLUG === $page || 'edit' == $action ) {

			if ( ! defined( 'WHEREUSED_HELPERSLIBRARY_ADMIN_STYLING' ) ) {
				// Add Helpers Library Styling
				define( 'WHEREUSED_HELPERSLIBRARY_ADMIN_STYLING', true );
			}

			wp_enqueue_style( WHEREUSED_SLUG . '-styles', WHEREUSED_ASSETS_URL . 'styles.css', [], filemtime( WHEREUSED_ASSETS_DIR . '/styles.css' ), 'all' );
		}

	}

	/**
	 * Add A Setting Page: Admin > Tools > WhereUsed
	 *
	 * @package WhereUsed
	 * @since   1.0.0
	 *
	 * @return void
	 */
	public static function admin_menu(): void {

		if ( is_multisite() ) {

			if ( is_network_admin() ) {
				// Super Admins Only
				add_submenu_page( 'settings.php', WHEREUSED_NAME, WHEREUSED_NAME, 'manage_network', WHEREUSED_SLUG, [
					self::class,
					'display_page',
				] );
			}

		}

		if ( is_admin() ) {

			$settings = Settings::get_current_settings();

			if ( $settings->can_user_access_tool() ) {
				add_submenu_page( 'tools.php', WHEREUSED_NAME, WHEREUSED_NAME, Settings::get_user_access_capability(), WHEREUSED_SLUG, [
					self::class,
					'display_page',
				] );
			}

		}

	}

	/**
	 * Adds the meta box
	 *
	 * @package WhereUsed
	 * @since   1.0.0
	 */
	public static function add_meta_boxes() {

		global $post;

		// Add References of a WP Block
		$settings = Settings::get_current_settings();
		$scan_post_types = $settings->get('scan_post_types');

		if ( ! empty( $scan_post_types ) ) {

			add_meta_box( WHEREUSED_SLUG . '_to_this', sprintf( __( 'References To This %s', WHEREUSED_SLUG ), ucwords( $post->post_type ) ), [
				self::class,
				'meta_box_references_to',
			], $scan_post_types );

			add_meta_box( WHEREUSED_SLUG . '_from_this', sprintf( __( 'References From This %s', WHEREUSED_SLUG ), ucwords( $post->post_type ) ), [
				self::class,
				'meta_box_references_from',
			], $scan_post_types );

			if ( Get::using_redirection_plugin() ) {
				add_meta_box( WHEREUSED_SLUG . '_redirections', __( 'Redirection Rules Involved', WHEREUSED_SLUG ), [
					self::class,
					'meta_box_redirections',
				], $scan_post_types );
			}

		}

	}

	/**
	 * Meta Box Content: Reference to this post
	 *
	 * @package WhereUsed
	 * @since   1.0.0
	 */
	public static function meta_box_references_to(): void {

		include_once( WHEREUSED_TABLES_DIR . '/Metabox_References_Table.php' );

		new Metabox_References_Table( 'to' );

	}

	/**
	 * Meta Box Content: Reference from this post
	 *
	 * @package WhereUsed
	 * @since   1.0.0
	 */
	public static function meta_box_references_from(): void {

		include_once( WHEREUSED_TABLES_DIR . '/Metabox_References_Table.php' );

		new Metabox_References_Table( 'from' );

	}

	/**
	 * Meta Box contents
	 *
	 * @package WhereUsed
	 * @since   1.0.0
	 */
	public static function meta_box_redirections(): void {

		include_once( WHEREUSED_TABLES_DIR . '/Metabox_References_Table.php' );

		new Metabox_References_Table( 'redirection' );
	}

}

