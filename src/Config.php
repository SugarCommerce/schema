<?php
/**
 * What a plugin tells the generator about itself.
 *
 * @package     SugarCommerce\Schema
 * @copyright   Copyright (c) 2026, ArrayPress Limited
 * @license     GPL-2.0-or-later
 * @since       1.0.0
 */

declare( strict_types=1 );

namespace SugarCommerce\Schema;

/**
 * The generator's settings, resolved.
 *
 * Read from three places, each over the one before: `extra.schema` in the
 * plugin's composer.json, a `"$config"` object at the top of the schema file,
 * and a file passed with `--config`. Lists replace lists; objects merge key
 * by key.
 *
 * Everything that names the plugin is here and nowhere in the templates: the
 * namespace and prefixes, the classes the generated ones extend, where the
 * files go, what the column kinds are and which function cleans each.
 *
 * @since 1.0.0
 */
final class Config {

	/**
	 * The integer column types.
	 *
	 * @since 1.0.0
	 */
	public const array INTEGERS = [ 'tinyint', 'smallint', 'mediumint', 'int', 'bigint' ];

	/**
	 * The text column types: no default in CREATE TABLE, no length passed on.
	 *
	 * @since 1.0.0
	 */
	public const array TEXTS = [ 'tinytext', 'text', 'mediumtext', 'longtext' ];

	/**
	 * Column keys that say how a value is shaped, not how the table is built.
	 *
	 * Left out of a table's fingerprint: MySQL never hears of them, so saying
	 * a column is a "key" must not raise a version and send BerlinDB looking
	 * for an upgrade there is none of.
	 *
	 * @since 1.0.0
	 */
	public const array NOT_DDL = [ 'kind', 'sanitize', 'default_function', 'transition' ];

	/**
	 * Every setting, with its default.
	 *
	 * @var array<string, mixed>
	 * @since 1.0.0
	 */
	private const array DEFAULTS = [
		'schema'            => 'bin/schema.json',
		'lock'              => '',
		'namespace'         => '',
		'package'           => '',
		'prefix'            => '',
		'function_prefix'   => '',
		'hook_prefix'       => '',
		'register_function' => '',
		'register_action'   => '',
		'default_number'    => '100',
		'kern'              => 'BerlinDB\\Database\\Kern',
		'bases'             => [
			'schema' => '',
			'table'  => '',
			'row'    => '',
			'query'  => '',
		],
		'paths'             => [
			'classes'   => 'src/Database',
			'functions' => 'functions',
			'concerns'  => '',
		],
		'components'        => [
			'class'   => 'Core',
			'summary' => 'The components this plugin ships with.',
		],
		'header'            => [
			'copyright' => '',
			'license'   => 'GPL-2.0-or-later',
			'since'     => '1.0.0',
		],
		'initial_version'   => '1',
		'command'           => 'vendor/bin/schema generate',
		'texts'             => self::TEXTS,
		'kinds'             => [],
		'rules'             => null,
	];

	/**
	 * The project root every path is relative to.
	 *
	 * @since 1.0.0
	 */
	public readonly string $root;

	/**
	 * The resolved settings.
	 *
	 * @var array<string, mixed>
	 * @since 1.0.0
	 */
	private array $values;

	/**
	 * Constructor.
	 *
	 * @param string               $root   The project root.
	 * @param array<string, mixed> $values Settings, over the defaults.
	 *
	 * @throws GeneratorException When a required setting is missing or a kind is malformed.
	 * @since 1.0.0
	 */
	public function __construct( string $root, array $values ) {
		$this->root   = rtrim( $root, '/' );
		$this->values = self::resolve( self::merge( self::DEFAULTS, $values ) );
	}

	/**
	 * Read the settings for a project.
	 *
	 * @param string      $root The project root.
	 * @param string|null $file A settings file over the rest, from `--config`.
	 *
	 * @return self
	 * @throws GeneratorException When a file cannot be read or a setting is missing.
	 * @since 1.0.0
	 */
	public static function load( string $root, ?string $file = null ): self {
		$values   = [];
		$composer = rtrim( $root, '/' ) . '/composer.json';

		if ( is_file( $composer ) ) {
			$extra  = self::read_json( $composer )['extra']['schema'] ?? [];
			$values = is_array( $extra ) ? $extra : [];
		}

		$schema = rtrim( $root, '/' ) . '/' . ( $values['schema'] ?? self::DEFAULTS['schema'] );

		if ( is_file( $schema ) ) {
			$inline = self::read_json( $schema )['$config'] ?? [];
			$values = self::merge( $values, is_array( $inline ) ? $inline : [] );
		}

		if ( null !== $file ) {
			$values = self::merge( $values, self::read_json( $file ) );
		}

		return new self( $root, $values );
	}

