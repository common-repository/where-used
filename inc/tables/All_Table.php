<?php

namespace WhereUsed;

// Prevent Direct Access
( defined( 'ABSPATH' ) ) || die;

use WhereUsed\HelpersLibrary\REQUEST;

if ( ! class_exists( 'Table' ) ) {
	require_once( WHEREUSED_TABLES_DIR . '/Table.php' );
}

/**
 * Class All_Table
 *
 * @package WhereUsed
 * @since   1.0.0
 */
final class All_Table extends Table {

	/**
	 * All_Table constructor. Automatically prepares and displays table
	 *
	 * @package WhereUsed
	 * @since   1.0.0
	 *
	 * @uses    All_Table::prepare_items()
	 * @uses    \WP_List_Table::display()
	 */
	public function __construct() {

		// Set the DB table (must be run first)
		$this->table = Get::table_name();

		parent::__construct();

		// Correct Filters to prevent conflicts
		if ( REQUEST::text_field( 'type' ) == 'block' ) {
			// Reset filters
			$_REQUEST['redirection_location'] = '';
			$_REQUEST['redirection_type'] = '';
			$_REQUEST['status'] = '';

		} else {
			// We don't need block filter
			$_REQUEST['block'] = '';
		}

		$this->prepare_items();
		$this->display();

	}

