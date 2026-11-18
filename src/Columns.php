<?php
/**
 * Columns and indexes, as a Schema class declares them.
 *
 * @package     SugarCommerce\Schema
 * @copyright   Copyright (c) 2026, ArrayPress Limited
 * @license     GPL-2.0-or-later
 * @since       1.0.0
 */

declare( strict_types=1 );

namespace SugarCommerce\Schema;

/**
 * The columns and indexes every table has, and how each is written.
 *
 * The output is already in the shape the WordPress coding standards' fixer
 * leaves it in -- arrows aligned within each array -- so running the fixer
 * over it changes nothing.
 *
 * @since 1.0.0
 */
final class Columns {

	/**
	 * Column flags written as `=> true`, in the order they are written.
	 *
	 * @since 1.0.0
	 */
	private const FLAGS = [ 'unsigned', 'primary', 'searchable', 'sortable', 'allow_null', 'uuid', 'date_query', 'compare', 'cache_key', 'created', 'modified' ];

	/**
	 * Constructor.
	 *
	 * @param Config $config The settings.
	 * @param Kinds  $kinds  What works out a column's callback.
	 *
	 * @since 1.0.0
	 */
	public function __construct( private readonly Config $config, private readonly Kinds $kinds ) {
	}

	/**
	 * The columns every table has, around its own.
	 *
	 * No 'primary' flag on the id column: BerlinDB 3.0 counts a primary
	 * column AND a primary index as two primary keys and rejects the schema,
	 * and only the index actually emits PRIMARY KEY in the DDL. Query's
	 * get_primary_column_name() falls back to 'id', which every table uses.
	 *
	 * @param array<string, mixed> $table The table.
	 *
	 * @return array<int, array<string, mixed>>
	 * @since 1.0.0
	 */
	public static function base( array $table ): array {
		$columns = [
			[
				'name'      => 'id',
				'type'      => 'bigint',
				'length'    => 20,
				'unsigned'  => true,
				'extra'     => 'auto_increment',
				'sortable'  => true,
				'cache_key' => true,
			],
		];

		foreach ( (array) ( $table['columns'] ?? [] ) as $column ) {
			$columns[] = (array) $column;
		}

		if ( Value::truthy( $table['uuid'] ?? false ) ) {
			$columns[] = [
				'name'    => 'uuid',
				'type'    => 'varchar',
				'length'  => 100,
				'uuid'    => true,
				'default' => '',
			];
		}

		// Last, always. Reading a table means reading its own columns, and two
		// timestamps every table has are noise in the middle of them -- so
		// they go at the end where the eye can skip them. uuid comes before,
		// because it identifies the row rather than describing when it
		// changed.
		//
		// 'created' and 'modified' are what make BerlinDB stamp these on
		// write. Without them the column keeps its default and every row
		// reads 0000-00-00 00:00:00 -- which is also invalid under MySQL
		// strict mode.
		$columns[] = [
			'name'       => 'date_created',
			'type'       => 'datetime',
			'sortable'   => true,
			'date_query' => true,
			'created'    => true,
			'default'    => '',
		];
		$columns[] = [
			'name'       => 'date_modified',
			'type'       => 'datetime',
			'sortable'   => true,
			'date_query' => true,
			'modified'   => true,
			'default'    => '',
		];

		return $columns;
	}

	/**
	 * Every index a table has.
	 *
	 * @param array<string, mixed> $table The table.
	 *
	 * @return array<int, array<string, mixed>>
	 * @since 1.0.0
	 */
	public static function indexes( array $table ): array {
		$out = [
			[
				'name'    => 'primary',
				'columns' => [ 'id' ],
				'type'    => 'primary',
			],
		];

		foreach ( (array) ( $table['unique'] ?? [] ) as $columns ) {
			$out[] = [
				'name'    => implode( '_', (array) $columns ),
				'columns' => (array) $columns,
				'unique'  => true,
			];
		}

		foreach ( (array) ( $table['columns'] ?? [] ) as $column ) {
			if ( Value::truthy( $column['unique'] ?? null ) ) {
				$out[] = [
					'name'    => $column['name'],
					'columns' => [ $column['name'] ],
					'unique'  => true,
				];
			}
		}

		foreach ( (array) ( $table['indexes'] ?? [] ) as $columns ) {
			$out[] = [
				'name'    => implode( '_', (array) $columns ),
				'columns' => (array) $columns,
			];
		}

		foreach ( (array) ( $table['columns'] ?? [] ) as $column ) {
			if ( Value::truthy( $column['index'] ?? null ) ) {
				$out[] = [
					'name'    => $column['name'],
					'columns' => [ $column['name'] ],
				];
			}
		}

		$out[] = [
			'name'    => 'date_created',
			'columns' => [ 'date_created' ],
		];

		return $out;
	}

