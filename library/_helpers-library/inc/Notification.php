<?php

namespace WhereUsed\HelpersLibrary;

// Prevent Direct Access
( defined( 'ABSPATH' ) ) || die;

/**
 * Class Notification - A child class must extend this class
 * and then the init() function must be called when the
 * child class is loaded.
 */
abstract class Notification extends Base {

	protected string $message = '';
	protected bool $read_status = false;
	protected string $date = '';
	protected string $alert_level = 'info';
	protected string $link_url = '';
	protected string $link_anchor_text = '';

	/**
	 * Constructor for Notification class: loads data and sets properties
	 *
	 * @param array|object $data
	 */
	function __construct( $data = [] ) {

		// Load data
		parent::__construct( $data );

		$this->date = ( $this->date ) ?: wp_date( 'Y-m-d H:i:s' );

	}

	/**
	 * Registers all hooks related to notifications
	 *
	 * @return void
	 */
	public static function init(): void {

		static::schedule_cron();

		// Update Read Status
		add_action( 'wp_ajax_' . static::get_ajax_hook(), [
			static::class,
			'update_read_status',
		] );

	}

	/**
	 * Schedules the notifications cleanup cron
	 *
	 * @return void
	 */
	public static function schedule_cron(): void {

		$cleanup_cron = static::get_cleanup_cron_hook();
		add_action( $cleanup_cron, [
			static::class,
			'cleanup_notifications',
		] );
		register_deactivation_hook( static::get_constant_value( 'FILE' ), [
			static::class,
			'cron_deactivate',
		] );

		if ( ! wp_next_scheduled( $cleanup_cron ) ) {
			wp_schedule_event( time(), 'daily', $cleanup_cron );
		}
	}

	/**
	 * Deletes expired notifications older than 60 days
	 *
	 * @return void
	 */
	public static function cleanup_notifications(): void {

		// Get current notifications
		$notifications = static::get_notifications();

		$not_expired = [];

		if ( ! empty( $notifications ) ) {

			$days_ago = strtotime( '-90 days' );
			$max_notifications = 30;

			$count = 0;

			// Count the unread notifications
			foreach ( $notifications as $notification ) {
				++ $count;

				if ( $count <= $max_notifications ) {
					$read_status = $notification->get( 'read_status' );
					$date = strtotime( $notification->get( 'date' ) );

					// Keep unread notifications and ones that have not expired
					if ( ! $read_status || $date > $days_ago ) {
						$not_expired[] = $notification;
					}
				}
			}
		}

		// Save list of not expired notifications
		static::save_notifications( $not_expired );

	}

	/**
	 * Gets the notifications from the current site specific to this plugin
	 *
	 * @param bool $force_update
	 *
	 * @return array
	 */
	public static function get_notifications( bool $force_update = false ): array {

		$plugin_global = static::get_constant_value( 'GLOBAL' );

		// Grab plugin's global
		global $$plugin_global;

		if ( $force_update || empty( $$plugin_global['notifications'] ) ) {
			$$plugin_global['notifications'] = get_option( static::get_constant_value( 'NOTIFICATIONS_OPTION' ) );

			if ( ! is_array( $$plugin_global['notifications'] ) ) {
				$$plugin_global['notifications'] = [];
			}
		}

		return $$plugin_global['notifications'];
	}

	/**
	 * Saves an array of notifications to the database.
	 *
	 * @param array $notifications
	 *
	 * @return bool
	 */
	public static function save_notifications( array $notifications ): bool {

		$option = static::get_constant_value( 'NOTIFICATIONS_OPTION' );
		$result = update_option( $option, $notifications, false );

		if ( $result ) {
			// Clear cache
			wp_cache_delete( $option, 'options' );
		}

		return $result;

	}

	/**
	 * Removes the schedule cron on plugin deactivation
	 *
	 * @return void
	 */
	public static function cron_deactivate(): void {
		wp_clear_scheduled_hook( static::get_cleanup_cron_hook() );
	}

	/**
	 * Adds a notification to the current notifications
	 *
	 * @param array $data The new notification we want to add to the DB
	 */
	public static function add_notification( array $data ): void {

		// Get current notifications
		$notifications = static::get_notifications();

		// Create new notification from provided dataS
		$notification = new static( $data );

		// Add new notification to beginning of array
		array_unshift( $notifications, $notification );

		// Save list of notifications
		static::save_notifications( $notifications );

	}

