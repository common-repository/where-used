<?php

namespace WhereUsed;

// Prevent Direct Access
( defined( 'ABSPATH' ) ) || die;

use WhereUsed\HelpersLibrary\REQUEST;
use WhereUsed\HelpersLibrary\Table as Table_Base;

// Extend the Helper's Library Table Class
if ( ! class_exists( 'Table' ) ) {
	require_once( WHEREUSED_HELPERSLIBRARY_TABLES_DIR . '/Table.php' );
}

/**
 * Class Table - Extends the Helper's Library Table Class
 * Notice: Must set the get_icons()
 * Notice: Must create the get_results()
 * Notice: This is where you add all the custom column methods
 */
abstract class Table extends Table_Base {

	/**
	 * Set the plugin's icons
	 *
	 * @param string $type
	 *
	 * @return string[]
	 */
	protected static function get_icons( string $type = 'column' ): array {

		$icons = [
			'from_location' => 'admin-multisite',
			'to_location' => 'admin-multisite',
			'redirection_type' => 'redo flip-v',
			'redirection_location' => 'admin-multisite',
			'from_where' => 'location',
			'from_post_type' => 'admin-post',
			'to_post_type' => 'admin-post',
			'type' => 'tag',
			'status' => 'cloud',
			'block' => 'block-default',
		];

		if ( 'filter' == $type ) {
			// Get filter icons instead
			$icons = Filters::get_icons();
		}

		return $icons;

	}

	/**
	 * Converts the results into the proper format for the plugin.
	 * Must be overwritten so that we can allow the plugin to have it's own format for each row (Row class)
	 */
	protected static function get_results( array $results ): array {

		return Reference::get_results( $results );

	}

	/**
	 * Displays the redirection column
	 *
	 * @package WhereUsed
	 * @since   1.0.0
	 *
	 * @param \WhereUsed\Row $row
	 *
	 * @return string
	 */
	protected function column_redirection( Row $row ): string {

		$content = '';
		$content_rows = [];

		if ( $rule = $row->get_redirection_rule() ) {
			$content_rows[] = '<li class="redirection-rule"><span class="dashicons dashicons-redo flip-v"></span><span class="dashicons dashicons-edit" title="' . esc_attr( __( 'Edit Redirection Rule', WHEREUSED_SLUG ) ) . '"></span><a href="' . esc_url( admin_url( 'tools.php?page=redirection.php&filterby%5Bid%5D=' . $row->get( 'redirection_id' ) ) ) . '"  target="_blank" rel="noreferer noopener">' . esc_html( $rule ) . '</a></li>';
		}

		if ( $row->get( 'from_where' ) == 'redirection' ) {
			// Incoming redirection
			$direction = 'dashicons-arrow-left-alt';
			$redirection_to = $row->get( 'to_url_full' );
			$help_text = sprintf( __( 'Traffic is being redirected to %s', WHEREUSED_SLUG ), $redirection_to );
		} else {
			// Outgoing redirection
			// Value Given to us by http-api request
			$direction = 'dashicons-arrow-right-alt';
			$redirection_to = $row->get( 'redirection_url' );
			$help_text = sprintf( __( '%s is redirected to %s', WHEREUSED_SLUG ), $row->get( 'to_url_full' ), $redirection_to );
		}

		if ( $redirection_to ) {
			$content_rows[] = '<li class="redirection-to"><span class="direction dashicons ' . esc_attr( $direction ) . '" title="' . esc_attr( $help_text ) . '"></span><a href="' . esc_url( $redirection_to ) . '"  target="_blank" rel="noreferer noopener">' . esc_html( $redirection_to ) . '</a></li>';
		}

		if ( is_multisite() ) {
			$location = $row->get_site_domain( 'redirection' );

			if ( $location ) {
				$content_rows[] = '<li class="redirection-location"><span class="dashicons dashicons-admin-multisite
" title="' . esc_attr( __( 'Site', WHEREUSED_SLUG ) ) . '"></span> <i>' . esc_html( $location ) . '</i></li>';
			}
		}

		if ( ! empty( $content_rows ) ) {
			$content .= '<ul>' . implode( '', $content_rows ) . '</ul>';
		}

		return $content;

	}

