<?php
/**
 * The generated files, byte for byte.
 *
 * @package SugarCommerce\Schema
 */

declare( strict_types=1 );

namespace SugarCommerce\Schema\Tests;

use SugarCommerce\Schema\Config;
use SugarCommerce\Schema\Generator;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * The Acme fixture against the files it must produce.
 *
 * tests/fixtures/acme/expected was written by the generator this replaced,
 * then passed through the WordPress coding standards' fixer. Three tables
 * reach every branch: a uuid, meta and no meta, unique and plain indexes,
 * insert defaults, every kind, a column that names its kind, one that names
 * its own callback, one with none, transitions on and off, a decimal with
 * and without a rate, a date that is not a datetime, and a hand-written row
 * trait. If one byte moves, a plugin regenerating gets a diff it did not ask
 * for.
 */
final class GoldenTest extends TestCase {

	/**
	 * Every expected file.
	 *
	 * @return array<string, array{0: string}>
	 */
	public static function expected(): array {
		$out = [];

		foreach ( self::files( self::fixture( 'acme/expected' ) ) as $file ) {
			$out[ $file ] = [ $file ];
		}

		return $out;
	}

	/**
	 * Each file comes out exactly as expected.
	 *
	 * @param string $file Relative path.
	 *
	 * @return void
	 */
	#[DataProvider( 'expected' )]
	public function test_the_file_matches( string $file ): void {
		$dir = $this->acme();

		( new Generator( Config::load( $dir ) ) )->generate();

		$this->assertFileExists( "{$dir}/{$file}" );
		$this->assertSame( file_get_contents( self::fixture( "acme/expected/{$file}" ) ), file_get_contents( "{$dir}/{$file}" ) );
	}

	/**
	 * Nothing is written that is not expected.
	 *
	 * @return void
	 */
	public function test_nothing_else_is_written(): void {
		$dir = $this->acme();

		( new Generator( Config::load( $dir ) ) )->generate();

		$want = array_values( array_unique( array_merge( self::files( self::fixture( 'acme/expected' ) ), self::files( self::fixture( 'acme/input' ) ) ) ) );
		sort( $want );

		$this->assertSame( $want, self::files( $dir ) );
	}

	/**
	 * Generating twice changes nothing, and check() then finds nothing.
	 *
	 * @return void
	 */
	public function test_regenerating_changes_nothing(): void {
		$dir = $this->acme();
		$gen = static fn() => new Generator( Config::load( $dir ) );

		$gen()->generate();

		$first = array_map( static fn( string $f ): string => (string) file_get_contents( "{$dir}/{$f}" ), self::files( $dir ) );

		$gen()->generate();

		$this->assertSame( $first, array_map( static fn( string $f ): string => (string) file_get_contents( "{$dir}/{$f}" ), self::files( $dir ) ) );
		$this->assertSame( [], $gen()->check() );
	}

	/**
	 * The summary counts what was written.
	 *
	 * @return void
	 */
	public function test_the_summary_counts(): void {
		$done = ( new Generator( Config::load( $this->acme() ) ) )->generate();

		$this->assertSame( 17, $done['files'] );
		$this->assertSame( 5, $done['tables'] );
		$this->assertSame( 2, $done['meta'] );
		$this->assertSame( 3, $done['components'] );
		$this->assertSame( 3, $done['functions'] );
		$this->assertSame( 45, $done['hooks'] );
	}

	/**
	 * A table keeps the version the lock gives it; its meta table keeps the
	 * initial one, since a meta table's shape never changes.
	 *
	 * @return void
	 */
	public function test_versions_come_from_the_lock(): void {
		$dir  = $this->acme();
		$lock = json_decode( (string) file_get_contents( self::fixture( 'acme/expected/bin/schema.lock.json' ) ), true );

		$lock['tickets']['version'] = 202612031;

		self::put( "{$dir}/bin/schema.lock.json", (string) json_encode( $lock ) );

		( new Generator( Config::load( $dir ) ) )->generate();

		$this->assertStringContainsString( "protected \$version = 202612031;\n", (string) file_get_contents( "{$dir}/src/Database/Tables/Tickets.php" ) );
		$this->assertStringContainsString( "protected \$version = 202611150;\n", (string) file_get_contents( "{$dir}/src/Database/Tables/TicketMeta.php" ) );
		$this->assertSame( 202612031, json_decode( (string) file_get_contents( "{$dir}/bin/schema.lock.json" ), true )['tickets']['version'] );
	}

