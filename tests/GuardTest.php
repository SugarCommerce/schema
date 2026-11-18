<?php
/**
 * Not overwriting what somebody wrote by hand.
 *
 * @package SugarCommerce\Schema
 */

declare( strict_types=1 );

namespace SugarCommerce\Schema\Tests;

use SugarCommerce\Schema\Config;
use SugarCommerce\Schema\Generator;
use SugarCommerce\Schema\GeneratorException;

/**
 * Migrations, hand-written functions, old copies -- and stopping before
 * anything is written.
 */
final class GuardTest extends TestCase {

	/**
	 * A generated Acme plugin.
	 *
	 * @return string
	 */
	private function generated(): string {
		$dir = $this->acme();

		( new Generator( Config::load( $dir ) ) )->generate();

		return $dir;
	}

	/**
	 * Run the generator, expecting it to stop.
	 *
	 * @param string $dir     The plugin.
	 * @param string $message What it must say.
	 *
	 * @return void
	 */
	private function assertRefused( string $dir, string $message ): void {
		try {
			( new Generator( Config::load( $dir ) ) )->generate();
			$this->fail( 'The generator did not stop.' );
		} catch ( GeneratorException $e ) {
			$this->assertStringContainsString( $message, $e->getMessage() );
		}
	}

	/**
	 * A table class with an upgrade routine is not overwritten.
	 *
	 * @return void
	 */
	public function test_a_migration_stops_the_run(): void {
		$dir   = $this->generated();
		$table = "{$dir}/src/Database/Tables/Tickets.php";

		file_put_contents( $table, str_replace( "\tprotected \$upgrades = array();\n", "\tprotected \$upgrades = array();\n\n\tprotected function __202612010() {\n\t\treturn true;\n\t}\n", (string) file_get_contents( $table ) ) );

		$this->assertRefused( $dir, "src/Database/Tables/Tickets.php carries 1 schema migration(s) this tool does not generate:\n\n      __202612010()\n" );
	}

	/**
	 * A migration-shaped method outside Tables/ is not a migration.
	 *
	 * @return void
	 */
	public function test_only_table_classes_are_checked_for_migrations(): void {
		$dir   = $this->generated();
		$query = "{$dir}/src/Database/Queries/Ticket.php";

		file_put_contents( $query, (string) file_get_contents( $query ) . "\nfunction __202612010() {}\n" );

		( new Generator( Config::load( $dir ) ) )->generate();

		$this->assertFileEquals( self::fixture( 'acme/expected/src/Database/Queries/Ticket.php' ), $query );
	}

	/**
	 * A functions file somebody added to is not overwritten.
	 *
	 * @return void
	 */
	public function test_a_handwritten_function_stops_the_run(): void {
		$dir  = $this->generated();
		$file = "{$dir}/functions/tickets/tickets.php";

		file_put_contents( $file, (string) file_get_contents( $file ) . "\nfunction acme_ticket_is_late( int \$id ): bool {\n\treturn false;\n}\n" );

		$this->assertRefused( $dir, "functions/tickets/tickets.php defines 1 function(s) this tool does not generate:\n\n      acme_ticket_is_late()\n" );
	}

	/**
	 * Stopping writes nothing at all, not even the files before the bad one.
	 *
	 * @return void
	 */
	public function test_a_refused_run_writes_nothing(): void {
		$dir  = $this->generated();
		$file = "{$dir}/functions/tickets/tickets.php";

		file_put_contents( $file, (string) file_get_contents( $file ) . "\nfunction acme_extra() {}\n" );

		$schema                                = json_decode( (string) file_get_contents( "{$dir}/bin/schema.json" ), true );
		$schema['audit_entries']['columns'][0]['length'] = 40;
		file_put_contents( "{$dir}/bin/schema.json", json_encode( $schema ) );

		$before = [
			'lock'   => file_get_contents( "{$dir}/bin/schema.lock.json" ),
			'schema' => file_get_contents( "{$dir}/src/Database/Schemas/AuditEntries.php" ),
		];

		$this->assertRefused( $dir, 'acme_extra()' );

		$this->assertSame( $before['lock'], file_get_contents( "{$dir}/bin/schema.lock.json" ) );
		$this->assertSame( $before['schema'], file_get_contents( "{$dir}/src/Database/Schemas/AuditEntries.php" ) );
	}

