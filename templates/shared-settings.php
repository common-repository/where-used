<?php

namespace WhereUsed;

use WP_Roles;
use WhereUsed\HelpersLibrary\Settings_Display;

/**
 * Shared Settings Section
 *
 * This section of content is on both the network and the site settings pages
 */

// Prevent Direct Access
( defined( 'ABSPATH' ) ) || die;

$disabled_by_network = ! is_network_admin() && isset( $using_network_settings ) && $using_network_settings;

$disabled = ! empty( $scan_running ) || $disabled_by_network;

Admin::check_permissions();

// $settings should be set in the parent template file that includes this file (network.php or settings.php)
$settings = $settings ?? [];

?>

<h2 style="text-align: center"><span class="dashicons dashicons-hourglass"></span> <?php
	esc_html_e( 'Scan Options', WHEREUSED_SLUG ); ?></h2>

<p style="text-align: center"><?php
	esc_html_e( 'Choose which areas of the site to scan.', WHEREUSED_SLUG ); ?>
</p>

<table id="scan-options">
    <tr class="menus-row">

        <td class="label"><label for="scan_menus"><?php
				esc_html_e( 'Menus', WHEREUSED_SLUG ); ?></label>
            <p class="info"><?php
				esc_html_e( 'Do you want to scan menus?', WHEREUSED_SLUG ); ?>
				<?php
				if ( current_user_can( 'manage_options' ) ) { ?>
                    <br><a href="<?php
					echo esc_attr( admin_url( 'nav-menus.php' ) ); ?>"><?php
						echo esc_html( __( 'View Menus', WHEREUSED_SLUG ) ); ?></a>
				<?php
				} ?></p>
        </td>
        <td>
			<?php

			$options = [];

			$options[] = [
				'value' => 1,
				'label' => 'Yes',
			];

			$options[] = [
				'value' => 0,
				'label' => 'No',
			];

			$args = [
				'property' => 'scan_menus',
				'settings' => $settings,
				'options' => $options,
				'disabled' => $disabled,
			];

			Settings_Display::select( $args );

			?>
        </td>
    </tr>
    <tr class="users-row">

        <td class="label"><label for="scan_users"><?php
				esc_html_e( 'Users', WHEREUSED_SLUG ); ?></label>
            <p class="info"><?php
				esc_html_e( 'Do you want to scan user meta?', WHEREUSED_SLUG ); ?>
				<?php
				if ( current_user_can( 'manage_options' ) ) { ?>
                    <br><a href="<?php
					echo esc_attr( admin_url( 'users.php' ) ); ?>"><?php
						echo esc_html( __( 'View Users', WHEREUSED_SLUG ) ); ?></a>
				<?php
				} ?></p>
        </td>
        <td>
			<?php

			$options = [];

			$options[] = [
				'value' => 1,
				'label' => 'Yes',
			];

			$options[] = [
				'value' => 0,
				'label' => 'No',
			];

			$args = [
				'property' => 'scan_users',
				'settings' => $settings,
				'options' => $options,
				'disabled' => $disabled,
			];

			Settings_Display::select( $args );

			?>
        </td>
    </tr>
    <tr class="post-types-row">

        <td class="label"><label for="scan_post_types"><?php
				esc_html_e( 'Post Types', WHEREUSED_SLUG ); ?></label>
            <p class="info"><?php
				esc_html_e( 'Choose the post types to scan. Scan includes content and post meta.', WHEREUSED_SLUG ); ?></p>
        </td>
        <td>
			<?php
			$all_post_types = [];

            $excluded_post_types = Get::excluded_post_types();

			if ( is_network_admin() ) {

				$sites = get_sites();

				if ( ! empty( $sites ) ) {
					foreach ( $sites as $site ) {
						switch_to_blog( $site->blog_id );
						$post_types = get_post_types();

						foreach ( $post_types as $slug ) {

							if ( in_array( $slug, $excluded_post_types ) ) {
								// Post type excluded
								continue;
							}

							$post_type = get_post_type_object( $slug );
							$all_post_types[ $post_type->name ] = $post_type->label . ' (' . $post_type->name . ') ';

						}
						restore_current_blog();
					}
				}

			} else {
				$post_types = get_post_types();

				foreach ( $post_types as $slug ) {
					if ( in_array( $slug, $excluded_post_types ) ) {
                        // Post type excluded
						continue;
					}

					$post_type = get_post_type_object( $slug );
					$all_post_types[ $post_type->name ] = $post_type->label . ' (' . $post_type->name . ') ';

				}
			}

			asort( $all_post_types );

			$options = [];

			$recommended = Get::recommended_post_types();
			if ( ! empty( $all_post_types ) ) {
				foreach ( $all_post_types as $slug => $label ) {

					if ( in_array( $slug, $excluded_post_types ) ) {
						// Post type excluded
						continue;
					}

					$append = [];

					if ( in_array( $slug, $recommended ) ) {
						$append[] = [
							'text' => __( 'recommended', WHEREUSED_SLUG ),
							'link' => '',
							'style' => 'font-weight:bold; font-style:italic',
						];

						// Add a dash between recommended and the label
						$label .= ' - ';
					}

					$options[] = [
						'value' => $slug,
						'label' => $label,
						'description' => '',
						'append' => $append,
					];
				}

				$args = [
					'property' => 'scan_post_types',
					'settings' => $settings,
					'options' => $options,
					'disabled' => $disabled,
				];

				Settings_Display::checkboxes( $args );
			}

			?>

        </td>
    </tr>
    <tr class="taxonomies-row">

        <td class="label"><label for="scan_taxonomies"><?php
				esc_html_e( 'Taxonomies', WHEREUSED_SLUG ); ?></label>
            <p class="info"><?php
				esc_html_e( 'Choose taxonomies to scan. Scan includes term description and term meta.', WHEREUSED_SLUG ); ?></p>
        </td>
        <td>
			<?php

            $excluded_taxonomies = Get::excluded_taxonomies();

			if ( is_network_admin() ) {

				$all_taxonomies = [];
				$sites = get_sites();

				if ( ! empty( $sites ) ) {
					foreach ( $sites as $site ) {
						switch_to_blog( $site->blog_id );
						$taxonomies = get_taxonomies();

						foreach ( $taxonomies as $slug ) {
							if ( in_array( $slug, $excluded_taxonomies ) ) {
								continue;
							}
							$taxonomy = get_taxonomy( $slug );
							$all_taxonomies[ $taxonomy->name ] = $taxonomy->label . ' (' . $taxonomy->name . ') ';
						}
						restore_current_blog();
					}
				}

			} else {
				$all_taxonomies = [];
				$taxonomies = get_taxonomies();

				foreach ( $taxonomies as $slug ) {
					if ( in_array( $slug, $excluded_taxonomies ) ) {
						continue;
					}
					$taxonomy = get_taxonomy( $slug );
					$all_taxonomies[ $taxonomy->name ] = $taxonomy->label . ' (' . $taxonomy->name . ') ';
				}
			}

			asort( $all_taxonomies );

			$options = [];

			$recommended = Get::recommended_taxonomies();
			if ( ! empty( $all_taxonomies ) ) {
				foreach ( $all_taxonomies as $slug => $label ) {

					if ( in_array($slug, $excluded_taxonomies) ) {
						continue;
					}

					$append = [];
					if ( in_array( $slug, $recommended ) ) {
						$append[] = [
							'text' => __( 'recommended', WHEREUSED_SLUG ),
							'link' => '',
							'style' => 'font-weight:bold; font-style:italic',
						];
						// Add a dash between recommended and the label
						$label .= ' - ';
					}

					$options[] = [
						'value' => $slug,
						'label' => $label,
						'description' => '',
						'append' => $append,
					];
				}

				$args = [
					'property' => 'scan_taxonomies',
					'settings' => $settings,
					'options' => $options,
					'disabled' => $disabled,
				];

				Settings_Display::checkboxes( $args );
			}

			?>
        </td>
    </tr>
