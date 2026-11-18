<?php
/**
 * Live tables against the Schema classes.
 *
 * @package SugarCommerce\Schema
 */

declare( strict_types=1 );

namespace SugarCommerce\Schema\Tests;

use SugarCommerce\Schema\Config;
use SugarCommerce\Schema\Generator;
use SugarCommerce\Schema\GeneratorException;
use SugarCommerce\Schema\Reconciler;

/**
 * What reconcile reports and what it changes.
 */
final class ReconcilerTest extends TestCase {

	/**
	 * A generated Acme plugin and a database to compare it with.
	 *
	 * Every declared table is there with its declared columns, unless
	 * $tables says otherwise: a list of columns replaces a table's, and
	 * null leaves it out.
	 *
	 * @param array<string, array<int, string>|null> $tables Live tables, unprefixed => columns.
	 * @param array<string, int>                     $rows   Answers to COUNT(*), by a fragment of the query.
	 *
	 * @return array{0: Reconciler, 1: object}
	 */
	private function reconciler( array $tables, array $rows = [] ): array {
		$dir = $this->acme();

		( new Generator( Config::load( $dir ) ) )->generate();

		foreach ( ( new Reconciler( Config::load( $dir ), new \stdClass() ) )->schema_files() as $name => $file ) {
			if ( ! array_key_exists( $name, $tables ) ) {
				$tables[ $name ] = array_keys( Reconciler::declared_definitions( (string) file_get_contents( $file ) ) );
			}
		}

		$tables = array_filter( $tables, static fn( ?array $columns ): bool => null !== $columns );

		$db = new class( $tables, $rows ) {
			/**
			 * Table prefix.
			 *
			 * @var string
			 */
			public string $prefix = 'wp_';

			/**
			 * The last error.
			 *
			 * @var string
			 */
			public string $last_error = '';

			/**
			 * Statements run.
			 *
			 * @var array<int, string>
			 */
			public array $ran = [];

			/**
			 * Constructor.
			 *
			 * @param array<string, array<int, string>> $tables Tables.
			 * @param array<string, int>                $rows   Counts.
			 */
			public function __construct( private array $tables, private array $rows ) {
			}

			/**
			 * Escape for LIKE.
			 *
			 * @param string $text Text.
			 *
			 * @return string
			 */
			public function esc_like( string $text ): string {
				return addcslashes( $text, '_%\\' );
			}

			/**
			 * Prepare.
			 *
			 * @param string $query Query.
			 * @param mixed  ...$args Values.
			 *
			 * @return string
			 */
			public function prepare( string $query, ...$args ): string {
				return vsprintf( str_replace( '%s', "'%s'", $query ), $args );
			}

			/**
			 * A column of results.
			 *
			 * @param string $query Query.
			 *
			 * @return array<int, string>
			 */
			public function get_col( string $query ): array {
				if ( str_starts_with( $query, 'SHOW TABLES LIKE' ) ) {
					return array_map( static fn( string $t ): string => 'wp_acme_' . $t, array_keys( $this->tables ) );
				}

				preg_match( '/SHOW COLUMNS FROM `wp_acme_([a-z_]+)`/', $query, $m );

				return $this->tables[ $m[1] ];
			}

			/**
			 * One value.
			 *
			 * @param string $query Query.
			 *
			 * @return int
			 */
			public function get_var( string $query ): int {
				foreach ( $this->rows as $fragment => $count ) {
					if ( str_contains( $query, $fragment ) ) {
						return $count;
					}
				}

				return 0;
			}

			/**
			 * Run a statement.
			 *
			 * @param string $query Query.
			 *
			 * @return int
			 */
			public function query( string $query ): int {
				$this->ran[] = preg_replace( '/\s+/', ' ', trim( $query ) );

				return 1;
			}
		};

		return [ new Reconciler( Config::load( $dir ), $db ), $db ];
	}

	/**
	 * The live columns of the audit_entries table as declared.
	 *
	 * @return array<int, string>
	 */
	private static function audit_columns(): array {
		return [ 'id', 'object_type', 'object_id', 'total', 'date_seen', 'date_created', 'date_modified' ];
	}

	/**
	 * Run, capturing what is printed.
	 *
	 * @param Reconciler $reconciler The reconciler.
	 * @param bool       $apply      --apply.
	 * @param bool       $drop       --drop.
	 * @param bool       $force      --force.
	 *
	 * @return array{0: int, 1: string}
	 */
	private static function reconcile_run( Reconciler $reconciler, bool $apply = false, bool $drop = false, bool $force = false ): array {
		ob_start();
		$code = $reconciler->run( $apply, $drop, $force );

		return [ $code, (string) ob_get_clean() ];
	}

	/**
	 * Columns are read from a Schema file; index blocks are not columns.
	 *
	 * @return void
	 */
	public function test_columns_are_read_from_the_schema_file(): void {
		$columns = Reconciler::declared_definitions( (string) file_get_contents( self::fixture( 'acme/expected/src/Database/Schemas/Tickets.php' ) ) );

		$this->assertSame( 'id', array_key_first( $columns ) );
		$this->assertSame( 'date_modified', array_key_last( $columns ) );
		$this->assertArrayNotHasKey( 'primary', $columns );
		$this->assertArrayNotHasKey( 'status_date_modified', $columns, 'An index naming a column is not a column.' );
		$this->assertSame( '4', $columns['tax_rate']['scale'] );
		$this->assertSame( 'true', $columns['date_closed']['allow_null'] );
	}

