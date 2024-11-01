<?php

namespace WhereUsed\HelpersLibrary;

// Prevent Direct Access
( defined( 'ABSPATH' ) ) || die;

/**
 * Class Menu
 *
 * @package WhereUsed\HelpersLibrary
 * @since   1.0.0
 */
abstract class Menu extends Base {

	protected int $id = 0;
	protected int $site_id = 0;
	protected string $slug = '';
	protected string $name = '';
	protected string $edit_url = '';
	protected array $items = [];

	/**
	 * Registers hooks related to menus
	 *
	 * @return void
	 */
	public static function init(): void {

		// Delete Nav Menu
		add_action( 'wp_delete_nav_menu', [
			static::class,
			'wp_delete_nav_menu',
		], 999, 1 );

		// Add/Update Nav Menu
		add_action( 'wp_update_nav_menu', [
			static::class,
			'wp_update_nav_menu',
		], 999, 1 );

	}

	/**
	 * Cleans up the database after a Nav Menu has been deleted
	 *
	 * @param int $term_id ID of the deleted menu.
	 *
	 * @return void
	 */
	public static function wp_delete_nav_menu( int $term_id ): void {

		$cache_status_codes_option = static::get_class( 'Get' )::cache_status_codes_option_name('wp_delete_nav_menu', $term_id );

		static::get_class( 'Scan' )::reprocess_entries( $term_id, 'menu', $cache_status_codes_option );

		// clear cache
		delete_option( $cache_status_codes_option );
		
	}

	/**
	 * Rescans Menu After it has been updated.
	 *
	 * @param int $menu_id ID of the updated menu
	 *
	 * @return void
	 */
	public static function wp_update_nav_menu( int $menu_id ): void {

		$settings = static::get_class( 'Settings' )::get_current_settings();

		// Make sure this post type can be scanned
		if ( ! $settings->get( 'scan_menus' ) ) {
			return;
		}

		$cache_status_codes_option = static::get_class( 'Get' )::cache_status_codes_option_name('wp_update_nav_menu', $menu_id );

		$menu = static::get_by_id( $menu_id );
		if ( $menu ) {
			static::get_class( 'Scan' )::scan_menu( $menu, WHEREUSED_HELPERSLIBRARY_CURRENT_SITE_ID, $cache_status_codes_option );
		}

		// clear cache
		delete_option( $cache_status_codes_option );

	}

	/**
	 * Retrieves the edit menu URL when provided the menu id
	 *
	 * @param int $menu_id
	 *
	 * @return string
	 */
	public static function get_edit_url( int $menu_id ): string {

		return admin_url( 'nav-menus.php?action=edit&menu=' . $menu_id );

	}

	/**
	 * Returns the edit url for the menu object
	 *
	 * @return string
	 */
	public function edit_url(): string {

		return static::get_edit_url( $this->id );

	}

	/**
	 * Gets all registered menus and their associated items (optional)
	 *
	 * @param bool   $items
	 * @param string $field
	 *
	 * @return array
	 */
	public static function get_all( bool $items = true, string $field = '' ): array {

		$menus = [];

		$nav_menus = wp_get_nav_menus();

		$current_site_id = get_current_blog_id();

		foreach ( $nav_menus as $nav_menu ) {

			$args = [
				'id' => $nav_menu->term_id,
				'site_id' => $current_site_id,
				'slug' => $nav_menu->slug,
				'name' => $nav_menu->name,
				'edit_url' => static::get_edit_url( $nav_menu->term_id ),
			];

			if ( $items ) {
				$args['items'] = wp_get_nav_menu_items( $nav_menu->slug );
			}

			if ( $field ) {
				$menus[] = $args[ $field ] ?? 'invalid field';
			} else {
				$menu_class = static::get_class( 'Menu' );
				$menus[] = new $menu_class( $args );
			}

		}

		return $menus;
	}

	/**
	 * Gets a menu by id and the menu items (optional)
	 *
	 * @param int  $menu_id
	 * @param bool $items
	 *
	 * @return Menu | false
	 */
	public static function get_by_id( int $menu_id, bool $items = true ) {

		$menu = false;
		$nav_menu = wp_get_nav_menu_object( $menu_id );

		if ( is_object( $nav_menu ) ) {
			$args = [
				'id' => $nav_menu->term_id,
				'site_id' => get_current_blog_id(),
				'slug' => $nav_menu->slug,
				'name' => $nav_menu->name,
				'edit_url' => static::get_edit_url( $nav_menu->term_id ),
			];

			if ( $items ) {
				$args['items'] = wp_get_nav_menu_items( $nav_menu->slug );
			}

			$menu_class = static::get_class( 'Menu' );

			$menu = new $menu_class( $args );

		}

		return $menu;
	}

}