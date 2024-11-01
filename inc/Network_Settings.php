<?php

namespace WhereUsed;

// Prevent Direct Access
( defined( 'ABSPATH' ) ) || die;

use WhereUsed\HelpersLibrary\Base;

/**
 * Class Settings - Contains all the settings information
 *
 * @package WhereUsed
 * @since   1.0.0
 */
class Network_Settings extends Base {

	protected string $option = WHEREUSED_NETWORK_OPTION; // Where we can find these settings in the option table
	protected string $db_version = '';
	protected array $db_version_history = [];

	protected array $sites = []; // The sites in the multisite

	// Scan
	protected array $scan_post_types = [];
	protected array $scan_taxonomies = []; // Taxonomies to scan
	protected bool $scan_users = true; // Scan users
	protected bool $scan_menus = true; // Scan menus

	// Access
	protected array $access_tool_roles = []; // The roles that can have access to using this tool
	protected array $access_settings_roles = []; // The roles that can have access to modifying settings

	// Cron Check Statuses
	protected bool $cron_check_status = true;
	protected string $cron_check_status_frequency = 'monthly'; // weekly, bi-weekly, monthly
	protected int $cron_check_status_dom = 0; // Which day of the month should the cron run (if monthly)
	protected string $cron_check_status_dow = ''; // Which day of the week does the cron run
	protected string $cron_check_status_tod = ''; // When the cron runs at that time of day

	protected bool $debug = false; // If true, the debug tab will appear in the admin

	/**
	 * Constructs the Settings object with provided data or gets the data from database
	 *
	 * @package WhereUsed
	 * @since   1.0.0
	 *
	 * @param array | object $settings
	 */
	public function __construct( $settings = [] ) {

		if ( empty( $settings ) ) {
			// Load from database

			// Clear cache
			wp_cache_delete( '1:notoptions', 'site-options' );

			// Get from the DB
			$db_settings = get_site_option( $this->option );

			if ( ! empty( $db_settings ) ) {
				// We already have settings in DB

				$this->overwrite( $db_settings );

				if ( ! isset( $db_settings['scan_taxonomies'] ) ) {
					$this->set( 'scan_taxonomies', Get::recommended_taxonomies() );
				}
			} else {
				// No settings in DB

				$this->set_default();

				if ( ! defined( 'WHEREUSED_NETWORK_SAVING' ) ) {
					// Save Default Settings
					$this->save( false );
				}
			}

		} else {
			// Load from provided data
			$this->load( $settings );
		}

	}

	/**
	 * Set the default settings if nothing is in the database
	 *
	 * @package WhereUsed
	 * @since   1.0.0
	 */
	protected function set_default() {

		$active_sites = [];
		if ( is_multisite() ) {
			$sites = get_sites();
			if ( ! empty( $sites ) ) {
				foreach ( $sites as $site ) {
					if ( is_plugin_active( WHEREUSED_PLUGIN ) ) {
						// This plugin is active on this site: set to use network settings by default
						$active_sites[] = $site->blog_id;
					}
				}
			}
		}

		$this->set( 'sites', $active_sites );

		$this->set( 'scan_post_types', Get::recommended_post_types() );

		$this->set( 'scan_taxonomies', Get::recommended_taxonomies() );

		$this->set( 'scan_users', true );

		$this->set( 'db_version', WHEREUSED_VERSION );

		/**
		 * Default Cron Settings
		 *
		 * We are assuming that the person is setting up this plugin during the day,
		 * so we are going to assume that 12 hours before this moment will be at night
		 * during low traffic (our default time to run).
		 */
		$hours_ago_12 = date( 'Y-m-d H:i:s', strtotime( '-12 hours' ) );
		$dom = (int) date( 'j', strtotime( $hours_ago_12 ) );
		$dom = ( $dom > 28 ) ? 28 : $dom;
		$this->set( 'cron_check_status_dom', $dom );
		$this->set( 'cron_check_status_frequency', 'monthly' );
		$this->set( 'cron_check_status_dow', strtolower( date( 'l', strtotime( $hours_ago_12 ) ) ) );
		$this->set( 'cron_check_status_tod', strtolower( date( 'H:i:s', strtotime( $hours_ago_12 ) ) ) );

 }