	/**
	 * Where a column goes.
	 *
	 * @return void
	 */
	public function test_the_after_clause(): void {
		$want = [ 'id', 'a', 'b', 'c' ];

		$this->assertSame( 'AFTER `a`', Reconciler::column_after( $want, [ 'id', 'a', 'c' ], 'b' ) );
		$this->assertSame( 'AFTER `id`', Reconciler::column_after( $want, [ 'id', 'c' ], 'b' ), 'Walks back past a column that is also missing.' );
		$this->assertSame( 'FIRST', Reconciler::column_after( $want, [ 'b' ], 'id' ) );
		$this->assertSame( '', Reconciler::column_after( $want, $want, 'zzz' ) );
	}

	/**
	 * A column's DDL.
	 *
	 * @return void
	 */
	public function test_the_column_sql(): void {
		$this->assertSame( '`tax_rate` decimal(10,4) NOT NULL DEFAULT \'0\'', Reconciler::column_sql( [ 'name' => 'tax_rate', 'type' => 'decimal', 'length' => '10', 'scale' => '4', 'default' => '0' ] ) );
		$this->assertSame( '`n` bigint(20) unsigned NOT NULL DEFAULT \'0\'', Reconciler::column_sql( [ 'name' => 'n', 'type' => 'bigint', 'length' => '20', 'unsigned' => 'true', 'default' => '0' ] ) );
		$this->assertSame( '`d` datetime NOT NULL DEFAULT \'0000-00-00 00:00:00\'', Reconciler::column_sql( [ 'name' => 'd', 'type' => 'datetime', 'default' => '' ] ) );
		$this->assertSame( '`d` datetime NULL DEFAULT NULL', Reconciler::column_sql( [ 'name' => 'd', 'type' => 'datetime', 'allow_null' => 'true' ] ) );
		$this->assertSame( '`b` longtext NOT NULL', Reconciler::column_sql( [ 'name' => 'b', 'type' => 'longtext', 'default' => 'x' ] ) );
	}

	/**
	 * A meta table's name finds its class on any filesystem.
	 *
	 * @return void
	 */
	public function test_meta_tables_map_to_their_classes(): void {
		[ $reconciler ] = $this->reconciler( [] );

		$files = $reconciler->schema_files();

		$this->assertStringEndsWith( '/src/Database/Schemas/TicketMeta.php', $files['ticketmeta'] );
		$this->assertStringEndsWith( '/src/Database/Schemas/AuditEntryMeta.php', $files['audit_entrymeta'] );
		$this->assertStringEndsWith( '/src/Database/Schemas/TicketReplies.php', $files['ticket_replies'] );
	}

	/**
	 * Matching tables report nothing and exit 0.
	 *
	 * @return void
	 */
	public function test_nothing_drifted(): void {
		[ $reconciler, $db ] = $this->reconciler( [ 'audit_entries' => self::audit_columns() ] );

		[ $code, $out ] = self::reconcile_run( $reconciler );

		$this->assertSame( 0, $code );
		$this->assertStringContainsString( '0 of 5 tables have drifted.', $out );
		$this->assertStringNotContainsString( 'not there', $out );
		$this->assertSame( [], $db->ran );
	}

	/**
	 * Missing, extra and misplaced columns are reported, and nothing changes.
	 *
	 * @return void
	 */
	public function test_drift_is_reported(): void {
		[ $reconciler, $db ] = $this->reconciler(
			[
				'audit_entries' => [ 'id', 'object_type', 'total', 'old_note', 'date_seen', 'date_created', 'date_modified' ],
				'tickets_old'   => [ 'id' ],
			],
			[ '`wp_acme_tickets_old`' => 3 ]
		);

		[ $code, $out ] = self::reconcile_run( $reconciler );

		$this->assertSame( 1, $code );
		$this->assertStringContainsString( "audit_entries\n    missing: `object_id` bigint(20) unsigned NOT NULL DEFAULT '0' AFTER `object_type`\n    extra:   old_note\n", $out );
		$this->assertStringContainsString( "Tables with no schema in the code:\n    tickets_old                3 rows\n", $out );
		$this->assertStringContainsString( 'Nothing was changed.', $out );
		$this->assertSame( [], $db->ran );
	}

	/**
	 * A declared table that is not there is reported, and fails the run.
	 *
	 * @return void
	 */
	public function test_a_missing_table_is_reported(): void {
		[ $reconciler, $db ] = $this->reconciler( [ 'ticketmeta' => null ] );

		[ $code, $out ] = self::reconcile_run( $reconciler, true, true, true );

		$this->assertSame( 1, $code );
		$this->assertStringContainsString( "Tables the schema declares that are not there:\n    ticketmeta\n", $out );
		$this->assertStringContainsString( '0 of 4 tables have drifted.', $out );
		$this->assertSame( [], $db->ran, 'Nothing is created or dropped for it.' );
	}

