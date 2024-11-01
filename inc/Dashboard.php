<?php

namespace WhereUsed;

// Prevent Direct Access
( defined( 'ABSPATH' ) ) || die;

use WP_Roles;
use WhereUsed\HelpersLibrary\Settings_Display;
use function Network_Media_Library\get_site_id;

/**
 * Dashboard class
 *
 * @package WhereUsed
 * @since   1.0.0
 */
class Dashboard {

	/**
	 * Sets all the draggable dashboard widgets before we display the dashboard
	 *
	 * @package WhereUsed
	 * @since   1.0.0
	 */
	public static function set_widgets(): void {

		$scan = Scan::get_current();

		if ( $scan->get( 'needed' ) ) {
			if ( ! $scan->get( 'start_date' ) ) {
				// Initial Scan Needed
				$show_dashboard = false;
			} else {
				// Scan needed but we have data
				$show_dashboard = true;
			}
		} else {
			$show_dashboard = true;
		}

		if ( $show_dashboard ) {
			wp_add_dashboard_widget( 'find_url_references', __( 'Find Links to URL' ), [
				static::class,
				'metabox_find_url_references',
			] );
			wp_add_dashboard_widget( 'overview_references', __( 'Overview' ), [
				static::class,
				'matabox_overview_references',
			] );

			// Display Missing Attachments by Default
			$display_attachments_not_referenced = true;

			if ( Get::using_network_media_library() ) {
				// Using Network Media Library

				if ( get_site_id() != WHEREUSED_CURRENT_SITE_ID ) {
					// Do not display meta box for other network sites
					$display_attachments_not_referenced = false;
				}

			}

			if ( $display_attachments_not_referenced ) {
				wp_add_dashboard_widget( 'attachment_no_references', __( 'Attachments Not Referenced' ), [
					static::class,
					'metabox_attachments_not_referenced',
				] );
			}

		}

		wp_add_dashboard_widget( 'help', __( 'Need Help?' ), [
			static::class,
			'metabox_help',
		], null, null, 'side' );

		wp_add_dashboard_widget( 'settings', __( 'Current Settings' ), [
			static::class,
			'metabox_settings',
		], null, null, 'side' );

		wp_add_dashboard_widget( 'scan', __( 'Site Reference Scan' ), [
			static::class,
			'metabox_scan',
		], null, null, 'side' );

	}

	/**
	 * Displays the current settings for this site
	 *
	 * @package WhereUsed
	 * @since   1.0.0
	 *
	 * @return void
	 */
	public static function metabox_settings(): void {

		$settings = Settings::get_current_settings();

		if ( $settings->can_user_access_settings() ) {
			echo '<p>' . esc_html__( 'These setting affect the manual and automatic scans. If you adjust the settings, be sure to run a fresh scan.', WHEREUSED_SLUG ) . '</p>
			<p><a href="' . esc_url( WHEREUSED_SETTINGS_URL ) . '">' . esc_html__( 'Change Settings', WHEREUSED_SLUG ) . ' &raquo;</a></p>';
		} else {
			echo '<p>' . esc_html__( 'These setting affect the manual and automatic scans. If they are incorrect, please contact the site administrator.', WHEREUSED_SLUG ) . '</p>';
		}

		?>
        <hr/>

        <h2><span class="dashicons dashicons-hourglass"></span> <?php
			esc_html_e( 'Scan Options', WHEREUSED_SLUG ); ?></h2>
        <div class="padded-left">
            <h3><span class="dashicons dashicons-menu-alt2"></span> Menus</h3>
			<?php
			$scan_menus = $settings->get( 'scan_menus' );
			echo '<ul><li>';
			echo ( $scan_menus ) ? 'Yes' : 'No';
			echo '</li></ul>';
			?>
            <h3><span class="dashicons dashicons-admin-users"></span> Users</h3>

			<?php
			$scan_users = $settings->get( 'scan_users' );
			echo '<ul><li>';
			echo ( $scan_users ) ? 'Yes' : 'No';
			echo '</li></ul>';
			?>
			<?php
			$post_types = $settings->get( 'scan_post_types', 'array' );
			$all_post_types = [];

			if ( ! empty( $post_types ) ) {
				foreach ( $post_types as $slug ) {
					if ( ! in_array( $slug, Get::excluded_post_types() ) ) {
						$post_type = get_post_type_object( $slug );
						$all_post_types[ $post_type->name ] = $post_type->label;
					}
				}
			}

			Settings_Display::list( __( 'Post Types' ), $all_post_types, 'dashicons-admin-post' );

			$taxonomies = $settings->get( 'scan_taxonomies', 'array' );
			$all_taxonomies = [];

			if ( ! empty( $taxonomies ) ) {
				foreach ( $taxonomies as $slug ) {
					$taxonomy = get_taxonomy( $slug );
					$all_taxonomies[ $taxonomy->name ] = $taxonomy->label . ' (' . $taxonomy->name . ') ';
				}
			}

			Settings_Display::list( __( 'Taxonomies' ), $all_taxonomies, 'dashicons-tag' );
			?>
        </div>

		<?php

		// Grab the roles
		$roles_obj = new WP_Roles();
		$roles_names = $roles_obj->get_names();

		// Add the administrator to the list of role that can access
		$access_tool_roles = array_merge( [ 'administrator' ], $settings->get( 'access_tool_roles', 'array' ) );
		if ( ! empty( $access_tool_roles ) ) {
			foreach ( $access_tool_roles as $key => $role_slug ) {

				if ( isset( $roles_names[ $role_slug ] ) ) {
					// Replace role slug with role name
					$access_tool_roles[ $key ] = $roles_names[ $role_slug ];
				} else {
					// This role doesn't exist for this site, so let's hide it
					unset( $access_tool_roles[ $key ] );
				}

			}
		}

		$access_settings_roles = array_merge( [ 'administrator' ], $settings->get( 'access_settings_roles', 'array' ) );

		if ( ! empty( $access_settings_roles ) ) {
			foreach ( $access_settings_roles as $key => $role_slug ) {
				// Replace role slug with role name
				$access_settings_roles[ $key ] = $roles_names[ $role_slug ];
			}
		}
		?>

        <h2><span class="dashicons dashicons-admin-users"></span> <?php
			esc_html_e( 'User Access Options', WHEREUSED_SLUG ); ?></h2>
        <div class="padded-left">
			<?php
			Settings_Display::list( __( 'Roles Can Access This Plugin' ), $access_tool_roles, 'dashicons-admin-plugins' );
			Settings_Display::list( __( "Roles Can Modify This Plugin's Settings" ), $access_settings_roles, 'dashicons-admin-settings' );
			?>
        </div>
		<?php
	}

