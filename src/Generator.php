<?php
/**
 * From one schema file to every generated file.
 *
 * @package     SugarCommerce\Schema
 * @copyright   Copyright (c) 2026, ArrayPress Limited
 * @license     GPL-2.0-or-later
 * @since       1.0.0
 */

declare( strict_types=1 );

namespace SugarCommerce\Schema;

use SugarCommerce\Schema\Render\Classes;
use SugarCommerce\Schema\Render\Functions;
use SugarCommerce\Schema\Render\Versions;
use SugarCommerce\Schema\Support\Json;
use SugarCommerce\Schema\Support\Value;

/**
 * Generate the database layer and the CRUD API from the schema.
 *
 * A development tool, never shipped. Thirty tables times four classes is
 * over a hundred files that have to agree with each other about column
 * names, types and defaults -- hand-writing them guarantees they eventually
 * will not. The JSON is the source of truth; run this after editing it.
 *
 * Everything is worked out in memory first and checked before a byte is
 * written, so a run that has to stop leaves every file as it was.
 *
 * @since 1.0.0
 */
final class Generator {

	/**
	 * Names BerlinDB's Query keeps for itself.
	 *
	 * Every column is also a query argument of the same name, and these are
	 * the arguments that say how to query rather than what to match: a column
	 * called `number` turns the generated `_count_` and `_get_` functions'
	 * `'number' => 0` into `WHERE number = ''`. BerlinDB means the control
	 * meaning to win and logs a warning nobody reads; as tested (a22276b),
	 * the filter wins.
	 * Either way the column cannot be asked for by name, so it is refused
	 * here, where the fix is a rename and costs nothing.
	 *
	 * @since 1.0.0
	 */
	public const array RESERVED = [
		'aggregate',
		'by',
		'cache_results',
		'count',
		'criteria',
		'distinct',
		'explain',
		'fields',
		'groupby',
		'having',
		'index_hints',
		'no_found_rows',
		'number',
		'offset',
		'order',
		'orderby',
		'select',
		'update_item_cache',
		'update_meta_cache',
		'with',
	];

	/**
	 * What a table, column, index or alias may be called.
	 *
	 * Each is written into PHP and SQL as it stands -- between quotes, after
	 * a function prefix, as a file name -- so anything but a lowercase
	 * identifier would end the string around it or name a file elsewhere.
	 *
	 * @var string
	 * @since 1.0.0
	 */
	public const string IDENTIFIER = '/^[a-z][a-z0-9_]*$/';

	/**
	 * What a column's type, length and scale may be.
	 *
	 * @var array<string, string>
	 * @since 1.0.0
	 */
	private const array SHAPES = [
		'type'   => '/^[a-z]+$/',
		'length' => '/^[0-9]+$/',
		'scale'  => '/^[0-9]+$/',
	];

	/**
	 * A function a column names: `myshop_get_currency`, `\\MyShop\\currency`.
	 *
	 * @var string
	 * @since 1.0.0
	 */
	private const string FUNCTION_NAME = '/^\\\\?[A-Za-z_][A-Za-z0-9_]*(?:\\\\[A-Za-z_][A-Za-z0-9_]*)*$/';

	/**
	 * A folder of functions under the functions path: `orders`, `shop/orders`.
	 *
	 * @var string
	 * @since 1.0.0
	 */
	private const string FOLDER = '#^[a-z0-9_-]+(?:/[a-z0-9_-]+)*/?$#';


	/**
	 * The tables, as arrays, by plural name.
	 *
	 * @var array<string, array<string, mixed>>
	 * @since 1.0.0
	 */
	private array $tables;

	/**
	 * The same tables as decoded objects, for fingerprinting.
	 *
	 * @var array<string, \stdClass>
	 * @since 1.0.0
	 */
	private array $objects;

	/**
	 * Table versions.
	 *
	 * @since 1.0.0
	 */
	private readonly Versions $versions;