	/**
	 * Sets the filters for this table
	 *
	 * @package WhereUsed
	 * @since   1.0.0
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

		// From
		$this->column_filters['from']['from_post_type'] = Filters::get_from_post_types();
		$this->column_filters['from']['from_where'] = Filters::get_from_where();

		// To
		$this->column_filters['to']['to_post_type'] = Filters::get_to_post_types();
		$this->column_filters['to']['type'] = Filters::get_to_types();
		$this->column_filters['to']['block'] = Filters::get_scanned_blocks();
		$this->column_filters['to']['status'] = Filters::get_statuses();

		// Redirection
		$this->column_filters['redirection']['redirection_type'] = Filters::get_redirection_types();

		if ( is_multisite() ) {
			// Locations

			$this->column_filters['from']['from_location'] = Filters::get_locations();
			$this->column_filters['to']['to_location'] = Filters::get_locations();

			if ( Get::using_redirection_plugin() ) {
				$this->column_filters['redirection']['redirection_location'] = Filters::get_locations();
			}
		}

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

		//$columns['id'] = __( 'ID', WHEREUSED_SLUG );
		$columns['from'] = __( 'From', WHEREUSED_SLUG );
		//$columns['from_how'] = __( 'From How', WHEREUSED_SLUG );
		$columns['to'] = __( 'To', WHEREUSED_SLUG );

		if ( REQUEST::text_field( 'type' ) != 'block' ) {
			$columns['redirection'] = __( 'Redirection', WHEREUSED_SLUG );
		}

		return $columns;
	}

	/**
	 * Get a list of sortable columns. The format is:
	 * 'internal-name' => 'orderby'
	 * or
	 * 'internal-name' => array( 'orderby', true )
	 *
	 * The second format will make the initial sorting order be descending
	 *
	 * @package WordPress
	 * @since   3.1.0
	 *
	 * @return array
	 */
	function get_sortable_columns(): array {

		return [
			'to_url' => [
				'to_url',
				true,
			],
		];

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

		$searchable = [
			'`to_url`',
			'`to_url_full`',
			'`to_url_absolute`',
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
	 * Gets the search query
	 *
	 * @package WhereUsed
	 * @since   1.0.0
	 *
	 * @return string
	 */
	function get_orderby(): string {

		return GET::orderby( self::get_sortable_columns(), 'ID' );

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

		// from_where Filter
		$where = Filters::get_from_where();
		$from_where = REQUEST::text_field( 'from_where' );
		$from_where = ( isset( $where[ $from_where ] ) ) ? $where[ $from_where ] : '';

		if ( $from_where ) {
			// Add filter if it exists
			$values[] = $from_where;
			$filters[] = "`from_where` = '%" . count( $values ) . "\$s'";
		}

		// from_post_type Filter
		$from_post_types = Filters::get_from_post_types();
		$from_post_type = REQUEST::text_field( 'from_post_type' );
		$from_post_type = $from_post_types[ $from_post_type ] ?? '';

		if ( $from_post_type ) {
			// Add filter if it exists
			$values[] = $from_post_type;
			$filters[] = "`from_post_type` = '%" . count( $values ) . "\$s'";
		}

		if ( Get::using_redirection_plugin() ) {

			if ( is_multisite() ) {
				$redirection_location = REQUEST::int( 'redirection_location' );
				if ( $redirection_location ) {
					$values[] = $redirection_location;
					$filters[] = "`redirection_site_id` = '%" . count( $values ) . "\$d'";
				}
			}

			// Allow us to link directly to results involving a specific redirection rule
			// No visual filter associated with this
			if ( $redirection_id = REQUEST::int( 'redirection' ) ) {
				$values[] = $redirection_id;
				$filters[] = "`redirection_id` == %d";
			}

		}

		if ( $redirection_type = REQUEST::int( 'redirection_type' ) ) {

			if ( 1 === $redirection_type ) {
				// All Redirects (Rules & 3XX Status)
				$filters[] = "(`redirection_id` > 0 || `to_url_status` LIKE '3%')";
			} elseif ( 2 === $redirection_type ) {
				// Redirection Rules
				$filters[] = "`redirection_id` > 0";
			} elseif ( 3 === $redirection_type ) {
				// 3XX Status
				$filters[] = "`to_url_status` LIKE '3%'";
			} elseif ( 4 === $redirection_type ) {
				// Incoming Redirection Rules
				$filters[] = "(`redirection_id` > 0 AND `from_where` LIKE 'redirection')";
			} elseif ( 5 === $redirection_type ) {
				// Outgoing Redirection Rules
				$filters[] = "(`redirection_id` > 0 AND `from_where` != 'redirection')";
			}

		}

		$from_post_id = REQUEST::int( 'from' );
		if ( $from_post_id ) {
			$values[] = $from_post_id;
			$filters[] = "`from_post_id` = '%" . count( $values ) . "\$d'";
		}

		if ( is_multisite() ) {
			$from_location = REQUEST::int( 'from_location' );
			if ( $from_location ) {
				$values[] = $from_location;
				$filters[] = "`from_site_id` = '%" . count( $values ) . "\$d'";
			}
		}

		$to_post_id = REQUEST::int( 'to' );
		if ( $to_post_id ) {
			$values[] = $to_post_id;
			$filters[] = "`to_post_id` = '%" . count( $values ) . "\$d'";
		}

		if ( is_multisite() ) {
			$to_location = REQUEST::int( 'to_location' );
			if ( $to_location ) {
				$values[] = $to_location;
				$filters[] = "`to_site_id` = '%" . count( $values ) . "\$d'";
			}
		}

		// to_post_type Filter
		$to_post_types = Filters::get_to_post_types();
		$to_post_type = REQUEST::text_field( 'to_post_type' );
		$to_post_type = $to_post_types[ $to_post_type ] ?? '';

		if ( $to_post_type ) {
			// Add filter if it exists
			$values[] = $to_post_type;
			$filters[] = "`to_post_type` = '%" . count( $values ) . "\$s'";
		}

		$to_url_status = REQUEST::key( 'status' );
		if ( $to_url_status || '0' == $to_url_status ) {

			if ( '0' == $to_url_status ) {
				$filters[] = "`to_url_status` = '0'";
			} else {
				$values[] = $to_url_status;
				//$filters[] = "`to_url_status` LIKE %s%";
				$filters[] = "`to_url_status` LIKE '%" . count( $values ) . "\$s%'";
			}

		}

		$to_type = REQUEST::text_field( 'type' );
		if ( $to_type ) {
			$values[] = $to_type;
			$filters[] = "`to_type` = '%" . count( $values ) . "\$s'";
		}

		$block = REQUEST::text_field( 'block' );
		if ( $block ) {
			$values[] = $block;
			$filters[] = "`to_block_name` = '%" . count( $values ) . "\$s'";
		}

		return ( empty( $filters ) ) ? '' : implode( ' AND ', $filters );

	}

}