	/**
	 * Displays the scan button and progress bar
	 *
	 * @package WhereUsed
	 * @since   1.0.0
	 */
	public static function metabox_scan() {

		$scan = Scan::get_current();

		$scan_start_date = $scan->get( 'start_date' );
		$scan_end_date = $scan->get( 'end_date' );
		$scan_needed = $scan->get( 'needed' );

		$settings = Settings::get_current_settings();

		if ( $settings->can_user_access_settings() ) {

			if ( $scan_needed ) {

				if ( $scan_start_date ) {
					// New Scan is needed
					Scan::display_start_scan_button( 'new' );

					Scan::display_scan_stats();
				} else {
					// Initial Scan is needed
					Scan::display_start_scan_button();
				}
			} elseif ( $scan_start_date && ! $scan_end_date ) {
				// Scan in progress
				Scan_Process::display_progress_bar();
			} else {
				// New Scan is needed
				Scan::display_start_scan_button( 'new' );

				Scan::display_scan_stats();
			}

		} else {

            // User doesn't have access to run a scan

			Scan::display_scan_stats();

			?><p><span class="notice-text"><?php
				_e( 'Notice:', WHEREUSED_SLUG ); ?></span> <?php
			_e( 'Only users with access to WhereUsed settings can start full scans. Please contact your website
                administrator for assistance.', WHEREUSED_SLUG ); ?></p><?php
		}

	}

	public static function matabox_overview_references(): void {

		global $wpdb;

		$values = [];
		$values[] = Get::table_name();
		$results = $wpdb->get_results( $wpdb->prepare( 'SELECT `to_url_status` as `status_code`, count(`to_url_status`) as `count` FROM `%1$s` WHERE `to_url_status` != -1 GROUP BY `to_url_status` ORDER BY `to_url_status` DESC;', $values ) );

		$labels = [];
		$data = [];
		$colors = [];

		$labels_key = Get::all_status_codes();

		$colors_key = [
			'-' => 'rgb( 240, 240, 240 )',
			'0' => 'rgb( 207, 0, 0 )',
			'1' => 'rgb( 230, 230, 230 )',
			'2' => 'rgb( 0, 100, 0 )',
			'3' => 'rgb( 70, 130, 180 )',
			'4' => 'rgb( 255, 69, 0 )',
			'5' => 'rgb( 255, 0, 0 )',
            '6' => 'rgb( 204, 204, 204 )',
			'7' => 'rgb( 204, 204, 204 )',
			'8' => 'rgb( 204, 204, 204 )',
			'9' => 'rgb( 204, 204, 204 )',
		];

		$sections = [
			'0' => 'No Response From Servers',
			'1' => '1XX Informational Responses',
			'2' => '2XX Successful Responses',
			'3' => '3XX Redirects',
			'4' => '4XX Client Errors',
			'5' => '5XX Server Errors',
            '6' => '6XX Custom Status Code',
			'7' => '7XX Custom Status Code',
			'8' => '8XX Custom Status Code',
			'9' => '9XX Custom Status Code',
		];

		$section_codes = [];

		if ( count( $results ) > 1 ) {
			// Move No Response to the beginning of the array
			$no_response = array_pop( $results );
			if ( 0 == $no_response->status_code ) {
				// Place no response eat the beginning
				array_unshift( $results, $no_response );
			} else {
				// It wasn't a no response, so add it back to the end
				$results[] = $no_response;
			}
		}


		ob_start();

		$total_count = 0;
		foreach ( $results as $result ) {
			$code_class = substr( $result->status_code, 0, 1 );
			$labels[] = $label = $labels_key[ $result->status_code ] ?? $result->status_code;
			$data[] = $result->count;
			$colors[] = $colors_key[ $code_class ];
			$total_count += $result->count;
			$section_codes[ $code_class ][ $label ] = $result;
		}

		foreach ( $section_codes as $code_class => $values ) {
			echo '<h2 style="text-align: center;">' . esc_html( $sections[ $code_class ] ) . '</h2>';

			echo '<ul class="chart-legend">';
			foreach ( $values as $label => $result ) {
                echo '<li><a class="color-box-link" href="' . esc_url( WHEREUSED_ADMIN_URL ) . '&tab=references&status=' . esc_attr( $result->status_code ) . '"><span class="color-box" style="background: ' . esc_attr( $colors_key[ $code_class ] ) . '">' . esc_html( $result->count ) . '</span>' . esc_html( $label ) . '</a></li>';
			}
			echo '</ul>';

			echo '<h3>' . esc_html( __( 'Most Popular URLs' ) ) . '</h3>';
			static::show_most_popular( $code_class );
		}

		$labels = implode( '|', $labels );
		$data = implode( '|', $data );
		$colors = implode( '|', $colors );

		// Display Chart With Legend Below Including Status Codes and Counts
		echo '
<div class="doughnut-chart">
    <canvas id="overview-chart" data-labels="' . esc_attr( $labels ) . '" data-datasets-label="' . esc_attr__( 'Detected References', WHEREUSED_SLUG ) . '" data-data="' . esc_attr( $data ) . '" data-backgroundColor="' . esc_attr( $colors ) . '"></canvas>
    <div class="center-label">' . wp_kses( sprintf( __( 'Found <span>%d</span> URLs', WHEREUSED_SLUG ), $total_count ), [ 'span' => [] ] ) . '</div>
</div>
<br />
<p style="font-weight: bold; text-align: center">' . esc_html__( 'When a URL is accessed the host server responds with a status code to inform the browser of that resource\'s status.', WHEREUSED_SLUG ) . '</p>' . ob_get_clean();

	}

