<?php

namespace WhereUsed\HelpersLibrary;

// Prevent Direct Access
( defined( 'ABSPATH' ) ) || die;

/**
 * Sanitizes all $_REQUEST, $_GET, and $_POST variables reliably
 */
final class REQUEST {

	/**
	 * Retrieves and sanitizes the supplied key as a boolean.
	 *
	 * @param string $key
	 * @param string $subkey
	 * @param bool   $default
	 *
	 * @return bool
	 */
	public static function bool( string $key = '', string $subkey = '', bool $default = false ): bool {

		$value = $default;

		if ( $subkey && isset( $_REQUEST[ $key ][ $subkey ] ) ) {
			$value = (bool) $_REQUEST[ $key ][ $subkey ];
		} elseif ( isset( $_REQUEST[ $key ] ) ) {
			$value = (bool) $_REQUEST[ $key ];
		}

		return $value;
	}

	/**
	 * Retrieves and sanitizes the supplied key as a text field.
	 *
	 * @param string $key
	 * @param string $subkey
	 * @param string $default
	 *
	 * @return string
	 */
	public static function text_field( string $key = '', string $subkey = '', string $default = '' ): string {

		$value = $default;

		if ( $subkey && isset( $_REQUEST[ $key ][ $subkey ] ) ) {
			$value = sanitize_text_field( $_REQUEST[ $key ][ $subkey ] );
		} elseif ( isset( $_REQUEST[ $key ] ) ) {
			$value = sanitize_text_field( $_REQUEST[ $key ] );
		}

		return $value;
	}

	/**
	 * Retrieves and sanitizes the supplied key as a URL field.
	 *
	 * @param string $key
	 * @param string $subkey
	 * @param string $default
	 *
	 * @return string
	 */
	public static function url( string $key = '', string $subkey = '', string $default = '' ): string {

		$value = $default;

		if ( $subkey && isset( $_REQUEST[ $key ][ $subkey ] ) ) {
			$value = sanitize_url( $_REQUEST[ $key ][ $subkey ] );
		} elseif ( isset( $_REQUEST[ $key ] ) ) {
			$value = sanitize_url( $_REQUEST[ $key ] );
		}

		return $value;
	}

	/**
	 * Retrieves and sanitizes the supplied key as an email field.
	 *
	 * @param string $key
	 * @param string $subkey
	 * @param string $default
	 *
	 * @return string
	 */
	public static function email( string $key = '', string $subkey = '', string $default = '' ): string {

		$value = $default;

		if ( $subkey && isset( $_REQUEST[ $key ][ $subkey ] ) ) {
			$value = sanitize_email( $_REQUEST[ $key ][ $subkey ] );
		} elseif ( isset( $_REQUEST[ $key ] ) ) {
			$value = sanitize_email( $_REQUEST[ $key ] );
		}

		return $value;
	}

	/**
	 * Retrieves and sanitizes the supplied key as an integer.
	 *
	 * @param string $key
	 * @param string $subkey
	 * @param int    $default
	 *
	 * @return int
	 */
	public static function int( string $key = '', string $subkey = '', int $default = 0 ): int {

		$value = $default;

		if ( $subkey && isset( $_REQUEST[ $key ][ $subkey ] ) ) {
			$value = (int) $_REQUEST[ $key ][ $subkey ];
		} elseif ( isset( $_REQUEST[ $key ] ) ) {
			$value = (int) $_REQUEST[ $key ];
		}

		return $value;

	}

	/**
	 * Retrieves and sanitizes the supplied key as a key string.
	 *
	 * @param string $key
	 * @param string $subkey
	 * @param string $default
	 * @param string $prepend
	 * @param string $append
	 *
	 * @return string
	 */
	public static function key( string $key = '', string $subkey = '', string $default = '', string $prepend = '', string $append = '' ): string {

		$value = $default;

		if ( $subkey && isset( $_REQUEST[ $key ][ $subkey ] ) ) {
			$value = sanitize_key( $_REQUEST[ $key ][ $subkey ] );
		} elseif ( isset( $_REQUEST[ $key ] ) ) {
			$value = sanitize_key( $_REQUEST[ $key ] );
		}

		return $prepend . $value . $append;

	}