	/**
	 * --only writes one table's files, and still the full registry and lock.
	 *
	 * @return void
	 */
	public function test_only_writes_the_named_tables(): void {
		$dir = $this->acme();

		( new Generator( Config::load( $dir ) ) )->generate( [ 'ticket_replies' ] );

		$this->assertFileExists( "{$dir}/src/Database/Schemas/TicketReplies.php" );
		$this->assertFileExists( "{$dir}/functions/tickets/ticket_replies.php" );
		$this->assertFileDoesNotExist( "{$dir}/src/Database/Schemas/Tickets.php" );
		$this->assertFileDoesNotExist( "{$dir}/functions/tickets/tickets.php" );
		$this->assertFileEquals( self::fixture( 'acme/expected/src/Database/Components/Core.php' ), "{$dir}/src/Database/Components/Core.php" );
		$this->assertFileEquals( self::fixture( 'acme/expected/bin/schema.lock.json' ), "{$dir}/bin/schema.lock.json" );
	}

	/**
	 * An unknown table in --only is refused.
	 *
	 * @return void
	 */
	public function test_only_refuses_an_unknown_table(): void {
		$this->expectException( \SugarCommerce\Schema\GeneratorException::class );
		$this->expectExceptionMessage( 'There is no table "nope"' );

		( new Generator( Config::load( $this->acme() ) ) )->generate( [ 'nope' ] );
	}

	/**
	 * Check reports a stale file, a hand edit and a missing one.
	 *
	 * @return void
	 */
	public function test_check_reports_drift(): void {
		$dir = $this->acme();

		( new Generator( Config::load( $dir ) ) )->generate();

		file_put_contents( "{$dir}/functions/tickets/tickets.php", "<?php\n// edited\n" );
		unlink( "{$dir}/src/Database/Rows/TicketReply.php" );

		$schema                                  = json_decode( (string) file_get_contents( "{$dir}/bin/schema.json" ), true );
		$schema['audit_entries']['columns'][]    = [
			'name'    => 'reason',
			'type'    => 'varchar',
			'length'  => 100,
			'default' => '',
		];
		file_put_contents( "{$dir}/bin/schema.json", json_encode( $schema ) );

		$drift = ( new Generator( Config::load( $dir ) ) )->check();

		$this->assertSame( 'differs', $drift['functions/tickets/tickets.php'] ?? null );
		$this->assertSame( 'missing', $drift['src/Database/Rows/TicketReply.php'] ?? null );
		$this->assertSame( 'differs', $drift['src/Database/Schemas/AuditEntries.php'] ?? null );
		$this->assertSame( 'differs', $drift['bin/schema.lock.json'] ?? null, 'A changed table changes its version.' );
		$this->assertArrayNotHasKey( 'src/Database/Schemas/Tickets.php', $drift );
	}

	/**
	 * A "$config" key in the schema file is settings, not a table.
	 *
	 * @return void
	 */
	public function test_a_config_key_in_the_schema_is_not_a_table(): void {
		$dir    = $this->acme();
		$schema = json_decode( (string) file_get_contents( "{$dir}/bin/schema.json" ), true );

		file_put_contents( "{$dir}/bin/schema.json", json_encode( [ '$config' => [ 'components' => [ 'class' => 'Bundled' ] ] ] + $schema ) );

		$generator = new Generator( Config::load( $dir ) );

		$this->assertSame( [ 'tickets', 'ticket_replies', 'audit_entries' ], array_keys( $generator->tables() ) );

		$generator->generate();

		$this->assertFileExists( "{$dir}/src/Database/Components/Bundled.php" );
		$this->assertStringContainsString( 'final class Bundled {', (string) file_get_contents( "{$dir}/src/Database/Components/Bundled.php" ) );
	}
}
