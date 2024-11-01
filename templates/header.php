<?php

namespace WhereUsed;

/**
 * Header Template
 *
 * This is used by every page template. It is excluded during an AJAX request.
 *
 * Each tab of the admin area can be loaded directly. If you click on a tab,
 * an AJAX call will load the content of that tab in the main content area
 * reducing the need to reload the entire page.
 */

// Prevent Direct Access
( defined( 'ABSPATH' ) ) || die;

Admin::check_permissions();

$pages = [];

$tab = Admin::get_current_tab();

// Grab settings
if ( is_network_admin() ){
	$settings = Network_Settings::get_current_settings(true);
} else {
	$settings = Settings::get_current_settings(true);
}

if ( is_network_admin() ) {
	$admin_url = WHEREUSED_SETTINGS_NETWORK_URL;

	$pages['settings'] = __( 'Network Settings', WHEREUSED_SLUG );

} else {
	$admin_url = WHEREUSED_ADMIN_URL;

	$pages['dashboard'] = __( 'Dashboard', WHEREUSED_SLUG );
	$pages['references'] = __( 'References', WHEREUSED_SLUG );

	if ( $settings->can_user_access_settings() ) {
		$pages['settings'] = __( 'Settings', WHEREUSED_SLUG );
	}
}

if ( $settings->can_user_access_settings() ) {
	if ( $settings->get( 'debug' ) ) {
		$pages['debug'] = __( 'Debug', WHEREUSED_SLUG );
	}
}

$icons = [
	'dashboard' => 'dashboard',
	'references' => 'book-alt',
	'settings' => 'admin-settings',
    'debug' => 'admin-tools'
];
?>
<div class="privacy-settings-header">
    <div class="header-inner">
        <div class="privacy-settings-title-section">
            <img src="<?php
			echo esc_attr(WHEREUSED_ASSETS_URL . 'img/logo.svg'); ?>" alt="WhereUsed Logo" style="padding:21px;"/>
        </div>
        <nav class="privacy-settings-tabs-wrapper" aria-label="Secondary menu">
			<?php
			$num = 0;
			foreach ( $pages as $key => $label ) {
				$num ++;
				$active = ( ( 1 === $num && ! $tab ) || $tab === $key );
				$url = $admin_url . '&tab=' . $key;

				$classes = 'tab-' . $key . ' privacy-settings-tab';
				$classes .= ( $active ) ? ' active' : '';

				?>
            <a href="<?php
			echo esc_url( $url ) ?>" class="<?php
			echo esc_attr( $classes ) ?>" data-action="<?php echo esc_attr(WHEREUSED_HOOK_PREFIX . 'page'); ?>">
				<?php
				if ( isset( $icons[ $key ] ) ) {
					?><span class="dashicons dashicons-<?php
					echo esc_attr( $icons[ $key ] ) ?>"></span> <?php
				} ?>
				<?php
				echo esc_html( $label ) ?></a><?php
			}
			?>
        </nav>
		<?php
		if ( ! is_network_admin() ) {
			Notification::display_bell();
		}
		?>
    </div>

	<?php
	if ( current_user_can( 'manage_network' ) && ! is_network_admin() ){
		?>
        <div id="screen-meta-links" class="<?php echo ('settings' === $tab) ? '' : 'hidden'; ?>">
            <div id="screen-options-network-settings" class="">
                <a href="<?php echo esc_attr(WHEREUSED_SETTINGS_NETWORK_URL); ?>" id="show-network-settings-link" class="button show-settings"><?php echo __('Network Settings', WHEREUSED_SLUG); ?></a>
            </div>
        </div>
		<?php
	}
	?>
</div>
<?php