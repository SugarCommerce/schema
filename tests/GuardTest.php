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

	/**
	 * A name that is not a plain identifier is refused before anything is
	 * written: each one is written into PHP and SQL as it stands, so a quote
	 * in one would end the string around it, and a folder of ".." would
	 * write outside the plugin.
	 *
	 * @return void
	 */
	public function test_names_that_would_escape_the_code_stop_the_run(): void {
		$column = [ 'name' => 'title', 'type' => 'varchar', 'length' => 20 ];
		$bad    = [
			'table'           => [ "orders'", [ 'singular' => 'order' ] ],
			'singular'        => [ 'orders', [ 'singular' => 'order\'' ] ],
			'alias'           => [ 'orders', [ 'singular' => 'order', 'alias' => "o'" ] ],
			'functions_dir'   => [ 'orders', [ 'singular' => 'order', 'functions_dir' => '../../outside' ] ],
			'column'          => [ 'orders', [ 'singular' => 'order', 'columns' => [ [ 'name' => "x', 'type' => 'int" ] + $column ] ] ],
			'type'            => [ 'orders', [ 'singular' => 'order', 'columns' => [ [ 'type' => 'varchar(20); DROP' ] + $column ] ] ],
			'length'          => [ 'orders', [ 'singular' => 'order', 'columns' => [ [ 'length' => "20'" ] + $column ] ] ],
			'default_function' => [ 'orders', [ 'singular' => 'order', 'columns' => [ [ 'default_function' => 'f(); exit' ] + $column ] ] ],
			'index'           => [ 'orders', [ 'singular' => 'order', 'indexes' => [ [ "title'" ] ] ] ],
			'index prefix'    => [ 'orders', [ 'singular' => 'order', 'indexes' => [ [ 'title(191); DROP' ] ] ] ],
			'index zero'      => [ 'orders', [ 'singular' => 'order', 'indexes' => [ [ 'title(0)' ] ] ] ],
		];

		foreach ( $bad as $what => [ $plural, $table ] ) {
			$dir = $this->plugin( [ $plural => $table + [ 'columns' => [ $column ] ] ] );

			try {
				( new Generator( Config::load( $dir ) ) )->generate();
				$this->fail( "A bad {$what} was accepted." );
			} catch ( GeneratorException $e ) {
				$this->assertStringContainsString( 'is not', $e->getMessage(), $what );
			}

			$this->assertSame( [ 'bin/schema.json', 'composer.json' ], self::files( $dir ), "Nothing is written for a bad {$what}." );
		}
	}

	/**
	 * A long text column indexed by its first characters: the length goes
	 * to BerlinDB with the column, and the index is named after the column.
	 *
	 * @return void
	 */
	public function test_an_index_can_take_a_prefix_length(): void {
		$dir = $this->plugin(
			[
				'files' => [
					'singular' => 'file',
					'columns'  => [ [ 'name' => 'path', 'type' => 'varchar', 'length' => 1000, 'default' => '' ] ],
					'indexes'  => [ [ 'path(191)' ] ],
				],
			]
		);

		( new Generator( Config::load( $dir ) ) )->generate();

		$code = (string) file_get_contents( "{$dir}/src/Database/Schemas/Files.php" );

		$this->assertMatchesRegularExpression( "/'name'\s*=>\s*'path',\s*'columns'\s*=>\s*array\( 'path\(191\)' \)/", $code );
	}

	/**
	 * A default with a quote or a backslash in it is written as a PHP
	 * string that says exactly that, and a "why" cannot end the comment it
	 * is written into.
	 *
	 * @return void
	 */
	public function test_free_text_stays_inside_its_string_and_comment(): void {
		$dir = $this->plugin(
			[
				'notes' => [
					'singular' => 'note',
					'why'      => 'Ends here */ echo 1; /* or not.',
					'columns'  => [
						[ 'name' => 'title', 'type' => 'varchar', 'length' => 40, 'default' => "it's a \\ back" ],
					],
				],
			]
		);

		( new Generator( Config::load( $dir ) ) )->generate();

		foreach ( [ 'Schemas/Notes.php', 'Tables/Notes.php' ] as $file ) {
			$code   = (string) file_get_contents( "{$dir}/src/Database/{$file}" );
			$tokens = array_filter( token_get_all( $code ), 'is_array' );
			$echoes = array_filter( $tokens, static fn( array $t ): bool => T_ECHO === $t[0] );

			$this->assertSame( [], $echoes, "{$file}: the why stays a comment." );
		}

		$code    = (string) file_get_contents( "{$dir}/src/Database/Schemas/Notes.php" );
		$strings = [];

		foreach ( token_get_all( $code ) as $token ) {
			if ( is_array( $token ) && T_CONSTANT_ENCAPSED_STRING === $token[0] ) {
				$strings[] = $token[1];
			}
		}

		$this->assertContains( var_export( "it's a \\ back", true ), $strings, 'The default reads back as written.' );
	}
}
