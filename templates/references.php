<?php

namespace WhereUsed;

// Prevent Direct Access
( defined( 'ABSPATH' ) ) || die;

use WhereUsed\HelpersLibrary\Get;
use WhereUsed\HelpersLibrary\REQUEST;

$table = REQUEST::key( 'table' );

if ( 'media' == $table ) {

	Admin::display_header(__( 'Attachments Not Referenced', WHEREUSED_SLUG ));
	echo Get::subheader();

	if ( Scan::has_full_scan_ran() ) {
        // Full scan has ran
		echo '<p><b style="color:red">' . __( 'WARNING: Delete attachments at your own risk!' ) . '</b><br />' . __( 'We have not detected any references to the attachments below. These results are heavily dependent on the WhereUsed settings prior to a full scan. In addition, WhereUsed does NOT scan hardcoded references found in code; it only scans the database. The scan does not scan custom database tables created by other plugins or themes. Furthermore, the list below has no awareness of attachments referenced by other external websites. This table of attachments serve as a better insight to what has not been referenced on the site. We cannot 100% guarantee that the attachments in this table are not used.' ) . '</p>';

		include( WHEREUSED_TABLES_DIR . '/Unused_Attachments.php' );
		new Unused_Attachments();

	} else {
		// Scan is needed or the last scan is not finished
		echo '<p>' . __('Data not available until a full scan has ran.', WHEREUSED_SLUG) . '</p>';
    }
} else {

	Admin::display_header();
	echo Get::subheader();

	include( WHEREUSED_TABLES_DIR . '/All_Table.php' );
	new All_Table();
}
?>

    <input type="hidden" id="nonces" data-check-status="<?php
	echo wp_create_nonce( WHEREUSED_SLUG . '-check-status-' . get_current_user_id() ); ?>">
<?php

Admin::display_footer();
