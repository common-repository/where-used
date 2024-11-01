<?php

namespace WhereUsed;

/**
 * Network Settings Page
 *
 * This page manages settings across all sites for a multisite setups
 */

// Prevent Direct Access (require main file to be loaded)
( defined( 'ABSPATH' ) ) || die;

Admin::check_permissions( true );

// Check to ensure only multisite can see
( is_multisite() ) || die( 'Not Multisite' );

use WhereUsed\HelpersLibrary\REQUEST;
use WhereUsed\HelpersLibrary\Settings_Display;

$sites = Get::sites();

$settings = Network_Settings::get_current_settings();

$current_user_id = get_current_user_id();

$scan_running = [];

// check all the sites to see if any scans are running
foreach ( $sites as $site ) {
	switch_to_blog( $site->blog_id );

	if ( is_plugin_active( WHEREUSED_PLUGIN ) ) {
		$current_scan = Scan::get_current(true);
		if ( $current_scan->is_running() ) {
			$scan_running[ $site->blog_id ] = $site;
		}
	}

	restore_current_blog();
}
if ( ! empty( $scan_running ) ) {
	Admin::add_notice( [
		'message' => __( 'Warning: You cannot change network settings while a scan is running on one of the sites. Please wait for the scan to finish or use the site\'s dashboard to cancel the scan.', WHEREUSED_SLUG ),
		'alert_level' => 'warning',
	] );
} elseif ( ! wp_doing_ajax() && 'POST' === REQUEST::SERVER_text_field( 'REQUEST_METHOD' ) ) {
	// Handle Saving Settings

	if ( wp_verify_nonce( REQUEST::text_field( 'nonce' ), WHEREUSED_SLUG . '-save-network-settings-' . $current_user_id ) ) {

		$var_types = [
			'sites' => 'array',
			'scan_post_types' => 'array',
			'scan_taxonomies' => 'array',
			'access_tool_roles' => 'array',
			'access_settings_roles' => 'array',
		];

		// This makes sure that empty arrays exists for these keys if they do not exist
		$default_values = [
			'sites' => [],
			'scan_post_types' => [],
			'scan_taxonomies' => [],
			'access_tool_roles' => [],
			'access_settings_roles' => [],
		];

		$settings->overwrite( REQUEST::POST( $var_types, $default_values ) );

		// Use saved settings
		$settings = $settings->save();

	} else {
		Admin::add_notice( [
			'message' => __( 'Error: Expired session. Please try again.', WHEREUSED_SLUG ),
			'alert_level' => 'error',
		] );
	}
}

// Disable fields if a scan is running on the network
$disabled = ! empty( $scan_running );

Admin::display_header();

?>
    <form method="post" action="<?php
	echo esc_url( WHEREUSED_SETTINGS_NETWORK_URL ); ?>">
        <h2 style="text-align:center"><span class="dashicons dashicons-admin-multisite"></span> <?php
			esc_html_e( 'Network Settings', WHEREUSED_SLUG ); ?></h2>
        <p style="text-align:center"><?php
			esc_html_e( 'These settings are global and affect all sites selected below:', WHEREUSED_SLUG ); ?></p>
        <table class="settings-network">
            <tr class="sites-row">

                <td class="label">
                    <label for="sites[]"><?php
						esc_html_e( 'Sites Using Network Settings', WHEREUSED_SLUG ); ?></label>
                    <p class="info"><?php
						esc_html_e( "Choose which sites that you would like to use the network settings.", WHEREUSED_SLUG ); ?></p>
                </td>
                <td>
					<?php

					$options = [];
					foreach ( $sites as $site ) {

						if ( is_multisite() ) {
							switch_to_blog( $site->blog_id );
						}

						if ( is_plugin_active( WHEREUSED_PLUGIN ) ) {
							$scan = Scan::get_current( true );

							// Links beside checkboxes
							$append = [];
							$append[] = [
								'text' => __( 'settings', WHEREUSED_SLUG ),
								'link' => get_admin_url( $site->blog_id, WHEREUSED_SETTINGS_URI ),
								'style' => '',
							];

                            if ( isset( $scan_running[ $site->blog_id ] )){
                                // This site's scan is running

	                            $append[] = [
		                            'text' => __( 'scan running', WHEREUSED_SLUG ),
		                            'link' => get_admin_url( $site->blog_id, WHEREUSED_ADMIN_URI . '#scan' ),
		                            'link-icon' => 'spin dashicons-update',
		                            'style' => 'color:#128000; font-weight: bold;',
		                            'before' => ' - ',
	                            ];
                            } else {
                                // Site is not currently running a scan

	                            // Add link if a scan is needed
	                            if ( $scan->get( 'needed' ) ) {
		                            $append[] = [
			                            'text' => __( 'scan needed', WHEREUSED_SLUG ),
			                            'link' => get_admin_url( $site->blog_id, WHEREUSED_ADMIN_URI . '#scan' ),
			                            'style' => 'color:#ba4200;',
			                            'before' => ' - ',
		                            ];
	                            }
                            }

							$options[] = [
								'value' => $site->blog_id,
								'label' => $site->domain,
								'description' => '',
								'append' => $append,
							];
						}

						if ( is_multisite() ) {
							restore_current_blog();
						}
					}

					$args = [
						'property' => 'sites',
						'settings' => $settings,
						'options' => $options,
						'disabled' => $disabled,
					];

					Settings_Display::checkboxes( $args );

					?>
                </td>
            </tr>
            <tr>
                <td colspan="2">

					<?php
					include( 'shared-settings.php' );
					?>
                </td>
            </tr>
	        <?php if ( empty( $scan_running ) ) { ?>
            <tr>
                <td colspan="2">
                    <input type="hidden" name="nonce" value="<?php
					echo esc_attr( wp_create_nonce( WHEREUSED_SLUG . '-save-network-settings-' . $current_user_id ) ); ?>"/>

					<?php
					submit_button( __( 'Save Network Settings', WHEREUSED_SLUG ) ); ?>

                </td>
            </tr>
            <?php } ?>
        </table>
    </form>
<?php

Admin::display_footer();