	/**
	 * Find URL References Meta Box
	 *
	 * @package WhereUsed
	 * @since   1.0.0
	 */
	public static function metabox_find_url_references(): void {
		if ( ! class_exists( 'Table' ) ) {
			require_once( WHEREUSED_TABLES_DIR . '/Table.php' );
		}

		?>
        <div>
            <form method="get" action="<?php
			echo esc_attr( WHEREUSED_SETTINGS_URL ); ?>">
                <input type="hidden" name="page" value="where-used"/>
                <input type="hidden" name="tab" value="references"/>
				<?php
				Table::display_search_box( __( 'Referenced URL', WHEREUSED_SLUG ), 'usage' ); ?>
            </form>
        </div>
		<?php
	}

	/**
	 * Shows most popular references for 4a give status code class
	 *
	 * @package WhereUsed
	 * @since   1.0.0
	 */
	private static function show_most_popular( int $code_class ): void {

		global $wpdb;

		$values = [];
		$values[] = Get::table_name();
		$values[] = $code_class;

		$total_items = $wpdb->query( $wpdb->prepare( 'SELECT id FROM `%1$s` WHERE `to_url_status` LIKE "%2$s%"', $values ) );

		if ( $total_items > 0 ) {

			$results = $wpdb->get_results( $wpdb->prepare( 'SELECT COUNT(`to_url_absolute`) as `total`, `to_url`, `to_url_absolute` FROM `%1$s` WHERE `to_url_status` LIKE "%2$s%" GROUP BY `to_url_absolute` ORDER BY `total` DESC LIMIT 5;', $values ) );

			$base_url = WHEREUSED_SETTINGS_URI . '&tab=references&status=' . $code_class;
			$exact_search_url = $base_url . '&exact-search=on';
			?>
            <ul>
				<?php
				foreach ( $results as $r ) {
					if ( $r->to_url_absolute ) {
						?>
                        <li><?php
							echo '(' . esc_html( $r->total ) . ') - '; ?> <a href="<?php
							echo esc_url( admin_url( $exact_search_url . '&s=' . $r->to_url_absolute ) ); ?>"><?php
								echo esc_html( $r->to_url_absolute ); ?></a></li>
						<?php
					} else {
						?>
                        <li><?php
						echo '(' . esc_html( $r->total ) . ') - ';
						_e( 'No URL Provided', WHEREUSED_SLUG ); ?></li><?php
					}
				} ?>
            </ul>
			<?php
			if ( 0 !== $code_class ) { ?>
                <p>
                    <a href="<?php
					echo esc_url( admin_url( $base_url ) ); ?>"><?php
						printf( __( 'View All %dXX References', WHEREUSED_SLUG ), $code_class ); ?> &raquo;</a>
                </p>
				<?php
			}
		}

	}

