<?php
/**
 * Compare every table to the schema that describes it.
 *
 * @package     SugarCommerce\Schema
 * @copyright   Copyright (c) 2026, ArrayPress Limited
 * @license     GPL-2.0-or-later
 * @since       1.0.0
 */

declare( strict_types=1 );

namespace SugarCommerce\Schema;

/**
 * Live tables against their Schema classes.
 *
 * BerlinDB 3.1 adds the columns and indexes a table is missing when its
 * version rises. It never removes one and never changes a type, and a table
 * whose version option says it is current is never looked at -- so a dev
 * database that has been through a few renames drifts, and says nothing
 * until something tries to write.
 *
 *     schema reconcile                          report what has drifted
 *     schema reconcile --apply                  add what is missing, put columns in order
 *     schema reconcile --apply --drop           and remove what is no longer declared
 *     ... --force                               drop even when the column has values
 *
 * --drop is separate because it destroys data, and the two cases are not
 * equally safe: adding a column the code already expects only makes the
 * table match, while dropping one throws away whatever is in it.
 *
 * phpcs:disable WordPress.Security.EscapeOutput.OutputNotEscaped -- Plain text for a terminal, never a web page.
 * phpcs:disable WordPress.DB.DirectDatabaseQuery -- Schema maintenance: DDL against the live tables is the whole job.
 * phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared -- Table and column names come from SHOW TABLES and the generated Schema classes, never from input, and are backquoted.
 *
 * @since 1.0.0
 */
final class Reconciler {

	/**
	 * Constructor.
	 *
	 * @param Config $config The settings.
	 * @param object $wpdb   WordPress's database object.
	 *
	 * @since 1.0.0
	 */
	public function __construct( private readonly Config $config, private readonly object $wpdb ) {
	}

	/**
	 * Where WordPress's wp-load.php is.
	 *
	 * The folder given, or the nearest one above the plugin that has it.
	 *
	 * @param string      $root The plugin folder.
	 * @param string|null $wp   A WordPress folder, from `--wp`.
	 *
	 * @return string
	 * @throws GeneratorException When there is none.
	 * @since 1.0.0
	 */
	public static function find_wordpress( string $root, ?string $wp = null ): string {
		if ( null !== $wp ) {
			$file = rtrim( $wp, '/' ) . '/wp-load.php';

			if ( is_file( $file ) ) {
				return $file;
			}

			throw new GeneratorException( "There is no wp-load.php in {$wp}." );
		}

		$dir = realpath( $root );

		while ( false !== $dir && '' !== $dir ) {
			if ( is_file( $dir . '/wp-load.php' ) ) {
				return $dir . '/wp-load.php';
			}

			$up = dirname( $dir );

			if ( $up === $dir ) {
				break;
			}

			$dir = $up;
		}

		throw new GeneratorException( "Could not find WordPress above {$root}. Pass --wp=path/to/wordpress." );
	}

	/**
	 * Every declared column in a Schema file, with enough of its definition
	 * to build an ALTER.
	 *
	 * Read from the file rather than the class: the generated file is what
	 * the plugin ships, and reading it needs no autoloader and no BerlinDB.
	 *
	 * @param string $source The Schema file's contents.
	 *
	 * @return array<string, array<string, string>>
	 * @since 1.0.0
	 */
	public static function declared_definitions( string $source ): array {
		// Each array( ... ) block that carries a 'type' is a column; the ones
		// carrying 'columns' are indexes.
		preg_match_all( '/array\(\s*(.*?)\s*\),\n/s', $source, $blocks );

		$columns = [];

		foreach ( $blocks[1] as $block ) {
			// The key, not the word. A column can be called `type`, and an
			// index over it lists 'type' among its columns -- which read as
			// a column named after the index, reported missing for ever.
			if ( ! preg_match( "/'type'\s*=>/", $block ) ) {
				continue;
			}

			preg_match_all( "/'([a-z_]+)'\s*=>\s*(?:'([^']*)'|([a-zA-Z0-9_]+))/", $block, $pairs, PREG_SET_ORDER );

			$definition = [];

			foreach ( $pairs as $pair ) {
				$definition[ $pair[1] ] = '' !== $pair[2] ? $pair[2] : ( $pair[3] ?? '' );
			}

			if ( isset( $definition['name'] ) ) {
				$columns[ $definition['name'] ] = $definition;
			}
		}

		return $columns;
	}

	/**
	 * Where a column belongs, as an AFTER clause.
	 *
	 * The nearest column before it that the table already has -- walking back
	 * rather than taking the immediately preceding declared one, because that
	 * one may itself be missing and about to be added.
	 *
	 * @param array<int, string> $want   The declared order.
	 * @param array<int, string> $live   What the table has.
	 * @param string             $column The column being placed.
	 *
	 * @return string
	 * @since 1.0.0
	 */
	public static function column_after( array $want, array $live, string $column ): string {
		$index = array_search( $column, $want, true );

		if ( false === $index ) {
			return '';
		}

		for ( $i = $index - 1; $i >= 0; $i-- ) {
			if ( in_array( $want[ $i ], $live, true ) ) {
				return 'AFTER `' . $want[ $i ] . '`';
			}
		}

		return 'FIRST';
	}

