<?php

namespace WhereUsed;

/**
 * Debug Page
 *
 * Provides tools to debug issues in WhereUsed
 *
 * @package WhereUsed
 * @since   1.1.0
 */

// Prevent Direct Access
( defined( 'ABSPATH' ) ) || die;

if ( is_network_admin() ) {
	// Load Network Debug Page Instead
	include( WHEREUSED_TEMPLATES_DIR . 'network-debug.php' );

	return;
}

Admin::check_permissions();

// Grab settings
$settings = Settings::get_current_settings( true );

if ( ! $settings->can_user_access_settings() ) {
	error_log( $error = __( 'You do not have permission to access settings.', WHEREUSED_SLUG ) );
	die( $error );
}

Admin::display_header();

echo Get::subheader();
?>

    <nav class="nav-tab-wrapper">
        <a href="#settings" class="nav-tab nav-tab-active nav-tab-settings">Settings</a>
        <a href="#scans" class="nav-tab nav-tab-scans">Scans</a>
        <a href="#constants" class="nav-tab nav-tab-constants">Constants</a>
        <a href="#logs" class="nav-tab nav-tab-logs">Logs</a>
    </nav>
    <div class="all-tab-content">
        <section id="settings" class="tab-content active">
            <h2><?php
				_e( 'Settings', WHEREUSED_SLUG ); ?></h2>

			<?php
			Debug::display_table( $settings, 'settings' ); ?>
        </section>
        <section id="scans" class="tab-content">
			<?php
			$scan = Scan::get_current( true );
			?>
            <h2><?php
				_e( 'Latest/Current Scan Details', WHEREUSED_SLUG ); ?></h2>
			<?php
			Debug::display_table( $scan, 'scan', [ 'history' ] );
			?>

            <br><br>
            <h2><?php
				_e( 'Scan History Details', WHEREUSED_SLUG ); ?></h2>

            <div style="width:100%; height: 500px; overflow: auto;">
				<?php

				$exclude = [
					'needed',
					'history',
					'started',
				];

				$history = $scan->get( 'history' );
				foreach ( $history as $h ) {

					$history_scan = new Scan( $h );
					Debug::display_table( $history_scan, 'scan', $exclude );
				}
				?>
            </div>
        </section>
        <section id="constants" class="tab-content">
            <h2><?php
				_e( 'Constant Variables', WHEREUSED_SLUG ); ?></h2>
            <p><?php echo sprintf( __('Not all constants are listed below. <a href="%s" target="_blank">View full list of constants</a> in the documentation.', WHEREUSED_SLUG), 'https://whereused.com/docs/constants/' ); ?></p>

            <?php
			Debug::display_constants_table();
			?>
        </section>
        <section id="logs" class="tab-content">
            <h2><?php
				_e( 'Debug Log', WHEREUSED_SLUG ); ?></h2>

            <textarea id="debug-log" style="width:100%; height:500px; background: black; color: white;">
                <?php
                // Grab existing content
                $data = ( file_exists( Debug::get_debug_log_file() ) ) ? file_get_contents( Debug::get_debug_log_file() ) : 'Debug log not found';

                echo $data;
                ?>
            </textarea>

            <a href="<?php
			echo esc_url( WHEREUSED_ADMIN_URL . '&tab=debug&reset=1' ); ?>" class="button button-secondary">Clear
                Log</a>
        </section>
    </div>

	<?php
Admin::display_footer();
