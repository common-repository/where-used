<?php

namespace WhereUsed;

// Prevent Direct Access
( defined( 'ABSPATH' ) ) || die;

if ( ! class_exists( 'Table' ) ) {
	require_once( WHEREUSED_TABLES_DIR . '/Table.php' );
}

/**
 * Class Metabox_References_Table
 *
 * @package WhereUsed
 * @since   1.0.0
 */
final class Metabox_References_Table extends Table {

	private string $type = 'to'; // from or to

	/**
	 * Metabox_References_Table constructor. Automatically prepares and displays table
	 */
	public function __construct( $type = 'to' ) {

		// Set the type so we can show different kinds of metabox tables
		$this->type = $type;

		parent::__construct();

		$this->prepare_items();
		$this->display();

	}

	/**
	 * Sets the filters for this table
	 *
	 * @param array $override
	 *
	 * @return void
	 */
	public function set_filters( array $override = [] ): void {

		$this->filters = [];

	}

	/**
	 * Get a list of columns. The format is:
	 * 'internal-name' => 'Title'
	 *
	 * @package WordPress
	 * @since   3.1.0
	 * @abstract
	 *
	 * @return array
	 */
	function get_columns(): array {

		$columns = [];
		$columns['from'] = __( 'From', WHEREUSED_SLUG );
		$columns['to'] = __( 'To', WHEREUSED_SLUG );
		$columns['redirection'] = __( 'Redirection', WHEREUSED_SLUG );

		return $columns;
	}

	/**
	 * Get the array of searchable columns in the database
	 *
	 * @package WhereUsed
	 * @since   1.0.0
	 *
	 * @param bool $stripped Removes table specific designation and table ticks
	 *
	 * @return  array An unassociated array.
	 */
	protected static function get_searchable_columns( bool $stripped = true ): array {

		return [];

	}