</table>

<h2 style="text-align: center;"><span class="dashicons dashicons-admin-users"></span> <?php
	esc_html_e( 'User Access Options', WHEREUSED_SLUG ); ?></h2>
<p style="text-align: center;"><?php
	echo esc_html( sprintf( __( 'Only users with the capability "%s", can be selected.', WHEREUSED_SLUG ), Settings::get_user_access_capability() ) ); ?></p>
<table id="access-options">
    <tr class="access-tool-roles-row">
        <td><?php

			// Grab the roles
			$roles_obj = new WP_Roles();
			$roles = $roles_obj->get_names();

			$rows_value = [];
			foreach ( $roles as $role_slug => $role_name ) {

				$row_value = [
					'label' => $role_name,
					'value' => $role_slug,
					'excluded' => [],
					// Displays an X
					'disabled' => [],
					// Show checkbox, but disabled
					'checked' => [],
					// Fields to automatically check
				];

				if ( 'administrator' == $role_slug ) {
					// Of course admin has access
					$row_value['disabled'] = [
						'access_tool_roles',
						'access_settings_roles',
					];
					$row_value['checked'] = [
						'access_tool_roles',
						'access_settings_roles',
					];
				} else {
					$role = get_role( $role_slug );

					if ( ! $role->has_cap( Settings::get_user_access_capability() ) ) {
						$row_value['excluded'] = [
							'access_tool_roles',
							'access_settings_roles',
						];
					}
				}

				$rows_value[] = $row_value;
			}

			$args = [
				'rows' => [
					'label' => __( 'User Role', WHEREUSED_SLUG ),
					'values' => $rows_value,
				],
				'options' => [
					0 => [
						'label' => __( 'Tool Access', WHEREUSED_SLUG ),
						'property' => 'access_tool_roles',
						'value' => $settings->get( 'access_tool_roles' ),
					],
					1 => [
						'label' => __( 'Settings Access', WHEREUSED_SLUG ),
						'property' => 'access_settings_roles',
						'value' => $settings->get( 'access_settings_roles' ),
					],
				],
				'disabled' => $disabled,
			];

			Settings_Display::checkbox_table( $args ); ?></td>
    </tr>
