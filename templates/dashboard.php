<?php

namespace WhereUsed;

// Prevent Direct Access
( defined( 'ABSPATH' ) ) || die;

use WhereUsed\HelpersLibrary\REQUEST;

include( WHEREUSED_INC_DIR . '/Dashboard.php' );
require_once( WHEREUSED_HELPERSLIBRARY_ADMIN_DIR . '/includes/dashboard.php' );

// This allows you to reset the dashboard widgets order to default.
// @todo add screen options so that the user has the ability to reset the order of widgets for the dashboard
if ( REQUEST::bool( 'reset-widgets' ) ) {
	$uid = get_current_user_id();
	$screen = get_current_screen();

	delete_user_meta( $uid, 'meta-box-order_' . $screen->base );
	delete_user_meta( $uid, 'closedpostboxes_' . $screen->base );
	delete_user_meta( $uid, 'metaboxhidden_' . $screen->base );
}

// Get Widgets
Dashboard::set_widgets();

Admin::display_header();

echo Get::subheader();
?>
<div id="dashboard-widgets-wrap">

	<?php
	// Display Widgets
	wp_dashboard();
	?>

    <div class="clear"></div>
</div><!-- dashboard-widgets-wrap -->

<script src="<?php
echo esc_url( WHEREUSED_LIBRARY_JS_URL ); ?>/chart.min.js"></script>
<?php
Admin::display_footer();