	/**
	 * Saves the current data that is loaded in the object to the database
	 *
	 * @package WhereUsed
	 * @since   1.0.0
	 *
	 * @param bool $display_notice Used to turn off admin notices when ran outside the admin context
	 */
	public function save( bool $display_notice = true ): Network_Settings {

		if ( ! defined( 'WHEREUSED_NETWORK_SAVING' ) ) {
			// Used to detect that we are in the process of saving. Prevents infinite loop scenarios
			define( 'WHEREUSED_NETWORK_SAVING', true );
		}

		$existing_settings = static::get_current_settings( true );

		if ( $this == $existing_settings && ! empty( get_site_option( $this->option ) ) ) {
			$response = 2;
		} else {

			$array = [];

			// Notify the sites that need to be scanned if scans are needed
			$this->are_scans_needed( $existing_settings );

			// Convert into an array before storing
			foreach ( $this as $property => $value ) {
				$array[ $property ] = $value;
			}

			// Save settings
			if ( $response = update_site_option( $this->option, $array ) ) {
				// Clear wp cache
				wp_cache_delete( '1:notoptions', 'site-options' );
			}

			$existing_settings = static::get_current_settings( true );

		}

		if ( $display_notice ) {
			// Display admin notices

			if ( $response ) {
				if ( 2 === $response ) {
					Admin::add_notice( [
						'message' => __( 'Network settings saved.', WHEREUSED_SLUG ),
						'alert_level' => 'success',
						'dismiss' => true,
					] );
				} else {
					Admin::add_notice( [
						'message' => __( 'Network settings have been saved.', WHEREUSED_SLUG ),
						'alert_level' => 'success',
						'dismiss' => true,
					] );
				}

			} else {
				Admin::add_notice( [
					'message' => __( 'Error: Network settings could not be saved.', WHEREUSED_SLUG ),
					'alert_level' => 'error',
					'dismiss' => true,
				] );
			}
		}

		return $existing_settings;

	}

	/**
	 * Gets the current settings
	 *
	 * @package WhereUsed
	 * @since   1.0.0
	 *
	 * @param bool $from_db
	 *
	 * @return \WhereUsed\Network_Settings
	 */
	public static function get_current_settings( bool $from_db = false ): Network_Settings {

		global $whereused;

		$network_settings = $whereused['network-settings'] ?? [];

		if ( $from_db || empty( $network_settings ) ) {
			$network_settings = new Network_Settings();
		}

		$whereused['network-settings'] = $network_settings;

		return $whereused['network-settings'];
	}

	/**
	 * Checks the differences of current and previous settings to determine if a scan is needed.
	 *
	 * @param object $existing_settings
	 *
	 * @return array
	 */
	protected function check_differences( object $existing_settings ): array {

		$scan_needed = [];

		if ( $this->scan_post_types != $existing_settings->scan_post_types ) {
			// Scan post types changed
			$scan_needed[] = 1;
		} elseif ( $this->scan_taxonomies != $existing_settings->scan_taxonomies ) {
			// Scan taxonomy terms changed
			$scan_needed[] = 2;
		} elseif ( $this->scan_users != $existing_settings->scan_users ) {
			// Scan users changed
			$scan_needed[] = 3;
		} elseif( $this->scan_menus != $existing_settings->scan_menus ) {
			// Scan menus changed
			$scan_needed[] = 4;
		}

		return $scan_needed;
	}

	/**
	 * Compare the network settings against the current network settings to see if a new full scan is needed
	 * for the sites using the
	 *
	 * @package WhereUsed
	 * @since   1.0.0
	 *
	 * @param \WhereUsed\Network_Settings $existing_settings
	 */
	private function are_scans_needed( Network_Settings $existing_settings ): void {

		$sites = [];

		$scan_needed = $this->check_differences( $existing_settings );

		// Grab previous and current sites affected
		$previous_sites = $existing_settings->get( 'sites' );
		$current_sites = $this->get( 'sites' );

		if ( $current_sites != $previous_sites ) {
			// Sites using network settings changed
			$scan_needed[] = 10;

			// Grab the difference between the two and only notify them to rescan
			$sites = array_diff( $previous_sites, $current_sites );
		}

		$scan_needed = array_flip( $scan_needed );

		// If anything other that sites changed, then force all sites to be rescanned
		if ( isset( $scan_needed[1] ) || isset( $scan_needed[2] ) || isset( $scan_needed[3] ) || isset( $scan_needed[4] ) ) {
			$sites = array_merge( $previous_sites, $current_sites, $sites );
		}

		$sites = array_unique( $sites );

		if ( ! empty( $scan_needed ) ) {

			$codes = '';
			foreach ( $scan_needed as $needed ) {
				$codes .= '[' . $needed . ']';
			}

			$user = wp_get_current_user();

			$is_multisite = is_multisite();

			// Notify all the sites that are affected by the modification of network settings

			if ( ! empty( $sites ) ) {
				// Notify each site
				foreach ( $sites as $site_id ) {
					if ( $is_multisite ) {
						switch_to_blog( $site_id );
					}

					if ( is_plugin_active( WHEREUSED_PLUGIN ) ) {

						$scan = Scan::get_current( true );
						$scan->set_needed( true );
						$scan->save();

						Notification::add_notification( [
							'message' => sprintf( __( 'The scan settings have been changed by %s. A new scan will need to be run to reflect these settings. %s', WHEREUSED_SLUG ), $user->display_name, $codes ),
							'link_url' => WHEREUSED_ADMIN_URL,
							'link_anchor_text' => __( 'View Scan Options on Dashboard', WHEREUSED_SLUG ),
							'alert_level' => 'notice',
						] );

					}

					if ( $is_multisite ) {
						restore_current_blog();
					}
				}
			}
		}

	}

	/**
	 * Converts the object Settings into an array
	 *
	 * @return array
	 */
	public static function get_array(): array {

		$settings = static::get_current_settings();

		$settings_array = [];
		foreach ( $settings as $key => $value ) {
			$settings_array[ $key ] = $value;
		}

		return $settings_array;
	}

