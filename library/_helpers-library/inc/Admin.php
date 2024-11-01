<?php

namespace WhereUsed\HelpersLibrary;

// Prevent Direct Access
( defined( 'ABSPATH' ) ) || die;

/**
 * Class Admin - The base functionality of the admin
 */
abstract class Admin {

	use Constants;

	/**
	 * Set Hooks and display errors
	 */
	public static function init(): void {

		if ( wp_doing_ajax() ) {
			// Setup AJAX Requests

			/**
			 * Make JS AJAX compatible with network admin
			 *
			 * @internal In JS send AJAX variable networkAdmin with true value to force is_network_admin() as true in PHP
			 */
			if ( REQUEST::bool( 'networkAdmin' ) ) {
				if ( ! defined( 'WP_NETWORK_ADMIN' ) ) {
					define( 'WP_NETWORK_ADMIN', true );
				}
			}

			// Display Page **Must use static:: so that AJAX works properly
			add_action( 'wp_ajax_' . static::get_constant_value( 'HOOK_PREFIX' ) . 'page', [
				static::class,
				'display_page',
			] );

		} else {

			add_action( 'admin_head', [
				self::class,
				'hide_all_admin_notices',
			], 999 );

			add_filter( 'admin_body_class', [
				self::class,
				'body_class',
			], 999 );

			// Scripts that load on a full page load
			add_action( 'admin_enqueue_scripts', [
				self::class,
				'scripts',
			], 999 );

		}

		add_action( 'after_plugin_row_' . static::get_constant_value( 'PLUGIN' ), [
			static::class,
			'prevent_update',
		], 0, 2 );

		add_action( static::get_constant_value( 'HOOK_PREFIX' ) . 'admin_notices', [
			static::class,
			'display_notices',
		], 999, 0 );

	}

	/**
	 * Prevents the user from updating the plugin if the plugin directory is on GIT versioning
	 *
	 * @param string $file        Plugin basename.
	 * @param array  $plugin_data Plugin information.
	 *
	 * @return void
	 */
	public static function prevent_update( string $file, array $plugin_data ): void {

		$current = get_site_transient( 'update_plugins' );
		if ( ! isset( $current->response[ $file ] ) || ! file_exists(static::get_constant_value( 'DIR' ).'/.git') ) {
			return;
		}

		// Remove the core update notice
		remove_action( "after_plugin_row_{$file}", 'wp_plugin_update_row', 10, 2 );

		$response = $current->response[ $file ];
		$plugin_slug = isset( $response->slug ) ? $response->slug : $response->id;

		$plugins_allowedtags = [
			'a' => [
				'href' => [],
				'title' => [],
			],
			'abbr' => [ 'title' => [] ],
			'acronym' => [ 'title' => [] ],
			'code' => [],
			'em' => [],
			'strong' => [],
		];

		/** @var WP_Plugins_List_Table $wp_list_table */
		$wp_list_table = _get_list_table( 'WP_Plugins_List_Table', [
			'screen' => get_current_screen(),
		] );

		// Determine if this plugin is actually active in this scope
		if ( is_network_admin() ) {
			$active_class = is_plugin_active_for_network( $file ) ? ' active' : '';
		} else {
			$active_class = is_plugin_active( $file ) ? ' active' : '';
		}

		printf( '<tr class="plugin-update-tr%s" id="%s" data-slug="%s" data-plugin="%s">' . '<td colspan="%s" class="plugin-update colspanchange">' . '<div class="update-message notice inline notice-warning notice-alt"><p>', $active_class, esc_attr( $plugin_slug . '-update' ), esc_attr( $plugin_slug ), esc_attr( $file ), esc_attr( $wp_list_table->get_column_count() ), );

		// Display a notice telling the user to PULL from git repo
		echo 'Version ' . esc_html( $response->new_version ) . ' of ' . wp_kses( $plugin_data['Name'], $plugins_allowedtags ) . ' is available. Git Repo: Please PULL from the MAIN branch.';

	}

	/**
	 * Adds class to the admin body so that we can force styling it appropriately
	 *
	 * @param string $classes
	 *
	 * @return string
	 */
	public static function body_class( string $classes ): string {

		if ( defined( 'WHEREUSED_HELPERSLIBRARY_ADMIN_BODY_CLASS' ) ) {
			$classes .= ' tools-page-custom';
		}

		return $classes;

	}

