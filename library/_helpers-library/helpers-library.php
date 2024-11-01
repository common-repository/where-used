<?php

namespace WhereUsed\HelpersLibrary;

// Prevent Direct Access
( defined( 'ABSPATH' ) ) || die;

$this_version = '1.5.0';
$min_php_version = '7.4.0';
/**
 * Helpers Library
 *
 * @package WhereUsed\HelpersLibrary
 * @license http://www.gnu.org/licenses/gpl-3.0.html GNU General Public License, version 3 or higher
 *
 * Plugin Name: Helpers Library
 * Plugin URI: https://gitlab.com/sovdeveloping/helpers-library
 * Version: 1.5.0
 * Description: This library is an add-on for plugins so the visual WordPress admin interface can be easily  implemented for a plugin.
 * Author: Steven Ayers
 * Author URI: https://profiles.wordpress.org/stevenayers63/
 * Text Domain: helpers-library
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

// Define which plugin is loading this library
$this_plugin = basename( dirname( __DIR__, 2 ) );

if ( ! defined( 'WHEREUSED_HELPERSLIBRARY_VERSION' ) ) {

	// Set current site
	$current_site_id = ( is_multisite() ) ? get_current_blog_id() : 1;
	define( 'WHEREUSED_HELPERSLIBRARY_CURRENT_SITE_ID', $current_site_id );

	// Load the plugin
	define( 'WHEREUSED_HELPERSLIBRARY_VERSION', $this_version );
	define( 'WHEREUSED_HELPERSLIBRARY_MIN_PHP', $min_php_version );
	define( 'WHEREUSED_HELPERSLIBRARY_SLUG', 'helpers-library' );
	define( 'WHEREUSED_HELPERSLIBRARY_PLUGIN_LOADED', $this_plugin );
	define( 'WHEREUSED_HELPERSLIBRARY_DIR', __DIR__ );
	define( 'WHEREUSED_HELPERSLIBRARY_INC_DIR', WHEREUSED_HELPERSLIBRARY_DIR . '/inc' );
	define( 'WHEREUSED_HELPERSLIBRARY_LIBRARY_DIR', WHEREUSED_HELPERSLIBRARY_DIR . '/library' );
	define( 'WHEREUSED_HELPERSLIBRARY_TABLES_DIR', WHEREUSED_HELPERSLIBRARY_INC_DIR . '/tables' );

	define( 'WHEREUSED_HELPERSLIBRARY_ASSETS_DIR', WHEREUSED_HELPERSLIBRARY_DIR . '/assets' );
	define( 'WHEREUSED_HELPERSLIBRARY_URL', plugin_dir_url( __FILE__ ) );
	define( 'WHEREUSED_HELPERSLIBRARY_ASSETS_URL', WHEREUSED_HELPERSLIBRARY_URL . 'assets' );

	// Must be loaded before defining admin URLs
	require( WHEREUSED_HELPERSLIBRARY_INC_DIR . '/REQUEST.php' );

	define( 'WHEREUSED_HELPERSLIBRARY_ADMIN_FOLDER', str_replace( str_replace( ['https://', 'http://'], '', site_url() ) . '/', '', str_replace( ['https://', 'http://'], '', admin_url() ) ) ); // e.g. wp-admin/
	define( 'WHEREUSED_HELPERSLIBRARY_ADMIN_DIR', substr_replace( ABSPATH . WHEREUSED_HELPERSLIBRARY_ADMIN_FOLDER, "", - 1 ) );  // e.g. /var/www/wp-admin

	// Admin URLs
	define( 'WHEREUSED_HELPERSLIBRARY_ADMIN_URI', REQUEST::key( 'page', '', '', 'tools.php?page=' ) );
	define( 'WHEREUSED_HELPERSLIBRARY_ADMIN_URL', admin_url( WHEREUSED_HELPERSLIBRARY_ADMIN_URI ) );

	// Setup the current admin page
	define( 'WHEREUSED_HELPERSLIBRARY_ADMIN_URI_CURRENT', WHEREUSED_HELPERSLIBRARY_ADMIN_URI . REQUEST::key( 'tab', '', '', '&tab=' ) );
	define( 'WHEREUSED_HELPERSLIBRARY_ADMIN_URL_CURRENT', admin_url( WHEREUSED_HELPERSLIBRARY_ADMIN_URI_CURRENT ) );

	// DB Connection
	require( WHEREUSED_HELPERSLIBRARY_INC_DIR . '/Constants.php' );
	require( WHEREUSED_HELPERSLIBRARY_INC_DIR . '/Plugin.php' );
	require( WHEREUSED_HELPERSLIBRARY_INC_DIR . '/Base.php' );
	require( WHEREUSED_HELPERSLIBRARY_INC_DIR . '/Debug.php' );
	require( WHEREUSED_HELPERSLIBRARY_INC_DIR . '/Get.php' );
	require( WHEREUSED_HELPERSLIBRARY_INC_DIR . '/Run.php' );
	require( WHEREUSED_HELPERSLIBRARY_INC_DIR . '/Migration.php' );
	require( WHEREUSED_HELPERSLIBRARY_INC_DIR . '/Notification.php' );
	require( WHEREUSED_HELPERSLIBRARY_INC_DIR . '/Menu.php' );
	require( WHEREUSED_HELPERSLIBRARY_INC_DIR . '/Scan.php' );

	// Load needed WP functions
	include_once( WHEREUSED_HELPERSLIBRARY_ADMIN_DIR . '/includes/plugin.php' );

	if ( is_admin() || is_network_admin() ) {
		require( WHEREUSED_HELPERSLIBRARY_INC_DIR . '/Admin.php' );
		require( WHEREUSED_HELPERSLIBRARY_TABLES_DIR . '/Table.php' );
		require( WHEREUSED_HELPERSLIBRARY_INC_DIR . '/Settings_Display.php' );
	}

} else {
	// The library is already loaded

	// Check to see if it's the same version already loaded
	if ( WHEREUSED_HELPERSLIBRARY_VERSION !== $this_version ) {

		// We have a conflict in version. Set constant so that the main plugin can use this as a flag to prevent the plugin from loading.
		define( 'WHEREUSED_HELPERSLIBRARY_CONFLICT', sprintf( __( 'There is a dependency conflict between plugins %s and %s, so we are not loading %s. Please make sure both plugins are updated and using the latest versions available. Also, notify both plugin authors to install the Helpers Library as a sub namespace of their plugin per the install instructions in readme.txt to avoid conflicts like this in the future.', $this_plugin ), $this_plugin, WHEREUSED_HELPERSLIBRARY_PLUGIN_LOADED, $this_plugin ) );

	}

}