	/**
	 * Displays the notification bell icon in the header
	 *
	 * @return void
	 */
	public static function display_bell(): void {

		$notifications = static::get_notifications();

		$unread = 0;

		if ( ! empty( $notifications ) ) {
			// Count the unread notifications
			foreach ( $notifications as $notification ) {
				if ( ! $notification->get( 'read_status' ) ) {
					$unread ++;
				}
			}
		}

		$class = ( $unread > 0 ) ? 'on' : '';

		echo '<a href="#notifications" id="notifications" class="dashicons dashicons-bell notifications-bell ' . esc_attr( $class ) . '"><span class="bell-inner"><span class="ada-hidden">' . esc_html__( 'Notifications', static::get_constant_value( 'SLUG' ) ) . '</span><span class="count">' . esc_html( $unread ) . '</span></span></a>
		<ul class="notifications" data-nonce="' . esc_attr( wp_create_nonce( static::get_nonce_key() ) ) . '" data-action="' . esc_attr( static::get_ajax_hook() ) . '"><li><h3>' . esc_html__( 'Notifications', static::get_constant_value( 'SLUG' ) ) . '</h3><a href="#all-read" class="mark-all-read">' . esc_html__( 'Mark All As Read', static::get_constant_value( 'SLUG' ) ) . '</a></li>';

		if ( empty( $notifications ) ) {
			echo '<li>' . esc_html__( 'No notifications found', static::get_constant_value( 'SLUG' ) ) . '</li>';
		} else {
			$count = 0;
			foreach ( $notifications as $notification ) {

				++ $count;
				$notification = new static( $notification );

				$class = $notification->get( 'alert_level' );
				$read_status = $notification->get( 'read_status' );
				$class .= ( $read_status ) ? '' : ' unread';

				$link_url = $notification->get( 'link_url' );
				$link_anchor_text = $notification->get( 'link_anchor_text' );

				echo '<li class="' . esc_attr( $class ) . ' notice-' . esc_attr( $count ) . '"><p>' . esc_html( $notification->get( 'message' ) ) . '</p>';
				echo ( $read_status ) ? '<input class="read-status" name="read_status" type="checkbox" CHECKED  aria-label="' . esc_attr__( 'Read Status', static::get_constant_value( 'SLUG' ) ) . '"/>' : '<input class="read-status" name="read_status" type="checkbox" aria-label="' . esc_attr__( 'Read Status', static::get_constant_value( 'SLUG' ) ) . '"/>';
				echo ( $link_url && $link_anchor_text ) ? '<p><a href="' . esc_url( $link_url ) . '" class="link">' . esc_html( $link_anchor_text ) . '</a></p>' : '';
				echo '<div class="date">' . esc_html( $notification->get( 'date' ) ) . '</div></li>';
			}
		}

		echo '</ul>';

	}

	/**
	 * Sets the read_status from outside the class
	 *
	 * @param bool $value
	 *
	 * @return void
	 */
	public function set_read_status( bool $value ): void {

		$this->set( 'read_status', $value );

	}

	/**
	 * AJAX - Update notification read status
	 *
	 * @return void
	 */
	public final static function update_read_status(): void {

		// Require nonce specific to this user
		if ( ! wp_verify_nonce( REQUEST::text_field( 'nonce' ), static::get_nonce_key() ) ) {

			$response = [ 'html' => static::get_constant_value( 'SLUG' ) . ' - ' . __( 'Invalid nonce. Try refreshing the page to fix the issue.', static::get_constant_value( 'SLUG' ) ) ];
			error_log( $response['html'] );

			wp_send_json( $response, 401 );

		} else {
			$marked_read = REQUEST::array( 'checked' );

			$updated = false;

			if ( ! empty( $marked_read ) ) {

				$notifications = static::get_notifications();

				if ( ! empty( $notifications ) ) {
					$num = 0;
					$update = [];
					foreach ( $notifications as $notification ) {
						if ( isset( $marked_read[ $num ] ) ) {
							$notification->set_read_status( filter_var( $marked_read[ $num ], FILTER_VALIDATE_BOOLEAN ) );
						}
						$update[] = $notification;
						$num ++;
					}

					$updated = static::save_notifications( $update );

				}
			}

			$response = ( $updated ) ? 1 : 2;

			wp_send_json( [
				'html' => $response,
				'nonce' => wp_create_nonce( static::get_nonce_key() ),
			] );
		}

	}

	/**
	 * Retrieves the ajax hook use to update the read statuses
	 *
	 * @package WhereUsed\HelpersLibrary
	 * @since   1.1.0
	 *
	 * @return string
	 * @throws \ReflectionException
	 */
	public static function get_ajax_hook(): string {

		return static::get_constant_value( 'HOOK_PREFIX' ) . 'notification_read_update';

	}

	/**
	 * Retrieves the nonce key
	 *
	 * @package WhereUsed\HelpersLibrary
	 * @since   1.1.0
	 *
	 * @return string
	 */
	public static function get_nonce_key(): string {

		return static::get_ajax_hook() . '_' . get_current_user_id();

	}

	/**
	 * Retrieves the cleanup cron hook
	 *
	 * @package WhereUsed\HelpersLibrary
	 * @since   1.1.0
	 *
	 * @return string
	 * @throws \ReflectionException
	 */
	public static function get_cleanup_cron_hook(): string {

		return static::get_constant_value( 'HOOK_PREFIX' ) . 'cleanup_notifications_cron';

	}

}
