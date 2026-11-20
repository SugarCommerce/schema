<?php
/**
 * What a column holds, and what cleans it.
 *
 * @package     SugarCommerce\Schema
 * @copyright   Copyright (c) 2026, ArrayPress Limited
 * @license     GPL-2.0-or-later
 * @since       1.0.0
 */

declare( strict_types=1 );

namespace SugarCommerce\Schema\Render;

use SugarCommerce\Schema\Config;
use SugarCommerce\Schema\GeneratorException;
use SugarCommerce\Schema\Support\Value;

/**
 * The shape of a stored value.
 *
 * Every column is given a save-time callback -- BerlinDB's `validate`, which
 * it calls with the value alone on every insert and on every update that
 * changes the column, and whose answer is what gets written. Which callback
 * depends on the KIND of thing the column holds, and the kind is worked out
 * from the column's name and type, so the schema stays a list of columns
 * rather than a list of callbacks. A column that is not what its name
 * suggests says so:
 *
 *     "kind": "text"                      one of the configured kinds
 *     "sanitize": "my_plugin_clean_sku"   any function taking the value
 *     "sanitize": false                   no callback at all
 *
 * @since 1.0.0
 */
final class Kinds {

	/**
	 * Constructor.
	 *
	 * @param Config $config The settings.
	 *
	 * @since 1.0.0
	 */
	public function __construct( private readonly Config $config ) {
	}

	/**
	 * What a column holds, from its name and type unless it says.
	 *
	 * @param array<string, mixed> $column The column.
	 *
	 * @return string|null The kind, or null when it is left to BerlinDB.
	 * @throws GeneratorException When the column names a kind nobody configured.
	 * @since 1.0.0
	 */
	public function of( array $column ): ?string {
		if ( Value::truthy( $column['kind'] ?? null ) ) {
			$kind = (string) $column['kind'];

			if ( null === $this->config->kind( $kind ) ) {
				$known = $this->config->kind_names();
				sort( $known );

				throw new GeneratorException( "{$column['name']}: there is no kind '{$kind}'. One of: " . implode( ', ', $known ) . '.' );
			}

			return $kind;
		}

		// BerlinDB stamps these itself and checks a uuid's own format; a
		// callback here would only be in the way.
		foreach ( [ 'created', 'modified', 'uuid', 'extra' ] as $flag ) {
			if ( Value::truthy( $column[ $flag ] ?? null ) ) {
				return null;
			}
		}

		foreach ( (array) $this->config->get( 'rules' ) as $rule ) {
			if ( self::matches( (array) $rule, $column ) ) {
				return isset( $rule['kind'] ) ? (string) $rule['kind'] : null;
			}
		}

		return null;
	}

	/**
	 * The PHP for a column's `validate`, and the adapter it needs if any.
	 *
	 * BerlinDB hands a callback the value and nothing else, so a function
	 * that also wants the column's length cannot be named directly. Those
	 * columns get a one-line static method on their Schema class --
	 * shape_{column}() -- that passes the length on, and the column names
	 * that.
	 *
	 * @param array<string, mixed> $column The column.
	 *
	 * @return array{0: string|null, 1: array{0: string, 1: string, 2: string}|null}
	 *         The `validate` value, and [ column, function, length ] for an adapter.
	 * @throws GeneratorException When the column names a kind nobody configured.
	 * @since 1.0.0
	 */
	public function shaper( array $column ): array {
		$sanitize = $column['sanitize'] ?? null;

		if ( false === $sanitize ) {
			return [ null, null ];
		}

		if ( Value::truthy( $sanitize ) ) {
			return [ "'" . Value::text( $sanitize ) . "'", null ];
		}

		$kind = $this->of( $column );
		$spec = null === $kind ? null : $this->config->kind( $kind );

		if ( null === $spec ) {
			return [ null, null ];
		}

		[ $function, $wants_length ] = $spec;

		$length = $column['length'] ?? null;

		if ( $wants_length && Value::truthy( $length ) && ! in_array( $column['type'], (array) $this->config->get( 'texts' ), true ) ) {
			return [
				"array( self::class, 'shape_{$column['name']}' )",
				[ (string) $column['name'], $function, Value::text( $length ) ],
			];
		}

		return [ "'{$function}'", null ];
	}

	/**
	 * Whether a rule applies to a column.
	 *
	 * @param array<string, mixed> $rule   The rule.
	 * @param array<string, mixed> $column The column.
	 *
	 * @return bool
	 * @since 1.0.0
	 */
	public static function matches( array $rule, array $column ): bool {
		$name = (string) ( $column['name'] ?? '' );

		if ( isset( $rule['types'] ) && ! in_array( $column['type'] ?? null, (array) $rule['types'], true ) ) {
			return false;
		}

		if ( array_key_exists( 'length', $rule ) && ( $column['length'] ?? null ) !== $rule['length'] ) {
			return false;
		}

		$tests = array_intersect_key( $rule, array_flip( [ 'names', 'prefixes', 'suffixes', 'patterns' ] ) );

		if ( [] === $tests ) {
			return true;
		}

		if ( in_array( $name, (array) ( $rule['names'] ?? [] ), true ) ) {
			return true;
		}

		foreach ( (array) ( $rule['prefixes'] ?? [] ) as $prefix ) {
			if ( str_starts_with( $name, (string) $prefix ) ) {
				return true;
			}
		}

		foreach ( (array) ( $rule['suffixes'] ?? [] ) as $suffix ) {
			if ( str_ends_with( $name, (string) $suffix ) ) {
				return true;
			}
		}

		foreach ( (array) ( $rule['patterns'] ?? [] ) as $pattern ) {
			if ( 1 === preg_match( (string) $pattern, $name ) ) {
				return true;
			}
		}

		return false;
	}
}