	/**
	 * Hides all admin notices from other plugins and core
	 */
	public final static function hide_all_admin_notices(): void {

		if ( defined( 'WHEREUSED_HELPERSLIBRARY_ADMIN_HIDE_NOTICES' ) && ! defined( 'WHEREUSED_HELPERSLIBRARY_ADMIN_HIDE_NOTICES_CLEARED' ) ) {

			define( 'WHEREUSED_HELPERSLIBRARY_ADMIN_HIDE_NOTICES_CLEARED', true );

			remove_all_actions( 'admin_notices' );
			remove_all_actions( 'all_admin_notices' );
			remove_all_actions( 'user_admin_notices' );
			remove_all_actions( 'network_admin_notices' );
		}

	}

	/**
	 * Load Scripts
	 */
	public static function scripts(): void {

		if ( defined( 'WHEREUSED_HELPERSLIBRARY_ADMIN_SCRIPTS' ) ) {
			wp_enqueue_script( WHEREUSED_HELPERSLIBRARY_SLUG . '-plugin', WHEREUSED_HELPERSLIBRARY_ASSETS_URL . '/js/plugin.js', [], filemtime( WHEREUSED_HELPERSLIBRARY_ASSETS_DIR . '/js/plugin.js' ), 'all' );
			wp_localize_script( WHEREUSED_HELPERSLIBRARY_SLUG . '-plugin', 'WhereUsedHelpersLibraryAjax', [ 'ajaxURL' => admin_url( 'admin-ajax.php' ) ] );

			wp_enqueue_script( WHEREUSED_HELPERSLIBRARY_SLUG . '-notifications', WHEREUSED_HELPERSLIBRARY_ASSETS_URL . '/js/notifications.js', [], filemtime( WHEREUSED_HELPERSLIBRARY_ASSETS_DIR . '/js/notifications.js' ), 'all' );
			wp_localize_script( WHEREUSED_HELPERSLIBRARY_SLUG . '-notifications', 'WhereUsedHelpersLibraryAjax', [ 'ajaxURL' => admin_url( 'admin-ajax.php' ) ] );
		}

		if ( defined( 'WHEREUSED_HELPERSLIBRARY_ADMIN_DASHBOARD_SCRIPTS' ) ) {
			wp_enqueue_script( 'dashboard' );
			wp_admin_css( 'dashboard' );
			add_thickbox();
		}

		if ( defined( 'WHEREUSED_HELPERSLIBRARY_ADMIN_STYLING' ) ) {
			wp_enqueue_style( WHEREUSED_HELPERSLIBRARY_SLUG . '-styles', WHEREUSED_HELPERSLIBRARY_ASSETS_URL . '/styles.css', [], filemtime( WHEREUSED_HELPERSLIBRARY_ASSETS_DIR . '/styles.css' ) );
		}

	}

	/**
	 * Tells you which tab you are on adn sets the default tab
	 */
	abstract public static function get_current_tab(): string;

	/**
	 * Displays the page
	 */
	public static function display_page(): void {

		Run::prevent_caching();

		$is_network_admin = is_network_admin();

		$page = REQUEST::key( 'page' );
		$prefix = $is_network_admin ? 'network-' : '';
		$tab = static::get_current_tab();

		// Template We are loading
		$template = static::get_constant_value( 'TEMPLATES_DIR' ) . '/' . $prefix . $tab . '.php';

		if ( file_exists( $template ) ) {

			// Load Template
			include_once( $template );

		} else {

			static::display_header();

			echo '<p>' . __( 'Error: This page does not exist: ', static::get_constant_value( 'SLUG' ) ) . esc_html( $template ) . '</p>';

			static::display_footer();
		}

	}

	/**
	 * Displays the page header
	 *
	 * @return void
	 */
	public static function display_header( string $h1 = '' ): void {

		if ( wp_doing_ajax() ) {
			// AJAX: Send status header before outputting html
			header( "HTTP/1.1 200 Ajax Response" );

			echo '<div class="wrap">';
		} else {

			include( static::get_constant_value( 'TEMPLATES_DIR' ) . '/header.php' );

			echo '<div class="content-body">';
			echo '<div class="wrap">';
		}

		// Hook to trigger any actions before the header displays anything within the content area
		do_action( static::get_constant_value( 'HOOK_PREFIX' ) . 'display_header' );

		if ( $h1 ) {
			echo '<h1>' . esc_html( $h1 ) . '</h1>';
		}

		do_action( static::get_constant_value( 'HOOK_PREFIX' ) . 'admin_notices' );

	}