	/**
	 * --apply adds and reorders, and leaves extra columns alone.
	 *
	 * @return void
	 */
	public function test_apply_adds_and_reorders(): void {
		[ $reconciler, $db ] = $this->reconciler(
			[
				'audit_entries' => [ 'id', 'object_type', 'total', 'old_note', 'date_seen', 'date_created', 'date_modified' ],
				'ticket_replies' => [ 'id', 'content', 'ticket_id', 'status', 'token', 'user_id', 'date_created', 'date_modified' ],
			]
		);

		[ , $out ] = self::reconcile_run( $reconciler, true );

		$this->assertContains( "ALTER TABLE `wp_acme_audit_entries` ADD COLUMN `object_id` bigint(20) unsigned NOT NULL DEFAULT '0' AFTER `object_type`", $db->ran );
		$this->assertContains( "ALTER TABLE `wp_acme_ticket_replies` MODIFY COLUMN `ticket_id` bigint(20) unsigned NOT NULL DEFAULT '0' AFTER `id`", $db->ran );
		$this->assertCount( 0, array_filter( $db->ran, static fn( string $q ): bool => str_contains( $q, 'DROP' ) ) );
		$this->assertStringContainsString( 'Extra columns were left alone.', $out );
	}

	/**
	 * --drop removes an empty extra column, not one with values.
	 *
	 * @return void
	 */
	public function test_drop_spares_columns_with_values(): void {
		$live = [ 'id', 'object_type', 'object_id', 'total', 'empty_one', 'full_one', 'date_seen', 'date_created', 'date_modified' ];

		[ $reconciler, $db ] = $this->reconciler( [ 'audit_entries' => $live ], [ '`full_one` IS NOT NULL' => 4 ] );

		[ , $out ] = self::reconcile_run( $reconciler, true, true );

		$this->assertContains( 'ALTER TABLE `wp_acme_audit_entries` DROP COLUMN `empty_one`', $db->ran );
		$this->assertNotContains( 'ALTER TABLE `wp_acme_audit_entries` DROP COLUMN `full_one`', $db->ran );
		$this->assertStringContainsString( 'left alone: 4 rows still have a value.', $out );

		[ $reconciler, $db ] = $this->reconciler( [ 'audit_entries' => $live ], [ '`full_one` IS NOT NULL' => 4 ] );

		self::reconcile_run( $reconciler, true, true, true );

		$this->assertContains( 'ALTER TABLE `wp_acme_audit_entries` DROP COLUMN `full_one`', $db->ran, '--force drops it anyway.' );
	}

	/**
	 * --drop removes an empty orphan table, never one with rows.
	 *
	 * @return void
	 */
	public function test_drop_spares_orphans_with_rows(): void {
		[ $reconciler, $db ] = $this->reconciler(
			[
				'gone_empty' => [ 'id' ],
				'gone_full'  => [ 'id' ],
			],
			[ '`wp_acme_gone_full`' => 2 ]
		);

		self::reconcile_run( $reconciler, true, true, true );

		$this->assertContains( 'DROP TABLE `wp_acme_gone_empty`', $db->ran );
		$this->assertNotContains( 'DROP TABLE `wp_acme_gone_full`', $db->ran, 'Not even with --force.' );
	}

	/**
	 * A meta table's own key is not "extra", even when its Schema class --
	 * written by hand -- leaves it to BerlinDB.
	 *
	 * @return void
	 */
	public function test_meta_id_is_not_extra(): void {
		[ $reconciler ] = $this->reconciler( [ 'ticketmeta' => [ 'meta_id', 'acme_ticket_id', 'meta_key', 'meta_value' ] ] );

		[ $code ] = self::reconcile_run( $reconciler );

		$this->assertSame( 0, $code );

		$file = $reconciler->schema_files()['ticketmeta'];

		file_put_contents( $file, (string) preg_replace( "/\t\tarray\(\n\t\t\t'name'\s+=> 'meta_id',.*?\n\t\t\),\n/s", '', (string) file_get_contents( $file ), 1 ) );

		$this->assertArrayNotHasKey( 'meta_id', Reconciler::declared_definitions( (string) file_get_contents( $file ) ) );

		[ $code, $out ] = self::reconcile_run( $reconciler );

		$this->assertSame( 0, $code, $out );
	}

	/**
	 * WordPress is found above the plugin, or where it is said to be.
	 *
	 * @return void
	 */
	public function test_wordpress_is_found(): void {
		$site = $this->folder();

		self::put( "{$site}/wp-load.php", '<?php' );
		mkdir( "{$site}/wp-content/plugins/acme", 0777, true );

		$this->assertSame( realpath( $site ) . '/wp-load.php', Reconciler::find_wordpress( "{$site}/wp-content/plugins/acme" ) );
		$this->assertSame( "{$site}/wp-load.php", Reconciler::find_wordpress( '/nowhere', $site ) );

		$this->expectException( GeneratorException::class );

		Reconciler::find_wordpress( $this->folder(), $this->folder() );
	}
}
