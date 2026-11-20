<?php
/**
 * The BerlinDB classes behind each table.
 *
 * @package     SugarCommerce\Schema
 * @copyright   Copyright (c) 2026, ArrayPress Limited
 * @license     GPL-2.0-or-later
 * @since       1.0.0
 */

declare( strict_types=1 );

namespace SugarCommerce\Schema\Render;

use SugarCommerce\Schema\Config;
use SugarCommerce\Schema\Support\Value;

/**
 * Writes the Schema, Table, Query and Row classes, and the class that
 * registers them.
 *
 * Written against BerlinDB 3.x, which differs from 2.x in three ways that
 * all fail silently if you get them wrong:
 *
 *   * Classes live in BerlinDB\Database\Kern\*. The old names are a legacy
 *     alias shim resolved through a hardcoded string map.
 *   * Table::$schema is a Schema *class name*, and CREATE TABLE is built
 *     from that object. Table::set_schema() is private, so a subclass
 *     declaring one is writing a method nothing calls -- and the table is
 *     never created.
 *   * PRIMARY is not derived from a column's 'primary' flag. It has to be an
 *     explicit entry in the Schema's $indexes or the table has no primary
 *     key.
 *
 * The output is already in the shape the WordPress coding standards' fixer
 * leaves it in -- arrows aligned within each array -- so running the fixer
 * over it changes nothing.
 *
 * @since 1.0.0
 */
final class Classes {

	/**
	 * What a column's kind is worked out by.
	 *
	 * @since 1.0.0
	 */
	private readonly Kinds $kinds;

	/**
	 * How columns and indexes are written.
	 *
	 * @since 1.0.0
	 */
	private readonly Columns $columns;

	/**
	 * Constructor.
	 *
	 * @param Config $config The settings.
	 *
	 * @since 1.0.0
	 */
	public function __construct( private readonly Config $config ) {
		$this->kinds   = new Kinds( $config );
		$this->columns = new Columns( $config, $this->kinds );
	}

	/**
	 * Every file for one table.
	 *
	 * @param string               $plural  The table's name, which is its plural.
	 * @param array<string, mixed> $table   Its definition.
	 * @param string               $version Its version.
	 *
	 * @return array<string, string> Path, relative to the root, => contents.
	 * @throws GeneratorException When a column names a kind nobody configured.
	 * @since 1.0.0
	 */
	public function build( string $plural, array $table, string $version ): array {
		$dir    = $this->config->get( 'paths' )['classes'];
		$ns     = $this->config->get( 'namespace' );
		$sing   = (string) $table['singular'];
		$single = self::studly( $sing );
		$many   = self::studly( $plural );
		$why    = Value::truthy( $table['why'] ?? null ) ? Value::comment( Value::text( $table['why'] ) ) : ucfirst( str_replace( '_', ' ', $plural ) ) . '.';
		$out    = [];

		$out[ "{$dir}/Schemas/{$many}.php" ] = $this->schema_class( $many, $why, Columns::base( $table ), Columns::indexes( $table ) );
		$out[ "{$dir}/Tables/{$many}.php" ]  = $this->table_class( $many, $plural, $why, self::fqn_literal( "{$ns}\\Schemas\\{$many}" ), $version );

		if ( Value::truthy( $table['meta'] ?? false ) ) {
			$key     = $this->config->get( 'prefix' ) . "_{$sing}_id";
			$columns = Columns::meta( $key );
			$indexes = Columns::meta_indexes( $key );

			$out[ "{$dir}/Schemas/{$single}Meta.php" ] = $this->schema_class( "{$single}Meta", "Columns for the {$sing}meta table.", $columns, $indexes );
			$out[ "{$dir}/Tables/{$single}Meta.php" ]  = $this->table_class( "{$single}Meta", "{$sing}meta", "Arbitrary metadata for a {$sing}.", self::fqn_literal( "{$ns}\\Schemas\\{$single}Meta" ), (string) $this->config->get( 'initial_version' ) );
		}

		$out[ "{$dir}/Queries/{$single}.php" ] = $this->query_class( $plural, $table );
		$out[ "{$dir}/Rows/{$single}.php" ]    = $this->row_class( $table );

		return $out;
	}