	/**
	 * Tells you if the network settings are active
	 *
	 * @package WhereUsed
	 * @since   1.0.0
	 *
	 * @param int $bid Blog ID
	 *
	 * @return bool
	 */
	public function using_network_settings( int $bid = WHEREUSED_CURRENT_SITE_ID ): bool {

		$active = false;

		if ( is_multisite() ) {
			if ( is_a( $this, 'Settings' ) ) {
				$network_settings = Network_Settings::get_current_settings();

				$active = $network_settings->using_network_settings( $bid );
			} else {
				$active = in_array( $bid, $this->sites );
			}
		}

		return $active;

	}

	/**
	 * Check to see if a user can access the tool
	 *
	 * @package WhereUsed
	 * @since   1.0.0
	 *
	 * @return bool
	 */
	public function can_user_access_settings(): bool {

		global $current_user;

		$roles = $current_user->roles;

		// Check all user roles
		foreach ( $roles as $role_slug ) {
			if ( $this->can_role_access_settings( $role_slug ) ) {
				return true;
			}
		}

		return false;

	}

	/**
	 * Check to see if a role can access the settings
	 *
	 * @package WhereUsed
	 * @since   1.0.0
	 *
	 * @param string $role_slug
	 *
	 * @return bool
	 */
	public function can_role_access_settings( string $role_slug = '' ): bool {

		if ( 'administrator' === $role_slug ) {
			// Of course admin can
			return true;
		}

		$role = get_role( $role_slug );

		// Assume nothing: make sure they have the capability
		if ( $role->has_cap( Settings::get_user_access_capability() ) ) {
			return in_array( $role_slug, $this->access_settings_roles );
		}

		return false;
	}

	/**
	 * This is the capability needed so that a user can be an option to have access to the tool and settings
	 *
	 * @package WhereUsed
	 * @since   1.0.0
	 *
	 * @return string
	 */
	public static function get_user_access_capability(): string {

		/**
		 * Filter: whereused_user_access_capability
		 *
		 * @package WhereUsed
		 * @since   1.0.0
		 *
		 * @link https://whereused.com/docs/hooks/whereused_user_access_capability/
		 *
		 * @return string
		 */
		return apply_filters( WHEREUSED_HOOK_PREFIX . 'user_access_capability', 'edit_pages' );

	}

	/**
	 * Check to see if a user can access the tool
	 *
	 * @package WhereUsed
	 * @since   1.0.0
	 *
	 * @return bool
	 */
	public function can_user_access_tool(): bool {

		global $current_user;

		if ( current_user_can( Settings::get_user_access_capability() ) ) {

			$roles = $current_user->roles;

			// Check all user roles
			foreach ( $roles as $role_slug ) {
				if ( $this->can_role_access_tool( $role_slug ) ) {
					return true;
				}
			}
		}

		return false;

	}

	/**
	 * Check to see if a role can access the tool
	 *
	 * @package WhereUsed
	 * @since   1.0.0
	 *
	 * @param string $role_slug
	 *
	 * @return bool
	 */
	public function can_role_access_tool( string $role_slug = '' ): bool {

		if ( 'administrator' === $role_slug ) {
			// Of course admin can
			return true;
		}

		// If they can access settings, they can access the tool
		if ( $this->can_role_access_settings( $role_slug ) ) {
			return true;
		}

		$role = get_role( $role_slug );

		// Assume nothing: make sure they have the capability
		if ( $role->has_cap( Settings::get_user_access_capability() ) ) {
			return in_array( $role_slug, $this->access_tool_roles );
		}

		return false;

	}

	/**
	 * Checks if the given post type can be scanned per the current settings
	 *
	 * @package WhereUsed
	 * @since   1.0.0
	 *
	 * @param $post_type
	 *
	 * @return bool
	 */
	public function can_scan_post_type( $post_type ): bool {

		return in_array( $post_type, $this->scan_post_types );

	}

	/**
	 * Adds a blog ID to the array of sites using network settings
	 *
	 * @param int $bid Blog ID
	 *
	 * @return void
	 */
	public function add_site( int $bid ): void {

		$sites = $this->get( 'sites' );

		if ( ! in_array( $bid, $sites ) ) {
			$sites[] = $bid;
			$this->set( 'sites', $sites );
			$this->save();
		}

	}

	/**
	 * Sets the version history so we can use this for troubleshooting upgrade issues
	 *
	 * @package WhereUsed
	 * @since 1.1.0
	 *
	 * @param string $version New version
	 * @return void
	 */
	public function set_version( string $version ) {
		$current_version = $this->get( 'db_version' );
		$version_history = $this->get( 'db_version_history' );

		if ( ! isset( $version_history[ $current_version ] ) ) {
			// Just in case the current version didn't go into history
			$version_history[ $this->get('db_version') ] = wp_date('Y-m-d H:i:s');
		}

		// A new version into history
		$version_history[ $version ] = wp_date('Y-m-d H:i:s');
		$this->set('db_version_history', $version_history);
		$this->set('db_version', $version);
	}

}