	/**
	 * Retrieves and sanitizes the supplied key's array values
	 *
	 * @param string $key
	 *
	 * @return array
	 */
	public static function array( string $key = '' ): array {

		$array =  $_REQUEST[ $key ] ?? [];

		if ( ! empty( $array ) ) {
			$array = self::sanitize( $array );
		}

		return $array;

	}

	/**
	 * Retrieves and sanitizes the entire $_GET array
	 *
	 * @param array $var_types Example: ['key_name' => 'int|string|bool|float|array']
	 * @param array $default_values If the array key doesn't exist, the key will be created and the default value will be applied.
	 *
	 * @return array
	 */
	public static function GET( array $var_types = [], array $default_values = [] ): array {

		return self::var( 'GET', $var_types, $default_values );

	}

	/**
	 * Retrieves and sanitizes the entire $_POST array
	 *
	 * @param array $var_types Example: ['key_name' => 'int|string|bool|float|array']
	 * @param array $default_values If the array key doesn't exist, the key will be created and the default value will be applied.
	 *
	 * @return array
	 */
	public static function POST( array $var_types = [], array $default_values = [] ): array {

		return self::var( 'POST', $var_types, $default_values );

	}

	/**
	 * Retrieves and sanitizes the entire $_REQUEST array
	 *
	 * @param array $var_types Example: ['key_name' => 'int|string|bool|float|array']
	 * @param array $default_values If the array key doesn't exist, the key will be created and the default value will be applied.
	 *
	 * @return array
	 */
	public static function REQUEST( array $var_types = [], array $default_values = [] ): array {

		return self::var( 'REQUEST', $var_types, $default_values );

	}

	/**
	 * Retrieves and sanitizes the entire $_SERVER array
	 *
	 * @param array $var_types Example: ['key_name' => 'int|string|bool|float|array']
	 * @param array $default_values If the array key doesn't exist, the key will be created and the default value will be applied.
	 *
	 * @return array
	 */
	public static function SERVER( array $var_types = [], array $default_values = [] ): array {

		return self::var( 'SERVER', $var_types, $default_values );

	}

	/**
	 * Retrieves and sanitizes the supplied $_SERVER key as a text field.
	 *
	 * @param string $key
	 * @param string $default
	 *
	 * @return string
	 */
	public static function SERVER_text_field( string $key = '', string $default = '' ) : string {

		$value = $default;

		if ( isset( $_SERVER[ $key ] ) ) {
			$value = self::sanitize( $_SERVER[ $key ], $key );
		}

		return (string) $value;
	}

	/**
	 * Retrieves and sanitizes $_GET, $_POST, and $_REQUEST arrays
	 *
	 * @param string $type
	 * @param array $default_values If the array key doesn't exist, the key will be created and the default value will be applied.
	 *
	 * @return array
	 */
	private static function var( string $type = 'GET', array $var_types = [], array $default_values = [] ): array {

		$array = [];

		// Grab the unsanitized raw data
		if ( 'GET' === $type && ! empty( $_GET ) ) {
			$array = $_GET;
		} elseif ( 'POST' === $type && ! empty( $_POST ) ) {
			$array = $_POST;
		} elseif ( 'REQUEST' === $type && ! empty( $_REQUEST ) ) {
			$array = $_REQUEST;
		} elseif ( 'SERVER' === $type && ! empty( $_SERVER ) ) {
			$array = $_SERVER;
		}

		// Ensure we have default values
		$array = array_merge( $default_values, $array );

		// Sanitize Data
		if ( ! empty( $array ) ) {
			foreach ( $array as $k => $v ) {

				if ( ! empty( $var_types[ $k ] ) ) {

					switch ( $var_types[ $k ] ) {
						case 'int':
							$array[ $k ] = (int) $v;
							break;
						case 'float':
							$array[ $k ] = (float) $v;
							break;
						case 'bool':
							$array[ $k ] = (bool) $v;
							break;
						case 'string':
							$array[ $k ] = (string) $v;
							break;
						default:
							$array[ $k ] = self::sanitize( $v, $k );
					}

				} else {
					// Default to generic sanitization
					$array[ $k ] = self::sanitize( $v, $k );
				}

			}
		}

		return $array;

	}