	/**
	 * Load directly from the API instead of from the database.
	 *
	 * @package WordPress
	 */
	function prepare_items(): void {

		global $wpdb, $post;

		// Array of all the values to be sanitized
		$values = [];
		$values[] = Get::table_name();
		$values[] = $post->ID;
		$values[] = WHEREUSED_CURRENT_SITE_ID;

		$per_page = 3; // Limit per page
		$this->items = []; // Default

		if ( 'to' == $this->type ) {
			$total_items = $wpdb->get_col( $wpdb->prepare( 'SELECT count(`id`) FROM `%1$s` WHERE `to_post_id` = %2$d AND `to_site_id` = %3$d AND `from_where` != \'redirection\'', $values ) );
			$total_items = $total_items[0] ?? 0;

			if ( $total_items ) {
				$values[] = $per_page;
				$this->items = Reference::get_results( $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM `%1$s` WHERE `to_post_id` = %2$d AND `to_site_id` = %3$d AND `from_where` != \'redirection\' ORDER BY `ID` DESC LIMIT 0, %4$d', $values ) ) );
			}
		} elseif ( 'from' == $this->type ) {
			$total_items = $wpdb->get_col( $wpdb->prepare( 'SELECT count(`id`) FROM `%1$s` WHERE `from_post_id` = %2$d AND `from_site_id` = %3$d AND `from_where` != \'redirection\'', $values ) );
			$total_items = $total_items[0] ?? 0;

			if ( $total_items ) {
				$values[] = $per_page;
				$this->items = Reference::get_results( $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM `%1$s` WHERE `from_post_id` = %2$d AND `from_site_id` = %3$d AND `from_where` != \'redirection\' ORDER BY `ID` DESC LIMIT 0, %4$d', $values ) ) );
			}
		} elseif ( 'redirection' == $this->type ) {
			$total_items = $wpdb->get_col( $wpdb->prepare( 'SELECT count(`id`) FROM `%1$s` WHERE `to_post_id` = %2$d AND `to_site_id` = %3$d AND `redirection_id` > 0', $values ) );
			$total_items = $total_items[0] ?? 0;

			if ( $total_items ) {
				$values[] = $per_page;
				$this->items = Reference::get_results( $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM `%1$s` WHERE `to_post_id` = %2$d AND `to_site_id` = %3$d AND `redirection_id` > 0 ORDER BY `ID` DESC LIMIT 0, %4$d', $values ) ) );
			}
		}

		$this->set_pagination_args( [
			'total_items' => $total_items,
			'per_page' => $per_page,
		] );

	}

	/**
	 * Displays the table.
	 *
	 * @package WordPress
	 * @since   3.1.0
	 */
	public function display() {

		global $post;

		$singular = $this->_args['singular'];

		$total_items = $this->get_pagination_arg( 'total_items' );

		ob_start();
		?><?php
		if ( $total_items ) {

			if ( 'redirection' == $this->type ) {
				echo '<p style="text-align:center;font-size: 14px;">' . sprintf( __( 'Displaying %d of %d redirections.', WHEREUSED_SLUG ), count( $this->items ), $total_items ) . '</p>';
			} else {
				echo '<p style="text-align:center;font-size: 14px;">' . sprintf( __( 'Displaying %d of %d references.', WHEREUSED_SLUG ), count( $this->items ), $total_items ) . '</p>';
			}
			if ( $total_items > $this->get_pagination_arg( 'per_page' ) ) {
				if ( 'to' == $this->type ) {
					$view_all_url = 'tools.php?page=where-used&tab=references&to=' . $post->ID . '&to_location=' . WHEREUSED_CURRENT_SITE_ID;
					$view_all_text = sprintf( __( 'View All References To This %s', WHEREUSED_SLUG ), ucwords( $post->post_type ) );
				} elseif ( 'from' == $this->type ) {
					$view_all_url = 'tools.php?page=where-used&tab=references&from=' . $post->ID . '&from_location=' . WHEREUSED_CURRENT_SITE_ID;
					$view_all_text = sprintf( __( 'View All References From This %s', WHEREUSED_SLUG ), ucwords( $post->post_type ) );
				} elseif ( 'redirection' == $this->type ) {
					$view_all_url = 'tools.php?page=where-used&tab=references&to=' . $post->ID . '&to_location=' . WHEREUSED_CURRENT_SITE_ID . '&redirection_type=2';
					$view_all_text = __( 'View All Redirects', WHEREUSED_SLUG );
				}
				?>
                <p style="text-align:center;font-size: 14px;"><a href="<?php
				echo esc_attr( admin_url( $view_all_url ) ); ?>"><?php
					echo esc_html( $view_all_text );
					?> &raquo;</a></p><?php
			} ?></p><?php

		} else {
			echo '<p style="text-align:center;font-size: 14px;">';
			$this->no_items();
			echo '</p>';
		}

		echo ob_get_clean();

		if ( $total_items ) {
			$this->screen->render_screen_reader_content( 'heading_list' );
			?>
            <table class="wp-list-table <?php
			echo implode( ' ', $this->get_table_classes() ); ?>">
                <thead>
                <tr>
					<?php
					$this->print_column_headers(); ?>
                </tr>
                </thead>

                <tbody id="the-list"
					<?php
					if ( $singular ) {
						echo " data-wp-lists='list:$singular'";
					}
					?>
                >
				<?php
				$this->display_rows_or_placeholder(); ?>
                </tbody>

            </table>
			<?php

		}
	}

	/**
	 * Added to overwrite the protected class in WP_List_Table() to remove nonce and the unused aspects
	 *
	 * @package WordPress
	 *
	 * @param string $which
	 */
	protected function display_tablenav( $which ) {

		?>
        <div class="tablenav <?php
		echo esc_attr( $which ); ?>">
			<?php
			$this->pagination( $which );
			?>
            <br class="clear"/>
        </div>
		<?php
	}

	/**
	 * Message to be displayed when there are no items
	 *
	 * @package WordPress
	 * @since   3.1.0
	 *
	 * @return void
	 */
	public function no_items(): void {

		if ( 'redirection' == $this->type ) {
			_e( 'No redirections found.', WHEREUSED_SLUG );
		} else {
			_e( 'No references found.', WHEREUSED_SLUG );
		}

	}

}