	/**
	 * The class that registers every component.
	 *
	 * @param array<string, array<string, mixed>> $tables The tables, by plural.
	 *
	 * @return array{0: string, 1: string} The path and its contents.
	 * @since 1.0.0
	 */
	public function components( array $tables ): array {
		$ns       = $this->config->get( 'namespace' );
		$settings = $this->config->get( 'components' );
		$class    = (string) $settings['class'];
		$register = (string) $this->config->get( 'register_function' );
		$regs     = [];

		foreach ( $tables as $plural => $table ) {
			$single = self::studly( (string) $table['singular'] );
			$many   = self::studly( $plural );
			$meta   = Value::truthy( $table['meta'] ?? false ) ? "Tables\\{$single}Meta::class" : 'false';

			$regs[] = "\t\t{$register}(\n"
				. "\t\t\t'{$table['singular']}',\n"
				. "\t\t\tarray(\n"
				. "\t\t\t\t'schema' => Schemas\\{$many}::class,\n"
				. "\t\t\t\t'table'  => Tables\\{$many}::class,\n"
				. "\t\t\t\t'query'  => Queries\\{$single}::class,\n"
				. "\t\t\t\t'row'    => Rows\\{$single}::class,\n"
				. "\t\t\t\t'meta'   => {$meta},\n"
				. "\t\t\t)\n"
				. "\t\t);";
		}

		$body = $this->head( "Registering this plugin's own components.", "{$ns}\\Components", "{$ns}\\Queries" )
			. strtr(
				Template::get( 'components' ),
				[
					'{ns}'      => $ns,
					'{summary}' => (string) $settings['summary'],
					'{schema}'  => (string) $this->config->get( 'schema' ),
					'{class}'   => $class,
					'{action}'  => (string) $this->config->get( 'register_action' ),
					'{body}'    => implode( "\n\n", $regs ),
				]
			);

		return [ $this->config->get( 'paths' )['classes'] . "/Components/{$class}.php", $this->since( $body ) ];
	}

	/**
	 * `order_item` as `OrderItem`.
	 *
	 * @param string $name A snake_case name.
	 *
	 * @return string
	 * @since 1.0.0
	 */
	public static function studly( string $name ): string {
		return implode( '', array_map( static fn( string $word ): string => ucfirst( strtolower( $word ) ), explode( '_', $name ) ) );
	}

	/**
	 * The file header every class shares.
	 *
	 * @param string $desc What the file is.
	 * @param string $ns   Its namespace.
	 * @param string $use  The one class it imports.
	 *
	 * @return string
	 * @since 1.0.0
	 */
	private function head( string $desc, string $ns, string $use ): string {
		return Header::file( $this->config, $desc, $ns ) . "\nnamespace {$ns};\n\nuse {$use};\n\ndefined( 'ABSPATH' ) || exit;\n";
	}

	/**
	 * A Schema class.
	 *
	 * @param string                           $name    The class.
	 * @param string                           $desc    What the table is for.
	 * @param array<int, array<string, mixed>> $columns The columns.
	 * @param array<int, array<string, mixed>> $indexes The indexes.
	 *
	 * @return string
	 * @throws GeneratorException When a column names a kind nobody configured.
	 * @since 1.0.0
	 */
	private function schema_class( string $name, string $desc, array $columns, array $indexes ): string {
		$base = $this->config->get( 'bases' )['schema'];

		return $this->since(
			$this->head( $desc, $this->config->get( 'namespace' ) . '\\Schemas', $base ) . strtr(
				Template::get( 'schema' ),
				[
					'{desc}'     => $desc,
					'{schema}'   => (string) $this->config->get( 'schema' ),
					'{command}'  => (string) $this->config->get( 'command' ),
					'{name}'     => $name,
					'{base}'     => Config::short( $base ),
					'{columns}'  => implode( "\n", array_map( [ $this->columns, 'column_php' ], $columns ) ),
					'{indexes}'  => implode( "\n", array_map( [ Columns::class, 'index_php' ], $indexes ) ),
					'{adapters}' => $this->adapters( $columns ),
				]
			)
		);
	}

	/**
	 * The shape_{column}() methods a Schema class needs.
	 *
	 * @param array<int, array<string, mixed>> $columns The columns.
	 *
	 * @return string
	 * @throws GeneratorException When a column names a kind nobody configured.
	 * @since 1.0.0
	 */
	private function adapters( array $columns ): string {
		$out = '';

		foreach ( $columns as $column ) {
			[ , $adapter ] = $this->kinds->shaper( $column );

			if ( null === $adapter ) {
				continue;
			}

			[ $name, $function, $length ] = $adapter;

			$out .= strtr(
				Template::get( 'adapter' ),
				[
					'{name}'     => $name,
					'{function}' => $function,
					'{length}'   => $length,
				]
			);
		}

		return $out;
	}

	/**
	 * A Table class.
	 *
	 * @param string $name    The class.
	 * @param string $table   The table's name, unprefixed.
	 * @param string $desc    What it is for.
	 * @param string $schema  The Schema class, as a PHP string literal's contents.
	 * @param string $version Its version.
	 *
	 * @return string
	 * @since 1.0.0
	 */
	private function table_class( string $name, string $table, string $desc, string $schema, string $version ): string {
		$base = $this->config->get( 'bases' )['table'];

		return $this->since(
			$this->head( $desc, $this->config->get( 'namespace' ) . '\\Tables', $base ) . strtr(
				Template::get( 'table' ),
				[
					'{desc}'        => $desc,
					'{name}'        => $name,
					'{base}'        => Config::short( $base ),
					'{unprefixed}'  => $table,
					'{prefix}'      => (string) $this->config->get( 'prefix' ),
					'{schema}'      => $schema,
					'{schema_file}' => (string) $this->config->get( 'schema' ),
					'{version}'     => $version,
				]
			)
		);
	}