	/**
	 * Displays the usage title column
	 *
	 * @package WhereUsed
	 * @since   1.0.0
	 *
	 * @param \WhereUsed\Row $row
	 *
	 * @return string
	 */
	protected function column_usage_title( Row $row ): string {

		if ( isset( $row->usage_title ) ) {
			if ( $row->usage_title ) {
				return ( isset( $row->usage_url ) && $row->usage_url ) ? Get::icon( 'warning' ) . '<a href="' . esc_url( $row->usage_url ) . '" target="_blank" rel="noreferer noopener">' . esc_html( $row->usage_title ) . '</a>' : Get::icon( 'warning' ) . esc_html( $row->usage_title );
			} else {
				return Get::icon( 'good' );
			}
		} else {
			return Get::icon( 'good' );
		}

	}

	/**
	 * Displays the Reference From column
	 *
	 * @package WordPress
	 * @since   1.0.0
	 *
	 * @param \WhereUsed\Reference $reference
	 *
	 * @return string
	 */
	protected function column_from( Reference $reference ): string {

		return $this->get_display_link( $reference, 'from' );

	}

	/**
	 * Displays the Reference To column
	 *
	 * @package WhereUsed
	 * @since   1.0.0
	 *
	 * @param \WhereUsed\Reference $reference
	 *
	 * @return string
	 */
	protected function column_to( Reference $reference ): string {

		return $this->get_display_link( $reference, 'to' );

	}