	/**
	 * One setting.
	 *
	 * @param string $key The setting.
	 *
	 * @return mixed
	 * @since 1.0.0
	 */
	public function get( string $key ): mixed {
		return $this->values[ $key ] ?? null;
	}

	/**
	 * A path under the project root.
	 *
	 * @param string $relative The path, relative to the root.
	 *
	 * @return string
	 * @since 1.0.0
	 */
	public function path( string $relative ): string {
		return $this->root . '/' . ltrim( $relative, '/' );
	}

	/**
	 * The function and whether it wants the column's length, for a kind.
	 *
	 * @param string $kind The kind.
	 *
	 * @return array{0: string, 1: bool}|null
	 * @since 1.0.0
	 */
	public function kind( string $kind ): ?array {
		return $this->values['kinds'][ $kind ] ?? null;
	}

	/**
	 * Every configured kind's name.
	 *
	 * @return array<int, string>
	 * @since 1.0.0
	 */
	public function kind_names(): array {
		return array_keys( $this->values['kinds'] );
	}

	/**
	 * A class's short name, as it is written after `extends`.
	 *
	 * @param string $class A fully qualified class name.
	 *
	 * @return string
	 * @since 1.0.0
	 */
	public static function short( string $class ): string {
		$parts = explode( '\\', trim( $class, '\\' ) );

		return (string) end( $parts );
	}

	/**
	 * The rules that work out a column's kind when it does not say.
	 *
	 * In order; the first that matches decides. A rule matches when the
	 * column's type is among its `types` (if it names any), its length is
	 * its `length` (if it names one), and its name is one of its `names`,
	 * starts with one of its `prefixes`, ends with one of its `suffixes` or
	 * matches one of its `patterns` (if it names any of those). A `kind` of
	 * null means "no callback".
	 *
	 * @return array<int, array<string, mixed>>
	 * @since 1.0.0
	 */
	public static function default_rules(): array {
		return [
			[
				'kind'  => 'datetime',
				'types' => [ 'datetime' ],
			],
			[
				'kind'   => 'flag',
				'types'  => [ 'tinyint' ],
				'length' => 1,
			],
			[
				'kind'     => 'id',
				'types'    => self::INTEGERS,
				'suffixes' => [ '_id' ],
			],
			[
				'kind'     => 'amount',
				'types'    => self::INTEGERS,
				'names'    => [ 'amount', 'total' ],
				'prefixes' => [ 'amount_' ],
				'suffixes' => [ '_amount', '_fee', '_net' ],
			],
			[
				'kind'  => 'count',
				'types' => self::INTEGERS,
			],
			[
				'kind'     => 'rate',
				'types'    => [ 'decimal' ],
				'suffixes' => [ '_rate' ],
			],
			[
				'kind'  => null,
				'types' => [ 'decimal', 'float', 'double' ],
			],
			[
				'kind'  => 'textarea',
				'types' => self::TEXTS,
			],
			[
				'kind'     => 'currency',
				'names'    => [ 'currency' ],
				'suffixes' => [ '_currency' ],
			],
			[
				'kind'  => 'country',
				'names' => [ 'country' ],
			],
			[
				'kind'  => 'email',
				'names' => [ 'email' ],
			],
			[
				'kind'  => 'ip',
				'names' => [ 'ip' ],
			],
			[
				'kind'     => 'key',
				'names'    => [ 'status', 'type' ],
				'suffixes' => [ '_status', '_type' ],
			],
			[
				'kind' => 'text',
			],
		];
	}

