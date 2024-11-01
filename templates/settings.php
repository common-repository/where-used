<?php

namespace WhereUsed;

/**
 * Site Settings Page
 *
 * This page manages settings for a specific site
 *
 * @package WhereUsed
 * @since   1.0.0
 */

// Prevent Direct Access
( defined( 'ABSPATH' ) ) || die;

Admin::check_permissions();

use WhereUsed\HelpersLibrary\REQUEST;

// Grab settings
$settings = Settings::get_current_settings( true );

if ( ! $settings->can_user_access_settings() ) {
	error_log( $error = __( 'You do not have permission to access settings.', WHEREUSED_SLUG ) );
	die( $error );
}

$current_user_id = get_current_user_id();

$using_network_settings = $settings->using_network_settings();
$settings_network = [];

$scan_running = [];

$current_scan = Scan::get_current(true);

// Get current scan so we can check whether a scan is running
if( $current_scan->is_running() ){
    // A Scan is currently running

    $scan_running[1] = true;

	Admin::add_notice( [
		'message' => __( 'Warning: You cannot changes setting while a scan is running. Please cancel the scan on the dashboard or wait for it to finish.', WHEREUSED_SLUG ),
		'alert_level' => 'warning',
	] );

} elseif ( ! wp_doing_ajax() && ! $using_network_settings && 'POST' === REQUEST::SERVER_text_field( 'REQUEST_METHOD' ) ) {

	// Handle Saving Settings

	if ( wp_verify_nonce( REQUEST::text_field( 'nonce' ), WHEREUSED_SLUG . '-save-settings-' . $current_user_id ) ) {

		$var_types = [
			'scan_post_types' => 'array',
			'scan_taxonomies' => 'array',
			'access_tool_roles' => 'array',
			'access_settings_roles' => 'array',
		];

		// This makes sure that empty arrays exists for these keys if they do not exist
		$default_values = [
			'scan_post_types' => [],
			'scan_taxonomies' => [],
			'access_tool_roles' => [],
			'access_settings_roles' => [],
		];

		$settings->overwrite( REQUEST::POST( $var_types, $default_values ) );
		$settings = $settings->save();

		// Update Cron Schedule
		Scan::deactivate_cron_status_check();
		Scan::schedule_cron();

	} else {

		Admin::add_notice( [
			'message' => __( 'Error: Expired session. Please try again.', WHEREUSED_SLUG ),
			'alert_level' => 'error',
		] );
	}

}

Admin::display_header();

echo Get::subheader();

?>

    <form method="post" action="<?php
	echo esc_attr( WHEREUSED_SETTINGS_URL ); ?>">

        <table class="settings-site">
            <tr>
                <td>
					<?php
					if ( $using_network_settings ) {
						// Notify the user that we are using network settings
						if ( current_user_can( 'manage_network' ) ) {
							echo '<p style="text-align: center;">' . wp_kses( sprintf( __( 'This site is using the <a href="%s">network settings</a>.', WHEREUSED_SLUG ), WHEREUSED_SETTINGS_NETWORK_URL ), [ 'a' => [ 'href' => [] ] ] ) . '</p>';
						} else {
							echo '<p style="text-align: center;">' . esc_html__( 'Settings are maintained at the network level. Contact your site administrator.', WHEREUSED_SLUG ) . '</p>';
						}
					}

					// Display Shared Settings
					include( 'shared-settings.php' );

					?>
                </td>
            </tr>
			<?php
			if ( ! $using_network_settings && empty( $scan_running ) ) { ?>
                <tr>
                    <td>
                        <input type="hidden" name="nonce" value="<?php
						echo esc_attr( wp_create_nonce( WHEREUSED_SLUG . '-save-settings-' . $current_user_id ) ) ?>"/>
						<?php
						submit_button( __( 'Save Settings', WHEREUSED_SLUG ) ); ?>
                    </td>
                </tr>
				<?php
			} ?>

        </table>
    </form>
<?php

Admin::display_footer();