	/**
	 * One column, as MySQL wants it declared.
	 *
	 * @param array<string, string> $column The definition.
	 *
	 * @return string
	 * @since 1.0.0
	 */
	public static function column_sql( array $column ): string {
		$sql = '`' . $column['name'] . '` ' . $column['type'];

		if ( isset( $column['length'] ) && '' !== $column['length'] ) {
			// A decimal carries its digits after the point as a second figure.
			$scale = isset( $column['scale'] ) && '' !== $column['scale'] ? ',' . $column['scale'] : '';

			$sql .= '(' . $column['length'] . $scale . ')';
		}

		if ( isset( $column['unsigned'] ) && 'false' !== $column['unsigned'] ) {
			$sql .= ' unsigned';
		}

		$nullable = isset( $column['allow_null'] ) && 'false' !== $column['allow_null'];

		$sql .= $nullable ? ' NULL' : ' NOT NULL';

		$default = $column['default'] ?? null;

		// A schema says default '' and means "whatever an empty one is". For
		// a datetime that is not the empty string -- MySQL refuses it outright
		// under strict mode -- it is the zero date, which is what BerlinDB
		// creates the column with. A text column cannot carry a default.
		if ( in_array( $column['type'], [ 'datetime', 'timestamp', 'date' ], true ) && '' === $default ) {
			$default = '0000-00-00 00:00:00';
		}

		if ( in_array( $column['type'], [ 'tinytext', 'text', 'mediumtext', 'longtext' ], true ) ) {
			$default = null;
		}

		if ( null !== $default ) {
			$sql .= " DEFAULT '" . $default . "'";
		} elseif ( $nullable ) {
			$sql .= ' DEFAULT NULL';
		}

		return $sql;
	}

	/**
	 * Each table this plugin declares, unprefixed, => its Schema file.
	 *
	 * From the schema, so a meta table's name (`ordermeta`) finds its class
	 * (`OrderMeta`) on a case-sensitive filesystem too.
	 *
	 * @return array<string, string>
	 * @since 1.0.0
	 */
	public function schema_files(): array {
		$dir   = $this->config->path( $this->config->get( 'paths' )['classes'] . '/Schemas' );
		$files = [];

		foreach ( ( new Generator( $this->config ) )->tables() as $plural => $table ) {
			$files[ $plural ] = $dir . '/' . Classes::studly( $plural ) . '.php';

			if ( Value::truthy( $table['meta'] ?? false ) ) {
				$files[ $table['singular'] . 'meta' ] = $dir . '/' . Classes::studly( (string) $table['singular'] ) . 'Meta.php';
			}
		}

		return $files;
	}