	/**
	 * Fill in what is derived from something else, and check what is required.
	 *
	 * @param array<string, mixed> $v The merged settings.
	 *
	 * @return array<string, mixed>
	 * @throws GeneratorException When a required setting is missing or a kind is malformed.
	 * @since 1.0.0
	 */
	private static function resolve( array $v ): array {
		foreach ( [ 'namespace', 'prefix' ] as $required ) {
			if ( ! is_string( $v[ $required ] ) || '' === $v[ $required ] ) {
				throw new GeneratorException( "No \"{$required}\" is set. Add it to extra.schema in composer.json, or to \"\$config\" in the schema file." );
			}
		}

		$v['namespace'] = trim( $v['namespace'], '\\' );
		$v['kern']      = trim( (string) $v['kern'], '\\' );

		if ( '' === $v['package'] ) {
			$v['package'] = explode( '\\', $v['namespace'] )[0];
		}

		foreach ( [ 'function_prefix', 'hook_prefix' ] as $key ) {
			if ( '' === $v[ $key ] ) {
				$v[ $key ] = $v['prefix'];
			}
		}

		if ( '' === $v['register_function'] ) {
			$v['register_function'] = $v['function_prefix'] . '_register_component';
		}

		if ( '' === $v['register_action'] ) {
			$v['register_action'] = $v['hook_prefix'] . '_register_components';
		}

		foreach ( [
			'schema' => 'Schema',
			'table'  => 'Table',
			'row'    => 'Row',
		] as $key => $class ) {
			if ( '' === $v['bases'][ $key ] ) {
				$v['bases'][ $key ] = $v['kern'] . '\\' . $class;
			}
		}

		// sugarcommerce/data's Query, beside this package: named from this
		// namespace rather than written out, so a build that renames one
		// renames the other.
		if ( '' === $v['bases']['query'] ) {
			$v['bases']['query'] = substr( __NAMESPACE__, 0, (int) strrpos( __NAMESPACE__, '\\' ) ) . '\\Data\\Query';
		}

		foreach ( $v['bases'] as $key => $class ) {
			$v['bases'][ $key ] = trim( (string) $class, '\\' );
		}

		$v['paths']['classes']   = trim( (string) $v['paths']['classes'], '/' );
		$v['paths']['functions'] = trim( (string) $v['paths']['functions'], '/' );

		if ( '' === $v['paths']['concerns'] ) {
			$v['paths']['concerns'] = $v['paths']['classes'] . '/Rows/Concerns';
		}

		if ( '' === $v['lock'] ) {
			$v['lock'] = preg_replace( '/\.json$/', '', (string) $v['schema'] ) . '.lock.json';
		}

		$v['kinds'] = self::kinds( (array) $v['kinds'] );

		if ( null === $v['rules'] ) {
			$v['rules'] = self::default_rules();
		}

		return $v;
	}

	/**
	 * Kinds, each as [ function, wants the length ].
	 *
	 * A kind may be given as the function's name alone, as `[ name, true ]`,
	 * or as `{ "function": name, "length": true }`.
	 *
	 * @param array<string, mixed> $kinds The kinds as configured.
	 *
	 * @return array<string, array{0: string, 1: bool}>
	 * @throws GeneratorException When a kind names no function.
	 * @since 1.0.0
	 */
	private static function kinds( array $kinds ): array {
		$out = [];

		foreach ( $kinds as $kind => $spec ) {
			if ( is_string( $spec ) ) {
				$spec = [ $spec, false ];
			} elseif ( is_array( $spec ) && isset( $spec['function'] ) ) {
				$spec = [ $spec['function'], ! empty( $spec['length'] ) ];
			}

			if ( ! is_array( $spec ) || ! isset( $spec[0] ) || ! is_string( $spec[0] ) || '' === $spec[0] ) {
				throw new GeneratorException( "The kind \"{$kind}\" names no function." );
			}

			$out[ (string) $kind ] = [ $spec[0], ! empty( $spec[1] ) ];
		}

		return $out;
	}

	/**
	 * Settings over settings: objects merge, anything else replaces.
	 *
	 * @param array<mixed> $base The settings underneath.
	 * @param array<mixed> $over The settings on top.
	 *
	 * @return array<mixed>
	 * @since 1.0.0
	 */
	private static function merge( array $base, array $over ): array {
		foreach ( $over as $key => $value ) {
			$under = $base[ $key ] ?? null;

			$base[ $key ] = is_array( $value ) && is_array( $under ) && ! array_is_list( $value ) && ! array_is_list( $under )
				? self::merge( $under, $value )
				: $value;
		}

		return $base;
	}

	/**
	 * A JSON file, as arrays.
	 *
	 * @param string $file The file.
	 *
	 * @return array<mixed>
	 * @throws GeneratorException When it cannot be read or is not JSON.
	 * @since 1.0.0
	 */
	private static function read_json( string $file ): array {
		$raw = is_readable( $file ) ? file_get_contents( $file ) : false; // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- A local file, read by a command-line tool.

		if ( false === $raw ) {
			throw new GeneratorException( "Could not read {$file}." );
		}

		$data = json_decode( $raw, true );

		if ( ! is_array( $data ) ) {
			throw new GeneratorException( "{$file} is not a JSON object: " . json_last_error_msg() );
		}

		return $data;
	}
}