	/**
	 * An old copy of a table's functions in another folder is removed.
	 *
	 * @return void
	 */
	public function test_an_old_copy_is_removed(): void {
		$dir = $this->generated();

		self::put( "{$dir}/functions/tickets.php", (string) file_get_contents( "{$dir}/functions/tickets/tickets.php" ) );
		self::put( "{$dir}/functions/legacy/tickets.php", "<?php\nfunction acme_get_ticket() {}\n" );

		$done = ( new Generator( Config::load( $dir ) ) )->generate();

		$this->assertSame( [ 'functions/legacy/tickets.php', 'functions/tickets.php' ], $done['removed'] );
		$this->assertFileDoesNotExist( "{$dir}/functions/tickets.php" );
		$this->assertFileDoesNotExist( "{$dir}/functions/legacy/tickets.php" );
		$this->assertFileExists( "{$dir}/functions/tickets/tickets.php" );
	}

	/**
	 * An old copy with functions of its own stops the run.
	 *
	 * @return void
	 */
	public function test_an_old_copy_with_its_own_functions_stops_the_run(): void {
		$dir = $this->generated();

		self::put( "{$dir}/functions/tickets.php", "<?php\nfunction acme_get_ticket() {}\nfunction acme_ticket_extra() {}\n" );

		$this->assertRefused( $dir, "functions/tickets.php is an old copy of functions/tickets/tickets.php with functions of its own in it.\n" );
		$this->assertFileExists( "{$dir}/functions/tickets.php" );
	}

	/**
	 * A hand-written file not named after a table is never touched.
	 *
	 * @return void
	 */
	public function test_other_files_are_left_alone(): void {
		$dir = $this->generated();

		self::put( "{$dir}/functions/tickets/ticket-details.php", "<?php\nfunction acme_ticket_is_late() {}\n" );

		( new Generator( Config::load( $dir ) ) )->generate();

		$this->assertSame( "<?php\nfunction acme_ticket_is_late() {}\n", file_get_contents( "{$dir}/functions/tickets/ticket-details.php" ) );
	}

	/**
	 * A column named like one of BerlinDB's query arguments is refused,
	 * before anything is written.
	 *
	 * @return void
	 */
	public function test_a_column_named_like_a_query_argument_stops_the_run(): void {
		foreach ( [ 'number', 'order', 'count', 'fields' ] as $name ) {
			$dir = $this->plugin(
				[
					'orders' => [
						'singular' => 'order',
						'columns'  => [
							[ 'name' => 'title', 'type' => 'varchar', 'length' => 20 ],
							[ 'name' => $name, 'type' => 'varchar', 'length' => 20 ],
						],
					],
				]
			);

			try {
				( new Generator( Config::load( $dir ) ) )->generate();
				$this->fail( "A column called {$name} was accepted." );
			} catch ( GeneratorException $e ) {
				$this->assertStringContainsString( "a column cannot be called \"{$name}\"", $e->getMessage() );
			}

			$this->assertFileDoesNotExist( "{$dir}/src/Database/Tables/Orders.php" );
		}
	}

	/**
	 * A name that only contains one of them is fine.
	 *
	 * @return void
	 */
	public function test_a_column_containing_a_reserved_word_is_allowed(): void {
		$dir = $this->plugin(
			[
				'orders' => [
					'singular' => 'order',
					'columns'  => [
						[ 'name' => 'order_number', 'type' => 'varchar', 'length' => 20 ],
						[ 'name' => 'sort_order', 'type' => 'int', 'length' => 10 ],
					],
				],
			]
		);

		( new Generator( Config::load( $dir ) ) )->generate();

		$this->assertFileExists( "{$dir}/src/Database/Tables/Orders.php" );
	}
}