	/**
	 * Constructor.
	 *
	 * @param Config        $config The settings.
	 * @param \Closure|null $today  Today as YYYYMMDD, for tests.
	 *
	 * @throws GeneratorException When the schema cannot be read.
	 * @since 1.0.0
	 */
	public function __construct( private readonly Config $config, ?\Closure $today = null ) {
		$file = $config->path( (string) $config->get( 'schema' ) );
		$raw  = is_readable( $file ) ? file_get_contents( $file ) : false; // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- A local file, read by a command-line tool.

		if ( false === $raw ) {
			throw new GeneratorException( "Could not read the schema at {$file}." );
		}

		$tables  = json_decode( $raw, true );
		$objects = json_decode( $raw, false );

		if ( ! is_array( $tables ) || ! $objects instanceof \stdClass ) {
			throw new GeneratorException( "{$file} is not a JSON object: " . json_last_error_msg() );
		}

		$this->tables  = [];
		$this->objects = [];

		foreach ( $tables as $plural => $table ) {
			// "$config", and any other key starting with a dollar sign, is
			// about the file rather than a table in it.
			if ( str_starts_with( (string) $plural, '$' ) ) {
				continue;
			}

			if ( ! is_array( $table ) || ! isset( $table['singular'], $table['columns'] ) ) {
				throw new GeneratorException( "{$plural} needs at least \"singular\" and \"columns\"." );
			}

			self::check_names( (string) $plural, $table );

			foreach ( (array) $table['columns'] as $column ) {
				$name = (string) ( $column['name'] ?? '' );

				if ( in_array( $name, self::RESERVED, true ) ) {
					throw new GeneratorException( "{$plural}: a column cannot be called \"{$name}\". BerlinDB reads \"{$name}\" as an instruction to the query, so the generated functions would filter on the column instead -- a count of every row comes back nought. Name it for what it holds: \"{$plural}_{$name}\", \"{$table['singular']}_{$name}\"." );
				}
			}

			$this->tables[ (string) $plural ]  = $table;
			$this->objects[ (string) $plural ] = $objects->{$plural};
		}

		$this->versions = new Versions( (string) $config->get( 'initial_version' ), $today );
	}

	/**
	 * The tables, by plural name.
	 *
	 * @return array<string, array<string, mixed>>
	 * @since 1.0.0
	 */
	public function tables(): array {
		return $this->tables;
	}

	/**
	 * Every generated file, without writing any.
	 *
	 * @param array<int, string>|null $only Plural names to write the table
	 *                                      files for, or null for all. The
	 *                                      registering class and the lock
	 *                                      always cover every table.
	 *
	 * @return array{classes: array<string, string>, functions: array<string, string>, lock: array<string, string>}
	 * @throws GeneratorException When a table is unknown or a column names an unknown kind.
	 * @since 1.0.0
	 */
	public function render( ?array $only = null ): array {
		foreach ( (array) $only as $plural ) {
			if ( ! isset( $this->tables[ $plural ] ) ) {
				throw new GeneratorException( "There is no table \"{$plural}\" in the schema." );
			}
		}

		$stamped   = $this->versions->stamp( $this->objects, $this->lock() );
		$classes   = new Classes( $this->config );
		$functions = new Functions( $this->config );
		$out       = [
			'classes'   => [],
			'functions' => [],
			'lock'      => [ (string) $this->config->get( 'lock' ) => Versions::lock_file( $stamped ) ],
		];

		foreach ( $this->tables as $plural => $table ) {
			if ( null !== $only && ! in_array( $plural, $only, true ) ) {
				continue;
			}

			$out['classes'] += $classes->build( $plural, $table, (string) $stamped[ $plural ]['version'] );

			$out['functions'][ $functions->path( $plural, $table ) ] = $functions->build( $plural, $table );
		}

		[ $path, $body ] = $classes->components( $this->tables );

		$out['classes'][ $path ] = $body;

		return $out;
	}

	/**
	 * Write every generated file.
	 *
	 * @param array<int, string>|null $only Plural names, or null for all.
	 *
	 * @return array{files: int, tables: int, meta: int, components: int, functions: int, hooks: int, removed: array<int, string>}
	 * @throws GeneratorException When writing would lose code, or a file cannot be written.
	 * @since 1.0.0
	 */
	public function generate( ?array $only = null ): array {
		$out   = $this->render( $only );
		$guard = new Guard( $this->config );

		foreach ( $out['classes'] as $path => $body ) {
			$guard->no_migrations( $path, $body );
		}

		foreach ( $out['functions'] as $path => $body ) {
			$guard->no_handwriting( $path, $body );
		}

		$stale = [];

		foreach ( $out['functions'] as $path => $body ) {
			$stale = array_merge( $stale, $guard->stale_copies( $path, $body ) );
		}

		foreach ( $out['lock'] + $out['classes'] + $out['functions'] as $path => $body ) {
			$this->write( $path, $body );
		}

		foreach ( $stale as $path ) {
			unlink( $this->config->path( $path ) ); // phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink -- A generated file the plugin no longer uses, removed by a command-line tool.
		}

		$meta = count( array_filter( $this->tables, static fn( array $t ): bool => Value::truthy( $t['meta'] ?? false ) ) );

		return [
			'files'      => count( $out['classes'] ),
			'tables'     => count( $this->tables ) + $meta,
			'meta'       => $meta,
			'components' => count( $this->tables ),
			'functions'  => count( $out['functions'] ),
			'hooks'      => array_sum( array_map( [ Functions::class, 'hooks_in' ], $out['functions'] ) ),
			'removed'    => $stale,
		];
	}

