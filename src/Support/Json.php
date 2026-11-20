<?php
/**
 * JSON written the one way the lock file depends on.
 *
 * @package     SugarCommerce\Schema
 * @copyright   Copyright (c) 2026, ArrayPress Limited
 * @license     GPL-2.0-or-later
 * @since       1.0.0
 */

declare( strict_types=1 );

namespace SugarCommerce\Schema\Support;

/**
 * A stable JSON encoder.
 *
 * A table's fingerprint is a hash of its definition written out as JSON, so
 * the writing has to be the same on every machine and in every release, or
 * a fingerprint moves when nothing about the table did -- and a moved
 * fingerprint raises a version and sends every site looking for an upgrade
 * there is none of.
 *
 * So this does not use json_encode(), whose spacing, escaping and key order
 * are its own. It writes objects with their keys sorted, `", "` and `": "`
 * between items, every character outside printable ASCII as `\uXXXX`, and a
 * slash left alone -- the format the first generation of lock files was
 * written in, so their fingerprints still match.
 *
 * Objects are stdClass. A PHP array is written as a JSON array when it is a
 * list and as an object when it is not, which is ambiguous only for an
 * empty one: that is written as `[]`.
 *
 * @since 1.0.0
 */
final class Json {

	/**
	 * Encode a value.
	 *
	 * @param mixed    $value     What to write.
	 * @param bool     $sort_keys Whether to sort object keys.
	 * @param int|null $indent    Spaces per level, or null for one line.
	 *
	 * @return string
	 * @since 1.0.0
	 */
	public static function encode( mixed $value, bool $sort_keys = false, ?int $indent = null ): string {
		return self::write( $value, $sort_keys, $indent, 0 );
	}

	/**
	 * One value, at one depth.
	 *
	 * @param mixed    $value     What to write.
	 * @param bool     $sort_keys Whether to sort object keys.
	 * @param int|null $indent    Spaces per level, or null.
	 * @param int      $depth     How deep this value is.
	 *
	 * @return string
	 * @since 1.0.0
	 */
	private static function write( mixed $value, bool $sort_keys, ?int $indent, int $depth ): string {
		if ( null === $value ) {
			return 'null';
		}

		if ( true === $value ) {
			return 'true';
		}

		if ( false === $value ) {
			return 'false';
		}

		if ( is_int( $value ) ) {
			return (string) $value;
		}

		if ( is_float( $value ) ) {
			return self::number( $value );
		}

		if ( is_string( $value ) ) {
			return self::string( $value );
		}

		if ( $value instanceof \stdClass ) {
			return self::container( get_object_vars( $value ), true, $sort_keys, $indent, $depth );
		}

		if ( is_array( $value ) ) {
			return self::container( $value, ! array_is_list( $value ), $sort_keys, $indent, $depth );
		}

		throw new \InvalidArgumentException( 'Cannot write a ' . get_debug_type( $value ) . ' as JSON.' );
	}

	/**
	 * An object or an array.
	 *
	 * @param array<mixed> $items     The members.
	 * @param bool         $object    Whether to write an object.
	 * @param bool         $sort_keys Whether to sort keys.
	 * @param int|null     $indent    Spaces per level, or null.
	 * @param int          $depth     How deep this is.
	 *
	 * @return string
	 * @since 1.0.0
	 */
	private static function container( array $items, bool $object, bool $sort_keys, ?int $indent, int $depth ): string {
		[ $open, $close ] = $object ? [ '{', '}' ] : [ '[', ']' ];

		if ( [] === $items ) {
			return $open . $close;
		}

		if ( $object && $sort_keys ) {
			uksort( $items, static fn( $a, $b ): int => strcmp( (string) $a, (string) $b ) );
		}

		$parts = [];

		foreach ( $items as $key => $item ) {
			$written = self::write( $item, $sort_keys, $indent, $depth + 1 );
			$parts[] = $object ? self::string( (string) $key ) . ': ' . $written : $written;
		}

		if ( null === $indent ) {
			return $open . implode( ', ', $parts ) . $close;
		}

		$inner = "\n" . str_repeat( ' ', $indent * ( $depth + 1 ) );
		$outer = "\n" . str_repeat( ' ', $indent * $depth );

		return $open . $inner . implode( ',' . $inner, $parts ) . $outer . $close;
	}

	/**
	 * A string, quoted and escaped.
	 *
	 * @param string $value The string.
	 *
	 * @return string
	 * @since 1.0.0
	 */
	private static function string( string $value ): string {
		$short = [
			'"'    => '\\"',
			'\\'   => '\\\\',
			"\n"   => '\\n',
			"\r"   => '\\r',
			"\t"   => '\\t',
			"\x08" => '\\b',
			"\x0c" => '\\f',
		];

		$out = '';

		foreach ( mb_str_split( $value, 1, 'UTF-8' ) as $char ) {
			if ( isset( $short[ $char ] ) ) {
				$out .= $short[ $char ];
				continue;
			}

			$point = mb_ord( $char, 'UTF-8' );

			if ( $point >= 0x20 && $point <= 0x7e ) {
				$out .= $char;
				continue;
			}

			if ( $point > 0xffff ) {
				$point -= 0x10000;
				$out   .= sprintf( '\\u%04x\\u%04x', 0xd800 | ( $point >> 10 ), 0xdc00 | ( $point & 0x3ff ) );
				continue;
			}

			$out .= sprintf( '\\u%04x', $point );
		}

		return '"' . $out . '"';
	}

	/**
	 * A float, in its shortest form that reads back the same.
	 *
	 * @param float $value The number.
	 *
	 * @return string
	 * @since 1.0.0
	 */
	private static function number( float $value ): string {
		if ( is_nan( $value ) ) {
			return 'NaN';
		}

		if ( is_infinite( $value ) ) {
			return $value > 0 ? 'Infinity' : '-Infinity';
		}

		$out = var_export( $value, true );

		return str_contains( $out, '.' ) || str_contains( $out, 'E' ) || str_contains( $out, 'e' ) ? $out : $out . '.0';
	}
}