	/**
	 * Sanitize based on the value given without context unless an array key provides content.
	 *
	 * @param array|string|bool|int|float $value
	 *
	 * @return array|string|bool|int|float
	 */
	private static function sanitize( $value, string $key = '' ) {

		$clean = false;

		if( $key ) {

			// Sanitize based on given array key
			$url_fields = [
				'REQUEST_URI',
				'DOCUMENT_URI',
				'SCRIPT_NAME',
				'PHP_SELF',
				'PATH_INFO',
			];
			$file_paths_fields = [
				'HOME',
				'DOCUMENT_ROOT',
				'SCRIPT_FILENAME',
			];
			$url_decode_fields = [
				'QUERY_STRING',
			];

			if ( in_array( $key, $url_fields ) ) {
				// The key indicates that the value is a URL
				$value = sanitize_url( $value );
				$clean = true;
			} elseif ( in_array( $key, $file_paths_fields ) ) {
				// The key indicates that the value is a file path
				// don't touch - don't want to remove or change %20 or "+" to anything
				// @todo figure out a reliable way to sanitize this
				$clean = true;
			} elseif ( in_array( $key, $url_decode_fields ) ) {
				// The ey indicates that the value needs to be decoded
				$value = url_decode( $value );
				$clean = true;
			}
		}
		if ( ! $clean ) {
			if ( is_array( $value ) ) {
				// sanitize array
				if ( ! empty( $value ) ) {
					foreach ( $value as $k => $v ) {
						$value[ $k ] = self::sanitize( $v, $k );
					}
				}
			} elseif ( is_string( $value ) && self::is_url( $value ) ) {
				// Sanitize URL: NOTE: Does not recognize file paths and relative URLs
				$value = sanitize_url( $value );
			} elseif ( is_string( $value ) && is_email( $value ) ) {
				// Sanitize email
				$value = sanitize_email( $value );
			} elseif ( is_numeric( $value ) ) {
				// Sanitize Number
				$value = filter_var( $value, FILTER_SANITIZE_NUMBER_FLOAT );
			} elseif ( is_bool( $value ) ) {
				// Sanitize Boolean
				$value = (bool) $value;
			} else {
				// Generic Sanitization as text field
				$value = sanitize_text_field( $value );
			}
		}

		return $value;

	}

	/**
	 * Detect if the value provided is a URL
	 *
	 * @param string $value
	 *
	 * @return bool
	 */
	private static function is_url( string $value ): bool {

		$is_url = false;

		// Strip extra spaces
		$value = trim( $value );

		if ( !empty( $value) ) {

			$parsed_url = parse_url( $value );

			$scheme = $parsed_url["scheme"] ?? '';
			$scheme_valid = false;

			if ( in_array($scheme, wp_allowed_protocols() ) ) {
				$scheme_valid = true;
			}

			$host = $parsed_url["host"] ?? '';
			$host_valid = false;

			if ( $host ) {
				// It has domain name

				// Check to see if there is a TLD involved
				$domain_parts = explode( '.', $host );
				$tld = end($domain_parts);

				if ( strlen($tld) > 1 && strlen($tld) <= 10 ) {
					// It has a TLD (may not be a legit one though)
					$host_valid = true;
				}
			}

			$path = $parsed_url["path"] ?? '';
			$path_valid = false;

			if ( $path && strlen( $path ) < 3048 ) {
				if ( $scheme_valid && ! in_array( $scheme, [
						'http',
						'https',
					] ) ) {
					// Not typical scheme thus we accept any path value
					$path_valid = true;
				} elseif ( substr( $path, 0, 1 ) === '/' ) {
					// It's a relative path or URL
					$path_valid = true;
				} elseif ( substr( $path, 0, 3 ) === '../' ) {
					// It's relative path going up a directory
					$path_valid = true;
				}
			}

			if ( $scheme ){
				// We have a scheme
				if ( $scheme_valid && ($host_valid || $path_valid ) ) {
					$is_url = true;
				}
			}  elseif ( $host_valid || $path_valid ) {
				$is_url = true;
			} elseif ( isset($parsed_url["fragment"]) ) {
				$is_url = true;
			}
		}

		return $is_url;
	}
}