	/**
	 * Displays the page footer
	 *
	 * @return void
	 */
	public static function display_footer(): void {

		echo '</div><!-- .wrap -->';

		if ( wp_doing_ajax() ) {
			// Stop PHP for AJAX
			exit;
		} else {

			echo '</div><!-- .content-body -->';
		}
	}

	/**
	 * Adds a notice to be displayed
	 *
	 * @param array $notice
	 */
	public final static function add_notice( array $notice ): void {

		$plugin_global = static::get_constant_value( 'GLOBAL' );

		// Grab plugin's global
		global $$plugin_global;

		$notices = $$plugin_global['admin-notices'] ?? [];
		$notices[] = $notice;

		$$plugin_global['admin-notices'] = $notices;

	}

	/**
	 * Displays all admin notices
	 */
	public final static function display_notices(): void {

		$plugin_global = static::get_constant_value( 'GLOBAL' );

		// Grab plugin's global
		global $$plugin_global;

		if ( ! empty( $$plugin_global['admin-notices'] ) ) {

			foreach ( $$plugin_global['admin-notices'] as $notice ) {

				// Display Single Notice
				static::display_notice( $notice );

			}

			// Reset Messages
			$$plugin_global['admin-notices'] = [];

		}

	}

	/**
	 * Displays given message in the admin.
	 *
	 * @param array $notice
	 *
	 * @return void
	 */
	protected final static function display_notice( array $notice ): void {

		$message = $notice['message'] ?? '';

		if ( $message ) {

			$plugin = $notice['plugin'] ?? '';
			$link_url = $notice['link_url'] ?? '';
			$link_anchor_text = $notice['link_anchor_text'] ?? '';
			$alert_level = $notice['alert_level'] ?? '';
			$dismiss = $notice['dismiss'] ?? false;

			$classes = [];

			// Set Classes
			$classes[] = 'notice-success';
			if ( $alert_level == 'info' ) {
				$classes[] = 'notice-info';
			} elseif ( $alert_level == 'warning' ) {
				$classes[] = 'notice-warning';
			} elseif ( $alert_level == 'error' ) {
				$classes[] = 'notice-error';
			}

			$classes[] = 'active';
			$classes[] = 'notice';

			if ( $dismiss ) {
				$classes[] = ' is-dismissible';
			}

			if ( $plugin ) {
				$classes[] = 'plugin-' . $plugin;
			}

			// Each message must be sanitized when set due to variability of message types
			echo '<div class="' . esc_attr( implode( ' ', $classes ) ) . '"><p>' . esc_html( $message );
			if ( $link_url && $link_anchor_text ) {
				echo ' <a href="' . esc_url( $link_url ) . '">' . esc_html( $link_anchor_text ) . '</a>';
			}
			echo '</p>';

			if ( $dismiss ) {
				echo '<button type="button" class="notice-dismiss" onclick="(function(el){el.closest(\'.notice\').remove();})(this)"><span class="screen-reader-text">' . esc_html__( 'Dismiss this notice', static::get_constant_value( 'SLUG' ) ) . '.</span></button>';
			}

			echo '</div>';

		}

	}

	/**
	 * Checks to see if the user has the permissions to view this resource and blocks them if they do not.
	 *
	 * @param bool   $network_only
	 * @param string $error
	 * @param bool   $return
	 * @param bool   $can_access_tool
	 *
	 * @return string
	 */
	public static function check_permissions( bool $network_only = false, string $error = '', bool $return = false ): string {

		$can_access_tool = static::get_class( 'Settings' )::get_current_settings()->can_user_access_tool();

		$slug = static::get_constant_value( 'SLUG' );

		if ( $network_only ) {

			if ( ! current_user_can( 'manage_network' ) ) {
				$error = __( 'Network - You do not have sufficient permissions to access this page.', $slug );
			}

		} else {
			if ( ! $can_access_tool ) {
				if ( wp_doing_ajax() ) {
					$error = __( 'You do not have proper access to do this AJAX request.', $slug );
				} elseif ( is_admin() ) {
					$error = __( 'You do not have sufficient permissions to access this page.', $slug );
				} else {
					// Catch All
					$error = __( 'You do not have sufficient permissions.', $slug );
				}
			}
		}


		if ( ! $return && $error ) {
			die( $error );
		}

		return $error;

	}

}
