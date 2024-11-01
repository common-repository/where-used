<?php

namespace WhereUsed\HelpersLibrary;

// Prevent Direct Access
( defined( 'ABSPATH' ) ) || die;

/**
 * Class Base - Contains the common methods library
 */
abstract class Base {

	use Constants;

	/**
	 * Loads data
	 *
	 * @param array|object $data
	 */
	public function __construct( $data = [] ) {
		if ( ! empty( $data ) ) {
			$this->load( $data );
		}
	}

	/**
	 * Gets property
	 *
	 * @param string $property
	 * @param string $convert_to
	 *
	 * @return mixed
	 */
	public final function get( string $property, string $convert_to = '' ) {

		// Get the value if the property exists or default to null
		$value = ( $property && property_exists( $this, $property ) ) ? $this->$property : null;

		// Convert the value to a specific type
		if ( $convert_to ) {
			if ( 'json' === $convert_to ) {
				$value = json_encode( $value );
			} else {
				settype( $value, $convert_to );
			}
		}

		return $value;

	}

	/**
	 * Overwrites the objects current data with new data
	 *
	 * @param array|object $data
	 */
	public final function overwrite( $data = [] ): void {

		$this->load( $data, true );

	}

	/**
	 * Loads the given settings into the current object
	 *
	 * @param array|object $data
	 * @param bool         $use_all_properties
	 */
	protected final function load( $data, bool $use_all_properties = false ): void {

		if ( ! empty( $data ) ) {

			$ignore_properties = [];

			if ( ! $use_all_properties ) {
				$ignore_properties = $this->ignore_properties();
				if ( ! empty( $ignore_properties ) ) {
					$ignore_properties = array_flip( $ignore_properties );
				}
			}

			if ( is_object( $data ) && method_exists( $data, 'get' ) ) {
				$vars = get_object_vars( $this );

				foreach ( $vars as $property => $value ) {

					if ( ! isset( $ignore_properties[ $property ] ) ) {
						if ( property_exists( $data, $property ) ) {
							$this->set( $property, $data->get( $property ) );
						}
					}

				}
			} else {
				// Assuming properties are public
				foreach ( $data as $property => $value ) {
					if ( ! isset( $ignore_properties[ $property ] ) ) {
						$this->set( $property, $value );
					}
				}
			}

		}

	}

	/**
	 * (optional) An array of properties to ignore when loading data
	 *
	 * @return array
	 */
	protected function ignore_properties(): array {
		// Override with child class
		return [];
	}

	/**
	 * Sets the given property for this class
	 *
	 * @param string $property
	 * @param mixed  $value
	 *
	 * @return void
	 */
	protected final function set( string $property, $value ): void {

		if ( $property && property_exists( $this, $property ) ) {

			$type = gettype( $this->$property );

			if ( 'integer' === $type ) {
				$this->$property = (int) $value;
			} elseif ( 'string' === $type ) {
				$this->$property = (string) $value;
			} elseif ( 'boolean' === $type ) {
				$this->$property = (bool) $value;
			} elseif ( 'array' === $type ) {

				// Check if value is JSON and convert it to an array if it is
				if ( is_string( $value ) ) {
					$json = json_decode( $value, true );

					if ( is_array( $json ) && json_last_error() == JSON_ERROR_NONE ) {
						// We have a JSON string so let's use it
						$value = $json;
					}
				}

				$this->$property = (array) $value;
			}

		}

	}

	/**
	 * Returns all property names and type of the current object regardless of visibility.
	 * This is helpful if you want to iterate over the entire object and there
	 * are private or protected properties involved.
	 *
	 * @return array
	 */
	public function get_properties(): array {

		$properties = [];

		foreach ( $this as $key => $value ) {
			$properties[ $key ] = gettype( $this->$key );
		}

		return $properties;
	}

}