	/**
	 * Report, and with $apply fix, every drifted table.
	 *
	 * @param bool $apply Add missing columns and put them in order.
	 * @param bool $drop  Remove columns and empty tables nothing declares.
	 * @param bool $force Drop a column even when it holds values.
	 *
	 * @return int 1 when anything had drifted, was missing or was left over, else 0.
	 * @since 1.0.0
	 */
	public function run( bool $apply, bool $drop, bool $force ): int {
		$db      = $this->wpdb;
		$files   = $this->schema_files();
		$prefix  = $db->prefix . $this->config->get( 'prefix' ) . '_';
		$tables  = (array) $db->get_col( $db->prepare( 'SHOW TABLES LIKE %s', $db->esc_like( $prefix ) . '%' ) );
		$added   = 0;
		$dropped = 0;
		$moved   = 0;
		$orphans = [];
		$drifted = 0;

		foreach ( $tables as $table ) {
			$short = substr( $table, strlen( $prefix ) );
			$file  = $files[ $short ] ?? '';

			if ( '' === $file || ! is_file( $file ) ) {
				$orphans[] = $short;
				continue;
			}

			$definitions = self::declared_definitions( (string) file_get_contents( $file ) ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- A local file, read by a command-line tool.
			$want        = array_keys( $definitions );
			$live        = (array) $db->get_col( "SHOW COLUMNS FROM `{$table}`" );

			$missing = array_values( array_diff( $want, $live ) );
			$extra   = array_values( array_diff( $live, $want ) );

			// A meta table's key may be left to BerlinDB by a Schema class
			// written by hand. Then it is neither extra nor out of order.
			$unlisted = in_array( 'meta_id', $want, true ) ? [] : [ 'meta_id' ];
			$extra    = array_values( array_diff( $extra, $unlisted ) );

			// Order, once presence is settled. Two tables holding the same
			// columns in a different order are the same table to MySQL and a
			// different one to anybody reading it.
			$ordered  = array_values( array_diff( $want, $extra ) );
			$current  = array_values( array_diff( $live, $extra, $missing, $unlisted ) );
			$shuffled = [];

			if ( ! $missing && $ordered !== $current ) {
				foreach ( $ordered as $index => $column ) {
					if ( ( $current[ $index ] ?? '' ) === $column ) {
						continue;
					}

					$shuffled[] = $column;
				}
			}

			if ( ! $missing && ! $extra && ! $shuffled ) {
				continue;
			}

			++$drifted;

			echo $short, "\n";

			foreach ( $missing as $column ) {
				$sql = self::column_sql( $definitions[ $column ] );

				// Where the schema puts it. Without this MySQL appends, and a
				// column added months after the table was made sits past
				// date_modified -- correct, and wrong to read.
				$after = self::column_after( $want, $live, $column );

				printf( "    missing: %s %s\n", $sql, $after );

				if ( ! $apply ) {
					continue;
				}

				$db->query( "ALTER TABLE `{$table}` ADD COLUMN {$sql} {$after}" );

				'' === $db->last_error
					? ++$added
					: printf( "      FAILED: %s\n", $db->last_error );
			}

			foreach ( $shuffled as $column ) {
				$sql   = self::column_sql( $definitions[ $column ] );
				$after = self::column_after( $want, $want, $column );

				printf( "    order:   %s %s\n", $column, $after );

				if ( ! $apply ) {
					continue;
				}

				$db->query( "ALTER TABLE `{$table}` MODIFY COLUMN {$sql} {$after}" );

				'' === $db->last_error
					? ++$moved
					: printf( "      FAILED: %s\n", $db->last_error );
			}

			foreach ( $extra as $column ) {
				printf( "    extra:   %s\n", $column );

				if ( ! $apply || ! $drop ) {
					continue;
				}

				// A rename looks exactly like this from here: the old name is
				// extra and the new one is missing. Dropping it would throw the
				// values away and the added column would sit at its default --
				// every total nought, and nothing to say where they went.
				//
				// A migration is what renames a column. This only removes one
				// that no longer holds anything.
				$rows = (int) $db->get_var( "SELECT COUNT(*) FROM `{$table}` WHERE `{$column}` IS NOT NULL AND `{$column}` != '' AND `{$column}` != 0" );

				if ( $rows > 0 && ! $force ) {
					printf( "      left alone: %d rows still have a value. If this is a rename, migrate it; --force to drop anyway.\n", $rows );
					continue;
				}

				$db->query( "ALTER TABLE `{$table}` DROP COLUMN `{$column}`" );

				'' === $db->last_error
					? ++$dropped
					: printf( "      FAILED: %s\n", $db->last_error );
			}
		}

		// The other way round: declared, and not there. BerlinDB creates a
		// table only when its version option is missing or behind, so one
		// dropped while its option stayed is never made again -- and every
		// query against it fails until somebody notices. Nothing here can
		// create it (that needs the plugin's own classes loaded); the
		// plugin's installer does, on activation or its hourly repair.
		$present = array_map( static fn( string $table ): string => substr( $table, strlen( $prefix ) ), $tables );
		$absent  = array_values( array_diff( array_keys( $files ), $present ) );

		if ( $absent ) {
			echo "\nTables the schema declares that are not there:\n";

			foreach ( $absent as $name ) {
				printf( "    %s\n", $name );
			}

			echo "      The plugin's installer creates them: reactivate it, or run its repair.\n";
		}

		if ( $orphans ) {
			echo "\nTables with no schema in the code:\n";

			foreach ( $orphans as $orphan ) {
				$name = $prefix . $orphan;
				$rows = (int) $db->get_var( "SELECT COUNT(*) FROM `{$name}`" );

				printf( "    %-26s %d rows\n", $orphan, $rows );

				if ( ! $apply || ! $drop ) {
					continue;
				}

				// An empty table left behind by a feature that went is
				// rubbish. One with rows in it is somebody's data, and no
				// schema in the code is exactly the state where nothing can
				// tell you what it was for.
				if ( $rows > 0 ) {
					echo "      left alone: it has rows, and nothing in the code can say what they are\n";
					continue;
				}

				$db->query( "DROP TABLE `{$name}`" );

				'' === $db->last_error
					? printf( "      dropped\n" )
					: printf( "      FAILED: %s\n", $db->last_error );
			}
		}

		printf( "\n%d of %d tables have drifted.\n", $drifted, count( $tables ) );

		if ( $apply ) {
			printf( "%d columns added, %d moved, %d dropped.\n", $added, $moved, $dropped );

			if ( ! $drop ) {
				echo "Extra columns were left alone. Pass --drop as well to remove them.\n";
			}
		} else {
			echo "\nNothing was changed. Pass --apply to add missing columns, and --drop as well to remove extra ones.\n";
		}

		return $drifted || $orphans || $absent ? 1 : 0;
	}
}