</table>

<h2 style="text-align: center;"><span class="dashicons dashicons-calendar-alt"></span> <?php
	_e( 'Check Status Codes Cron', WHEREUSED_SLUG ); ?></h2>
<p style="text-align: center;"><?php
	_e( 'This cron runs periodically to ensure that reference status codes are updated periodically.', WHEREUSED_SLUG ); ?></p>

<table id="cron-options">
    <tr class="cron-check-status-row">
        <td class="label"><label for="cron_check_status"><?php
				_e( 'Schedule Check Status Cron', WHEREUSED_SLUG ); ?></label></td>
        <td>
			<?php

			$options = [
				[
					'value' => 1,
					'label' => 'Yes',
				],
				[
					'value' => 0,
					'label' => 'No',
				],
			];

			$args = [
				'property' => 'cron_check_status',
				'settings' => $settings,
				'options' => $options,
				'disabled' => $disabled,
			];

			Settings_Display::select( $args );

			?>
        </td>
    </tr>
    <tr class="cron-check-status-frequency-row">
        <td class="label"><label for="cron_check_status_frequency"><?php
				_e( 'Frequency', WHEREUSED_SLUG ); ?></label></td>
        <td>
			<?php

			$options = [
				'monthly' => __( 'Monthly', WHEREUSED_SLUG ),
                'bi-weekly' => __( 'Bi-Weekly', WHEREUSED_SLUG ),
            ];

			$args = [
				'property' => 'cron_check_status_frequency',
				'settings' => $settings,
				'options' => $options,
				'disabled' => $disabled,
			];

			Settings_Display::select( $args );
			?>
        </td>
    </tr>

    <tr class="cron-check-status-dom-row">
        <td class="label"><label for="cron_check_status_dom"><?php
				_e( 'Day of Month', WHEREUSED_SLUG ); ?></label></td>
        <td>
			<?php

			$options = [
				1 => 1,
				2 => 2,
				3 => 3,
				4 => 4,
				5 => 5,
				6 => 6,
				7 => 7,
				8 => 8,
				9 => 9,
				10 => 10,
				11 => 11,
				12 => 12,
				13 => 13,
				14 => 14,
				15 => 15,
				16 => 16,
				17 => 17,
				18 => 18,
				19 => 19,
				20 => 20,
				21 => 21,
				22 => 22,
				23 => 23,
				24 => 24,
				25 => 25,
				26 => 26,
				27 => 27,
				28 => 28,
			];

			$args = [
				'property' => 'cron_check_status_dom',
				'settings' => $settings,
				'options' => $options,
				'disabled' => $disabled,
			];

			Settings_Display::select( $args );
			?>

        </td>
    </tr>


    <tr class="cron-check-status-dow-row">
        <td class="label"><label for="cron_check_status_dow"><?php
				_e( 'Day of Week', WHEREUSED_SLUG ); ?></label></td>
        <td>
			<?php

			$options = [
				'sunday' => __( 'Sunday', WHEREUSED_SLUG ),
				'monday' => __( 'Monday', WHEREUSED_SLUG ),
				'tuesday' => __( 'Tuesday', WHEREUSED_SLUG ),
				'wednesday' => __( 'Wednesday', WHEREUSED_SLUG ),
				'thursday' => __( 'Thursday', WHEREUSED_SLUG ),
				'friday' => __( 'Friday', WHEREUSED_SLUG ),
				'saturday' => __( 'Saturday', WHEREUSED_SLUG ),
			];

			$args = [
				'property' => 'cron_check_status_dow',
				'settings' => $settings,
				'options' => $options,
				'disabled' => $disabled,
			];

			Settings_Display::select( $args );
			?>

        </td>
    </tr>

    <tr class="cron-check-status-tod-row">
        <td class="label"><label for="cron_check_status_tod"><?php
				_e( 'Time', WHEREUSED_SLUG ); ?></label></td>
        <td>
			<?php

			$args = [
				'property' => 'cron_check_status_tod',
				'settings' => $settings,
				'type' => 'time',
				'disabled' => $disabled,
			];
			Settings_Display::input( $args );
			?>
        </td>
    </tr>
</table>
<table id="debug-options">
    <tr class="debug-row">
        <td class="label"><label for="debug"><?php
				esc_html_e( 'Debug Mode', WHEREUSED_SLUG ); ?></label>
            <p class="info"><?php echo esc_html(__('Enable to display "Debug" page in WhereUsed main menu.', WHEREUSED_SLUG)); ?></p>
        </td>
        <td>
		    <?php

		    $options = [
			    '' => __( 'Disabled', WHEREUSED_SLUG ),
			    '1' => __( 'Enabled', WHEREUSED_SLUG ),
		    ];

		    $args = [
			    'property' => 'debug',
			    'settings' => $settings,
			    'options' => $options,
			    'disabled' => $disabled,
		    ];

		    Settings_Display::select( $args );
		    ?>
        </td>
    </tr>
</table>
