<?php

namespace WhereUsed\HelpersLibrary;

// Prevent Direct Access
( defined( 'ABSPATH' ) ) || die;

/**
 * Class Settings_Display - Contains all the helper methods for displaying a settings page
 */
final class Settings_Display {

	use Constants;

	/**
	 * Displays the select dropdown for the setting property
	 */
	public static function select( array $args ): void {

		$property = $args['property'] ?? '';
		$settings = $args['settings'] ?? [];
		$options = $args['options'] ?? [];
		$disabled = $args['disabled'] ?? false;
		$disabled = ( $disabled ) ? ' DISABLED' : '';

		if ( empty( $args ) ) {
			echo '<b>Empty args!</b>';

			return;
		}

		if ( empty( $options ) ) {
			echo '<b>Empty options!</b>';

			return;
		}

		if ( ! $property ) {
			echo '<b>Empty Property!</b>';

			return;
		}

		$class = str_replace( '_', '-', $property );
		$value = $settings->get( $args['property'] );

		echo '<select name="' . esc_attr( $property ) . '" class="' . esc_attr( $class ) . '"' . esc_attr( $disabled ) . '>';

		foreach ( $options as $key => $option ) {

			if ( is_array( $option ) ) {
				$v = $option['value'] ?? '';
				$l = $option['label'] ?? '';
			} else {
				// Make compatible with basic array
				// label => value
				$v = $key;
				$l = $option;
			}

			echo '<option value="' . esc_attr( $v ) . '"';
			if ( $v == $value ) {
				echo 'SELECTED';
			}
			echo '>' . esc_html( $l ) . '</option>';

		}

		echo '</select>';

	}

	/**
	 * Displays the input for the setting property
	 *
	 * @param array $args
	 */
	public static function input( array $args ): void {

		$property = $args['property'] ?? '';
		$settings = $args['settings'] ?? [];
		$type = $args['type'] ?? 'text';
		$disabled = $args['disabled'] ?? false;
		$disabled = ( $disabled ) ? ' DISABLED' : '';

		// Check to ensure we have a property
		if ( ! $property ) {
			echo '<b>Empty Property!</b>';

			return;
		}

		// Check to ensure we have settings
		if ( empty( $settings ) ) {
			echo '<b>Empty settings!</b>';

			return;
		}

		$class = str_replace( '_', '-', $property );
		$value = $settings->get( $property );

		echo '<input type="' . esc_attr( $type ) . '" name="' . esc_attr( $property ) . '" class="' . esc_attr( $class ) . '" value="' . esc_attr( $value ) . '"' . esc_attr( $disabled ) . '/>';

	}

	/**
	 * Displays checkboxes
	 *
	 * @param array $args
	 *
	 * @return void
	 */
	public static function checkboxes( array $args ): void {

		$property = $args['property'] ?? '';
		$settings = $args['settings'] ?? [];
		$options = $args['options'] ?? [];
		$disabled = $args['disabled'] ?? false;

		// Check to ensure we have a property
		if ( ! $property ) {
			echo '<b>Empty Property!</b>';

			return;
		}

		// Check to ensure we have settings
		if ( empty( $settings ) ) {
			echo '<b>Empty settings!</b>';

			return;
		}

		// Check to ensure we have options
		if ( empty( $options ) ) {
			echo '<b>Empty options!</b>';

			return;
		}

		$disabled = ( $disabled ) ? ' DISABLED' : '';

		$num = 0;
		$values = $settings->get( $property );

		foreach ( $options as $option_key => $option ) {

			// Ensure we have defaults
			$option['append'] = $option['append'] ?? [];
			$option['description'] = $option['description'] ?? [];

			if ( ! isset( $option['value'] ) || ! isset( $option['label'] ) ) {
				echo '<b>invalid format for option:' . esc_html( $option_key ) . ' </b>';
			}
			$num ++;

			$checked = '';

			// Check the checkboxes based on the values given
			if ( ! empty( $values ) ) {
				foreach ( $values as $key => $value ) {
					if ( $option['value'] == $value ) {
						unset( $values[ $key ] ); // Remove this from the list
						$checked = ' CHECKED';
						break;
					}
				}
			}

			$id = $property . '-' . $num;

			echo '<div class="checkbox-row item-row"><input name="' . esc_attr( $property ) . '[]" value="' . esc_attr( $option['value'] ) . '" type="checkbox" id="' . esc_attr( $id ) . '" ' . esc_html( $checked ) . esc_html( $disabled ) . ' /><label for="' . esc_attr( $id ) . '">' . esc_html( $option['label'] ) . '</label>';

			if ( ! empty( $option['append'] ) ) {

				foreach ( $option['append'] as $append ) {

					$text = $append['text'] ?? '';
					$link = $append['link'] ?? '';
					$link_icon = $append['link-icon'] ?? '';
					$style = $append['style'] ?? '';
					$before = $append['before'] ?? '';
					$after = $append['after'] ?? '';

					if ( $before ) {
						echo esc_html( $before );
					}

					if ( $link ) {
						if ( $link_icon ) {
							echo '<span class="dashicons ' . esc_attr( $link_icon ) . '"></span>';
						}
						echo ' <a href="' . esc_attr( $link ) . '" style="' . esc_attr( $style ) . '">' . esc_html( $text ) . '</a> ';
					} else {
						echo ' <span style="' . esc_attr( $style ) . '">' . esc_html( $text ) . '</span> ';
					}

					if ( $after ) {
						echo esc_html( $after );
					}
				}

			}

			if ( ! empty( $option['description'] ) ) {
				echo '<br /><small><i>';
				foreach ( $option['description'] as $description ) {

					$text = $description['text'] ?? '';
					$link = $description['link'] ?? '';
					$style = $description['style'] ?? '';

					if ( $link ) {
						echo '<a href="' . esc_attr( $link ) . '" style="' . esc_attr( $style ) . '">' . esc_html( $text ) . '</a> ';
					} else {
						echo '<span style="' . esc_attr( $style ) . '">' . esc_html( $text ) . '</span> ';
					}
				}
				echo '</i></small>';
			}
			echo '</div>';

		}

	}