	/**
	 * Every generated file that is not what the schema says it should be.
	 *
	 * For a CI step: a schema edited without regenerating, a generated file
	 * edited by hand, or a lock file left behind.
	 *
	 * @return array<string, string> Path => `missing` or `differs`.
	 * @throws GeneratorException When a column names an unknown kind.
	 * @since 1.0.0
	 */
	public function check(): array {
		$out   = $this->render();
		$drift = [];

		foreach ( $out['lock'] + $out['classes'] + $out['functions'] as $path => $body ) {
			$file = $this->config->path( $path );

			if ( ! is_file( $file ) ) {
				$drift[ $path ] = 'missing';
			} elseif ( file_get_contents( $file ) !== $body ) { // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- A local file, read by a command-line tool.
				$drift[ $path ] = 'differs';
			}
		}

		return $drift;
	}

	/**
	 * The lock file as it stands.
	 *
	 * @return array<string, array<string, mixed>>
	 * @since 1.0.0
	 */
	private function lock(): array {
		$file = $this->config->path( (string) $this->config->get( 'lock' ) );

		if ( ! is_file( $file ) ) {
			return [];
		}

		$lock = json_decode( (string) file_get_contents( $file ), true ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- A local file, read by a command-line tool.

		return is_array( $lock ) ? $lock : [];
	}

	/**
	 * Refuse a name that would not stay a name in the code written from it.
	 *
	 * The schema file is the plugin's own, but what is in it is written
	 * into PHP strings, SQL and file paths without further quoting, so a
	 * stray quote, a type with a statement after it or a folder of `..`
	 * is stopped here, before anything is written.
	 *
	 * @param string               $plural The table's key.
	 * @param array<string, mixed> $table  Its entry.
	 *
	 * @return void
	 * @throws GeneratorException When a name is not one.
	 * @since 1.0.0
	 */
	private static function check_names( string $plural, array $table ): void {
		self::refuse_unless( $plural, 'the table', $plural, self::IDENTIFIER );
		self::refuse_unless( $plural, '"singular"', $table['singular'], self::IDENTIFIER );
		self::refuse_unless( $plural, '"alias"', $table['alias'] ?? null, self::IDENTIFIER );
		self::refuse_unless( $plural, '"functions_dir"', $table['functions_dir'] ?? null, self::FOLDER );

		foreach ( (array) $table['columns'] as $column ) {
			$column = (array) $column;

			self::refuse_unless( $plural, 'a column name', $column['name'] ?? null, self::IDENTIFIER );

			foreach ( self::SHAPES as $key => $pattern ) {
				self::refuse_unless( $plural, "\"{$key}\"", $column[ $key ] ?? null, $pattern );
			}

			foreach ( [ 'default_function', 'sanitize' ] as $key ) {
				if ( is_string( $column[ $key ] ?? null ) ) {
					self::refuse_unless( $plural, "\"{$key}\"", $column[ $key ], self::FUNCTION_NAME );
				}
			}
		}

		foreach ( [ 'indexes', 'unique' ] as $key ) {
			foreach ( (array) ( $table[ $key ] ?? [] ) as $columns ) {
				foreach ( (array) $columns as $name ) {
					self::refuse_unless( $plural, "a column in \"{$key}\"", $name, self::IDENTIFIER );
				}
			}
		}
	}

	/**
	 * One value against its pattern. Not given, or empty, passes.
	 *
	 * @param string $plural  The table's key.
	 * @param string $what    What it is, for the message.
	 * @param mixed  $value   The value.
	 * @param string $pattern What it must match.
	 *
	 * @return void
	 * @throws GeneratorException When it does not match.
	 * @since 1.0.0
	 */
	private static function refuse_unless( string $plural, string $what, mixed $value, string $pattern ): void {
		if ( null === $value || '' === $value ) {
			return;
		}

		if ( ! is_scalar( $value ) || 1 !== preg_match( $pattern, (string) $value ) ) {
			throw new GeneratorException( sprintf( '%s: %s %s is not allowed -- it is written into the generated PHP and SQL as it stands. Use lowercase letters, digits and underscores.', $plural, $what, Json::encode( is_scalar( $value ) ? $value : get_debug_type( $value ) ) ) );
		}
	}

	/**
	 * Write one file, making its folder if need be.
	 *
	 * @param string $path Relative to the root.
	 * @param string $body Its contents.
	 *
	 * @return void
	 * @throws GeneratorException When it cannot be written.
	 * @since 1.0.0
	 */
	private function write( string $path, string $body ): void {
		$file = $this->config->path( $path );
		$dir  = dirname( $file );

		if ( ! is_dir( $dir ) && ! mkdir( $dir, 0755, true ) && ! is_dir( $dir ) ) { // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_mkdir -- A command-line tool writing into the plugin it was run in.
			throw new GeneratorException( "Could not make {$dir}." );
		}

		if ( false === file_put_contents( $file, $body ) ) { // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- A command-line tool writing into the plugin it was run in.
			throw new GeneratorException( "Could not write {$file}." );
		}
	}
}
