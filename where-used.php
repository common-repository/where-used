<?php

namespace WhereUsed;

define( 'WHEREUSED_NAME', 'WhereUsed' );
define( 'WHEREUSED_SLUG', 'where-used' );
define( 'WHEREUSED_VERSION', '1.4.0' );
define( 'WHEREUSED_MIN_PHP', '7.4.0' );
define( 'WHEREUSED_MIN_WP', '5.3.0' );

/**
 * @package   WhereUsed
 * @license   https://www.gnu.org/licenses/gpl-3.0.html GNU General Public License, version 3 or higher
 *
 * Plugin Name: WhereUsed
 * Plugin URI: https://wordpress.org/plugins/where-used/
 * Description: Where used? This plugin helps you find usage of attachments, posts, links, blocks and more in all post types, taxonomy terms, post meta, user meta, and menus. This plugin is multisite compatible!
 * Author: WhereUsed
 * Author URI: https://whereused.com/
 * Version: 1.4.0
 * Text Domain: where-used
 * License: GPL v3
 *
 * This software is provided "as is" and any express or implied warranties, including, but not limited to, the
 * implied warranties of merchantibility and fitness for a particular purpose are disclaimed. In no event shall
 * the copyright owner or contributors be liable for any direct, indirect, incidental, special, exemplary, or
 * consequential damages(including, but not limited to, procurement of substitute goods or services; loss of
 * use, data, or profits; or business interruption) however caused and on any theory of liability, whether in
 * contract, strict liability, or tort(including negligence or otherwise) arising in any way out of the use of
 * this software, even if advised of the possibility of such damage.
 *
 * For full license details see license.txt
 */

// Prevent Direct Access
( defined( 'ABSPATH' ) ) || die;

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {

	if ( check_compatibility() ) {
		require_once( __DIR__ . '/inc/Plugin.php' );

		// Initialize Plugin
		Plugin::init();

	} else {
		if ( defined( 'WHEREUSED_COMPATIBLE_ERROR' ) ) {

			if ( ( is_admin() || is_network_admin() ) && ! wp_doing_ajax() && ! wp_doing_cron() ) {
				// Display plugin compatibility error notice
				add_action( 'admin_notices', __NAMESPACE__ . '\\display_compatibility_notice' );
				add_action( 'network_admin_notices', __NAMESPACE__ . '\\display_compatibility_notice' );
			}
		}
	}
}


/**
 * Check to see if the current WordPress install is compatible with our plugin
 *
 * @package WhereUsed
 * @since   1.0.0
 *
 * @global string $wp_version The WordPress version string.
 * @return bool
 *
 * @note    DO NOT ADD TYPE HINTING: The function must be backwards compatible with old versions of PHP 5.6
 */
function check_compatibility() {

	global $wp_version;

	if ( ! defined( 'WHEREUSED_COMPATIBLE_ERROR' ) ) {
		if ( version_compare( PHP_VERSION, WHEREUSED_MIN_PHP, '<' ) ) {
			// PHP version is less than minimum required
			$message = sprintf( __( 'Error: %s - Plugin requires PHP version %s or higher. You are currently running %s.', WHEREUSED_SLUG ), WHEREUSED_NAME, WHEREUSED_MIN_PHP, PHP_VERSION );
			define( 'WHEREUSED_COMPATIBLE_ERROR', $message );
		} elseif ( version_compare( $wp_version, WHEREUSED_MIN_WP, '<' ) ) {
			// WP version is less than minimum required
			$message = sprintf( __( 'Error: %s - Plugin requires WordPress version %s or higher. You are currently running version %s.', WHEREUSED_SLUG ), WHEREUSED_NAME, WHEREUSED_MIN_WP, $wp_version );
			define( 'WHEREUSED_COMPATIBLE_ERROR', $message );
		} else {

			// System is compatible with this plugin up to this point

			if ( ! defined('WP_UNINSTALL_PLUGIN') || WP_UNINSTALL_PLUGIN == WHEREUSED_SLUG . '.php' ){

				$helpers_library_file = __DIR__ . '/library/_helpers-library/helpers-library.php';

				if ( file_exists( $helpers_library_file ) ) {
					// Load Helper's Library
					include_once( $helpers_library_file );
				} else {

					$helpers_library_source_file = __DIR__ . '/library/helpers-library/helpers-library.php';

					if ( file_exists( $helpers_library_source_file ) ) {
						$get_args = [
							'blocking' => true,
							'timeout' => 15,
							'redirection' => 0,
							'user-agent' => __NAMESPACE__ . ' - ' . get_bloginfo( 'url' ),
							'cookies' => $_COOKIE,
							'sslverify' => apply_filters( 'https_local_ssl_verify', false ),
							'body' => [ 'namespace' => __NAMESPACE__, 'plugin_dir' => __DIR__ ],
						];
						$response = wp_remote_post( plugin_dir_url( __FILE__ ) . '/library/helpers-library/install.php', $get_args );

						if ( file_exists( $helpers_library_file ) ) {
							// Load Helper's Library
							include_once( $helpers_library_file );
						} else {
							define( 'WHEREUSED_HELPERSLIBRARY_CONFLICT', WHEREUSED_NAME . ': Helpers Library not loaded (1)' );
						}
					} else {
						define( 'WHEREUSED_HELPERSLIBRARY_CONFLICT', WHEREUSED_NAME . ': Helpers Library missing. Please reinstall the ' . WHEREUSED_NAME . ' plugin.' );
					}
				}

			} else {
				define('WHEREUSED_HELPERSLIBRARY_CONFLICT', WHEREUSED_NAME . ': Helpers Library not loaded (2)');
			}

			if ( defined( 'WHEREUSED_HELPERSLIBRARY_CONFLICT' ) ) {
				// Another plugin already loaded this library and there's a conflicting version
				define( 'WHEREUSED_COMPATIBLE_ERROR', WHEREUSED_HELPERSLIBRARY_CONFLICT );
			}

		}
	}

	return ! defined( 'WHEREUSED_COMPATIBLE_ERROR' );

}

/**
 * Displays the plugin compatibility error in the admin and network admin areas
 *
 * @package WhereUsed
 * @since 1.2.2
 *
 * @return void
 */
function display_compatibility_notice() {

	echo '<div class="active notice notice-error plugin-' . esc_attr( WHEREUSED_SLUG ) . '"><p>' . esc_html( WHEREUSED_COMPATIBLE_ERROR ) . '</p></div>';

}