	/**
	 * A meta table's columns.
	 *
	 * @param string $key The column holding the owning row's id.
	 *
	 * @return array<int, array<string, mixed>>
	 * @since 1.0.0
	 */
	public static function meta( string $key ): array {
		return [
			[
				'name'      => 'meta_id',
				'type'      => 'bigint',
				'length'    => 20,
				'unsigned'  => true,
				'extra'     => 'auto_increment',
				'sortable'  => true,
				'cache_key' => true,
			],
			// "sanitize": false -- these four are written by WordPress's
			// own metadata functions, which serialise and unserialise the
			// value; a callback here on meta_value would be handed a
			// serialised array and asked to tidy it.
			[
				'name'     => $key,
				'type'     => 'bigint',
				'length'   => 20,
				'unsigned' => true,
				'default'  => '0',
				'sanitize' => false,
			],
			// 191 rather than core's 255: BerlinDB 3.0's Index has no
			// prefix length, so a 255 key could not be shortened the way
			// WordPress does it.
			[
				'name'       => 'meta_key',
				'type'       => 'varchar',
				'length'     => 191,
				'allow_null' => true,
				'searchable' => true,
				'sanitize'   => false,
			],
			[
				'name'       => 'meta_value',
				'type'       => 'longtext',
				'allow_null' => true,
				'searchable' => true,
				'sanitize'   => false,
			],
		];
	}

	/**
	 * A meta table's indexes.
	 *
	 * @param string $key The column holding the owning row's id.
	 *
	 * @return array<int, array<string, mixed>>
	 * @since 1.0.0
	 */
	public static function meta_indexes( string $key ): array {
		return [
			[
				'name'    => 'primary',
				'columns' => [ 'meta_id' ],
				'type'    => 'primary',
			],
			[
				'name'    => $key,
				'columns' => [ $key ],
			],
			[
				'name'    => 'meta_key',
				'columns' => [ 'meta_key' ],
			],
		];
	}

	/**
	 * One column, as the Schema class declares it.
	 *
	 * @param array<string, mixed> $column The column.
	 *
	 * @return string
	 * @throws GeneratorException When the column names a kind nobody configured.
	 * @since 1.0.0
	 */
	public function column_php( array $column ): string {
		$texts = (array) $this->config->get( 'texts' );
		$pairs = [
			'name' => "'" . Value::text( $column['name'] ) . "'",
			'type' => "'" . Value::text( $column['type'] ) . "'",
		];

		if ( null !== ( $column['length'] ?? null ) ) {
			$pairs['length'] = "'" . Value::text( $column['length'] ) . "'";
		}

		// A decimal's digits after the point. BerlinDB renders length and
		// scale together as decimal(precision,scale); without it the column
		// is whole numbers and a rate of 1.234 is stored as 1.
		if ( null !== ( $column['scale'] ?? null ) ) {
			$pairs['scale'] = "'" . Value::text( $column['scale'] ) . "'";
		}

		// BerlinDB makes every number unsigned unless told otherwise, so a
		// column that has to hold a negative says `"unsigned": false` -- the
		// one flag whose false has to be written out.
		if ( false === ( $column['unsigned'] ?? null ) ) {
			$pairs['unsigned'] = 'false';
		}

		foreach ( self::FLAGS as $flag ) {
			if ( true === ( $column[ $flag ] ?? null ) ) {
				$pairs[ $flag ] = 'true';
			}
		}

		// A status is a transition column unless it says otherwise, so
		// BerlinDB fires {prefix}_transition_{item}_status whenever one
		// changes. Not part of the table -- MySQL never hears of it -- so it
		// is left out of the fingerprint and raises no version.
		$transition = array_key_exists( 'transition', $column ) ? $column['transition'] : 'status' === $column['name'];

		if ( true === $transition ) {
			$pairs['transition'] = 'true';
		}

		if ( Value::truthy( $column['extra'] ?? null ) ) {
			$pairs['extra'] = "'" . Value::text( $column['extra'] ) . "'";
		}

		if ( array_key_exists( 'default', $column ) && ! in_array( $column['type'], $texts, true ) ) {
			$pairs['default'] = "'" . Value::text( $column['default'] ) . "'";
		}

		[ $validate ] = $this->kinds->shaper( $column );

		if ( null !== $validate ) {
			$pairs['validate'] = $validate;
		}

		return self::block( $pairs );
	}

	/**
	 * One index, as the Schema class declares it.
	 *
	 * @param array<string, mixed> $index The index.
	 *
	 * @return string
	 * @since 1.0.0
	 */
	public static function index_php( array $index ): string {
		$pairs = [
			'name'    => "'" . Value::text( $index['name'] ) . "'",
			'columns' => 'array( ' . implode( ', ', array_map( static fn( $c ): string => "'" . Value::text( $c ) . "'", (array) $index['columns'] ) ) . ' )',
		];

		if ( Value::truthy( $index['type'] ?? null ) ) {
			$pairs['type'] = "'" . Value::text( $index['type'] ) . "'";
		}

		if ( Value::truthy( $index['unique'] ?? null ) ) {
			$pairs['unique'] = 'true';
		}

		return self::block( $pairs );
	}

	/**
	 * An array of key => value lines, the arrows lined up.
	 *
	 * @param array<string, string> $pairs Key => PHP for its value.
	 *
	 * @return string
	 * @since 1.0.0
	 */
	private static function block( array $pairs ): string {
		$width = max( array_map( static fn( $key ): int => strlen( (string) $key ) + 2, array_keys( $pairs ) ) );
		$lines = [];

		foreach ( $pairs as $key => $value ) {
			$lines[] = str_pad( "'{$key}'", $width ) . ' => ' . $value;
		}

		return "\t\tarray(\n\t\t\t" . implode( ",\n\t\t\t", $lines ) . ",\n\t\t),";
	}
}