	/**
	 * A Query class.
	 *
	 * @param string               $plural The table's name.
	 * @param array<string, mixed> $table  Its definition.
	 *
	 * @return string
	 * @since 1.0.0
	 */
	private function query_class( string $plural, array $table ): string {
		$ns     = $this->config->get( 'namespace' );
		$prefix = (string) $this->config->get( 'prefix' );
		$base   = $this->config->get( 'bases' )['query'];
		$sing   = (string) $table['singular'];
		$single = self::studly( $sing );

		return $this->since(
			$this->head( "Querying {$plural}.", "{$ns}\\Queries", $base ) . strtr(
				Template::get( 'query' ),
				[
					'{plural}'   => $plural,
					'{single}'   => $single,
					'{base}'     => Config::short( $base ),
					'{prefix}'   => $prefix,
					'{sing}'     => $sing,
					'{alias}'    => Value::text( $table['alias'] ?? '' ),
					'{schema}'   => self::fqn_literal( "{$ns}\\Schemas\\" . self::studly( $plural ) ),
					'{shape}'    => self::fqn_literal( "{$ns}\\Rows\\{$single}" ),
					'{defaults}' => $this->insert_defaults( $table ),
				]
			)
		);
	}

	/**
	 * The columns a new row is given a value for by a function.
	 *
	 * A column's `default` goes into CREATE TABLE, where it has to be a
	 * literal, and BerlinDB runs it through the column's own callback while
	 * the Schema is being built -- so it cannot be "whatever the store's
	 * currency is today". A column that wants that names a function in
	 * "default_function", and the name is written here, where the base
	 * Query's add_item() calls it for a new row that left the column empty.
	 *
	 * @param array<string, mixed> $table The table.
	 *
	 * @return string
	 * @since 1.0.0
	 */
	private function insert_defaults( array $table ): string {
		$pairs = [];

		foreach ( (array) ( $table['columns'] ?? [] ) as $column ) {
			if ( Value::truthy( $column['default_function'] ?? null ) ) {
				$pairs[ (string) $column['name'] ] = Value::text( $column['default_function'] );
			}
		}

		if ( [] === $pairs ) {
			return '';
		}

		$width = max( array_map( static fn( $name ): int => strlen( (string) $name ), array_keys( $pairs ) ) ) + 2;
		$rows  = [];

		foreach ( $pairs as $name => $function ) {
			$rows[] = "\t\t" . str_pad( "'{$name}'", $width ) . " => '{$function}',";
		}

		return strtr(
			Template::get( 'insert-defaults' ),
			[
				'{schema}' => (string) $this->config->get( 'schema' ),
				'{rows}'   => implode( "\n", $rows ),
			]
		);
	}

	/**
	 * A Row class.
	 *
	 * What a row can be asked for beyond its own columns -- an order's
	 * items, a product's prices -- is written by hand in a trait beside the
	 * rows, and used here when there is one. The row file is rewritten on
	 * every run; the trait never is.
	 *
	 * @param array<string, mixed> $table The table.
	 *
	 * @return string
	 * @since 1.0.0
	 */
	private function row_class( array $table ): string {
		$sing   = (string) $table['singular'];
		$single = self::studly( $sing );
		$base   = $this->config->get( 'bases' )['row'];
		$props  = [];

		foreach ( Columns::base( $table ) as $column ) {
			$props[] = "\t/**\n\t * @var mixed\n\t * @since 1.0.0\n\t */\n\tpublic \${$column['name']};\n";
		}

		$concern = is_file( $this->config->path( $this->config->get( 'paths' )['concerns'] . "/{$single}.php" ) )
			? "\n\tuse Concerns\\{$single};\n"
			: '';

		return $this->since(
			$this->head( "A single {$sing}.", $this->config->get( 'namespace' ) . '\\Rows', $base ) . strtr(
				Template::get( 'row' ),
				[
					'{sing}'    => $sing,
					'{single}'  => $single,
					'{base}'    => Config::short( $base ),
					'{concern}' => $concern,
					'{props}'   => implode( "\n", $props ),
				]
			)
		);
	}

	/**
	 * A class name as the contents of a single-quoted PHP string, with its
	 * leading backslash: `\\Plugin\\Database\\Schemas\\Orders`.
	 *
	 * @param string $class The class name.
	 *
	 * @return string
	 * @since 1.0.0
	 */
	private static function fqn_literal( string $class ): string {
		return '\\\\' . str_replace( '\\', '\\\\', trim( $class, '\\' ) );
	}

	/**
	 * The configured version in every `@since`.
	 *
	 * @param string $text A file.
	 *
	 * @return string
	 * @since 1.0.0
	 */
	private function since( string $text ): string {
		return Header::since( $this->config, $text );
	}
}
