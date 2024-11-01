<?php

namespace WhereUsed;

// Prevent Direct Access
( defined( 'ABSPATH' ) ) || die;

use WhereUsed\HelpersLibrary\REQUEST;

if ( ! class_exists( 'Table' ) ) {
	require_once( WHEREUSED_TABLES_DIR . '/Table.php' );
}

/**
 * Class Unused_Attachments
 *
 * @package WhereUsed
 * @since   1.0.0
 */
final class Unused_Attachments extends Table {

	/**
	 * Unused_Attachments constructor. Automatically prepares and displays table
	 *
	 * @uses All_Table::prepare_items()
	 * @uses \WP_List_Table::display()
	 */
	public function __construct() {

		parent::__construct();

		$this->prepare_items();
		$this->display();

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
		$columns['post_title'] = __( 'File', WHEREUSED_SLUG );
		$columns['file_type'] = __( 'File type', WHEREUSED_SLUG );
		$columns['author'] = __( 'Author', WHEREUSED_SLUG );
		$columns['uploaded_to'] = __( 'Uploaded To', WHEREUSED_SLUG );
		$columns['date'] = __( 'Date', WHEREUSED_SLUG );

		return $columns;
	}

	/**
	 * Sets the filters for this table
	 *
	 * @param array $override
	 *
	 * @return void
	 */
	public function set_filters( array $override = [] ): void {

		if ( ! empty( $override ) ) {
			// Override filters
			$this->filters = $override;

			return;
		}

		// Set filters

		$this->filters['file-type'] = Filters::get_mime_types();

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

		return [
			'`post_title`',
		];

		if ( $stripped ) {
			foreach ( $searchable as $index => $column ) {
				if ( strpos( $column, '.' ) !== false ) {
					// Remove specific table designation
					$column = substr( $column, strpos( $column, "." ) + 1 );
				}
				$searchable[ $index ] = str_replace( '`', '', $column );
			}
		}

		return $searchable;

	}

	/**
	 * Gets the query for the additional filters
	 *
	 * @package WhereUsed
	 * @since   1.0.0
	 *
	 * @param array $values
	 *
	 * @return string
	 */
	public function get_additional_filters_query( array &$values ): string {

		$filters = [];

		$to_type = REQUEST::text_field( 'file-type' );
		if ( $to_type ) {
			$values[] = $to_type;
			$filters[] = "`post_mime_type` = '%" . count( $values ) . "\$s'";
		}

		return ( empty( $filters ) ) ? '' : implode( ' AND ', $filters );

	}

	/**
	 * Add filters and per_page
	 *
	 * @param string $which
	 *
	 * @return void
	 */
	public function bulk_actions( $which = '' ): void {

		$this->bulk_actions_load( $which );

	}

	/**
	 * Prepares the list of items for displaying.
	 *
	 * @package  WordPress
	 * @since    3.1.0
	 * @abstract
	 * @uses     WP_List_Table::set_pagination_args()
	 *
	 * @global $wpdb
	 */
	function prepare_items(): void {

		global $wpdb;

		$values = [];
		$values[] = $wpdb->prefix . 'posts';
		$values[] = Get::table_name();

		$where_queries = [ ' `post_type` = \'attachment\' AND `ID` NOT IN ( SELECT `to_post_id` FROM `%2$s` WHERE `to_post_id` > 0 GROUP BY `to_post_id` )' ];
		$where_query = $this->get_where_query( $values, $where_queries );

		$total_items = $wpdb->get_col( $wpdb->prepare( 'SELECT count(`ID`) FROM `%1$s` ' . $where_query, $values ) );
		$total_items = $total_items[0] ?? 0;

		$values_count = count( $values );
		$page = $this->get_pagenum();
		$per_page = $this->get_items_per_page();
		$values[] = ( $page - 1 ) * $per_page; // Limit Start
		$values[] = $per_page;

		$this->items = $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM `%1$s` ' . $where_query . ' LIMIT %' . ++ $values_count . '$d, %' . ++ $values_count . '$d', $values ) );

		$this->set_pagination_args( [
			'total_items' => $total_items,
			'per_page' => $per_page,
		] );

	}

	public function column_post_title( $item ) {

		ob_start();

		[ $mime ] = explode( '/', $item->post_mime_type );

		$title = ( $item->post_title ) ? $item->post_title : _draft_or_post_title();
		$thumb = wp_get_attachment_image( $item->ID, [ 60, 60, ], true, [ 'alt' => '' ] );

		$class = $thumb ? ' class="has-media-icon"' : '';
		?>
    <strong class="<?php echo esc_attr( $class ); ?>">
		<?php
		if ( current_user_can( 'edit_post', $item->ID ) ) {
			echo sprintf( '<a href="%s" aria-label="%s">', get_edit_post_link( $item->ID ), /* translators: %s: Attachment title. */ esc_attr( sprintf( __( '&#8220;%s&#8221; (Edit)' ), $title ) ) );
		}

		if ( $thumb ) {
			?>
            <span class="media-icon <?php echo esc_attr( sanitize_html_class( $mime . '-icon' ) ); ?>"><?php
				echo wp_get_attachment_image( $item->ID, [ 60, 60, ], true, [ 'alt' => '' ] ); ?></span>
		<?php
		}

		$this->highlight_search( 'post_title', $title, true );

		if ( current_user_can( 'edit_post', $item->ID ) ) {
			echo '</a>';
		}

		_media_states( $item );
		?>
        </strong><?php

		return ob_get_clean();

	}

	public function column_file_type( $item ) {
		return esc_html( $item->post_mime_type );
	}

	public function column_uploaded_to( $item ): string {

		$content = '';
		if ( $item->post_parent ) {
			$content = '<a href="' . get_edit_post_link( $item->post_parent ) . '">' . esc_html( get_the_title( $item->post_parent ) ) . '</a>';
		}

		return $content;
	}

	public function column_author( $item ): string {

		if ( $item->post_author ) {
			$content = get_the_author_meta( 'display_name', $item->post_author );
		}

		return esc_html( $content );
	}

	/**
	 * Displays the date column
	 *
	 * @package WhereUsed
	 * @since   1.0.0
	 *
	 * @param $item
	 *
	 * @return string
	 */
	public function column_date( $item ): string {

		return esc_html( date( 'D M d, Y g:i:s A', strtotime( $item->post_date ) ) );

	}

}