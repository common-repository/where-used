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

Admin::check_permissions();

// Grab settings
$settings = Network_Settings::get_current_settings( true );

if ( ! $settings->can_user_access_settings() ) {
	$error = __( 'You do not have permission to access settings.', WHEREUSED_SLUG );
	die( $error );
}

if ( ! $settings->get('debug') ) {
	$error = __( 'Please enable debug in settings to access this page.', WHEREUSED_SLUG );
	die( $error );
}

Admin::display_header();

?>

    <nav class="nav-tab-wrapper">
        <a href="#settings" class="nav-tab nav-tab-active nav-tab-settings">Settings</a>
        <a href="#constants" class="nav-tab nav-tab-constants">Constants</a>
    </nav>
    <div class="all-tab-content">
        <section id="settings" class="tab-content active">
            <h2><?php
                _e( 'Network Settings', WHEREUSED_SLUG ); ?></h2>

            <?php
            Debug::display_table( $settings, 'settings' );
            ?>
        </section>
        <section id="constants" class="tab-content">
            <h2><?php
                _e( 'Constant Variables', WHEREUSED_SLUG ); ?></h2>

            <?php
            Debug::display_constants_table(); ?>
        </section>
    </div>
<?php
Admin::display_footer();