	/**
	 * Not Referenced Attachments
	 *
	 * @package WhereUsed
	 * @since   1.0.0
	 */
	public static function metabox_attachments_not_referenced() {

		global $wpdb;

		if ( Scan::has_full_scan_ran() ) {
			// Array of all the values to be sanitized
			$values = [];
			$values[] = $wpdb->prefix . 'posts';
			$values[] = Get::table_name();
			$total_items = $wpdb->get_col( $wpdb->prepare( 'SELECT count(`ID`) FROM `%1$s` WHERE `post_type` = \'attachment\' AND `ID` NOT IN ( SELECT `to_post_id` from `%2$s` WHERE `to_post_id` > 0 GROUP BY `to_post_id` )', $values ) );
			$total_items = $total_items[0] ?? 0;

			$values[] = 0;
			$values[] = 5;
			$attachments = $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM `%1$s` WHERE `post_type` = \'attachment\' AND `ID` NOT IN ( SELECT `to_post_id` from `%2$s` WHERE `to_post_id` > 0 GROUP BY `to_post_id` ) LIMIT %3$d, %4$d', $values ) );
			?>
            <p><b><?php
					echo sprintf( __( '%d attachment not referenced.', WHEREUSED_SLUG ), $total_items ); ?></b></p>
            <ol>
				<?php
				foreach ( $attachments as $attachment ) {

					$title = ( $attachment->post_title ) ?: _draft_or_post_title();
					?>
                    <li><a href="<?php
						echo esc_attr( get_edit_post_link( $attachment->ID ) ); ?>" target="_blank"><?php
							echo esc_html( $title ); ?></a></li>
					<?php
				} ?>
            </ol>
            <p><a href="<?php
				echo esc_url( WHEREUSED_ADMIN_URL . '&tab=references&table=media&unreferenced=1' ); ?>"><?php
					_e( 'View All', WHEREUSED_SLUG ); ?> &raquo;</a></p>
			<?php
		} else {

            // Full scan has not ran
            Scan::display_results_not_available();

		}

	}

	/**
	 * Displays the scan button and progress bar
	 *
	 * @package WhereUsed
	 * @since   1.0.0
	 */
	public static function metabox_help() {
		?>
        <p>Checkout our website <a href="https://whereused.com/docs/" target="_blank">documentation</a> for assistance. Need <a href="https://wordpress.org/support/plugin/where-used/" target="_blank">support</a>?</p>

        <h2>Getting Started</h2>
        <ol>
            <li><a href="https://whereused.com/docs/tutorials/install-recommended-plugins/">Install Recommended Plugins</a></li>
            <li><a href="https://whereused.com/docs/tutorials/configuring-settings/">Configuring Settings</a></li>
            <li><a href="https://whereused.com/docs/tutorials/running-your-first-scan/">Running your first scan</a></li>
            <li><a href="https://whereused.com/docs/tutorials/use-while-editing-a-post-or-attachment/">Use while editing a post or attachment</a></li>
            <li><a href="https://whereused.com/docs/tutorials/finding-usage-of-a-link-image-or-block/">Finding usage of a link, image, or block</a></li>
            <li><a href="https://whereused.com/docs/tutorials/" target="_blank">more tutorials &raquo;</a></li>
        </ol>
        <?php
	}

}