	/**
	 * Displays the link of the reference property type. Will also allow row actions.
	 *
	 * @package WhereUsed
	 * @since   1.0.0
	 *
	 * @param string               $type
	 * @param string               $destination
	 * @param bool                 $row_actions
	 *
	 * @param \WhereUsed\Reference $row
	 *
	 * @return string
	 *
	 */
	protected function get_display_link( Reference $row, string $type, string $destination = 'edit', bool $row_actions = true ): string {

		$content = '<div class="td-content">';
		$content_rows = [];

		$post_id = $row->get( $type . '_post_id' );
		$site_id = $row->get( $type . '_site_id' );

		// Post Type Row
		$post_type = $row->get( $type . '_post_type' );
		if ( $post_type ) {
			$content_rows[15] = '<li class="' . esc_attr( $type ) . '_post_type clear"><span class="dashicons dashicons-admin-post" title="' . esc_attr( __( 'Post Type', WHEREUSED_SLUG ) ) . '"></span> ' . esc_html( $post_type ) . '</li>';
		}

		if ( 'to' == $type ) {
			// Display To URL Status

			$status = $row->get( 'to_url_status' );

			if ( - 1 !== $status ) {

				$status_class = 'code-' . substr( $status, 0, 1 ) . 'xx';
				$page = ( $status ) ?: 'no-response';
				$content .= '<a href="https://wheregoes.com/http-status-codes/' . esc_attr( $page ) . '/" class="status ' . esc_attr( $status_class ) . '" title="' . sprintf( __( 'Click to learn more about %d status.', WHEREUSED_SLUG ), $status ) . '" target="_blank" rel="noreferer noopener"><span class="dashicons dashicons-cloud"></span><span class="code">' . esc_html( $status ) . '</span></a>';
			}

			$to_type = $row->get( 'to_type' );

			if ( $to_type ) {
				$content_rows[20] = '<li class="to_type clear"><span class="dashicons dashicons-tag" title="' . esc_attr( __( 'Type', WHEREUSED_SLUG ) ) . '"></span> ' . esc_html( $to_type ) . '</li>';
			}

			$to_block_name = $row->get( 'to_block_name' );

			if ( $to_block_name ) {
				$content_rows[80] = '<li class="to_block_name clear"><span class="dashicons dashicons-block-default" title="' . esc_attr( __( 'Block Name', WHEREUSED_SLUG ) ) . '"></span> ' . esc_html( $to_block_name ) . '</li>';
			}

			$to_anchor_text = $row->get( 'to_anchor_text' );

			if ( $to_anchor_text ) {
				$content_rows[90] = '<li class="to_anchor_text clear"><span class="dashicons dashicons-editor-quote" title="' . esc_attr( __( 'Link Anchor Text', WHEREUSED_SLUG ) ) . '"></span> ' . esc_html( $to_anchor_text ) . '</li>';
			}

			$to_url = $row->get( 'to_url' );
			$to_url_full = $row->get( 'to_url_full' );

			if ( $to_url ) {
				$content_rows[100] = '<li class="to_url clear"><span class="dashicons dashicons-admin-links" title="' . esc_attr( __( 'URL', WHEREUSED_SLUG ) ) . '"></span> <a href="' . esc_url( $to_url_full ) . '">' . $this->highlight_search( 'to_url', $to_url ) . '</a></li>';
			}

		}

		if ( $post_id ) {

			// This gets restored in column_to_id()
			if ( is_multisite() && $site_id ) {
				switch_to_blog( $site_id );
			}

			$edit_link = '';
			$edit_link_title = '';
			$view_link = '';
			$title = __( 'No Title', WHEREUSED_SLUG );
			$this_post = '';

			if ( isset( $row->cache[ $type . '_post' ] ) ) {
				$this_post = $row->cache[ $type . '_post' ];
			}

			if ( isset( $this_post->ID ) ) {
				if ( 'from' === $type && 'user' === $post_type ) {
					// We are dealing with a user
					$edit_link = get_edit_user_link( $this_post->ID );
					$edit_link_title = sprintf( __( 'Edit User: %s', WHEREUSED_SLUG ), $this_post->data->display_name );
					$title = $this_post->data->display_name;
				} else {
					// We are dealing with a post
					if ( REQUEST::key( 'post' ) != $this_post->ID ) {
						$edit_link = get_edit_post_link( $this_post->ID );
						$edit_link_title = sprintf( __( 'Edit Post: %s', WHEREUSED_SLUG ), $this_post->post_title );

						if ( $row->get( $type . '_is_public' ) ) {
							$view_link = get_permalink( $this_post->ID );
							$view_link_title = sprintf( __( 'View Post: %s', WHEREUSED_SLUG ), $this_post->post_title );
						}
					}
					$title = $this_post->post_title;
				}
			} elseif ( 'taxonomy term' === $post_type ) {

				// This is a term
				$edit_link = get_edit_term_link( $this_post );
				$edit_link_title = sprintf( __( 'Edit Term: %s', WHEREUSED_SLUG ), $this_post->post_title );

				if ( $row->get( $type . '_is_public' ) ) {
					$view_link = get_term_link( $this_post );
					$view_link_title = sprintf( __( 'View Term: %s', WHEREUSED_SLUG ), $this_post->post_title );
				}

				$title = $this_post->name;

			} elseif ( 'menu' === $post_type ) {
				// We are dealing with a menu
				$menu = Menu::get_by_id( $post_id );
				//d($post_id, $menu);
				$edit_link = $menu->edit_url();
				$edit_link_title = sprintf( __( 'Edit Menu: %s', WHEREUSED_SLUG ), $menu->get( 'name' ) );
				$title = $menu->get( 'name' );
			}

			// Force title to be first row
			$content_rows[0] = '<li class="post-title">';
			if ( $destination === 'edit' && $edit_link ) {
				$content_rows[0] .= '<span class="dashicons dashicons-edit" title="' . esc_attr( __( 'Edit', WHEREUSED_SLUG ) ) . '"></span><a href="' . esc_url( $edit_link ) . '" target="_blank" rel="noreferer noopener" title="' . esc_attr( $edit_link_title ) . '">' . esc_html( $title ) . '</a>';
			} elseif ( $view_link ) {
				$content_rows[0] .= '<a href="' . esc_url( $view_link ) . '" target="_blank" rel="noreferer noopener" title="' . esc_attr( $view_link_title ) . '">' . esc_html( $title ) . '</a>';
			} else {
				$content_rows[0] .= esc_html( $title );
			}
			$content_rows[0] .= '</li>';

			if ( is_multisite() ) {
				// Display Location ( second row )
				$content_rows[500] = '<li class="post-location clear"><span class="dashicons dashicons-admin-multisite" title="' . esc_attr( __( 'Site', WHEREUSED_SLUG ) ) . '"></span> <i>' . esc_html( $row->get_site_domain( $type ) ) . '</i></li>';
			}

			if ( 'from' === $type ) {
				$from_where_key = $row->get( 'from_where_key' );
				$from_where = $row->get( 'from_where' );
				$from_where .= ( $from_where_key ) ? ': ' . $from_where_key : '';

				if ( $from_where ) {
					$content_rows[30] = '<li class="from_where clear"><span class="dashicons dashicons-location" title="' . esc_attr( __( 'Where it was found', WHEREUSED_SLUG ) ) . '"></span> ' . esc_html( $from_where ) . '</li>';
				}
			}

			if ( is_multisite() && $site_id ) {
				restore_current_blog();
			}

		}

		// Display Row Actions
		if ( $row_actions ) {

			if ( $post_id ) {
				// Display Row Actions

				if ( $edit_link ) {
					$row_actions_args['edit'] = '<a href="' . esc_url( $edit_link ) . '" target="_blank" rel="noreferer noopener" aria-label="' . esc_attr( $edit_link_title ) . '">Edit</a>';
				}

				if ( $view_link ) {
					$row_actions_args['view'] = '<a href="' . esc_url( $view_link ) . '" target="_blank" rel="noreferer noopener" aria-label="' . esc_attr( $view_link_title ) . '">View</a>';
				}

				$row_actions_args[ 'view-all-' . $type ] = '<a href="' . esc_url( WHEREUSED_SETTINGS_URL . '&tab=references&' . $type . '=' . $post_id . '&' . $type . '_location=' . $site_id ) . '" aria-label="View All ' . esc_attr( ucwords( $type ) ) . ' ' . esc_attr( $title ) . '">References ' . esc_attr( ucwords( $type ) ) . ' This</a>';

			}

			if ( 'to' === $type && 'block' != $to_type ) {
				$to_url_full = $row->get( 'to_url_full' );

				if ( Get::is_real_url( $to_url_full ) ) {
					if ( REQUEST::key( 'action' ) != 'edit' ) {
						$row_actions_args['check'] = '<a href="#check-status" aria-label="Recheck Status" class="action-check-status" data-check-url="' . esc_url( $to_url_full ) . '">' . __( 'Recheck Status', WHEREUSED_SLUG ) . '</a>';
					}
					$row_actions_args['trace'] = '<a href="https://wheregoes.com/?url=' . esc_attr( urlencode( $to_url_full ) ) . '" target="_blank" rel="noreferer noopener" aria-label="Trace URL">' . __( 'Trace URL', WHEREUSED_SLUG ) . '</a>';
				}
			}

			if ( ! empty( $row_actions_args ) ) {
				// Make this the last row
				$content_rows[999] = '<li class="clear">' . $this->row_actions( $row_actions_args ) . '</li>';
			}
		}

		if ( ! empty( $content_rows ) ) {

			// Sort rows by key ASC
			ksort( $content_rows );

			// Add rows to content
			$content .= '<ul class="content-rows">' . implode( '', $content_rows ) . '</ul>';
		}

		$content .= '</div>';

		return $content;

	}

	/**
	 * Displays the date column
	 *
	 * @package WhereUsed
	 * @since   1.0.0
	 *
	 * @param \WhereUsed\Row $row
	 *
	 * @return string
	 */
	protected function column_date( Row $row ): string {

		return esc_html( date( 'D M d, Y g:i:s A', strtotime( $row->date ) ) );

	}

}