	/**
	 * Displays a table of checkboxes
	 *
	 * @param array $args
	 *
	 * @return void
	 */
	public static function checkbox_table( array $args ): void {

		$rows = $args['rows'] ?? '';
		$settings = $args['settings'] ?? [];
		$options = $args['options'] ?? [];
		$disabled = $args['disabled'] ?? false;

		$disabled = ( $disabled ) ? ' DISABLED' : '';

		$columns = $args['columns'] ?? [];

		// Check to ensure we have a label for the rows
		if ( empty( $rows['label'] ) ) {
			echo '<b>Empty Rows Label!</b>';

			return;
		}

		// Check rows values
		if ( empty( $rows['values'] ) ) {
			echo '<b>Rows missing values</b>';

			return;
		}

		// Check to ensure we have options
		if ( empty( $options ) ) {
			echo '<b>Empty options!</b>';

			return;
		}


		echo '<table>';

		// Create heading table row
		echo '<tr><th style="padding:8px">' . esc_html( $rows['label'] ) . '</th>';
		foreach ( $options as $option ) {
			echo '<th style="padding:8px">' . esc_html( $option['label'] ) . '</th>';
		}
		echo '</tr>';

		foreach ( $rows['values'] as $row ) {

			echo '<tr><td>' . esc_html( $row['label'] ) . '</td>';

			foreach ( $options as $option ) {

				if ( isset( $row['excluded'] ) && in_array( $option['property'], $row['excluded'] ) ) {
					echo '<td style="text-align: center;">x</td>';
				} else {
					// Check if the field should be force checked
					$checked = ( isset( $row['checked'] ) && in_array( $option['property'], $row['checked'] ) ) ? ' CHECKED' : '';

					// Check if the settings mark this as checked
					$checked = ( $checked || in_array( $row['value'], $option['value'] ) ) ? ' CHECKED' : '';

					// Check if specific checkbox is forced disabled
					$disabled_box = ( isset( $row['disabled'] ) && in_array( $option['property'], $row['disabled'] ) ) ? ' DISABLED' : '';

					$class = esc_attr( str_replace( '_', '-', $option['property'] ) );

					$label = $rows['label'] . ' ' . $row['label'] . ' ' . __( 'has', static::get_constant_value( 'SLUG' ) ) . ' ' . $option['label'];

					// Check if network disabled
					$disabled_box = ( $disabled || $disabled_box ) ? ' DISABLED' : '';
					echo '<td style="text-align: center;"><input name="' . esc_attr( $option['property'] ) . '[]" aria-label="' . esc_attr( $label ) . '" class="' . esc_attr( $class ) . '" value="' . esc_attr( $row['value'] ) . '" type="checkbox" ' . esc_html( $checked ) . esc_html( $disabled_box ) . '/></td>';
				}
			}

			echo '</tr>';
		}

		echo '</table>';

	}

	/**
	 * Displays a list with a heading for reading purposes only
	 *
	 * @param string $heading
	 * @param array  $items
	 * @param string $dashicon
	 * @param bool   $hr
	 *
	 */
	public static function list( string $heading, array $items, string $dashicon = '', bool $hr = false ): void {

		echo '<h3>';
		echo ( $dashicon ) ? '<span class="dashicons ' . esc_attr( $dashicon ) . '"></span> ' : '';

		echo esc_html( $heading ) . '</h3>';

		echo '<ul>';
		if ( empty( $items ) ) {
			echo '<li>' . esc_html__( 'None selected', static::get_constant_value( 'SLUG' ) ) . '</li>';
		} else {
			foreach ( $items as $item ) {
				$item = $item ?? '';
				if ( trim( $item ) ) {
					echo '<li>' . esc_html( trim( $item ) ) . '</li>';
				}
			}
		}
		echo '</ul>';

		if ( $hr ) {
			echo '<hr />';
		}

	}

}